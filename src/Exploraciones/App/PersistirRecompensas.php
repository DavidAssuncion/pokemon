<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\PlayerInventory;
use App\Models\Reclutable;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\User;
use App\Support\ItemCatalogo;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * Persiste en DB todas las recompensas de una exploración (increment-or-create
 * atómico por clave única; EXP al jugador y al equipo/reclutado).
 * Los caramelos y capturas van al inventario/reclutables del DUEÑO de la
 * exploración (user_id NOT NULL con FK cascade: el dueño siempre existe).
 * El destino de EXP puede ser un Team (exploración por equipo legacy) o un
 * Reclutado individual (exploración por reclutado).
 * No final: los tests sustituyen persistir() por un mock parcial.
 */
class PersistirRecompensas
{
    public function persistir(ResultadoRecompensas $recompensas, Team|Reclutado|null $destinoExp, ?User $usuario): void
    {
        $this->guardarCaramelosFamilia($recompensas->caramelosFamilia, $usuario);
        $this->guardarCaramelosEv($recompensas->caramelosEv, $usuario);
        $this->guardarCaramelosTipo($recompensas->caramelosTipo, $usuario);
        $this->aplicarCapturas($recompensas->capturas, $usuario);
        $this->aplicarExperiencia(
            $recompensas->expTotal,
            $recompensas->expPorMiembro,
            $recompensas->expTipoPorMiembro,
            $destinoExp,
            $usuario,
        );
    }

    /**
     * @param  Collection<int, RecompensaFamilia>  $caramelos
     */
    private function guardarCaramelosFamilia(Collection $caramelos, ?User $usuario): void
    {
        if ($usuario === null) {
            return;
        }

        foreach ($caramelos as $caramelo) {
            PlayerInventory::firstOrCreate(
                ['user_id' => $usuario->id, 'item_key' => ItemCatalogo::keyFamilia($caramelo->evolutionChainId)],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaEv>  $caramelos
     */
    private function guardarCaramelosEv(Collection $caramelos, ?User $usuario): void
    {
        if ($usuario === null) {
            return;
        }

        foreach ($caramelos as $caramelo) {
            PlayerInventory::firstOrCreate(
                ['user_id' => $usuario->id, 'item_key' => ItemCatalogo::keyEv($caramelo->stat)],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaTipo>  $caramelos
     */
    private function guardarCaramelosTipo(Collection $caramelos, ?User $usuario): void
    {
        if ($usuario === null) {
            return;
        }

        foreach ($caramelos as $caramelo) {
            PlayerInventory::firstOrCreate(
                ['user_id' => $usuario->id, 'item_key' => ItemCatalogo::keyTipo($caramelo->tipo)],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaCaptura>  $capturas
     */
    private function aplicarCapturas(Collection $capturas, ?User $usuario): void
    {
        if ($usuario === null) {
            return;
        }

        foreach ($capturas as $captura) {
            Reclutable::firstOrCreate(
                ['user_id' => $usuario->id, 'pokemon_id' => $captura->pokemonId],
                ['cantidad' => 0],
            )->increment('cantidad', $captura->cantidad);
        }
    }

    /**
     * Reparto de EXP (D3/RF-14): el jugador recibe el 100 % del total; cada
     * miembro del equipo recibe la parte del 80 % repartida entre 3
     * (expPorMiembro ya calculado por el dominio) y acumula la exp de tipo
     * correspondiente en `reclutados.exp.tipos`. Si el destino es un reclutado
     * individual (exploración individual), el reclutado recibe expPorMiembro.
     *
     * @param  array<string, int>  $expTipoPorMiembro
     */
    private function aplicarExperiencia(int $expTotal, int $expPorMiembro, array $expTipoPorMiembro, Team|Reclutado|null $destinoExp, ?User $usuario): void
    {
        if ($usuario !== null && $expTotal > 0) {
            $usuario->increment('experiencia', $expTotal);
        }

        if ($destinoExp === null || $expPorMiembro <= 0) {
            return;
        }

        if ($destinoExp instanceof Team) {
            foreach ($destinoExp->members as $miembro) {
                if ($miembro->reclutado !== null) {
                    $this->sumarExp($miembro->reclutado, $expPorMiembro, $expTipoPorMiembro);
                }
            }

            return;
        }

        $this->sumarExp($destinoExp, $expPorMiembro, $expTipoPorMiembro);
    }

    /**
     * @param  array<string, int>  $expTipoPorMiembro
     */
    private function sumarExp(Reclutado $reclutado, int $expTotal, array $expTipoPorMiembro): void
    {
        // El cast ExpReclutado devuelve el VO: se normaliza a array para sumar
        // el total y los tipos y se reasigna (el set del cast normaliza de nuevo).
        $expActual = $reclutado->exp->toArray();
        $expActual['total'] += $expTotal;

        foreach ($expTipoPorMiembro as $tipo => $exp) {
            if ($exp > 0) {
                $expActual['tipos'][$tipo] = ($expActual['tipos'][$tipo] ?? 0) + $exp;
            }
        }

        $reclutado->update(['exp' => $expActual]);
    }
}
