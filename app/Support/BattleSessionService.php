<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\AgregadoBatalla;

final class BattleSessionService
{
    /**
     * 11: Combatiente dejó de serializar 'velocidadAcumulada' (el motor de
     * turnos pasa a contador de acciones por ronda); los payloads v9/v10 con
     * esa clave siguen cargando (la clave se ignora, best-effort).
     * 10: BattleStats puede llevar ?StatsValue $ivs (propiedad opcional; los
     * payloads v9 sin esa clave siguen siendo válidos: la propiedad se
     * reconstruye en null sin migración).
     * 9: Combatiente cambió de etapas enteras ('etapas') a
     * MultiplicadoresStats ('multiplicadores' con factores float).
     */
    private const SESSION_VERSION = 11;

    /**
     * Versión mínima de payload aceptada por cargar(). SESSION_VERSION (11)
     * y MINIMUM_COMPATIBLE_SESSION_VERSION (9) comparten estructura salvo
     * claves opcionales: 'velocidadAcumulada' (ignorada) y BattleStats.$ivs
     * (default null).
     */
    private const MINIMUM_COMPATIBLE_SESSION_VERSION = 9;

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

        // Versión gate: un payload anterior a MINIMUM_COMPATIBLE_SESSION_VERSION
        // (p.ej. v8 con propiedades de array antes del refactor a colecciones)
        // puede lanzar TypeError al reconstruir AgregadoBatalla/EquipoBatalla.
        // Descartar la sesión limpia sin intentar unserialize; el try/catch
        // queda como red de seguridad para payloads corruptos del
        // SESSION_VERSION actual. Las sesiones v9/v10 siguen cargando
        // (estructura compatible con v11: velocidades acumuladas ignoradas y
        // BattleStats.$ivs opcional).
        if ((int) $version < self::MINIMUM_COMPATIBLE_SESSION_VERSION) {
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
