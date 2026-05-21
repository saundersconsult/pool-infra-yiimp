<?php

namespace app\jobs\coins;

use Yii;
use app\jobs\BaseJob;

/**
 * Refresh raw coin data and update the BTC/USD price from Bitstamp.
 *
 * @todo implement — calls CoinService::updateRawCoins()
 * Ports: updateRawcoins() + bitstamp_btcusd() — main.sh state 0 (~12 min).
 */
class UpdateRawCoinsJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        (new \app\services\CoinService())->updateRawCoins();
    }
}
