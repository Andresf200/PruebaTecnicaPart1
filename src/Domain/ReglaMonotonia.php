<?php

declare(strict_types=1);

namespace App\Domain;

final class ReglaMonotonia
{
    /**
     * Se considera vuelta de contador cuando el valor previo estaba en el
     * último tramo del contador y el posterior en el primero.
     * Umbrales por defecto: previo >= 90 % del tope y posterior <= 10 %.
     * Son una suposición no confirmada por negocio.
     */
    public function __construct(
        private readonly float $minimoAnterior = 0.9,
        private readonly float $maximoPosterior = 0.1,
    ) {
    }

    /**
     * @param int|null $anterior  última lectura real previa a la fecha (por CUPS)
     * @param int|null $siguiente primera lectura real posterior a la fecha (por CUPS)
     */
    public function evaluar(int $nueva, ?int $anterior, ?int $siguiente, int $digitosContador): Evaluacion
    {
        $tope = 10 ** $digitosContador;
        $avisos = [];

        if ($anterior !== null && $nueva < $anterior) {
            if (!$this->esVuelta($anterior, $nueva, $tope)) {
                return Evaluacion::rechazada(MotivoRechazo::LecturaDecreciente);
            }
            $avisos[] = Aviso::VueltaDeContador;
        }

        if ($siguiente !== null && $nueva > $siguiente) {
            if (!$this->esVuelta($nueva, $siguiente, $tope)) {
                return Evaluacion::rechazada(MotivoRechazo::LecturaSuperiorAPosterior);
            }
            if (!in_array(Aviso::VueltaDeContador, $avisos, true)) {
                $avisos[] = Aviso::VueltaDeContador;
            }
        }

        return Evaluacion::aceptada($avisos);
    }

    private function esVuelta(int $antes, int $despues, int $tope): bool
    {
        return $antes >= $tope * $this->minimoAnterior
            && $despues <= $tope * $this->maximoPosterior;
    }
}