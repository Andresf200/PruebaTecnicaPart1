<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use yii\db\Connection;
use yii\db\Transaction;

abstract class ConBaseDeDatos extends TestCase
{
    protected Connection $db;
    private Transaction $transaccion;

    protected function setUp(): void
    {
        $this->db = new Connection([
            'dsn' => getenv('DB_DSN'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ]);
        $this->db->open();
        $this->transaccion = $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->transaccion->getIsActive()) {
            $this->transaccion->rollBack();
        }
        $this->db->close();
    }
}