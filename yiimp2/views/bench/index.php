<?php
/**
 * @var yii\web\View   $this
 * @var string         $algo
 * @var string         $vid
 * @var int            $idchip
 * @var array          $algos     [algo => count]
 * @var array          $chips     [idchip => name]
 * @var array          $rows
 * @var array|null     $avg
 * @var bool           $isAdmin
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Benchmarks';
$cu  = Yii::$app->ConversionUtils;

// Algo dropdown
$algoOptions = '<option value="all">Show all</option>';
foreach ($algos as $a => $count) {
    $sel = ($a === $algo) ? ' selected' : '';
    $algoOptions .= "<option value=\"{$a}\"{$sel}>{$a}</option>";
}

// Chip dropdown
$chipOptions = '<option value="0">Show all</option>';
foreach ($chips as $id => $name) {
    $sel = ($id == $idchip) ? ' selected' : '';
    $chipOptions .= "<option value=\"{$id}\"{$sel}>{$name}</option>";
}
?>

<div style="text-align:right; margin-bottom:2px;">
  <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>

<style>
tr.ssrow.filtered { display:none; }
td.limited { color:silver; }
td.real    { color:black; }
.page .footer { width:auto; }
</style>

<div style="text-align:right; margin-top:-22px; margin-right:145px;">
  Select Algo: <select class="filter" id="algo_select"><?= $algoOptions ?></select>&nbsp;
  Chip: <select class="filter" id="chip_select"><?= $chipOptions ?></select>&nbsp;
</div>

<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
<?php if ($algo === 'all'): ?>
  Last 100 results
<?php else: ?>
  Last 100 <?= Html::encode($algo) ?> results,
  <?= Html::a('show totals', ['/bench/algo', 'algo' => $algo]) ?>
<?php endif; ?>
</p>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    textExtraction: {
        1: function(node,table,n){ return \$(node).attr('data'); },
        5: function(node,table,n){ return \$(node).attr('data'); },
        6: function(node,table,n){ return \$(node).attr('data'); }
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
  <th>Algo</th>
  <th>Time</th>
  <th>Chip</th>
  <th>Device</th>
  <th>Vendor ID</th>
  <th>Arch</th>
  <th>Hashrate</th>
  <th title="Intensity (-i) for GPU or Threads (-t) for CPU">Int.</th>
  <th title="MHz">Freq</th>
  <th title="Watts">W</th>
  <th title="Efficiency">H/W</th>
  <th>Client</th>
  <th>OS</th>
  <th>Driver / Compiler</th>
  <?= $isAdmin ? '<th width="30" data-sorter="">Admin</th>' : '' ?>
</tr>
</thead><tbody>

<?php foreach ($rows as $row):
    if (($row['chip_name'] ?? $row['chip'] ?? '') === 'Virtual') continue;

    $hashrate = $cu->Itoa2(1000 * round($row['khps'], 3), 2) . 'H';
    if ($row['algo'] === 'equihash') {
        $hashrate = $cu->Itoa2(1000 * round($row['khps'], 5), 3) . '&nbsp;Sol/s';
    }
    $age = $cu->datetoa2($row['time']);

    $chip     = $row['chip_name'] ?? $row['chip'] ?? '';
    $chipLink = $row['idchip']
        ? Html::a(Html::encode($chip), ['/bench', 'chip' => $row['idchip']])
        : Html::encode($chip);

    $device   = Html::encode(BenchService::formatDevice($row));
    $arch     = $row['type'] === 'gpu'
        ? Html::encode(BenchService::formatCudaArch((string)($row['arch'] ?? '')))
        : Html::encode((string)($row['arch'] ?? ''));

    // Frequency
    $freqContent = $row['freq'] ?: '-';
    $freqTitle   = '';
    $freqClass   = '';
    if ((int)($row['realfreq'] ?? 0) > ($row['freq'] ?? 0) / 10) {
        $freqContent = $row['realfreq'];
        $freqClass   = 'real';
        $freqTitle   = "Base clock: {$row['freq']} MHz\nMem clock: {$row['realmemf']} MHz";
    } elseif ($row['memf'] ?? 0) {
        $freqTitle = "Mem Clock: {$row['memf']} MHz";
    }

    // Power
    $factor = ($row['chip'] === '750' || $row['chip'] === '750 Ti' || $row['chip'] === 'Quadro K620') ? 2.0 : 1.0;
    $power  = (float)($row['power'] ?? 0) * $factor;
    $powerContent = $power > 0 ? $power : '-';
    $powerTitle   = '';
    $powerClass   = '';
    if ($row['plimit'] ?? 0) { $powerTitle = "Power limit {$row['plimit']}W"; $powerClass = 'limited'; }

    $hpw = ($power > 0) ? $cu->Itoa2(1000 * round($row['khps'] / $power, 4), 3) : '-';

    // Intensity / throughput
    if ($row['type'] === 'cpu') {
        $intContent = $row['throughput'] ?? '';
        $intTitle   = '';
    } elseif ($row['algo'] === 'neoscrypt') {
        $intContent = ($row['throughput'] ?? '') . '*';
        $intTitle   = 'neoscrypt intensity is different';
    } else {
        $intContent = $row['intensity'] ?: '-';
        $intTitle   = ($row['throughput'] ?? '') . ' threads';
    }
?>
<tr class="ssrow">
  <td><?= Html::a(Html::encode($row['algo']), ['/bench', 'algo' => $row['algo']]) ?></td>
  <td data="<?= (int)$row['time'] ?>"><?= $age ?></td>
  <td><?= $chipLink ?></td>
  <td><?= $device ?></td>
  <td><?= Html::a(Html::encode($row['vendorid'] ?? ''), ['/bench', 'vid' => $row['vendorid'] ?? '']) ?></td>
  <td><?= $arch ?></td>
  <td data="<?= (float)$row['khps'] ?>"><?= $hashrate ?></td>
  <td data="<?= $row['throughput'] ?? 0 ?>" title="<?= Html::encode($intTitle) ?>"><?= Html::encode((string)$intContent) ?></td>
  <td class="<?= $freqClass ?>" title="<?= Html::encode($freqTitle) ?>"><?= Html::encode((string)$freqContent) ?></td>
  <td class="<?= $powerClass ?>" title="<?= Html::encode($powerTitle) ?>"><?= Html::encode((string)$powerContent) ?></td>
  <td><?= $hpw ?></td>
  <td><?= Html::encode($row['client'] ?? '') ?></td>
  <td><?= Html::encode($row['os'] ?? '') ?></td>
  <td><?= Html::encode($row['driver'] ?? '') ?></td>
  <?php if ($isAdmin): ?>
  <td><?= Html::a('del', ['/admin/benchdel', 'id' => $row['id']], ['style' => 'color:darkred']) ?></td>
  <?php endif; ?>
</tr>
<?php endforeach; ?>

</tbody>
<?php if ($avg && (int)($avg['records'] ?? 0) > 0):
    $factor = 1.0;
    $avgPower = (float)($avg['power'] ?? 0) * $factor;
    $avgHpw   = $avgPower > 0 ? $cu->Itoa2(1000 * round((float)$avg['khps'] / $avgPower, 3), 2) : '';
?>
<tfoot>
<tr class="ssfoot">
  <th><?= Html::a(Html::encode($algo), ['/bench', 'algo' => $algo]) ?></th>
  <th>&nbsp;</th>
  <th colspan="4">Average (<?= (int)$avg['records'] ?> records)</th>
  <th><?= $cu->Itoa2(1000 * round($avg['khps'], 3), 2) ?>H</th>
  <th><?= $avg['intensity'] ? round($avg['intensity'], 1) : '' ?></th>
  <th><?= $avg['freq'] ? round($avg['freq']) : '' ?></th>
  <th><?= $avgPower > 0 ? round($avgPower) : '' ?></th>
  <th><?= $avgHpw ?></th>
  <th colspan="<?= $isAdmin ? 4 : 3 ?>">&nbsp;</th>
</tr>
</tfoot>
<?php endif; ?>
</table><br>

<p>
  <?= Html::a('Show current devices in the database', ['/bench/devices']) ?><br>
  <?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br>
</p>

<script>
$('select.filter').on('change', function () {
    var algo = $('#algo_select').val();
    var chip = $('#chip_select').val();
    window.location.href = '/bench?algo=' + algo + '&chip=' + chip;
});
</script>
