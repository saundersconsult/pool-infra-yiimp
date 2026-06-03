<?php

/** @var yii\web\View                        $this        */
/** @var app\models\Coins[]                  $coins       */
/** @var app\models\Mining|null              $mining      */
/** @var array{found:array,orphan:array}     $blockCounts */

use yii\helpers\Html;

$usdBtc = $mining->usdbtc ?? 0;

?>
<style type="text/css">
tr.ssrow.filtered { display: none; }
th.status, td.status { min-width: 28px; max-width: 48px; text-align: center; }
td.status { font-family: monospace; font-size: 9pt; letter-spacing: 3px; }
td.status span.progress { font-size: .8em; letter-spacing: 0; }
td.status span.hidden { visibility: hidden; }
span.eov { opacity: 0.5; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', '{
tableClass: "dataGrid",
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}'); ?>

<thead>
<tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text" width="30" class="status"></th>
<th data-sorter="text">Name</th>
<th data-sorter="text">Server</th>
<th data-sorter="currency" align="right">Difficulty<br/>Height</th>
<th data-sorter="currency" align="right" title="mBTC profit. shown in mining status">Profit<br/>Pool Net</th>
<th data-sorter="currency" align="right">Bid Price<br/>Ask Price</th>
<th data-sorter="currency" align="right">Immature<br/>Cleared</th>
<th data-sorter="currency" align="right">Balance<br/>Available</th>
<th data-sorter="currency" align="right">BTC</th>
<th data-sorter="currency" align="right">USD</th>
<th data-sorter="currency" align="right">Win<br/>Market</th>
</tr>
</thead><tbody>

<?php foreach ($coins as $coin):
    $algoColor  = Yii::$app->YiimpUtils->getAlgoColors($coin->algo);
    $version    = Yii::$app->ConversionUtils->formatWalletVersion($coin);
    if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

    $difficulty = Yii::$app->ConversionUtils->Itoa2($coin->difficulty, 3);
    if ($coin->difficulty > 1e20) $difficulty = '&nbsp;';

    $btcmhd  = Yii::$app->ConversionUtils->mbitcoinvaluetoa(Yii::$app->YiimpUtils->yiimp_profitability($coin));
    $ss1     = $blockCounts['found'][$coin->id]  ?? 0;
    $ss2     = $blockCounts['orphan'][$coin->id] ?? 0;
    $pPool1  = $ss1 ? $ss1 . '%' : '';
    $pPool2  = $ss2 ? $ss2 . '%' : '';

    $price   = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price);
    $price2  = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price2);

    $cellImmature = Yii::$app->ConversionUtils->valuetocell($coin->mint)
                  . '<br/>'
                  . Yii::$app->ConversionUtils->valuetocell($coin->cleared);
    $cellBalance  = Yii::$app->ConversionUtils->valuetocell($coin->balance)
                  . '<br/>'
                  . Yii::$app->ConversionUtils->valuetocell($coin->available);

    $btcBalance   = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->balance   * $coin->price);
    $btcAvailable = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->available * $coin->price);

    $fiatBalance   = round($coin->balance   * $coin->price * $usdBtc, 2) . ' $';
    $fiatAvailable = round($coin->available * $coin->price * $usdBtc, 2) . ' $';

    $deficitClass  = ($coin->balance + $coin->mint < $coin->cleared) ? ' class="red"' : '';
    $priceStyle    = ($coin->dontsell && YIIMP_ALLOW_EXCHANGE) ? 'background-color: #ffaaaa' : '';

    $statusCell  = (!$coin->enable    ? '<span class="hidden" title="Coin disabled">X</span>'
                 : ($coin->auto_ready ? '<span class="green"  title="Auto enable">A</span>'
                                      : '<span class="red"    title="Stratum disabled">D</span>'))
                 . ($coin->visible ? '<span title="Visible to public">V</span>' : '<span title="Hidden">H</span>')
                 . ($coin->auxpow  ? '<span title="AUX PoW">X</span>' : '&nbsp;')
                 . '<br/>'
                 . ($coin->rpccurl ? '<span title="RPC with Curl">C</span>' : '&nbsp;')
                 . ($coin->rpcssl  ? '<span title="RPC over SSL">S</span>'  : '&nbsp;')
                 . ($coin->watch   ? '<span title="Watched (history)">W</span>' : '&nbsp;');
    if ($coin->block_height < $coin->target_height)
        $statusCell .= '<br/><span class="progress">' . round($coin->block_height * 100 / $coin->target_height, 1) . '%</span>';
?>
<tr class="ssrow">

    <td><?= Html::img(Html::encode($coin->image), ['width' => 24]) ?></td>

    <td class="status" style="background-color: <?= $algoColor ?>;"><?= $statusCell ?></td>

    <td>
        <b><?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"), ['/admin/coinwallet', 'id' => $coin->id]) ?></b>
        <br><span style="font-size: .8em"><?= Html::encode($version) ?></span>
    </td>

    <td>
        <?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
        <?= $coin->connections ? ' (' . (int) $coin->connections . ')' : '' ?>
        <br><span style="font-size: .8em">
            <?= Html::encode($coin->rpcencoding) ?>
            <span style="background-color:<?= $algoColor ?>;">&nbsp; <?= Html::encode($coin->algo) ?> &nbsp;</span>
        </span>
    </td>

    <?php if (!empty($coin->errors)): ?>
        <td align="right" style="font-size: .9em;" class="red" title="<?= Html::encode($coin->errors) ?>">
            <b><?= $difficulty ?></b><br/><?= (int) $coin->block_height ?>
        </td>
    <?php else: ?>
        <td align="right" style="font-size: .9em;">
            <b><?= $difficulty ?></b><br><?= (int) $coin->block_height ?>
        </td>
    <?php endif ?>

    <td align="right" style="font-size: .9em;" title="Pool % of last 100 net blocks">
        <b><?= $btcmhd ?></b><br/>
        <?= $ss1 > 50 ? '<span class="blue">' . $pPool1 . '</span>' : $pPool1 ?>
        <span class="red" title="orphans"> <?= $pPool2 ?></span>
    </td>

    <td align="right" style="font-size: .9em;<?= $priceStyle ? " $priceStyle" : '' ?>">
        <?= $price ?><br><?= $price2 ?>
    </td>

    <td align="right" style="font-size: .9em;"<?= $deficitClass ?>><?= $cellImmature ?></td>

    <td align="right" style="font-size: .9em;"><?= $cellBalance ?></td>

    <td align="right" style="font-size: .9em;"><?= $btcBalance ?><br/><?= $btcAvailable ?></td>

    <td align="right" style="font-size: .9em;"><?= $fiatBalance ?><br/><?= $fiatAvailable ?></td>

    <td align="right" style="font-size: .9em;"><?= Html::encode((string) $coin->reward) ?></td>

</tr>
<?php endforeach ?>

</tbody>
<tr><th colspan="12"><?= count($coins) ?> wallets</th></tr>
</table>
<br/>
