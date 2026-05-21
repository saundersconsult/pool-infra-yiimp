<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\db\Query;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\models\Blocks;

$symbol = Yii::$app->request->get('symbol', 'all');
$coin   = null;

if ($symbol === 'all') {
    $users = Accounts::find()
        ->where(['>', 'balance', 0.001])
        ->orWhere(['in', 'id',
            (new Query)->select('userid')->from('workers')->distinct()
        ])
        ->orderBy(['balance' => SORT_DESC])
        ->all();
} else {
    $coin = Coins::find()->where(['symbol' => $symbol])->one();
    if (!$coin) {
        return;
    }
    $users = Accounts::find()
        ->where(['coinid' => $coin->id])
        ->andWhere(['or',
            ['>', 'balance', 0.001],
            ['in', 'id', (new Query)->select('userid')->from('workers')->distinct()],
        ])
        ->orderBy(['balance' => SORT_DESC])
        ->all();
}

$conv     = Yii::$app->ConversionUtils;
$util     = Yii::$app->YiimpUtils;
$db       = Yii::$app->db;

$target   = $util->hashrate_constant();   // default target, algo-independent
$interval = $util->hashrate_step();       // 300 s window
$delay    = time() - $interval;

?>
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
.red { color: darkred; }
tr.ssrow.filtered { display: none; }
.actions a { margin-right: 4px; }
</style>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
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
}");
?>

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
    // Hashrate across all algos in the last $interval seconds
    $userRate = (float) $db->createCommand(
        "SELECT SUM(difficulty) * :target / :interval / 1000
         FROM shares WHERE valid=1 AND time > :delay AND userid = :uid",
        [':target' => $target, ':interval' => $interval, ':delay' => $delay, ':uid' => $user->id]
    )->queryScalar();

    $userBad = (float) $util->user_rate_bad($user->id);
    $pctBad  = $userRate ? round($userBad * 100 / $userRate, 3) : 0;

    $minerCount = (int) Workers::find()->where(['userid' => $user->id])->count();
    $blockCount = (int) Blocks::find()->where(['userid'  => $user->id])->count();

    $paid = (float) $db->createCommand(
        "SELECT SUM(amount) FROM payouts WHERE account_id = :uid",
        [':uid' => $user->id]
    )->queryScalar();

    $blockDiff = ($paid > 0 && $blockCount > 0)
        ? round((float) $db->createCommand(
            "SELECT SUM(difficulty) FROM blocks WHERE userid = :uid",
            [':uid' => $user->id]
          )->queryScalar() / $paid, 3)
        : '?';

    // Coin image + link for this user
    $coinImg  = '';
    $coinLink = '';
    $userCoin = ($coin && $user->coinid == $coin->id) ? $coin : Coins::findOne((int) $user->coinid);
    if ($userCoin) {
        $coinImg  = Html::img(Html::encode($userCoin->image), ['width' => 16, 'alt' => Html::encode($userCoin->symbol)]);
        $coinLink = Html::a(Html::encode($userCoin->symbol), ['/admin/coinwallet', 'id' => $userCoin->id]);
    }

    $balance = $conv->bitcoinvaluetoa($user->balance);
    $paidFmt = $conv->bitcoinvaluetoa($paid);
    $d       = $conv->datetoa2($user->last_earning);

    $totalBalance += (float) $user->balance;
    $totalPaid    += $paid;
?>
<tr class="ssrow">
    <td width="24"><?= (int) $user->id ?></td>
    <td width="16"><?= $coinImg ?></td>
    <td width="48"><b><?= $coinLink ?></b></td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', ['/?address=' . urlencode($user->username)], ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $d ?></td>
    <td align="right"><?= $minerCount ?></td>
    <td width="32" data="<?= (int) $userRate ?>" align="right">
        <?= $userRate ? Html::encode($conv->Itoa2($userRate)) : '' ?>
    </td>
    <td width="32" align="right">
        <?= $pctBad ? round($pctBad, 1) . '&nbsp;%' : '' ?>
    </td>
    <td align="right"><?= $blockCount ?></td>
    <td align="right"><?= $userRate ? Html::encode((string) $blockDiff) : '' ?></td>
    <td align="right"><?= Html::encode($balance) ?></td>
    <td align="right"><?= Html::encode($paidFmt) ?></td>
    <td class="actions" align="right">
        <?php if ($user->logtraffic): ?>
            <?= Html::a('unwatch', ['/admin/loguser', 'id' => $user->id, 'en' => 0]) ?>
        <?php else: ?>
            <?= Html::a('watch',   ['/admin/loguser', 'id' => $user->id, 'en' => 1]) ?>
        <?php endif ?>

        <?php if ($user->is_locked): ?>
            <?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?>
        <?php else: ?>
            <?= Html::a('block',   ['/admin/blockuser',   'wallet' => $user->username]) ?>
        <?php endif ?>

        <?= Html::a('<span class="red">BAN</span>', ['/admin/banuser', 'id' => $user->id], [
            'encode'  => false,
            'onclick' => 'return confirm(' . json_encode('Ban ' . $user->username . '?') . ')',
        ]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>

<?php
$userCount    = count($users);
$totalBalance = $conv->bitcoinvaluetoa($totalBalance);
$totalPaid    = $conv->bitcoinvaluetoa($totalPaid);
$colspan      = 7; // empty filler columns between Coin and Balance
?>
<tfoot>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Users Total (<?= $userCount ?>)</b></th>
    <?php for ($c = 0; $c < $colspan; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($totalBalance) ?></b></th>
    <th align="right"><b><?= Html::encode($totalPaid) ?></b></th>
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
    <th align="right"><b><?= Html::encode($conv->bitcoinvaluetoa($coin->balance - (float) $totalBalance)) ?></b></th>
    <th colspan="2"></th>
</tr>
<?php endif ?>
</tfoot>
</table>
