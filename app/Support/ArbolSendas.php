<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TipoEnum;
use App\Models\Move;
use App\Models\PlayerInventory;
use App\Models\PlayerMoveUnlock;
use App\Models\PlayerTypePath;
use App\Models\TypePathNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Sendas\BeneficiosSenda;
use Src\Shared\Domain\Sendas\NivelSenda;
use Src\Shared\Domain\SlugTipo;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Árbol de movimientos desbloqueables por tipo (Sendas).
 *
 * Un nodo pertenece a una Senda de tipo y guarda su tramo original en
 * `required_level` (1-5). El nivel visible se comprime a 1-3 y el coste se
 * resuelve con SendaCostes a partir del tramo. Los tramos 4-5 son maestrías
 * que se desbloquean en el nivel visible 3. El desbloqueo es de cuenta: una vez
 * desbloqueado, el movimiento queda disponible para todos los Pokémon
 * compatibles, sin comprobar el nivel del Pokémon.
 */
final class ArbolSendas
{
    /** @var list<string> */
    private const CATEGORIAS = ['fisico', 'especial', 'estado'];

    /**
     * Resumen de las 18 Sendas para la portada /sendas.
     *
     * Cada tipo incluye `beneficios` con los porcentajes del nivel actual
     * (mismo shape que devuelve {@see self::beneficiosDe()}).
     *
     * @return list<array<string, mixed>>
     */
    public static function resumen(int $userId): array
    {
        $totales = DB::table('type_path_nodes')
            ->selectRaw('type_id, count(*) as total')
            ->groupBy('type_id')
            ->pluck('total', 'type_id');

        $desbloqueados = DB::table('type_path_nodes as nodos')
            ->join('player_move_unlocks as desbloqueos', 'desbloqueos.move_id', '=', 'nodos.move_id')
            ->where('desbloqueos.user_id', $userId)
            ->selectRaw('nodos.type_id, count(*) as total')
            ->groupBy('nodos.type_id')
            ->pluck('total', 'type_id');

        $paths = PlayerTypePath::query()
            ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('type_id');

        $caramelos = self::caramelosPorTipo($userId);

        $resultado = [];
        foreach (TipoEnum::cases() as $tipo) {
            $path = $paths->get($tipo->value);
            $exp = (int) ($path?->exp ?? 0);
            $progreso = SendaProgreso::resumen($exp);

            $resultado[] = array_merge(
                self::tipoBase($tipo),
                $progreso,
                [
                    'beneficios' => self::beneficiosDe($progreso['nivel']),
                    'total_nodos' => (int) ($totales[$tipo->value] ?? 0),
                    'desbloqueados' => (int) ($desbloqueados[$tipo->value] ?? 0),
                    'caramelos' => (int) ($caramelos[$tipo->value] ?? 0),
                ],
            );
        }

        return $resultado;
    }

