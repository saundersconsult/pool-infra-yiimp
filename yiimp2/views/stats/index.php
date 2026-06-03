<?php

/** @var yii\web\View  $this     */
/** @var string        $algo     */
/** @var string        $algoUnit */
/** @var array         $algos    */  // ['algoname' => coinCount, ...]
/** @var array         $stats1   */  // ['hashrate', 'total', 'btcPerMhd']
/** @var array         $stats2   */
/** @var array         $stats3   */

use yii\helpers\Html;

$height = '240px';

$options = '';
foreach ($algos as $a => $count) {
    $sel      = ($a === $algo) ? ' selected' : '';
    $options .= '<option value="' . Html::encode($a) . '"' . $sel . '>' . Html::encode($a) . '</option>';
}
?>

<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div align="right" style="margin-bottom:6px;">
    Select Algo: <select id="algo_select"><?= $options ?></select>&nbsp;
</div>

<div class="row gx-4">

    <!-- ── 48h column ─────────────────────────────────────────────────── -->
    <div class="col-12 col-md-4">
        <div class="main-left-box">
            <div class="main-left-title">Last 48 Hours</div>
            <div class="main-left-inner">
                <ul>
                    <li>Average Hashrate: <b><?= Html::encode($stats1['hashrate']) ?>h/s</b></li>
                    <li>BTC Value: <b><?= Html::encode($stats1['total']) ?></b></li>
                    <li>BTC/<?= Html::encode($algoUnit) ?>/d: <b><?= Html::encode($stats1['btcPerMhd']) ?></b></li>
                </ul>
                <div id="graph_results_1" style="height:<?= $height ?>;"></div>
                <div id="graph_results_2" style="height:<?= $height ?>;margin-top:1rem;"></div>
                <div id="graph_results_3" style="height:<?= $height ?>;margin-top:1rem;"></div>
            </div>
        </div>
    </div>

    <!-- ── 7-day column ───────────────────────────────────────────────── -->
    <div class="col-12 col-md-4">
        <div class="main-left-box">
            <div class="main-left-title">Last 7 Days</div>
            <div class="main-left-inner">
                <ul>
                    <li>Average Hashrate: <b><?= Html::encode($stats2['hashrate']) ?>h/s</b></li>
                    <li>BTC Value: <b><?= Html::encode($stats2['total']) ?></b></li>
                    <li>BTC/<?= Html::encode($algoUnit) ?>/d: <b><?= Html::encode($stats2['btcPerMhd']) ?></b></li>
                </ul>
                <div id="graph_results_4" style="height:<?= $height ?>;"></div>
                <div id="graph_results_5" style="height:<?= $height ?>;margin-top:1rem;"></div>
                <div id="graph_results_6" style="height:<?= $height ?>;margin-top:1rem;"></div>
            </div>
        </div>
    </div>

    <!-- ── 30-day column ──────────────────────────────────────────────── -->
    <div class="col-12 col-md-4">
        <div class="main-left-box">
            <div class="main-left-title">Last 30 Days</div>
            <div class="main-left-inner">
                <ul>
                    <li>Average Hashrate: <b><?= Html::encode($stats3['hashrate']) ?>h/s</b></li>
                    <li>BTC Value: <b><?= Html::encode($stats3['total']) ?></b></li>
                    <li>BTC/<?= Html::encode($algoUnit) ?>/d: <b><?= Html::encode($stats3['btcPerMhd']) ?></b></li>
                </ul>
                <div id="graph_results_7" style="height:<?= $height ?>;"></div>
                <div id="graph_results_8" style="height:<?= $height ?>;margin-top:1rem;"></div>
                <div id="graph_results_9" style="height:<?= $height ?>;margin-top:1rem;"></div>
            </div>
        </div>
    </div>

</div><!-- /.row -->

<script>
$('#algo_select').change(function () {
    window.location.href = '/site/algo?algo=' + encodeURIComponent($('#algo_select').val()) + '&r=/stats';
});

function page_refresh() {
    for (var i = 1; i <= 9; i++) {
        (function (n) {
            $.get('/stats/graph_results_' + n, '', function (data) {
                window['graph_init_' + n](data);
            });
        })(i);
    }
}

<?php
$au = Html::encode($algoUnit);
$graphInits = [
    1 => "yiimpChart('graph_results_1', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    2 => "yiimpChart('graph_results_2', JSON.parse(data), { type: 'bar', title: 'BTC/Day', btcDecimals: true });",
    3 => "yiimpChart('graph_results_3', JSON.parse(data), { type: 'bar', title: 'BTC/{$au}/d', btcDecimals: true });",
    4 => "yiimpChart('graph_results_4', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    5 => "yiimpChart('graph_results_5', JSON.parse(data), { type: 'bar', title: 'BTC/Day', btcDecimals: true });",
    6 => "yiimpChart('graph_results_6', JSON.parse(data), { type: 'bar', title: 'BTC/{$au}/d', btcDecimals: true });",
    7 => "yiimpChart('graph_results_7', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    8 => "yiimpChart('graph_results_8', JSON.parse(data), { title: 'BTC/Day', btcDecimals: true });",
    9 => "yiimpChart('graph_results_9', JSON.parse(data), { title: 'BTC/{$au}/d', btcDecimals: true });",
];
foreach ($graphInits as $n => $body):
    echo "function graph_init_{$n}(data) { {$body} }\n";
endforeach;
?>
</script>
