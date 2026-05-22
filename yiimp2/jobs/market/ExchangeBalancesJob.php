<?php

namespace app\jobs\market;

use Yii;
use app\jobs\BaseJob;

/**
 * Fetch current balances from all configured exchange accounts
 * (Bitstamp, CexIO, Kraken, Poloniex) and store them for trading logic.
 * Production-only.
 *
 * @todo implement — calls MarketService::updateExchangeBalances()
 * Ports: getBitstampBalances() + getCexIoBalances() + doKrakenTrading() + doPoloniexTrading()
 * main.sh state 1 (~12 min, prod only).
 */
class ExchangeBalancesJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        if (!defined('YIIMP_PRODUCTION') || !YIIMP_PRODUCTION) {
            return;
        }
        // TODO: (new \app\services\MarketService())->updateExchangeBalances();
        Yii::warning('ExchangeBalancesJob: not yet implemented (pending MarketService)', 'queue');
    }
}
