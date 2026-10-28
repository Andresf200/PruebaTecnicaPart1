<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Aviso;
use App\Domain\Lectura;
use App\Domain\MotivoRechazo;

final readonly class ResultadoRegistro
{
    /** @param list<Aviso> $avisos */
    private function __construct(
        public EstadoRegistro $estado,
        public ?Lectura $lectura,
        public ?MotivoRechazo $motivo,
        public array $avisos,
    ) {
    }

    /** @param list<Aviso> $avisos */
    public static function creada(Lectura $lectura, array $avisos = []): self
    {
        return new self(EstadoRegistro::Creada, $lectura, null, $avisos);
    }

    public static function duplicada(Lectura $existente): self
    {
        return new self(EstadoRegistro::Duplicada, $existente, null, []);
    }

    /** La lectura que lleva el resultado es la YA guardada, con su valor distinto. */
    public static function conflicto(Lectura $existente): self
    {
        return new self(EstadoRegistro::Conflicto, $existente, null, []);
    }

    public static function rechazada(MotivoRechazo $motivo): self
    {
        return new self(EstadoRegistro::Rechazada, null, $motivo, []);
    }
}