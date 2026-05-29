<?php

namespace app\exchanges\drivers;

use app\exchanges\ExchangeDriver;
use app\models\Balances;

class CexIoDriver extends ExchangeDriver
{
    public function name(): string { return 'cexio'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://cex.io/trade/{$symbol}-{$base}"; }
    public function supportsBalance(): bool { return true; }

    public function syncBalance(): void
    {
        if ($this->isDisabled() || !function_exists('cexio_api_user')) return;

        $row = Balances::find()->where(['name' => $this->name()])->one();
        if (!$row) return;

        $data = cexio_api_user('balance');
        if (!is_array($data)) return;

        $b = $data['BTC'] ?? [];
        $row->balance = (float) ($b['available'] ?? 0);
        $row->onsell  = (float) ($b['orders'] ?? 0);
        $row->save();
    }
}
