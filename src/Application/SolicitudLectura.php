<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\TipoLectura;
use DateTimeImmutable;

final readonly class SolicitudLectura
{
    public function __construct(
        public string $cups,
        public DateTimeImmutable $fecha,
        public int $lecturaKwh,
        public TipoLectura $tipo,
    ) {
    }
}