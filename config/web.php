<?php

declare(strict_types=1);

use App\Application\RegistrarLectura;
use App\Domain\ReglaEstadoContrato;
use App\Domain\ReglaFecha;
use App\Domain\ReglaMonotonia;
use App\Domain\ReglaRangoContador;
use App\Domain\ResolutorContrato;
use App\Infrastructure\SistemaReloj;
use App\Infrastructure\SqlServerContratoRepository;
use App\Infrastructure\SqlServerLecturaRepository;

return [
    'id' => 'lecturas-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'App\Http',
    'params' => [
        'apiKey' => getenv('API_KEY') ?: '',
    ],
    'container' => [
        'definitions' => [
            // Punto único de ensamblado: qué implementación usa cada pieza.
            RegistrarLectura::class => static fn (): RegistrarLectura => new RegistrarLectura(
                new SqlServerContratoRepository(Yii::$app->db),
                new SqlServerLecturaRepository(Yii::$app->db),
                new ResolutorContrato(),
                new ReglaFecha(),
                new ReglaEstadoContrato(),
                new ReglaRangoContador(),
                new ReglaMonotonia(),
                new SistemaReloj(),
            ),
        ],
    ],
    'components' => [
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/v1/salud' => 'salud/index',
                'api/v1/lecturas' => 'lecturas/crear', // sin verbo: otros métodos dan 405, no 404
            ],
        ],
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => getenv('DB_DSN'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ],
        'log' => [
            'targets' => [
                ['class' => yii\log\FileTarget::class, 'levels' => ['error', 'warning']],
            ],
        ],
    ],
];