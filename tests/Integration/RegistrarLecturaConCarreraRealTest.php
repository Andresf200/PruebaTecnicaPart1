<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\EstadoRegistro;
use App\Application\RegistrarLectura;
use App\Application\SolicitudLectura;
use App\Domain\ReglaEstadoContrato;
use App\Domain\ReglaFecha;
use App\Domain\ReglaMonotonia;
use App\Domain\ReglaRangoContador;
use App\Domain\ResolutorContrato;
use App\Domain\TipoLectura;
use App\Infrastructure\SistemaReloj;
use App\Infrastructure\SqlServerContratoRepository;
use App\Infrastructure\SqlServerLecturaRepository;
use DateTimeImmutable;
use Tests\Support\LecturasConBusquedaCiega;

/**
 * Reproduce de forma determinista el instante crítico de una carrera: la
 * comprobación previa no ve la lectura, el INSERT choca contra el índice
 * único REAL de SQL Server y el caso de uso debe resolverlo sin duplicar.
 */
final class RegistrarLecturaConCarreraRealTest extends ConBaseDeDatos
{
    private const CUPS_CONTRATO_3 = 'ES0904488766344002MV';

    private function casoDeUso(): RegistrarLectura
    {
        return new RegistrarLectura(
            new SqlServerContratoRepository($this->db),
            new LecturasConBusquedaCiega(new SqlServerLecturaRepository($this->db)),
            new ResolutorContrato(),
            new ReglaFecha(),
            new ReglaEstadoContrato(),
            new ReglaRangoContador(),
            new ReglaMonotonia(),
            new SistemaReloj(),
        );
    }

    private function total(): int
    {
        return (int) $this->db->createCommand('SELECT COUNT(*) FROM dbo.lecturas')->queryScalar();
    }

    private function solicitud(int $kwh): SolicitudLectura
    {
        // Ya existe: contrato 3, 2026-06-01, real, 47755 (id 121)
        return new SolicitudLectura(self::CUPS_CONTRATO_3, new DateTimeImmutable('2026-06-01'), $kwh, TipoLectura::Real);
    }

    public function testCarreraConMismoValorLaResuelveElIndiceUnicoComoDuplicada(): void
    {
        $antes = $this->total();

        $r = $this->casoDeUso()->ejecutar($this->solicitud(47755));

        self::assertSame(EstadoRegistro::Duplicada, $r->estado);
        self::assertSame(121, $r->lectura?->id);
        self::assertSame($antes, $this->total());
    }

    public function testCarreraConOtroValorLaResuelveElIndiceUnicoComoConflicto(): void
    {
        $antes = $this->total();

        // 47800 supera las comprobaciones de monotonía: solo el índice puede pararla
        $r = $this->casoDeUso()->ejecutar($this->solicitud(47800));

        self::assertSame(EstadoRegistro::Conflicto, $r->estado);
        self::assertSame(47755, $r->lectura?->lecturaKwh);
        self::assertSame($antes, $this->total());
    }
}