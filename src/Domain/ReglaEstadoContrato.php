<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * D1 (opción A): un contrato en estado 'baja' no acepta lecturas, ni siquiera
 * la de cierre en su fecha de baja. Si negocio decide aceptarla, el cambio
 * se hace únicamente aquí.
 */
final class ReglaEstadoContrato
{
    public function evaluar(Contrato $contrato): ?MotivoRechazo
    {
        return $contrato->estado === EstadoContrato::Baja
            ? MotivoRechazo::ContratoBaja
            : null;
    }
}