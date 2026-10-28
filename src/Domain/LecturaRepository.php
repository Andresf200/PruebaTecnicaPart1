<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface LecturaRepository
{
    public function buscarPorClave(int $contratoId, DateTimeImmutable $fecha, TipoLectura $tipo): ?Lectura;

    /** Valor de la última lectura REAL del CUPS con fecha estrictamente anterior (cualquier contrato). */
    public function ultimaRealAnterior(string $cups, DateTimeImmutable $fecha): ?int;

    /** Valor de la primera lectura REAL del CUPS con fecha estrictamente posterior (cualquier contrato). */
    public function primeraRealPosterior(string $cups, DateTimeImmutable $fecha): ?int;

    /**
     * Inserta la lectura. Devuelve el id nuevo, o null si ya existía una lectura
     * con la misma clave (contrato, fecha, tipo): lo decide la base de datos.
     */
    public function insertar(Lectura $lectura): ?int;
}