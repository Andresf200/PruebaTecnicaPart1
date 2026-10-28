<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\SolicitudLectura;
use App\Domain\TipoLectura;
use DateTimeImmutable;
use JsonException;

final readonly class PeticionLectura
{
    /** ES + 16 dígitos + 2 letras, con 2 caracteres de frontera opcionales (20 o 22 caracteres). */
    private const PATRON_CUPS = '/^ES\d{16}[A-Z]{2}(\d[FPCRXYZ])?$/';

    /** @param array<string, string> $errores */
    private function __construct(
        public ?SolicitudLectura $solicitud,
        public array $errores,
        public bool $jsonInvalido,
    ) {
    }

    public static function desdeJson(string $json): self
    {
        try {
            $datos = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new self(null, [], true);
        }

        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            return new self(null, ['cuerpo' => 'El cuerpo debe ser un objeto JSON.'], false);
        }

        $errores = [];

        $cups = $datos['cups'] ?? null;
        if (!is_string($cups)) {
            $errores['cups'] = 'Obligatorio: texto con el CUPS del punto de suministro.';
        } else {
            $cups = strtoupper((string) preg_replace('/\s+/', '', $cups));
            if (preg_match(self::PATRON_CUPS, $cups) !== 1) {
                $errores['cups'] = 'Formato no válido: ES + 16 dígitos + 2 letras (más 2 caracteres de frontera opcionales).';
            }
        }

        $fecha = null;
        $texto = $datos['fecha'] ?? null;
        if (!is_string($texto)) {
            $errores['fecha'] = 'Obligatorio: fecha de la lectura con formato YYYY-MM-DD.';
        } else {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
            if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
                $fecha = null;
                $errores['fecha'] = 'Formato esperado YYYY-MM-DD y una fecha existente.';
            }
        }

        $kwh = $datos['lectura'] ?? null;
        if (!is_int($kwh)) {
            $errores['lectura'] = 'Obligatorio: número entero de kWh (valor acumulado del contador).';
        }

        $tipo = is_string($datos['tipo'] ?? null) ? TipoLectura::tryFrom($datos['tipo']) : null;
        if ($tipo === null) {
            $errores['tipo'] = "Obligatorio: 'real' o 'estimada'.";
        }

        if ($errores !== []) {
            return new self(null, $errores, false);
        }

        return new self(new SolicitudLectura($cups, $fecha, $kwh, $tipo), [], false);
    }
}