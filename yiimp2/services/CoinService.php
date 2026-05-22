<?php

namespace app\services;

use Yii;
use app\models\Coins;
use app\components\rpc\WalletRPC;

/**
 * CoinService — wallet connectivity checks, reward/difficulty updates, GitHub version tracking.
 *
 * Ported from:
 *   web/yaamp/core/backend/coins.php → updateCoinStats, updateVersionFromGithub
 *
 * External utility methods used (already in Yii2 components):
 *   Yii::$app->YiimpUtils->get_algos()       ← YIIMP_get_algos()
 *   Yii::$app->YiimpUtils->pool_rate($algo)  ← YIIMP_pool_rate()
 *   Yii::$app->YiimpUtils->coin_nethash($c)  ← YIIMP_coin_nethash()
 *   Yii::$app->ConversionUtils->decode_compact()  ← decode_compact()
 *   Yii::$app->ConversionUtils->target_to_diff()  ← target_to_diff()
 */
class CoinService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Poll every installed coin's wallet daemon: update difficulty, block height,
     * reward, connection count, master-wallet address, rpc-encoding detection,
     * and profitability index fields.
     * Ports: BackendCoinsUpdate()
     */
    public function updateCoinStats(): void
    {
        $t1       = microtime(true);
        $poolRate = [];

        foreach (Yii::$app->YiimpUtils->get_algos() as $algo) {
            $poolRate[$algo] = Yii::$app->YiimpUtils->pool_rate($algo);
        }

        // --- Phase 1: per-coin RPC queries -----------------------------------
        $coins = Coins::find()->where(['installed' => 1])->all();

        foreach ($coins as $coin) {
            $remote = new WalletRPC($coin);
            $info   = $remote->getinfo();

            if (!$info && $coin->enable) {
                Yii::info("{$coin->symbol} no getinfo, retrying…", __CLASS__);
                sleep(3);
                $info = $remote->getinfo();
                if (!$info) {
                    Yii::info("{$coin->symbol} disabled after 2 failed attempts. {$remote->error}", __CLASS__);
                    $coin->enable      = false;
                    $coin->connections = 0;
                    $coin->save();
                    continue;
                }
            }

            if ($coin->auto_ready && !empty($info)) {
                $coin->enable = true;
            } elseif (empty($info)) {
                continue;
            }

            // Difficulty
            $difficulty = $info['difficulty'] ?? $remote->getdifficulty();
            if (is_array($difficulty)) {
                $coin->difficulty     = $difficulty['proof-of-work'] ?? null;
                $coin->difficulty_pos = $difficulty['proof-of-stake'] ?? null;
            } else {
                $coin->difficulty = $difficulty;
            }
            if ($coin->algo === 'quark') {
                $coin->difficulty /= 0x100;
            }
            if (!$coin->difficulty) {
                $coin->difficulty = 1;
            }

            $coin->errors     = $info['errors'] ?? '';
            if (str_contains((string) $coin->errors, 'check your network connection')) {
                $coin->errors = '';
            }
            $coin->txfee      = $info['paytxfee'] ?? '';
            $coin->connections = $info['connections'] ?? '';
            $coin->multialgos = (int) isset($info['pow_algo_id']);
            $coin->balance    = $info['balance'] ?? 0;
            $coin->stake      = $info['stake'] ?? $coin->stake;
            $coin->mint       = (float) Yii::$app->db->createCommand(
                "SELECT SUM(amount) FROM blocks WHERE coin_id=:id AND category='immature'",
                [':id' => $coin->id]
            )->queryScalar();

            // Detect master wallet address on first run
            if (is_null($coin->master_wallet)) {
                if ($coin->rpcencoding === 'DCR' && empty($coin->account)) {
                    $coin->account = 'default';
                }
                $coin->master_wallet = $remote->getaccountaddress($coin->account);
            }

            // Auto-detect rpcencoding
            if (empty($coin->rpcencoding)) {
                $coin->rpcencoding = $this->detectRpcEncoding($coin, $remote);
            }

            // Detect submitblock support
            if (is_null($coin->hassubmitblock)) {
                $remote->submitblock('');
                $coin->hassubmitblock = strcasecmp((string) $remote->error, 'method not found') !== 0;
            }

            // Detect AuxPoW support
            if (is_null($coin->auxpow)) {
                $remote->getauxblock();
                $coin->auxpow = strcasecmp((string) $remote->error, 'method not found') !== 0;
            }

            // Block template / reward
            $template = $this->getBlockTemplate($coin, $remote);

            if ($template && isset($template['coinbasevalue'])) {
                $coin->reward = $template['coinbasevalue'] / 1e8 * $coin->reward_mul;
                $this->applyRewardDeductions($coin, $template);

                if (isset($template['bits'])) {
                    $target = Yii::$app->ConversionUtils->decode_compact($template['bits']);
                    $coin->difficulty = Yii::$app->ConversionUtils->target_to_diff($target);
                }

            } elseif (in_array($coin->rpcencoding, ['GETH', 'NIRO'], true)) {
                $coin->auto_ready = ($coin->connections > 0);

            } elseif (strcasecmp((string) $remote->error, 'method not found') === 0) {
                $template = $remote->getmemorypool();
                if ($template && isset($template['coinbasevalue'])) {
                    $coin->usememorypool = true;
                    $coin->reward        = $template['coinbasevalue'] / 1e8 * $coin->reward_mul;
                    if (isset($template['bits'])) {
                        $target = Yii::$app->ConversionUtils->decode_compact($template['bits']);
                        $coin->difficulty = Yii::$app->ConversionUtils->target_to_diff($target);
                    }
                } else {
                    $coin->auto_ready = false;
                    $coin->errors     = $remote->error;
                }

            } elseif ($coin->symbol === 'ZEC' || $coin->rpcencoding === 'ZEC') {
                $this->applyZecReward($coin, $remote, $template);

            } elseif ($coin->rpcencoding === 'DCR') {
                $wi = $remote->walletinfo();
                $coin->auto_ready = ($coin->connections > 0 && ($wi['daemonconnected'] ?? false));
                if ($coin->auto_ready && !($wi['unlocked'] ?? false)) {
                    Yii::warning("{$coin->symbol} wallet is not unlocked!", __CLASS__);
                }

            } else {
                $coin->auto_ready = false;
                $coin->errors     = $remote->error;
            }

            if (strcasecmp((string) $coin->errors, 'No more PoW blocks') === 0) {
                $coin->dontsell   = true;
                $coin->auto_ready = false;
            }

            // Block height + TTF
            if (isset($info['blocks'])) {
                if ($coin->block_height != $info['blocks']) {
                    $count = $info['blocks'] - $coin->block_height;
                    $ttf   = $count > 0 ? (time() - (int) $coin->last_network_found) / $count : 0;
                    if (is_null($coin->actual_ttf)) {
                        $coin->actual_ttf = $ttf;
                    }
                    $coin->actual_ttf       = $this->percentFeedback((float) $coin->actual_ttf, $ttf, 5);
                    $coin->last_network_found = time();
                }
                $coin->block_height = $info['blocks'];
            } else {
                Yii::info("{$coin->symbol} wallet is missing blocks in info-array", __CLASS__);
            }

            $coin->version = substr((string) ($info['version'] ?? ''), 0, 32);

            if ($coin->powend_height > 0 && $coin->block_height > $coin->powend_height && $coin->auto_ready) {
                $coin->auto_ready = false;
                $coin->errors     = 'PoW end reached';
            }

            $coin->save();

            // Refresh pool balances if wallet is ahead of DB
            if ($coin->available < 0 || $coin->cleared > $coin->balance) {
                (new BlockService())->updatePoolBalances($coin->id);
            }
        }

        // --- Phase 2: profitability index ------------------------------------
        $coins = Coins::find()->where(['enable' => 1])->orderBy(['auxpow' => SORT_DESC])->all();

        foreach ($coins as $coin) {
            $coin = Coins::findOne($coin->id);
            if (!$coin) {
                continue;
            }

            if ($coin->difficulty) {
                $coin->network_hash = Yii::$app->YiimpUtils->coin_nethash($coin);
                $coin->index_avg    = $coin->reward * $coin->price * 10000 / $coin->difficulty;

                if (!$coin->auxpow && $coin->rpcencoding === 'POW') {
                    $indexAux        = (float) Yii::$app->db->createCommand(
                        "SELECT SUM(index_avg) FROM coins WHERE enable AND visible AND auto_ready AND auxpow AND algo=:algo",
                        [':algo' => $coin->algo]
                    )->queryScalar();
                    $coin->index_avg += $indexAux;
                }
            }

            if ($coin->network_hash) {
                $coin->network_ttf = (int) min($coin->difficulty * 0x100000000 / $coin->network_hash, 2147483647);
            }

            if (isset($poolRate[$coin->algo]) && $poolRate[$coin->algo]) {
                $coin->pool_ttf = (int) min(
                    Yii::$app->YiimpUtils->coin_nethash($coin) / $poolRate[$coin->algo] * ((float) ($coin->actual_ttf ?? 0)),
                    2147483647
                );
            }

            // Download remote coin image once and store locally
            if (str_contains((string) $coin->image, 'http')) {
                $data        = @file_get_contents($coin->image);
                $localPath   = "/images/coin-{$coin->id}.png";
                $coin->image = $localPath;
                $htdocs      = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : '';
                if ($data && $htdocs) {
                    @unlink($htdocs . $localPath);
                    file_put_contents($htdocs . $localPath, $data);
                }
            }

            $coin->save();
        }

        $elapsed = microtime(true) - $t1;
        Yii::debug(sprintf('%s took %.3fs', __METHOD__, $elapsed), 'performance');
    }

    /**
     * Check GitHub for newer wallet releases and send an email summary if found.
     * Ports: BackendCoinsVersionUpdate()
     */
    public function updateVersionFromGithub(string $algo = ''): void
    {
        $coins = $algo === ''
            ? Coins::find()->where(['installed' => 1])->orderBy('algo')->all()
            : Coins::find()->where(['installed' => 1, 'algo' => $algo])->all();

        $currentAlgo = '';
        $algoText    = '';
        $mailText    = '';

        foreach ($coins as $coin) {
            if ($currentAlgo !== $coin->algo) {
                if ($algoText !== '') {
                    $mailText .= "#### Algo: {$currentAlgo} ####\n{$algoText}";
                }
                $currentAlgo = $coin->algo;
                $algoText    = '';
            }

            $prefix = "{$coin->name}({$coin->symbol}) ID:{$coin->id} algo:{$coin->algo}";

            if (empty($coin->link_github) || !str_contains((string) $coin->link_github, 'github')) {
                $algoText .= "{$prefix} : missing github-repo\n";
                if ($coin->version_installed !== $coin->version_github) {
                    $algoText .= "{$prefix} : installed ({$coin->version_installed}) differs from release ({$coin->version_github})\n";
                }
                continue;
            }

            $apiUrl = str_replace('https://github.com/', 'https://api.github.com/repos/', $coin->link_github) . '/releases/latest';
            Yii::info("GitHub version check: {$coin->name} ({$coin->symbol})", __CLASS__);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'yiimp-version-checker/2.0',
            ]);
            if (defined('GITHUB_ACCESSTOKEN')) {
                curl_setopt($ch, CURLOPT_USERPWD, GITHUB_ACCESSTOKEN);
            }
            $obj = json_decode(strip_tags(curl_exec($ch)));
            curl_close($ch);

            if ($obj && isset($obj->tag_name) && $obj->tag_name !== '' && $obj->tag_name !== $coin->version_github) {
                Yii::info("New release for {$coin->symbol}: {$obj->tag_name}", __CLASS__);
                Yii::$app->db->createCommand(
                    'UPDATE coins SET version_github=:tag WHERE id=:id',
                    [':tag' => $obj->tag_name, ':id' => $coin->id]
                )->execute();
                $algoText .= "{$prefix} : New release ({$obj->tag_name})\n";
            }

            if ($coin->version_installed !== $coin->version_github) {
                $algoText .= "{$prefix} : installed ({$coin->version_installed}) differs from release ({$coin->version_github})\n";
            }

            sleep(5); // respect GitHub rate limit
        }

        // Flush last algo block
        if ($algoText !== '') {
            $mailText .= "#### Algo: {$currentAlgo} ####\n{$algoText}";
        }

        if ($mailText !== '') {
            Yii::info($mailText, __CLASS__);
            $admin = defined('YIIMP_ADMIN_EMAIL') ? YIIMP_ADMIN_EMAIL : (Yii::$app->params['adminEmail'] ?? '');
            if ($admin) {
                try {
                    Yii::$app->mailer->compose()
                        ->setTo($admin)
                        ->setSubject('new wallet versions found')
                        ->setTextBody($mailText)
                        ->send();
                } catch (\Throwable $e) {
                    Yii::warning("mailer failed: {$e->getMessage()}", __CLASS__);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function percentFeedback(float $v, float $n, float $p): float
    {
        return ($v * (100 - $p) + $n * $p) / 100;
    }

    private function detectRpcEncoding(Coins $coin, WalletRPC $remote): string
    {
        $etherCoins = ['ETH'];
        $zecCoins   = ['YEC','ZCL','ZEN','ZEC'];
        $zecParams  = [
            'ARRR' => ['ZEC','ZcashPoW',4], 'HUSH' => ['ZEC','ZcashPoW',4], 'KMD' => ['ZEC','ZcashPoW',4],
            'ANON' => ['ZEC','AnonyPoW',1], 'GLINK' => ['ZEC','sngemPoW',13],
            'BTCZ' => ['ZEC','BitcoinZ',13], 'BTG'  => ['POW','BgoldPoW',13],
        ];

        if ($coin->symbol === 'ETH' || in_array($coin->symbol, $etherCoins, true)) {
            return 'GETH';
        }
        if ($coin->symbol === 'DCR') {
            return 'DCR';
        }
        if ($coin->symbol === 'NIRO') {
            return 'NIRO';
        }
        if (in_array($coin->symbol, $zecCoins, true)) {
            $coin->personalization = 'ZcashPoW';
            $coin->powlimit_bits   = 13;
            return 'ZEC';
        }
        if (isset($zecParams[$coin->symbol])) {
            [$enc, $pers, $bits] = $zecParams[$coin->symbol];
            $coin->personalization = $pers;
            $coin->powlimit_bits   = $bits;
            return $enc;
        }

        $diff = $remote->getdifficulty();
        return is_array($diff) ? 'POS' : 'POW';
    }

    private function getBlockTemplate(Coins $coin, WalletRPC $remote): mixed
    {
        if ($coin->usemweb) {
            return $remote->getblocktemplate('{"rules":["segwit","mweb"]}');
        }
        if ($coin->usesegwit) {
            return $remote->getblocktemplate('{"rules":["segwit"]}');
        }
        return $remote->getblocktemplate('{}');
    }

    private function applyRewardDeductions(Coins $coin, array $template): void
    {
        if (isset($template['payee_amount']) && $coin->symbol !== 'LIMX') {
            $coin->charity_amount = ($coin->symbol === 'TAC' && isset($template['_V2']))
                ? $template['_V2'] / 1e8
                : (float) $template['payee_amount'] / 1e8;
            $coin->reward -= $coin->charity_amount;
        }

        switch ($coin->symbol) {
            case 'XZC':
                $coin->reward         = ($template['coinbasevalue'] ?? 0) / 1e8 * $coin->reward_mul;
                $coin->charity_amount = $coin->reward * $coin->charity_percent / 100;
                break;

            case 'BNODE':
            case 'BCRS':
            case 'IOTS':
                if (isset($template['masternode']) && ($template['masternode_payments_started'] ?? false)) {
                    $coin->reward -= ($template['masternode']['amount'] ?? 0) / 1e8;
                }
                if (isset($template['fundreward'])) {
                    $coin->reward -= ($template['fundreward']['amount'] ?? 0) / 1e8;
                }
                if (isset($template['evolution'])) {
                    $coin->reward -= ($template['evolution']['amount'] ?? 10000000) / 1e8;
                }
                break;

            default:
                if (isset($template['masternode']) && ($template['masternode_payments_enforced'] ?? false)
                    && ($template['masternode_payments_started'] ?? false)
                ) {
                    $mn = $template['masternode'];
                    if (is_array($mn) && !isset($mn['amount'])) {
                        foreach ($mn as $mnPayee) {
                            $coin->reward -= ($mnPayee['amount'] ?? 0) / 1e8;
                        }
                    } else {
                        $coin->reward -= ($mn['amount'] ?? 0) / 1e8;
                    }
                    $coin->hasmasternodes = true;
                }

                if (isset($template['devfee'])) {
                    $devfee = $template['devfee'];
                    if (is_array($devfee) && !isset($devfee['amount'])) {
                        foreach ($devfee as $devPayee) {
                            $coin->reward -= ($devPayee['amount'] ?? 0) / 1e8;
                        }
                    } else {
                        $coin->reward -= ($devfee['amount'] ?? 0) / 1e8;
                    }
                }

                if (!empty($coin->charity_address) && !$coin->charity_amount) {
                    $coin->reward -= $coin->reward * $coin->charity_percent / 100;
                }
                break;
        }
    }

    private function applyZecReward(Coins $coin, WalletRPC $remote, mixed $template): void
    {
        if ($template && isset($template['coinbasetxn'])) {
            $blockSubsidy        = $remote->getblocksubsidy();
            $coin->reward        = ($blockSubsidy['miner'] ?? 0);
            $coin->charity_amount = ($blockSubsidy['founders'] ?? 0);

            if (!$coin->reward) {
                $txn                 = $template['coinbasetxn'];
                $coin->charity_amount = ($txn['foundersreward'] ?? 0) / 1e8;
                $coin->reward         = $coin->charity_amount * 4 + ($txn['fee'] ?? 0) / 1e8;
            }

            if (isset($template['masternode']) && ($template['masternode_payments_enforced'] ?? false)) {
                $coin->reward -= ($template['masternode']['amount'] ?? 0) / 1e8;
                $coin->hasmasternodes = true;
            }
            if (isset($template['payee_amount']) && ($template['masternode_payments'] ?? false)) {
                $coin->charity_amount = $template['payee_amount'] / 1e8;
                $coin->reward        -= $coin->charity_amount;
            }

            $miningInfo      = $remote->getmininginfo();
            $coin->difficulty = $miningInfo['difficulty'] ?? $coin->difficulty;
        } else {
            $coin->auto_ready = false;
            $coin->errors     = $remote->error;
        }
    }

    // =========================================================================
    // Raw-coin / market discovery (ported from rawcoins.php)
    // =========================================================================

    /**
     * Refresh market listings from all active exchanges: set disabled flags,
     * delete stale market rows, and optionally create new coin records.
     * Ports: updateRawcoins()
     */
    public function updateRawCoins(): void
    {
        Yii::info(__METHOD__, __CLASS__);

        // Apply default enabled/disabled flags per exchange
        $exchangeDefaults = [
            'binance'  => true,
            'exbitron' => false,
            'nestex'   => false,
            'hitbtc'   => true,
            'kraken'   => true,
            'kucoin'   => true,
            'poloniex' => true,
            'yobit'    => false,
        ];
        foreach ($exchangeDefaults as $exchange => $disabled) {
            if (function_exists('exchange_set_default')) {
                exchange_set_default($exchange, 'disabled', $disabled);
            }
        }
        if (function_exists('settings_prefetch_all')) {
            settings_prefetch_all();
        }

        // Scan each active exchange for new coin listings
        $exchanges = \app\models\Balances::find()->all();
        foreach ($exchanges as $exchange) {
            $this->updateRawCoinExchange($exchange->name);
        }

        // Sync enabled/disabled state for every exchange that has market records
        $db = Yii::$app->db;
        $marketExchanges = $db->createCommand("SELECT DISTINCT name FROM markets")->queryColumn();

        foreach ($marketExchanges as $exchange) {
            $isDisabled = function_exists('exchange_get') && exchange_get($exchange, 'disabled');

            if ($isDisabled) {
                $affected = (int) $db->createCommand(
                    "UPDATE markets SET disabled=8 WHERE name=:ex",
                    [':ex' => $exchange]
                )->execute();

                // Allow individually whitelisted coins on a disabled exchange
                $coins = Coins::find()
                    ->where(['in', 'id',
                        (new \yii\db\Query())->select('coinid')->from('markets')->where(['name' => $exchange])
                    ])
                    ->all();

                foreach ($coins as $coin) {
                    if (function_exists('market_get') && market_get($exchange, $coin->getOfficialSymbol(), 'disabled', 1) == 0) {
                        $affected -= (int) $db->createCommand(
                            "UPDATE markets SET disabled=0 WHERE name=:ex AND coinid=:cid",
                            [':ex' => $exchange, ':cid' => $coin->id]
                        )->execute();
                    }
                }

                Yii::info("{$exchange}: {$affected} markets disabled", __CLASS__);
            } else {
                $re = (int) $db->createCommand(
                    "UPDATE markets SET disabled=0 WHERE name=:ex AND disabled=8",
                    [':ex' => $exchange]
                )->execute();
                if ($re) {
                    Yii::info("{$exchange}: {$re} markets re-enabled", __CLASS__);
                }
            }
        }

        $db->createCommand("DELETE FROM markets WHERE deleted")->execute();
    }

    /**
     * Scan a single exchange for its current coin list and upsert market records.
     * Ports: updateRawCoinExchange()
     */
    private function updateRawCoinExchange(string $exchange): void
    {
        Yii::info("==== Exchange {$exchange} ====", __CLASS__);

        $disabled = function_exists('exchange_get') && exchange_get($exchange, 'disabled');
        if ($disabled) {
            return;
        }

        $db = Yii::$app->db;

        switch ($exchange) {
            case 'exbitron':
                if (!function_exists('exbitron_api_query')) { break; }
                $list = exbitron_api_query('cmc/summary');
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $data) {
                    $base   = strtoupper($data->quote_currency);
                    $symbol = strtoupper($data->base_currency);
                    if ($symbol === 'BTC' && in_array($base, ['USDT','USDC'], true)) {
                        [$symbol, $base] = [$base, $symbol];
                    } elseif ($symbol === 'BTC') {
                        continue;
                    }
                    $this->updateRawCoin($exchange, $symbol, $symbol, $base === 'BTC' ? null : $base);
                }
                break;

            case 'nestex':
                if (!function_exists('nestex_api_query')) { break; }
                $list = nestex_api_query();
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $data) {
                    if (empty($data['base_currency']) || empty($data['target_currency'])) { continue; }
                    $symbol = strtoupper($data['base_currency']);
                    $base   = strtoupper($data['target_currency']);
                    if ($base !== 'USDT') { continue; }
                    $this->updateRawCoin($exchange, $symbol, $symbol, $base);
                }
                break;

            case 'nonkyc':
                if (!function_exists('nonkyc_api_query')) { break; }
                $list = nonkyc_api_query('tickers', '', 'array');
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $ticker) {
                    $base   = strtoupper($ticker['target_currency']);
                    $symbol = strtoupper($ticker['base_currency']);
                    if ($symbol === 'BTC' && in_array($base, ['USDT','USDC'], true)) {
                        [$symbol, $base] = [$base, $symbol];
                    } elseif ($symbol === 'BTC') {
                        continue;
                    }
                    $this->updateRawCoin($exchange, $symbol, $symbol, $base === 'BTC' ? null : $base);
                }
                break;

            case 'safetrade':
                if (!function_exists('safetrade_api_query')) { break; }
                $list = safetrade_api_query('trade/public/markets', '', 'array');
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $ticker) {
                    $base   = strtoupper($ticker['quote_unit']);
                    $symbol = strtoupper($ticker['base_unit']);
                    $this->updateRawCoin($exchange, $symbol, $symbol, $base === 'BTC' ? null : $base);
                }
                break;

            case 'tradeogre':
                if (!function_exists('tradeogre_api_query')) { break; }
                $list = tradeogre_api_query('markets');
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $ticker) {
                    $idx    = key($ticker);
                    $parts  = explode('-', $idx);
                    $base   = strtoupper($parts[1] ?? '');
                    $symbol = strtoupper($parts[0] ?? '');
                    $this->updateRawCoin($exchange, $symbol, $symbol, $base === 'BTC' ? null : $base);
                }
                break;

            case 'poloniex':
                if (!class_exists('poloniex')) { break; }
                $poloniex = new \poloniex();
                $tickers  = $poloniex->get_currencies();
                if (!$tickers) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($tickers as $symbol => $ticker) {
                    if ($ticker['disabled'] ?? false) { continue; }
                    if ($ticker['delisted'] ?? false) { continue; }
                    $this->updateRawCoin($exchange, $symbol);
                }
                break;

            case 'yobit':
                if (!function_exists('yobit_api_query')) { break; }
                $res = yobit_api_query('info');
                if (!$res) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($res->pairs as $i => $item) {
                    $symbol = strtoupper(explode('_', $i)[0]);
                    $this->updateRawCoin($exchange, $symbol);
                }
                break;

            case 'hitbtc':
                if (!function_exists('hitbtc_api_query')) { break; }
                $list = hitbtc_api_query('symbols');
                if (!is_object($list) || !isset($list->symbols)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list->symbols as $data) {
                    if (strtoupper($data->currency) !== 'BTC') { continue; }
                    $this->updateRawCoin($exchange, strtoupper($data->commodity));
                }
                break;

            case 'kraken':
                if (!function_exists('kraken_api_query')) { break; }
                $list = kraken_api_query('AssetPairs');
                if (!is_array($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $pair => $item) {
                    $parts  = explode('-', $pair);
                    $base   = reset($parts);
                    $symbol = end($parts);
                    if ($symbol === 'BTC' || $base !== 'BTC') { continue; }
                    if (in_array($symbol, ['GBP','CAD','EUR','USD','JPY'], true)) { continue; }
                    if (str_contains($symbol, '.d')) { continue; }
                    $this->updateRawCoin($exchange, strtoupper($symbol));
                }
                break;

            case 'binance':
                if (!function_exists('binance_api_query')) { break; }
                $list = binance_api_query('ticker/allBookTickers');
                if (!is_array($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $ticker) {
                    $base = substr($ticker->symbol, -3);
                    if ($base !== 'BTC') { continue; }
                    $symbol = substr($ticker->symbol, 0, strlen($ticker->symbol) - 3);
                    $this->updateRawCoin($exchange, $symbol);
                }
                break;

            case 'kucoin':
                if (!function_exists('kucoin_api_query') || !function_exists('kucoin_result_valid')) { break; }
                $list = kucoin_api_query('currencies');
                if (!kucoin_result_valid($list) || empty($list->data)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list->data as $item) {
                    $this->updateRawCoin($exchange, $item->name, $item->fullName);
                }
                break;

            case 'shapeshift':
                if (!function_exists('shapeshift_api_query')) { break; }
                $list = shapeshift_api_query('getcoins');
                if (!is_array($list) || empty($list)) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list as $item) {
                    if ($item['status'] !== 'available') { continue; }
                    $this->updateRawCoin($exchange, strtoupper($item['symbol']), trim($item['name']));
                }
                break;

            case 'bibox':
                if (!function_exists('bibox_api_query')) { break; }
                $list = bibox_api_query('marketAll');
                if (!isset($list['result'])) { break; }
                $db->createCommand("UPDATE markets SET deleted=true WHERE name=:ex", [':ex' => $exchange])->execute();
                foreach ($list['result'] as $currency) {
                    if ($currency['currency_symbol'] === 'BTC') { continue; }
                    $this->updateRawCoin($exchange, $currency['coin_symbol']);
                }
                break;

            default:
                Yii::info("No raw-coin scanner for exchange: {$exchange}", __CLASS__);
                break;
        }

        Yii::info('==== END Exchange ====', __CLASS__);
    }

    /**
     * Upsert a market record for a coin/exchange pair, and optionally create
     * the coin itself when YIIMP_CREATE_NEW_COINS is enabled.
     * Ports: updateRawCoin()
     */
    private function updateRawCoin(
        string  $exchange,
        string  $symbol,
        string  $name          = 'unknown',
        ?string $referenceSymbol = null
    ): void {
        if ($symbol === 'BTC') {
            return;
        }

        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        $createNew = defined('YIIMP_CREATE_NEW_COINS') && YIIMP_CREATE_NEW_COINS;

        if (!$coin && $createNew) {
            // Skip high-noise exchanges that would pollute the DB
            if (in_array($exchange, ['askcoin','binance','hitbtc','yobit','kucoin'], true)) {
                return;
            }
            if (function_exists('market_get') && market_get($exchange, $symbol, 'disabled')) {
                return;
            }

            Yii::info("new coin {$exchange} {$symbol} {$name}", __CLASS__);
            $coin             = new Coins();
            $coin->txmessage  = true;
            $coin->hassubmitblock = true;
            $coin->name       = $name;
            $coin->algo       = '';
            $coin->symbol     = $symbol;
            $coin->created    = time();
            $coin->save();
            sleep(1);

        } elseif ($coin && $coin->name === 'unknown' && $name !== 'unknown') {
            $coin->name = $name;
            $coin->save();
        }

        // Upsert the market record for all coins that match by symbol or symbol2
        $coinsForSymbol = Coins::find()
            ->where(['symbol' => $symbol])
            ->orWhere(['symbol2' => $symbol])
            ->all();

        foreach ($coinsForSymbol as $c) {
            $query = \app\models\Markets::find()->where(['coinid' => $c->id, 'name' => $exchange]);
            if (is_null($referenceSymbol)) {
                $query->andWhere(['or', ['base_coin' => null], ['base_coin' => '']]);
            } else {
                $query->andWhere(['base_coin' => $referenceSymbol]);
            }
            $market = $query->one();

            if (!$market) {
                $market            = new \app\models\Markets();
                $market->coinid    = $c->id;
                $market->name      = $exchange;
                $market->base_coin = $referenceSymbol;
            }

            $market->deleted = false;
            $market->save();
        }
    }
}
