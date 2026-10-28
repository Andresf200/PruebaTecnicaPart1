<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\RegistrarLectura;
use Yii;

final class LecturasController extends ApiController
{
    /** @return array<string, mixed> */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['apiKey'] = [
            'class' => ApiKeyFilter::class,
            'clave' => (string) Yii::$app->params['apiKey'],
        ];

        return $behaviors;
    }

    /** @return array<string, list<string>> */
    protected function verbs(): array
    {
        return ['crear' => ['POST']];
    }

    /** @return array<string, mixed> */
    public function actionCrear(): array
    {
        $peticion = PeticionLectura::desdeJson(Yii::$app->request->getRawBody());

        if ($peticion->solicitud === null) {
            return $this->responder(
                $peticion->jsonInvalido
                    ? RespuestaLectura::jsonInvalido()
                    : RespuestaLectura::validacion($peticion->errores),
            );
        }

        /** @var RegistrarLectura $registrar */
        $registrar = Yii::$container->get(RegistrarLectura::class);
        $resultado = $registrar->ejecutar($peticion->solicitud);

        return $this->responder(RespuestaLectura::desdeResultado($resultado, $peticion->solicitud->cups));
    }

    /** @return array<string, mixed> */
    private function responder(RespuestaLectura $respuesta): array
    {
        Yii::$app->response->statusCode = $respuesta->estadoHttp;

        return $respuesta->cuerpo;
    }
}