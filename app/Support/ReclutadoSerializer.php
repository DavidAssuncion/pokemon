<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Ability;
use App\Models\Move;
use App\Models\PlayerMoveUnlock;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\TypePathNode;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\CalculadorCP;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Serializador compartido de Reclutado para los endpoints de listado
 * (PlayerController, ReclutadoController). Preserva el contrato existente
 * de /equipos.
 *
 * Importante para rendimiento: NO se usa `$reclutado->toArray()` porque
 * arrastra todas las relaciones eager-loaded (incluido el catálogo completo de
 * movimientos con sus casts JSON), multiplicando por ~3 el payload y el CPU de
 * /equipos. El payload se construye explícitamente con las claves del contrato.
 */
final class ReclutadoSerializer
{
    /**
     * @param  int|null  $nivelUsuario  Nivel del usuario autenticado (B2); si es
     *                                  null se usa el nivel del reclutado (fallback).
     * @param  bool  $incluirMovimientosDisponibles  El listado de /equipos carga
     *                                               esta clave bajo demanda en el modal;
     *                                               el resto de endpoints la incluye.
     * @return array<string, mixed>
     */
    public static function serializar(
        Reclutado $reclutado,
        ?int $nivelUsuario = null,
        bool $incluirMovimientosDisponibles = true,
    ): array {
        $pokemon = $reclutado->pokemon;
        $expTotal = $reclutado->exp->total();
        $nivel = $nivelUsuario ?? NivelHelper::nivelDesdeExperiencia($expTotal);
        $movimientosSeleccionados = is_array($reclutado->movimientos)
            ? array_values($reclutado->movimientos)
            : [];

        return [
            'id' => $reclutado->id,
            'nombre' => $reclutado->nombre ?? $pokemon?->name ?? 'Desconocido',
            'pokemon_id' => $reclutado->pokemon_id,
            'exp' => $reclutado->exp->toArray(),
            'es_shiny' => (bool) $reclutado->es_shiny,
            'obj_equipados' => $reclutado->obj_equipados ?? [],
            'movimientos' => $movimientosSeleccionados,
            'created_at' => $reclutado->created_at?->toJSON(),
            'updated_at' => $reclutado->updated_at?->toJSON(),
            'user_id' => $reclutado->user_id,
            'behavior' => $reclutado->behavior,
            'evs' => $reclutado->evs->toArray(),
            'ivs' => $reclutado->ivs->toArray(),
            'habilidad_activa_id' => $reclutado->habilidad_activa_id,
            'pokemon' => self::pokemonDe($pokemon),
            'nivel' => $nivel,
            'exp_total' => $expTotal,
            'base_experience' => $pokemon?->base_experience,
            'rol' => $reclutado->rol()->value,
            'stats' => self::statsDe($pokemon),
            'stats_calculados' => self::statsCalculados($reclutado, $nivelUsuario),
            'cp' => self::cpDe($reclutado, $nivelUsuario),
            'habilidades' => self::nombresDeHabilidades($pokemon),
            'movimientos_pokemon' => self::nombresDeMovimientos($pokemon),
            'movimientos_seleccionados' => $movimientosSeleccionados,
            'habilidades_disponibles' => self::habilidadesDisponibles($pokemon),
            // Subconjunto mínimo para el hover de la tarjeta sin enviar el
            // catálogo completo de movimientos en el listado.
            'movimientos_seleccionados_detalle' => self::movimientosSeleccionadosDetalle(
                $pokemon,
                $movimientosSeleccionados,
                $nivel,
            ),
            'movimientos_disponibles' => $incluirMovimientosDisponibles
                ? self::movimientosDisponibles($reclutado, $nivelUsuario)
                : [],
        ];
    }

