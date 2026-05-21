<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Coins;
use app\models\Accounts;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$homeUrl = Yii::$app->homeUrl;

// ── Recent wallets cookie ────────────────────────────────────────────────────
$recents    = [];
$rawRecents = isset($_COOKIE['wallets']) ? explode('|', $_COOKIE['wallets']) : [];
foreach ($rawRecents as $addr) {
    if ($addr !== '') {
        $recents[$addr] = $addr;
    }
}

// ── Sanitise address (A–Za–z0–9 only, max 52 chars) ─────────────────────────
$address = trim((string) Yii::$app->request->get('address', ''));
if ($address !== '' && preg_match('/[^A-Za-z0-9]/', $address)) {
    die; // reject any injection attempt
}
$address = substr($address, 0, 52);

// ── Drop a cookie entry ──────────────────────────────────────────────────────
$dropAddress = Yii::$app->request->get('drop', '');
if ($dropAddress !== '' && isset($recents[$dropAddress])) {
    unset($recents[$dropAddress]);
    if (Yii::$app->user->identity !== null) {
        setcookie('wallets', implode('|', $recents), time() + 86400 * 30, '/');
    }
}

// ── Look up the user ─────────────────────────────────────────────────────────
$user = null;
if ($address !== '') {
    $user = Accounts::find()->where(['username' => $address])->one();
}

if ($user !== null) {
    Yii::$app->session->set('yaamp-wallet', $user->username);
    $recents[$user->username] = $user->username;

    // Update favicon to the user's coin icon
    $coin = Coins::findOne((int) $user->coinid);
    if ($coin) {
        $faviconUrl = Html::encode($coin->image);
        $this->registerJs(
            "$(function() {
                \$('#favicon').remove();
                \$('head').append('<link href=\"{$faviconUrl}\" id=\"favicon\" rel=\"shortcut icon\">');
            });",
            \yii\web\View::POS_READY,
            'favicon-handler'
        );
    }

    if (empty($user->hostaddr) && Yii::$app->user->identity === null) {
        $user->hostaddr = $_SERVER['REMOTE_ADDR'];
        $user->save();
    }
}

$username = $user ? $user->username : '';
if (Yii::$app->user->identity !== null) {
    setcookie('wallets', implode('|', $recents), time() + 86400 * 30, '/');
}

// Safe JS strings (address is already restricted to [A-Za-z0-9] so json_encode is belt+suspenders)
$jsUsername = json_encode($username);
$jsAddress  = json_encode($address);
$jsHomeUrl  = json_encode($homeUrl);
?>

<style>
/*
 * Two-column layout — Bootstrap row/col replaces the old <table> so that
 * column widths are firm when jqplot measures its containers.
 */
.wallet-col        { min-width: 0; }
.graph-wrap        { overflow: hidden; width: 100%; }
.graph-wrap canvas { max-width: 100% !important; }
.jqplot-target     { overflow: hidden !important; }
.main-left-box     { margin-bottom: 1rem; }

/* AJAX placeholder: reserve vertical space while content loads */
.ajax-placeholder  { min-height: 120px; }
</style>

