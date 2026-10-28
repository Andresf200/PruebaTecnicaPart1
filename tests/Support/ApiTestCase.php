<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use yii\db\Connection;

abstract class ApiTestCase extends TestCase
{
    protected ClienteApi $api;
    protected ?Connection $db = null;
    private int $idBase = 0;

    protected function setUp(): void
    {
        $this->api = new ClienteApi();
        if (!$this->api->disponible()) {
            self::markTestSkipped('La API no responde en ' . ClienteApi::BASE . '. Levanta el servicio app.');
        }

        $this->db = new Connection([
            'dsn' => getenv('DB_DSN'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ]);
        $this->db->open();
        $this->idBase = (int) $this->db->createCommand('SELECT ISNULL(MAX(id), 0) FROM dbo.lecturas')->queryScalar();
    }

    protected function tearDown(): void
    {
        if ($this->db === null) {
            return;
        }

        // Las peticiones HTTP se confirman de verdad: se borra lo creado por el test
        $this->db->createCommand('DELETE FROM dbo.lecturas WHERE id > :base', [':base' => $this->idBase])->execute();
        $this->db->close();
    }

    protected function totalLecturas(): int
    {
        return (int) $this->db->createCommand('SELECT COUNT(*) FROM dbo.lecturas')->queryScalar();
    }

    /** @return list<int> */
    protected function valoresDeLaClave(int $contratoId, string $fecha, string $tipo): array
    {
        $valores = $this->db->createCommand(
            'SELECT lectura_kwh FROM dbo.lecturas WHERE contrato_id = :c AND fecha_lectura = :f AND tipo = :t',
            [':c' => $contratoId, ':f' => $fecha, ':t' => $tipo],
        )->queryColumn();

        return array_map('intval', $valores);
    }

    /** @param array<string, mixed> $campos */
    protected function cuerpo(array $campos): string
    {
        return json_encode($campos, JSON_THROW_ON_ERROR);
    }
}