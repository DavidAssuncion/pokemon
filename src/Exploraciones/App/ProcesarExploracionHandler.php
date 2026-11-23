<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Closure;
use Illuminate\Support\Collection;
use LogicException;
use Src\Exploraciones\App\Procesamiento\AplicadorDescansoExplorador;
use Src\Exploraciones\App\Procesamiento\CargadorPoolHabitat;
use Src\Exploraciones\App\Procesamiento\EjecutorCombateExplorador;
use Src\Exploraciones\App\Procesamiento\GestorBonosHabitat;
use Src\Exploraciones\App\Procesamiento\GestorTickExploracion;
use Src\Exploraciones\App\Procesamiento\MapeadorResolucionCombate;
use Src\Exploraciones\App\Procesamiento\MapeadorVentanaExploracion;
use Src\Exploraciones\App\Procesamiento\ResolutorEmboscada;
use Src\Exploraciones\App\Procesamiento\ResolutorEncuentro;
use Src\Exploraciones\App\Procesamiento\ResolutorEventoExploracion;
use Src\Exploraciones\App\Procesamiento\ResultadoTick;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\Tick\CalculadoraEvasionEmboscada;
use Src\Exploraciones\Domain\Tick\VentanaTick;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Domain\EscaladorNivelRival;
use Src\Shared\Domain\Ports\FactorAparicion;
use Src\Shared\Domain\Ports\Reloj;
use Src\Shared\Domain\Random\GeneradorAleatorioPredeterminado;
use Src\Shared\Domain\Time\RelojPredeterminado;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Tick de expedición individual (RF-05/D2): genera un evento por slot y lo
 * resuelve con combate real 1v1 (CombateExploracion) en lugar del evaluador
 * de capacidad. El explorador es un ÚNICO reclutado (sin roles ni sinergia).
 *
 * Tras cada combate: victoria → regenera barreras al 100 % (solo si NO es
 * emboscada secuencial); el HP no se cura por combate. Si HP < 50 % → descanso
 * hasta 100 % a 1 %/min real (acumulado en tiempo_perdido + evento bitácora).
 * Si el explorador pierde un combate → la exploración termina (derrota).
 *
 * La orquestación del tick vive en GestorTickExploracion; este handler
 * conserva guardado, cierre y despacho con el mismo timing que antes.
 */
final class ProcesarExploracionHandler implements CommandHandler
{
    private readonly Closure $aleatorio;

    private readonly Reloj $reloj;

    private readonly MapeadorVentanaExploracion $mapeador;

    private readonly GestorBonosHabitat $bonos;

    private readonly CargadorPoolHabitat $pool;

    private readonly GestorTickExploracion $gestor;

    /**
     * @param  callable():float|null  $aleatorio  Proveedor determinista [0,1)
     *                                            para TEST (seam). null → puerto de azar.
     */
    public function __construct(
        private readonly CommandBus $bus,
        CombateExploracion $combate,
        EscaladorNivelRival $escalador,
        ?callable $aleatorio = null,
        ?ResolverBonosFamilia $bonosFamilia = null,
        ?EsDelHabitat $esDelHabitat = null,
        ?Reloj $reloj = null,
        ?FactorAparicion $factorAparicion = null,
    ) {
        $this->aleatorio = $aleatorio !== null ? Closure::fromCallable($aleatorio) : self::aleatorioPredeterminado();
        $this->reloj = $reloj ?? new RelojPredeterminado();
        $this->mapeador = new MapeadorVentanaExploracion($this->reloj);
        $this->bonos = new GestorBonosHabitat($esDelHabitat);
        $this->pool = new CargadorPoolHabitat($bonosFamilia, $factorAparicion);
        $this->gestor = $this->crearGestor($combate, $escalador);
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof ProcesarExploracionCommand) {
            throw new LogicException('ProcesarExploracionHandler requires a ProcesarExploracionCommand.');
        }

        $exploracion = $command->exploracion;

        if ($exploracion->regreso !== null) {
            return null;
        }

        if ($exploracion->reclutado === null || $exploracion->user === null) {
            return null;
        }

