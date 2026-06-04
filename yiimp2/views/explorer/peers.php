<?php

use app\components\rpc\WalletRPC;
use yii\helpers\Html;

if (!$coin) return Yii::$app->controller->goBack();

$this->title = 'Peers — ' . $coin->name;
$isTailwind  = Yii::$app->LayoutManager->isTailwind();

$remote     = new WalletRPC($coin);
$info       = $remote->error === null ? $remote->getinfo()    : false;
$list       = $remote->error === null ? $remote->getpeerinfo(): [];

$addnode = [];
foreach ((array) $list as $peer) {
    $node = Yii::$app->ConversionUtils->arraySafeVal($peer, 'addr');
    if (str_contains($node, '127.0.0.1')) continue;
    if (str_contains($node, '192.168.'))  continue;
    if (str_contains($node, 'yiimp'))     continue;
    $addnode[] = ($coin->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=') . $node;
}
asort($addnode);
?>

<?php if ($isTailwind): ?>
<!DOCTYPE html>
<html lang="en" class="<?= !empty($_COOKIE['yiimp_darkmode']) ? 'dark' : '' ?>">
<head>
    <meta charset="utf-8">
    <title><?= Html::encode($this->title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-4 text-sm">
    <h1 class="font-semibold text-base mb-3">
        <?= Html::encode($coin->name) ?> Peers
    </h1>
    <?php if (empty($addnode)): ?>
    <p class="text-gray-400 dark:text-gray-500">No peers available.</p>
    <?php else: ?>
    <pre class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                rounded-lg p-3 text-xs font-mono overflow-auto"><?= Html::encode(implode("\n", $addnode)) ?></pre>
    <?php endif ?>
</body>
</html>
<?php else: ?>
<style>body{margin:4px;}pre{margin:0 4px;}</style>
<div class="main-left-box">
<div class="main-left-title"><?= Html::encode($this->title) ?></div>
<div class="main-left-inner">
<pre><?= Html::encode(implode("\n", $addnode)) ?></pre>
</div></div>
<?php endif ?>
