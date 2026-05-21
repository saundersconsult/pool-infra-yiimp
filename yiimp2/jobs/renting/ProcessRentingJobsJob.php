<?php

namespace app\jobs\renting;

use Yii;
use app\jobs\BaseJob;

/**
 * Process the internal renting job activation queue: start/stop stratum connections
 * for active renting jobs based on price eligibility.
 *
 * @todo implement — calls a method to be added to RentingService
 * Ports: BackendProcessList() — called by blocks.sh every 20s.
 */
class ProcessRentingJobsJob extends BaseJob
{
    public int $intervalSeconds = 30;

    protected function perform(): void
    {
        // TODO: (new \app\services\RentingService())->processJobList();
        Yii::warning('ProcessRentingJobsJob: not yet implemented (pending BackendProcessList port)', 'queue');
    }
}