<!-- "Auto refresh paused" banner -->
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<!-- Two-column layout -->
<div class="row gx-4 mt-2">

    <!-- LEFT: wallet stats, earnings graph, hashrate graphs, miners, found blocks, wallet search -->
    <div class="col-12 col-md-6 wallet-col">

        <?php if ($user): ?>
        <div id="main_wallet_results" class="ajax-placeholder"></div>

        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Balance: <?= Html::encode($username) ?></div>
            <div class="main-left-inner">
                <div class="graph-wrap">
                    <div id="graph_earnings_results" style="height:240px;"></div>
                </div>
                <div style="float:right; margin-top:4px;">
                    <span style="font-size:.8em;color:#4bb2c5;">Balance</span>&nbsp;
                    <span style="font-size:.8em;color:#eaa228;">Pending</span>
                </div>
                <div style="clear:both;"></div>
            </div>
        </div>

        <div id="main_graphs_results"  class="ajax-placeholder"></div>
        <div id="main_miners_results"  class="ajax-placeholder"></div>
        <div id="main_found_results"   class="ajax-placeholder"></div>
        <?php endif ?>

        <!-- Wallet search + recent wallets -->
        <div class="main-left-box">
            <div class="main-left-title">Search Wallet:</div>
            <div class="main-left-inner">
                <form action="<?= Html::encode($homeUrl) ?>" method="get" style="padding:10px;">
                    <input type="text" name="address" class="main-text-input" placeholder="Wallet Address">
                    <input type="submit" value="Submit" class="main-submit-button"><br><br>

                    <table class="dataGrid2">
                    <?php foreach ($recents as $addr):
                        if (empty($addr)) continue;
                        $recentUser = Accounts::find()->where(['username' => $addr])->one();
                        if (!$recentUser) continue;
                        $recentCoin = Coins::findOne((int) $recentUser->coinid);

                        $rowStyle = ($recentUser->username === $username)
                            ? "background-color:#e0d3e8;"
                            : '';
                        $bal = Yii::$app->ConversionUtils->bitcoinvaluetoa($recentUser->balance);
                        $balDisplay = '';
                        if ($recentCoin) {
                            $balDisplay = $bal > 0 ? "{$bal} {$recentCoin->symbol}" : '';
                        } else {
                            $balDisplay = $bal > 0 ? "{$bal} BTC" : '';
                        }
                        $delIcon = ($address === $addr) ? '' :
                            '<img src="/images/base/delete.png" onclick="drop_cookie(this);" style="cursor:pointer;">';
                    ?>
                    <tr class="ssrow" style="<?= Html::encode($rowStyle) ?>">
                        <td width="24">
                            <?php if ($recentCoin): ?>
                                <img width="16" src="<?= Html::encode($recentCoin->image) ?>" alt="">
                            <?php endif ?>
                        </td>
                        <td>
                            <?= Html::a(Html::encode($addr),
                                $homeUrl . '?address=' . urlencode($addr),
                                ['class' => 'address', 'style' => 'font-family:monospace;font-size:1.1em;']
                            ) ?>
                        </td>
                        <td align="right"><?= Html::encode($balDisplay) ?></td>
                        <td style="width:16px;max-width:16px;"><?= $delIcon ?></td>
                    </tr>
                    <?php endforeach ?>
                    </table>
                </form>
            </div>
        </div>

    </div><!-- /col left -->

    <!-- RIGHT: pool current status + found earnings -->
    <div class="col-12 col-md-6 wallet-col" style="overflow-x:auto;">
        <div id="pool_current_results" class="ajax-placeholder"></div>
        <?php if ($user): ?>
        <div id="found_results"        class="ajax-placeholder"></div>
        <?php endif ?>
    </div>

</div><!-- /row -->

<script>
var _plotEarnings = null;
var _plotHashrate = {};

var _username = <?= $jsUsername ?>;
var _homeUrl  = <?= $jsHomeUrl ?>;
var _address  = <?= $jsAddress ?>;

function page_refresh()
{
    pool_current_refresh();
    found_refresh();

    if (_username !== '') {
        main_wallet_refresh();
        main_miners_refresh();
        main_graphs_refresh();
        main_title_refresh();
        main_found_refresh();
    }
}

function select_algo(algo)
{
    window.location.href = _homeUrl + 'site/algo?algo=' + algo + '&r=/site/mining';
}

/* ── wallet results ──────────────────────────────────────────────────────── */
function main_wallet_refresh() {
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username), '', function(data) {
        $('#main_wallet_results').html(data);
    });
}

function main_wallet_refresh_details() {
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username) + '&showdetails=1', '', function(data) {
        $('#main_wallet_results').html(data);
    });
}

function main_found_refresh() {
    $.get(_homeUrl + 'site/wallet_found_results?address=' + encodeURIComponent(_username), '', function(data) {
        $('#main_found_results').html(data);
    });
}

/* ── miners ──────────────────────────────────────────────────────────────── */
function main_miners_refresh() {
    $.get(_homeUrl + 'site/wallet_miners_results?address=' + encodeURIComponent(_username), '', function(data) {
        $('#main_miners_results').html(data);
    });
}

