<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Evaluacion
{
    /** @param list<Aviso> $avisos */
    private function __construct(
        public ?MotivoRechazo $rechazo,
        public array $avisos,
    ) {
    }

    /** @param list<Aviso> $avisos */
    public static function aceptada(array $avisos = []): self
    {
        return new self(null, $avisos);
    }

    public static function rechazada(MotivoRechazo $motivo): self
    {
        return new self($motivo, []);
    }

    public function esAceptada(): bool
    {
        return $this->rechazo === null;
    }
}