<?php

/** @var yii\web\View                    $this         */
/** @var app\models\Markets[]            $stuckMarkets */
/** @var app\models\Orders[]             $orders       */
/** @var app\models\Exchange_deposit[]   $deposits     */
/** @var app\models\Coins[]              $coins        */

use yii\helpers\Html;

$conv  = Yii::$app->ConversionUtils;
$utils = Yii::$app->YiimpUtils;

// ── Stuck markets ─────────────────────────────────────────────────────────────
if ($stuckMarkets):
?>
<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th>
    <th>Exchange</th>
    <th>Sent</th>
    <th>Traded</th>
    <th></th>
</tr></thead>
<tbody>
<?php foreach ($stuckMarkets as $market):
    $coin = $coins[$market->coinid] ?? null;
    if (!$coin) continue;
    $marketUrl = $utils->getMarketUrl($coin, $market->name);
?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"), ['/admin/coin', 'id' => $coin->id]) ?></b></td>
    <td><b><?= Html::a(Html::encode($market->name), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($market->lastsent) ?> ago</td>
    <td><?= $conv->datetoa2($market->lasttraded) ?> ago</td>
    <td><?= Html::a('[clear]', ['/admin/clearmarket', 'id' => $market->id]) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endif ?>

<?php
// ── Open orders ───────────────────────────────────────────────────────────────
$totalValue = 0.0;
$totalBid   = 0.0;
?>
<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th>
    <th>Exchange</th>
    <th>Created</th>
    <th>Quantity</th>
    <th>Ask</th>
    <th>Bid</th>
    <th>Value</th>
    <th></th>
</tr></thead>
<tbody>
<?php foreach ($orders as $order):
    $coin = $coins[$order->coinid] ?? null;
    if (!$coin) continue;
    $marketUrl = $utils->getMarketUrl($coin, $order->market);

    $value    = (float) $order->amount * (float) $order->price;
    $bidValue = (float) $order->amount * (float) $order->bid;
    $totalValue += $value;
    $totalBid   += $bidValue;
    $bidPct = $value > 0 ? round(($value - $bidValue) / $value * 100, 1) : 0;
?>
<tr class="ssrow">
    <td width="16"><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode($coin->name), ['/admin/coin', 'id' => $coin->id]) ?></b>&nbsp;(<?= Html::encode($coin->symbol) ?>)</td>
    <td><b><?= Html::a(Html::encode($order->market), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($order->created) ?> ago</td>
    <td><?= Html::encode((string) $order->amount) ?></td>
    <td><?= $conv->bitcoinvaluetoa($order->price) ?></td>
    <td><?= $conv->bitcoinvaluetoa($order->bid) ?> (<?= $bidPct ?>%)</td>
    <td><?= $bidValue > 0.01 ? '<b>' . $conv->bitcoinvaluetoa($bidValue) . '</b>' : $conv->bitcoinvaluetoa($bidValue) ?></td>
    <td>
        <?= Html::a('[cancel]', ['/admin/cancelorder', 'id' => $order->id], ['title' => 'Cancel on exchange']) ?>
        <?= Html::a('[clear]',  ['/admin/clearorder',  'id' => $order->id], ['title' => 'Remove from DB only']) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<?php
$totalBidPct = $totalValue > 0 ? round(($totalValue - $totalBid) / $totalValue * 100, 1) : '';
?>
<tfoot>
<tr>
    <td></td>
    <td><b>Total</b></td>
    <td colspan="3"></td>
    <td><b><?= $conv->bitcoinvaluetoa($totalValue) ?></b></td>
    <td><b><?= $conv->bitcoinvaluetoa($totalBid) ?> (<?= $totalBidPct ?>%)</b></td>
    <td></td>
</tr>
</tfoot>
</table>

<?php
// ── Exchange deposits ─────────────────────────────────────────────────────────
?>
<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th>
    <th>Market</th>
    <th>Created</th>
    <th>Quantity</th>
    <th>Estimate</th>
    <th>Sold Price</th>
    <th>Value</th>
    <th></th>
</tr></thead>
<tbody>
<?php foreach ($deposits as $dep):
    $coin = $coins[$dep->coinid] ?? null;
    if (!$coin) continue;
    $marketUrl = $utils->getMarketUrl($coin, $dep->market);

    $price = $dep->price
        ? $conv->bitcoinvaluetoa($dep->price)
        : $conv->bitcoinvaluetoa($coin->price);
    $total = $dep->price
        ? $conv->bitcoinvaluetoa($dep->quantity * $dep->price)
        : $conv->bitcoinvaluetoa($dep->quantity * $coin->price);
    $totalRaw = $dep->price
        ? (float) $dep->quantity * (float) $dep->price
        : (float) $dep->quantity * (float) $coin->price;

    $trStyle = $dep->status === 'waiting' ? " style='background-color:#e0d3e8;'" : " class='ssrow'";
?>
<tr<?= $trStyle ?>>
    <td width="16"><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode("{$coin->name}"), ['/admin/coinwallet', 'id' => $coin->id]) ?></b>&nbsp;(<?= Html::encode($coin->symbol) ?>)</td>
    <td><b><?= Html::a(Html::encode($dep->market), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($dep->send_time) ?> ago</td>
    <td><?= Html::encode((string) $dep->quantity) ?></td>
    <td><?= $conv->bitcoinvaluetoa($dep->price_estimate) ?></td>
    <td><?= $price ?></td>
    <td><?= $totalRaw > 0.01 ? "<b>{$total}</b>" : $total ?></td>
    <td>
        <?php if ($dep->status === 'waiting'): ?>
            <?= Html::a('[del]', ['/admin/deleteexchangedeposit', 'id' => $dep->id]) ?>
        <?php endif ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
