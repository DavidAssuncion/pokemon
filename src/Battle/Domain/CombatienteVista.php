<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Mapea un Combatiente al array que consume la vista de batalla.
 *
 * Vive en Domain (no en Presentation) para que Combatiente pueda delegar sin
 * invertir la dirección de dependencias del módulo. No usa Eloquent ni HTTP.
 */
final class CombatienteVista
{
    /**
     * @return array<string, mixed>
     */
    public static function para(Combatiente $combatiente, int $teamIdx): array
    {
        $battleStats = $combatiente->pokemon()->battleStats();
        $estadoMayor = $combatiente->estadoMayor();
        $prioridad = $combatiente->prioridadDeclarada();

        return [
            'refId' => $combatiente->id(),
            'nombre' => $combatiente->nombre(),
            'icon' => self::urlIcono($combatiente),
            'es_shiny' => $combatiente->shiny(),
            'hp' => $combatiente->hpActual(),
            'maxHp' => $battleStats->hp,
            'defHp' => $combatiente->defensaHpActual(),
            'maxDefHp' => $battleStats->defenseHp,
            'spDefHp' => $combatiente->defensaEspHpActual(),
            'maxSpDefHp' => $battleStats->spDefenseHp,
            'posicion' => $combatiente->posicion()->value,
            'alive' => $combatiente->estaVivo(),
            'speed' => $battleStats->speed,
            'effectiveSpeed' => $combatiente->velocidadEfectiva(),
            'status' => $combatiente->estado()->value,
            'statusTurns' => $combatiente->turnosEstado(),
            'statusMayor' => $estadoMayor->value,
            'statusMayorTurns' => $combatiente->turnosEstadoMayor(),
            'venenoCargas' => $combatiente->cargasVeneno(),
            'prioridadDeclarada' => $prioridad !== null ? $prioridad->nombre : '',
            'stages' => $combatiente->multiplicadores()->obtenerEtapas()->toArray(),
            'buffTurns' => $combatiente->multiplicadores()->turnosTemporales(),
            'condiciones' => array_map(
                static fn (CondicionVolatil $condicion): string => $condicion->clave->value,
                $combatiente->condiciones()->all(),
            ),
            'condicionesDetalle' => array_map(
                static fn (CondicionVolatil $condicion): array => [
                    'clave' => $condicion->clave->value,
                    'label' => $condicion->clave->label(),
                    'turnos' => $condicion->turnos,
                ],
                $combatiente->condiciones()->all(),
            ),
            'habilidades' => array_map(
                static fn (InterfazEfecto $efecto): string => $efecto->obtenerClave(),
                $combatiente->effects()->all(),
            ),
            'tiposDefensivos' => array_map(
                static fn (TipoPokemon $tipo): int => $tipo->value,
                $combatiente->tiposDefensivos()->toList(),
            ),
            'team' => $teamIdx,
            'item' => $combatiente->item(),
            'itemCargas' => $combatiente->itemCargas(),
            'itemActivo' => $combatiente->itemActivo(),
            'itemBloqueado' => $combatiente->itemBloqueado(),
            'movimientoBloqueado' => $combatiente->movimientoBloqueado(),
            'habilidadAnulada' => $combatiente->habilidadAnulada(),
            'ignoraHabilidadRival' => $combatiente->ignoraHabilidadRival(),
            'esJefe' => $combatiente->esJefe(),
            'jefe' => $combatiente->jefe()?->toArray(),
            'postura' => $combatiente->postura()->value,
            'objetivoCubierto' => $combatiente->objetivoCubiertoId(),
            'accionesExtra' => $combatiente->accionesExtra(),
            'turnoRetrasado' => $combatiente->turnoRetrasado(),
            'puedeRetrasar' => ! $combatiente->retrasoUsado(),
            'cargasMovimiento' => $combatiente->cargasMovimientoPorNombre()->toArray(),
            'usosMovimiento' => $combatiente->usosMovimientoPorNombre()->toArray(),
            'combo' => $combatiente->comboMovimiento() !== '' ? $combatiente->combo() : 0,
            'comboMovimiento' => $combatiente->comboMovimiento(),
            'comboMultiplicador' => $combatiente->comboMovimiento() !== ''
                ? $combatiente->comboMultiplicador()
                : 1.0,
            'esInvocacion' => $combatiente->esInvocacion(),
            'turnosInvocacion' => $combatiente->turnosInvocacion(),
            'invocadoPorId' => $combatiente->invocadoPorId(),
        ];
    }

    private static function urlIcono(Combatiente $combatiente): string
    {
        $speciesId = $combatiente->speciesId();
        $shiny = $combatiente->shiny();

        if ($speciesId === 0) {
            return $shiny
                ? '/images/iconos_webp/shiny/0_s.webp'
                : '/images/iconos_webp/0.webp';
        }

        $sufijo = $combatiente->formSuffix() !== '' ? "_{$combatiente->formSuffix()}" : '';

        return $shiny
            ? "/images/iconos_webp/shiny/{$speciesId}{$sufijo}_s.webp"
            : "/images/iconos_webp/{$speciesId}{$sufijo}.webp";
    }
}
