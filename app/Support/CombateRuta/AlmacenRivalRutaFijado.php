<?php

declare(strict_types=1);

namespace App\Support\CombateRuta;

use Illuminate\Contracts\Session\Session;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;

/**
 * Almacén en sesión del rival de ruta fijado al abrir la configuración.
 *
 * El payload serializado queda ligado al usuario, al hábitat y al nivel, y
 * caduca pasada una hora. Cualquier lectura inválida se descarta.
 */
final class AlmacenRivalRutaFijado
{
    private const PREFIJO = 'ruta_preview';

    private const VERSION = 1;

    private const TTL_SEGUNDOS = 3600;

    private const MAXIMO_POR_USUARIO = 5;

    public function __construct(
        private readonly Session $session,
    ) {
    }

    public function obtener(int $userId, string $previewId, int $habitatId, int $nivel): ?RivalRutaFijado
    {
        $clave = $this->clave($userId, $previewId);
        $payload = $this->session->get($clave);

        if (! is_string($payload)) {
            $this->session->forget($clave);

            return null;
        }

        $partes = explode('|', $payload, 6);
        if (count($partes) !== 6) {
            $this->session->forget($clave);

            return null;
        }

        [$version, $habitat, $nivelGuardado, $nivelJugador, $creadoEn, $serializado] = $partes;

        if (
            $version !== 'v'.self::VERSION
            || $habitat !== (string) $habitatId
            || $nivelGuardado !== (string) $nivel
            || ! ctype_digit($nivelJugador)
            || ! ctype_digit($creadoEn)
            || time() - (int) $creadoEn > self::TTL_SEGUNDOS
        ) {
            $this->session->forget($clave);

            return null;
        }

        try {
            $equipo = unserialize($serializado);
        } catch (\Throwable) {
            $this->session->forget($clave);

            return null;
        }

        if (! $equipo instanceof DatosPokemonBatallaCollection) {
            $this->session->forget($clave);

            return null;
        }

        return new RivalRutaFijado(
            previewId: $previewId,
            habitatId: $habitatId,
            nivel: $nivel,
            nivelJugador: (int) $nivelJugador,
            creadoEn: (int) $creadoEn,
            equipo: $equipo,
        );
    }

    public function fijar(
        int $userId,
        int $habitatId,
        int $nivel,
        int $nivelJugador,
        DatosPokemonBatallaCollection $equipo,
    ): RivalRutaFijado {
        $previewId = bin2hex(random_bytes(16));
        $creadoEn = time();

        $payload = implode('|', [
            'v'.self::VERSION,
            (string) $habitatId,
            (string) $nivel,
            (string) $nivelJugador,
            (string) $creadoEn,
            serialize($equipo),
        ]);

        $this->session->put($this->clave($userId, $previewId), $payload);

        $this->podar($userId);

        return new RivalRutaFijado(
            previewId: $previewId,
            habitatId: $habitatId,
            nivel: $nivel,
            nivelJugador: $nivelJugador,
            creadoEn: $creadoEn,
            equipo: $equipo,
        );
    }

    public function limpiar(int $userId, string $previewId): void
    {
        $this->session->forget($this->clave($userId, $previewId));
    }

    private function clave(int $userId, string $previewId): string
    {
        return self::PREFIJO.':'.$userId.':'.$previewId;
    }

    /**
     * Purga snapshots caducados o corruptos del usuario y limita cuántos
     * previews vigentes puede conservar, descartando los más antiguos.
     */
    private function podar(int $userId): void
    {
        $prefijo = self::PREFIJO.':'.$userId.':';
        $vigentes = [];

        foreach (array_keys($this->session->all()) as $clave) {
            if (! is_string($clave) || ! str_starts_with($clave, $prefijo)) {
                continue;
            }

            $payload = $this->session->get($clave);
            if (! is_string($payload)) {
                $this->session->forget($clave);

                continue;
            }

            $partes = explode('|', $payload, 6);
            if (count($partes) !== 6) {
                $this->session->forget($clave);

                continue;
            }

            [$version, $habitat, $nivel, $nivelJugador, $creadoEn] = $partes;

            if (
                $version !== 'v'.self::VERSION
                || ! ctype_digit($habitat)
                || ! ctype_digit($nivel)
                || ! ctype_digit($nivelJugador)
                || ! ctype_digit($creadoEn)
                || time() - (int) $creadoEn > self::TTL_SEGUNDOS
            ) {
                $this->session->forget($clave);

                continue;
            }

            $vigentes[$clave] = (int) $creadoEn;
        }

        if (count($vigentes) <= self::MAXIMO_POR_USUARIO) {
            return;
        }

        asort($vigentes);

        $sobrantes = count($vigentes) - self::MAXIMO_POR_USUARIO;
        foreach (array_slice(array_keys($vigentes), 0, $sobrantes) as $clave) {
            $this->session->forget($clave);
        }
    }
}
