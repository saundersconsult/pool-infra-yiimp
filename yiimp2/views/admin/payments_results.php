<?php

/** @var yii\web\View           $this        */
/** @var int                    $coinId      */
/** @var app\models\Accounts[]  $list        */
/** @var app\models\Coins[]     $coins       */
/** @var app\models\Coins|null  $coin        */
/** @var array<string,float>    $immatureMap */
/** @var array<int,float>       $failedMap   */

use yii\helpers\Html;

$conv     = Yii::$app->ConversionUtils;
$saveSort = $coinId ? 'false' : 'true';
?>
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
.currency { width:120px; max-width:180px; text-align:right; }
.red      { color: darkred; }
.actions  { width:120px; text-align:right; }
table.totals { margin-top:8px; margin-right:16px; }
table.totals th { text-align:left; width:100px; }
table.totals td { text-align:right; }
table.totals tr.red td { color:darkred; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        3: function(node, table, n) { return \$(node).attr('data'); }
    },
    widgets: ['zebra','filter','Storage','saveSort'],
    widgetOptions: {
        saveSort: {$saveSort},
        filter_saveFilters: {$saveSort},
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}"); ?>

<thead>
<tr>
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last block</th>
    <th data-sorter="currency" class="currency">Pool</th>
    <th data-sorter="currency" class="currency">Balance</th>
    <th data-sorter="currency" class="currency">Immature</th>
    <th data-sorter="currency" class="currency">Failed</th>
    <th data-sorter="" class="actions">Actions</th>
</tr>
</thead>
<tbody>
<?php
$totalBalance  = 0.0;
$totalImmature = 0.0;
$totalFailed   = 0.0;

foreach ($list as $user):
    $rowCoin    = $coins[$user->coinid] ?? null;
    $immKey     = $rowCoin ? "{$rowCoin->id}-{$user->id}" : "0-{$user->id}";
    $rawImmature = $immatureMap[$immKey] ?? 0.0;
    $rawFailed   = $failedMap[$user->id] ?? 0.0;

    $totalBalance  += (float) $user->balance;
    $totalImmature += $rawImmature;
    $totalFailed   += $rawFailed;

    $coinBalance = $rowCoin
        ? ($rowCoin->balance ? $conv->bitcoinvaluetoa($rowCoin->balance) : '')
        : '-';
    $balanceFmt  = $user->balance ? $conv->bitcoinvaluetoa($user->balance) : '';
    $immatureFmt = $rawImmature   ? $conv->bitcoinvaluetoa($rawImmature)   : '';
    $failedFmt   = $rawFailed     ? $conv->bitcoinvaluetoa($rawFailed)     : '';
?>
<tr class="ssrow">
    <td><?php if ($rowCoin): ?><img width="16" src="<?= Html::encode($rowCoin->image) ?>" alt=""><?php endif ?></td>
    <td>
        <?php if ($rowCoin): ?>
            <b><?= Html::a(Html::encode($rowCoin->name), ['/admin/coinwallet', 'id' => $rowCoin->id]) ?></b>
            &nbsp;(<?= Html::encode($rowCoin->symbol_show) ?>)
        <?php endif ?>
    </td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', '/?address=' . urlencode($user->username), ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $conv->datetoa2($user->last_earning) ?></td>
    <td class="currency"><?= Html::encode($coinBalance) ?></td>
    <td class="currency"><?= Html::encode($balanceFmt) ?></td>
    <td class="currency"><?= Html::encode($immatureFmt) ?></td>
    <td class="currency red"><?= Html::encode($failedFmt) ?></td>
    <td class="actions">
        <?php if ($rawFailed > 0): ?>
            <?= Html::a('[add to balance]', ['/admin/cancelUserPayment', 'id' => $user->id],
                ['title' => 'Restore failed payouts to user balance']) ?>
        <?php endif ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr>
    <th colspan="9">
        <?= count($list) ?> user<?= count($list) !== 1 ? 's' : '' ?>
        <?= (!$coinId && count($list) === 100) ? ' (limited to 100)' : '' ?>
    </th>
</tr>
</tfoot>
</table>

<?php if ($coinId && $coin):
    $symbol = Html::encode($coin->symbol);
?>
<div class="totals" align="right">
    <table class="totals">
        <tr>
            <th>Balances</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalBalance)) ?> <?= $symbol ?></td>
        </tr>
        <tr>
            <th>Immature</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalImmature)) ?> <?= $symbol ?></td>
        </tr>
        <?php if ($totalFailed > 0): ?>
        <tr class="red">
            <th>Failed</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalFailed)) ?> <?= $symbol ?></td>
        </tr>
        <tr>
            <td colspan="2">
                <?= Html::a(
                    'Reset all failed',
                    ['/admin/cancelUsersPayment', 'id' => $coinId],
                    [
                        'title'   => 'Add all failed payouts back to user balances',
                        'onclick' => 'return confirm("Restore all failed payouts for ' . $symbol . ' to user balances?")',
                    ]
                ) ?>
            </td>
        </tr>
        <?php endif ?>
    </table>
</div>
<?php endif ?>
