<?php

/** @var yii\web\View              $this     */
/** @var int                       $coinId   */
/** @var app\models\Earnings[]     $earnings */
/** @var app\models\Coins[]        $coins    */
/** @var app\models\Accounts[]     $accounts */
/** @var app\models\Blocks[]       $blocks   */
/** @var app\models\Coins|null     $coin     */
/** @var float                     $cleared  */

use yii\helpers\Html;

$saveSort = $coinId ? 'false' : 'true';
?>
<div align="right" style="margin-top: -14px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
.actions { width: 120px; text-align: right; }
table.dataGrid a.red { color: darkred; }
table.totals { margin-top: 8px; margin-left: 16px; display: inline-block; }
table.totals th { text-align: left; width: 100px; }
table.totals td { text-align: right; }
table.totals tr.red td { color: darkred; }
.page .footer { width: auto; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	widgets: ['zebra','filter','Storage','saveSort'],
	textExtraction: {
		6: function(node, table, n) { return \$(node).attr('data'); },
		7: function(node, table, n) { return \$(node).attr('data'); }
	},
	widgetOptions: {
		saveSort: {$saveSort},
		filter_saveFilters: {$saveSort},
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}"); ?>

<thead>
<tr>
<th data-sorter="" width="20"></th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="currency">Quantity</th>
<th data-sorter="currency">BTC</th>
<th data-sorter="numeric">Block</th>
<th data-sorter="">Status</th>
<th data-sorter="numeric">Sent</th>
<th data-sorter="" class="actions">Actions</th>
</tr>
</thead><tbody>

<?php
$total = 0.0; $totalImmat = 0.0; $totalStake = 0.0;

foreach ($earnings as $earning):
    $coin    = $coins[$earning->coinid]    ?? null;  if (!$coin)    continue;
    $user    = $accounts[$earning->userid] ?? null;  if (!$user)    continue;
    $block   = $blocks[$earning->blockid]  ?? null;  if (!$block)   continue;

    $t1 = Yii::$app->ConversionUtils->datetoa2($earning->create_time) . ' ago';
    $t2 = Yii::$app->ConversionUtils->datetoa2($earning->mature_time);
    if ($t2) $t2 = '+' . $t2;

    if ($block->category === 'immature') {
        $total      += (float) $earning->amount;
        $totalImmat += (float) $earning->amount;
    } elseif ($block->category === 'generate') {
        $total += (float) $earning->amount;
    } elseif ($block->category === 'stake' || $block->category === 'generated') {
        $totalStake += (float) $earning->amount;
    }
?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]) ?></td>
    <td><b><?= Html::a(Html::encode($coin->name), ['/admin/coin', 'id' => $coin->id]) ?></b>&nbsp;(<?= Html::encode($coin->symbol_show) ?>)</td>
    <td><b><?= Html::a(Html::encode($user->username), ['/?address=' . urlencode($user->username)]) ?></b></td>
    <td><?= Yii::$app->ConversionUtils->bitcoinvaluetoa($earning->amount) ?></td>
    <td><?= Yii::$app->ConversionUtils->bitcoinvaluetoa($earning->amount * $earning->price) ?></td>
    <td><?= (int) $block->height ?></td>
    <td data="<?= (int) $block->height ?>"><?= Html::encode($block->category) ?> (<?= (int) $block->confirmations ?>)</td>
    <td data="<?= (int) $earning->create_time ?>"><?= $t1 ?> <?= $t2 ?></td>
    <td class="actions">
        <?= Html::a('clear',  ['/admin/clearearning',  'id' => $earning->id]) ?>
        <?= Html::a('delete', ['/admin/deleteearning', 'id' => $earning->id], ['class' => 'red']) ?>
    </td>
</tr>
<?php endforeach ?>

</tbody>
<tfoot>
<tr><th colspan="9">
    <?= count($earnings) ?> records<?= count($earnings) >= 1500 ? ' (limit reached)' : '' ?>
</th></tr>
</tfoot>
</table>

<?php if ($coinId && $coin): ?>
<?php
    $symbol   = Html::encode($coin->symbol);
    $feePct   = Yii::$app->YiimpUtils->yiimp_fee($coin->algo);
    $totalFees = ($total / ((100 - $feePct) / 100.0)) - $total;
    $exchange  = $total - $totalImmat;
    $cu        = Yii::$app->ConversionUtils;
?>
<div class="totals" align="right">
    <table class="totals">
        <tr><th>Immature</th>                  <td><?= $cu->bitcoinvaluetoa($totalImmat)                          ?> <?= $symbol ?></td></tr>
        <tr><th>Total owed</th>                <td><?= $cu->bitcoinvaluetoa($total)                               ?> <?= $symbol ?></td></tr>
        <tr><th>Pool Fees <?= round($feePct, 1) ?>%</th><td><?= $cu->bitcoinvaluetoa($totalFees)                 ?> <?= $symbol ?></td></tr>
        <?php if ($coin->rpcencoding === 'POS'): ?>
        <tr><th>Stake</th>                     <td><?= $cu->bitcoinvaluetoa($totalStake)                          ?> <?= $symbol ?></td></tr>
        <?php endif ?>
    </table>
    <table class="totals">
        <tr><th>Balance</th>                   <td><?= $cu->bitcoinvaluetoa($coin->balance)                       ?> <?= $symbol ?></td></tr>
        <tr><th>Cleared</th>                   <td><?= $cu->bitcoinvaluetoa($cleared)                             ?> <?= $symbol ?></td></tr>
        <tr title="Available = (Balance - Cleared - in exchange)">
            <th>Available</th>                 <td><?= $cu->bitcoinvaluetoa($coin->balance - $exchange - $cleared) ?> <?= $symbol ?></td>
        </tr>
    </table>
</div>
<?php endif ?>
