<?php

declare(strict_types=1);

namespace App\Http;

use Yii;
use yii\base\ActionFilter;

final class ApiKeyFilter extends ActionFilter
{
    public string $clave = '';

    /** @param \yii\base\Action $action */
    public function beforeAction($action): bool
    {
        $recibida = (string) Yii::$app->request->headers->get('X-API-Key', '');

        // Si no hay clave configurada, se rechaza todo: fallar cerrado
        if ($this->clave !== '' && hash_equals($this->clave, $recibida)) {
            return true;
        }

        $respuesta = RespuestaLectura::noAutorizada();
        Yii::$app->response->statusCode = $respuesta->estadoHttp;
        Yii::$app->response->data = $respuesta->cuerpo;

        return false;
    }
}