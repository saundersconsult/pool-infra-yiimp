<?php

/** @var yii\web\View $this */

$height = '240px';

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$algo      = Yii::$app->session->get('yaamp-algo');
$homeUrl   = Yii::$app->homeUrl;
$algoUnit  = 'Mh';
$algoFactor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
if ($algoFactor == 0.001)       $algoUnit = 'Kh';
if ($algoFactor == 1000)        $algoUnit = 'Gh';
if ($algoFactor == 1000000)     $algoUnit = 'Th';
if ($algoFactor == 1000000000)  $algoUnit = 'Ph';

?>
<style>
/*
 * Two-column layout: Bootstrap row/col replaces the old <table> so that
 * column widths are well-defined before jqplot draws its canvases.
 * The overflow guards prevent any chart overflow from pushing sibling columns.
 */
.mining-col        { min-width: 0; }   /* critical: lets flex children shrink */
.graph-wrap        { overflow: hidden; width: 100%; }
.graph-wrap canvas { max-width: 100% !important; }

/* jqplot internal elements must not escape their wrapper */
.jqplot-target     { overflow: hidden !important; }

.main-left-box     { margin-bottom: 1rem; }
</style>

<!-- "Auto refresh paused" banner -->
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<!-- Two-column layout via Bootstrap grid -->
<div class="row gx-4 mt-2">

    <!-- LEFT: algo mining table + graphs -->
    <div class="col-12 col-md-6 mining-col">

        <div id="mining_results"></div>

        <?php if ($algo !== 'all'): ?>

        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Estimate (<?= htmlspecialchars($algo) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap">
                    <div id="graph_results_price" style="height:<?= $height ?>"></div>
                </div>
            </div>
        </div>

        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Hashrate (<?= htmlspecialchars($algo) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap">
                    <div id="pool_hashrate_results" style="height:<?= $height ?>"></div>
                </div>
            </div>
        </div>

        <?php endif ?>

    </div><!-- /col left -->

    <!-- RIGHT: pool status + recent blocks -->
    <div class="col-12 col-md-6 mining-col" style="overflow-x:auto;">
        <div id="pool_current_results"></div>
        <div id="found_results"></div>
    </div>

</div><!-- /row -->

<script>
var global_algo  = '<?= addslashes($algo) ?>';
var querystring  = global_algo ? '?algo=' + global_algo : '';

/* Store plot references so we can replot on window resize */
var _plotPrice    = null;
var _plotHashrate = null;

function select_algo(algo)
{
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + algo + '&r=<?= $homeUrl ?>site/mining';
}

function page_refresh()
{
    pool_current_refresh();
    mining_refresh();
    found_refresh();

    if (global_algo !== 'all') {
        pool_hashrate_refresh();
        main_refresh_price();
    }
}

/* ── pool current ─────────────────────────────────────────────── */
function pool_current_refresh()
{
    $.get('<?= $homeUrl ?>site/current_results' + querystring, '', function(data) {
        $('#pool_current_results').html(data);
    });
}

/* ── mining algo list ─────────────────────────────────────────── */
function mining_refresh()
{
    $.get('<?= $homeUrl ?>site/mining_results' + querystring, '', function(data) {
        $('#mining_results').html(data);
    });
}

/* ── found blocks ─────────────────────────────────────────────── */
function found_refresh()
{
    $.get('<?= $homeUrl ?>site/found_results' + querystring, '', function(data) {
        $('#found_results').html(data);
    });
}

/* ── 24h estimate graph ───────────────────────────────────────── */
function main_refresh_price()
{
    $.get('<?= $homeUrl ?>site/graph_price_results' + querystring, '', function(data) {
        $('#graph_results_price').empty();
        try {
            _plotPrice = $.jqplot('graph_results_price', $.parseJSON(data), {
                title: '<b>Estimate (mBTC/<?= $algoUnit ?>/day)</b>',
                axes: {
                    xaxis: {
                        tickInterval: 7200,
                        renderer: $.jqplot.DateAxisRenderer,
                        tickOptions: { formatString: '<font size=1>%#Hh</font>' }
                    },
                    yaxis: {
                        min: 0,
                        tickOptions: { formatString: '<font size=1>%#.3f &nbsp;</font>' }
                    }
                },
                seriesDefaults: { markerOptions: { style: 'none' } },
                grid: { borderWidth: 1, shadowWidth: 0, shadowDepth: 0, background: '#ffffff' }
            });
        } catch(e) { /* data not ready yet */ }
    });
}

/* ── 24h hashrate graph ───────────────────────────────────────── */
function pool_hashrate_refresh()
{
    $.get('<?= $homeUrl ?>site/graph_hashrate_results' + querystring, '', function(data) {
        $('#pool_hashrate_results').empty();
        try {
            _plotHashrate = $.jqplot('pool_hashrate_results', $.parseJSON(data), {
                title: '<b>Pool Hashrate (<?= $algoUnit ?>/s)</b>',
                axes: {
                    xaxis: {
                        tickInterval: 7200,
                        renderer: $.jqplot.DateAxisRenderer,
                        tickOptions: { formatString: '<font size=1>%#Hh</font>' }
                    },
                    yaxis: {
                        min: 0,
                        tickOptions: { formatString: '<font size=1>%#.3f &nbsp;</font>' }
                    }
                },
                seriesDefaults: { markerOptions: { style: 'none' } },
                grid: { borderWidth: 1, shadowWidth: 0, shadowDepth: 0, background: '#ffffff' },
                highlighter: { show: true }
            });
        } catch(e) { /* data not ready yet */ }
    });
}

/* Replot on window resize so graphs fill their column correctly */
$(window).on('resize', function() {
    if (_plotPrice)    { try { _plotPrice.replot();    } catch(e) {} }
    if (_plotHashrate) { try { _plotHashrate.replot(); } catch(e) {} }
});
</script>
