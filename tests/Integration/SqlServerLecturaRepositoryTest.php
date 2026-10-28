<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Lectura;
use App\Domain\TipoLectura;
use App\Infrastructure\SqlServerLecturaRepository;
use DateTimeImmutable;

final class SqlServerLecturaRepositoryTest extends ConBaseDeDatos
{
    private SqlServerLecturaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SqlServerLecturaRepository($this->db);
    }

    private function lectura(int $contrato, string $fecha, int $kwh, TipoLectura $tipo = TipoLectura::Real): Lectura
    {
        return new Lectura(null, $contrato, new DateTimeImmutable($fecha), $kwh, $tipo, new DateTimeImmutable('2026-10-28 10:00:00'));
    }

    // --- buscarPorClave -------------------------------------------------

    public function testBuscarPorClaveEncuentraLaLecturaExistente(): void
    {
        $l = $this->repo->buscarPorClave(3, new DateTimeImmutable('2026-06-01'), TipoLectura::Real);

        self::assertNotNull($l);
        self::assertSame(121, $l->id); // la 137 se depuró en la migración 001
        self::assertSame(47755, $l->lecturaKwh);
        self::assertSame(TipoLectura::Real, $l->tipo);
    }

    public function testBuscarPorClaveDevuelveNullSiNoExiste(): void
    {
        self::assertNull($this->repo->buscarPorClave(3, new DateTimeImmutable('2026-06-01'), TipoLectura::Estimada));
    }

    // --- insertar -------------------------------------------------------

    public function testInsertarDevuelveElIdYLaLecturaQuedaGuardada(): void
    {
        $id = $this->repo->insertar($this->lectura(1, '2026-10-15', 10900));

        self::assertIsInt($id);
        $guardada = $this->repo->buscarPorClave(1, new DateTimeImmutable('2026-10-15'), TipoLectura::Real);
        self::assertSame($id, $guardada?->id);
        self::assertSame(10900, $guardada?->lecturaKwh);
        self::assertSame('2026-10-28 10:00:00', $guardada?->fechaRecepcion->format('Y-m-d H:i:s'));
    }

    public function testInsertarClaveDuplicadaDevuelveNullYNoLanzaExcepcion(): void
    {
        // Contrato 3, 2026-06-01, real: ya existe (id 121). Lo rechaza el índice único.
        self::assertNull($this->repo->insertar($this->lectura(3, '2026-06-01', 99999)));
    }

    public function testElMismoDiaConOtroTipoNoEsDuplicado(): void
    {
        self::assertIsInt($this->repo->insertar($this->lectura(3, '2026-06-01', 47760, TipoLectura::Estimada)));
    }

    // --- ultimaRealAnterior / primeraRealPosterior ---------------------

    public function testLaVecinaAnteriorPuedeSerDeOtroContratoDelMismoCups(): void
    {
        // CUPS compartido por los contratos 12 y 24. Antes del 2026-07-16 solo hay
        // una lectura: la de cierre del contrato 12 (2026-07-15, 25317).
        $anterior = $this->repo->ultimaRealAnterior('ES0904688281126584QG', new DateTimeImmutable('2026-07-16'));

        self::assertSame(25317, $anterior);
    }

    public function testVecinasEnMitadDeLaSerie(): void
    {
        $cups = 'ES0904688281126584QG';
        $fecha = new DateTimeImmutable('2026-08-15');

        self::assertSame(25613, $this->repo->ultimaRealAnterior($cups, $fecha));   // 2026-08-01
        self::assertSame(25785, $this->repo->primeraRealPosterior($cups, $fecha)); // 2026-09-01
    }

    public function testLasVecinasIgnoranLasEstimadas(): void
    {
        // Contrato 18 tiene una estimada el 2026-06-01 (44894). Debe saltarse.
        $cups = 'ES0904828347285568BB';
        $fecha = new DateTimeImmutable('2026-06-15');

        self::assertSame(44651, $this->repo->ultimaRealAnterior($cups, $fecha));   // real del 2026-05-01
        self::assertSame(45096, $this->repo->primeraRealPosterior($cups, $fecha)); // real del 2026-07-01
    }

    public function testSinVecinasDevuelveNull(): void
    {
        // Contrato 22: su primera lectura es el 2026-03-01 (la comparación es estricta)
        self::assertNull($this->repo->ultimaRealAnterior('ES0904473573638560MM', new DateTimeImmutable('2026-03-01')));
        // Contrato 21: su última lectura es el 2026-10-01
        self::assertNull($this->repo->primeraRealPosterior('ES0904417758881440PH', new DateTimeImmutable('2026-10-01')));
    }
}