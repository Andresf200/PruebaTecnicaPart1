<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class ReglaFecha
{
    public function evaluar(DateTimeImmutable $fecha, DateTimeImmutable $hoy): ?MotivoRechazo
    {
        return $fecha->format('Y-m-d') > $hoy->format('Y-m-d')
            ? MotivoRechazo::FechaFutura
            : null;
    }
}