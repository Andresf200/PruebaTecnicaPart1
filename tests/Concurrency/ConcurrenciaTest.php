<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Tests\Support\ApiTestCase;

final class ConcurrenciaTest extends ApiTestCase
{
    private const PETICIONES = 20;
    private const CUPS = 'ES0904998698797309NB'; // contrato 1
    private const FECHA = '2026-10-10';

    protected function setUp(): void
    {
        parent::setUp();

        // Una estimada no se compara con la real posterior, y 10.900 supera la real anterior (10.849)
        $this->db->createCommand(
            "DELETE FROM dbo.lecturas WHERE contrato_id = 1 AND fecha_lectura = :f AND tipo = 'estimada'",
            [':f' => self::FECHA],
        )->execute();
    }

    private function cuerpo2(int $kwh): string
    {
        return $this->cuerpo(['cups' => self::CUPS, 'fecha' => self::FECHA, 'lectura' => $kwh, 'tipo' => 'estimada']);
    }

    public function testVeintePeticionesIdenticasGuardanUnaSolaFila(): void
    {
        $respuestas = $this->api->enviarVarios(array_fill(0, self::PETICIONES, $this->cuerpo2(10900)));

        $estados = array_count_values(array_column($respuestas, 'estado'));

        self::assertSame(1, $estados[201] ?? 0, 'Exactamente una petición debe crear la lectura: ' . json_encode($estados));
        self::assertSame(self::PETICIONES - 1, $estados[200] ?? 0, 'El resto deben ser duplicadas idempotentes: ' . json_encode($estados));
        self::assertSame([10900], $this->valoresDeLaClave(1, self::FECHA, 'estimada'));
    }

    public function testPeticionesConValoresDistintosGuardanUnaSolaFilaYTodasVenElMismoValor(): void
    {
        $cuerpos = [];
        for ($i = 0; $i < self::PETICIONES; $i++) {
            $cuerpos[] = $this->cuerpo2($i % 2 === 0 ? 10900 : 10950);
        }

        $respuestas = $this->api->enviarVarios($cuerpos);

        $creadas = array_filter($respuestas, static fn (array $r): bool => $r['estado'] === 201);
        self::assertCount(1, $creadas, 'Solo una petición puede ganar la carrera');

        $guardado = $this->valoresDeLaClave(1, self::FECHA, 'estimada');
        self::assertCount(1, $guardado);

        foreach ($respuestas as $r) {
            self::assertContains($r['estado'], [200, 201, 409]);
            $visto = $r['json']['lectura']['lectura'] ?? $r['json']['lectura_existente']['lectura'] ?? null;
            self::assertSame($guardado[0], $visto, 'Toda respuesta debe reflejar el valor realmente guardado');
        }
    }
}