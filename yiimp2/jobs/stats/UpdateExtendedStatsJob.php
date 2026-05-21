<?php

namespace app\jobs\stats;

use Yii;
use app\jobs\BaseJob;

/**
 * Update extended/historical statistics snapshots (15-minute bucketed stats).
 *
 * @todo implement — calls StatsService::updateExtendedStats()
 * Ports: BackendStatsUpdate2() from web/yaamp/core/backend/stats.php
 * Called by loop2.sh every 5 min.
 */
class UpdateExtendedStatsJob extends BaseJob
{
    public int $intervalSeconds = 300;

    protected function perform(): void
    {
        (new \app\services\StatsService())->updateExtendedStats();
    }
}
