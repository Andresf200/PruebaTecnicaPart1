<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Lectura;
use App\Domain\LecturaRepository;
use App\Domain\TipoLectura;
use DateTimeImmutable;

final class LecturasEnMemoria implements LecturaRepository
{
    /** @var list<Lectura> */
    private array $lecturas = [];
    private int $siguienteId = 1000;

    /** Número de búsquedas iniciales que fingen no encontrar nada (simula una carrera). */
    public int $busquedasOcultas = 0;

    /** @param array<int, string> $cupsPorContrato */
    public function __construct(private readonly array $cupsPorContrato)
    {
    }

    public function sembrar(int $contratoId, string $fecha, int $kwh, TipoLectura $tipo = TipoLectura::Real): void
    {
        $this->lecturas[] = new Lectura(
            $this->siguienteId++,
            $contratoId,
            new DateTimeImmutable($fecha),
            $kwh,
            $tipo,
            new DateTimeImmutable('2026-01-01'),
        );
    }

    public function total(): int
    {
        return count($this->lecturas);
    }

    public function buscarPorClave(int $contratoId, DateTimeImmutable $fecha, TipoLectura $tipo): ?Lectura
    {
        if ($this->busquedasOcultas > 0) {
            $this->busquedasOcultas--;

            return null;
        }

        return $this->buscar($contratoId, $fecha, $tipo);
    }

    public function ultimaRealAnterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        $dia = $fecha->format('Y-m-d');
        $candidatas = array_values(array_filter(
            $this->realesDelCups($cups),
            static fn (Lectura $l): bool => $l->fechaLectura->format('Y-m-d') < $dia,
        ));
        usort($candidatas, static fn (Lectura $a, Lectura $b): int
            => [$b->fechaLectura->format('Y-m-d'), $b->id] <=> [$a->fechaLectura->format('Y-m-d'), $a->id]);

        return $candidatas === [] ? null : $candidatas[0]->lecturaKwh;
    }

    public function primeraRealPosterior(string $cups, DateTimeImmutable $fecha): ?int
    {
        $dia = $fecha->format('Y-m-d');
        $candidatas = array_values(array_filter(
            $this->realesDelCups($cups),
            static fn (Lectura $l): bool => $l->fechaLectura->format('Y-m-d') > $dia,
        ));
        usort($candidatas, static fn (Lectura $a, Lectura $b): int
            => [$a->fechaLectura->format('Y-m-d'), $a->id] <=> [$b->fechaLectura->format('Y-m-d'), $b->id]);

        return $candidatas === [] ? null : $candidatas[0]->lecturaKwh;
    }

    public function insertar(Lectura $lectura): ?int
    {
        // Emula el índice único de la base de datos
        if ($this->buscar($lectura->contratoId, $lectura->fechaLectura, $lectura->tipo) !== null) {
            return null;
        }

        $id = $this->siguienteId++;
        $this->lecturas[] = new Lectura(
            $id,
            $lectura->contratoId,
            $lectura->fechaLectura,
            $lectura->lecturaKwh,
            $lectura->tipo,
            $lectura->fechaRecepcion,
        );

        return $id;
    }

    private function buscar(int $contratoId, DateTimeImmutable $fecha, TipoLectura $tipo): ?Lectura
    {
        foreach ($this->lecturas as $l) {
            if ($l->contratoId === $contratoId
                && $l->tipo === $tipo
                && $l->fechaLectura->format('Y-m-d') === $fecha->format('Y-m-d')) {
                return $l;
            }
        }

        return null;
    }

    /** @return list<Lectura> */
    private function realesDelCups(string $cups): array
    {
        return array_values(array_filter(
            $this->lecturas,
            fn (Lectura $l): bool => $l->tipo === TipoLectura::Real
                && ($this->cupsPorContrato[$l->contratoId] ?? null) === $cups,
        ));
    }
}