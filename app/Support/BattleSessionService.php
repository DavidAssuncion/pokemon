<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\AgregadoBatalla;

final class BattleSessionService
{
    /**
     * 20: Combatiente incorpora estado de objeto (cargas, embargo, reciclaje),
     * supresión/copia de habilidades, sustituto y estado de jefe (fases y
     * puntos débiles). Los payloads v9-v19 cargan con objeto pasivo, sin
     * supresión, sin sustituto y sin jefe.
     * 19: Combatiente incorpora racha de combo y duración de invocación;
     * AgregadoBatalla añade el contador de
     * invocaciones. Las condiciones MARCADO y VINCULO_DANO amplían
     * CondicionVolatil con factor y fracción compartida. Los payloads
     * v9-v18 cargan con medidor 0, combo 0 y sin invocación.
     * 17: Combatiente guarda la prioridad declarada en preparación.
     * 16: MultiplicadoresStats pasa a {base, temporales, turnos}; CambioStat
     * guarda etapas y aplica +30 %/-30 % temporal. Combatiente añade cargas
     * de veneno; AgregadoBatalla añade Trick Room. Los payloads v9-v15 cargan
     * con temporales vacíos y cargas 0.
     * 15: Combatiente incorpora postura, objetivo cubierto, acciones extra y
     * su flag de ronda; AgregadoBatalla guarda la ronda preparada. Los
     * payloads v9-v14 cargan con valores por defecto (atacar, 0 extras).
     * 14: MovimientoBatalla incorpora objetivo, flags y efectos secundarios;
     * Combatiente incorpora condiciones volátiles, último daño y coste de
     * velocidad. Los payloads v9-v13 cargan con valores por defecto.
     * 13: PerfilMovimiento pasa de 'golpes' fijo a 'golpesMin'/'golpesMax'.
     * Los payloads v12 con 'golpes' se migran a min=max en __unserialize.
     * 12: MovimientoBatalla añade PerfilMovimiento (golpes, probabilidad de
     * estado, drenaje/recoil, multiplicadores de escudo/salud, efecto especial)
     * y Combatiente cachea el clima. Los payloads v9-v11 siguen cargando con
     * los valores por defecto.
     * 11: Combatiente dejó de serializar 'velocidadAcumulada' (el motor de
     * turnos pasa a contador de acciones por ronda); los payloads v9/v10 con
     * esa clave siguen cargando (la clave se ignora, best-effort).
     * 10: BattleStats puede llevar ?StatsValue $ivs (propiedad opcional; los
     * payloads v9 sin esa clave siguen siendo válidos: la propiedad se
     * reconstruye en null sin migración).
     * 9: Combatiente cambió de etapas enteras ('etapas') a
     * MultiplicadoresStats ('multiplicadores' con factores float).
     */
    private const SESSION_VERSION = 20;

    /**
     * Versión mínima de payload aceptada por cargar(). SESSION_VERSION (20)
     * y MINIMUM_COMPATIBLE_SESSION_VERSION (9) comparten estructura salvo
     * claves opcionales: objeto/cargas/embargo, habilidad anulada/copiada,
     * sustituto y jefe; 'velocidadAcumulada' (ignorada), BattleStats.$ivs
     * (default null), PerfilMovimiento (default, con migración de 'golpes'),
     * Combatiente.clima (NONE), postura (ATACAR), prioridad declarada (null),
     * cargas/usos (vacíos), retraso (false), extras (0), veneno (0),
     * MultiplicadoresStats temporales (vacíos), Trick Room (0), combo (0),
     * invocación (ninguna) y contador de invocaciones (0).
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
