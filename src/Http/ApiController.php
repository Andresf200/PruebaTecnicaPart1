<?php

declare(strict_types=1);

namespace App\Http;

use yii\rest\Controller;

abstract class ApiController extends Controller
{
    /**
     * Yii activa por defecto un limitador de tasa que exige un componente
     * `user` con identityClass. Esta API no tiene usuarios (se autentica con
     * API key), así que se desactiva. El rate limiting queda como siguiente paso.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        unset($behaviors['rateLimiter']);

        return $behaviors;
    }
}