<?php
/** @var yii\web\View $this */

$exch = Yii::$app->request->get('exch', '');

$this->title = 'Balances' . ($exch ? " - {$exch}" : '');

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>
<style type="text/css">
p.notes { opacity: 0.7; }
</style>

<div id="main_results"></div>

<p class="notes">Non-zero exchange balances tracked by yiimp. Use "update ticker" to manually trigger an API price refresh for that exchange.</p>

<script type="text/javascript">
var main_delay = 60000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}

function main_refresh() {
    var url = '/admin/balances_results?exch=<?= urlencode($exch) ?>';
    clearTimeout(main_timeout);
    $.get(url, '', main_ready).fail(main_error);
}
</script>

<?php Yii::$app->view->registerJs('main_refresh();'); ?>
