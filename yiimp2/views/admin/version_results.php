<?php

/** @var yii\web\View  $this */
/** @var string        $algo */
/** @var array[]       $rows */

use yii\helpers\Html;

if ($algo === '') {
    echo '<p class="text-muted">No algo selected.</p>';
    return;
}

$conv = Yii::$app->ConversionUtils;
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
<?php foreach ($rows as $item):
    $hashrate = (float) $item['hashrate'];
    $invalid  = (float) $item['invalid'];
    $percent  = $hashrate ? round($invalid * 100 / $hashrate, 3) : 0;
?>
<tr class="ssrow">
    <td><b><?= Html::encode($item['version']) ?></b></td>
    <td align="right"><?= (int) $item['workers'] ?></td>
    <td align="right"><?= Html::encode($conv->Itoa2($hashrate) . 'h/s') ?></td>
    <td align="right"><?= Html::encode($conv->Itoa2($invalid)  . 'h/s') ?></td>
    <td align="right"><?= $percent ?>%</td>
</tr>
<?php endforeach ?>
<?php if (empty($rows)): ?>
<tr><td colspan="5" class="text-muted">No workers connected for algo <b><?= Html::encode($algo) ?></b>.</td></tr>
<?php endif ?>
</tbody>
</table>
