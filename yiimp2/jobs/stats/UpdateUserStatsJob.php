<?php

namespace app\jobs\stats;

use Yii;
use app\jobs\BaseJob;

/**
 * Aggregate per-user hashrate, share counts, and earnings summaries.
 *
 * @todo implement — calls UserService::updateUserStats() (or StatsService)
 * Ports: BackendUsersUpdate() from web/yaamp/core/backend/users.php
 * Called by loop2.sh every 60s.
 */
class UpdateUserStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\UserService())->updateUserStats();
    }
}
