<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Application\ResultadoRegistro;
use App\Domain\Aviso;
use App\Domain\Lectura;
use App\Domain\MotivoRechazo;
use App\Domain\TipoLectura;
use App\Http\RespuestaLectura;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RespuestaLecturaTest extends TestCase
{
    private const CUPS = 'ES0904998698797309NB';

    private function lectura(int $kwh = 10900): Lectura
    {
        return new Lectura(77, 1, new DateTimeImmutable('2026-10-15'), $kwh, TipoLectura::Real, new DateTimeImmutable('2026-10-28 10:00:00'));
    }

    public function testCreadaEs201ConLecturaYAvisos(): void
    {
        $r = RespuestaLectura::desdeResultado(
            ResultadoRegistro::creada($this->lectura(), [Aviso::VueltaDeContador]),
            self::CUPS,
        );

        self::assertSame(201, $r->estadoHttp);
        self::assertTrue($r->cuerpo['guardada']);
        self::assertSame('LECTURA_CREADA', $r->cuerpo['codigo']);
        self::assertSame(
            ['id' => 77, 'cups' => self::CUPS, 'fecha' => '2026-10-15', 'lectura' => 10900, 'tipo' => 'real'],
            $r->cuerpo['lectura'],
        );
        self::assertSame('VUELTA_DE_CONTADOR', $r->cuerpo['avisos'][0]['codigo']);
    }

    public function testNoSeExponeElContratoInterno(): void
    {
        $r = RespuestaLectura::desdeResultado(ResultadoRegistro::creada($this->lectura()), self::CUPS);

        self::assertArrayNotHasKey('contrato_id', $r->cuerpo['lectura']);
    }

    public function testDuplicadaEs200ConGuardadaTrue(): void
    {
        $r = RespuestaLectura::desdeResultado(ResultadoRegistro::duplicada($this->lectura()), self::CUPS);

        self::assertSame(200, $r->estadoHttp);
        self::assertTrue($r->cuerpo['guardada']);
        self::assertSame('LECTURA_DUPLICADA', $r->cuerpo['codigo']);
    }

    public function testConflictoEs409YMuestraElValorYaGuardado(): void
    {
        $r = RespuestaLectura::desdeResultado(ResultadoRegistro::conflicto($this->lectura(10900)), self::CUPS);

        self::assertSame(409, $r->estadoHttp);
        self::assertFalse($r->cuerpo['guardada']);
        self::assertSame('LECTURA_CONFLICTO', $r->cuerpo['codigo']);
        self::assertSame(10900, $r->cuerpo['lectura_existente']['lectura']);
        self::assertStringContainsString('10900', $r->cuerpo['mensaje']);
    }

    /** @return iterable<string, array{MotivoRechazo}> */
    public static function motivos(): iterable
    {
        foreach (MotivoRechazo::cases() as $motivo) {
            yield $motivo->name => [$motivo];
        }
    }

    #[DataProvider('motivos')]
    public function testCadaRechazoDeNegocioEs422ConSuCodigoYNoSeGuarda(MotivoRechazo $motivo): void
    {
        $r = RespuestaLectura::desdeResultado(ResultadoRegistro::rechazada($motivo), self::CUPS);

        self::assertSame(422, $r->estadoHttp);
        self::assertFalse($r->cuerpo['guardada']);
        self::assertSame($motivo->value, $r->cuerpo['codigo']);
        self::assertSame($motivo->mensaje(), $r->cuerpo['mensaje']);
    }

    public function testValidacionJsonInvalidoYNoAutorizada(): void
    {
        $validacion = RespuestaLectura::validacion(['fecha' => 'mal']);
        self::assertSame(422, $validacion->estadoHttp);
        self::assertSame('VALIDACION', $validacion->cuerpo['codigo']);
        self::assertSame(['fecha' => 'mal'], $validacion->cuerpo['errores']);

        self::assertSame(400, RespuestaLectura::jsonInvalido()->estadoHttp);
        self::assertSame(401, RespuestaLectura::noAutorizada()->estadoHttp);
        self::assertFalse(RespuestaLectura::noAutorizada()->cuerpo['guardada']);
    }
}