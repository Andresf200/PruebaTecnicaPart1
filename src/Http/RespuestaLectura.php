<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\EstadoRegistro;
use App\Application\ResultadoRegistro;
use App\Domain\Aviso;
use App\Domain\Lectura;
use App\Domain\MotivoRechazo;

final readonly class RespuestaLectura
{
    /** @param array<string, mixed> $cuerpo */
    private function __construct(
        public int $estadoHttp,
        public array $cuerpo,
    ) {
    }

    public static function desdeResultado(ResultadoRegistro $r, string $cups): self
    {
        return match ($r->estado) {
            EstadoRegistro::Creada => new self(201, [
                'guardada' => true,
                'codigo' => 'LECTURA_CREADA',
                'mensaje' => 'Lectura registrada.',
                'lectura' => self::lectura($r->lectura, $cups),
                'avisos' => self::avisos($r->avisos),
            ]),
            EstadoRegistro::Duplicada => new self(200, [
                'guardada' => true,
                'codigo' => 'LECTURA_DUPLICADA',
                'mensaje' => 'La lectura ya estaba registrada con el mismo valor; no se ha vuelto a guardar.',
                'lectura' => self::lectura($r->lectura, $cups),
                'avisos' => [],
            ]),
            EstadoRegistro::Conflicto => new self(409, [
                'guardada' => false,
                'codigo' => 'LECTURA_CONFLICTO',
                'mensaje' => sprintf(
                    'Ya existe una lectura para ese CUPS, fecha y tipo con un valor distinto (%d kWh). No se ha modificado.',
                    $r->lectura->lecturaKwh,
                ),
                'lectura_existente' => self::lectura($r->lectura, $cups),
            ]),
            EstadoRegistro::Rechazada => new self(self::estadoDeRechazo($r->motivo), [
                'guardada' => false,
                'codigo' => $r->motivo->value,
                'mensaje' => $r->motivo->mensaje(),
            ]),
        };
    }

    /** @param array<string, string> $errores */
    public static function validacion(array $errores): self
    {
        return new self(422, [
            'guardada' => false,
            'codigo' => 'VALIDACION',
            'mensaje' => 'La petición no es válida. Revise los campos indicados.',
            'errores' => $errores,
        ]);
    }

    public static function jsonInvalido(): self
    {
        return new self(400, [
            'guardada' => false,
            'codigo' => 'JSON_INVALIDO',
            'mensaje' => 'El cuerpo no es un JSON válido.',
        ]);
    }

    public static function noAutorizada(): self
    {
        return new self(401, [
            'guardada' => false,
            'codigo' => 'NO_AUTORIZADA',
            'mensaje' => 'API key ausente o no válida. Envíe la cabecera X-API-Key.',
        ]);
    }

    /** Todos los rechazos de negocio son errores del contenido (422). Si alguno debe diferenciarse, aquí. */
    private static function estadoDeRechazo(MotivoRechazo $motivo): int
    {
        return 422;
    }

    /** @return array<string, mixed> */
    private static function lectura(Lectura $l, string $cups): array
    {
        // No se expone contrato_id: es un dato interno
        return [
            'id' => $l->id,
            'cups' => $cups,
            'fecha' => $l->fechaLectura->format('Y-m-d'),
            'lectura' => $l->lecturaKwh,
            'tipo' => $l->tipo->value,
        ];
    }

    /**
     * @param list<Aviso> $avisos
     * @return list<array{codigo: string, mensaje: string}>
     */
    private static function avisos(array $avisos): array
    {
        return array_map(
            static fn (Aviso $a): array => ['codigo' => $a->value, 'mensaje' => $a->mensaje()],
            $avisos,
        );
    }
}