<?php

declare(strict_types=1);

namespace Tests\Support;

final class ClienteApi
{
    public const BASE = 'http://127.0.0.1:8000';

    private string $clave;

    public function __construct()
    {
        $this->clave = getenv('API_KEY') ?: 'dev-key';
    }

    public function disponible(): bool
    {
        $ch = curl_init(self::BASE . '/api/v1/salud');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
        curl_exec($ch);
        $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $estado === 200;
    }

    /** @return array{estado: int, json: array<string, mixed>} */
    public function enviar(string $cuerpo, bool $conClave = true): array
    {
        return $this->enviarVarios([$cuerpo], $conClave)[0];
    }

    /**
     * Lanza todas las peticiones a la vez con curl_multi.
     *
     * @param list<string> $cuerpos
     * @return list<array{estado: int, json: array<string, mixed>}>
     */
    public function enviarVarios(array $cuerpos, bool $conClave = true): array
    {
        $multi = curl_multi_init();
        $manejadores = [];

        foreach ($cuerpos as $i => $cuerpo) {
            $cabeceras = ['Content-Type: application/json'];
            if ($conClave) {
                $cabeceras[] = 'X-API-Key: ' . $this->clave;
            }

            $ch = curl_init(self::BASE . '/api/v1/lecturas');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $cuerpo,
                CURLOPT_HTTPHEADER => $cabeceras,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            curl_multi_add_handle($multi, $ch);
            $manejadores[$i] = $ch;
        }

        do {
            $estado = curl_multi_exec($multi, $activos);
            if ($activos) {
                curl_multi_select($multi, 1.0);
            }
        } while ($activos && $estado === CURLM_OK);

        $resultados = [];
        foreach ($manejadores as $i => $ch) {
            $json = json_decode((string) curl_multi_getcontent($ch), true);
            $resultados[$i] = [
                'estado' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'json' => is_array($json) ? $json : [],
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $resultados;
    }
}