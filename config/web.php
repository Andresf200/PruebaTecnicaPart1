<?php

declare(strict_types=1);

return [
    'id' => 'lecturas-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'App\Http',
    'components' => [
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/v1/salud' => 'salud/index',
            ],
        ],
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => getenv('DB_DSN'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ],
    ],
];