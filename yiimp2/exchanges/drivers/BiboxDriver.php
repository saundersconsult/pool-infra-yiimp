<?php

namespace app\exchanges\drivers;

use Yii;
use app\exchanges\ExchangeDriver;
use app\models\Coins;
use app\models\Markets;

class BiboxDriver extends ExchangeDriver
{
    public function name(): string { return 'bibox'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://www.bibox.com/exchange?coinPair={$symbol}_{$base}"; }
    public function supportsMarkets(): bool  { return true; }
    public function supportsDiscover(): bool { return true; }

    public function updateMarkets(): void
    {
        if ($this->isDisabled()) return;
        if (!function_exists('bibox_api_query')) {
            Yii::warning('bibox: bibox_api_query not available', __CLASS__);
            return;
        }

        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(id) FROM markets WHERE name LIKE 'bibox%'")->queryScalar();
        if (!$count) return;

        $list = bibox_api_query('marketAll');
        if (!is_array($list)) return;

        foreach ($list['result'] as $marketData) {
            if ($marketData['currency_symbol'] !== 'BTC') continue;
            $symbol = $marketData['coin_symbol'];
            $coin   = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) continue;
            $market = Markets::find()->where(['coinid' => $coin->id, 'name' => $this->name()])->one();
            if (!$market) continue;
            if ($this->marketDisabled($symbol, $market)) continue;

            $ticker         = bibox_api_query("ticker&pair={$symbol}_BTC")['result'];
            $price2         = ($ticker['buy'] + $ticker['sell']) / 2;
            $market->price2 = $this->averageIncrement((float) $market->price2, $price2);
            $market->price  = $this->averageIncrement((float) $market->price,  (float) $ticker['buy']);
            $market->pricetime = time();
            $market->save();
        }
    }

    public function discoverCoins(): void
    {
        if ($this->isDisabled() || !function_exists('bibox_api_query')) return;

        $list = bibox_api_query('marketAll');
        if (!isset($list['result'])) return;

        $this->softDeleteMarkets();
        foreach ($list['result'] as $currency) {
            if ($currency['currency_symbol'] === 'BTC') continue;
            $this->upsertMarket($currency['coin_symbol']);
        }
    }
}
