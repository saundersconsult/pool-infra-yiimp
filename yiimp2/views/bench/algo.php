<?php
/**
 * @var yii\web\View $this
 * @var string       $algo
 * @var array        $algos    [algo => count]
 * @var array        $rows     aggregated per-chip rows
 * @var float        $algo24E  mBTC per kHs (24h average)
 * @var float        $btcusd   BTC/USD rate
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Algo Benchmarks';
$cu = Yii::$app->ConversionUtils;

$options = '';
foreach ($algos as $a => $count) {
    $sel      = ($a === $algo) ? ' selected' : '';
    $options .= "<option value=\"{$a}\"{$sel}>{$a}</option>";
}
?>

<div style="text-align:right; margin-bottom:2px;">
  <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>

<style>
tr.ssrow.filtered { display:none; }
td.red { color:darkred; }
.page .footer { width:auto; }
</style>

<div style="text-align:right; margin-top:-22px; margin-right:145px;">
  Select Algo: <select class="filter" id="algo_select"><?= $options ?></select>&nbsp;
</div>

<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
  Overall <?= Html::encode($algo) ?> performance
</p>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    textExtraction: {
        2: function(node,table,n){ return \$(node).attr('data'); },
        4: function(node,table,n){ return \$(node).attr('data'); }
    },
    widgetOptions: {
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}");
?>
<thead>
<tr>
  <th width="50">Type</th>
  <th width="300">Chip</th>
  <th width="100">Hashrate</th>
  <th width="80">Power</th>
  <th width="100">H/W</th>
  <th width="100" title="mBTC/day">Cost*</th>
  <th width="100" title="mBTC/day">Reward</th>
  <th width="100" title="mBTC/day">Profit**</th>
  <th width="100">Int</th>
  <th width="100">Freq</th>
</tr>
</thead><tbody>

<?php foreach ($rows as $row):
    if (($row['chip'] ?? '') === 'Virtual') continue;

    $factor = ($row['chip'] === '750' || $row['chip'] === '750 Ti' || $row['chip'] === 'Quadro K620') ? 2.0 : 1.0;
    $power  = (float)($row['power'] ?? 0) * $factor;
    $khps   = (float)($row['khps'] ?? 0);

    $cost   = BenchService::powercostMbtc($power, $btcusd);
    $reward = $khps * $algo24E;
    $profit = $reward - $cost;
    $ppw    = $power > 0 ? $khps / $power : 0.0;

    $chip     = Html::encode($row['chip'] ?? '');
    $chipLink = Html::a($chip, ['/bench', 'chip' => $row['idchip'], 'algo' => $algo]);

    if ($algo === 'equihash') {
        $hashStr = round(1000 * $khps, 1) . '&nbsp;Sol/s';
        $ppwStr  = $power > 0 ? $cu->Itoa2(1000 * round($ppw, 5), 2) . '&nbsp;Sol/W' : '-';
    } else {
        $hashStr = $cu->Itoa2(1000 * round($khps, 3), 3) . 'H';
        $ppwStr  = $power > 0 ? $cu->Itoa2(1000 * round($ppw, 3), 3) . 'H' : '-';
    }

    $powerStr = $power > 0
        ? round($power) . ($factor > 1.0 ? '&nbsp;W*' : '&nbsp;W')
        : '-';
    $powerTitle = $factor > 1.0
        ? "Note: The {$row['chip']} power value seems to be for the chip only, x2 factor applied!"
        : '';
?>
<tr class="ssrow">
  <td><?= strtoupper(Html::encode($row['type'] ?? '')) ?></td>
  <td><?= $chipLink ?></td>
  <td data="<?= $khps ?>"><?= $hashStr ?></td>
  <td title="<?= Html::encode($powerTitle) ?>"><?= $powerStr ?></td>
  <td data="<?= $ppw ?>"><?= $ppwStr ?></td>
  <td><?= $power > 0 ? $cu->mbitcoinvaluetoa($cost) : '-' ?></td>
  <td><?= $cu->mbitcoinvaluetoa($reward) ?></td>
  <td class="<?= $profit < 0 ? 'red' : '' ?>"><?= $power > 0 ? $cu->mbitcoinvaluetoa($profit) : '-' ?></td>
  <td><?= ($row['intensity'] ?? 0) > 0 ? round($row['intensity']) : '-' ?></td>
  <td><?= ($row['freq'] ?? 0) > 0 ? round($row['freq']) : '-' ?></td>
</tr>
<?php endforeach; ?>

</tbody></table><br>

<p>
  * Device power cost per day based on <?= defined('YIIMP_KWH_USD_PRICE') ? YIIMP_KWH_USD_PRICE : 0.25 ?> USD per kWh<br>
  ** Reward and profit are based on the average estimates from the last 24 hours<br><br>
</p>

<?= Html::a('Show current devices in the database', ['/bench/devices']) ?><br>
<?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br><br>

<script>
$('select.filter').on('change', function () {
    window.location.href = '/bench/algo?algo=' + $('#algo_select').val();
});
</script>
