<?php

declare(strict_types=1);

namespace App\Domain;

final class ReglaRangoContador
{
    public function evaluar(int $lecturaKwh, int $digitosContador): ?MotivoRechazo
    {
        $tope = 10 ** $digitosContador;

        return ($lecturaKwh < 0 || $lecturaKwh >= $tope)
            ? MotivoRechazo::LecturaFueraDeRango
            : null;
    }
}