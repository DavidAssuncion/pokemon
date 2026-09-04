<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\Pokemon;
use App\Models\Reclutado;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\Shared\Domain\NivelHelper;

/**
 * Orquesta el combate automático 1v1 para una exploración, reutilizando el
 * motor de batalla real (src/Battle/Domain/). Sin usar sesión: cada combate
 * es una instancia independiente de AgregadoBatalla en memoria.
 */
class CombateExploracion
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
    ) {
    }

    /**
     * Combate 1v1 real entre un reclutado del jugador y un pokémon salvaje.
     * Construye los DatosPokemonBatalla desde los modelos Eloquent y delega
     * en combatirDatos().
     *
     * @param  array{hp: float, barrera_fisica: float, barrera_especial: float}|null  $estadoInicial
     *        Estado inicial del explorador (hp/barreras) para poder reanudar
     *        combates secuenciales (emboscada). null = comienza al 100 %.
     * @return array{victoria: bool, hp_final: float, barrera_fisica_final: float, barrera_especial_final: float, log: array, hp_max: float, barrera_fisica_max: float, barrera_especial_max: float}
     */
    public function combatir(
        Reclutado $reclutado,
        Pokemon $salvaje,
        int $nivelRival,
        ?array $estadoInicial = null,
    ): array {
        $reclutado->loadMissing('pokemon.stats', 'pokemon.types');

        $nivelPokemon = NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $item = $reclutado->obj_equipados[0] ?? null;

        $explorador = $this->mapeador->desdePokemon(
            pokemon: $reclutado->pokemon,
            id: (string) $reclutado->id,
            nombre: $reclutado->nombre ?? $reclutado->pokemon->name,
            posicion: Posicion::VANGUARDIA,
            shiny: $reclutado->es_shiny,
            item: $item,
            nivel: $nivelPokemon,
        );

        $salvaje->loadMissing('stats', 'types');
        $salvajeDatos = $this->mapeador->desdePokemon(
            pokemon: $salvaje,
            id: 'salvaje_'.$salvaje->id,
            nombre: $salvaje->name,
            posicion: Posicion::VANGUARDIA,
            nivel: $nivelRival,
        );

        return $this->combatirDatos($explorador, $salvajeDatos, $estadoInicial);
    }

    /**
     * Combate 1v1 puro a partir de DatosPokemonBatalla (sin Eloquent).
     * Testeable en unit tests sin BD.
     *
     * @param  array{hp: float, barrera_fisica: float, barrera_especial: float}|null  $estadoInicial
     * @return array{victoria: bool, hp_final: float, barrera_fisica_final: float, barrera_especial_final: float, log: array, hp_max: float, barrera_fisica_max: float, barrera_especial_max: float}
     */
    public function combatirDatos(
        DatosPokemonBatalla $explorador,
        DatosPokemonBatalla $salvaje,
        ?array $estadoInicial = null,
    ): array {
        $team1 = EquipoBatalla::fromData([$explorador], 'Explorador');
        $team2 = EquipoBatalla::fromData([$salvaje], 'Salvaje');

        // Aplicar estado inicial si se proporciona (para combates secuenciales)
        $combatiente = $team1->combatants()[0];
        if ($estadoInicial !== null) {
            $combatiente->setHpActual((float) ($estadoInicial['hp'] ?? $combatiente->hpActual()));
            $combatiente->setDefensaHpActual((float) ($estadoInicial['barrera_fisica'] ?? $combatiente->defensaHpActual()));
            $combatiente->setDefensaEspHpActual((float) ($estadoInicial['barrera_especial'] ?? $combatiente->defensaEspHpActual()));
        }

        $batalla = new AgregadoBatalla($team1, $team2);
        $batalla->triggerBattleStartEffects();
        $log = $batalla->ejecutarBatalla();

        $hpMax = $combatiente->pokemon()->battleStats()->hp;
        $barreraFisicaMax = $combatiente->pokemon()->battleStats()->defenseHp;
        $barreraEspecialMax = $combatiente->pokemon()->battleStats()->spDefenseHp;

        return [
            'victoria' => $team2->todosDebilitados(),
            'hp_final' => $combatiente->hpActual(),
            'barrera_fisica_final' => $combatiente->defensaHpActual(),
            'barrera_especial_final' => $combatiente->defensaEspHpActual(),
            'hp_max' => $hpMax,
            'barrera_fisica_max' => $barreraFisicaMax,
            'barrera_especial_max' => $barreraEspecialMax,
            'log' => $log,
        ];
    }
}
