<?php

/** @var yii\web\View $this */

$this->title = 'Dashboard';

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

&nbsp;-&nbsp;
<a href='/admin/memcached'>Memcache</a>&nbsp;
<a href='/admin/connections'>Connections</a>&nbsp;

<?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
<a href='/renting/admin'>Rental</a>&nbsp;
<?php endif ?>

<!-- Graph containers are injected by common_results.php into #main_results below. -->

<style>
#graph_results_assets,
#graph_results_negative  { overflow: hidden; width: 100%; }
</style>

<div id="main_results"></div>

<br>
<a href='/admin/coincreate'><b>CREATE COIN</b></a>&nbsp;&nbsp;
<a href='/admin/updateprice'><b>UPDATE PRICE</b></a>

<script>

$(function() { main_refresh(); });

var main_delay   = 30000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    main_timeout = setTimeout(main_refresh, main_delay);
    // Graph containers are now in the DOM — fetch and render
    main_refresh_assets();
    main_refresh_negative();
}

function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}

function main_refresh() {
    clearTimeout(main_timeout);
    $.get('/admin/common_results', '', main_ready).fail(main_error);
}

// ── Assets graph ─────────────────────────────────────────────────────────────
function main_refresh_assets() {
    $.get('/admin/graph_assets_results', '', function(data) {
        var $el = $('#graph_results_assets');
        if (!$el.length) return;
        try {
            var t = JSON.parse(data);
            var isEmpty = !t || t.every(function(s) { return !s || s.length === 0; });
            if (isEmpty) {
                $el.html('<div style="color:#aaa;text-align:center;padding-top:60px;">No stats data yet — StatsService needs to run first</div>');
                return;
            }
            yiimpChart('graph_results_assets', t, {
                type:   'bar',
                stack:  true,
                labels: ['Margin', 'Balances', 'On sell', 'Wallets']
            });
        } catch(e) {
            $el.html('<div style="color:#aaa;text-align:center;padding-top:60px;">Graph error: ' + e.message + '</div>');
        }
    });
}

// ── Liabilities graph ─────────────────────────────────────────────────────────
function main_refresh_negative() {
    $.get('/admin/graph_negative_results', '', function(data) {
        var $el = $('#graph_results_negative');
        if (!$el.length) return;
        try {
            var t = JSON.parse(data);
            var isEmpty = !t || t.every(function(s) { return !s || s.length === 0; });
            if (isEmpty) {
                $el.html('<div style="color:#aaa;text-align:center;padding-top:50px;">No stats data yet</div>');
                return;
            }
            yiimpChart('graph_results_negative', t, {
                type:   'bar',
                stack:  true,
                labels: ['Waiting', 'Immature']
            });
        } catch(e) {
            $el.html('<div style="color:#aaa;text-align:center;padding-top:50px;">Graph error: ' + e.message + '</div>');
        }
    });
}
</script>
