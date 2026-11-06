<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\ModoExploracion;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\ReglasExploracion;
use Src\Exploraciones\Domain\Tick\GeneradorEventosTick;
use Src\Exploraciones\Domain\Tick\VentanaTick;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Orquesta la resolución de un tick de exploración.
 *
 * Genera eventos, aplica descansos, resuelve encuentros/emboscadas y deja el
 * payload mutado en la colección `eventos`. No persiste ni despacha: devuelve
 * ResultadoTick y el handler conserva el timing de save()/dispatch().
 */
final class GestorTickExploracion
{
    /**
     * @param  Closure():float  $aleatorio
     */
    public function __construct(
        private readonly CargadorPoolHabitat $pool,
        private readonly AplicadorDescansoExplorador $descanso,
        private readonly ResolutorEventoExploracion $evento,
        private readonly Closure $aleatorio,
        private readonly Reloj $reloj,
    ) {
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    public function procesar(
        ExploracionActiva $exploracion,
        Collection $eventos,
        VentanaTick $ventana,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): ResultadoTick {
        $parametros = $this->prepararParametros($eventos, $ventana, $capacidades, $dificultad);

        if (! $ventana->procesable()) {
            $eventos->put('ultimo_procesado', $ventana->hasta->toIso8601String());

            return new ResultadoTick(false, '', true);
        }

        $pool = $this->pool->cargar($exploracion);
        $perfil = PerfilExploracion::de(ModoExploracion::desde($exploracion->modo));
        $nuevos = $this->generarNuevos($pool, $ventana, $parametros, $capacidades, $dificultad, $perfil);

        if ($nuevos === []) {
            $eventos->put('ultimo_procesado', $ventana->hasta->toIso8601String());

            return new ResultadoTick(false, '', true);
        }

        return $this->resolverTick(
            $exploracion,
            $eventos,
            $ventana,
            $capacidades,
            $dificultad,
            $pool,
            $perfil,
            $nuevos,
            $parametros->eventosGenerados,
        );
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function prepararParametros(
        Collection $eventos,
        VentanaTick $ventana,
        CapacidadesStats $capacidades,
        int $dificultad,
    ): ParametrosGeneracionTick {
        $tiempoPerdido = (int) $eventos->get('tiempo_perdido', 0);
        $intervaloBase = ReglasExploracion::MINUTOS_POR_ENCUENTRO;
        $reduccionIntervalo = $capacidades->reduccionIntervaloMovilidad($dificultad);
        $bonusEventos = $capacidades->bonusEventosExploracion($dificultad);

        return new ParametrosGeneracionTick(
            tiempoPerdido: $tiempoPerdido,
            intervaloBase: $intervaloBase,
            reduccionIntervalo: $reduccionIntervalo,
            bonusEventos: $bonusEventos,
            eventosGenerados: $this->contarGenerados(
                $eventos,
                $ventana->inicio,
                $ventana->hasta,
                $tiempoPerdido,
                $intervaloBase,
                $reduccionIntervalo,
                $bonusEventos,
            ),
        );
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function contarGenerados(
        Collection $eventos,
        \Carbon\CarbonInterface $inicio,
        \Carbon\CarbonInterface $hasta,
        int $tiempoPerdido,
        int $intervaloBase,
        float $reduccionIntervalo,
        int $bonusEventos,
    ): int {
        if ($eventos->has('eventos_generados')) {
            return (int) $eventos->get('eventos_generados');
        }

        $bitacora = $eventos->get('bitacora', []);
        $generados = GeneradorEventosTick::backfill(
            is_array($bitacora) ? $bitacora : [],
            $inicio,
            $hasta,
            $tiempoPerdido,
            $intervaloBase,
            $reduccionIntervalo,
            $bonusEventos,
        );

        $eventos->put('eventos_generados', $generados);

        return $generados;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generarNuevos(
        PoolHabitat $pool,
        VentanaTick $ventana,
        ParametrosGeneracionTick $parametros,
        CapacidadesStats $capacidades,
        int $dificultad,
        PerfilExploracion $perfil,
    ): array {
        $pendientes = GeneradorEventosTick::pendientes(
            $ventana->inicio,
            $ventana->hasta,
            $parametros->eventosGenerados,
            $parametros->tiempoPerdido,
            $parametros->intervaloBase,
            $parametros->reduccionIntervalo,
            $parametros->bonusEventos,
        );

        return GeneradorEventosTick::generar(
            $pool,
            $pendientes,
            $ventana->desde,
            $ventana->hasta,
            $this->aleatorio,
            $capacidades->permitirEmboscadas($dificultad),
            $capacidades->permitirExcepcionales($dificultad),
            $perfil,
        )->aArrays();
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     * @param  list<array<string, mixed>>  $nuevos
     */
    private function resolverTick(
        ExploracionActiva $exploracion,
        Collection $eventos,
        VentanaTick $ventana,
        CapacidadesStats $capacidades,
        int $dificultad,
        PoolHabitat $pool,
        PerfilExploracion $perfil,
        array $nuevos,
        int $eventosGenerados,
    ): ResultadoTick {
        $programados = count($nuevos);
        $contexto = new ContextoTickExploracion(
            $exploracion,
            $eventos,
            $capacidades,
            $dificultad,
            $pool,
            $perfil,
            $ventana,
        );

        $contexto->perdidoTick = $this->descanso->aplicar($contexto);
        $resueltos = $this->iterar($nuevos, $contexto, $perfil);
        $this->volcar($contexto, $resueltos, $eventosGenerados, $programados);

        return new ResultadoTick(
            $contexto->derrota || $contexto->retirada,
            $contexto->derrota ? 'derrota' : ($contexto->retirada ? 'retirada' : ''),
            true,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $nuevos
     * @return list<array<string, mixed>>
     */
    private function iterar(array $nuevos, ContextoTickExploracion $contexto, PerfilExploracion $perfil): array
    {
        $resueltos = [];
        $limiteIteraciones = 2 * count($nuevos);
        $indice = 0;

        while ($indice < count($nuevos) && $indice < $limiteIteraciones) {
            $resuelto = $this->evento->resolver($nuevos[$indice], $contexto);
            $resueltos[] = $resuelto;
            $contexto->perdidoTick += $resuelto['duration_loss'] ?? 0;

            if (($resuelto['resolucion'] ?? '') === 'derrota') {
                $contexto->derrota = true;
                break;
            }

            if (($resuelto['retirada_probable'] ?? false) === true && ($this->aleatorio)() < 0.5) {
                $contexto->retirada = true;
                break;
            }

            if (($resuelto['evitada'] ?? false) === true) {
                $nuevos[] = GeneradorEventosTick::extra(
                    $contexto->pool,
                    $contexto->ventana->desde,
                    $contexto->ventana->hasta,
                    $this->aleatorio,
                    $perfil,
                )->aArray();
            }

            $contexto->perdidoTick += $this->descanso->aplicar($contexto);
            $indice++;
        }

        return $resueltos;
    }

    /**
     * @param  list<array<string, mixed>>  $resueltos
     */
    private function volcar(
        ContextoTickExploracion $contexto,
        array $resueltos,
        int $eventosGenerados,
        int $programados,
    ): void {
        $eventos = $contexto->eventos;
        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, ...$resueltos]);
        $eventos->put('tiempo_perdido', (int) $eventos->get('tiempo_perdido', 0) + $contexto->perdidoTick);
        $eventos->put('eventos_generados', $eventosGenerados + $programados);
        $eventos->put('explorador', $contexto->estado->aArray());

        if ($contexto->retirada) {
            $eventos->put('retirada', [
                'reason' => 'grupo_enemigo',
                'timestamp' => Carbon::instance($this->reloj->ahora())->toIso8601String(),
            ]);
        }

        $eventos->put(
            'ultimo_procesado',
            $contexto->ventana->hasta->copy()->addMinutes($contexto->perdidoTick)->toIso8601String(),
        );
    }
}
