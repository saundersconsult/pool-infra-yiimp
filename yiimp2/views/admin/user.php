<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\db\Query;
use app\models\Coins;

$this->title = 'Users';

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$symbol = Yii::$app->request->get('symbol', 'all');

// Coins that have active users with a balance or recent earnings
$activeCoins = Coins::find()
    ->where(['enable' => 1])
    ->andWhere(['or',
        ['in', 'id', (new Query)->select('coinid')->from('accounts')->where(['>', 'balance', 0.0001])->distinct()],
        ['in', 'id', (new Query)->select('coinid')->from('earnings')->distinct()],
    ])
    ->orderBy('symbol')
    ->all();

$options = '<option value="all"' . ($symbol === 'all' ? ' selected' : '') . '>-all-</option>';
foreach ($activeCoins as $coin) {
    $sel     = $coin->symbol === $symbol ? ' selected' : '';
    $options .= '<option value="' . Html::encode($coin->symbol) . '"' . $sel . '>'
              . Html::encode($coin->symbol) . '</option>';
}

$homeUrl = Yii::$app->homeUrl;
?>

<div align="right" style="margin-top:-14px; margin-bottom:-6px; margin-right:140px;">
    Select coin: <select id="coin_select"><?= $options ?></select>&nbsp;
</div>

<div id="main_results"></div>

<script>
$(function() {
    $('#coin_select').on('change', function() {
        window.location.href = '<?= $homeUrl ?>admin/user?symbol=' + encodeURIComponent($(this).val());
    });
    main_refresh();
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
    var symbol = $('#coin_select').val();
    clearTimeout(main_timeout);
    $.get('<?= $homeUrl ?>admin/user_results?symbol=' + encodeURIComponent(symbol), '', main_ready)
     .fail(main_error);
}
</script>
