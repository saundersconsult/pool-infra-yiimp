<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $jsOptions = ['position' => \yii\web\View::POS_HEAD];
    public $css = [
        'css/site.css',
        'css/main.css',
        'css/table.css',
        'css/jquery-ui.css',
        'css/uni-form.css'
    ];
    public $js = [
        'js/jquery.tablesorter.js',
        'js/jqplot/jquery.jqplot.js',
        'js/jqplot/plugins/jqplot.barRenderer.js',
        'js/jqplot/plugins/jqplot.dateAxisRenderer.js',
        'js/jqplot/plugins/jqplot.highlighter.js',
        'js/jqplot/plugins/jqplot.cursor.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
}
