<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

final class LecturasApiTest extends ApiTestCase
{
    private const CUPS_CONTRATO_1 = 'ES0904998698797309NB';
    private const CUPS_CONTRATO_8 = 'ES0904145244106460TD';

    /** @return array<string, mixed> */
    private function valida(int $lectura = 10900): array
    {
        return ['cups' => self::CUPS_CONTRATO_1, 'fecha' => '2026-10-15', 'lectura' => $lectura, 'tipo' => 'real'];
    }

    public function testCreaDespuesDuplicaYDespuesConflicto(): void
    {
        $creada = $this->api->enviar($this->cuerpo($this->valida(10900)));
        self::assertSame(201, $creada['estado']);
        self::assertTrue($creada['json']['guardada']);
        self::assertSame('LECTURA_CREADA', $creada['json']['codigo']);

        $duplicada = $this->api->enviar($this->cuerpo($this->valida(10900)));
        self::assertSame(200, $duplicada['estado']);
        self::assertTrue($duplicada['json']['guardada']);
        self::assertSame('LECTURA_DUPLICADA', $duplicada['json']['codigo']);
        self::assertSame($creada['json']['lectura']['id'], $duplicada['json']['lectura']['id']);

        $conflicto = $this->api->enviar($this->cuerpo($this->valida(11000)));
        self::assertSame(409, $conflicto['estado']);
        self::assertFalse($conflicto['json']['guardada']);
        self::assertSame('LECTURA_CONFLICTO', $conflicto['json']['codigo']);
        self::assertSame(10900, $conflicto['json']['lectura_existente']['lectura']);

        self::assertSame([10900], $this->valoresDeLaClave(1, '2026-10-15', 'real'));
    }

    public function testSinApiKeyDevuelve401YNoGuarda(): void
    {
        $antes = $this->totalLecturas();

        $r = $this->api->enviar($this->cuerpo($this->valida()), conClave: false);

        self::assertSame(401, $r['estado']);
        self::assertSame('NO_AUTORIZADA', $r['json']['codigo']);
        self::assertSame($antes, $this->totalLecturas());
    }

    public function testJsonRotoDevuelve400(): void
    {
        $r = $this->api->enviar('{"cups":');

        self::assertSame(400, $r['estado']);
        self::assertSame('JSON_INVALIDO', $r['json']['codigo']);
    }

    public function testCamposInvalidosDevuelven422ConErroresPorCampo(): void
    {
        $r = $this->api->enviar($this->cuerpo(['cups' => 'x', 'fecha' => '15/10/2026', 'lectura' => '10900', 'tipo' => 'otra']));

        self::assertSame(422, $r['estado']);
        self::assertSame('VALIDACION', $r['json']['codigo']);
        self::assertSame(['cups', 'fecha', 'lectura', 'tipo'], array_keys($r['json']['errores']));
    }

    /** @return array<string, array{string, string, int, string}> */
    public static function rechazosDeNegocio(): array
    {
        return [
            'contrato de baja' => ['ES0904229486628360ZC', '2026-08-15', 12000, 'CONTRATO_BAJA'],
            'fuera de vigencia' => ['ES0904229486628360ZC', '2026-09-15', 12100, 'FUERA_DE_VIGENCIA'],
            'cups desconocido' => ['ES0000000000000000XX', '2026-10-15', 100, 'CUPS_DESCONOCIDO'],
            'fecha futura' => [self::CUPS_CONTRATO_1, '2099-01-01', 11000, 'FECHA_FUTURA'],
            'fuera de rango' => [self::CUPS_CONTRATO_1, '2026-10-15', 100000, 'LECTURA_FUERA_DE_RANGO'],
            'decreciente' => [self::CUPS_CONTRATO_1, '2026-10-20', 10000, 'LECTURA_DECRECIENTE'],
            'superior a la posterior' => [self::CUPS_CONTRATO_1, '2026-09-15', 20000, 'LECTURA_SUPERIOR_A_POSTERIOR'],
        ];
    }

    #[DataProvider('rechazosDeNegocio')]
    public function testRechazosDeNegocioDevuelven422YNoGuardan(string $cups, string $fecha, int $lectura, string $codigo): void
    {
        $antes = $this->totalLecturas();

        $r = $this->api->enviar($this->cuerpo(['cups' => $cups, 'fecha' => $fecha, 'lectura' => $lectura, 'tipo' => 'real']));

        self::assertSame(422, $r['estado']);
        self::assertFalse($r['json']['guardada']);
        self::assertSame($codigo, $r['json']['codigo']);
        self::assertSame($antes, $this->totalLecturas());
    }

    public function testVueltaDeContadorSeAceptaConAviso(): void
    {
        // Contrato 8: su última lectura real es 99.812 con contador de 5 dígitos
        $r = $this->api->enviar($this->cuerpo(['cups' => self::CUPS_CONTRATO_8, 'fecha' => '2026-10-15', 'lectura' => 150, 'tipo' => 'real']));

        self::assertSame(201, $r['estado']);
        self::assertSame('VUELTA_DE_CONTADOR', $r['json']['avisos'][0]['codigo']);
    }
}