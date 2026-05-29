<?php
/**
 * @var yii\web\View $this
 * @var array        $devices      rows: device, type, chip, idchip, vendorid
 * @var string[]     $algos        distinct algo names (last 30 days, max 20)
 * @var array        $gpuCoverage  [vendorid => [algo, ...]]
 * @var array        $cpuCoverage  [device  => [algo, ...]]
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Devices';
?>

<div style="text-align:right; margin-bottom:2px;">
  <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>

<style>
tr.ssrow.filtered { display:none; }
td.tick { font-weight:bolder; }
span.generic { color:gray; }
.page .footer { width:auto; }
</style>

<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
  Devices in database
</p>

<?php
$algoCols = '';
foreach ($algos as $a) {
    $algoCols .= '<th>' . Html::encode($a) . '</th>';
}

Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
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
  <th width="70">Chip</th>
  <th width="220">Device</th>
  <th width="70">Vendor ID</th>
  <?= $algoCols ?>
</tr>
</thead><tbody>

<?php foreach ($devices as $row):
    if (($row['chip'] ?? '') === 'Virtual') continue;

    $vendorid = $row['vendorid'] ?? '';
    $chip     = $row['chip'] ?? '';
    if (empty($chip)) $chip = BenchService::formatCPUPublic($row);

    $chipCell = $row['idchip']
        ? Html::a(Html::encode($chip), ['/bench', 'chip' => $row['idchip'], 'algo' => 'all'])
        : Html::encode($chip);

    $deviceLabel = Html::encode(BenchService::formatDevice($row));

    if (str_starts_with($vendorid, '10de')) {
        $vidCell = '<span class="generic" title="nVidia product id">' . Html::encode($vendorid) . '</span>';
    } else {
        $vidCell = $vendorid
            ? Html::a(Html::encode($vendorid), ['/bench', 'vid' => $vendorid])
            : '';
    }

    $covered = $vendorid
        ? ($gpuCoverage[$vendorid] ?? [])
        : ($cpuCoverage[$row['device']] ?? []);
?>
<tr class="ssrow">
  <td><?= $chipCell ?></td>
  <td><?= $deviceLabel ?></td>
  <td><?= $vidCell ?></td>
  <?php foreach ($algos as $a):
      $tick = in_array($a, $covered)
          ? Html::a('✓', array_filter(['/bench', 'algo' => $a, 'chip' => $row['idchip'] ?: null]))
          : '&nbsp;';
  ?>
  <td class="tick"><?= $tick ?></td>
  <?php endforeach; ?>
</tr>
<?php endforeach; ?>

</tbody></table><br>

<?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br><br>
