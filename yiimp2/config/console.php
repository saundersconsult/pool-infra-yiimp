<?php

// include serverconfig
require_once('/etc/yiimp/serverconfig.php');
require_once(__DIR__ . '/constants.php');   // promote YAAMP_* → YIIMP_* if serverconfig is old

if (defined('YIIMP_DEBUG') && (YIIMP_DEBUG === true)) {
    define('YII_DEBUG', true);
    define('YII_ENV', 'dev');
}
else {
    define('YII_DEBUG', false);
    define('YII_ENV', 'prod');
}

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'queue' => [
            'class'     => \yii\queue\db\Queue::class,
            'db'        => 'db',
            'tableName' => '{{%queue}}',
            'channel'   => 'default',
            'mutex'     => \yii\mutex\MysqlMutex::class,
        ],
        'mutex' => [
            'class' => \yii\mutex\MysqlMutex::class,
        ],
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV === 'dev') {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
