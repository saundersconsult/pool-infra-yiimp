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

if ((defined('YIIMP_MEMCACHE_HOST')) && (YIIMP_MEMCACHE_HOST != '')) {
    $cache_config = [
            'class' => 'yii\caching\MemCache',
            'servers' => [
                [
                    'host' => YIIMP_MEMCACHE_HOST,
                    'port' => YIIMP_MEMCACHE_PORT,
                    'weight' => 60,
                ],
            ],
        ];
}
else {
    $cache_config = [
            'class' => 'yii\caching\FileCache',
        ];
}

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'YlmPlD1AbsvqCV3LLXoSvOJNhBcIAZEq',
        ],
        'cache' => $cache_config,
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => YIIMP_LOGS.'/yiimp2.log',
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

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // Map the legacy camelCase/underscore API URLs to Yii2 hyphenated action IDs
                'api/walletEx'        => 'api/wallet-ex',
                'api/walletex'        => 'api/wallet-ex',
                'api/rental_price'    => 'api/rental-price',
                'api/rental_hashrate' => 'api/rental-hashrate',
                'api/rental_start'    => 'api/rental-start',
                'api/rental_stop'     => 'api/rental-stop',
            ],
        ],

        'YiimpUtils' => [
            'class' => 'app\components\YiimpUtils',
        ],
        'ViewUtils' => [
            'class' => 'app\components\ViewUtils',
        ],
        'ConversionUtils' => [
            'class' => 'app\components\ConversionUtils',
        ],
        'ExplorerUtils' => [
            'class' => 'app\components\ExplorerUtils',
        ],
    ],
    'params' => $params,
];

if (YII_ENV === 'dev') {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
