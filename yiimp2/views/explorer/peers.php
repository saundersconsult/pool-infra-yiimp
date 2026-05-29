<?php

use app\components\rpc\WalletRPC;

if (!$coin) $this->goback();

//require dirname(__FILE__).'/../../ui/lib/pageheader.php';

$pageTitle = 'Peers - '.$coin->name;

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

//////////////////////////////////////////////////////////////////////////////////////

echo <<<end
<style type="text/css">
body { margin: 4px; }
pre { margin: 0 4px; }
</style>

<div class="main-left-box">
<div class="main-left-title">{$pageTitle}</div>
<div class="main-left-inner">
end;

$addnode = array();
$version = '';
$localheight = Yii::$app->ConversionUtils->arraySafeVal($info, 'blocks');

$list = $remote->getpeerinfo();

if(!empty($list))
foreach($list as $peer)
{
	$node = Yii::$app->ConversionUtils->arraySafeVal($peer,'addr');
	if (strstr($node,'127.0.0.1')) continue;
	if (strstr($node,'192.168.')) continue;
	if (strstr($node,'yiimp')) continue;

	$addnode[] = ($coin->rpcencoding=='DCR' ? 'addpeer=' : 'addnode=') . $node;

	$peerver = trim(Yii::$app->ConversionUtils->arraySafeVal($peer,'subver'),'/');
	$version = max($version, $peerver);
}

asort($addnode);

echo '<pre>';
echo implode("\n",$addnode);
echo '</pre>';

echo '</div>';
echo '</div>';
