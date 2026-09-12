<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\AgregadoBatalla;

final class BattleSessionService
{
    /**
     * 9: Combatiente cambió de etapas enteras ('etapas') a
     * MultiplicadoresStats ('multiplicadores' con factores float).
     * La carga acepta payloads v8 (etapas int) vía MultiplicadoresStats::desdeEtapas.
     */
    private const SESSION_VERSION = 9;

    public function crearId(): string
    {
        return 'battle_'.uniqid();
    }

    public function guardar(string $battleId, AgregadoBatalla $battle): void
    {
        session()->put($battleId, self::SESSION_VERSION.'|'.serialize($battle));
    }

    public function cargar(string $battleId): ?AgregadoBatalla
    {
        $data = session($battleId);
        if ($data === null) {
            return null;
        }

        // Formato: "v{version}|{serialized}"
        if (! str_contains($data, '|')) {
            session()->forget($battleId);

            return null;
        }

        [$version, $payload] = explode('|', $data, 2);

        // Compuerta de versión: un payload de una versión anterior (p.ej. v8 con
        // propiedades de array antes del refactor a colecciones) puede lanzar
        // TypeError al reconstruir AgregadoBatalla/EquipoBatalla. Descartar la
        // sesión limpia sin intentar unserialize; el try/catch queda como red de
        // seguridad para payloads corruptos del SESSION_VERSION actual.
        if ((int) $version < self::SESSION_VERSION) {
            session()->forget([$battleId, $battleId.'_meta']);

            return null;
        }

        try {
            /** @var AgregadoBatalla $battle */
            $battle = unserialize($payload);
        } catch (\Throwable $e) {
            // Versión antigua incompatible, limpiar sesión
            session()->forget($battleId);

            return null;
        }

        if (! $battle instanceof AgregadoBatalla) {
            session()->forget($battleId);

            return null;
        }

        return $battle;
    }

    public function limpiar(string $battleId): void
    {
        session()->forget($battleId);
    }

    public function guardarMeta(string $battleId, array $meta): void
    {
        session()->put($battleId.'_meta', $meta);
    }

    public function cargarMeta(string $battleId): ?array
    {
        $meta = session($battleId.'_meta');

        return is_array($meta) ? $meta : null;
    }

    public function limpiarMeta(string $battleId): void
    {
        session()->forget($battleId.'_meta');
    }
}
