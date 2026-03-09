<?php

use app\models\Coins;
use app\models\Orders;
use app\models\Exchange_deposit;

$orders = Orders::find()->orderBy('(amount*bid) desc')->all();

echo "<br><table class='dataGrid'>";
//showTableSorter('maintable');
echo "<thead>";
echo "<tr>";
echo "<th width=20></th>";
echo "<th>Name</th>";
echo "<th>Exchange</th>";
echo "<th>Created</th>";
echo "<th>Quantity</th>";
echo "<th>Ask</th>";
echo "<th>Bid</th>";
echo "<th>Value</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

$totalvalue = 0;
$totalbid = 0;

foreach($orders as $order)
{
	$coin = Coins::findone(['id' => $order->coinid]);

	$marketurl = getMarketUrl($coin, $order->market);
	$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'16'));

	echo "<tr class='ssrow'>";

	$created = Yii::$app->ConversionUtils->datetoa2($order->created). ' ago';
	$price = $order->price? Yii::$app->ConversionUtils->bitcoinvaluetoa($order->price): '';

	$price = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->price);
	$bid = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->bid);
	$value = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->amount*$order->price);
	$bidvalue = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->amount*$order->bid);
	$totalvalue += $value;
	$totalbid += $bidvalue;
	$bidpercent = $value>0? round(($value-$bidvalue)/$value*100, 1): 0;

	echo '<td width="16">'.$coinimg.'</td>';
	echo "<td><b><a href='/admin/coin?id=$coin->id'>$coin->name</a></b>&nbsp;($coin->symbol)</td>";
	echo "<td><b><a href='$marketurl' target=_blank>$order->market</a></b></td>";

	echo "<td>$created</td>";
	echo "<td>$order->amount</td>";
	echo "<td>$price</td>";
	echo "<td>$bid ({$bidpercent}%)</td>";
	echo $bidvalue>0.01? "<td><b>$bidvalue</b></td>": "<td>$bidvalue</td>";

 	echo "<td>";
 	echo "<a href='/admin/cancelorder?id=$order->id'>[cancel]</a> ";
// 	echo "<a href='/admin/sellorder?id=$order->id'>[sell]</a>";
 	echo "</td>";
	echo "</tr>";
}

$bidpercent = $totalvalue? round(($totalvalue-$totalbid)/$totalvalue*100, 1): '';

echo "<tr>";
echo "<td></td>";
echo "<td>Total</td>";
echo "<td colspan=3></td>";
echo "<td><b>".Yii::$app->ConversionUtils->bitcoinvaluetoa($totalvalue)."</b></td>";
echo "<td><b>".Yii::$app->ConversionUtils->bitcoinvaluetoa($totalbid)." ({$bidpercent}%)</b></td>";
echo "<td></td>";
echo "</tr>";

echo "</tbody></table>";

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

$exchanges_deposits = Exchange_deposit::find()->orderBy('send_time desc')->limit(150)->all();

echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th width=20></th>";
echo "<th>Name</th>";
echo "<th>Market</th>";
echo "<th>Created</th>";
echo "<th>Quantity</th>";
echo "<th>Estimate</th>";
echo "<th>Sold Price</th>";
echo "<th>Value</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

foreach($exchanges_deposits as $exchange_deposit)
{
	$coin = Coins::findOne(['id' => $exchange_deposit->coinid]);

	$lowsymbol = strtolower($coin->symbol);
	$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'16'));

	$marketurl = getMarketUrl($coin, $exchange_deposit->market);

	if($exchange_deposit->status == 'waiting')
		echo "<tr style='background-color: #e0d3e8;'>";
	else
		echo "<tr class='ssrow'>";

	$sent = Yii::$app->ConversionUtils->datetoa2($exchange_deposit->send_time). ' ago';
	$received = $exchange_deposit->receive_time? Yii::$app->ConversionUtils->sectoa($exchange_deposit->receive_time-$exchange_deposit->send_time): '';
	$price = $exchange_deposit->price? Yii::$app->ConversionUtils->bitcoinvaluetoa($exchange_deposit->price): Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price);
	$estimate = Yii::$app->ConversionUtils->bitcoinvaluetoa($exchange_deposit->price_estimate);
	$total = $exchange_deposit->price? Yii::$app->ConversionUtils->bitcoinvaluetoa($exchange_deposit->quantity*$exchange_deposit->price): Yii::$app->ConversionUtils->bitcoinvaluetoa($exchange_deposit->quantity*$coin->price);

	echo '<td width="16">'.$coinimg.'</td>';
	echo '<td><b><a href="/admin/coinwallet?id='.$coin->id.'">'."$coin->name</a></b>&nbsp;($coin->symbol)</td>";
	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$exchange_deposit->market.'</a></b></td>';
	echo '<td>'.$sent.'</td>';
	echo '<td>'.$exchange_deposit->quantity.'</td>';
	echo '<td>'.$estimate.'</td>';
	echo '<td>'.$price.'</td>';
	echo $total>0.01? "<td><b>$total</b></td>": "<td>$total</td>";

	echo "<td>";

	if($exchange_deposit->status == 'waiting')
	{
	//	echo "<a href='/admin/clearexchange?id=$exchange_deposit->id'>[clear]</a>";
		echo "<a href='/admin/deleteexchangedeposit?id=$exchange_deposit->id'>[del]</a>";
	}

	echo "</td>";
	echo "</tr>";
}

echo "</tbody></table>";

