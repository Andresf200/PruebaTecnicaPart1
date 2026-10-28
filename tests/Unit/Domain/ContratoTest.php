<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contratos;

final class ContratoTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function fechasContratoConBaja(): array
    {
        // Contrato vigente del 2024-02-01 al 2026-08-31, ambos inclusive
        return [
            'día anterior al alta' => ['2024-01-31', false],
            'día del alta' => ['2024-02-01', true],
            'mitad de la vigencia' => ['2025-05-10', true],
            'día de la baja' => ['2026-08-31', true],
            'día de la baja con hora' => ['2026-08-31 23:59:59', true],
            'día posterior a la baja' => ['2026-09-01', false],
        ];
    }

    #[DataProvider('fechasContratoConBaja')]
    public function testVigenciaInclusivaEnAltaYBaja(string $fecha, bool $esperado): void
    {
        $contrato = Contratos::crear(alta: '2024-02-01', baja: '2026-08-31');

        self::assertSame($esperado, $contrato->estaVigenteEn(new DateTimeImmutable($fecha)));
    }

    public function testContratoSinBajaEsVigenteIndefinidamente(): void
    {
        $contrato = Contratos::crear(alta: '2024-02-01', baja: null);

        self::assertTrue($contrato->estaVigenteEn(new DateTimeImmutable('2040-01-01')));
    }

    public function testContratoActivoConAltaFuturaNoEsVigenteAntesDelAlta(): void
    {
        // Caso del contrato 25: estado 'activo', pero alta el 2026-11-01
        $contrato = Contratos::crear(alta: '2026-11-01');

        self::assertFalse($contrato->estaVigenteEn(new DateTimeImmutable('2026-10-31')));
        self::assertTrue($contrato->estaVigenteEn(new DateTimeImmutable('2026-11-01')));
    }
}