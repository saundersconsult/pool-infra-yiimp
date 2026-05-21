<?php

namespace app\jobs\stats;

use Yii;
use app\jobs\BaseJob;

/**
 * Update pool-wide hashrate, share, and earnings statistics.
 *
 * @todo implement — calls StatsService::updatePoolStats()
 * Ports: BackendStatsUpdate() from web/yaamp/core/backend/stats.php
 * Called by loop2.sh every 60s.
 */
class UpdatePoolStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\StatsService())->updatePoolStats();
    }
}
