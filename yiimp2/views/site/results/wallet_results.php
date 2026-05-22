<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Mining;
use app\models\Payouts;

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

$mining = Mining::find()->one();

$defaultalgo = Yii::$app->session->get('yaamp-algo');

$show_details = Yii::$app->getRequest()->getQueryParam('showdetails');

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user) return;

WriteBoxHeader("Wallet: $user->username");

$refcoin = Coins::find()->where(['id' => $user->coinid])->one();
if(!$refcoin)
{
	if($user->coinid != null)
		echo "<div style='color: red; padding: 10px; '>This wallet address is not valid.
			You will not receive payments using this address.</div>";

	$refcoin = Coins::find()->where(['symbol' => 'BTC'])->one();

} elseif (!YIIMP_ALLOW_EXCHANGE && $user->coinid == 6 && $defaultalgo != 'sha256') {

	echo "<div style='color: red; padding: 10px; '>This pool does not convert/trade currencies.
		You will not receive payments using this BTC address.</div>";
	return;
}

echo "<table class='dataGrid2'>";

echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Name</th>";
echo "<th align=right>Immature</th>";
echo "<th align=right>Confirmed</th>";
echo "<th align=right>Total</th>";
echo "<th align=right>Value*</th>";
echo "</tr>";
echo "</thead>";

$total_pending = 0;

if($show_details)
{
	$t1 = microtime(true);

	$list = (new \yii\db\Query())
                ->select(['coinid'])
                ->from('earnings')
                ->where(['userid' => $user->id])
				->groupBy('coinid')
				->all();
	
	if(!count($list))
		echo "<tr><td></td><td colspan=5><i>-none-</i></td></tr>";

	else
	{
		// sort by value
		foreach($list as $item)
		{
			$coin = getdbo('db_coins', $item['coinid']);
			if(!$coin) continue;

			$name = substr($coin->name, 0, 12);

			$confirmed = Yii::$app->cache->get("wallet_confirmed-$user->id-$coin->id");
			if (!$confirmed) {
				$confirmed = (new \yii\db\Query())
						->select(['sum(amount)'])
						->from('earnings')
						->where(['status' => 1, 'userid' => $user->id, 'coinid' => $coin->id])
						->orderBy(['time' => SORT_DESC])
						->scalar();
				Yii::$app->cache->set("wallet_confirmed-$user->id-$coin->id", $confirmed);
			}

			$unconfirmed = Yii::$app->cache->get("wallet_unconfirmed-$user->id-$coin->id");
			if (!$unconfirmed) {
				$unconfirmed = (new \yii\db\Query())
						->select(['sum(amount)'])
						->from('earnings')
						->where(['status' => 0, 'userid' => $user->id, 'coinid' => $coin->id])
						->orderBy(['time' => SORT_DESC])
						->scalar();
				Yii::$app->cache->set("wallet_unconfirmed-$user->id-$coin->id", $unconfirmed);
			}

			$total = $confirmed + $unconfirmed;
			$value = Yii::$app->ConversionUtils->bitcoinvaluetoa(Yii::$app->YiimpUtils->convert_amount_user($coin, $total, $user));

			$confirmed = Yii::$app->ConversionUtils->altcoinvaluetoa($confirmed);
			$unconfirmed = Yii::$app->ConversionUtils->altcoinvaluetoa($unconfirmed);
			$total = Yii::$app->ConversionUtils->altcoinvaluetoa($total);

			echo "<tr class='ssrow'>";
			echo "<td width=18><img width=16 src='$coin->image'></td>";
			echo "<td><b><a href='/site/block?id=$coin->id' title='$coin->version'>$name</a></b><span style='font-size: .8em'> ($coin->algo)</span></td>";

			echo "<td align=right style='font-size: .8em;'>$unconfirmed</td>";
			echo "<td align=right style='font-size: .8em;'>$confirmed</td>";
			echo "<td align=right style='font-size: .8em;'>$total</td>";
			echo "<td align=right style='font-size: .8em;'>$value $refcoin->symbol</td>";

			echo "</tr>";
		}
	}

//	$d1 = microtime(true) - $t1;
//	controller()->memcache->add_monitoring_function('wallet_results-1', $d1);
}

//////////////////////////////////////////////////////////////////////////////

