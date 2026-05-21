<?php

/** @var yii\web\View $this */
/** @var array[]     $jobs           One entry per job from JobsController::buildJobData() */
/** @var bool        $tableExists    False when the queue table hasn't been created yet */
/** @var bool        $balancesLocked True when PaymentsJob holds the payment lock */

use yii\helpers\Html;

$this->title = 'Queue Jobs';

/* ── helpers ───────────────────────────────────────────────────────────────── */

function fmtSecs(?int $s): string {
    if ($s === null) return '—';
    if ($s <  0)     return '<span class="text-danger">overdue</span>';
    if ($s <  60)    return "{$s}s";
    if ($s < 3600)   return round($s / 60) . 'm';
    return round($s / 3600, 1) . 'h';
}

function fmtAgo(?int $ts): string {
    if (!$ts) return '—';
    $s = time() - $ts;
    if ($s <  60)    return "{$s}s ago";
    if ($s < 3600)   return round($s / 60) . 'm ago';
    if ($s < 86400)  return round($s / 3600, 1) . 'h ago';
    return round($s / 86400, 1) . 'd ago';
}

$badgeCfg = [
    'running'    => ['bg-primary',   'running'],
    'ready'      => ['bg-success',   'ready'],
    'pending'    => ['bg-success',   'pending'],
    'late'       => ['bg-warning text-dark', 'late'],
    'paused'     => ['bg-secondary', 'paused'],
    'not_seeded' => ['bg-danger',    'not seeded'],
];

// Group jobs by domain
$grouped = [];
foreach ($jobs as $j) {
    $grouped[$j['domain']][] = $j;
}
$domainLabels = [
    'blocks'   => 'Block Pipeline',
    'earnings' => 'Earnings & Payments',
    'coins'    => 'Coin Management',
    'stats'    => 'Statistics',
    'market'   => 'Market & Exchange',
    'renting'  => 'Renting (pool rents out)',
    'nicehash' => 'NiceHash (pool buys in)',
    'system'   => 'System & Housekeeping',
];

$countRunning  = count(array_filter($jobs, fn($j) => $j['status'] === 'running'));
$countPaused   = count(array_filter($jobs, fn($j) => $j['paused']));
$countMissing  = count(array_filter($jobs, fn($j) => $j['status'] === 'not_seeded'));
?>

<style>
.jobs-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.domain-header { font-size: .75rem; font-weight: 700; letter-spacing: .06em;
                 text-transform: uppercase; color: #6c757d; padding: .35rem .5rem;
                 background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
.jobs-table td, .jobs-table th { vertical-align: middle; font-size: .875rem; }
.jobs-table .actions form { display: inline; margin-right: 2px; }
#refresh-bar { height: 3px; background: #0d6efd; transition: width 1s linear; }
</style>

<!-- refresh progress bar -->
<div id="refresh-bar" style="width:100%; margin-bottom:.5rem;"></div>

<?php if (!$tableExists): ?>
<div class="alert alert-danger">
    The <code>queue</code> table does not exist yet.
    Run <code>php yii migrate --migrationPath=@yii/queue/db/migrations</code> then
    <code>php yii queue/seed</code> to initialise the queue.
</div>
<?php else: ?>

<!-- header bar -->
<div class="jobs-header">
    <div>
        <h4 class="mb-0">Queue Jobs
            <?php if ($balancesLocked): ?>
                <span class="badge bg-warning text-dark ms-2" title="PaymentsJob is running">⚠ balances locked</span>
            <?php endif ?>
        </h4>
        <small class="text-muted">
            <?= $countRunning ?> running &nbsp;·&nbsp;
            <?= count($jobs) - $countPaused - $countMissing ?> active &nbsp;·&nbsp;
            <?php if ($countPaused): ?><span class="text-secondary"><?= $countPaused ?> paused</span> &nbsp;·&nbsp;<?php endif ?>
            <?php if ($countMissing): ?><span class="text-danger"><?= $countMissing ?> not seeded</span><?php endif ?>
        </small>
    </div>
    <div>
        <?= Html::beginForm(['jobs/seed-all']) ?>
        <?= Html::submitButton('Seed missing', ['class' => 'btn btn-sm btn-outline-primary', 'title' => 'Push any jobs that have no queue row']) ?>
        <?= Html::endForm() ?>
    </div>
</div>

<?= \app\widgets\Alert::widget() ?>

<!-- per-domain tables -->
<?php foreach ($grouped as $domain => $domainJobs):
    $label = $domainLabels[$domain] ?? ucfirst($domain);
?>
<table class="table table-sm table-bordered jobs-table mb-3">
    <thead>
        <tr><th colspan="7" class="domain-header"><?= Html::encode($label) ?></th></tr>
        <tr class="table-light">
            <th>Job</th>
            <th>Every</th>
            <th>Status</th>
            <th>Runs in</th>
            <th>Last run</th>
            <th class="actions">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($domainJobs as $j):
        [$badgeClass, $badgeText] = $badgeCfg[$j['status']] ?? ['bg-light text-dark', $j['status']];
        $interval = $j['interval'] < 60
            ? $j['interval'] . 's'
            : ($j['interval'] < 3600 ? ($j['interval'] / 60) . 'm' : ($j['interval'] / 3600) . 'h');
    ?>
    <tr>
        <td><code><?= Html::encode($j['name']) ?></code></td>
        <td class="text-muted"><?= $interval ?></td>
        <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
        <td><?= fmtSecs($j['secsLeft']) ?></td>
        <td class="text-muted"><?= fmtAgo($j['lastRun']) ?></td>
        <td class="actions">
            <?php if (!$j['paused']): ?>
                <!-- Pause -->
                <?= Html::beginForm(['jobs/pause', 'job' => $j['name']]) ?>
                <?= Html::submitButton('Pause', [
                    'class' => 'btn btn-xs btn-outline-secondary',
                    'title' => 'Stop rescheduling after current run',
                    'style' => 'font-size:.75rem; padding:1px 6px;',
                ]) ?>
                <?= Html::endForm() ?>
            <?php else: ?>
                <!-- Resume -->
                <?= Html::beginForm(['jobs/resume', 'job' => $j['name']]) ?>
                <?= Html::submitButton('Resume', [
                    'class' => 'btn btn-xs btn-outline-success',
                    'title' => 'Clear pause flag and re-push',
                    'style' => 'font-size:.75rem; padding:1px 6px;',
                ]) ?>
                <?= Html::endForm() ?>
            <?php endif ?>

            <?php if (!$j['running']): ?>
                <!-- Run now -->
                <?= Html::beginForm(['jobs/run-now', 'job' => $j['name']]) ?>
                <?= Html::submitButton('▶ Now', [
                    'class' => 'btn btn-xs btn-outline-primary',
                    'title' => 'Cancel delay and run immediately',
                    'style' => 'font-size:.75rem; padding:1px 6px;',
                    'onclick' => 'return confirm("Run ' . Html::encode($j['name']) . ' immediately?")',
                ]) ?>
                <?= Html::endForm() ?>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endforeach ?>

<?php endif // tableExists ?>

<script>
// Auto-refresh every 15 s with a visible countdown bar
(function () {
    const total   = 15;
    const bar     = document.getElementById('refresh-bar');
    let   elapsed = 0;

    function tick() {
        elapsed++;
        const pct = (elapsed / total) * 100;
        bar.style.width = pct + '%';
        if (elapsed >= total) {
            location.reload();
        } else {
            setTimeout(tick, 1000);
        }
    }

    setTimeout(tick, 1000);
})();
</script>
