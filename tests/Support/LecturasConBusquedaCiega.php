<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Lectura;
use App\Domain\LecturaRepository;
use App\Domain\TipoLectura;
use DateTimeImmutable;

final class LecturasConBusquedaCiega implements LecturaRepository
{
    private int $ciegas;

    public function __construct(private readonly LecturaRepository $real, int $ciegas = 1)
    {
        $this->ciegas = $ciegas;
    }

    public function buscarPorClave(int $contratoId, DateTimeImmutable $fecha, TipoLectura $tipo): ?Lectura
    {
        if ($this->ciegas > 0) {
            $this->ciegas--;

            return null;
        }

        return $this->real->buscarPorClave($contratoId, $fecha, $tipo);
    }

    public function ultimaRealAnterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        return $this->real->ultimaRealAnterior($cups, $fecha);
    }

    public function primeraRealPosterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        return $this->real->primeraRealPosterior($cups, $fecha);
    }

    public function insertar(Lectura $lectura): ?int
    {
        return $this->real->insertar($lectura);
    }
}