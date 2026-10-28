<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Reloj;
use DateTimeImmutable;
use DateTimeZone;

final class SistemaReloj implements Reloj
{
    public function __construct(private readonly DateTimeZone $zona = new DateTimeZone('Europe/Madrid'))
    {
    }

    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zona);
    }
}