<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contrato;
use App\Domain\ContratoRepository;
use App\Domain\Evaluacion;
use App\Domain\Lectura;
use App\Domain\LecturaRepository;
use App\Domain\MotivoRechazo;
use App\Domain\ReglaEstadoContrato;
use App\Domain\ReglaFecha;
use App\Domain\ReglaMonotonia;
use App\Domain\ReglaRangoContador;
use App\Domain\ResolutorContrato;
use App\Domain\TipoLectura;
use RuntimeException;

final class RegistrarLectura
{
    public function __construct(
        private readonly ContratoRepository $contratos,
        private readonly LecturaRepository $lecturas,
        private readonly ResolutorContrato $resolutor,
        private readonly ReglaFecha $reglaFecha,
        private readonly ReglaEstadoContrato $reglaEstado,
        private readonly ReglaRangoContador $reglaRango,
        private readonly ReglaMonotonia $reglaMonotonia,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(SolicitudLectura $solicitud): ResultadoRegistro
    {
        $ahora = $this->reloj->ahora();

        $motivo = $this->reglaFecha->evaluar($solicitud->fecha, $ahora);
        if ($motivo !== null) {
            return ResultadoRegistro::rechazada($motivo);
        }

        $delCups = $this->contratos->contratosPorCups($solicitud->cups);
        if ($delCups === []) {
            return ResultadoRegistro::rechazada(MotivoRechazo::CupsDesconocido);
        }

        $contrato = $this->resolutor->resolver($delCups, $solicitud->fecha);
        if ($contrato === null) {
            return ResultadoRegistro::rechazada(MotivoRechazo::FueraDeVigencia);
        }

        // D3: un reintento de algo ya guardado se resuelve ANTES de aplicar
        // las reglas de negocio.
        $existente = $this->lecturas->buscarPorClave($contrato->id, $solicitud->fecha, $solicitud->tipo);
        if ($existente !== null) {
            return $this->resolverExistente($existente, $solicitud);
        }

        $motivo = $this->reglaEstado->evaluar($contrato)
            ?? $this->reglaRango->evaluar($solicitud->lecturaKwh, $contrato->digitosContador);
        if ($motivo !== null) {
            return ResultadoRegistro::rechazada($motivo);
        }

        $evaluacion = $this->evaluarMonotonia($solicitud, $contrato);
        if ($evaluacion->rechazo !== null) {
            return ResultadoRegistro::rechazada($evaluacion->rechazo);
        }

        $id = $this->lecturas->insertar(new Lectura(
            null,
            $contrato->id,
            $solicitud->fecha,
            $solicitud->lecturaKwh,
            $solicitud->tipo,
            $ahora,
        ));

        if ($id !== null) {
            return ResultadoRegistro::creada(
                new Lectura($id, $contrato->id, $solicitud->fecha, $solicitud->lecturaKwh, $solicitud->tipo, $ahora),
                $evaluacion->avisos,
            );
        }

        // Carrera: otra petición idéntica insertó entre nuestra comprobación y
        // nuestro INSERT. El índice único nos ha frenado: releemos y decidimos.
        $existente = $this->lecturas->buscarPorClave($contrato->id, $solicitud->fecha, $solicitud->tipo);
        if ($existente === null) {
            throw new RuntimeException(
                'La base de datos informa de clave duplicada pero la lectura no existe.',
            );
        }

        return $this->resolverExistente($existente, $solicitud);
    }

    private function resolverExistente(Lectura $existente, SolicitudLectura $solicitud): ResultadoRegistro
    {
        return $existente->lecturaKwh === $solicitud->lecturaKwh
            ? ResultadoRegistro::duplicada($existente)
            : ResultadoRegistro::conflicto($existente);
    }

    private function evaluarMonotonia(SolicitudLectura $solicitud, Contrato $contrato): Evaluacion
    {
        $anterior = $this->lecturas->ultimaRealAnterior($solicitud->cups, $solicitud->fecha);

        // Una estimada no se compara con la real posterior: puede regularizarse a la baja.
        $siguiente = $solicitud->tipo === TipoLectura::Real
            ? $this->lecturas->primeraRealPosterior($solicitud->cups, $solicitud->fecha)
            : null;

        return $this->reglaMonotonia->evaluar(
            $solicitud->lecturaKwh,
            $anterior,
            $siguiente,
            $contrato->digitosContador,
        );
    }
}