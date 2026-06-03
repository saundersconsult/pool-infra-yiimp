<?php

/** @var yii\web\View           $this          */
/** @var string                 $symbol        */
/** @var app\models\Accounts[]  $users         */
/** @var app\models\Coins|null  $coin          */
/** @var app\models\Coins[]     $coins         */
/** @var array<int,float>       $rateMap       */
/** @var array<int,float>       $badRateMap    */
/** @var array<int,int>         $minerCountMap */
/** @var array<int,array>       $blockDataMap  */
/** @var array<int,float>       $paidMap       */

use yii\helpers\Html;

$conv = Yii::$app->ConversionUtils;
?>
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
.red { color: darkred; }
tr.ssrow.filtered { display: none; }
.actions a { margin-right: 4px; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        4: function(node, table, cellIndex) { return \$(node).attr('data'); },
        6: function(node, table, cellIndex) { return \$(node).attr('data'); }
    },
    widgets: ['zebra','filter','Storage','saveSort'],
    widgetOptions: {
        saveSort: true,
        filter_saveFilters: false,
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}"); ?>

<thead>
<tr>
    <th data-sorter="numeric">UID</th>
    <th data-sorter="false">&nbsp;</th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last</th>
    <th data-sorter="numeric" align="right">Workers</th>
    <th data-sorter="numeric" align="right">Hashrate</th>
    <th data-sorter="numeric" align="right">Bad&nbsp;%</th>
    <th data-sorter="numeric" align="right">Blocks</th>
    <th data-sorter="numeric" align="right">Diff/Paid</th>
    <th data-sorter="currency" align="right">Balance</th>
    <th data-sorter="currency" align="right">Total Paid</th>
    <th data-sorter="false" class="actions" align="right" width="150">Actions</th>
</tr>
</thead>
<tbody>
<?php
$totalBalance = 0.0;
$totalPaid    = 0.0;

foreach ($users as $user):
    $uid        = $user->id;
    $userRate   = $rateMap[$uid]        ?? 0.0;
    $userBad    = $badRateMap[$uid]     ?? 0.0;
    $minerCount = $minerCountMap[$uid]  ?? 0;
    $blockData  = $blockDataMap[$uid]   ?? ['cnt' => 0, 'diff_sum' => 0.0];
    $paid       = $paidMap[$uid]        ?? 0.0;

    $blockCount = $blockData['cnt'];
    $blockDiff  = ($paid > 0 && $blockCount > 0)
        ? round($blockData['diff_sum'] / $paid, 3)
        : '?';

    $pctBad  = $userRate ? round($userBad * 100 / $userRate, 3) : 0;

    $userCoin = $coins[$user->coinid] ?? null;
    $coinImg  = $userCoin ? Html::img(Html::encode($userCoin->image), ['width' => 16, 'alt' => Html::encode($userCoin->symbol)]) : '';
    $coinLink = $userCoin ? Html::a(Html::encode($userCoin->symbol), ['/admin/coinwallet', 'id' => $userCoin->id]) : '';

    $totalBalance += (float) $user->balance;
    $totalPaid    += $paid;
?>
<tr class="ssrow">
    <td width="24"><?= (int) $uid ?></td>
    <td width="16"><?= $coinImg ?></td>
    <td width="48"><b><?= $coinLink ?></b></td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', ['/?address=' . urlencode($user->username)], ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $conv->datetoa2($user->last_earning) ?></td>
    <td align="right"><?= $minerCount ?></td>
    <td width="32" data="<?= (int) $userRate ?>" align="right">
        <?= $userRate ? Html::encode($conv->Itoa2($userRate)) : '' ?>
    </td>
    <td width="32" align="right">
        <?= $pctBad ? round($pctBad, 1) . '&nbsp;%' : '' ?>
    </td>
    <td align="right"><?= $blockCount ?></td>
    <td align="right"><?= $userRate ? Html::encode((string) $blockDiff) : '' ?></td>
    <td align="right"><?= Html::encode($conv->bitcoinvaluetoa($user->balance)) ?></td>
    <td align="right"><?= Html::encode($conv->bitcoinvaluetoa($paid)) ?></td>
    <td class="actions" align="right">
        <?php if ($user->logtraffic): ?>
            <?= Html::a('unwatch', ['/admin/loguser', 'id' => $uid, 'en' => 0]) ?>
        <?php else: ?>
            <?= Html::a('watch',   ['/admin/loguser', 'id' => $uid, 'en' => 1]) ?>
        <?php endif ?>

        <?php if ($user->is_locked): ?>
            <?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?>
        <?php else: ?>
            <?= Html::a('block',   ['/admin/blockuser',   'wallet' => $user->username]) ?>
        <?php endif ?>

        <?= Html::a('<span class="red">BAN</span>', ['/admin/banuser', 'id' => $uid], [
            'encode'  => false,
            'onclick' => 'return confirm(' . json_encode('Ban ' . $user->username . '?') . ')',
        ]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>

<?php
$balanceFmt = $conv->bitcoinvaluetoa($totalBalance);
$paidFmt    = $conv->bitcoinvaluetoa($totalPaid);
$colspan    = 7;
?>
<tfoot>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Users Total (<?= count($users) ?>)</b></th>
    <?php for ($c = 0; $c < $colspan; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($balanceFmt) ?></b></th>
    <th align="right"><b><?= Html::encode($paidFmt) ?></b></th>
    <th></th>
</tr>
<?php if ($coin): ?>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Wallet Balance</b></th>
    <?php for ($c = 0; $c < $colspan; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?></b></th>
    <th colspan="2"></th>
</tr>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Wallet Profit</b></th>
    <?php for ($c = 0; $c < $colspan; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($conv->bitcoinvaluetoa($coin->balance - $totalBalance)) ?></b></th>
    <th colspan="2"></th>
</tr>
<?php endif ?>
</tfoot>
</table>
