<?php

/** @var yii\web\View $this */
/* @var $form yii\widgets\ActiveForm */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\jui\Tabs;
use app\components\CUFHtml;

use yii\helpers\ArrayHelper;
use app\models\Algos;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

if (!is_null($coin->id))
	echo " - <a href='/admin/coinwallet?id={$coin->id}'>{$coin->name}</a><br/>";
else
	echo " - new coin";

$ListAlgos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
$coin_algo = ($coin->algo)? '<span style="color: green;">'.$coin->algo.'</span>' : '<span style="color: red;">None</span>';

$form = ActiveForm::begin(['options' => ['class' => 'uniForm']]);

echo CUFHtml::beginTag('fieldset', array('class'=>'inlineLabels'));

$tab_general = 
	CUFHtml::openActiveCtrlHolder($coin, 'name').
	CUFHtml::activeLabelEx($coin, 'name').
	CUFHtml::activeTextField($coin, 'name', array('maxlength'=>200)).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'symbol').
	CUFHtml::activeLabelEx($coin, 'symbol').
	CUFHtml::activeTextField($coin, 'symbol', array('maxlength'=>200,'style'=>'width: 120px;')).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'symbol2').
	CUFHtml::activeLabelEx($coin, 'symbol2').
	CUFHtml::activeTextField($coin, 'symbol2', array('maxlength'=>200,'style'=>'width: 120px;')).
	'<p class="formHint2">Set it if symbol is different</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'algo').
	CUFHtml::activeLabelEx($coin, 'algo').
	CUFHtml::dropDownList('db_coins[algo]', $coin->algo, $ListAlgos, array(
		'style' => 'border: 1px solid #dfdfdf; height: 26px; width:135px',
		'class' => 'textInput tweetnews-input'
	)).
	'<label style="padding-left: 20px;" for="algo">Algo Selected: '.$coin_algo.'</label>'.
	'<p class="formHint2">Required all lower case</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'auto_exchange').
	CUFHtml::activeLabelEx($coin, 'auto_exchange').
	CUFHtml::activeCheckBox($coin, 'auto_exchange', ['label' => '']).
	'<p class="formHint2">include in automatic miningselection</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'image').
	CUFHtml::activeLabelEx($coin, 'image').
	CUFHtml::activeTextField($coin, 'image', array('maxlength'=>200)).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'version_installed').
	CUFHtml::activeLabelEx($coin, 'version_installed').
	CUFHtml::activeTextField($coin, 'version_installed', array('maxlength'=>64,'style'=>'width: 120px;')).
	'<p class="formHint2">walletversion installed</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'version_github').
	CUFHtml::activeLabelEx($coin, 'version_github').
	CUFHtml::activeTextField($coin, 'version_github', array('maxlength'=>200,'style'=>'width: 100px;','readonly'=>'readonly')).
	'<p class="formHint2">walletversion on GitHub</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'payout_min').
	CUFHtml::activeLabelEx($coin, 'payout_min').
	CUFHtml::activeTextField($coin, 'payout_min', array('maxlength'=>200,'style'=>'width: 120px;')).
	'<p class="formHint2">Pay users when they reach this amount</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'payout_max').
	CUFHtml::activeLabelEx($coin, 'payout_max').
	CUFHtml::activeTextField($coin, 'payout_max', array('maxlength'=>200,'style'=>'width: 120px;')).
	'<p class="formHint2">Maximum transaction amount</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'txfee').
	CUFHtml::activeLabelEx($coin, 'txfee').
	CUFHtml::activeTextField($coin, 'txfee', array('maxlength'=>200,'style'=>'width: 100px;','readonly'=>'readonly')).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'block_height').
	CUFHtml::activeLabelEx($coin, 'block_height').
	CUFHtml::activeTextField($coin, 'block_height', array('readonly'=>'readonly','style'=>'width: 120px;')).
	'<p class="formHint2">Current height</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'target_height').
	CUFHtml::activeLabelEx($coin, 'target_height').
	CUFHtml::activeTextField($coin, 'target_height', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Known height of the network</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'powend_height').
	CUFHtml::activeLabelEx($coin, 'powend_height').
	CUFHtml::activeTextField($coin, 'powend_height', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Height of the end of PoW mining</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'mature_blocks').
	CUFHtml::activeLabelEx($coin, 'mature_blocks').
	CUFHtml::activeTextField($coin, 'mature_blocks', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Required block count to mature</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'powlimit_bits').
	CUFHtml::activeLabelEx($coin, 'powlimit_bits').
	CUFHtml::activeTextField($coin, 'powlimit_bits', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">number of leading \'0\' bits on powlimit (basehash for diff 1)</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'block_time').
	CUFHtml::activeLabelEx($coin, 'block_time').
	CUFHtml::activeTextField($coin, 'block_time', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Average block time (sec)</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'errors').
	CUFHtml::activeLabelEx($coin, 'errors').
	CUFHtml::activeTextField($coin, 'errors', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 600px;')).
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'specifications').
	CUFHtml::activeLabelEx($coin, 'specifications').
	CUFHtml::activeTextArea($coin, 'specifications', array('maxlength'=>2048,'lines'=>5,'class'=>'tweetnews-input','style'=>'width: 600px;')).
	CUFHtml::closeCtrlHolder()
		;

$tab_settings = 
	CUFHtml::openActiveCtrlHolder($coin, 'enable').
	CUFHtml::activeLabelEx($coin, 'enable').
	CUFHtml::activeCheckBox($coin, 'enable', ['label' => '']).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'auto_ready').
	CUFHtml::activeLabelEx($coin, 'auto_ready').
	CUFHtml::activeCheckBox($coin, 'auto_ready', ['label' => '']).
	'<p class="formHint2">Allowed to mine</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'visible').
	CUFHtml::activeLabelEx($coin, 'visible').
	CUFHtml::activeCheckBox($coin, 'visible', ['label' => '']).
	'<p class="formHint2">Visibility for the public</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'installed').
	CUFHtml::activeLabelEx($coin, 'installed').
	CUFHtml::activeCheckBox($coin, 'installed', ['label' => '']).
	'<p class="formHint2">Required to be visible in the Wallets board</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'no_explorer').
	CUFHtml::activeLabelEx($coin, 'no_explorer').
	CUFHtml::activeCheckBox($coin, 'no_explorer', ['label' => '']).
	'<p class="formHint2">Disable block explorer for the public</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'watch').
	CUFHtml::activeLabelEx($coin, 'watch').
	CUFHtml::activeCheckBox($coin, 'watch', ['label' => '']).
	'<p class="formHint2">Track balance and markets history</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'auxpow').
	CUFHtml::activeLabelEx($coin, 'auxpow').
	CUFHtml::activeCheckBox($coin, 'auxpow', ['label' => '']).
	'<p class="formHint2">Merged mining</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'enable_rpcdebug').
	CUFHtml::activeLabelEx($coin, 'enable_rpcdebug').
	CUFHtml::activeCheckBox($coin, 'enable_rpcdebug', ['label' => '']).
	'<p class="formHint2">enable debug of rpc-communication from stratum to wallet</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'personalization').
	CUFHtml::activeLabelEx($coin, 'personalization').
	CUFHtml::activeTextField($coin, 'personalization', array('maxlength'=>100)).
	'<p class="formHint2">personalization-string for equihash-coins<br>default "ZcashPoW" (see src/crypto/equihash.cpp for value)</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'max_miners').
	CUFHtml::activeLabelEx($coin, 'max_miners').
	CUFHtml::activeTextField($coin, 'max_miners', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Miners allowed by the stratum</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'max_shares').
	CUFHtml::activeLabelEx($coin, 'max_shares').
	CUFHtml::activeTextField($coin, 'max_shares', array('maxlength'=>32,'style'=>'width: 120px;')).
	'<p class="formHint2">Auto restart stratum after this amount of shares</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'master_wallet').
	CUFHtml::activeLabelEx($coin, 'master_wallet').
	CUFHtml::activeTextField($coin, 'master_wallet', array('maxlength'=>200)).
	'<p class="formHint2">The pool wallet address</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'wallet_zaddress').
	CUFHtml::activeLabelEx($coin, 'wallet_zaddress').
	CUFHtml::activeTextField($coin, 'wallet_zaddress', array('maxlength'=>200)).
	'<p class="formHint2">zaddress for privacy-coins (zcash)</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'reward').
	CUFHtml::activeLabelEx($coin, 'reward').
	CUFHtml::activeTextField($coin, 'reward', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 120px;')).
	'<p class="formHint2">PoW block value</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'reward_mul').
	CUFHtml::activeLabelEx($coin, 'reward_mul').
	CUFHtml::activeTextField($coin, 'reward_mul', array('maxlength'=>200,'style'=>'width: 120px;')).
	'<p class="formHint2">Adjust the block reward if incorrect</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'charity_percent').
	CUFHtml::activeLabelEx($coin, 'charity_percent').
	CUFHtml::activeTextField($coin, 'charity_percent', array('maxlength'=>10,'style'=>'width: 30px;')).
	'<p class="formHint2">Reward for foundation or dev fees, generally between 1 and 10 %</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'charity_address').
	CUFHtml::activeLabelEx($coin, 'charity_address').
	CUFHtml::activeTextField($coin, 'charity_address', array('maxlength'=>200)).
	'<p class="formHint2">Foundation address if "dev fees" are required</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'hasgetinfo').
	CUFHtml::activeLabelEx($coin, 'hasgetinfo').
	CUFHtml::activeCheckBox($coin, 'hasgetinfo', ['label' => '']).
	'<p class="formHint2">Enable if getinfo rpc method is present</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'hassubmitblock').
	CUFHtml::activeLabelEx($coin, 'hassubmitblock').
	CUFHtml::activeCheckBox($coin, 'hassubmitblock', ['label' => '']).
	'<p class="formHint2">Enable if submitblock method is present</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'txmessage').
	CUFHtml::activeLabelEx($coin, 'txmessage').
	CUFHtml::activeCheckBox($coin, 'txmessage', ['label' => '']).
	'<p class="formHint2">Block template with a tx message</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'hasmasternodes').
	CUFHtml::activeLabelEx($coin, 'hasmasternodes').
	CUFHtml::activeCheckBox($coin, 'hasmasternodes', ['label' => '']).
	'<p class="formHint2">Require "payee" and "payee_amount", or masternode object in getblocktemplate</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'usesegwit').
	CUFHtml::activeLabelEx($coin, 'usesegwit').
	CUFHtml::activeCheckBox($coin, 'usesegwit', ['label' => '']).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'usemweb').
	CUFHtml::activeLabelEx($coin, 'usemweb').
	CUFHtml::activeCheckBox($coin, 'usemweb', ['label' => '']).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder();

