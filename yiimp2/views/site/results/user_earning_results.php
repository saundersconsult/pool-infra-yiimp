<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Earnings;
use app\models\Coins;
use app\models\Blocks;

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

$algo = Yii::$app->session->get('yaamp-algo');

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user || $user->is_locked) return;

$count = (int) Yii::$app->getRequest()->getQueryParam('count');
$count = $count? $count: 50;

WriteBoxHeader("Last $count Earnings: $user->username");
$earnings = Earnings::find()
				->where(['userid' => $user->id])
				->orderBy('create_time desc')
				->limit($count)
				->all();

echo <<<EOT
<style type="text/css">
span.block { padding: 2px; display: inline-block; text-align: center; min-width: 75px; border-radius: 3px; }
span.block.invalid  { color: white; background-color: #d9534f; }
span.block.immature { color: white; background-color: #f0ad4e; }
span.block.exchange { color: white; background-color: #5cb85c; }
span.block.cleared  { color: white; background-color: gray; }
</style>
<table class="dataGrid2">
<thead>
<tr>
<td></td>
<th>Name</th>
<th align=right>Block</th>
<th align=right>Amount</th>
<th align=right>Percent</th>
<th align=right>mBTC</th>
<th align=right>Time</th>
<th align=right>Status</th>
</tr>
</thead>
EOT;

$showrental = (bool) YIIMP_RENTAL;

foreach($earnings as $earning)
{
	$coin = Coins::find()->where(['id'=>$earning->coinid])->one();
	$block = Blocks::find()->where(['id'=>$earning->blockid])->one();

	if (!$block) {
		debuglog("missing block id {$earning->blockid}!");
		continue;
	}

	$d = Yii::$app->ConversionUtils->datetoa2($earning->create_time);
	if(!$coin)
	{
		if (!$showrental)
			continue;

		$reward = Yii::$app->ConversionUtils->bitcoinvaluetoa($earning->amount);
		$value = Yii::$app->ConversionUtils->mbitcoinvaluetoa($earning->amount*1000);
		$percent = $block->amount ? Yii::$app->ConversionUtils->percentvaluetoa($earning->amount * 100/$block->amount) : 0;

		echo '<tr class="ssrow">';
		echo '<td width="18"><img width="16" src="/images/btc.png"></td>';
		echo '<td><b>Rental</b><span style="font-size: .8em;"> ('.$block->algo.')</span></td>';
		echo '<td align="right" style="font-size: .8em;"><b>'.$reward.' BTC</b></td>';
		echo '<td align="right" style="font-size: .8em;">'.$percent.'%</td>';
		echo '<td align="right" style="font-size: .8em;">'.$value.'</td>';
		echo '<td align="right" style="font-size: .8em;">'.$d.'&nbsp;ago</td>';
		echo '<td align="right" style="font-size: .8em;"><span class="block cleared">Cleared</span></td>';
		echo '</tr>';

		continue;
	}

	$height = number_format($block->height, 0, '.', ' ');
	$reward = Yii::$app->ConversionUtils->altcoinvaluetoa($earning->amount);
	$percent = $block->amount ? Yii::$app->ConversionUtils->percentvaluetoa($earning->amount * 100/$block->amount) : 0;
	$value = Yii::$app->ConversionUtils->mbitcoinvaluetoa($earning->amount*$earning->price*1000);

	$blockUrl = $coin->createExplorerLink($coin->name, array('height'=>$block->height));
	echo '<tr class="ssrow">';
	echo '<td width="18"><img width="16" src="'.$coin->image.'"></td>';
	echo '<td><b>'.$blockUrl.'</b><span style="font-size: .8em;"> ('.$coin->algo.')</span></td>';
	echo '<td align="right" style="font-size: .8em;">'.$height.'</td>';
	echo '<td align="right" style="font-size: .8em;"><b>'.$reward.' '.$coin->symbol_show.'</b></td>';
	echo '<td align="right" style="font-size: .8em;">'.$percent.'%</td>';
	echo '<td align="right" style="font-size: .8em;">'.$value.'</td>';
	echo '<td align="right" style="font-size: .8em;">'.$d.'&nbsp;ago</td>';
	echo '<td align="right" style="font-size: .8em;">';

	if($earning->status == 0) {
		$eta = '';
		if ($coin->block_time && $coin->mature_blocks) {
			$t = (int) ($coin->mature_blocks - $block->confirmations) * $coin->block_time;
			$eta = "ETA: ".sprintf('%dh %02dmn', ($t/3600), ($t/60)%60);
		}
		echo '<span class="block immature" title="'.$eta.'">Immature ('.$block->confirmations.'/'.$coin->mature_blocks.')</span>';
	}

	else if($earning->status == 1)
		echo '<span class="block exchange">'.(YIIMP_ALLOW_EXCHANGE ? 'Exchange' : 'Confirmed').'</span>';

	else if($earning->status == 2)
		echo '<span class="block cleared">Cleared</span>';

	else if($earning->status == -1)
		echo '<span class="block invalid">Invalid</span>';

	echo "</td>";
	echo "</tr>";
}

echo "</table>";

echo "<br></div></div><br>";
