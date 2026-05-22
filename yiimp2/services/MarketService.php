<?php

namespace app\services;

use Yii;
use app\models\Coins;
use app\models\Markets;
use app\models\Market_history;
use app\models\Balances;
use app\components\rpc\WalletRPC;

/**
 * MarketService — coin price updates, market history, and per-exchange ticker syncs.
 *
 * Ported from: web/yaamp/core/backend/markets.php
 *
 * Exchange API functions (safetrade_api_query, binance_api_query, etc.) are
 * legacy global functions defined in web/yaamp/modules/trading/.  Each per-exchange
 * method guards its API call with function_exists() so the service degrades
 * gracefully until those clients are ported to Yii2 classes.
 *
 * Settings helpers (exchange_get, market_get, market_set_default, settings_prefetch_all)
 * are also still legacy globals — same guard pattern applied.
 */
class MarketService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Sync all market prices and compute the best BTC price for every installed coin.
     * Ports: BackendPricesUpdate()
     */
    public function updatePrices(): void
    {
        Yii::info(__METHOD__, __CLASS__);

        if (function_exists('market_set_default')) {
            market_set_default('yobit', 'DCR', 'disabled', true);
        }
        if (function_exists('settings_prefetch_all')) {
            settings_prefetch_all();
        }

        // Update prices per active exchange
        $exchanges = Balances::find()->all();
        foreach ($exchanges as $exchange) {
            $this->updatePricesForExchange($exchange->name);
        }

        // Propagate prices from coins with a symbol2 alias
        $aliasCoins = Coins::find()
            ->where(['installed' => 1])
            ->andWhere(['not', ['symbol2' => null]])
            ->andWhere(['not', ['symbol2' => '']])
            ->all();

        foreach ($aliasCoins as $coin2) {
            $coin = Coins::find()->where(['symbol' => $coin2->symbol2])->one();
            if (!$coin) {
                continue;
            }

            $markets = Markets::find()->where(['coinid' => $coin->id])->all();
            foreach ($markets as $market) {
                $market2 = Markets::find()
                    ->where(['coinid' => $coin2->id, 'name' => $market->name])
                    ->one();
                if (!$market2) {
                    continue;
                }
                $market2->price          = $market->price;
                $market2->price2         = $market->price2;
                $market2->deposit_address = $market->deposit_address;
                $market2->pricetime      = $market->pricetime;
                $market2->save();
            }
        }

        // Set best price on each coin
        $db    = Yii::$app->db;
        $coins = Coins::find()
            ->where(['installed' => 1])
            ->andWhere(['in', 'id',
                (new \yii\db\Query())->select('coinid')->from('markets')->distinct()
            ])
            ->all();

        $exchangeFee = defined('YIIMP_FEES_EXCHANGE') ? (float) YIIMP_FEES_EXCHANGE : 2.0;

        foreach ($coins as $coin) {
            if ($coin->symbol === 'BTC') {
                $coin->price  = 1;
                $coin->price2 = 1;
                $coin->save();
                continue;
            }

            $market = $this->getBestMarket($coin);
            if ($market) {
                if (is_null($market->base_coin)) {
                    $coin->price  = $market->price * (1 - $exchangeFee / 100);
                    $coin->price2 = $market->price2;
                } else {
                    $baseCoin = Coins::find()->where(['symbol' => $market->base_coin])->one();
                    if ($baseCoin) {
                        $coin->price  = $market->price * $baseCoin->price;
                        $coin->price2 = $market->price2 * $baseCoin->price;
                    }
                }
            } else {
                $coin->price  = 0;
                $coin->price2 = 0;
            }

            $coin->save();
            $db->createCommand(
                "UPDATE earnings SET price={$coin->price} WHERE status!=2 AND coinid={$coin->id}"
            )->execute();
        }

        $db->createCommand(
            "UPDATE markets SET message=NULL WHERE disabled=0 AND message='disabled from settings'"
        )->execute();

        Yii::info('==== END ' . __METHOD__ . ' ====', __CLASS__);
    }

    /**
     * Dispatch the per-exchange price update for a single exchange.
     * Ports: BackendPricesUpdateExchange()
     */
    public function updatePricesForExchange(string $exchange): void
    {
        Yii::info("==== Start Sync Market Price {$exchange} ====", __CLASS__);

        match ($exchange) {
            'safetrade'  => $this->updateSafeTradeMarkets(),
            'exbitron'   => $this->updateExbitronMarkets(),
            'nestex'     => $this->updateNestexMarkets(),
            'yobit'      => $this->updateYobitMarkets(),
            'tradeogre'  => $this->updateTradeOgreMarkets(),
            'binance'    => $this->updateBinanceMarkets(),
            'bibox'      => $this->updateBiboxMarkets(),
            'shapeshift' => $this->updateShapeShiftMarkets(),
            'nonkyc'     => $this->updateNonKycMarkets(),
            'gateio'     => $this->updateGateioMarkets(),
            'kraken'     => $this->updateKrakenMarkets(),
            'poloniex'   => $this->updatePoloniexMarkets(),
            'hitbtc'     => $this->updateHitBtcMarkets(),
            'kucoin'     => $this->updateKuCoinMarkets(),
            default      => Yii::info("No updater for exchange: {$exchange}", __CLASS__),
        };

        Yii::info('==== End Sync Market Price ====', __CLASS__);
    }

    /**
     * Record price and balance history snapshots for watched coins and DCR stake tracking.
     * Ports: BackendWatchMarkets()
     */
    public function watchMarkets(?string $marketName = null): void
    {
        // Populate watch flag from config
        if (defined('YIIMP_WATCH_CURRENCIES')) {
            foreach (explode(',', YIIMP_WATCH_CURRENCIES) as $symbol) {
                Yii::$app->db->createCommand(
                    'UPDATE coins SET watch=1 WHERE symbol=:sym',
                    [':sym' => trim($symbol)]
                )->execute();
            }
        }

        $coins = Coins::find()->where(['watch' => 1])->all();

        foreach ($coins as $coin) {
            // BTC: record USD price from mining table
            if ($coin->symbol === 'BTC') {
                if ($marketName) {
                    continue;
                }
                $mh          = new Market_history();
                $mh->time    = time();
                $mh->idcoin  = $coin->id;
                $mh->idmarket = null;
                $mh->price   = Yii::$app->db->createCommand("SELECT usdbtc FROM mining LIMIT 1")->queryScalar();
                if (defined('YIIMP_FIAT_ALTERNATIVE') && YIIMP_FIAT_ALTERNATIVE === 'EUR' && function_exists('kraken_btceur')) {
                    $mh->price2 = kraken_btceur();
                }
                $mh->balance = Yii::$app->db->createCommand("SELECT SUM(balance) FROM balances")->queryScalar();
                $mh->save();
                continue;
            }

            if ($coin->installed) {
                $mh           = new Market_history();
                $mh->time     = time();
                $mh->idcoin   = $coin->id;
                $mh->idmarket = null;
                $mh->price    = $coin->price;
                $mh->price2   = $coin->price2;
                $mh->balance  = $coin->balance;
                $mh->save();
            }

            // DCR: record locked-by-tickets balance as a "stake" market entry
            if ($coin->rpcencoding === 'DCR') {
                $remote   = new WalletRPC($coin);
                $stake    = 0.0;
                $balances = $remote->getbalance('*', 0);
                if (isset($balances['balances'])) {
                    foreach ($balances['balances'] as $accb) {
                        $stake += (float) ($accb['lockedbytickets'] ?? 0);
                    }
                }
                $stakeInfo = $remote->getstakeinfo();
                if (empty($remote->error) && isset($stakeInfo['difficulty'])) {
                    Yii::$app->db->createCommand(
                        "UPDATE markets SET balance=0, ontrade=:stake, balancetime=:t,
                             price=:ticketprice, price2=:live, pricetime=NULL
                         WHERE coinid=:id AND name='stake'",
                        [
                            ':ticketprice' => $stakeInfo['difficulty'],
                            ':live'        => $stakeInfo['live'],
                            ':stake'       => $stake,
                            ':id'          => $coin->id,
                            ':t'           => time(),
                        ]
                    )->execute();
                }
            }

            // Per-market history snapshots
            $markets = Markets::find()
                ->where(['coinid' => $coin->id, 'disabled' => 0])
                ->all();

            foreach ($markets as $market) {
                if ($marketName && $market->name !== $marketName) {
                    continue;
                }
                if (!empty($market->base_coin)) {
                    continue;
                }
                if (empty($market->price)) {
                    continue;
                }

                $mh           = new Market_history();
                $mh->time     = time();
                $mh->idcoin   = $coin->id;
                $mh->idmarket = $market->id;
                $mh->price    = $market->price;
                $mh->price2   = $market->price2;
                $mh->balance  = (float) $market->balance + (float) $market->ontrade;
                $mh->save();
            }
        }
    }

    /**
     * Find the market record with the best (highest) BTC price for a given coin.
     * Ports: getBestMarket()
     */
    public function getBestMarket(Coins $coin): ?object
    {
        if ($coin->symbol === 'BTC') {
            return null;
        }

        // Recurse through symbol2 aliases
        if (!empty($coin->symbol2)) {
            $alt = Coins::find()->where(['symbol' => $coin->symbol2])->one();
            if ($alt && $alt->symbol2 !== $coin->symbol2) {
                return $this->getBestMarket($alt);
            }
        }

        $market = null;

        // Prefer the coin's configured market if set
        if (!empty($coin->market) && $coin->market !== 'BEST' && $coin->market !== 'unknown') {
            $market = Markets::find()
                ->where(['coinid' => $coin->id, 'name' => $coin->market])
                ->andWhere(['not', ['price' => 0]])
                ->andWhere(['deleted' => 0, 'disabled' => 0])
                ->andWhere(['not', ['deposit_address' => null]])
                ->andWhere(['not', ['deposit_address' => '']])
                ->one();
        }

        // Fall back to best price across all markets
        if (!$market) {
            $bestId = Yii::$app->db->createCommand(
                "SELECT markets.id
                 FROM markets
                 LEFT JOIN coins ON coins.symbol = markets.base_coin
                 WHERE markets.coinid = :cid AND markets.price != 0
                   AND NOT markets.deleted AND NOT markets.disabled
                 ORDER BY markets.priority DESC,
                   CASE markets.base_coin
                     WHEN markets.base_coin THEN markets.price * COALESCE(coins.price, 1)
                     ELSE markets.price
                   END DESC
                 LIMIT 1",
                [':cid' => $coin->id]
            )->queryScalar();

            if ($bestId) {
                $market = Markets::findOne((int) $bestId);
            }
        }

        if (!$market && empty($coin->market)) {
            Yii::info("best market for {$coin->symbol} is unknown", __CLASS__);
            $coin->market = 'unknown';
            $coin->save();
        }

        return $market;
    }

    // -------------------------------------------------------------------------
    // Per-exchange updaters
    // All API calls are guarded with function_exists() so the service runs
    // even before the legacy exchange API clients are ported to Yii2 classes.
    // -------------------------------------------------------------------------

    private function updateSafeTradeMarkets(): void
    {
        $exchange = 'safetrade';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('safetrade_api_query')) {
            Yii::warning("{$exchange}: safetrade_api_query not available (pending exchange API port)", __CLASS__);
            return;
        }

        $marketList = safetrade_api_query('trade/public/markets', '', 'array');
        if (empty($marketList) || !is_array($marketList)) {
            return;
        }

        $marketNames = [];
        foreach ($marketList as $data) {
            $marketNames[$data['id']] = ['coinsymbol' => $data['base_unit'], 'basesymbol' => $data['quote_unit']];
        }

        $tickers = safetrade_api_query('trade/public/tickers');
        if (!is_array($tickers)) {
            return;
        }

        foreach ($tickers as $key => $data) {
            if (!isset($marketNames[$key])) {
                continue;
            }
            $symbol = strtoupper($marketNames[$key]['coinsymbol']);
            $base   = strtoupper($marketNames[$key]['basesymbol']);
            if ($base !== 'BTC') {
                continue;
            }

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                continue;
            }

            $market = $this->findOrCreateMarket($coin, $exchange);
            if (!$market) {
                continue;
            }
            if ($this->marketDisabled($exchange, $coin->getOfficialSymbol(), $market)) {
                continue;
            }

            $bid    = (float) $data->low;
            $ask    = (float) $data->high;
            $price2 = ($ask + $bid) / 2;

            $market->price2     = $this->averageIncrement((float) $market->price2, $price2);
            $market->price      = $this->averageIncrement((float) $market->price, $bid);
            $market->priority   = -1;
            $market->txfee      = 0.2;
            $market->pricetime  = time();
            $market->save();

            if (!empty($market->price2) && (empty($coin->price2) || $coin->price2 == 0)) {
                $coin->price  = $market->price;
                $coin->price2 = $market->price2;
                $coin->save();
            }
        }
    }

    private function updateExbitronMarkets(): void
    {
        $exchange = 'exbitron';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('exbitron_api_query')) {
            Yii::warning("{$exchange}: exbitron_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $data = exbitron_api_query('cmc/summary');
        if (!is_array($data)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = $coin->getOfficialSymbol();
            $pair   = strtoupper($symbol . '_' . ($market->base_coin ?: 'BTC'));

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            foreach ($data as $ticker) {
                if (strtoupper($ticker->trading_pairs) !== $pair) {
                    continue;
                }
                $price2         = ($ticker->highest_bid + $ticker->lowest_ask) / 2;
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker->highest_bid);
                $market->pricetime = time();
                $market->save();
                if (empty($coin->price) && $ticker->lowest_ask) {
                    $coin->price  = $market->price;
                    $coin->price2 = $price2;
                    $coin->save();
                }
                break;
            }
        }
    }

    private function updateNestexMarkets(): void
    {
        $exchange = 'nestex';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('nestex_api_query')) {
            Yii::warning("{$exchange}: nestex_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $data = nestex_api_query();
        if (!is_array($data)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = strtoupper($coin->getOfficialSymbol());
            $pair   = $symbol . '_USDT';

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            foreach ($data as $ticker) {
                if (!isset($ticker['ticker_id'])) {
                    continue;
                }
                if (strtoupper($ticker['ticker_id']) !== $pair) {
                    continue;
                }
                $bid = (float) $ticker['bid'];
                $ask = (float) $ticker['ask'];
                if (!$bid || !$ask) {
                    continue;
                }
                $price2         = ($bid + $ask) / 2;
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->price  = $this->averageIncrement((float) $market->price, $bid);
                $market->pricetime = time();
                $market->save();
                if (empty($coin->price) && $ask) {
                    $coin->price  = $market->price;
                    $coin->price2 = $price2;
                    $coin->save();
                }
                break;
            }
        }
    }

    private function updateNonKycMarkets(): void
    {
        $exchange = 'nonkyc';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('nonkyc_api_query')) {
            Yii::warning("{$exchange}: nonkyc_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $data = nonkyc_api_query('tickers', '', 'array');
        if (!is_array($data)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol      = $coin->getOfficialSymbol();
            $pair        = strtolower($symbol . '_' . ($market->base_coin ?: 'btc'));
            $pairReverse = 'btc_' . strtolower($symbol);

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            foreach ($data as $ticker) {
                if ($ticker['type'] !== 'market') {
                    continue;
                }
                $tickerId = strtolower($ticker['ticker_id']);

                if ($tickerId === $pair) {
                    $price2         = ($ticker['bid'] + $ticker['ask']) / 2;
                    $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                    $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['bid']);
                    $market->pricetime = time();
                    $market->save();
                    if (empty($coin->price) && $ticker['ask']) {
                        $coin->price  = $market->price;
                        $coin->price2 = $price2;
                        $coin->save();
                    }
                    break;
                }

                if ($tickerId === $pairReverse) {
                    $tmpBid = $ticker['ask'] ? 1 / $ticker['ask'] : 0.0;
                    $tmpAsk = $ticker['bid'] ? 1 / $ticker['bid'] : 0.0;
                    $price2         = ($tmpBid + $tmpAsk) / 2;
                    $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                    $market->price  = $this->averageIncrement((float) $market->price, $tmpBid);
                    $market->pricetime = time();
                    $market->save();
                    if (empty($coin->price) && $tmpAsk) {
                        $coin->price  = $market->price;
                        $coin->price2 = $price2;
                        $coin->save();
                    }
                    break;
                }
            }
        }
    }

    private function updateTradeOgreMarkets(): void
    {
        $exchange = 'tradeogre';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('tradeogre_api_query')) {
            Yii::warning("{$exchange}: tradeogre_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $markets = tradeogre_api_query('markets');
        if (!is_array($markets)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = $coin->getOfficialSymbol();
            $dbPair = strtoupper($symbol . '-' . ($market->base_coin ?: 'BTC'));

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            foreach ($markets as $ticker) {
                $pair = key($ticker);
                if ($pair !== $dbPair) {
                    continue;
                }
                $price2         = ($ticker[$pair]['bid'] + $ticker[$pair]['ask']) / 2;
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker[$pair]['bid']);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                $market->save();
                if (empty($coin->price) || empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->market = $exchange;
                    $coin->save();
                }
            }
        }
    }

    private function updateBiboxMarkets(): void
    {
        $exchange = 'bibox';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('bibox_api_query')) {
            Yii::warning("{$exchange}: bibox_api_query not available", __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE '{$exchange}%'")->queryScalar();
        if (!$count) {
            return;
        }

        $list = bibox_api_query('marketAll');
        if (!is_array($list)) {
            return;
        }

        foreach ($list['result'] as $marketData) {
            if ($marketData['currency_symbol'] !== 'BTC') {
                continue;
            }
            $symbol = $marketData['coin_symbol'];
            $coin   = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                continue;
            }
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $exchange])->one();
            if (!$market) {
                continue;
            }
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }
            $ticker         = bibox_api_query("ticker&pair={$symbol}_BTC")['result'];
            $price2         = ($ticker['buy'] + $ticker['sell']) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['buy']);
            $market->pricetime = time();
            $market->save();
        }
    }

    private function updateGateioMarkets(): void
    {
        $exchange = 'gateio';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('gateio_api_query')) {
            Yii::warning("{$exchange}: gateio_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $markets = gateio_api_query('tickers');
        if (!is_array($markets)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }
            $dbPair = strtolower($symbol) . '_btc';
            foreach ($markets as $pair => $ticker) {
                if ($pair !== $dbPair) {
                    continue;
                }
                $price2         = ((float) $ticker['highestBid'] + (float) $ticker['lowestAsk']) / 2;
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['highestBid']);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                $market->priority  = -1;
                $market->txfee     = 0.2;
                $market->save();
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->market = $exchange;
                    $coin->save();
                }
            }
        }
    }

    private function updateKrakenMarkets(): void
    {
        $exchange = 'kraken';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('kraken_api_query')) {
            Yii::warning("{$exchange}: kraken_api_query not available", __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE '{$exchange}%'")->queryScalar();
        if (!$count) {
            return;
        }

        $result = kraken_api_query('AssetPairs');
        if (!is_array($result)) {
            return;
        }

        foreach ($result as $pair => $data) {
            $parts  = explode('-', $pair);
            $base   = reset($parts);
            $symbol = end($parts);
            if ($symbol === 'BTC' || $base !== 'BTC') {
                continue;
            }
            if (in_array($symbol, ['GBP','CAD','EUR','USD','JPY'], true)) {
                continue;
            }
            if (str_contains($symbol, '.d')) {
                continue;
            }

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin || (!$coin->installed && !$coin->watch)) {
                continue;
            }

            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $exchange])->one();
            if (!$market) {
                continue;
            }

            $fees       = reset($data['fees']);
            $market->txfee = is_array($fees) ? end($fees) : null;

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }
            $market->save();
            if ($market->disabled || $market->deleted) {
                continue;
            }

            sleep(1);
            $ticker = kraken_api_query('Ticker', $symbol);
            if (!is_array($ticker) || !isset($ticker[$pair])) {
                continue;
            }
            $t = $ticker[$pair] ?? [];
            if (!isset($t['b'])) {
                continue;
            }

            $price1 = (float) $t['a'][0];
            $price2 = (float) $t['b'][0];

            // Kraken alt markets are inverted against BTC
            if ($price2 > $price1) {
                [$price1, $price2] = [$price2 ? 1 / $price2 : 0, $price1 ? 1 / $price1 : 0];
            } else {
                [$price1, $price2] = [$price1 ? 1 / $price1 : 0, $price2 ? 1 / $price2 : 0];
            }

            $market->price  = $this->averageIncrement((float) $market->price, $price1);
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->pricetime = time();
            $market->save();
        }
    }

    private function updatePoloniexMarkets(): void
    {
        $exchange = 'poloniex';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!class_exists('poloniex')) {
            Yii::warning("{$exchange}: poloniex class not available", __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE '{$exchange}%'")->queryScalar();
        if (!$count) {
            return;
        }

        $poloniex = new \poloniex();
        $tickers  = $poloniex->get_ticker();
        if (!is_array($tickers)) {
            return;
        }

        foreach ($tickers as $symbol => $ticker) {
            $a = explode('_', $symbol);
            if (!isset($a[1]) || $a[0] !== 'BTC') {
                continue;
            }
            $symbol = $a[1];
            $coin   = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                continue;
            }
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $exchange])->one();
            if (!$market) {
                continue;
            }
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }
            if ($market->disabled || $market->deleted) {
                continue;
            }

            $price2         = ((float) $ticker['highestBid'] + (float) $ticker['lowestAsk']) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['highestBid']);
            $market->pricetime = time();
            $market->save();

            if (empty($market->deposit_address) && $coin->installed && !empty(defined('EXCH_POLONIEX_KEY') ? EXCH_POLONIEX_KEY : '')) {
                $lastChecked = Yii::$app->cache->get("{$exchange}-deposit_address-check");
                if (time() - (int) $lastChecked < 3600) {
                    $poloniex->generate_address($coin->symbol);
                    sleep(1);
                }
                Yii::$app->cache->set("{$exchange}-deposit_address-check", 0, 10);
            }
        }

        // Sync deposit addresses
        if (!empty(defined('EXCH_POLONIEX_KEY') ? EXCH_POLONIEX_KEY : '')) {
            $lastChecked = Yii::$app->cache->get("{$exchange}-deposit_address-check");
            if (!$lastChecked) {
                $addresses = $poloniex->get_deposit_addresses();
                if (!is_array($addresses)) {
                    return;
                }
                foreach ($addresses as $sym => $addr) {
                    if ($sym === 'BTC') {
                        continue;
                    }
                    $coin = Coins::find()->where(['symbol' => $sym])->one();
                    if (!$coin) {
                        continue;
                    }
                    $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $exchange])->one();
                    if ($market && $market->deposit_address !== $addr) {
                        $market->deposit_address = $addr;
                        $market->save();
                        Yii::info("{$exchange}: deposit address for {$sym} updated", __CLASS__);
                    }
                }
                Yii::$app->cache->set("{$exchange}-deposit_address-check", time(), 12 * 3600);
            }
        }
    }

    private function updateYobitMarkets(): void
    {
        $exchange = 'yobit';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('yobit_api_query')) {
            Yii::warning("{$exchange}: yobit_api_query not available", __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE '{$exchange}%'")->queryScalar();
        if (!$count) {
            return;
        }

        $res = yobit_api_query('info');
        if (!is_object($res)) {
            return;
        }

        foreach ($res->pairs as $i => $item) {
            $parts      = explode('_', $i);
            $symbol     = strtoupper($parts[0]);
            $baseSymbol = strtoupper($parts[1]);
            if ($symbol === 'BTC') {
                continue;
            }

            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                continue;
            }

            if ($baseSymbol !== 'BTC') {
                $inDb = (int) Yii::$app->db->createCommand(
                    "SELECT COUNT(M.id) FROM markets M INNER JOIN coins C ON C.id=M.coinid
                     WHERE C.installed AND C.symbol=:sym AND M.name LIKE '{$exchange} %' AND M.base_coin=:base",
                    [':sym' => $symbol, ':base' => $baseSymbol]
                )->queryScalar();
                if (!$inDb) {
                    continue;
                }
                $sqlFilter = "AND base_coin='{$baseSymbol}'";
            } else {
                $sqlFilter = "AND IFNULL(base_coin,'')=''";
            }

            $market = Markets::find()
                ->where(['coinid' => $coin->id])
                ->andWhere(['like', 'name', $exchange . '%', false])
                ->andFilterWhere(['base_coin' => $baseSymbol === 'BTC' ? null : $baseSymbol])
                ->one();
            if (!$market) {
                continue;
            }

            $market->txfee = $item->fee ?? 0.2;
            if ($market->disabled < 9) {
                $market->disabled = $item->hidden ?? 0;
            }
            if (time() - (int) $market->pricetime > 6 * 3600) {
                $market->price = 0;
            }
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }
            $market->save();
            if ($market->deleted || $market->disabled) {
                continue;
            }
            if (!$coin->installed && !$coin->watch) {
                continue;
            }

            $symbol = $coin->getOfficialSymbol();
            $pair   = strtolower("{$symbol}_{$baseSymbol}");
            $ticker = yobit_api_query("ticker/{$pair}");
            if (!$ticker || !isset($ticker->$pair)) {
                continue;
            }
            if (!isset($ticker->$pair->buy)) {
                Yii::warning("{$exchange}: invalid data for {$pair}", __CLASS__);
                continue;
            }

            $price2         = ($ticker->$pair->buy + $ticker->$pair->sell) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker->$pair->buy);
            if ($ticker->$pair->buy < $market->price) {
                $market->price = $ticker->$pair->buy;
            }
            $market->pricetime = time();
            $market->save();

            if (!empty(defined('EXCH_YOBIT_KEY') ? EXCH_YOBIT_KEY : '') && function_exists('yobit_api_query2')) {
                $cacheKey    = "{$exchange}-deposit_address-check-{$symbol}";
                $lastChecked = Yii::$app->cache->get($cacheKey);
                if ($lastChecked) {
                    continue;
                }
                sleep(1);
                $address = yobit_api_query2('GetDepositAddress', ['coinName' => $symbol]);
                if (!empty($address) && isset($address['return']) && $address['success']) {
                    $addr = $address['return']['address'];
                    if (!empty($addr) && $addr !== $market->deposit_address) {
                        $market->deposit_address = $addr;
                        Yii::info("{$exchange}: deposit address for {$symbol} updated", __CLASS__);
                        $market->save();
                    }
                }
                Yii::$app->cache->set($cacheKey, time(), 24 * 3600);
            }
        }
    }

    private function updateHitBtcMarkets(): void
    {
        $exchange = 'hitbtc';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('hitbtc_api_query')) {
            Yii::warning("{$exchange}: hitbtc_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $data = hitbtc_api_query('ticker', '', 'array');
        if (!is_array($data) || empty($data)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $base   = $market->base_coin ?: 'BTC';
            $symbol = $coin->getOfficialSymbol();
            $pair   = empty($market->base_coin)
                ? strtoupper($symbol) . $base
                : strtoupper($base . $symbol);

            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            foreach ($data as $p => $ticker) {
                if ($p !== $pair) {
                    continue;
                }
                $price2         = ((float) $ticker['bid'] + (float) $ticker['ask']) / 2;
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['bid']);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                $market->priority  = -1;
                $market->save();
                if (empty($coin->price2) && str_contains($pair, 'BTC')) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
            }
        }
    }

    private function updateBinanceMarkets(): void
    {
        $exchange = 'binance';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('binance_api_query')) {
            Yii::warning("{$exchange}: binance_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $tickers = binance_api_query('ticker/allBookTickers');
        if (!is_array($tickers)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            $pair = $symbol . 'BTC';
            foreach ($tickers as $ticker) {
                if ($pair !== $ticker->symbol) {
                    continue;
                }
                $price2         = ($ticker->bidPrice + $ticker->askPrice) / 2;
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker->bidPrice);
                $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
                $market->pricetime = time();
                if ($market->disabled < 9) {
                    $market->disabled = (int) ((float) $ticker->bidQty < 0.01);
                }
                $market->save();
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
            }
        }
    }

    private function updateKuCoinMarkets(): void
    {
        $exchange = 'kucoin';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('kucoin_api_query') || !function_exists('kucoin_result_valid')) {
            Yii::warning("{$exchange}: kucoin_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }

        $symbols = kucoin_api_query('symbols', 'market=BTC');
        if (!kucoin_result_valid($symbols) || empty($symbols->data)) {
            return;
        }
        usleep(500);
        $markets = kucoin_api_query('market/allTickers');
        if (!kucoin_result_valid($markets) || !isset($markets->data->ticker)) {
            return;
        }
        $tickers = $markets->data->ticker;

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            $symbol = $coin->getOfficialSymbol();
            if ($this->marketDisabled($exchange, $symbol, $market)) {
                continue;
            }

            $pair          = strtoupper($symbol) . '-BTC';
            $enableTrading = false;

            foreach ($symbols->data as $sym) {
                if (($sym->symbol ?? null) !== $pair) {
                    continue;
                }
                $enableTrading = (bool) ($sym->enableTrading ?? false);
                break;
            }

            if ($market->disabled == (int) $enableTrading) {
                $market->disabled = (int) (!$enableTrading);
                $market->save();
                if ($market->disabled) {
                    continue;
                }
            }

            foreach ($tickers as $ticker) {
                if ($ticker->symbol !== $pair) {
                    continue;
                }
                if (!isset($ticker->buy) || $ticker->buy == -1) {
                    continue;
                }
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker->buy);
                $market->price2 = $this->averageIncrement((float) $market->price2, (float) ($ticker->sell ?? $ticker->buy));
                $market->priority  = -1;
                $market->pricetime = time();
                if ((float) $ticker->vol > 0.01) {
                    $market->save();
                }
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
            }
        }
    }

    private function updateShapeShiftMarkets(): void
    {
        $exchange = 'shapeshift';
        if ($this->exchangeDisabled($exchange)) {
            return;
        }
        if (!function_exists('shapeshift_api_query')) {
            Yii::warning("{$exchange}: shapeshift_api_query not available", __CLASS__);
            return;
        }

        $list = Markets::find()->where(['like', 'name', $exchange . '%', false])->all();
        if (empty($list)) {
            return;
        }
        $markets = shapeshift_api_query('marketinfo');
        if (!is_array($markets) || empty($markets)) {
            return;
        }

        foreach ($list as $market) {
            $coin = Coins::findOne((int) $market->coinid);
            if (!$coin) {
                continue;
            }
            if ($this->marketDisabled($exchange, $coin->symbol, $market)) {
                continue;
            }

            $symbol = $coin->getOfficialSymbol();
            $pair   = empty($market->base_coin)
                ? strtoupper($symbol) . '_BTC'
                : strtoupper($symbol) . '_' . strtoupper($market->base_coin);

            foreach ($markets as $ticker) {
                if ($ticker['pair'] !== $pair) {
                    continue;
                }
                $market->price  = $this->averageIncrement((float) $market->price, (float) $ticker['rate']);
                $market->price2 = $this->averageIncrement((float) $market->price2, (float) $ticker['rate']);
                $market->txfee  = $ticker['minerFee'] * 100;
                $market->pricetime = time();
                $market->priority  = -1;
                $market->save();
                if (empty($coin->price2)) {
                    $coin->price  = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Utility helpers
    // -------------------------------------------------------------------------

    /** Weighted exponential moving average (80/20 blend). */
    private function averageIncrement(float $value1, float $value2): float
    {
        return ($value1 * 20 + $value2 * 80) / 100;
    }

    /** Returns true if the exchange is marked disabled in settings. */
    private function exchangeDisabled(string $exchange): bool
    {
        if (function_exists('exchange_get')) {
            return (bool) exchange_get($exchange, 'disabled');
        }
        return false;
    }

    /**
     * Returns true and marks the market record if the coin/market is disabled in settings.
     * Also saves the market record if disabled.
     */
    private function marketDisabled(string $exchange, string $symbol, Markets $market): bool
    {
        if (function_exists('market_get') && market_get($exchange, $symbol, 'disabled')) {
            $market->disabled = 1;
            $market->message  = 'disabled from settings';
            $market->save();
            return true;
        }
        return false;
    }

    /** Find a market record or create a new stub for a coin/exchange pair. */
    private function findOrCreateMarket(Coins $coin, string $exchange): ?Markets
    {
        $market = Markets::find()
            ->where(['coinid' => $coin->id, 'name' => $exchange])
            ->andWhere(['or', ['base_coin' => null], ['base_coin' => ''], ['base_coin' => 'BTC']])
            ->one();

        if (!$market) {
            $market          = new Markets();
            $market->coinid  = $coin->id;
            $market->deleted = 0;
            $market->name    = $exchange;
        }

        return $market;
    }

    // =========================================================================
    // Sell coins to exchanges (ported from sell.php)
    // =========================================================================

    /**
     * Send excess balances from all enabled non-BTC coin wallets to their best market.
     * Ports: TradingSellCoins()
     */
    public function sellCoins(): void
    {
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->andWhere(['>', 'balance', 0])
            ->andWhere(['!=', 'symbol', 'BTC'])
            ->all();

        foreach ($coins as $coin) {
            $this->sellCoinToExchange($coin);
        }
    }

    /**
     * Send available balance for a single coin to its best market deposit address.
     * Handles z-address shielding for Zcash-family coins and per-coin amount caps.
     * Ports: sellCoinToExchange()
     */
    private function sellCoinToExchange(Coins $coin): void
    {
        if ($coin->dontsell) {
            return;
        }

        $remote        = new \app\components\rpc\WalletRPC($coin);
        $walletZAddress = $coin->wallet_zaddress;
        $zBalance      = false;

        if (!empty($walletZAddress)) {
            $zBalance = $remote->z_getbalance($walletZAddress);
        }

        $info = $remote->getinfo();
        if (!$info || (!(float) ($info['balance'] ?? 0) && !$zBalance)) {
            return;
        }

        // Send to symbol2 master wallet (alias coin relay)
        if (!empty($coin->symbol2)) {
            $aliasCoin = Coins::find()->where(['symbol' => $coin->symbol2])->one();
            if (!$aliasCoin) {
                return;
            }
            $amount = ((float) ($info['balance'] ?? 0) - (float) ($info['paytxfee'] ?? 0)) * 0.9;
            $remote->sendtoaddress($aliasCoin->master_wallet, $amount);
            return;
        }

        $market = $this->getBestMarket($coin);
        if (!$market) {
            return;
        }

        // Skip if a previous send is still pending confirmation
        if ($market->lastsent !== null && $market->lastsent > $market->lasttraded) {
            return;
        }

        $depositAddress = $market->deposit_address;
        $marketName     = $market->name;

        if (empty($depositAddress)) {
            return;
        }

        $reserved1 = (float) Yii::$app->db->createCommand(
            "SELECT SUM(balance) FROM accounts WHERE coinid=:cid",
            [':cid' => $coin->id]
        )->queryScalar();
        $reserved2 = (float) Yii::$app->db->createCommand(
            "SELECT SUM(amount*price) FROM earnings
             WHERE status!=2 AND userid IN (SELECT id FROM accounts WHERE coinid=:cid)",
            [':cid' => $coin->id]
        )->queryScalar();

        $txFee    = (float) ($info['paytxfee'] ?? 0);
        $reserved = ($reserved1 + $reserved2) * 10;
        $amount   = (float) ($info['balance'] ?? 0) - $txFee - $reserved;

        // ZCash-family: shield coinbase to z-address first, then send from z-address
        if (!empty($walletZAddress)) {
            if ($amount > $coin->sellthreshold) {
                $result = $remote->z_shieldcoinbase('*', $walletZAddress);
                if (!$result) {
                    return;
                }
            }
            $zAmount = (float) $zBalance - $txFee - $reserved;
            if ($zAmount < $coin->sellthreshold) {
                return;
            }
            $remote->z_sendmany($walletZAddress, [
                ['address' => $depositAddress, 'amount' => round($zAmount - 0.0001, 8)],
            ]);
            return;
        }

        if ($amount < (float) $coin->sellthreshold) {
            return;
        }
        if ($amount > (float) $coin->sellthreshold && $amount < (float) $coin->reward / 4) {
            return;
        }

        // Validate deposit address before sending
        $addrInfo = $remote->validateaddress($depositAddress);
        if (!($addrInfo['isvalid'] ?? false)) {
            Yii::warning("sell: invalid deposit address {$depositAddress} for {$coin->symbol}", __CLASS__);
            return;
        }

        $amount = round($amount, 8);
        $tx     = $remote->sendtoaddress($depositAddress, $amount);

        if (!$tx) {
            // Retry with a reduced amount for known high-UTXO coins
            $caps = ['DIME' => 10000000, 'CNOTE' => 10000, 'SRC' => 500];
            $amount = isset($caps[$coin->symbol])
                ? min($amount, $caps[$coin->symbol])
                : round($amount * 0.99, 8);

            sleep(1);
            $tx = $remote->sendtoaddress($depositAddress, $amount);
            if (!$tx) {
                Yii::warning("sell: {$coin->symbol} failed to send {$amount} → {$depositAddress}: {$remote->error}", __CLASS__);
                return;
            }
        }

        $market->lastsent = time();
        $market->save();

        $deposit             = new \app\models\Exchange_deposit();
        $deposit->market     = $marketName;
        $deposit->coinid     = $coin->id;
        $deposit->send_time  = time();
        $deposit->quantity   = $amount;
        $deposit->price_estimate = $coin->price;
        $deposit->status     = 'waiting';
        $deposit->tx         = $tx;
        $deposit->save();
    }
}
