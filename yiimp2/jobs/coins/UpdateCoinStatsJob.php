<?php

namespace app\jobs\coins;

use Yii;
use app\jobs\BaseJob;

/**
 * Update per-coin statistics: block height, wallet info, connection status.
 *
 * @todo implement — calls CoinService::updateCoinStats()
 * Ports: BackendCoinsUpdate() from web/yaamp/core/backend/coins.php
 * Called by loop2.sh every 60s.
 */
class UpdateCoinStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\CoinService())->updateCoinStats();
    }
}
