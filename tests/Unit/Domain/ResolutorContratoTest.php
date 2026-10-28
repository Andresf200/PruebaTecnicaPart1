<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\EstadoContrato;
use App\Domain\ResolutorContrato;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contratos;

final class ResolutorContratoTest extends TestCase
{
    public function testElCambioDeTitularSeResuelvePorFecha(): void
    {
        $anterior = Contratos::crear(12, EstadoContrato::Baja, '2023-01-01', '2026-07-15');
        $nuevo = Contratos::crear(24, EstadoContrato::Activo, '2026-07-16');
        $resolutor = new ResolutorContrato();

        self::assertSame($anterior, $resolutor->resolver([$anterior, $nuevo], new DateTimeImmutable('2026-07-15')));
        self::assertSame($nuevo, $resolutor->resolver([$anterior, $nuevo], new DateTimeImmutable('2026-07-16')));
    }

    public function testDevuelveNullSiNingunContratoEsVigente(): void
    {
        $contrato = Contratos::crear(5, EstadoContrato::Baja, '2021-02-01', '2026-08-31');

        self::assertNull((new ResolutorContrato())->resolver([$contrato], new DateTimeImmutable('2026-09-15')));
    }

    public function testDevuelveNullSinContratos(): void
    {
        self::assertNull((new ResolutorContrato())->resolver([], new DateTimeImmutable('2026-01-01')));
    }
}