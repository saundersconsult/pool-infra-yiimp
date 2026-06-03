<?php

/** @var yii\web\View              $this     */
/** @var app\models\Connections[]  $list     */
/** @var string|null               $lastTime */

use yii\helpers\Html;

Yii::$app->ViewUtils->showTableSorter('maintable');
?>
<thead>
<tr>
    <th>ID</th>
    <th>User</th>
    <th>Host</th>
    <th>Database</th>
    <th>Idle</th>
    <th>Created</th>
    <th>Last</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($list as $conn): ?>
<tr class="ssrow">
    <td><?= (int) $conn->id ?></td>
    <td><?= Html::encode($conn->user) ?></td>
    <td><?= Html::encode($conn->host) ?></td>
    <td><?= Html::encode($conn->db) ?></td>
    <td><?= Yii::$app->ConversionUtils->sectoa($conn->idle) ?></td>
    <td><?= Yii::$app->ConversionUtils->datetoa2($conn->created) ?></td>
    <td><?= Yii::$app->ConversionUtils->datetoa2($conn->last) ?></td>
    <td><?= Yii::$app->ConversionUtils->Booltoa($conn->last == $lastTime) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table><br>
<?= count($list) ?> connections to the database<br>
