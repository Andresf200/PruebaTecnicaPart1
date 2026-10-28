<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\EstadoContrato;
use App\Domain\MotivoRechazo;
use App\Domain\ReglaEstadoContrato;
use App\Domain\ReglaFecha;
use App\Domain\ReglaRangoContador;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contratos;

final class ReglasSencillasTest extends TestCase
{
    /** @return array<string, array{string, ?MotivoRechazo}> */
    public static function fechas(): array
    {
        // «hoy» fijado en 2026-10-28
        return [
            'ayer' => ['2026-10-27', null],
            'hoy' => ['2026-10-28', null],
            'hoy con hora' => ['2026-10-28 23:59:59', null],
            'mañana' => ['2026-10-29', MotivoRechazo::FechaFutura],
        ];
    }

    #[DataProvider('fechas')]
    public function testFechaFutura(string $fecha, ?MotivoRechazo $esperado): void
    {
        $resultado = (new ReglaFecha())->evaluar(new DateTimeImmutable($fecha), new DateTimeImmutable('2026-10-28'));

        self::assertSame($esperado, $resultado);
    }

    public function testContratoActivoPasaYContratoDeBajaSeRechaza(): void
    {
        $regla = new ReglaEstadoContrato();

        self::assertNull($regla->evaluar(Contratos::crear(estado: EstadoContrato::Activo)));
        self::assertSame(
            MotivoRechazo::ContratoBaja,
            $regla->evaluar(Contratos::crear(estado: EstadoContrato::Baja, baja: '2026-08-31')),
        );
    }

    /** @return array<string, array{int, int, ?MotivoRechazo}> */
    public static function rangos(): array
    {
        return [
            'cero' => [0, 5, null],
            'máximo con 5 dígitos' => [99999, 5, null],
            'excede 5 dígitos' => [100000, 5, MotivoRechazo::LecturaFueraDeRango],
            'negativa' => [-1, 5, MotivoRechazo::LecturaFueraDeRango],
            'máximo con 6 dígitos' => [999999, 6, null],
            'excede 6 dígitos' => [1000000, 6, MotivoRechazo::LecturaFueraDeRango],
            '100000 es válida con 6 dígitos' => [100000, 6, null],
        ];
    }

    #[DataProvider('rangos')]
    public function testRangoPorDigitosDelContador(int $lectura, int $digitos, ?MotivoRechazo $esperado): void
    {
        self::assertSame($esperado, (new ReglaRangoContador())->evaluar($lectura, $digitos));
    }
}