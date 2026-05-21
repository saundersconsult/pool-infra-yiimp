<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Accounts;
use app\models\Coins;

$this->title = 'Botnets';

echo Yii::$app->ViewUtils->getAdminSideBarLinks() . '<br/><br/>';

// Accounts with more than 10 distinct IPs across any (userid, algo, pid) group.
// These are likely clients connecting from botnets or VPN farms.
$botnets = Yii::$app->db->createCommand(
    "SELECT userid, algo, pid,
            MAX(time)         AS time,
            COUNT(userid)     AS workers,
            COUNT(DISTINCT ip) AS ips,
            MAX(version)      AS version
     FROM workers
     GROUP BY userid, algo, pid
     HAVING ips > 10
     ORDER BY ips DESC"
)->queryAll();

?>
<style>
.red { color: darkred; }
table.dataGrid { max-width: 99.5%; }
table.dataGrid a.red, table.dataGrid .btn-ban { color: darkred; }
.actions form { display: inline; margin-right: 3px; }
</style>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        4: function(node, table, n) { return \$(node).attr('data'); }
    },
    widgets: ['zebra','Storage','saveSort'],
    widgetOptions: { saveSort: true }
}");
?>

<thead>
<tr>
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Algo</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last seen</th>
    <th data-sorter="numeric">PID</th>
    <th data-sorter="numeric">IPs</th>
    <th data-sorter="numeric">Workers</th>
    <th data-sorter="text">Version</th>
    <th data-sorter="false" class="actions" align="right" width="180">Actions</th>
</tr>
</thead>
<tbody>
<?php
if (!empty($botnets)):
    foreach ($botnets as $botnet):
        if (!$botnet['userid']) continue;

        $user = Accounts::findOne((int) $botnet['userid']);
        if (!$user) continue;

        $coin = Coins::findOne((int) $user->coinid);
        if (!$coin) continue;

        $coinImg  = Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]);
        $coinLink = Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]);
        $addrLink = Html::a(Html::encode($user->username), ['/?address=' . urlencode($user->username)]);
        $d        = Yii::$app->ConversionUtils->datetoa2($botnet['time']);
?>
<tr class="ssrow">
    <td><?= $coinImg ?></td>
    <td><?= $coinLink ?></td>
    <td><?= Html::encode($botnet['algo']) ?></td>
    <td><?= $addrLink ?></td>
    <td data="<?= (int) $botnet['time'] ?>"><?= $d ?></td>
    <td><?= (int) $botnet['pid'] ?></td>
    <td><b><?= (int) $botnet['ips'] ?></b></td>
    <td><?= (int) $botnet['workers'] ?></td>
    <td><?= Html::encode(substr((string) $botnet['version'], 0, 30)) ?></td>
    <td class="actions" align="right">
        <!-- watch / unwatch -->
        <?php if ($user->logtraffic): ?>
            <?= Html::a('unwatch', ['/admin/loguser', 'id' => $user->id, 'en' => 0]) ?>
        <?php else: ?>
            <?= Html::a('watch', ['/admin/loguser', 'id' => $user->id, 'en' => 1]) ?>
        <?php endif ?>

        <!-- block / unblock -->
        <?php if ($user->is_locked): ?>
            <?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?>
        <?php else: ?>
            <?= Html::a('block', ['/admin/blockuser', 'wallet' => $user->username]) ?>
        <?php endif ?>

        <!-- ban (irreversible — balance zeroed + locked) -->
        <?= Html::a(
            '<span class="red">BAN</span>',
            ['/admin/banuser', 'id' => $user->id],
            ['onclick' => "return confirm('Ban ' + " . json_encode($user->username) . " + '?')"]
        ) ?>
    </td>
</tr>
<?php
    endforeach;
endif;
?>
</tbody>
<tfoot>
<?php if (empty($botnets)): ?>
<tr><th colspan="10">No botnets detected (threshold: &gt; 10 distinct IPs per worker group)</th></tr>
<?php endif ?>
</tfoot>
</table><br>
