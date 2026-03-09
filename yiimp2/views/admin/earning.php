<?php

use app\models\Coins;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$coin_id = (int) Yii::$app->getRequest()->getQueryParam('id');
if ($coin_id) {
	$coin = Coins::findOne(['id' => $coin_id]);
	$this->title = 'Earnings - '.$coin->symbol;
}
?>

<div id='main_results'></div>

<script type="text/javascript">

var main_delay=60000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = '/admin/earning_results?id=<?= $coin_id ?>';
	var minh = $(window).height() - 150;
	$('#main_results').css({'min-height': minh + 'px'});

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php

Yii::$app->view->registerJs("main_refresh();");
