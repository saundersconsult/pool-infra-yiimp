<?php

namespace app\jobs\market;

use Yii;
use app\jobs\BaseJob;

/**
 * Fetch current market prices for all active coins and watch for price alerts.
 *
 * @todo implement — calls MarketService::updatePrices()
 * Ports: BackendPricesUpdate() + BackendWatchMarkets() — main.sh state 3 (~12 min).
 */
class UpdatePricesJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        $svc = new \app\services\MarketService();
        $svc->updatePrices();
        $svc->watchMarkets();
    }
}