    /**
     * Árbol completo de una Senda.
     *
     * El payload incluye `beneficios` con los porcentajes del nivel actual y
     * `siguiente_nivel` / `beneficios_siguiente` con los del nivel siguiente.
     * El nivel siguiente tiene tope en el máximo: en el nivel máximo ambos
     * bloques coinciden con los del nivel actual.
     *
     * @return array<string, mixed>
     */
    public static function para(int $userId, TipoEnum $tipo): array
    {
        $nodos = TypePathNode::query()
            ->with('move')
            ->where('type_id', $tipo->value)
            ->orderBy('category')
            ->orderBy('required_level')
            ->orderBy('sort_order')
            ->get();

        $moveIds = $nodos
            ->pluck('move_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $desbloqueados = self::desbloqueados($userId, $moveIds);
        $caramelos = (int) (self::caramelosPorTipo($userId)[$tipo->value] ?? 0);
        $path = PlayerTypePath::query()
            ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->where('type_id', $tipo->value)
            ->first();

        $progreso = SendaProgreso::resumen((int) ($path?->exp ?? 0));
        $nivelActual = $progreso['nivel'];
        $siguienteNivel = min(SendaProgreso::NIVEL_MAXIMO, $nivelActual + 1);

        $ramas = [
            'fisico' => [],
            'especial' => [],
            'estado' => [],
        ];
        $totalDesbloqueados = 0;

        foreach ($nodos as $nodo) {
            $move = $nodo->move;
            if ($move === null) {
                continue;
            }

            $categoria = in_array($nodo->category, self::CATEGORIAS, true)
                ? $nodo->category
                : 'estado';
            $tramo = max(1, min(5, (int) $nodo->required_level));
            $nivelRequerido = min(3, $tramo);
            $coste = SendaCostes::coste($tramo);
            $estaDesbloqueado = isset($desbloqueados[(int) $nodo->move_id]);
            $nivelVisibleJugador = min(3, (int) $progreso['nivel']);
            $disponible = $nivelVisibleJugador >= $nivelRequerido;

            $ramas[$categoria][] = [
                'slug' => (string) $move->slug,
                'name' => (string) $move->name,
                'description' => (string) ($move->description ?? ''),
                'type_id' => (int) $move->type_id,
                'tipo_nombre' => $tipo->label(),
                'tipo_slug' => self::tipoBase($tipo)['slug'],
                'category' => $categoria,
                'base_power' => (int) $move->base_power,
                'accuracy' => $move->accuracy,
                'nivel_requerido' => $nivelRequerido,
                'tramo' => $tramo,
                'coste' => $coste,
                'es_maestria' => $tramo >= 4,
                'desbloqueado' => $estaDesbloqueado,
                'disponible' => $disponible,
                'puede_desbloquear' => ! $estaDesbloqueado
                    && $disponible
                    && $caramelos >= $coste,
                'orden' => (int) $nodo->sort_order,
            ];

            if ($estaDesbloqueado) {
                $totalDesbloqueados++;
            }
        }

        foreach ($ramas as &$nodosRama) {
            usort($nodosRama, static function (array $a, array $b): int {
                return [
                    $a['nivel_requerido'],
                    $a['tramo'],
                    $a['base_power'],
                    $a['name'],
                ] <=> [
                    $b['nivel_requerido'],
                    $b['tramo'],
                    $b['base_power'],
                    $b['name'],
                ];
            });
        }
        unset($nodosRama);

        return array_merge(
            ['tipo' => self::tipoBase($tipo)],
            $progreso,
            [
                'beneficios' => self::beneficiosDe($nivelActual),
                'siguiente_nivel' => $siguienteNivel,
                'beneficios_siguiente' => self::beneficiosDe($siguienteNivel),
                'caramelos' => $caramelos,
                'total_nodos' => $nodos->count(),
                'desbloqueados' => $totalDesbloqueados,
                'ramas' => $ramas,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function nodo(string $slug, int $userId): ?array
    {
        $move = Move::query()->where('slug', $slug)->first();
        if ($move === null) {
            return null;
        }

        $tipo = TipoEnum::tryFrom((int) $move->type_id);
        if ($tipo === null) {
            return null;
        }

        $arbol = self::para($userId, $tipo);
        foreach ($arbol['ramas'] as $nodos) {
            foreach ($nodos as $nodo) {
                if ($nodo['slug'] === $slug) {
                    return $nodo;
                }
            }
        }

        return null;
    }

    public static function tipoPorSlug(string $slug): ?TipoEnum
    {
        foreach (TipoEnum::cases() as $tipo) {
            if (SlugTipo::de($tipo->label()) === $slug) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * @return array{id: int, nombre: string, slug: string, color: string}
     */
    private static function tipoBase(TipoEnum $tipo): array
    {
        return [
            'id' => $tipo->value,
            'nombre' => $tipo->label(),
            'slug' => SlugTipo::de($tipo->label()),
            'color' => TipoPokemon::tryFrom($tipo->value)?->color() ?? '#6B7280',
        ];
    }

    /**
     * Beneficios pasivos del nivel de Senda listos para la UI.
     *
     * `captura` y `caramelo_tipo` son incrementos absolutos (0..1) y
     * `dano_recibido` es la reducción del daño entrante. Los niveles 1-3 no
     * tienen pasivos y devuelven ceros.
     *
     * @return array{captura: float, dano_infligido: float, dano_recibido: float, caramelo_tipo: float}
     */
    private static function beneficiosDe(int $nivel): array
    {
        $beneficios = BeneficiosSenda::para(NivelSenda::desdeInt($nivel), self::porcentajesBeneficios());

        return [
            'captura' => $beneficios->captura,
            'dano_infligido' => $beneficios->danoInfligido,
            'dano_recibido' => $beneficios->danoRecibido,
            'caramelo_tipo' => $beneficios->carameloTipo,
        ];
    }

    /**
     * Porcentajes configurados de beneficios de Senda (nivel => 0..1).
     *
     * @return array<int, mixed>
     */
    private static function porcentajesBeneficios(): array
    {
        $beneficios = config('sendas.beneficios', []);

        return is_array($beneficios) ? $beneficios : [];
    }

    /**
     * @param  list<int>  $moveIds
     * @return array<int, true>
     */
    private static function desbloqueados(int $userId, array $moveIds): array
    {
        if ($moveIds === []) {
            return [];
        }

        return array_fill_keys(
            PlayerMoveUnlock::query()
                ->withoutGlobalScope(PlayerMoveUnlock::USER_SCOPE_NAME)
                ->where('user_id', $userId)
                ->whereIn('move_id', $moveIds)
                ->pluck('move_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
            true,
        );
    }

    /**
     * @return Collection<int, int>
     */
    private static function caramelosPorTipo(int $userId): Collection
    {
        $items = PlayerInventory::query()
            ->withoutGlobalScope(PlayerInventory::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->where('item_key', 'like', 'tipo:%')
            ->pluck('cantidad', 'item_key');

        return $items->mapWithKeys(static function (mixed $cantidad, string $itemKey): array {
            $slug = substr($itemKey, 5);

            foreach (TipoEnum::cases() as $tipo) {
                if (SlugTipo::de($tipo->label()) === $slug) {
                    return [$tipo->value => (int) $cantidad];
                }
            }

            return [];
        });
    }
}
