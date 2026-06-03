<?php

/** @var yii\web\View              $this           */
/** @var string                    $algo           */
/** @var app\models\Workers[]      $workers        */
/** @var app\models\Accounts[]     $accounts       */
/** @var app\models\Coins[]        $coins          */
/** @var array<int,array>          $shareStatsMap  */
/** @var array<int,int>            $shareCountMap  */
/** @var array<int,int>            $workerBlockMap */
/** @var array<int,int>            $userBlockMap   */
/** @var float                     $totalRate      */

use yii\helpers\Html;

if ($algo === '') {
    echo '<p class="text-muted">No algo selected.</p>';
    return;
}

$conv = Yii::$app->ConversionUtils;
?>
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all"
           style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        6: function(node, table, n) { return \$(node).attr('data'); }
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
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="text">Pass</th>
    <th data-sorter="text">Client</th>
    <th data-sorter="text">Version</th>
    <th data-sorter="numeric">Hashrate</th>
    <th data-sorter="numeric">Diff</th>
    <th data-sorter="numeric">Shares</th>
    <th data-sorter="numeric">Bad</th>
    <th data-sorter="numeric">%</th>
    <th data-sorter="numeric">Found</th>
    <th data-sorter="text" width="30">Name</th>
    <th data-sorter="text"></th>
</tr>
</thead>
<tbody>
<?php foreach ($workers as $worker):
    $wid  = $worker->id;
    $uid  = $worker->userid ?: null;

    $stats      = $shareStatsMap[$wid]  ?? ['rate' => 0.0, 'bad' => 0.0];
    $workerRate = $stats['rate'];
    $workerBad  = $stats['bad'];
    $shares     = $shareCountMap[$wid]  ?? 0;
    $wBlocks    = $workerBlockMap[$wid] ?? 0;
    $uBlocks    = $uid ? ($userBlockMap[$uid] ?? 0) : 0;

    $sharePercent = ($totalRate > 0) ? (100.0 * $workerRate) / $totalRate : 0.0;
    $pctBad       = ($workerRate + $workerBad) > 0
        ? round($workerBad * 100 / ($workerRate + $workerBad), 3)
        : 0;
    $rateDisplay  = $workerRate ? $conv->Itoa2($workerRate) . 'H' : '-';

    $user = $uid ? ($accounts[$uid] ?? null) : null;
    $coin = $user ? ($coins[$user->coinid] ?? null) : null;

    $coinImg  = $coin ? Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]) : '';
    $coinLink = $coin ? Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]) : '';
    $coinsym  = $coin ? $coin->symbol : '';

    $displayName = $worker->worker ?? '';
    if ($displayName === '' && $user) {
        $displayName = $user->login ?? $user->username ?? '';
    }

    $dns = !empty($worker->dns) ? $worker->dns : $worker->ip;
    if (strlen((string) $dns) > 40) {
        $dns = '…' . substr($dns, strlen($dns) - 40);
    }
?>
<tr class="ssrow">
    <td width="20"><?= $coinImg ?></td>
    <td>
        <b><?= $coinLink ?></b>
        <?= $coinsym ? '&nbsp;(' . Html::encode($coinsym) . ')' : '<span class="text-muted">-</span>' ?>
    </td>
    <td><?= Html::a('<b>' . Html::encode($worker->name) . '</b>', '/?address=' . urlencode($worker->name), ['encode' => false]) ?></td>
    <td><?= Html::encode($worker->password) ?></td>
    <td title="<?= Html::encode($worker->ip) ?>"><?= Html::encode($dns) ?></td>
    <td><?= Html::encode($worker->version) ?></td>
    <td data="<?= (float) $workerRate ?>"><?= Html::encode($rateDisplay) ?></td>
    <td><?= (int) $worker->difficulty ?></td>
    <td><?= $shares ?></td>
    <td>
        <?php if ($workerBad > 0): ?>
            <?= $pctBad > 50 ? '<b>' . round($pctBad, 1) . '%</b>' : round($pctBad, 1) . '%' ?>
        <?php endif ?>
    </td>
    <td><?= number_format($sharePercent, 1, '.', '') ?>%</td>
    <td><?= $wBlocks ?> / <?= $uBlocks ?></td>
    <td><?= Html::encode($displayName) ?></td>
    <td><?= $user && $user->donation ? Html::encode($user->donation) . '&nbsp;%' : '' ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
