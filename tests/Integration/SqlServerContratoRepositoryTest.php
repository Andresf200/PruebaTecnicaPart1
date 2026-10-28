<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\EstadoContrato;
use App\Infrastructure\SqlServerContratoRepository;

final class SqlServerContratoRepositoryTest extends ConBaseDeDatos
{
    private SqlServerContratoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SqlServerContratoRepository($this->db);
    }

    public function testCupsConDosContratosLosDevuelveOrdenadosPorAlta(): void
    {
        $contratos = $this->repo->contratosPorCups('ES0904688281126584QG');

        self::assertCount(2, $contratos);
        self::assertSame([12, 24], array_map(static fn ($c) => $c->id, $contratos));
        self::assertSame(EstadoContrato::Baja, $contratos[0]->estado);
        self::assertSame('2026-07-15', $contratos[0]->fechaBaja?->format('Y-m-d'));
        self::assertSame(EstadoContrato::Activo, $contratos[1]->estado);
        self::assertNull($contratos[1]->fechaBaja);
    }

    public function testCupsDesconocidoDevuelveListaVacia(): void
    {
        self::assertSame([], $this->repo->contratosPorCups('ES0000000000000000XX'));
    }

    public function testMapeaDigitosDelContadorYFechaDeAlta(): void
    {
        $seisDigitos = $this->repo->contratosPorCups('ES0904858176252807GJ')[0];
        $altaFutura = $this->repo->contratosPorCups('ES0904386713006681DW')[0];

        self::assertSame(6, $seisDigitos->digitosContador);
        self::assertSame(EstadoContrato::Activo, $altaFutura->estado);
        self::assertSame('2026-11-01', $altaFutura->fechaAlta->format('Y-m-d'));
    }
}