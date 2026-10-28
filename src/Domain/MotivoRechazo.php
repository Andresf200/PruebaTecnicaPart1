<?php

declare(strict_types=1);

namespace App\Domain;

enum MotivoRechazo: string
{
    case CupsDesconocido = 'CUPS_DESCONOCIDO';
    case ContratoBaja = 'CONTRATO_BAJA';
    case FueraDeVigencia = 'FUERA_DE_VIGENCIA';
    case FechaFutura = 'FECHA_FUTURA';
    case LecturaFueraDeRango = 'LECTURA_FUERA_DE_RANGO';
    case LecturaDecreciente = 'LECTURA_DECRECIENTE';
    case LecturaSuperiorAPosterior = 'LECTURA_SUPERIOR_A_POSTERIOR';

    public function mensaje(): string
    {
        return match ($this) {
            self::CupsDesconocido => 'No existe ningún contrato para el CUPS indicado.',
            self::ContratoBaja => 'El contrato está dado de baja y no acepta lecturas.',
            self::FueraDeVigencia => 'La fecha de la lectura queda fuera de la vigencia del contrato.',
            self::FechaFutura => 'La fecha de la lectura no puede ser futura.',
            self::LecturaFueraDeRango => 'La lectura no cabe en los dígitos del contador.',
            self::LecturaDecreciente => 'La lectura es inferior a la anterior y no parece una vuelta de contador.',
            self::LecturaSuperiorAPosterior => 'La lectura es superior a una lectura posterior ya registrada.',
        };
    }
}