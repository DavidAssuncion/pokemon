<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\ExploracionActiva;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\App\EsDelHabitat;
use Src\Exploraciones\Domain\CalculadorBotinExploracion;
use Src\Exploraciones\Domain\ContextoBotin;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\Ports\ConsultorPlantaciones;
use Src\Exploraciones\Domain\Recompensas\RecompensaItemCollection;
use Src\Shared\Domain\Items\ClaveBonguri;

/**
 * Resuelve el flag de Pokémon nativo del hábitat y calcula el botín de la
 * expedición. Capa App: usa Eloquent a través de los colaboradores inyectados.
 */
final class ResolutorBotinExploracion
{
    public function __construct(
        private readonly CalculadoraTiemposExpedicion $tiempos,
        private readonly ConsultorPlantaciones $consultor,
        private readonly ?EsDelHabitat $esDelHabitat = null,
    ) {
    }

    /**
     * Flag “Pokémon nativo del hábitat”. Se reutiliza el valor persistido por
     * el tick; si falta, se calcula una única vez con `EsDelHabitat`.
     *
     * @param  BaseCollection<string, mixed>  $eventos
     */
    public function delHabitat(ExploracionActiva $exploracion, BaseCollection $eventos): bool
    {
        $bonos = $eventos->get('bonos');

        if (is_array($bonos) && array_key_exists('del_habitat', $bonos)) {
            return (bool) $bonos['del_habitat'];
        }

        $reclutado = $exploracion->reclutado;

        if ($reclutado === null) {
            return false;
        }

        return ($this->esDelHabitat ?? new EsDelHabitat())
            ->handle($reclutado->pokemon_id, $exploracion->habitat_id);
    }

    /**
     * Calcula el botín de items de la expedición según el modo.
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     */
    public function botin(
        ExploracionActiva $exploracion,
        PerfilExploracion $perfil,
        array $bitacora,
        BaseCollection $eventos,
        bool $delHabitat,
    ): RecompensaItemCollection {
        return (new CalculadorBotinExploracion())->calcular(new ContextoBotin(
            modo: $perfil->modo,
            bonguriHabitat: ClaveBonguri::tryFrom($exploracion->habitat->bonguri ?? '')
                ?? ClaveBonguri::porDefecto(),
            nivelHabitat: $exploracion->nivel,
            duracionHoras: $exploracion->duracion_horas ?? 0,
            delHabitat: $delHabitat,
            huboDerrota: $this->terminoPorDerrota($bitacora, $eventos),
            multiplicadorBotin: $perfil->multiplicadorBotin,
            factorTiempo: $this->factorTiempo($exploracion),
            factoresPlantacion: $this->consultor->factoresPara(
                $exploracion->user_id,
                $exploracion->habitat_id,
            ),
        ));
    }

    /**
     * Detecta si la exploración terminó por derrota (bitácora contiene un
     * evento con resolución 'derrota' o el flag eventos['derrota'] existe).
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     */
    public function terminoPorDerrota(array $bitacora, BaseCollection $eventos): bool
    {
        if ($eventos->get('derrota') !== null) {
            return true;
        }

        foreach ($bitacora as $evento) {
            if (($evento['resolucion'] ?? '') === 'derrota') {
                return true;
            }
        }

        return false;
    }

    private function factorTiempo(ExploracionActiva $exploracion): float
    {
        return $this->tiempos->factorTiempo($exploracion);
    }
}
