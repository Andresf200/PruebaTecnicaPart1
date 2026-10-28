<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Lectura
{
    public function __construct(
        public ?int $id,
        public int $contratoId,
        public DateTimeImmutable $fechaLectura,
        public int $lecturaKwh,
        public TipoLectura $tipo,
        public DateTimeImmutable $fechaRecepcion,
    ) {
    }
}