$confirmed = Yii::$app->YiimpUtils->convert_earnings_user($user, "status=1");
$unconfirmed = Yii::$app->YiimpUtils->convert_earnings_user($user, "status=0");

$total_unsold = Yii::$app->ConversionUtils->bitcoinvaluetoa($confirmed + $unconfirmed);
$confirmed = $confirmed? Yii::$app->ConversionUtils->bitcoinvaluetoa($confirmed): '';
$unconfirmed = $unconfirmed? Yii::$app->ConversionUtils->bitcoinvaluetoa($unconfirmed): '';
$total_pending = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_pending);

if(!$show_details && $total_unsold > 0)
{
	echo '
	<tr><td colspan="6" align="right">
		<label style="font-size: .8em;">
			<input type="checkbox" onclick="javascript:main_wallet_refresh_details()">
			Show Details
		</label>
	</td></tr>';
}

echo '<tr class="ssrow" style="border-top: 3px solid #eee;">';

echo '<td valign="top"><img width="16" src="'.$refcoin->image.'"></td>';
echo '<td valign="top"><b>';

if($refcoin->symbol == 'BTC')
	echo $refcoin->name;
else
	echo '<a href="/site/block?id='.$refcoin->id.'">'.$refcoin->name.'</a>';

echo '<br/><span style="font-size: .8em;"">(total pending)</span></b></td>';

echo '<td valign="top" align="right" style="font-size: .8em;">'.$unconfirmed.'</td>';
echo '<td valign="top" align="right" style="font-size: .8em;">'.$confirmed.'</td>';
echo '<td valign="top" align="right" style="font-size: .8em;"></td>';
echo '<td valign="top" align="right" style="font-size: .8em;">'.$total_unsold.' '.$refcoin->symbol.'</td>';

echo "</tr>";

// ////////////////////////////////////////////////////////////////////////////

$fees_notice = '';
if ($user->donation > 0) {
	$fees_notice = 'Currently donating '.$user->donation.' % of the rewards.';
} else if ($user->no_fees == 1) {
	$fees_notice = 'Currently mining without pool fees.';
}
echo '<tr><td colspan="6" style="text-align:right; font-size: .8em;"><b>'.$fees_notice.'</b></td></tr>';

// ////////////////////////////////////////////////////////////////////////////

$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance);

echo "<tr class='ssrow' style='border-top: 1px solid #eee;'>";
echo "<td><img width=16 src='$refcoin->image'></td>";
echo "<td colspan=3><b>Balance</b></td>";
echo "<td align=right style='font-size: .8em;'><b></b></td>";
echo "<td align=right style='font-size: .9em;'><b>$balance $refcoin->symbol</b></td>";
echo "</tr>";

////////////////////////////////////////////////////////////////////////////

$total_unpaid = Yii::$app->ConversionUtils->bitcoinvaluetoa($balance + $total_unsold);

echo "<tr class='ssrow' style='border-top: 3px solid #eee;'>";
echo "<td><img width=16 src='$refcoin->image'></td>";
echo "<td colspan=3><b>Total Unpaid</b></td>";
echo "<td align=right style='font-size: .8em;'></td>";
echo "<td align=right style='font-size: .9em;'>$total_unpaid $refcoin->symbol</td>";
echo "</tr>";

////////////////////////////////////////////////////////////////////////////

$total_paid = Yii::$app->cache->get("wallet_total_paid-$user->id");
if (!$total_paid) {
	$total_paid = (new \yii\db\Query())
			->select(['sum(amount)'])
			->from('payouts')
			->where([ 'account_id' => $user->id ])
			->scalar();
	Yii::$app->cache->set("wallet_total_paid-$user->id", $total_paid);
}

$total_paid = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_paid);

echo "<tr class='ssrow' style='border-top: 1px solid #eee;'>";
echo "<td><img width=16 src='$refcoin->image'></td>";
echo "<td colspan=3><b>Total Paid</b></td>";
echo "<td align=right style='font-size: .8em;'></td>";
echo "<td align=right style='font-size: .9em;'><a href='javascript:main_wallet_tx()'>$total_paid $refcoin->symbol</a></td>";
echo "</tr>";

////////////////////////////////////////////////////////////////////////////

//$delay = 7*24*60*60;

$total_earned = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_unsold + $balance + $total_paid);

