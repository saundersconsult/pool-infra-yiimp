<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Workers';

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$currentAlgo = Yii::$app->session->get('yaamp-algo', '');
$algos       = Yii::$app->YiimpUtils->get_algos() ?? [];

// Build options; ensure the current algo is always present in the list
$options = '';
if ($currentAlgo !== '' && !in_array($currentAlgo, $algos, true)) {
    $options .= '<option value="' . Html::encode($currentAlgo) . '" selected>'
              . Html::encode($currentAlgo) . '</option>';
}
foreach ($algos as $a) {
    $sel      = ($a === $currentAlgo) ? ' selected' : '';
    $options .= '<option value="' . Html::encode($a) . '"' . $sel . '>'
              . Html::encode($a) . '</option>';
}

$homeUrl = Yii::$app->homeUrl;
?>

<div align="right" style="margin-top:-14px; margin-bottom:-6px; margin-right:140px;">
    Select Algo: <select id="algo_select"><?= $options ?></select>&nbsp;
</div>

<div id="main_results"></div>

<script>
$(function() {
    main_refresh();
    $('#algo_select').on('change', function() {
        main_refresh();
    });
});

var main_delay   = 30000;
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
    var url = '<?= $homeUrl ?>admin/worker_results?algo=' + encodeURIComponent($('#algo_select').val());
    $.get(url, '', main_ready).fail(main_error);
}
</script>
