<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\EquipoBatalla;

/**
 * Secuencia compartida de creación de combates 5v5: construye los dos
 * EquipoBatalla desde los datos ya generados, dispara los efectos de inicio
 * y persiste la batalla + meta en la sesión del navegador.
 *
 * Centraliza el "tail" idéntico de IniciarCombateRuta, IniciarCombateEntrenador,
 * IniciarCombateGimnasio e IniciarCombateMazmorra (DRY).
 */
final class CreadorBatallaSesion
{
    public function __construct(
        private readonly BattleSessionService $battleSession,
    ) {
    }

    /**
     * @param  list<DatosPokemonBatalla>  $datosJugador  team1
     * @param  list<DatosPokemonBatalla>  $datosRival    team2
     * @param  array<string, mixed>  $meta  Metadatos de la batalla (tipo, ids…)
     */
    public function crearYGuardar(
        array $datosJugador,
        string $nombreJugador,
        array $datosRival,
        string $nombreRival,
        string $prefijoId,
        array $meta,
    ): string {
        $team1 = EquipoBatalla::fromData($datosJugador, $nombreJugador);
        $team2 = EquipoBatalla::fromData($datosRival, $nombreRival);

        $batalla = new AgregadoBatalla($team1, $team2);
        $batalla->triggerBattleStartEffects();

        $battleId = 'battle_'.$prefijoId.'_'.uniqid();

        $this->battleSession->guardar($battleId, $batalla);
        $this->battleSession->guardarMeta($battleId, $meta);

        return $battleId;
    }
}