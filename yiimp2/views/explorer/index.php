<?php

use yii\helpers\Html;
use app\components\rpc\WalletRPC;
use app\models\Coins;

$this->title = 'Block Explorer';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;

// ── Shared coin list + RPC data ───────────────────────────────────────────────
$list = Coins::find()->where(['enable' => 1, 'visible' => 1])->orderBy('name')->all();

$coinRows = [];
foreach ($list as $coin) {
    if ($coin->symbol === 'BTC') continue;
    if (!empty($coin->symbol2)) continue;

    $coin->version = $conv->formatWalletVersion($coin);

    $coin->network_hash = Yii::$app->cache->get("yiimp-nethashrate-{$coin->symbol}");
    if (!$coin->network_hash) {
        $remote = new WalletRPC($coin);
        $info   = $remote->error === null ? $remote->getmininginfo() : false;
        if (isset($info['networkhashps'])) {
            $nh = is_array($info['networkhashps'])
                ? ($info['networkhashps'][$coin->algo] ?? 0)
                : $info['networkhashps'];
            $coin->network_hash = $nh;
            Yii::$app->cache->set("yiimp-nethashrate-{$coin->symbol}", $nh, 60);
        } elseif (isset($info['netmhashps'])) {
            $nh = floatval($info['netmhashps']) * 1e6;
            $coin->network_hash = $nh;
            Yii::$app->cache->set("yiimp-nethashrate-{$coin->symbol}", $nh, 60);
        }
    }

    $difficulty  = $conv->Itoa2($coin->difficulty, 3);
    $diffnote    = in_array($coin->algo, ['equihash', 'quark']) ? '*' : '';
    $nethash     = $coin->network_hash
                   ? strtoupper($conv->Itoa2($coin->network_hash)) . 'H/s' : '';
    $cnxClass    = (intval($coin->connections) > 3) ? '' : 'low';
    $peersUrl    = "javascript:wallet_peers({$coin->id});";
    $link        = !empty($coin->link_bitcointalk) ? $coin->link_bitcointalk
                 : (!empty($coin->link_site) ? $coin->link_site : null);
    $linkLabel   = !empty($coin->link_bitcointalk) ? 'forum' : 'site';

    $coinRows[] = compact('coin', 'difficulty', 'diffnote', 'nethash',
                          'cnxClass', 'peersUrl', 'link', 'linkLabel');
}
?>
<script>
function wallet_peers(id) {
    window.open('/explorer/peers?id=' + id, 'peers',
        'width=400,height=600,location=no,menubar=no,resizable=yes,status=no,toolbar=no');
}
</script>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>a.low { color:red; font-weight:bold; }</style>
<br/>
<div class="main-left-box">
<div class="main-left-title">Block Explorer</div>
<div class="main-left-inner">
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid2',
    textExtraction: {
        6: function(node,table,n){ return \$(node).attr('data'); },
        8: function(node,table,n){ return \$(node).attr('data'); }
    }
}"); ?>
<thead><tr>
    <th width="30" data-sorter=""></th>
    <th>Name</th><th>Symbol</th><th>Algo</th><th>Version</th><th>Height</th>
    <th>Difficulty</th><th>Connections</th><th>Network Hash</th><th data-sorter=""></th>
</tr></thead><tbody>
<?php foreach ($coinRows as $r):
    $coin = $r['coin']; ?>
<tr class="ssrow">
    <td><img src="<?= $coin->image ?>" width="18"></td>
    <td><b><?= $coin->createExplorerLink($coin->name) ?></b></td>
    <td><b><?= Html::encode($coin->symbol) ?></b></td>
    <td><?= Html::encode($coin->algo) ?></td>
    <td><?= Html::encode($coin->version) ?></td>
    <td><?= Html::encode($coin->block_height) ?></td>
    <td data="<?= $coin->difficulty ?>"><?= $r['difficulty'] ?><?= $r['diffnote'] ?></td>
    <td><?= Html::a($coin->connections, $r['peersUrl'], ['class' => $r['cnxClass']]) ?></td>
    <td data="<?= $coin->network_hash ?>"><?= $r['nethash'] ?></td>
    <td><?php if ($r['link']): ?><?= Html::a($r['linkLabel'], $r['link'], ['target' => '_blank']) ?><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody></table>
