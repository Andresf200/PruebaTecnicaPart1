<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\EstadoRegistro;
use App\Application\RegistrarLectura;
use App\Application\ResultadoRegistro;
use App\Application\SolicitudLectura;
use App\Domain\Aviso;
use App\Domain\Contrato;
use App\Domain\EstadoContrato;
use App\Domain\MotivoRechazo;
use App\Domain\ReglaEstadoContrato;
use App\Domain\ReglaFecha;
use App\Domain\ReglaMonotonia;
use App\Domain\ReglaRangoContador;
use App\Domain\ResolutorContrato;
use App\Domain\TipoLectura;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contratos;
use Tests\Support\ContratosEnMemoria;
use Tests\Support\LecturasEnMemoria;
use Tests\Support\RelojFijo;

final class RegistrarLecturaTest extends TestCase
{
    private const CUPS = 'ES0904998698797309NB';
    private const CUPS_COMPARTIDO = 'ES0904688281126584QG';

    private RegistrarLectura $caso;
    private LecturasEnMemoria $lecturas;

    private function montar(Contrato ...$contratos): void
    {
        $cupsPorContrato = [];
        foreach ($contratos as $c) {
            $cupsPorContrato[$c->id] = $c->cups;
        }

        $this->lecturas = new LecturasEnMemoria($cupsPorContrato);
        $this->caso = new RegistrarLectura(
            new ContratosEnMemoria(array_values($contratos)),
            $this->lecturas,
            new ResolutorContrato(),
            new ReglaFecha(),
            new ReglaEstadoContrato(),
            new ReglaRangoContador(),
            new ReglaMonotonia(),
            new RelojFijo(new DateTimeImmutable('2026-10-28 10:00:00')),
        );
    }

    private function pedir(
        string $fecha,
        int $kwh,
        TipoLectura $tipo = TipoLectura::Real,
        string $cups = self::CUPS,
    ): ResultadoRegistro {
        return $this->caso->ejecutar(new SolicitudLectura($cups, new DateTimeImmutable($fecha), $kwh, $tipo));
    }

    private function assertRechazo(MotivoRechazo $esperado, ResultadoRegistro $r): void
    {
        self::assertSame(EstadoRegistro::Rechazada, $r->estado);
        self::assertSame($esperado, $r->motivo);
    }

    // --- Camino feliz e idempotencia -----------------------------------

    public function testLecturaNuevaSeCreaYSeGuardaConLaHoraDelReloj(): void
    {
        $this->montar(Contratos::crear(1));

        $r = $this->pedir('2026-10-15', 10900);

        self::assertSame(EstadoRegistro::Creada, $r->estado);
        self::assertNotNull($r->lectura?->id);
        self::assertSame('2026-10-28 10:00:00', $r->lectura->fechaRecepcion->format('Y-m-d H:i:s'));
        self::assertSame(1, $this->lecturas->total());
    }

    public function testReintentoIdenticoDevuelveDuplicadaSinGuardarOtraFila(): void
    {
        $this->montar(Contratos::crear(1));

        $primero = $this->pedir('2026-10-15', 10900);
        $segundo = $this->pedir('2026-10-15', 10900);

        self::assertSame(EstadoRegistro::Creada, $primero->estado);
        self::assertSame(EstadoRegistro::Duplicada, $segundo->estado);
        self::assertSame($primero->lectura?->id, $segundo->lectura?->id);
        self::assertSame(1, $this->lecturas->total());
    }

    public function testMismaClaveConOtroValorEsConflictoYNoSobrescribe(): void
    {
        $this->montar(Contratos::crear(1));
        $this->pedir('2026-10-15', 10900);

        $r = $this->pedir('2026-10-15', 11000);

        self::assertSame(EstadoRegistro::Conflicto, $r->estado);
        self::assertSame(10900, $r->lectura?->lecturaKwh);
        self::assertSame(1, $this->lecturas->total());
    }

    public function testRealYEstimadaDelMismoDiaSonLecturasDistintas(): void
    {
        $this->montar(Contratos::crear(1));
        $this->pedir('2026-10-15', 10900, TipoLectura::Estimada);

        $r = $this->pedir('2026-10-15', 10950, TipoLectura::Real);

        self::assertSame(EstadoRegistro::Creada, $r->estado);
        self::assertSame(2, $this->lecturas->total());
    }

    public function testCarreraConMismoValorSeResuelveComoDuplicada(): void
    {
        $this->montar(Contratos::crear(1));
        $this->lecturas->sembrar(1, '2026-10-15', 10900);
        $this->lecturas->busquedasOcultas = 1; // la comprobación previa "no la ve"

        $r = $this->pedir('2026-10-15', 10900);

        self::assertSame(EstadoRegistro::Duplicada, $r->estado);
        self::assertSame(1, $this->lecturas->total());
    }

    public function testCarreraConOtroValorSeResuelveComoConflicto(): void
    {
        $this->montar(Contratos::crear(1));
        $this->lecturas->sembrar(1, '2026-10-15', 10900);
        $this->lecturas->busquedasOcultas = 1;

        $r = $this->pedir('2026-10-15', 11000);

        self::assertSame(EstadoRegistro::Conflicto, $r->estado);
        self::assertSame(1, $this->lecturas->total());
    }

    // --- D3: duplicado antes que reglas de negocio ---------------------

