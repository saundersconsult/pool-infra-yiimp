<?php
/** @var yii\web\View         $this */
/** @var app\models\Coins     $coin */
/** @var array|false|null     $info */
/** @var string               $src  */

use yii\helpers\Html;

$btn = fn(string $label, string $url, string $variant = 'secondary') =>
    Html::a($label, $url, ['class' => "btn btn-sm btn-outline-{$variant} me-1"]);
?>
<div class="d-flex flex-wrap gap-1 mb-2">
    <?= $btn('Properties', '/admin/coinwallet_update?id=' . $coin->id, 'primary') ?>
    <?php if ($info): ?>
        <?= $coin->createExplorerLink(Html::tag('span', 'Explorer')) ?>
        <?= $btn('Peers',    '/admin/coinwallet_peers?id='   . $coin->id) ?>
        <?php if (YIIMP_ADMIN_WEBCONSOLE): ?>
            <?= $btn('Console', '/admin/coinwallet_console?id=' . $coin->id) ?>
        <?php endif ?>
        <?= $btn('Triggers', '/admin/cointriggers?id=' . $coin->id) ?>
        <?php if ($src !== 'wallet'): ?>
            <?= $btn(Html::encode($coin->symbol), '/admin/coinwallet?id=' . $coin->id) ?>
        <?php endif ?>
    <?php endif ?>
    <?php if (!$info && $coin->enable): ?>
        <?= $btn('Stop coind', '/admin/stopcoin?id=' . $coin->id, 'danger') ?>
    <?php endif ?>
    <?= $coin->auto_ready
        ? $btn('Unset auto', '/admin/coinwallet_unsetauto?id=' . $coin->id, 'warning')
        : $btn('Set auto',   '/admin/coinwallet_setauto?id='   . $coin->id, 'success') ?>
</div>
<div class="mb-2 small">
    <?php if (!empty($coin->link_bitcointalk)): ?><?= Html::a('forum',  $coin->link_bitcointalk, ['target' => '_blank', 'class' => 'me-2']) ?><?php endif ?>
    <?php if (!empty($coin->link_github)):      ?><?= Html::a('git',    $coin->link_github,      ['target' => '_blank', 'class' => 'me-2']) ?><?php endif ?>
    <?php if (!empty($coin->link_site)):        ?><?= Html::a('site',   $coin->link_site,        ['target' => '_blank', 'class' => 'me-2']) ?><?php endif ?>
    <?php if (!empty($coin->link_explorer)):    ?><?= Html::a('chain',  $coin->link_explorer,    ['target' => '_blank', 'class' => 'me-2', 'title' => 'External Blockchain Explorer']) ?><?php endif ?>
    <?= Html::a('google', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), ['target' => '_blank']) ?>
</div>
