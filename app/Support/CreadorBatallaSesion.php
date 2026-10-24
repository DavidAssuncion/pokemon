<?php

declare(strict_types=1);

namespace App\Support;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\EquipoBatalla;

/**
 * Secuencia compartida de creación de combates 3v3: construye los dos
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
        private readonly FabricaEfectos $fabricaEfectos,
    ) {
    }

    /**
     * @param  DatosPokemonBatallaCollection  $datosJugador  team1
     * @param  DatosPokemonBatallaCollection  $datosRival    team2
     * @param  array<string, mixed>  $meta  Metadatos de la batalla (tipo, ids…)
     */
    public function crearYGuardar(
        DatosPokemonBatallaCollection $datosJugador,
        string $nombreJugador,
        DatosPokemonBatallaCollection $datosRival,
        string $nombreRival,
        string $prefijoId,
        array $meta,
    ): string {
        $team1 = EquipoBatalla::fromData($datosJugador, $nombreJugador, $this->fabricaEfectos);
        $team2 = EquipoBatalla::fromData($datosRival, $nombreRival, $this->fabricaEfectos);

        $batalla = new AgregadoBatalla($team1, $team2);
        $batalla->triggerBattleStartEffects();

        $battleId = 'battle_'.$prefijoId.'_'.uniqid();

        $this->battleSession->guardar($battleId, $batalla);
        $this->battleSession->guardarMeta($battleId, $meta);

        return $battleId;
    }
}
