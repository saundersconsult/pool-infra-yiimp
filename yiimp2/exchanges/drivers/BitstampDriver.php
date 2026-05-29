<?php

namespace app\exchanges\drivers;

use app\exchanges\ExchangeDriver;
use app\models\Balances;

class BitstampDriver extends ExchangeDriver
{
    public function name(): string { return 'bitstamp'; }
    public function supportsBalance(): bool { return true; }

    public function syncBalance(): void
    {
        if ($this->isDisabled() || !function_exists('bitstamp_api_user')) return;

        $row = Balances::find()->where(['name' => $this->name()])->one();
        if (!$row) return;

        $data = bitstamp_api_user('balance');
        if (!is_array($data)) return;

        $row->balance = ($data['btc_balance'] ?? 0.0) - ($data['btc_reserved'] ?? 0.0);
        $row->onsell  = $data['btc_reserved'] ?? 0.0;
        $row->save();
    }
}
