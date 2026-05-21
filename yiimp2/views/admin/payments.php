<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Coins;

$coinId = (int) Yii::$app->request->get('id', 0);

if ($coinId) {
    $coin        = Coins::findOne($coinId);
    $this->title = 'Payments' . ($coin ? ' — ' . $coin->symbol : '');
} else {
    $this->title = 'Payments';
}

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$homeUrl = Yii::$app->homeUrl;
$jsId    = (int) $coinId;
?>

<div id="main_results"></div>

<script>
var main_delay   = 60000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}

function main_refresh() {
    clearTimeout(main_timeout);
    $('#main_results').css('min-height', ($(window).height() - 150) + 'px');
    $.get('<?= $homeUrl ?>admin/payments_results?id=<?= $jsId ?>', '', main_ready)
     .fail(main_error);
}

$(function() { main_refresh(); });
</script>
