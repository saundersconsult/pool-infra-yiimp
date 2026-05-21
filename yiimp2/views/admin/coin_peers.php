<?php

/** @var yii\web\View $this */
/** @var app\models\Coins $coin */

use yii\helpers\Html;
use app\components\rpc\WalletRPC;

$this->title = 'Peers - ' . $coin->symbol;

$remote = new WalletRPC($coin);
$info   = $remote->getinfo();

echo Yii::$app->ViewUtils->getAdminSideBarLinks() . '<br/><br/>';
echo Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers') . '<br/><br/>';

$localHeight = $info['blocks'] ?? 0;
$list        = $remote->getpeerinfo() ?: [];
$addnodeLines = [];
$latestVersion = '';

?>
<style>
td.red { color: darkred; }
table.dataGrid a.red { color: darkred; }
div.form { text-align: right; height: 30px; width: 350px; float: right;
           margin-top: -48px; margin-bottom: 16px; margin-right: -8px; }
.main-submit-button { cursor: pointer; }
</style>

<div class="form">
    <form action="<?= Html::encode(Yii::$app->urlManager->createUrl(['admin/coinpeeradd', 'id' => $coin->id])) ?>"
          method="post" style="padding: 8px;">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="text" name="node" class="main-text-input"
               placeholder="addr[:port]" autocomplete="off" style="width:150px; margin-right:4px;">
        <input type="submit" value="Add node" class="main-submit-button">
    </form>
</div>

<?php
// Flash messages
foreach (['error', 'success'] as $type) {
    if (Yii::$app->session->hasFlash($type)) {
        $cls = $type === 'error' ? 'red' : '';
        echo '<p class="' . $cls . '">' . Html::encode(Yii::$app->session->getFlash($type)) . '</p>';
    }
}
?>

<table id="maintable" class="dataGrid tablesorter">
<thead>
<tr>
    <th>Address</th>
    <th>Version</th>
    <th>Height</th>
    <th>Ping</th>
    <th>Services</th>
    <th>Since</th>
    <th>Last</th>
    <th>Rx / Tx (kB)</th>
    <th width="30"></th>
</tr>
</thead>
<tbody>
<?php foreach ($list as $peer):
    $node    = $peer['addr'] ?? '';
    $prefix  = $coin->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=';
    $addnodeLines[] = $prefix . $node;

    $peerVer = trim($peer['subver'] ?? '', '/');
    $latestVersion = max($latestVersion, $peerVer);

    $height = $peer['currentheight'] ?? ($peer['synced_blocks'] ?? 0);
    $heightClass = (abs($height - $localHeight) > 5) ? 'red' : '';

    $connTime      = $peer['conntime']      ?? time();
    $startHeight   = $peer['startingheight'] ?? '';
    $lastRecv      = $peer['lastrecv']      ?? time();
    $lastSend      = $peer['lastsend']      ?? time();
    $bytesRecv     = round(($peer['bytesrecv'] ?? 0) / 1024, 1);
    $bytesSent     = round(($peer['bytessent'] ?? 0) / 1024, 1);

    $removeUrl = Yii::$app->urlManager->createUrl([
        'admin/coinpeerremove', 'id' => $coin->id, 'node' => $node,
    ]);
?>
<tr class="ssrow">
    <td><?= Html::encode($node) ?></td>
    <td><?= Html::encode($peerVer) ?></td>
    <td class="<?= $heightClass ?>"><?= (int) $height ?></td>
    <td><?= Html::encode($peer['pingtime'] ?? '') ?></td>
    <td><?= Html::encode($peer['services'] ?? '') ?></td>
    <td><?= Yii::$app->ConversionUtils->datetoa2($connTime) ?> (<?= (int) $startHeight ?>)</td>
    <td><?= Yii::$app->ConversionUtils->datetoa2(max($lastRecv, $lastSend)) ?></td>
    <td><?= ($bytesRecv + $bytesSent) ? Html::encode("{$bytesRecv} / {$bytesSent}") : '' ?></td>
    <td><?= Html::a('remove', $removeUrl, ['class' => 'red', 'title' => 'Disconnect from node']) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<br>

<b>Local version:</b> <?= Html::encode(Yii::$app->ConversionUtils->formatWalletVersion($coin)) ?>&nbsp;
<b>Latest peer:</b> <?= Html::encode($latestVersion) ?>

<?php if ($addnodeLines): ?>
<pre><?= Html::encode(implode("\n", $addnodeLines)) ?></pre>
<?php endif ?>

<?php
$this->registerJs("
    if (typeof showTableSorter === 'function') {
        showTableSorter('maintable', {
            tableClass: 'dataGrid',
            headers: {
                2: {sorter:'numeric'}, 3: {sorter:'numeric'}, 7: {sorter:'numeric'}, 8: {sorter:false}
            },
            widgets: ['zebra','Storage','saveSort'],
            widgetOptions: { saveSort: true }
        });
    }
");
?>