echo "<tr class='ssrow' style='border-top: 3px solid #eee;'>";
echo "<td><img width=16 src='$refcoin->image'></td>";
echo "<td colspan=3><b>Total Earned</b></td>";
echo "<td align=right style='font-size: .8em;'></td>";
echo "<td align=right style='font-size: .9em;'>$total_earned $refcoin->symbol</td>";
echo "</tr>";

echo "</table>";

echo "</div>";

echo '<p style="font-size: .8em; margin-top: 0; padding-left: 4px;">';
echo '* approximate from current exchange rates<br/>';
if ($refcoin->symbol == 'BTC') {
	$usd = number_format($mining->usdbtc, 2, '.', ' ');
	echo '** bitstamp <b>'.$usd.'</b> USD/BTC';
}
echo '</p>';

if ($refcoin->payout_min) {
	echo '<p style="font-size: .8em; padding-left: 4px;">';
	echo '<b>Note:</b> Minimum payout for this wallet is '.($refcoin->payout_min).' '.$refcoin->symbol;
	echo '</p>';
}

echo '</div><br/>';

$header = "Last 24 Hours Payouts: ".$user->username;
WriteBoxHeader($header);

$t = time()-24*60*60;
$list = Payouts::find()
		->where(['account_id' => $user->id])
		->andWhere(['>','time',$t])
		->orderBy('time DESC')
		->all();

echo "<table  class='dataGrid2'>";

echo "<thead>";
echo "<tr>";
echo "<th align=right>Time</th>";
echo "<th align=right>Amount</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

$total = 0; $firstid = 999999999;
foreach($list as $payout)
{
	$d = Yii::$app->ConversionUtils->datetoa2($payout->time);
	$amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($payout->amount);
	$firstid = min($firstid, (int) $payout->id);

	echo '<tr class="ssrow">';
	echo '<td align="right"><b>'.$d.' ago</b></td>';
	echo '<td align="right"><b>'.$amount.'</b></td>';

	$payout_tx = substr($payout->tx, 0, 36).'...';
	$link = $refcoin->createExplorerLink($payout_tx, array('txid'=>$payout->tx), array(), true);

	echo '<td style="font-family: monospace;">'.$link.'</td>';
	echo '</tr>';

	$total += $payout->amount;
}

$amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($total);

echo <<<end
<tr class="ssrow">
<td align="right">Total:</td>
<td align="right"><b>{$amount}</b></td>
<td></td>
</tr>
end;

// Search extra Payouts which were not in the db (yiimp payout check command)
// In this case, the id are greater than last 24h ones and the fee column is filled
$list_extra = Payouts::find()
		->where(['account_id' => $user->id])
		->andWhere(['>','id',$firstid])
		->andWhere(['>','fee','0.0'])
		->orderBy('time DESC')
		->all();

if (!empty($list_extra)) {

	echo <<<end
	<tr class="ssrow" style="color: darkred;">
	<th colspan="3"><b>Extra payouts detected in the last 24H to explain negative balances (buggy Wallets)</b></th>
	</tr>
	<tr class="ssrow">
	<td colspan="3" style="font-size: .9em; padding-bottom: 8px;">
	Some wallets (UFO,LYB) have a problem and don't always confirm a transaction in the requested time.<br/>
	<!-- Please be honest and continue mining to handle these extra transactions sent to you. --><br/>
	</th>
	</tr>
	<tr class="ssrow">
	<th align="right">Time</th> <th align="right">Amount</th> <th>Tx</th>
	</tr>
end;

	$total = 0.0;
	foreach($list_extra as $payout)
	{
		$d = Yii::$app->ConversionUtils->datetoa2($payout->time);
		$amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($payout->amount);

		echo '<tr class="ssrow">';
		echo '<td align="right"><b>'.$d.' ago</b></td>';
		echo '<td align="right"><b>'.$amount.'</b></td>';

		$payout_tx = substr($payout->tx, 0, 36).'...';
		$link = $refcoin->createExplorerLink($payout_tx, array('txid'=>$payout->tx), array(), true);

		echo '<td style="font-family: monospace;">'.$link.'</td>';
		echo '</tr>';

		$total += $payout->amount;
	}

	$amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($total);

	echo <<<end
	<tr class="ssrow" style="color: darkred;">
	<td align="right">Total:</td>
	<td align="right"><b>{$amount}</b></td>
	<td></td>
	</tr>
end;
}


echo "</table><br>";
echo "</div>";

echo "</div><br>";
