<?php

namespace app\jobs\system;

use Yii;
use app\jobs\BaseJob;

/**
 * Periodic housekeeping: quick database cleanup of expired shares, old stats, etc.
 * Runs independently of the payment cycle at a low frequency.
 *
 * @todo implement — calls PaymentService::quickClean() / cleanDatabase()
 * Ports: BackendQuickClean() — main.sh default case (after state 7 cycle).
 */
class MaintenanceJob extends BaseJob
{
    public int $intervalSeconds = 3600;

    protected function perform(): void
    {
        (new \app\services\PaymentService())->quickClean();
    }
}
