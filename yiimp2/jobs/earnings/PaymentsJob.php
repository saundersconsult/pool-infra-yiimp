<?php

namespace app\jobs\earnings;

use Yii;
use app\jobs\BaseJob;
use app\models\Mining;

/**
 * Execute the full payment sequence: backup, send coins to users, clean database.
 * Runs only in production and only when YIIMP_PAYMENTS_FREQ seconds have elapsed
 * since the last payout. Acquires 'balances_locked' for the duration.
 *
 * @todo implement — calls PaymentService::doPayments()
 * Ports: BackendDoBackup() + BackendPayments() + BackendCleanDatabase() from payment.php
 * Triggered from main.sh after the 8-state cycle when payment time is due.
 */
class PaymentsJob extends BaseJob
{
    /** Check frequently; actual payment only runs when frequency condition is met. */
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        if (!defined('YIIMP_PRODUCTION') || !YIIMP_PRODUCTION) {
            return;
        }

        $freq   = defined('YIIMP_PAYMENTS_FREQ') ? (int) YIIMP_PAYMENTS_FREQ : 14400;
        $mining = Mining::find()->one();

        if (!$mining) {
            Yii::error('PaymentsJob: mining state row not found; refusing to run payments.');
            return;
        }

        // mining.last_payout is the persistent authoritative payment-cycle timestamp.
        // Both the scheduler and UI must derive the next payout from this value.
        $lastPayout = (int) $mining->last_payout;

        if ($lastPayout + $freq > time()) {
            return;
        }

        Yii::$app->cache->set('balances_locked', true, 300);
        try {
            $svc = new \app\services\PaymentService();
            $svc->doBackup();
            $svc->doPayments();
            $svc->cleanDatabase();

            // Persist the payment-cycle anchor so it survives application/container
            // restarts and remains identical to the timestamp displayed by the UI.
            $mining->last_payout = time();
            if (!$mining->save(false, ['last_payout'])) {
                throw new \RuntimeException('PaymentsJob: failed to persist last_payout.');
            }
        } finally {
            Yii::$app->cache->delete('balances_locked');
        }
    }
}
