<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Contrato;
use App\Domain\ContratoRepository;
use App\Domain\EstadoContrato;
use yii\db\Connection;

final class SqlServerContratoRepository implements ContratoRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function contratosPorCups(string $cups): array
    {
        $filas = $this->db->createCommand(
            'SELECT id, cups, estado, fecha_alta, fecha_baja, digitos_contador
             FROM dbo.contratos
             WHERE cups = :cups
             ORDER BY fecha_alta, id',
            [':cups' => $cups],
        )->queryAll();

        return array_map(
            static fn (array $f): Contrato => new Contrato(
                (int) $f['id'],
                (string) $f['cups'],
                EstadoContrato::from((string) $f['estado']),
                Fechas::desdeBd($f['fecha_alta']),
                $f['fecha_baja'] === null ? null : Fechas::desdeBd($f['fecha_baja']),
                (int) $f['digitos_contador'],
            ),
            $filas,
        );
    }
}