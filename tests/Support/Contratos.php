<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Contrato;
use App\Domain\EstadoContrato;
use DateTimeImmutable;

final class Contratos
{
    public static function crear(
        int $id = 1,
        EstadoContrato $estado = EstadoContrato::Activo,
        string $alta = '2024-02-01',
        ?string $baja = null,
        int $digitos = 5,
        string $cups = 'ES0904998698797309NB',
    ): Contrato {
        return new Contrato(
            $id,
            $cups,
            $estado,
            new DateTimeImmutable($alta),
            $baja === null ? null : new DateTimeImmutable($baja),
            $digitos,
        );
    }
}