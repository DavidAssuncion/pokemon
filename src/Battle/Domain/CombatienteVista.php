<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Mapea un Combatiente al array que consume la vista de batalla.
 *
 * Vive en Domain (no en Presentation) para que Combatiente pueda delegar sin
 * invertir la dirección de dependencias del módulo. No usa Eloquent ni HTTP.
 *
 * Además de los datos de dominio, expone los campos derivados que antes se
 * calculaban en Blade (porcentajes, clases CSS, textos de estado/postura y
 * detalle de etapas). La vista sólo pinta el contrato.
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
        $hpPct = self::porcentaje($combatiente->hpActual(), $battleStats->hp);
        $defPct = self::porcentaje($combatiente->defensaHpActual(), $battleStats->defenseHp);
        $spDefPct = self::porcentaje($combatiente->defensaEspHpActual(), $battleStats->spDefenseHp);
        $postura = $combatiente->postura();
        $estado = $combatiente->estado();

        return [
            'refId' => $combatiente->id(),
            'nombre' => $combatiente->nombre(),
            'icon' => self::urlIcono($combatiente),
            'iconoNormal' => self::urlIcono($combatiente, shiny: false),
            'es_shiny' => $combatiente->shiny(),
            'hp' => $combatiente->hpActual(),
            'maxHp' => $battleStats->hp,
            'hpPct' => $hpPct,
            'hpTexto' => (string) ceil($combatiente->hpActual()),
            'maxHpTexto' => (string) ceil($battleStats->hp),
            'hpClass' => self::claseHp($hpPct),
            'hpBaja' => $combatiente->estaVivo() && $hpPct <= 25,
            'hpCritica' => $combatiente->estaVivo() && $hpPct <= 10,
            'defHp' => $combatiente->defensaHpActual(),
            'maxDefHp' => $battleStats->defenseHp,
            'defPct' => $defPct,
            'defTexto' => (string) ceil($combatiente->defensaHpActual()),
            'maxDefTexto' => (string) ceil($battleStats->defenseHp),
            'spDefHp' => $combatiente->defensaEspHpActual(),
            'maxSpDefHp' => $battleStats->spDefenseHp,
            'spDefPct' => $spDefPct,
            'spDefTexto' => (string) ceil($combatiente->defensaEspHpActual()),
            'maxSpDefTexto' => (string) ceil($battleStats->spDefenseHp),
            'posicion' => $combatiente->posicion()->value,
            'alive' => $combatiente->estaVivo(),
            'speed' => $battleStats->speed,
            'effectiveSpeed' => $combatiente->velocidadEfectiva(),
            'status' => $estado->value,
            'statusLabel' => $estado === EstadoPokemon::NONE ? '' : $estado->label(),
            'statusIcono' => self::iconoEstado($estado),
            'statusEmoji' => self::emojiEstado($estado),
            'statusTurns' => $combatiente->turnosEstado(),
            'statusTurnsTexto' => $combatiente->turnosEstado() > 0
                ? ' ('.$combatiente->turnosEstado().' turnos)'
                : '',
            'statusMayor' => $estadoMayor->value,
            'statusMayorTurns' => $combatiente->turnosEstadoMayor(),
            'venenoCargas' => $combatiente->cargasVeneno(),
            'prioridadDeclarada' => $prioridad !== null ? $prioridad->nombre : '',
            'stages' => $combatiente->multiplicadores()->obtenerEtapas()->toArray(),
            'stagesDetalle' => self::stagesDetalle($combatiente->multiplicadores()->obtenerEtapas()->toArray()),
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
            'condicionesBadge' => array_values(array_filter(
                array_map(
                    static fn (CondicionVolatil $condicion): array => [
                        'clave' => $condicion->clave->value,
                        'label' => $condicion->clave->label(),
                        'turnos' => $condicion->turnos,
                    ],
                    $combatiente->condiciones()->all(),
                ),
                static fn (array $condicion): bool => $condicion['clave'] !== 'confusion',
            )),
            'habilidades' => array_map(
                static fn (InterfazEfecto $efecto): string => $efecto->obtenerClave(),
                $combatiente->effects()->all(),
            ),
            'tiposDefensivos' => array_map(
                static fn (TipoPokemon $tipo): int => $tipo->value,
                $combatiente->tiposDefensivos()->toList(),
            ),
            'team' => $teamIdx,
            'claseEquipo' => $teamIdx === 0 ? 'equipo-jugador' : 'equipo-rival',
            'item' => $combatiente->item(),
            'itemNombre' => CatalogoObjetos::nombreDe($combatiente->item()),
            'itemIcono' => CatalogoObjetos::iconoDe($combatiente->item()),
            'itemCargas' => $combatiente->itemCargas(),
            'itemActivo' => $combatiente->itemActivo(),
            'itemBloqueado' => $combatiente->itemBloqueado(),
            'movimientoBloqueado' => $combatiente->movimientoBloqueado(),
            'habilidadAnulada' => $combatiente->habilidadAnulada(),
            'ignoraHabilidadRival' => $combatiente->ignoraHabilidadRival(),
            'esJefe' => $combatiente->esJefe(),
            'jefe' => $combatiente->jefe()?->toArray(),
            'jefePartes' => self::jefePartes($combatiente->jefe()?->toArray()),
            'postura' => $postura->value,
            'posturaLabel' => $postura->label(),
            'posturaCorta' => match ($postura) {
                PosturaCombate::ATACAR => 'ATQ',
                PosturaCombate::GUARDIA => 'GUARDIA',
                PosturaCombate::CUBRIR => 'CUBRE',
            },
            'posturaClase' => match ($postura) {
                PosturaCombate::GUARDIA => 'bg-primary text-white',
                PosturaCombate::CUBRIR => 'bg-success text-white',
                default => 'bg-light border text-secondary',
            },
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

    /**
     * @param  array<string, int>  $stages
     * @return list<array{stat: string, label: string, stage: int, factor: float, porcentaje: int, clase: string, titulo: string, texto: string}>
     */
    private static function stagesDetalle(array $stages): array
    {
        $detalle = [];

        foreach ($stages as $stat => $stage) {
            if ($stage === 0) {
                continue;
            }

            $label = StatClave::tryFrom($stat)?->label() ?? $stat;
            $factor = MultiplicadoresStats::factorDesdeStages($stage);
            $porcentaje = (int) round(($factor - 1) * 100);
            $signo = $porcentaje > 0 ? '+' : '';
            $etapas = abs($stage) === 1 ? 'etapa' : 'etapas';

            $detalle[] = [
                'stat' => $stat,
                'label' => $label,
                'stage' => $stage,
                'factor' => $factor,
                'porcentaje' => $porcentaje,
                'clase' => $stage > 0 ? 'stage-up' : 'stage-down',
                'titulo' => sprintf(
                    '%s %s %d %s (%s%d%%)',
                    $label,
                    $stage > 0 ? 'subió' : 'bajó',
                    abs($stage),
                    $etapas,
                    $signo,
                    $porcentaje,
                ),
                'texto' => sprintf('%s%d%% %s', $signo, $porcentaje, $label),
            ];
        }

        return $detalle;
    }

    /**
     * @param  array<string, mixed>|null  $jefe
     * @return list<array<string, mixed>>
     */
    private static function jefePartes(?array $jefe): array
    {
        $partes = [];

        foreach ($jefe['partes'] ?? [] as $parte) {
            $roto = (bool) ($parte['roto'] ?? false);
            $hp = (float) ($parte['hp'] ?? 0);
            $hpMax = (float) ($parte['hpMax'] ?? 0);

            $partes[] = array_merge($parte, [
                'roto' => $roto,
                'clase' => $roto ? 'bg-secondary text-decoration-line-through' : 'bg-dark',
                'hpTexto' => (string) ceil($hp),
                'hpMaxTexto' => (string) ceil($hpMax),
                'texto' => $roto ? '✖' : (string) ceil($hp),
            ]);
        }

        return $partes;
    }

    private static function porcentaje(float $actual, float $maximo): float
    {
        if ($maximo <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, ($actual / $maximo) * 100));
    }

    private static function claseHp(float $hpPct): string
    {
        if ($hpPct <= 25) {
            return 'bg-hp-low';
        }

        if ($hpPct <= 50) {
            return 'bg-hp-mid';
        }

        return 'bg-hp-high';
    }

    private static function iconoEstado(EstadoPokemon $estado): ?string
    {
        return match ($estado) {
            EstadoPokemon::BURN => '/images/type/fuego.webp',
            EstadoPokemon::POISON, EstadoPokemon::BAD_POISON => '/images/type/veneno.webp',
            EstadoPokemon::PARALYSIS => '/images/type/electrico.webp',
            default => null,
        };
    }

    private static function emojiEstado(EstadoPokemon $estado): ?string
    {
        return match ($estado) {
            EstadoPokemon::SLEEP => '💤',
            EstadoPokemon::FREEZE => '❄',
            EstadoPokemon::CONFUSION => '😵',
            default => null,
        };
    }

    private static function urlIcono(Combatiente $combatiente, bool $shiny = true): string
    {
        $speciesId = $combatiente->speciesId();
        $shiny = $shiny && $combatiente->shiny();

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