/* ── pool current ────────────────────────────────────────────────────────── */
function pool_current_refresh() {
    $.get(_homeUrl + 'site/current_results', '', function(data) {
        $('#pool_current_results').html(data);
    });
}

/* ── page title ──────────────────────────────────────────────────────────── */
function main_title_refresh() {
    $.get(_homeUrl + 'site/title_results?address=' + encodeURIComponent(_username), '', function(data) {
        document.title = data;
    });
}

/* ── user earnings table ─────────────────────────────────────────────────── */
function found_refresh() {
    $.get(_homeUrl + 'site/user_earning_results?address=' + encodeURIComponent(_username), '', function(data) {
        $('#found_results').html(data);
    });
}

/* ── per-algo hashrate graphs ────────────────────────────────────────────── */
var _lastGraphUpdate = 0;

function main_graphs_refresh() {
    var now = Date.now() / 1000;
    if (now < _lastGraphUpdate + 900) return;
    _lastGraphUpdate = now;

    $.get(_homeUrl + 'site/wallet_graphs_results?address=' + encodeURIComponent(_username), '', function(data) {
        $('#main_graphs_results').html(data);
        $('.graph_algo').each(function() {
            main_refresh_hashrate($(this).attr('id'));
        });
    });

    graph_earnings_refresh();
}

function main_refresh_hashrate(algo) {
    $.get(_homeUrl + 'site/graph_user_results?address=' + encodeURIComponent(_username) + '&algo=' + encodeURIComponent(algo), '', function(data) {
        graph_init_hashrate(data, algo);
    });
}

function graph_init_hashrate(data, algo) {
    $('#graph_results_' + algo).empty();
    try {
        var t = $.parseJSON(data);
        _plotHashrate[algo] = $.jqplot('graph_results_' + algo, t[0], {
            title: '<b>' + t[1] + '</b>',
            axes: {
                xaxis: { tickInterval: 7200, renderer: $.jqplot.DateAxisRenderer,
                         tickOptions: { formatString: '<font size=1>%#Hh</font>' } },
                yaxis: { min: 0, tickOptions: { formatString: '<font size=1>%#.3f &nbsp;</font>' } }
            },
            seriesDefaults: { markerOptions: { style: 'none' } },
            grid: { borderWidth: 1, shadowWidth: 0, shadowDepth: 0, background: '#ffffff' },
            highlighter: { show: true }
        });
    } catch(e) {}
}

/* ── 24h earnings/balance graph ──────────────────────────────────────────── */
function graph_earnings_refresh() {
    $.get(_homeUrl + 'site/graph_earnings_results?address=' + encodeURIComponent(_username), '', function(data) {
        graph_earnings_init(data);
    });
}

function graph_earnings_init(data) {
    $('#graph_earnings_results').empty();
    try {
        var t = $.parseJSON(data);
        _plotEarnings = $.jqplot('graph_earnings_results', t, {
            stackSeries: true,
            axes: {
                xaxis: { tickInterval: 7200, renderer: $.jqplot.DateAxisRenderer,
                         tickOptions: { formatString: '<font size=1>%#Hh</font>' } },
                yaxis: { min: 0, tickOptions: { formatString: '<font size=1>%#.8f &nbsp;</font>' } }
            },
            seriesDefaults: { markerOptions: { style: 'none' } },
            grid: { borderWidth: 1, shadowWidth: 0, shadowDepth: 0, background: '#ffffff' }
        });
    } catch(e) {}
}

/* ── cookie management ───────────────────────────────────────────────────── */
function main_wallet_tx() {
    window.open(_homeUrl + 'site/tx?address=' + encodeURIComponent(_username),
        'yaamp_tx', 'width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no');
}

function drop_cookie(el) {
    var addr = $(el).closest('tr').find('td a.address').text();
    window.location.href = '?address=' + encodeURIComponent(_address) + '&drop=' + encodeURIComponent(addr);
}

/* ── resize: replot all active charts ────────────────────────────────────── */
$(window).on('resize', function() {
    if (_plotEarnings) { try { _plotEarnings.replot(); } catch(e) {} }
    $.each(_plotHashrate, function(algo, plot) {
        if (plot) { try { plot.replot(); } catch(e) {} }
    });
});
</script>
