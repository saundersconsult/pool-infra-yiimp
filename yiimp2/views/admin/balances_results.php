<?php

/** @var yii\web\View            $this       */
/** @var string                  $exch       */
/** @var app\models\Markets[]    $markets    */
/** @var app\models\Mining       $mining     */
/** @var app\models\Coins[]      $coins      */
/** @var array<string,float>     $btcRateMap */

use yii\helpers\Html;

$conv   = Yii::$app->ConversionUtils;
$utils  = Yii::$app->YiimpUtils;
$usdbtc = (float) $mining->usdbtc;
?>
<style type="text/css">
td.disabled { color: gray; }
table.dataGrid th.ops, td.ops { text-align: right; padding-right: 16px; }
th.btc, td.btc { width: 120px; max-width: 120px; }
th.addr, td.addr { width: 300px; max-width: 300px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
</style>
<br/>
<table class="dataGrid">
<thead>
<tr>
    <th width="20"></th>
    <th>Name</th>
    <th>Market</th>
    <th class="btc">Bid</th>
    <th class="btc">Ask</th>
    <th title="last price update">Updated</th>
    <th class="btc">Locked</th>
    <th class="btc">Total</th>
    <th class="btc">BTC</th>
    <th>USD</th>
    <th title="last balance update">Updated</th>
    <th class="addr">Deposit</th>
    <th>Status</th>
    <th class="ops">API</th>
</tr>
</thead>
<tbody>
<?php
$totalsBtc    = 0.0;
$totalsUsd    = 0.0;
$seen         = [];
$dustThreshold = 0.00000001; // 1 satoshi — hide markets below this BTC value

foreach ($markets as $market):
    if (!$market->pricetime) continue;

    $coin = $coins[$market->coinid] ?? null;
    if (!$coin) continue;

    $base      = $market->base_coin ?: 'BTC';
    $btcFactor = $btcRateMap[$base] ?? 0.0;
    $total     = (float) $market->balance + (float) $market->ontrade;

    // Fall back to coin prices when the market hasn't been priced yet
    $rawPrice  = (float) ($market->price  ?: $coin->price);
    $rawPrice2 = (float) ($market->price2 ?: $coin->price2);

    if ($total * $rawPrice2 * $btcFactor < $dustThreshold) continue;

    $symbol = !empty($coin->symbol2) ? $coin->symbol2 : $coin->symbol;
    if (isset($seen[$symbol])) continue;
    $seen[$symbol] = true;

    $coinImg   = Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]);
    $marketUrl = $utils->getMarketUrl($coin, $market->name);

    $btcValueRaw = $total * $rawPrice * $btcFactor;
    $btcValue    = $conv->bitcoinvaluetoa($btcValueRaw);
    $usdValue    = round($btcValueRaw * $usdbtc, 2);
    $bold        = $btcValueRaw > 0.1;

    $ontrade = $market->ontrade ?: '-';
    $ptime   = $market->pricetime   ? $conv->datetoa2($market->pricetime)   . ' ago' : 'never';
    $btime   = $market->balancetime ? $conv->datetoa2($market->balancetime) . ' ago' : 'never';
    $tdClass = $market->disabled ? 'disabled' : '';

    $status = $market->disabled > 0 ? "market disabled ({$market->disabled})" : 'OK';
    if (!$coin->enable) $status = 'coin disabled';

    $totalsBtc += $btcValueRaw;
    $totalsUsd += $usdValue;
?>
<tr class="ssrow">
    <td width="16" class="<?= Html::encode($tdClass) ?>"><?= $coinImg ?></td>
    <td><b><a href="/admin/coinwallet?id=<?= $coin->id ?>"><?= Html::encode($symbol) ?></a></b></td>
    <td><b><a href="<?= Html::encode($marketUrl) ?>" target="_blank"><?= Html::encode($market->name) ?></a></b></td>
    <td class="btc"><?= $conv->bitcoinvaluetoa($rawPrice) ?></td>
    <td class="btc"><?= $conv->bitcoinvaluetoa($rawPrice2) ?></td>
    <td><?= $ptime ?></td>
    <td><?= Html::encode((string) $ontrade) ?></td>
    <td><?= $total ?></td>
    <td><?= $bold ? "<b>{$btcValue}</b>" : $btcValue ?></td>
    <td><?= $bold ? '<b>' . sprintf('%.2f', $usdValue) . '</b>' : sprintf('%.2f', $usdValue) ?></td>
    <td><?= $btime ?></td>
    <td class="addr"><?= Html::encode((string) $market->deposit_address) ?></td>
    <td><?= Html::encode($status) ?></td>
    <td class="ops"><a href="/admin/balanceUpdate?market=<?= $market->id ?>">update ticker</a></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr>
    <th colspan="8">Total</th>
    <th><b><?= $conv->bitcoinvaluetoa($totalsBtc) ?></b></th>
    <th><b><?= round($totalsUsd, 2) ?></b></th>
    <th></th><th></th><th></th><th></th>
</tr>
</tfoot>
</table>