$tab_exchange = 
	CUFHtml::openActiveCtrlHolder($coin, 'dontsell').
	CUFHtml::activeLabelEx($coin, 'dontsell').
	CUFHtml::activeCheckBox($coin, 'dontsell', ['label' => '']).
	'<p class="formHint2">Disable auto send to exchange</p>'.
	CUFHtml::closeCtrlHolder().


	CUFHtml::openActiveCtrlHolder($coin, 'sellonbid').
	CUFHtml::activeLabelEx($coin, 'sellonbid').
	CUFHtml::activeCheckBox($coin, 'sellonbid', ['label' => '']).
	'<p class="formHint2">Reduce the sell price on exchanges</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'sellthreshold').
	CUFHtml::activeLabelEx($coin, 'sellthreshold').
	CUFHtml::activeTextField($coin, 'sellthreshold', array('maxlength'=>16,'style'=>'width: 120px;')).
	'<p class="formHint2">min amount to sell</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'market').
	CUFHtml::activeLabelEx($coin, 'market').
	CUFHtml::activeTextField($coin, 'market', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2">Selected exchange</p>'.
	CUFHtml::closeCtrlHolder().

	((empty($coin->price) || empty($coin->market) || $coin->market == 'unknown') ? 

	(CUFHtml::openActiveCtrlHolder($coin, 'price').
	CUFHtml::activeLabelEx($coin, 'price').
	CUFHtml::activeTextField($coin, 'price', array('maxlength'=>16,'style'=>'width: 180px;')).
	'<p class="formHint2">Manually set the BTC price if missing</p>'.
	CUFHtml::closeCtrlHolder()) : '')
	;

// prepare data for tab
if(empty($coin->rpcport))
	$coin->rpcport = $coin->id*10;
if(empty($coin->rpcuser))
	$coin->rpcuser = 'yiimprpc';
// generate a random password
if(empty($coin->rpcpasswd))
	$coin->rpcpasswd = preg_replace("|[^\w]|m",'',base64_encode(pack("H*",md5("".time().YAAMP_SITE_URL))));

$port = Yii::$app->YiimpUtils->getAlgoPort($coin->algo);
$dedport = $coin->dedicatedport;

$tab_daemon = 
	CUFHtml::openActiveCtrlHolder($coin, 'program').
	CUFHtml::activeLabelEx($coin, 'program').
	CUFHtml::activeTextField($coin, 'program', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2">Daemon process name</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'conf_folder').
	CUFHtml::activeLabelEx($coin, 'conf_folder').
	CUFHtml::activeTextField($coin, 'conf_folder', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2">Generally close to the process name (.bitcoin)</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpchost').
	CUFHtml::activeLabelEx($coin, 'rpchost').
	CUFHtml::activeTextField($coin, 'rpchost', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2">Daemon (Wallet) IP</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpcport').
	CUFHtml::activeLabelEx($coin, 'rpcport').
	CUFHtml::activeTextField($coin, 'rpcport', array('maxlength'=>5,'style'=>'width: 60px;')).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpcuser').
	CUFHtml::activeLabelEx($coin, 'rpcuser').
	CUFHtml::activeTextField($coin, 'rpcuser', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpcpasswd').
	CUFHtml::activeLabelEx($coin, 'rpcpasswd').
	CUFHtml::activeTextField($coin, 'rpcpasswd', array('maxlength'=>128)).
	'<p class="formHint2"></p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'serveruser').
	CUFHtml::activeLabelEx($coin, 'serveruser').
	CUFHtml::activeTextField($coin, 'serveruser', array('maxlength'=>35,'style'=>'width: 180px;')).
	'<p class="formHint2">Daemon process username</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpcencoding').
	CUFHtml::activeLabelEx($coin, 'rpcencoding').
	CUFHtml::activeTextField($coin, 'rpcencoding', array('maxlength'=>5,'style'=>'width: 60px;')).
	'<p class="formHint2">POW/POS</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'dedicatedport').
	CUFHtml::activeLabelEx($coin, 'dedicatedport').
	CUFHtml::activeTextField($coin, 'dedicatedport', array(
		'maxlength' => 5,
		'style' => 'width: 60px;'
	)).
	'<p class="formHint2">Run addport to get Port Number</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpccurl').
	CUFHtml::activeLabelEx($coin, 'rpccurl').
	CUFHtml::activeCheckBox($coin, 'rpccurl', ['label' => '']).
	'<p class="formHint2">Force the stratum to use curl for RPC</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpcssl').
	CUFHtml::activeLabelEx($coin, 'rpcssl').
	CUFHtml::activeCheckBox($coin, 'rpcssl', ['label' => '']).
	'<p class="formHint2">Wallet RPC secured via SSL</p>'.
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'rpccert').
	CUFHtml::activeLabelEx($coin, 'rpccert').
	CUFHtml::activeTextField($coin, 'rpccert').
	"<p class='formHint2'>Certificat file for RPC via SSL</p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'account').
	CUFHtml::activeLabelEx($coin, 'account').
	CUFHtml::activeTextField($coin, 'account', array('maxlength'=>128,'style'=>'width: 180px;')).
	'<p class="formHint2">Wallet account to use</p>'.
	CUFHtml::closeCtrlHolder().

(($coin->id) ?
	Html::tag("hr").
	"<b>Sample config</b>:".
	Html::beginTag("pre").
	"rpcuser={$coin->rpcuser}\n".
	"rpcpassword={$coin->rpcpasswd}\n".
	"rpcport={$coin->rpcport}\n".
	"rpcthreads=8\n".
	"rpcallowip=127.0.0.1\n".
	"# onlynet=ipv4\n".
	"maxconnections=12\n".
	"daemon=1\n".
	"gen=0\n".
	"\n".
	"alertnotify=%s | mail -s \"{$coin->name} alert!\" ".YAAMP_ADMIN_EMAIL."\n".
	((empty($coin->dedicatedport))?
        "blocknotify=/var/stratum/blocknotify ".YAAMP_STRATUM_URL.":$port {$coin->id} %s\n"
		:
		"blocknotify=/var/stratum/blocknotify ".YAAMP_STRATUM_URL.":$dedport {$coin->id} %s\n"
	).
    " \n".
    Html::endTag("pre").

	Html::tag("hr").
	"<b>Miner command line</b>:".
	Html::beginTag("pre").
	"-a {$coin->algo} ".
	((empty($coin->dedicatedport))?
		"-o stratum+tcp://" . YAAMP_STRATUM_URL . ':' . $port . ' '
		:
		"-o stratum+tcp://" . YAAMP_STRATUM_URL . ':' . $dedport . ' ').
    "-u {$coin->master_wallet} ".
	"-p c={$coin->symbol} ".
	"\n".
	Html::endTag("pre")
: '')

;

$tab_links = 
	CUFHtml::openActiveCtrlHolder($coin, 'link_bitcointalk').
	CUFHtml::activeLabelEx($coin, 'link_bitcointalk').
	CUFHtml::activeTextField($coin, 'link_bitcointalk').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_github').
	CUFHtml::activeLabelEx($coin, 'link_github').
	CUFHtml::activeTextField($coin, 'link_github').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_site').
	CUFHtml::activeLabelEx($coin, 'link_site').
	CUFHtml::activeTextField($coin, 'link_site').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_exchange').
	CUFHtml::activeLabelEx($coin, 'link_exchange').
	CUFHtml::activeTextField($coin, 'link_exchange').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_explorer').
	CUFHtml::activeLabelEx($coin, 'link_explorer').
	CUFHtml::activeTextField($coin, 'link_explorer').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_twitter').
	CUFHtml::activeLabelEx($coin, 'link_twitter').
	CUFHtml::activeTextField($coin, 'link_twitter').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_discord').
	CUFHtml::activeLabelEx($coin, 'link_discord').
	CUFHtml::activeTextField($coin, 'link_discord').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder().

	CUFHtml::openActiveCtrlHolder($coin, 'link_facebook').
	CUFHtml::activeLabelEx($coin, 'link_facebook').
	CUFHtml::activeTextField($coin, 'link_facebook').
	"<p class='formHint2'></p>".
	CUFHtml::closeCtrlHolder()
;
// render Tab Widget

echo Tabs::widget([
        'items' => [
            [
                'label' => 'General',
                'content' => $tab_general,
                'active' => true
            ],
            [
                'label' => 'Settings',
                'content' => $tab_settings,
            ],
            [
                'label' => 'Exchange',
                'content' => $tab_exchange,
                'visible' => false,
            ],
            [
                'label' => 'Daemon',
                'content' => $tab_daemon,
                'visible' => false,
            ],
            [
                'label' => 'Links',
                'content' => $tab_links,
                'visible' => false,
            ],
        ],
    ]);

'<br><br><div class="form-group">';
echo Html::submitButton(($update? 'Save': 'Create'), ['class' => 'submitButton ui-button ui-corner-all ui-widget']);
'</div>';
echo CUFHtml::endTag('fieldset');
ActiveForm::end();
