<?php

declare(strict_types=1);

namespace App\Domain;

enum EstadoContrato: string
{
    case Activo = 'activo';
    case Baja = 'baja';
}