<p style="font-size:.8em;">&nbsp;* Unified difficulty (may differ from wallet)<br/></p>
</div></div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header py-2">
        <strong class="small">Block Explorer</strong>
        <span class="badge bg-secondary ms-2"><?= count($coinRows) ?> coins</span>
    </div>
    <div class="card-body p-0">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="false" style="width:28px"></th>
        <th data-sorter="text">Name</th>
        <th data-sorter="text" style="width:70px">Symbol</th>
        <th data-sorter="text" style="width:80px">Algo</th>
        <th data-sorter="text">Version</th>
        <th data-sorter="numeric" class="text-end">Height</th>
        <th data-sorter="numeric" class="text-end">Difficulty</th>
        <th data-sorter="numeric" class="text-end">Conns</th>
        <th data-sorter="numeric" class="text-end">Net Hash</th>
        <th data-sorter="false" style="width:60px"></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($coinRows as $r):
        $coin = $r['coin']; ?>
    <tr>
        <td><?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td><strong><?= $coin->createExplorerLink(Html::encode($coin->name)) ?></strong></td>
        <td class="font-monospace small"><?= Html::encode($coin->symbol) ?></td>
        <td><span class="badge bg-light text-dark border font-monospace"><?= Html::encode($coin->algo) ?></span></td>
        <td class="small font-monospace"><?= Html::encode($coin->version) ?></td>
        <td class="text-end small tabular-nums"><?= Html::encode($coin->block_height) ?></td>
        <td class="text-end small font-monospace" data="<?= $coin->difficulty ?>">
            <?= $r['difficulty'] ?><?= $r['diffnote'] ?>
        </td>
        <td class="text-end small <?= $r['cnxClass'] === 'low' ? 'text-danger fw-bold' : '' ?>">
            <?= Html::a(Html::encode((string)$coin->connections), $r['peersUrl']) ?>
        </td>
        <td class="text-end small font-monospace" data="<?= $coin->network_hash ?>">
            <?= Html::encode($r['nethash']) ?>
        </td>
        <td class="small"><?php if ($r['link']): ?>
            <?= Html::a($r['linkLabel'], $r['link'], ['target' => '_blank', 'class' => 'text-muted']) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="card-footer text-muted small py-1">
        * Unified difficulty (may differ from wallet value)
    </div>
</div>
<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: {
            6: function(node,table,n){ return \$(node).attr('data'); },
            8: function(node,table,n){ return \$(node).attr('data'); }
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Block Explorer</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300"><?= count($coinRows) ?> coins</span>
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Name</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Symbol</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Algo</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Version</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Height</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Difficulty</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Conns</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Net Hash</th>
        <th class="px-3 py-2.5"            data-sorter="false"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($coinRows as $r):
        $coin = $r['coin']; ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
            <?= $coin->createExplorerLink(Html::encode($coin->name)) ?>
        </td>
        <td class="px-3 py-2 font-mono text-indigo-600 dark:text-indigo-400">
            <?= Html::encode($coin->symbol) ?>
        </td>
        <td class="px-3 py-2">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                         bg-indigo-50 dark:bg-indigo-900/30
                         text-indigo-700 dark:text-indigo-300 font-mono">
                <?= Html::encode($coin->algo) ?>
            </span>
        </td>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500">
            <?= Html::encode($coin->version) ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= Html::encode($coin->block_height) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300"
            data="<?= $coin->difficulty ?>">
            <?= $r['difficulty'] ?><?= $r['diffnote'] ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums
                   <?= $r['cnxClass'] === 'low' ? 'text-red-500 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300' ?>">
            <?= Html::a(Html::encode((string)$coin->connections), $r['peersUrl'],
                ['class' => 'hover:underline']) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400"
            data="<?= $coin->network_hash ?>">
            <?= Html::encode($r['nethash']) ?>
        </td>
        <td class="px-3 py-2"><?php if ($r['link']): ?>
            <?= Html::a($r['linkLabel'], $r['link'],
                ['target' => '_blank',
                 'class'  => 'text-xs text-gray-400 dark:text-gray-500 hover:text-indigo-500 transition-colors']) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        * Unified difficulty (may differ from wallet value)
    </div>
</div>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0], ths = Array.from(table.tHead.rows[0].cells);
    var asc = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rs = Array.from(tbody.rows);
            rs.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rs.forEach(function (r) { tbody.appendChild(r); });
        });
    });
});
");
?>

<?php endif ?>
