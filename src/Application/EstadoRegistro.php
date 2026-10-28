<?php

declare(strict_types=1);

namespace App\Application;

enum EstadoRegistro: string
{
    case Creada = 'creada';
    case Duplicada = 'duplicada';
    case Conflicto = 'conflicto';
    case Rechazada = 'rechazada';
}