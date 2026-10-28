<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Domain\TipoLectura;
use App\Http\PeticionLectura;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeticionLecturaTest extends TestCase
{
    private const VALIDA = ['cups' => 'ES0904998698797309NB', 'fecha' => '2026-10-15', 'lectura' => 10900, 'tipo' => 'real'];

    /** @param array<string, mixed> $cambios */
    private static function json(array $cambios = [], string ...$quitar): string
    {
        $datos = array_merge(self::VALIDA, $cambios);
        foreach ($quitar as $campo) {
            unset($datos[$campo]);
        }

        return json_encode($datos, JSON_THROW_ON_ERROR);
    }

    public function testPeticionValidaGeneraLaSolicitud(): void
    {
        $p = PeticionLectura::desdeJson(self::json());

        self::assertSame([], $p->errores);
        self::assertSame('ES0904998698797309NB', $p->solicitud?->cups);
        self::assertSame('2026-10-15', $p->solicitud?->fecha->format('Y-m-d'));
        self::assertSame(10900, $p->solicitud?->lecturaKwh);
        self::assertSame(TipoLectura::Real, $p->solicitud?->tipo);
    }

    public function testElCupsSeNormalizaAMayusculasYSinEspacios(): void
    {
        $p = PeticionLectura::desdeJson(self::json(['cups' => ' es0904998698797309nb ']));

        self::assertSame('ES0904998698797309NB', $p->solicitud?->cups);
    }

    public function testCupsDeVeintidosCaracteresEsValido(): void
    {
        $p = PeticionLectura::desdeJson(self::json(['cups' => 'ES0904998698797309NB0F']));

        self::assertNotNull($p->solicitud);
    }

    /** @return array<string, array{string, string}> */
    public static function peticionesInvalidas(): array
    {
        return [
            'cups ausente' => [self::json([], 'cups'), 'cups'],
            'cups con formato incorrecto' => [self::json(['cups' => 'XX123']), 'cups'],
            'cups numérico' => [self::json(['cups' => 12345]), 'cups'],
            'fecha ausente' => [self::json([], 'fecha'), 'fecha'],
            'fecha con barras' => [self::json(['fecha' => '15/10/2026']), 'fecha'],
            'fecha inexistente' => [self::json(['fecha' => '2026-02-30']), 'fecha'],
            'fecha con hora' => [self::json(['fecha' => '2026-10-15T10:00:00']), 'fecha'],
            'fecha numérica' => [self::json(['fecha' => 20261015]), 'fecha'],
            'lectura ausente' => [self::json([], 'lectura'), 'lectura'],
            'lectura como texto' => [self::json(['lectura' => '10900']), 'lectura'],
            'lectura decimal' => [self::json(['lectura' => 10900.5]), 'lectura'],
            'lectura nula' => [self::json(['lectura' => null]), 'lectura'],
            'tipo ausente' => [self::json([], 'tipo'), 'tipo'],
            'tipo desconocido' => [self::json(['tipo' => 'otro']), 'tipo'],
            'tipo en mayúsculas' => [self::json(['tipo' => 'REAL']), 'tipo'],
            'tipo numérico' => [self::json(['tipo' => 1]), 'tipo'],
        ];
    }

    #[DataProvider('peticionesInvalidas')]
    public function testCamposInvalidosSeSenalanPorSuNombre(string $json, string $campo): void
    {
        $p = PeticionLectura::desdeJson($json);

        self::assertNull($p->solicitud);
        self::assertFalse($p->jsonInvalido);
        self::assertSame([$campo], array_keys($p->errores));
    }

    public function testSeAcumulanTodosLosErroresEnUnaSolaRespuesta(): void
    {
        $p = PeticionLectura::desdeJson('{}');

        self::assertSame(['cups', 'fecha', 'lectura', 'tipo'], array_keys($p->errores));
    }

    /** @return array<string, array{string}> */
    public static function jsonRoto(): array
    {
        return ['vacío' => [''], 'truncado' => ['{"cups":'], 'texto' => ['no es json']];
    }

    #[DataProvider('jsonRoto')]
    public function testJsonIlegibleSeMarcaComoInvalido(string $cuerpo): void
    {
        $p = PeticionLectura::desdeJson($cuerpo);

        self::assertTrue($p->jsonInvalido);
        self::assertNull($p->solicitud);
    }

    /** @return array<string, array{string}> */
    public static function noEsObjeto(): array
    {
        return ['lista' => ['[1,2]'], 'texto' => ['"hola"'], 'número' => ['5']];
    }

    #[DataProvider('noEsObjeto')]
    public function testUnCuerpoQueNoEsObjetoSeRechazaComoValidacion(string $cuerpo): void
    {
        $p = PeticionLectura::desdeJson($cuerpo);

        self::assertFalse($p->jsonInvalido);
        self::assertSame(['cuerpo'], array_keys($p->errores));
    }
}