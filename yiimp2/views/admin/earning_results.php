<?php

use app\models\Earnings;
use app\models\Coins;
use app\models\Accounts;
use app\models\Blocks;

use yii\bootstrap5\Html;

echo <<<end
<div align="right" style="margin-top: -14px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
.actions { width: 120px; text-align: right; }
table.dataGrid a.red { color: darkred; }
table.totals { margin-top: 8px; margin-left: 16px; display: inline-block; }
table.totals th { text-align: left; width: 100px; }
table.totals td { text-align: right; }
table.totals tr.red td { color: darkred; }
.page .footer { width: auto; }
</style>
end;

$coin_id = (int) Yii::$app->getRequest()->getQueryParam('id');

$saveSort = $coin_id ? 'false' : 'true';

Yii::$app->ViewUtils->showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	widgets: ['zebra','filter','Storage','saveSort'],
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); },
		7: function(node, table, n) { return $(node).attr('data'); }
	},
	widgetOptions: {
		saveSort: {$saveSort},
		filter_saveFilters: {$saveSort},
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="" width="20"></th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="currency">Quantity</th>
<th data-sorter="currency">BTC</th>
<th data-sorter="numeric">Block</th>
<th data-sorter="">Status</th>
<th data-sorter="numeric">Sent</th>
<th data-sorter="" class="actions">Actions</th>
</tr>
</thead><tbody>
end;

$coin_id = (int) Yii::$app->getRequest()->getQueryParam('id');
$sqlFilter = $coin_id ? "AND coinid={$coin_id}": '';
$limit = $coin_id ? '' : 'LIMIT 1500';

$get_earnings = Earnings::find()
			->where(['!=','status',2])
			->orderBy('create_time DESC');
if ($coin_id) $get_earnings->andWhere(['coinid' => $coin_id])->limit(1500);

$total = 0.; $total_btc = 0.; $totalimmat = 0.; $totalfees = 0.; $totalstake = 0.;
$earnings= $get_earnings->all();
foreach($earnings as $earning)
{
	$coin = Coins::findOne(['id' => $earning->coinid]);
	if(!$coin) continue;

	$user = Accounts::findOne(['id' => $earning->userid]);
	if(!$user) continue;

	$block = Blocks::findOne(['id' => $earning->blockid]);
	if(!$block) continue;

	$t1 = Yii::$app->ConversionUtils->datetoa2($earning->create_time). ' ago';
	$t2 = Yii::$app->ConversionUtils->datetoa2($earning->mature_time);
	if ($t2) $t2 = '+'.$t2;

	$coinimg = Html::image($coin->image, $coin->symbol, array('width'=>'16'));
	$coinlink = Html::a($coin->name, '/admin/coin?id='.$coin->id);

	echo '<tr class="ssrow">';
	echo "<td>$coinimg</td>";
	echo "<td><b>$coinlink</b>&nbsp;($coin->symbol_show)</td>";
	echo '<td><b><a href="/?address='.$user->username.'">'.$user->username.'</a></b></td>';
	echo '<td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($earning->amount).'</td>';
	echo '<td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($earning->amount * $earning->price).'</td>';
	echo '<td>'.$block->height.'</td>';
	echo '<td data="'.$block->height.'">'."$block->category ($block->confirmations)</td>";
	echo '<td data="'.$earning->create_time.'">'."$t1 $t2</td>";

	echo '<td class="actions">';
	echo '<a href="/admin/clearearning?id='.$earning->id.'">clear</a> ';
	echo '<a class="red" href="/admin/deleteearning?id='.$earning->id.'">delete</a>';
	echo '</td>';

//	echo "<td style='font-size: .7em'>$earning->tx</td>";
	echo "</tr>";

// 	if($block->category == 'generate' && $earning->status == 0)
// 	{
// 		$earning->status = 1;
// 		$earning->mature_time = time()-100*60;
// 		$earning->save();
// 	}

	if($block->category == 'immature') {
		$total += (double) $earning->amount;
		$total_btc += (double) $earning->amount * $earning->price;
		$totalimmat += (double) $earning->amount;
	}
	if($block->category == 'generate') {
		$total += (double) $earning->amount; // "Exchange" state
		$total_btc += (double) $earning->amount * $earning->price;
	}
	else if($block->category == 'stake' || $block->category == 'generated') {
		$totalstake += (double) $earning->amount;
	}
}

echo '</tbody><tfoot>';
echo '<tr><th colspan="9">';
echo count($earnings).' records';
if (count($earnings) >= 1000) echo " ($limit)";
echo '</th></tr>';
echo '</tfoot></table>';

if ($coin_id) {
	$coin = Coins::findOne(['id' => $coin_id]);
	if (!$coin) exit;
	$symbol = $coin->symbol;
	$feepct = Yii::$app->YiimpUtils->yiimp_fee($coin->algo);
	$totalfees = ($total / ((100 - $feepct) / 100.)) - $total;

	$cleared = (new \yii\db\Query())
				->select(['SUM(balance)'])
				->from('accounts')
                ->Where(['coinid' => $coin->id])
				->scalar();

	echo '<div class="totals" align="right">';

	echo '<table class="totals">';
	echo '<tr><th>Immature</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($totalimmat)." $symbol</td></tr>";
	echo '<tr><th>Total owed</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($total)." $symbol</td></tr>";
	//echo '<tr><th>Total BTC</th><td>'.bitcoinvaluetoa($total_btc)." BTC</td></tr>";
	echo '<tr><th>Pool Fees '.round($feepct,1).'%</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($totalfees)." $symbol</td></tr>";
	if ($coin->rpcencoding == 'POS')
		echo '<tr><th>Stake</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($totalstake)." $symbol</td></tr>";
	echo '</tr></table>';

	echo '<table class="totals">';
	echo '<tr><th>Balance</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->balance)." $symbol</td></tr>";
	echo '<tr><th>Cleared</th><td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($cleared)." $symbol</td></tr>";
	$exchange = $total - $totalimmat;
	echo '<tr><th title="Available = (Balance - Cleared - in exchange)">Available</th>';
	echo '<td>'.Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->balance - $exchange - $cleared)." $symbol</td></tr>";
	echo '</tr></table>';

	echo '</div>';
}