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
.mining-col        { min-width: 0; }
.graph-wrap        { overflow: hidden; width: 100%; }
.graph-wrap canvas { max-width: 100% !important; }
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
var global_algo = '<?= addslashes($algo) ?>';
var querystring = global_algo ? '?algo=' + global_algo : '';

function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + algo + '&r=<?= $homeUrl ?>site/mining';
}

function page_refresh() {
    pool_current_refresh();
    mining_refresh();
    found_refresh();
    if (global_algo !== 'all') {
        main_refresh_price();
        pool_hashrate_refresh();
    }
}

function pool_current_refresh() {
    $.get('<?= $homeUrl ?>site/current_results' + querystring, '', function(d) { $('#pool_current_results').html(d); });
}
function mining_refresh() {
    $.get('<?= $homeUrl ?>site/mining_results' + querystring, '', function(d) { $('#mining_results').html(d); });
}
function found_refresh() {
    $.get('<?= $homeUrl ?>site/found_results' + querystring, '', function(d) { $('#found_results').html(d); });
}

function main_refresh_price() {
    $.get('<?= $homeUrl ?>site/graph_price_results' + querystring, '', function(data) {
        try {
            yiimpChart('graph_results_price', JSON.parse(data), {
                title:   'Estimate (mBTC/<?= $algoUnit ?>/day)',
                labels:  ['Raw', 'Smoothed'],
                decimals: 5
            });
        } catch(e) {}
    });
}

function pool_hashrate_refresh() {
    $.get('<?= $homeUrl ?>site/graph_hashrate_results' + querystring, '', function(data) {
        try {
            yiimpChart('pool_hashrate_results', JSON.parse(data), {
                title:  'Pool Hashrate (<?= $algoUnit ?>/s)',
                labels: ['Raw', 'Smoothed']
            });
        } catch(e) {}
    });
}
</script>
