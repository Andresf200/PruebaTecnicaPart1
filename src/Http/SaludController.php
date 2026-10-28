<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;
use Yii;

final class SaludController extends ApiController
{
    /** @return array<string, string> */
    public function actionIndex(): array
    {
        try {
            Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (Throwable) {
            Yii::$app->response->statusCode = 503;

            return ['estado' => 'degradado', 'base_de_datos' => 'error'];
        }

        return ['estado' => 'ok', 'base_de_datos' => 'ok'];
    }
}