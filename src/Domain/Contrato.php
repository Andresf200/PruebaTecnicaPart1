<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Contrato
{
    public function __construct(
        public int $id,
        public string $cups,
        public EstadoContrato $estado,
        public DateTimeImmutable $fechaAlta,
        public ?DateTimeImmutable $fechaBaja,
        public int $digitosContador,
    ) {
    }

    /**
     * Vigencia inclusiva en alta y baja (D6). Se compara solo el día
     * (Y-m-d) para que una hora en la fecha no altere el resultado.
     */
    public function estaVigenteEn(DateTimeImmutable $fecha): bool
    {
        $dia = $fecha->format('Y-m-d');

        return $dia >= $this->fechaAlta->format('Y-m-d')
            && ($this->fechaBaja === null || $dia <= $this->fechaBaja->format('Y-m-d'));
    }
}