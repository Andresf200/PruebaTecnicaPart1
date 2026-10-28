<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Lectura;
use App\Domain\LecturaRepository;
use App\Domain\TipoLectura;
use DateTimeImmutable;
use yii\db\Connection;
use yii\db\Exception as DbException;

final class SqlServerLecturaRepository implements LecturaRepository
{
    /** Violación de índice único (2601) o de constraint única (2627). */
    private const CODIGOS_CLAVE_DUPLICADA = [2601, 2627];

    public function __construct(private readonly Connection $db)
    {
    }

    public function buscarPorClave(int $contratoId, DateTimeImmutable $fecha, TipoLectura $tipo): ?Lectura
    {
        $fila = $this->db->createCommand(
            'SELECT id, contrato_id, fecha_lectura, lectura_kwh, tipo, fecha_recepcion
             FROM dbo.lecturas
             WHERE contrato_id = :contrato AND fecha_lectura = :fecha AND tipo = :tipo',
            [
                ':contrato' => $contratoId,
                ':fecha' => $fecha->format('Y-m-d'),
                ':tipo' => $tipo->value,
            ],
        )->queryOne();

        if ($fila === false) {
            return null;
        }

        return new Lectura(
            (int) $fila['id'],
            (int) $fila['contrato_id'],
            Fechas::desdeBd($fila['fecha_lectura']),
            (int) $fila['lectura_kwh'],
            TipoLectura::from((string) $fila['tipo']),
            Fechas::desdeBd($fila['fecha_recepcion']),
        );
    }

    public function ultimaRealAnterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        $valor = $this->db->createCommand(
            "SELECT TOP 1 l.lectura_kwh
             FROM dbo.lecturas AS l
             INNER JOIN dbo.contratos AS c ON c.id = l.contrato_id
             WHERE c.cups = :cups AND l.tipo = 'real' AND l.fecha_lectura < :fecha
             ORDER BY l.fecha_lectura DESC, l.id DESC",
            [':cups' => $cups, ':fecha' => $fecha->format('Y-m-d')],
        )->queryScalar();

        return $valor === false ? null : (int) $valor;
    }

    public function primeraRealPosterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        $valor = $this->db->createCommand(
            "SELECT TOP 1 l.lectura_kwh
             FROM dbo.lecturas AS l
             INNER JOIN dbo.contratos AS c ON c.id = l.contrato_id
             WHERE c.cups = :cups AND l.tipo = 'real' AND l.fecha_lectura > :fecha
             ORDER BY l.fecha_lectura ASC, l.id ASC",
            [':cups' => $cups, ':fecha' => $fecha->format('Y-m-d')],
        )->queryScalar();

        return $valor === false ? null : (int) $valor;
    }

    public function insertar(Lectura $lectura): ?int
    {
        try {
            $id = $this->db->createCommand(
                'INSERT INTO dbo.lecturas (contrato_id, fecha_lectura, lectura_kwh, tipo, fecha_recepcion)
                 OUTPUT INSERTED.id
                 VALUES (:contrato, :fecha, :kwh, :tipo, :recepcion)',
                [
                    ':contrato' => $lectura->contratoId,
                    ':fecha' => $lectura->fechaLectura->format('Y-m-d'),
                    ':kwh' => $lectura->lecturaKwh,
                    ':tipo' => $lectura->tipo->value,
                    ':recepcion' => $lectura->fechaRecepcion->format('Y-m-d H:i:s'),
                ],
            )->queryScalar();
        } catch (DbException $e) {
            if (in_array((int) ($e->errorInfo[1] ?? 0), self::CODIGOS_CLAVE_DUPLICADA, true)) {
                return null;
            }

            throw $e;
        }

        return (int) $id;
    }
}