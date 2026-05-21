<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;

$algo = Yii::$app->session->get('yaamp-algo', '');
if ($algo === '') {
    echo '<p class="text-muted">No algo selected.</p>';
    return;
}

$conv = Yii::$app->ConversionUtils;
$util = Yii::$app->YiimpUtils;
$db   = Yii::$app->db;

$workers = Workers::find()
    ->where(['algo' => $algo])
    ->orderBy('name')
    ->all();

// Pre-compute total rate so we can show per-worker share percentages
$totalRate = 0.0;
foreach ($workers as $w) {
    $totalRate += $util->worker_rate($w->id);
}

?>
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all"
           style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
</style>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
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
}");
?>

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
    $workerRate  = $util->worker_rate($worker->id);
    $workerBad   = $util->worker_rate_bad($worker->id);
    $sharePercent = ($totalRate > 0) ? (100.0 * $workerRate) / $totalRate : 0.0;
    $pctBad      = ($workerRate + $workerBad) > 0
        ? round($workerBad * 100 / ($workerRate + $workerBad), 3)
        : 0;
    $rateDisplay = $workerRate ? $conv->Itoa2($workerRate) . 'H' : '-';

    // Resolve user + coin
    $user     = null;
    $coin     = null;
    $coinImg  = '';
    $coinLink = '';
    $coinsym  = '';
    $donationPct = null;

    if ($worker->userid) {
        $user = Accounts::findOne((int) $worker->userid);
        if ($user) {
            $coin = Coins::findOne((int) $user->coinid);
            if ($coin) {
                $coinsym  = $coin->symbol;
                $coinImg  = Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]);
                $coinLink = Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]);
            }
            $donationPct = $user->donation ?: null;
        }
    }

    // Display name: prefer worker.worker, fall back to user login
    $displayName = $worker->worker ?? '';
    if ($displayName === '' && $user) {
        $displayName = $user->login ?? $user->username ?? '';
    }

    // Truncate long DNS/IP to last 40 chars
    $dns = !empty($worker->dns) ? $worker->dns : $worker->ip;
    if (strlen((string) $dns) > 40) {
        $dns = '…' . substr($dns, strlen($dns) - 40);
    }

    // Share and block counts for this worker
    $shares = (int) $db->createCommand(
        "SELECT COUNT(id) FROM shares WHERE workerid = :wid AND algo = :algo",
        [':wid' => $worker->id, ':algo' => $algo]
    )->queryScalar();

    $workerBlocks = (int) $db->createCommand(
        "SELECT COUNT(id) FROM blocks WHERE workerid = :wid AND algo = :algo",
        [':wid' => $worker->id, ':algo' => $algo]
    )->queryScalar();

    $userBlocks = $worker->userid ? (int) $db->createCommand(
        "SELECT COUNT(id) FROM blocks
         WHERE userid = :uid AND algo = :algo
           AND time > (SELECT MIN(time) FROM workers WHERE algo = :algo2)",
        [':uid' => $worker->userid, ':algo' => $algo, ':algo2' => $algo]
    )->queryScalar() : 0;
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
            <?php if ($pctBad > 50): ?>
                <b><?= round($pctBad, 1) ?>%</b>
            <?php else: ?>
                <?= round($pctBad, 1) ?>%
            <?php endif ?>
        <?php endif ?>
    </td>
    <td><?= number_format($sharePercent, 1, '.', '') ?>%</td>
    <td><?= $workerBlocks ?> / <?= $userBlocks ?></td>
    <td><?= Html::encode($displayName) ?></td>
    <td><?= $donationPct ? Html::encode($donationPct) . '&nbsp;%' : '' ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
