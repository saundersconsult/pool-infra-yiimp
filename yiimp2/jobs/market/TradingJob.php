<?php

namespace app\jobs\market;

use Yii;
use app\jobs\BaseJob;

/**
 * Execute automated trading on all configured exchanges
 * (Binance, KuCoin, Yobit, Nestex, Nonkyc, Tradeogre).
 * Production-only.
 *
 * @todo implement — calls MarketService::doTrading()
 * Ports: doBinanceTrading() + doKuCoinTrading() + doYobitTrading() + others
 * main.sh state 2 (~12 min, prod only).
 */
class TradingJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        if (!defined('YAAMP_PRODUCTION') || !YAAMP_PRODUCTION) {
            return;
        }
        // TODO: (new \app\services\MarketService())->doTrading();
        Yii::warning('TradingJob: not yet implemented (pending MarketService)', 'queue');
    }
}
