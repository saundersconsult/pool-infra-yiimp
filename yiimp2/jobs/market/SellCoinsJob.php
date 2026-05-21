<?php

namespace app\jobs\market;

use Yii;
use app\jobs\BaseJob;

/**
 * Sell accumulated mined coins on exchanges to fund user payouts.
 *
 * @todo implement — calls MarketService::sellCoins()
 * Ports: TradingSellCoins() — main.sh state 5 (~12 min).
 */
class SellCoinsJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        (new \app\services\MarketService())->sellCoins();
    }
}
