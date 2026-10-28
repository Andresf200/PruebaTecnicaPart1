<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;

interface Reloj
{
    public function ahora(): DateTimeImmutable;
}