    /**
     * Serializa una colección de reclutados y la devuelve ordenada por CP
     * descendente. El orden forma parte del contrato del backend para que el
     * frontend no replique lógica de negocio.
     *
     * @param  iterable<int, Reclutado>  $reclutados
     * @return list<array<string, mixed>>
     */
    public static function serializarColeccion(
        iterable $reclutados,
        ?int $nivelUsuario = null,
        bool $incluirMovimientosDisponibles = true,
    ): array {
        return collect($reclutados)
            ->map(fn (Reclutado $reclutado): array => self::serializar(
                $reclutado,
                $nivelUsuario,
                $incluirMovimientosDisponibles,
            ))
            ->sortByDesc(static fn (array $reclutado): int => (int) ($reclutado['cp'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * Stats finales de combate al nivel indicado, con el desglose del aporte
     * de base, IV y EV. La fórmula vive únicamente en BattleStats.
     *
     * @return array<string, array{base: int, iv: int, ev: int, total: int}>
     */
    public static function statsCalculados(Reclutado $reclutado, ?int $nivelUsuario = null): array
    {
        $base = self::statsBaseValue($reclutado->pokemon);
        if ($base === null) {
            return [];
        }

        $nivel = $nivelUsuario ?? NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $ceros = StatsValue::ceros();
        $sinAportes = new BattleStats($base, $ceros, $nivel, $ceros);
        $conIvs = new BattleStats($base, $ceros, $nivel, $reclutado->ivs);
        $total = new BattleStats($base, $reclutado->evs, $nivel, $reclutado->ivs);

        return [
            'hp' => self::desgloseStat($sinAportes->hp, $conIvs->hp, $total->hp),
            'atk' => self::desgloseStat($sinAportes->attack, $conIvs->attack, $total->attack),
            'def' => self::desgloseStat($sinAportes->defense, $conIvs->defense, $total->defense),
            'spAtk' => self::desgloseStat($sinAportes->spAtk, $conIvs->spAtk, $total->spAtk),
            'spDef' => self::desgloseStat($sinAportes->spDef, $conIvs->spDef, $total->spDef),
            'speed' => self::desgloseStat($sinAportes->speed, $conIvs->speed, $total->speed),
        ];
    }

    /**
     * Catálogo completo de movimientos de la especie con el flag `desbloqueado`
     * calculado para el nivel indicado. Público para el endpoint bajo demanda
     * del modal de /equipos.
     *
     * @return list<array<string, mixed>>
     */
    public static function movimientosDisponibles(Reclutado $reclutado, ?int $nivelUsuario = null): array
    {
        $pokemon = $reclutado->pokemon;
        if ($pokemon === null) {
            return [];
        }

        $nivel = $nivelUsuario ?? NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $userId = auth()->id();

        $desbloqueadas = $userId === null
            ? []
            : array_fill_keys(
                PlayerMoveUnlock::query()
                    ->withoutGlobalScope(PlayerMoveUnlock::USER_SCOPE_NAME)
                    ->where('user_id', (int) $userId)
                    ->pluck('move_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
                true,
            );

        $sendas = TypePathNode::query()
            ->whereIn('move_id', $pokemon->moves->pluck('id'))
            ->get()
            ->keyBy('move_id');

        $prioridadOrigen = ['nivel' => 0, 'senda' => 1];

        return $pokemon->moves
            ->map(static fn (Move $move): array => self::formatearMovimiento(
                $move,
                $nivel,
                $desbloqueadas,
                $sendas->all(),
            ))
            ->sortBy([
                static fn (array $a, array $b): int => $prioridadOrigen[$a['origen']] <=> $prioridadOrigen[$b['origen']],
                static fn (array $a, array $b): int => self::nivelOrden($a) <=> self::nivelOrden($b),
                static fn (array $a, array $b): int => $a['type_id'] <=> $b['type_id'],
                static fn (array $a, array $b): int => self::ordenCategoria($a['category']) <=> self::ordenCategoria($b['category']),
                static fn (array $a, array $b): int => $b['base_power'] <=> $a['base_power'],
                static fn (array $a, array $b): int => $a['name'] <=> $b['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * Proyección compacta de la especie. Se omiten `moves` y `abilities`
     * anidados: ya viajan como claves de primer nivel y su serialización
     * completa es el principal coste de /equipos.
     *
     * @return array<string, mixed>|null
     */
    private static function pokemonDe(?Pokemon $pokemon): ?array
    {
        if ($pokemon === null) {
            return null;
        }

        return [
            'id' => $pokemon->id,
            'name' => $pokemon->name,
            'species_id' => $pokemon->species_id,
            'capture_rate' => $pokemon->capture_rate,
            'base_experience' => $pokemon->base_experience,
            'height' => $pokemon->height,
            'weight' => $pokemon->weight,
            'hatch' => $pokemon->hatch,
            'evolution_chain_id' => $pokemon->evolution_chain_id,
            'types' => self::tiposDe($pokemon),
            'stats' => self::statsDe($pokemon),
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    private static function movimientosSeleccionadosDetalle(?Pokemon $pokemon, array $slugs, int $nivel): array
    {
        if ($pokemon === null || $slugs === []) {
            return [];
        }

        $porSlug = $pokemon->moves->keyBy('slug');
        $detalle = [];

        foreach ($slugs as $slug) {
            $move = $porSlug->get($slug);
            if ($move !== null) {
                $detalle[] = self::formatearMovimiento($move, $nivel);
            }
        }

        return $detalle;
    }

    /**
     * @param  array<int, true>|null  $desbloqueadas  null = loadout ya validado
     * @param  array<int, TypePathNode>|null  $sendas
     * @return array<string, mixed>
     */
    private static function formatearMovimiento(
        Move $move,
        int $nivel,
        ?array $desbloqueadas = null,
        ?array $sendas = null,
    ): array {
        $source = $move->pivot->source ?? 'custom';
        $esNivel = $source === 'level-up';
        $nodo = $sendas[(int) $move->id] ?? null;
        $nivelSenda = $nodo instanceof TypePathNode ? (int) $nodo->required_level : null;
        $costeSenda = $nivelSenda !== null ? SendaCostes::coste($nivelSenda) : null;
        $desbloqueadoPorSenda = $desbloqueadas === null || isset($desbloqueadas[(int) $move->id]);
        $nivelRequerido = $esNivel ? $move->nivelRequeridoEfectivo() : null;
        $desbloqueadoPorNivel = $esNivel
            && $nivelRequerido !== null
            && $nivelRequerido <= $nivel;
        $desbloqueado = $esNivel
            ? ($desbloqueadoPorNivel || $desbloqueadoPorSenda)
            : $desbloqueadoPorSenda;
        $bloqueadoPorNivel = $esNivel
            && ! $desbloqueadoPorSenda
            && $nivelRequerido !== null
            && $nivelRequerido > $nivel;

        return [
            'slug' => $move->slug,
            'name' => $move->name,
            'description' => $move->description ?? '',
            'type_id' => $move->type_id,
            'tipo_nombre' => TipoEnum::fromId($move->type_id)?->label(),
            'tipo_slug' => TipoPokemon::tryFrom($move->type_id)?->slug(),
            'base_power' => $move->base_power,
            'nivel_requerido' => $nivelRequerido,
            'nivel_senda' => $nivelSenda,
            'coste_senda' => $costeSenda,
            'desbloqueado' => $desbloqueado,
            'bloqueado_por_nivel' => $bloqueadoPorNivel,
            'senda_desbloqueada' => $desbloqueadoPorSenda,
            'origen' => $esNivel ? 'nivel' : 'senda',
            'category' => $move->category,
        ];
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    private static function nivelOrden(array $movimiento): int
    {
        return $movimiento['origen'] === 'nivel'
            ? (int) ($movimiento['nivel_requerido'] ?? 0)
            : (int) ($movimiento['nivel_senda'] ?? 0);
    }

    /**
     * Orden de presentación de categorías de movimiento: físico, especial y estado.
     */
    private static function ordenCategoria(?string $categoria): int
    {
        return match (strtolower((string) $categoria)) {
            'fisico', 'físico' => 0,
            'especial' => 1,
            'estado' => 2,
            default => 3,
        };
    }

    /**
     * @return list<array{name: string, value: int}>
     */
    private static function statsDe(?Pokemon $pokemon): array
    {
        if ($pokemon === null) {
            return [];
        }

        return $pokemon->stats
            ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
            ->map(fn (PokemonStat $stat): array => [
                'name' => $stat->stat->label(),
                'value' => $stat->base_stat,
            ])
            ->values()
            ->all();
    }

    /**
     * Combat Power del reclutado (B2): BattleStats al NIVEL DEL USUARIO con los
     * EVs reales del reclutado como 2º argumento de CalculadorCP::calcular.
     * Si no se recibe nivelUsuario, se cae al nivel del reclutado (fallback que
     * mantiene el contrato de ReclutadoSerializerCPTest). Si el reclutado no
     * tiene pokémon o stats, devuelve 0.
     */
    private static function cpDe(Reclutado $reclutado, ?int $nivelUsuario = null): int
    {
        $base = self::statsBaseValue($reclutado->pokemon);
        if ($base === null) {
            return 0;
        }

        $nivel = $nivelUsuario ?? NivelHelper::nivelDesdeExperiencia($reclutado->exp->total());
        $stats = new BattleStats(
            stats: $base,
            evs: $reclutado->evs,
            nivel: $nivel,
            ivs: $reclutado->ivs,
        );

        $evTotal = (int) floor(array_sum($reclutado->evs->toArray()));

        return CalculadorCP::calcular($stats, $evTotal);
    }

    private static function statsBaseValue(?Pokemon $pokemon): ?StatsValue
    {
        if ($pokemon === null || $pokemon->stats->isEmpty()) {
            return null;
        }

        $base = ['hp' => 0.0, 'atk' => 0.0, 'def' => 0.0, 'spAtk' => 0.0, 'spDef' => 0.0, 'speed' => 0.0];

        foreach ($pokemon->stats as $stat) {
            $clave = match ($stat->stat) {
                StatEnum::HP => 'hp',
                StatEnum::ATTACK => 'atk',
                StatEnum::DEFENSE => 'def',
                StatEnum::SPECIAL_ATTACK => 'spAtk',
                StatEnum::SPECIAL_DEFENSE => 'spDef',
                StatEnum::SPEED => 'speed',
            };
            $base[$clave] = (float) $stat->base_stat;
        }

        return new StatsValue(
            hp: $base['hp'],
            attack: $base['atk'],
            defense: $base['def'],
            spAtk: $base['spAtk'],
            spDef: $base['spDef'],
            speed: $base['speed'],
        );
    }

    /**
     * @return array{base: int, iv: int, ev: int, total: int}
     */
    private static function desgloseStat(float $sinAportes, float $conIvs, float $total): array
    {
        $base = (int) $sinAportes;
        $conIvs = (int) $conIvs;
        $total = (int) $total;

        return [
            'base' => $base,
            'iv' => max(0, $conIvs - $base),
            'ev' => max(0, $total - $conIvs),
            'total' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tiposDe(Pokemon $pokemon): array
    {
        return $pokemon->types
            ->map(fn (PokemonType $tipo): array => $tipo->toArray() + ['tipo_nombre' => $tipo->tipo_nombre])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function nombresDeHabilidades(?Pokemon $pokemon): array
    {
        if ($pokemon === null) {
            return [];
        }

        return $pokemon->abilities
            ->map(static fn (Ability $ability): string => $ability->name)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function nombresDeMovimientos(?Pokemon $pokemon): array
    {
        if ($pokemon === null) {
            return [];
        }

        return $pokemon->moves
            ->map(static fn (Move $move): string => $move->name)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    private static function habilidadesDisponibles(?Pokemon $pokemon): array
    {
        if ($pokemon === null) {
            return [];
        }

        return $pokemon->abilities
            ->map(static fn (Ability $ability): array => [
                'id' => $ability->id,
                'name' => $ability->name,
                'description' => $ability->effect ?? '',
            ])
            ->values()
            ->all();
    }
}