    public function testReintentoDeLecturaYaGuardadaEnContratoDadoDeBajaDevuelveDuplicada(): void
    {
        // Caso real: lectura de cierre 161 del contrato 12
        $this->montar(Contratos::crear(12, EstadoContrato::Baja, '2023-01-01', '2026-07-15', cups: self::CUPS_COMPARTIDO));
        $this->lecturas->sembrar(12, '2026-07-15', 25317);

        $r = $this->pedir('2026-07-15', 25317, cups: self::CUPS_COMPARTIDO);

        self::assertSame(EstadoRegistro::Duplicada, $r->estado);
    }

    public function testLecturaNuevaEnContratoDeBajaSeRechazaAunqueEsteEnVigencia(): void
    {
        $this->montar(Contratos::crear(5, EstadoContrato::Baja, '2021-02-01', '2026-08-31'));

        $this->assertRechazo(MotivoRechazo::ContratoBaja, $this->pedir('2026-08-15', 12000));
        // D1 opción A: tampoco la lectura de cierre en la propia fecha de baja
        $this->assertRechazo(MotivoRechazo::ContratoBaja, $this->pedir('2026-08-31', 12100));
        self::assertSame(0, $this->lecturas->total());
    }

    // --- Rechazos -------------------------------------------------------

    public function testCupsDesconocido(): void
    {
        $this->montar(Contratos::crear(1));

        $this->assertRechazo(
            MotivoRechazo::CupsDesconocido,
            $this->pedir('2026-10-15', 10900, cups: 'ES0000000000000000XX'),
        );
    }

    public function testFechaFueraDeVigencia(): void
    {
        $this->montar(Contratos::crear(5, EstadoContrato::Baja, '2021-02-01', '2026-08-31'));

        $this->assertRechazo(MotivoRechazo::FueraDeVigencia, $this->pedir('2026-09-15', 12100));
        $this->assertRechazo(MotivoRechazo::FueraDeVigencia, $this->pedir('2021-01-31', 100));
    }

    public function testFechaFutura(): void
    {
        $this->montar(Contratos::crear(1));

        $this->assertRechazo(MotivoRechazo::FechaFutura, $this->pedir('2026-10-29', 10900));
        self::assertSame(EstadoRegistro::Creada, $this->pedir('2026-10-28', 10900)->estado);
    }

    public function testLecturaQueNoCabeEnElContador(): void
    {
        $this->montar(Contratos::crear(1, digitos: 5));

        $this->assertRechazo(MotivoRechazo::LecturaFueraDeRango, $this->pedir('2026-10-15', 100000));
    }

    // --- Monotonía ------------------------------------------------------

    public function testLecturaDecrecienteSeRechaza(): void
    {
        $this->montar(Contratos::crear(1));
        $this->lecturas->sembrar(1, '2026-10-01', 10849);

        $this->assertRechazo(MotivoRechazo::LecturaDecreciente, $this->pedir('2026-10-15', 10000));
    }

    public function testVueltaDeContadorSeAceptaConAviso(): void
    {
        $this->montar(Contratos::crear(8, digitos: 5));
        $this->lecturas->sembrar(8, '2026-10-01', 99812);

        $r = $this->pedir('2026-10-15', 150);

        self::assertSame(EstadoRegistro::Creada, $r->estado);
        self::assertSame([Aviso::VueltaDeContador], $r->avisos);
    }

    public function testLlegadaDesordenadaSuperiorALaPosteriorSeRechaza(): void
    {
        $this->montar(Contratos::crear(1));
        $this->lecturas->sembrar(1, '2026-09-01', 1000);

        $this->assertRechazo(MotivoRechazo::LecturaSuperiorAPosterior, $this->pedir('2026-08-01', 1200));
    }

    public function testUnaEstimadaNoSeCompararaConLaRealPosterior(): void
    {
        $this->montar(Contratos::crear(1));
        $this->lecturas->sembrar(1, '2026-09-01', 1000);

        $r = $this->pedir('2026-08-01', 1200, TipoLectura::Estimada);

        self::assertSame(EstadoRegistro::Creada, $r->estado);
    }

    public function testLasEstimadasNoCuentanComoLecturaAnterior(): void
    {
        $this->montar(Contratos::crear(18, cups: 'ES0904828347285568BB'));
        $this->lecturas->sembrar(18, '2026-05-01', 44651);
        $this->lecturas->sembrar(18, '2026-06-01', 44894, TipoLectura::Estimada);

        // Menor que la estimada, pero mayor que la última real: debe aceptarse
        $r = $this->pedir('2026-06-15', 44700, cups: 'ES0904828347285568BB');

        self::assertSame(EstadoRegistro::Creada, $r->estado);
    }

    // --- Cambio de titular ---------------------------------------------

    public function testElCambioDeTitularAsignaElContratoPorFechaYValidaPorCups(): void
    {
        $this->montar(
            Contratos::crear(12, EstadoContrato::Baja, '2023-01-01', '2026-07-15', cups: self::CUPS_COMPARTIDO),
            Contratos::crear(24, EstadoContrato::Activo, '2026-07-16', cups: self::CUPS_COMPARTIDO),
        );
        $this->lecturas->sembrar(12, '2026-07-15', 25317);

        $nueva = $this->pedir('2026-08-01', 25613, cups: self::CUPS_COMPARTIDO);
        self::assertSame(EstadoRegistro::Creada, $nueva->estado);
        self::assertSame(24, $nueva->lectura?->contratoId);

        // El contador es del CUPS: bajar respecto a la lectura del contrato anterior se rechaza
        $this->assertRechazo(
            MotivoRechazo::LecturaDecreciente,
            $this->pedir('2026-07-20', 25000, cups: self::CUPS_COMPARTIDO),
        );
    }
}