<?php

namespace app\jobs\system;

use Yii;
use app\jobs\BaseJob;

/**
 * Evaluate notification rules and dispatch alerts to users.
 *
 * @todo implement — calls NotificationService::checkRules()
 * Ports: NotifyCheckRules() — main.sh state 7 (~12 min).
 */
class NotificationsJob extends BaseJob
{
    public int $intervalSeconds = 900;

    protected function perform(): void
    {
        (new \app\services\NotificationService())->checkRules();
    }
}
