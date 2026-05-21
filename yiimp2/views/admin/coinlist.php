<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $provider */
/** @var int    $totalInstalled */
/** @var int    $totalActive */
/** @var string $searchQuery */
/** @var int    $pageSize */

use yii\helpers\Html;
use yii\widgets\LinkPager;
use app\models\Markets;

$this->title = 'Coins';

$pagination  = $provider->pagination;
$totalCoins  = $provider->totalCount;
$coins       = $provider->models;

$pageSizeOptions = [25 => '25', 50 => '50', 100 => '100', 250 => '250'];
?>

<style>
.page .footer { clear: both; width: auto; margin-top: 16px; }
</style>

<!-- search + page-size bar -->
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
    <?= Html::beginForm(['admin/coinlist'], 'get', ['class' => 'd-flex align-items-center gap-2']) ?>
    <?= Html::hiddenInput('pageSize', $pageSize) ?>
    <?= Html::textInput('q', $searchQuery, [
        'class'       => 'form-control form-control-sm',
        'placeholder' => 'Search name / symbol / algo…',
        'style'       => 'width:200px',
        'autocomplete'=> 'off',
    ]) ?>
    <?= Html::submitButton('Search', ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    <?php if ($searchQuery !== ''): ?>
        <?= Html::a('✕ Clear', ['admin/coinlist', 'pageSize' => $pageSize], ['class' => 'btn btn-sm btn-outline-danger']) ?>
    <?php endif ?>
    <?= Html::endForm() ?>

    <span class="text-muted small ms-auto">
        <?= $totalCoins ?> coin<?= $totalCoins !== 1 ? 's' : '' ?>
        <?php if ($searchQuery !== ''): ?> matching &ldquo;<?= Html::encode($searchQuery) ?>&rdquo;<?php endif ?>
        &nbsp;·&nbsp; <?= $totalInstalled ?> installed &nbsp;·&nbsp; <?= $totalActive ?> running
    </span>

    <!-- page size selector -->
    <div class="d-flex align-items-center gap-1">
        <span class="text-muted small">Show</span>
        <?php foreach ($pageSizeOptions as $n => $label):
            $active = ($n === $pageSize);
            echo Html::a($label, ['admin/coinlist', 'q' => $searchQuery, 'pageSize' => $n, 'page' => 1], [
                'class' => 'btn btn-sm ' . ($active ? 'btn-secondary' : 'btn-outline-secondary'),
            ]);
        endforeach ?>
    </div>
</div>

<?php if ($totalCoins === 0): ?>
<div class="alert alert-info">No coins found<?= $searchQuery !== '' ? ' for &ldquo;' . Html::encode($searchQuery) . '&rdquo;' : '' ?>.</div>
<?php else: ?>

<!-- top pager -->
<?= LinkPager::widget([
    'pagination'   => $pagination,
    'options'      => ['class' => 'pagination pagination-sm justify-content-center mb-2'],
    'linkOptions'  => ['class' => 'page-link'],
    'linkContainerOptions' => ['class' => 'page-item'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'maxButtonCount' => 8,
    'firstPageLabel' => '«',
    'lastPageLabel'  => '»',
]) ?>

<table id="maintable" class="dataGrid tablesorter">
<thead><tr>
    <th data-sorter="" width="30"></th>
    <th data-sorter="text">Name</th>
    <th data-sorter="text">Symbol</th>
    <th data-sorter="text">Algo</th>
    <th data-sorter="text">Status</th>
    <th data-sorter="text">Version</th>
    <th data-sorter="numeric">Created</th>
    <th data-sorter="numeric">Height</th>
    <th data-sorter="text">Message</th>
    <th data-sorter="">Links</th>
</tr></thead>
<tbody>
<?php foreach ($coins as $coin):
    $coin->version = substr((string) $coin->version, 0, 20);
    $created = Yii::$app->ConversionUtils->datetoa2($coin->created);
?>
<tr class="ssrow">
    <td><img src="<?= Html::encode($coin->image) ?>" width="18" alt=""></td>
    <td><b><?= Html::a(Html::encode($coin->name), ['/admin/coin_update', 'id' => $coin->id]) ?></b></td>
    <td><b><?= Html::a(Html::encode($coin->symbol), ['/admin/coinwallet_update', 'id' => $coin->id]) ?></b></td>
    <td><?= Html::encode($coin->algo) ?></td>
    <td>
        <?php if ($coin->enable): ?>
            <span class="text-success">running</span>
        <?php elseif ($coin->installed): ?>
            installed
        <?php endif ?>
    </td>
    <td><?= Html::encode($coin->version) ?></td>
    <td data="<?= (int) $coin->created ?>"><?= $created ?></td>
    <td class="text-center"><?= (int) $coin->block_height ?></td>
    <td><?= Html::encode(substr((string) $coin->errors, 0, 30)) ?></td>
    <td>
        <?php if (!empty($coin->link_bitcointalk)): ?>
            <?= Html::a('forum', Html::encode($coin->link_bitcointalk), ['target' => '_blank']) ?>&nbsp;
        <?php endif ?>
        <?php if (!empty($coin->link_github)): ?>
            <?= Html::a('git', Html::encode($coin->link_github), ['target' => '_blank']) ?>&nbsp;
        <?php endif ?>
        <?= Html::a('google', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), ['target' => '_blank']) ?>&nbsp;
        <?php
        $markets = Markets::find()->select('name')->where(['coinid' => $coin->id])->asArray()->all();
        foreach ($markets as $market):
        ?>
            <span class="text-muted small"><?= Html::encode($market['name']) ?></span>&nbsp;
        <?php endforeach ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr class="ssrow sfooter">
    <th></th>
    <th colspan="9">
        Page <?= $pagination->page + 1 ?> of <?= $pagination->pageCount ?>
        &nbsp;·&nbsp;
        <?= Html::a('Add a new coin', ['/admin/coin_create']) ?>
    </th>
</tr>
</tfoot>
</table>

<!-- bottom pager -->
<?= LinkPager::widget([
    'pagination'   => $pagination,
    'options'      => ['class' => 'pagination pagination-sm justify-content-center mt-2'],
    'linkOptions'  => ['class' => 'page-link'],
    'linkContainerOptions' => ['class' => 'page-item'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'maxButtonCount' => 8,
    'firstPageLabel' => '«',
    'lastPageLabel'  => '»',
]) ?>

<?php endif ?>

<?php
Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: { 6: function(node,table,n){ return \$(node).attr('data'); } },
    widgets: ['zebra'],
    widgetOptions: {}
}");
?>
