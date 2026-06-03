<?php

use app\components\rpc\WalletRPC;

if (!$coin) {
	return Yii::$app->controller->goHome();
}

$this->title = $coin->name." block explorer";

$start = (int) Yii::$app->getRequest()->getQueryParam('start');

echo <<<END
<script type="text/javascript">
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
});
</script>
<style type="text/css">
table.dataGrid2 { margin-top: 0; }
span.monospace { font-family: monospace; }
.main-text-input { }
.page .footer { width: auto; }
</style>
END;

// version is used for multi algo coins
// but each coin use different values...
$multiAlgos = $coin->multialgos || Yii::$app->ExplorerUtils->versionToAlgo($coin, 0) !== false;

echo '<br/>';
echo '<div class="main-left-box">';
echo '<div class="main-left-title">'.$coin->name.' Explorer</div>';
echo '<div class="main-left-inner" style="padding-left: 8px; padding-right: 8px;">';

echo '<table class="dataGrid2">';

echo "<thead>";
echo "<tr>";
echo "<th>Age</th>";
echo "<th>Height</th>";
echo "<th>Difficulty</th>";
echo "<th>Type</th>";
if ($multiAlgos) echo "<th>Algo</th>";
echo "<th>Tx</th>";
echo "<th>Conf</th>";
echo "<th>Blockhash</th>";
echo "</tr>";
echo "</thead>";

$remote = new WalletRPC($coin);
if (!$start || $start > $coin->block_height)
	$start = $coin->block_height;
for($i = $start; $i > max(1, $start-21); $i--)
{
	$hash = $remote->getblockhash($i);
	if(!$hash) continue;

	$block = $remote->getblock($hash);
	if(!$block) continue;

	$d = Yii::$app->ConversionUtils->datetoa2($block['time']);
	$confirms = isset($block['confirmations'])? $block['confirmations']: '';
	$tx = count($block['tx']);
	$diff = $block['difficulty'];
	$algo = Yii::$app->ExplorerUtils->versionToAlgo($coin, $block['version']);
	$type = '';
	if (Yii::$app->ConversionUtils->arraySafeval($block,'nonce',0) > 0) $type = 'PoW';
	else if (isset($block['auxpow'])) $type = 'Aux';
	else if (isset($block['mint']) || strstr(Yii::$app->ConversionUtils->arraySafeVal($block,'flags',''), 'proof-of-stake')) $type = 'PoS';

	// nonce 256bits
	if ($type == '' && $coin->symbol=='ZEC') $type = 'PoW';

//	debuglog($block);
	echo '<tr class="ssrow">';
	echo '<td>'.$d.'</td>';

	echo '<td>'.$coin->createExplorerLink($i, array('height'=>$i)).'</td>';

	echo '<td>'.$diff.'</td>';
	echo '<td>'.$type.'</td>';
	if ($multiAlgos) echo "<td>$algo</td>";
	echo '<td>'.$tx.'</td>';
	echo '<td>'.$confirms.'</td>';

	echo '<td style="overflow-x: hidden; max-width:800px;"><span class="monospace">';
	echo $coin->createExplorerLink($hash, array('hash'=>$hash));
	echo '</span></td>';

	echo "</tr>";
}

echo "</table>";

$pager = '';
if ($start <= $coin->block_height - 20)
	$pager  = $coin->createExplorerLink('<< Prev', array('start'=>min($coin->block_height,$start+20)));
if ($start != $coin->block_height)
	$pager .= '&nbsp; '.$coin->createExplorerLink('Now');
if ($start > 20)
	$pager .= '&nbsp; '.$coin->createExplorerLink('Next >>', array('start'=>max(1,$start-20)));

$actionUrl = $coin->visible ? '/explorer/'.$coin->symbol : '/explorer/search?id='.$coin->id;

echo <<<end
<div id="pager" style="float: right; width: 200px; text-align: right; margin-right: 16px; margin-top: 8px;">$pager</div>
<div id="form" style="width: 660px; height: 50px; overflow: hidden;">
<form action="{$actionUrl}" method="POST" style="padding-top: 4px; width: 650px;">
<input type="text" name="height" class="main-text-input" placeholder="Height" style="width: 80px;">
<input type="text" name="txid" class="main-text-input" placeholder="Transaction hash" style="width: 450px; margin: 4px;">
<input type="submit" value="Search" class="main-submit-button" >
</form>
</div>
end;

if ($start != $coin->block_height)
	return;

echo <<<end
<div id="diff_graph" style="margin-right:8px;margin-top:-16px;height:220px;"></div>

<script type="text/javascript">
var last_graph_update = 0;
function graph_refresh() {
    var now = Date.now() / 1000;
    if (now < last_graph_update + 900) return;
    last_graph_update = now;
    $.get('/explorer/graph?id={$coin->id}', '', diff_graph_data);
}
function diff_graph_data(data) {
    var t = JSON.parse(data);
    if (!t || !t.length) return;
    var s1 = t[0].map(function(p) { return [p[0], p[1]]; });
    var s2 = t[1] ? t[1].map(function(p) { return [p[0], p[1]]; }) : null;
    yiimpChart('diff_graph', s2 ? [s1, s2] : [s1], {
        title: 'Network diff', labels: ['Difficulty', 'Pool blocks'], decimals: 3
    });
}
</script>
end;

Yii::$app->view->registerJs(" graph_refresh(); ", \yii\web\View::POS_READY, 'graph');
