<?php
/** @var yii\web\View         $this */
/** @var app\models\Coins     $coin */
/** @var array|false|null     $info */
/** @var string               $src  */

use yii\helpers\Html;

$btn = fn(string $label, string $url, string $color = 'indigo') =>
    Html::a($label, $url, [
        'class' => "inline-flex items-center px-3 py-1 rounded-md text-xs font-medium
                    bg-{$color}-100 dark:bg-{$color}-900/40
                    text-{$color}-700 dark:text-{$color}-300
                    hover:bg-{$color}-200 dark:hover:bg-{$color}-800
                    border border-{$color}-200 dark:border-{$color}-700
                    transition-colors",
    ]);
?>
<div class="flex flex-wrap gap-1.5 mb-3">
    <?= $btn('Properties', '/admin/coinwallet_update?id=' . $coin->id) ?>
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
        <?= $btn('Stop coind', '/admin/stopcoin?id=' . $coin->id, 'red') ?>
    <?php endif ?>
    <?= $coin->auto_ready
        ? $btn('Unset auto', '/admin/coinwallet_unsetauto?id=' . $coin->id, 'yellow')
        : $btn('Set auto',   '/admin/coinwallet_setauto?id='   . $coin->id, 'green') ?>
</div>
<div class="flex gap-3 text-xs text-gray-500 dark:text-gray-400 mb-3">
    <?php if (!empty($coin->link_bitcointalk)): ?><?= Html::a('forum',  $coin->link_bitcointalk, ['target' => '_blank', 'class' => 'hover:text-indigo-500']) ?><?php endif ?>
    <?php if (!empty($coin->link_github)):      ?><?= Html::a('git',    $coin->link_github,      ['target' => '_blank', 'class' => 'hover:text-indigo-500']) ?><?php endif ?>
    <?php if (!empty($coin->link_site)):        ?><?= Html::a('site',   $coin->link_site,        ['target' => '_blank', 'class' => 'hover:text-indigo-500']) ?><?php endif ?>
    <?php if (!empty($coin->link_explorer)):    ?><?= Html::a('chain',  $coin->link_explorer,    ['target' => '_blank', 'class' => 'hover:text-indigo-500', 'title' => 'External Blockchain Explorer']) ?><?php endif ?>
    <?= Html::a('google', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), ['target' => '_blank', 'class' => 'hover:text-indigo-500']) ?>
</div>
