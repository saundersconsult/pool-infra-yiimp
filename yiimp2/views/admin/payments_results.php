<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\db\Query;
use app\models\Accounts;
use app\models\Coins;

$coinId   = (int) Yii::$app->request->get('id', 0);
$conv     = Yii::$app->ConversionUtils;
$db       = Yii::$app->db;
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

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
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
}");
?>

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

// ── Pre-fetch immature earnings: keyed by "coinid-userid" ────────────────────
$immatureMap = [];
$immQuery = $db->createCommand(
    "SELECT coinid, userid, SUM(amount) AS immature FROM earnings WHERE status = 0"
    . ($coinId ? " AND coinid = :cid" : "")
    . " GROUP BY coinid, userid",
    $coinId ? [':cid' => $coinId] : []
)->queryAll();
foreach ($immQuery as $row) {
    $immatureMap["{$row['coinid']}-{$row['userid']}"] = (float) $row['immature'];
}

// ── Pre-fetch failed payouts (no tx): keyed by account_id ───────────────────
$failedMap = [];
$failQuery = $db->createCommand(
    "SELECT account_id, SUM(amount) AS failed
     FROM payouts WHERE (tx IS NULL OR tx = '') AND completed = 0
     GROUP BY account_id"
)->queryAll();
foreach ($failQuery as $row) {
    $failedMap[(int) $row['account_id']] = (float) $row['failed'];
}

// ── Active user list ─────────────────────────────────────────────────────────
$query = Accounts::find()
    ->where(['!=', 'is_locked', 1])
    ->andWhere(['or',
        ['>', 'balance', 0],
        ['>', 'last_earning', time() - 3600],
        ['in', 'id',
            (new Query)->select('account_id')->from('payouts')
                ->where(['or', ['tx' => null], ['tx' => '']])->distinct()
        ],
    ])
    ->orderBy(['last_earning' => SORT_DESC]);

if ($coinId) {
    $query->andWhere(['coinid' => $coinId]);
} else {
    $query->limit(100);
}

$list = $query->all();

$totalBalance  = 0.0;
$totalImmature = 0.0;
$totalFailed   = 0.0;

foreach ($list as $user):
    $coin = Coins::findOne((int) $user->coinid);
    $d    = $conv->datetoa2($user->last_earning);

    $coinBalance = '';
    $immKey      = $coin ? "{$coin->id}-{$user->id}" : "0-{$user->id}";

    $rawFailed  = $failedMap[$user->id]   ?? 0.0;
    $rawImmature = $immatureMap[$immKey]  ?? 0.0;

    $totalBalance  += (float) $user->balance;
    $totalImmature += $rawImmature;
    $totalFailed   += $rawFailed;

    $balanceFmt  = $user->balance  ? $conv->bitcoinvaluetoa($user->balance) : '';
    $immatureFmt = $rawImmature    ? $conv->bitcoinvaluetoa($rawImmature)   : '';
    $failedFmt   = $rawFailed      ? $conv->bitcoinvaluetoa($rawFailed)     : '';
?>
<tr class="ssrow">
    <td><?php if ($coin): ?><img width="16" src="<?= Html::encode($coin->image) ?>" alt=""><?php endif ?></td>
    <td>
        <?php if ($coin): ?>
            <b><?= Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]) ?></b>
            &nbsp;(<?= Html::encode($coin->symbol_show) ?>)
            <?php $coinBalance = $coin->balance ? $conv->bitcoinvaluetoa($coin->balance) : ''; ?>
        <?php else: ?>
            <?php $coinBalance = '-'; ?>
        <?php endif ?>
    </td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', '/?address=' . urlencode($user->username), ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $d ?></td>
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

<?php if ($coinId):
    $coin   = Coins::findOne($coinId);
    $symbol = $coin ? $coin->symbol : '';
?>
<div class="totals" align="right">
    <table class="totals">
        <tr>
            <th>Balances</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalBalance)) ?> <?= Html::encode($symbol) ?></td>
        </tr>
        <tr>
            <th>Immature</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalImmature)) ?> <?= Html::encode($symbol) ?></td>
        </tr>
        <?php if ($totalFailed > 0): ?>
        <tr class="red">
            <th>Failed</th>
            <td><?= Html::encode($conv->bitcoinvaluetoa($totalFailed)) ?> <?= Html::encode($symbol) ?></td>
        </tr>
        <tr>
            <td colspan="2">
                <?= Html::a(
                    'Reset all failed',
                    ['/admin/cancelUsersPayment', 'id' => $coinId],
                    [
                        'title'   => 'Add all failed payouts back to user balances',
                        'onclick' => 'return confirm("Restore all failed payouts for ' . Html::encode($symbol) . ' to user balances?")',
                    ]
                ) ?>
            </td>
        </tr>
        <?php endif ?>
    </table>
</div>
<?php endif ?>
