<?php

use app\models\Connections;

$last = (new \yii\db\Query())
				->select(['max(last)'])
				->from('connections')
				->scalar();
$list = Connections::find()->orderBy('id desc')->all();

//echo "<table class='dataGrid'>";
Yii::$app->ViewUtils->showTableSorter('maintable');
echo "<thead>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>User</th>";
echo "<th>Host</th>";
echo "<th>Database</th>";
echo "<th>Idle</th>";
echo "<th>Created</th>";
echo "<th>Last</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

foreach($list as $conn)
{
	echo "<tr class='ssrow'>";

	$d1 = Yii::$app->ConversionUtils->sectoa($conn->idle);
	$d2 = Yii::$app->ConversionUtils->datetoa2($conn->created);
	$d3 = Yii::$app->ConversionUtils->datetoa2($conn->last);
	$b = Yii::$app->ConversionUtils->Booltoa($conn->last == $last);

	echo "<td>$conn->id</td>";
	echo "<td>$conn->user</td>";
	echo "<td>$conn->host</td>";
	echo "<td>$conn->db</td>";
	echo "<td>$d1</td>";
	echo "<td>$d2</td>";
	echo "<td>$d3</td>";
	echo "<td>$b</td>";

	echo "</tr>";
}

echo "</tbody></table><br>";

echo count($list)." connections to the database<br>";