        /** @var Collection<string, mixed> $eventos */
        $eventos = $exploracion->eventos ?? collect();

        // F3: el bonus de hábitat se calcula una sola vez y viaja en el JSON
        // de la exploración; la finalización lo reutiliza sin repetir consulta.
        $this->bonos->asegurar($exploracion, $eventos);

        $ventana = $this->mapeador->mapear($exploracion, $eventos);
        $capacidades = FabricaCapacidadesStats::desdeReclutado($exploracion->reclutado, $exploracion->user);
        $dificultad = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel)
            ?? EvaluadorExploracion::dificultad('normal', (int) ($exploracion->habitat?->peligro ?? 1));

        $resultado = $this->gestor->procesar($exploracion, $eventos, $ventana, $capacidades, $dificultad);

        $this->persistir($exploracion, $eventos, $resultado);
        $this->cerrarSiProcede($exploracion, $eventos, $ventana, $resultado, $command->forzarRegreso);

        return null;
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function persistir(ExploracionActiva $exploracion, Collection $eventos, ResultadoTick $resultado): void
    {
        if (! $resultado->debePersistir) {
            return;
        }

        $exploracion->eventos = $eventos;
        $exploracion->save();
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function cerrarSiProcede(
        ExploracionActiva $exploracion,
        Collection $eventos,
        VentanaTick $ventana,
        ResultadoTick $resultado,
        bool $forzarRegreso,
    ): void {
        $completada = $forzarRegreso
            || ($ventana->inicioVuelta !== null && $this->mapeador->ahora()->greaterThanOrEqualTo($ventana->inicioVuelta))
            || $resultado->terminada;

        if (! $completada) {
            return;
        }

        $eventos->put($resultado->motivo === 'derrota' ? 'derrota' : 'retirada', [
            'reason' => $resultado->motivo === 'derrota' ? 'explorador_debilitado' : 'grupo_enemigo',
            'timestamp' => $this->mapeador->ahora()->toIso8601String(),
        ]);
        $exploracion->eventos = $eventos;
        $exploracion->save();

        $this->bus->dispatch(new FinalizarExploracionCommand($exploracion));
    }

    private function crearGestor(
        CombateExploracion $combate,
        EscaladorNivelRival $escalador,
    ): GestorTickExploracion {
        $mapeadorCombate = new MapeadorResolucionCombate();
        $ejecutor = new EjecutorCombateExplorador($combate, $escalador);
        $descanso = new AplicadorDescansoExplorador($this->reloj);

        return new GestorTickExploracion(
            $this->pool,
            $descanso,
            new ResolutorEventoExploracion(
                new ResolutorEncuentro($mapeadorCombate, $ejecutor, $descanso),
                new ResolutorEmboscada(
                    $mapeadorCombate,
                    $ejecutor,
                    $descanso,
                    new CalculadoraEvasionEmboscada(),
                    $this->aleatorio,
                ),
            ),
            $this->aleatorio,
            $this->reloj,
        );
    }

    /**
     * Pool de encuentros: pokémon del hábitat asignados al nivel de la
     * exploración, con sus tipos y stats con effort>0 (para caramelos EV).
     *
     * Frontera — shape previo del pool (BC con tests que inspeccionan
     * poolHabitat por ReflectionMethod). El pipeline usa poolDeHabitat().
     *
     * @return list<array{id: int, capture_rate: int, hatch: int|null, tipos: list<TipoPokemon>, stats: list<array{stat: int, effort: int}>}>
     *
     * @deprecated Frontera (BC con tests que pasan arrays).
     *
     * @phpstan-ignore method.unused (invocado por tests vía ReflectionMethod)
     */
    private function poolHabitat(ExploracionActiva $exploracion): array
    {
        return $this->pool->cargar($exploracion)->aArrays();
    }

    /**
     * Seam por defecto: conserva la distribución mt_rand(0, 999) / 1000 a
     * través del puerto de azar, sin invocar mt_rand() directamente.
     */
    private static function aleatorioPredeterminado(): Closure
    {
        $generador = new GeneradorAleatorioPredeterminado();

        return static fn (): float => $generador->entero(0, 999) / 1000;
    }
}
