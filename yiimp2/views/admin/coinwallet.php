<?php

/** @var yii\web\View $this */

use app\components\rpc\WalletRPC;
use app\models\Coins;

$id = (int) Yii::$app->getRequest()->getQueryParam('id');
$coin = Coins::findOne($id);

if (!$coin) {
	return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));
}

$this->title = 'Wallet - '.$coin->symbol;

// force a refresh after 10mn to prevent memory leaks in chrome
$this->registerMetaTag(['http-equiv' => 'refresh', 'content' => '600']);

if (!empty($coin->algo) && $coin->algo != 'PoS')
	Yii::$app->session->set('yaamp-algo', $coin->algo);

$remote = new WalletRPC($coin);
$info = ($remote)? $remote->getinfo() : false;

$sellamount = $coin->balance;
//if ($info) $sellamount = floatval($sellamount) - arraySafeVal($info, "paytxfee") * 3;

echo Yii::$app->ViewUtils->getAdminSideBarLinks().'<br/><br/>';
echo Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet');

$maxrows = Yii::$app->ConversionUtils->arraySafeVal($_REQUEST,'rows', 500);
$since = Yii::$app->ConversionUtils->arraySafeVal($_REQUEST,'since', time() - (7*24*3600)); // one week

echo '<div id="main_actions">';

echo <<<END

<br/><a class="red" href="/admin/deleteearnings?id={$coin->id}"><b>DELETE EARNINGS</b></a>
<br/><a href="/admin/clearearnings?id={$coin->id}"><b>CLEAR EARNINGS</b></a>
<br/><a href="/admin/checkblocks?id={$coin->id}"><b>UPDATE BLOCKS</b></a>
<br/><a href="/admin/payuserscoin?id={$coin->id}"><b>DO PAYMENTS</b></a>
<br/>
</div>

<style type="text/css">
table.dataGrid a.red, table.dataGrid a.red:visited, a.red { color: darkred; }
div#main_actions {
	position: absolute; top: 60px; right: 16px; width: 280px; text-align: right;
}
div#markets {
	overflow-x: hidden; overflow-y: scroll; max-height: 156px;
}
div#transactions {
	overflow-x: hidden; overflow-y: scroll; min-height: 200px; max-height: 360px;
	margin-bottom: 8px;
}
div#sums {
	overflow-x: hidden; overflow-y: scroll; min-height: 250px; max-height: 600px;
	width: 380px; float: left; margin-top: 16px; margin-bottom: 8px; margin-right: 16px;
}
.page .footer { clear: both; width: auto; margin-top: 16px; }
tr.ssrow.bestmarket { background-color: #dfd; }
tr.ssrow.disabled { background-color: #fdd; color: darkred; }
tr.ssrow.orphan { color: darkred; }
</style>

<div id="main_results"></div>

<script type="text/javascript">

function uninstall_coin()
{
	if(!confirm("Uninstall this coin?"))
		return;

	window.location.href = '/admin/uninstallcoin?id=$coin->id';
}

var main_delay=30000;
var main_timeout;

function main_refresh()
{
	var url = "/admin/coinwallet_details?id={$id}&rows={$maxrows}&since={$since}";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready);
}

function main_ready(data)
{
	$('#main_results').html(data);
	$(window).trigger('resize'); // will draw graph
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function showSellAmountDialog(marketname, address, marketid, bookmarkid)
{
	marketid   = marketid   || 0;
	bookmarkid = bookmarkid || 0;

	document.getElementById('sell-dialog-title').textContent = 'Send $coin->symbol to ' + marketname;
	document.getElementById('dlgaddr').textContent = address;

	document.getElementById('btn-sell-confirm').onclick = function() {
		var amount = document.getElementById('input_sell_amount').value;
		if (marketid > 0)
			window.location.href = '/admin/market/sellto?id=' + marketid + '&amount=' + amount;
		else
			window.location.href = '/admin/bookmarkSend?id=' + bookmarkid + '&amount=' + amount;
	};

	new bootstrap.Modal(document.getElementById('sell-amount-dialog')).show();
	return false;
}

</script>

<div class="modal fade" id="sell-amount-dialog" tabindex="-1" aria-labelledby="sell-dialog-title" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sell-dialog-title">Send to Exchange</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Address: <span id="dlgaddr"></span></p>
        Amount: <input type="text" id="input_sell_amount" class="form-control mt-1" value="$sellamount">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-sell-confirm">Send / Sell</button>
      </div>
    </div>
  </div>
</div>

END;

Yii::$app->view->registerJs("main_refresh();");

if ($coin->watch) {
	echo Yii::$app->controller->renderPartial('coin_market_graph', array('coin'=>$coin));
//	echo $this->renderPartial('coin_market_graph', array('coin'=>$coin));
	Yii::$app->view->registerJs("$(window).resize(graph_resized);");
}

//////////////////////////////////////////////////////////////////////////////////////
