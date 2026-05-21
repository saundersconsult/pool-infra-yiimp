<?php

namespace app\jobs\system;

use Yii;
use app\jobs\BaseJob;

/**
 * Update benchmark chip data for algo profitability calculations.
 *
 * @todo implement — calls BenchService::updateChips()
 * Ports: BenchUpdateChips() — main.sh state 7 (~12 min).
 */
class BenchUpdateJob extends BaseJob
{
    public int $intervalSeconds = 900;

    protected function perform(): void
    {
        (new \app\services\BenchService())->updateChips();
    }
}
