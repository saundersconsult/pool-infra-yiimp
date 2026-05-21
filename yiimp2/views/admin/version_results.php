<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$algo = Yii::$app->session->get('yaamp-algo', '');
if ($algo === '') {
    echo '<p class="text-muted">No algo selected.</p>';
    return;
}

$conv     = Yii::$app->ConversionUtils;
$util     = Yii::$app->YiimpUtils;
$db       = Yii::$app->db;

$target   = $util->hashrate_constant($algo);
$interval = $util->hashrate_step();
$delay    = time() - $interval;

$versions = $db->createCommand(
    "SELECT version, COUNT(*) AS c FROM workers WHERE algo = :algo GROUP BY version ORDER BY c DESC",
    [':algo' => $algo]
)->queryAll();

?>
<br>
<table class="dataGrid">
<thead>
<tr>
    <th>Version</th>
    <th align="right">Workers</th>
    <th align="right">Hashrate</th>
    <th align="right">Bad</th>
    <th align="right">%</th>
</tr>
</thead>
<tbody>
<?php foreach ($versions as $item):
    $version = $item['version'];
    $count   = (int) $item['c'];

    $hashrate = (float) $db->createCommand(
        "SELECT SUM(difficulty) * :target / :interval / 1000
         FROM shares
         WHERE valid = 1 AND time > :delay
           AND workerid IN (SELECT id FROM workers WHERE algo = :algo AND version = :ver)",
        [':target' => $target, ':interval' => $interval, ':delay' => $delay,
         ':algo' => $algo, ':ver' => $version]
    )->queryScalar();

    $invalid = (float) $db->createCommand(
        "SELECT SUM(difficulty) * :target / :interval / 1000
         FROM shares
         WHERE valid != 1 AND time > :delay
           AND workerid IN (SELECT id FROM workers WHERE algo = :algo AND version = :ver)",
        [':target' => $target, ':interval' => $interval, ':delay' => $delay,
         ':algo' => $algo, ':ver' => $version]
    )->queryScalar();

    $percent     = $hashrate ? round($invalid * 100 / $hashrate, 3) : 0;
    $hashrateFmt = $conv->Itoa2($hashrate) . 'h/s';
    $invalidFmt  = $conv->Itoa2($invalid)  . 'h/s';
?>
<tr class="ssrow">
    <td><b><?= Html::encode($version) ?></b></td>
    <td align="right"><?= $count ?></td>
    <td align="right"><?= Html::encode($hashrateFmt) ?></td>
    <td align="right"><?= Html::encode($invalidFmt) ?></td>
    <td align="right"><?= $percent ?>%</td>
</tr>
<?php endforeach ?>
<?php if (empty($versions)): ?>
<tr><td colspan="5" class="text-muted">No workers connected for algo <b><?= Html::encode($algo) ?></b>.</td></tr>
<?php endif ?>
</tbody>
</table>
