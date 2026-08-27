<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\Caramelo;
use App\Models\CarameloEv;
use App\Models\CarameloTipo;
use App\Models\Reclutable;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * Persiste en DB todas las recompensas de una exploración (increment-or-create
 * atómico por clave única; EXP al jugador y a los miembros del equipo).
 * No final: los tests sustituyen persistir() por un mock parcial.
 */
class PersistirRecompensas
{
    public function persistir(ResultadoRecompensas $recompensas, ?Team $equipo, ?User $usuario): void
    {
        $this->guardarCaramelosFamilia($recompensas->caramelosFamilia);
        $this->guardarCaramelosEv($recompensas->caramelosEv);
        $this->guardarCaramelosTipo($recompensas->caramelosTipo);
        $this->aplicarCapturas($recompensas->capturas);
        $this->aplicarExperiencia($recompensas->expTotal, $equipo, $usuario);
    }

    /**
     * @param  Collection<int, RecompensaFamilia>  $caramelos
     */
    private function guardarCaramelosFamilia(Collection $caramelos): void
    {
        foreach ($caramelos as $caramelo) {
            Caramelo::updateOrCreate(
                ['evolution_chain_id' => $caramelo->evolutionChainId],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaEv>  $caramelos
     */
    private function guardarCaramelosEv(Collection $caramelos): void
    {
        foreach ($caramelos as $caramelo) {
            CarameloEv::updateOrCreate(
                ['stat' => $caramelo->stat],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaTipo>  $caramelos
     */
    private function guardarCaramelosTipo(Collection $caramelos): void
    {
        foreach ($caramelos as $caramelo) {
            CarameloTipo::updateOrCreate(
                ['tipo' => $caramelo->tipo],
                ['cantidad' => 0],
            )->increment('cantidad', $caramelo->cantidad);
        }
    }

    /**
     * @param  Collection<int, RecompensaCaptura>  $capturas
     */
    private function aplicarCapturas(Collection $capturas): void
    {
        foreach ($capturas as $captura) {
            Reclutable::updateOrCreate(
                ['pokemon_id' => $captura->pokemonId],
                ['cantidad' => 0],
            )->increment('cantidad', $captura->cantidad);
        }
    }

    private function aplicarExperiencia(int $expTotal, ?Team $equipo, ?User $usuario): void
    {
        if ($usuario !== null && $expTotal > 0) {
            $usuario->increment('experiencia', $expTotal);
        }

        if ($equipo === null) {
            return;
        }

        foreach ($equipo->members as $miembro) {
            $reclutado = $miembro->reclutado;
            if ($reclutado === null) {
                continue;
            }

            /** @var array<string, int> $expActual */
            $expActual = $reclutado->exp ?? ['total' => 0];
            $expActual['total'] = ($expActual['total'] ?? 0) + $expTotal;
            $reclutado->update(['exp' => $expActual]);
        }
    }
}
