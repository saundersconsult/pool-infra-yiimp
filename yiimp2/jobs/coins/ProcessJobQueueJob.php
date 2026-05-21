<?php

namespace app\jobs\coins;

use Yii;
use app\jobs\BaseJob;

/**
 * Process the internal coin action queue (jobs table): start/stop stratums, etc.
 *
 * @todo implement — calls CoinService::processJobQueue()
 * Ports: BackendProcessList() from web/yaamp/core/backend/coins.php
 * Called by blocks.sh every 20s.
 */
class ProcessJobQueueJob extends BaseJob
{
    public int $intervalSeconds = 30;

    protected function perform(): void
    {
        // TODO: (new \app\services\CoinService())->processJobQueue();
        Yii::warning('ProcessJobQueueJob: not yet implemented (pending CoinService)', 'queue');
    }
}
