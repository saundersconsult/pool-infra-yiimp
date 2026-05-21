<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\models\Blocks;

$this->title = 'Monsters';

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$conv = Yii::$app->ConversionUtils;
$db   = Yii::$app->db;
$t24h = time() - 86400;

/**
 * Render one user row.
 * $what labels why this user appeared: 'pid' | 'blocks' | 'miners' | 'shares' | 'locked'
 */
$showUser = function(int $userId, string $what) use ($conv, $db, $t24h): void {
    $user = Accounts::findOne($userId);
    if (!$user) {
        return;
    }

    $d       = $conv->datetoa2($user->last_earning);
    $balance = $conv->bitcoinvaluetoa($user->balance);

    $paidRaw = (float) $db->createCommand(
        "SELECT SUM(amount) FROM payouts WHERE account_id = :uid",
        [':uid' => $user->id]
    )->queryScalar();
    $paid = $conv->bitcoinvaluetoa($paidRaw);

    $minerCount = (int) Workers::find()->where(['userid' => $user->id])->count();

    $shareCount = (int) $db->createCommand(
        "SELECT COUNT(id) FROM shares WHERE userid = :uid",
        [':uid' => $user->id]
    )->queryScalar();

    $blockCount = (int) Blocks::find()
        ->where(['userid' => $user->id])
        ->andWhere(['>', 'time', $t24h])
        ->count();

    $coin = Coins::findOne((int) $user->coinid);

    echo "<tr class='ssrow'>";
    echo '<td width="24">' . (int) $user->id . '</td>';

    if (!$coin) {
        echo '<td width="60" colspan="2"></td>';
    } else {
        $coinLink = Html::a(Html::encode($coin->symbol), ['/admin/coinwallet', 'id' => $coin->id]);
        echo '<td width="16"><img src="' . Html::encode($coin->image) . '" width="16" alt=""></td>'
           . '<td width="48"><b>' . $coinLink . '</b></td>';
    }

    echo '<td>' . Html::a('<b>' . Html::encode($user->username) . '</b>',
            '/?address=' . urlencode($user->username), ['encode' => false]) . '</td>';
    echo '<td>' . Html::encode($what) . '</td>';
    echo '<td>' . $d . '</td>';
    echo '<td>' . $blockCount . '</td>';
    echo '<td>' . Html::encode($balance) . '</td>';

    if ($paidRaw > 0.01) {
        echo '<td><b>' . Html::encode($paid) . '</b></td>';
    } else {
        echo '<td>' . Html::encode($paid) . '</td>';
    }

    echo '<td>' . $minerCount . '</td>';
    echo '<td>' . $shareCount . '</td>';

    if ($user->is_locked) {
        echo '<td>locked</td>';
        echo '<td>' . Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) . '</td>';
    } else {
        echo '<td></td>';
        echo '<td>' . Html::a('block', ['/admin/blockuser', 'wallet' => $user->username]) . '</td>';
    }

    echo "</tr>\n";
};

?>
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
</style>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    headers: { 1: { sorter: false } },
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}");
?>

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

<?php
// 1. Workers whose PID is not in the active stratums list (stale / ghost workers)
$pidList = $db->createCommand(
    "SELECT userid FROM shares
     WHERE pid IS NULL OR pid NOT IN (SELECT pid FROM stratums)
     GROUP BY userid"
)->queryAll();
foreach ($pidList as $item) {
    $showUser((int) $item['userid'], 'pid');
}

// 2. Accounts with a positive balance but no blocks found in the last 24 h
$balanceList = $db->createCommand(
    "SELECT id FROM accounts
     WHERE balance > 0.001
       AND id NOT IN (
           SELECT DISTINCT userid FROM blocks
           WHERE userid IS NOT NULL AND time > :t
       )",
    [':t' => $t24h]
)->queryAll();
foreach ($balanceList as $item) {
    $showUser((int) $item['id'], 'blocks');
}

// 3. Top 5 accounts by total worker count (potential high-density miners)
$topMiners = $db->createCommand(
    "SELECT COUNT(*) AS total, userid FROM workers
     GROUP BY userid ORDER BY total DESC LIMIT 5"
)->queryAll();
foreach ($topMiners as $item) {
    $showUser((int) $item['userid'], 'miners');
}

// 4. Top 5 workers by total share count — look up the owning account
$topShareWorkers = $db->createCommand(
    "SELECT COUNT(*) AS total, workerid FROM shares
     GROUP BY workerid ORDER BY total DESC LIMIT 5"
)->queryAll();
foreach ($topShareWorkers as $item) {
    $worker = Workers::findOne((int) $item['workerid']);
    if (!$worker) {
        continue;
    }
    $showUser((int) $worker->userid, 'shares');
}

// 5. All currently locked accounts
$lockedUsers = Accounts::find()->where(['is_locked' => 1])->all();
foreach ($lockedUsers as $user) {
    $showUser((int) $user->id, 'locked');
}
?>

</tbody>
</table>
