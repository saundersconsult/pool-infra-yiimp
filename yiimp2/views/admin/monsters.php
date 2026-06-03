<?php

/** @var yii\web\View              $this          */
/** @var array{0:int,1:string}[]   $rows          */
/** @var app\models\Accounts[]     $accounts      */
/** @var app\models\Coins[]        $coins         */
/** @var array<int,float>          $paidMap       */
/** @var array<int,int>            $workerCountMap */
/** @var array<int,int>            $shareCountMap */
/** @var array<int,int>            $blockCountMap */

use yii\helpers\Html;

$this->title = 'Monsters';
$conv = Yii::$app->ConversionUtils;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    headers: { 1: { sorter: false } },
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}"); ?>

<thead>
<tr>
    <th>UID</th>
    <th></th>
    <th>Coin</th>
    <th>Address</th>
    <th></th>
    <th>Last</th>
    <th>Blocks</th>
    <th>Balance</th>
    <th>Total Paid</th>
    <th>Miners</th>
    <th>Shares</th>
    <th></th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as [$userId, $what]):
    $user = $accounts[$userId] ?? null;
    if (!$user) continue;

    $coin       = $coins[$user->coinid] ?? null;
    $paidRaw    = $paidMap[$userId]        ?? 0.0;
    $blockCount = $blockCountMap[$userId]  ?? 0;
    $minerCount = $workerCountMap[$userId] ?? 0;
    $shareCount = $shareCountMap[$userId]  ?? 0;

    $balance = $conv->bitcoinvaluetoa($user->balance);
    $paid    = $conv->bitcoinvaluetoa($paidRaw);
?>
<tr class="ssrow">
    <td width="24"><?= (int) $user->id ?></td>
    <?php if ($coin): ?>
        <td width="16"><img src="<?= Html::encode($coin->image) ?>" width="16" alt=""></td>
        <td width="48"><b><?= Html::a(Html::encode($coin->symbol), ['/admin/coinwallet', 'id' => $coin->id]) ?></b></td>
    <?php else: ?>
        <td width="60" colspan="2"></td>
    <?php endif ?>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>',
            '/?address=' . urlencode($user->username), ['encode' => false]) ?></td>
    <td><?= Html::encode($what) ?></td>
    <td><?= $conv->datetoa2($user->last_earning) ?></td>
    <td><?= $blockCount ?></td>
    <td><?= Html::encode($balance) ?></td>
    <td><?= $paidRaw > 0.01 ? '<b>' . Html::encode($paid) . '</b>' : Html::encode($paid) ?></td>
    <td><?= $minerCount ?></td>
    <td><?= $shareCount ?></td>
    <?php if ($user->is_locked): ?>
        <td>locked</td>
        <td><?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?></td>
    <?php else: ?>
        <td></td>
        <td><?= Html::a('block', ['/admin/blockuser', 'wallet' => $user->username]) ?></td>
    <?php endif ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
