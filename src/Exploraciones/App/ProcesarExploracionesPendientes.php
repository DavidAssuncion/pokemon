<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Shared\Bus\CommandBus;
use Throwable;

/**
 * Procesamiento bajo demanda de exploraciones pendientes.
 *
 * Compartido por `GET /exploraciones` (solo el usuario autenticado), por
 * `recoger` (una exploración con forzarRegreso) y por la consola (todos los
 * usuarios). El lock por exploración se adquiere aquí, FUERA de la
 * transacción del CommandBus, para que no se libere antes del commit.
 *
 * Reglas:
 * - Throttle: se omite si `eventos['ultimo_procesado']` es posterior a
 *   `now() - 30 s`. `forzarRegreso` salta el throttle (recoger manual).
 * - Lock: `exploracion:procesar:{id}`, TTL 60 s, no bloqueante. Si está
 *   ocupado se omite (otro proceso ya está trabajando; no es un error).
 * - Fallos: se aíslan por exploración, se registran y se continúa.
 * - Presupuesto: antes de cada exploración se comprueba el tiempo consumido.
 */
final class ProcesarExploracionesPendientes
{
    private const TTL_LOCK_SEGUNDOS = 60;

    private const THROTTLE_SEGUNDOS = 30;

    public function __construct(
        private readonly CommandBus $bus,
    ) {
    }

    /**
     * Procesa las exploraciones activas del usuario autenticado. No usa
     * `withoutUserScope()`: el scope global y el `user_id` explícito acotan.
     */
    public function paraUsuario(int $userId, int $limite = 10, int $presupuestoMs = 2000): ResultadoProcesamientoPendiente
    {
        $consulta = (new ExploracionActiva())->newQuery()
            ->where('user_id', $userId)
            ->where('regreso', null)
            ->with([
                'reclutado.pokemon.stats',
                'reclutado.pokemon.types',
                'habitat',
            ]);

        $consulta->getQuery()
            ->orderBy('inicio_exploracion')
            ->limit($limite);

        return $this->procesar($consulta->get(), $presupuestoMs);
    }

    /**
     * Procesa las exploraciones activas de TODOS los usuarios (consola).
     * Sin presupuesto: el comando acota por límite.
     */
    public function paraTodas(int $limite = 500): ResultadoProcesamientoPendiente
    {
        $consulta = (new ExploracionActiva())->newQuery()
            ->withoutGlobalScope(ExploracionActiva::USER_SCOPE_NAME)
            ->where('regreso', null)
            ->with([
                'reclutado.pokemon.stats',
                'reclutado.pokemon.types',
                'habitat',
            ]);

        $consulta->getQuery()
            ->orderBy('inicio_exploracion')
            ->limit($limite);

        return $this->procesar($consulta->get(), 0);
    }

    /**
     * Procesa una exploración concreta (recoger manual). Devuelve true solo
     * si se despachó; false si el throttle aplica, el lock está ocupado o el
     * despacho falla.
     */
    public function una(ExploracionActiva $exploracion, bool $forzarRegreso = false): bool
    {
        if (! $forzarRegreso && $this->estaThrottled($exploracion)) {
            return false;
        }

        try {
            return $this->despacharConLock($exploracion, $forzarRegreso);
        } catch (Throwable) {
            $this->registrarFallo($exploracion);

            return false;
        }
    }

    /**
     * @param  EloquentCollection<int, ExploracionActiva>  $exploraciones
     */
    private function procesar(EloquentCollection $exploraciones, int $presupuestoMs): ResultadoProcesamientoPendiente
    {
        $inicio = microtime(true);
        $procesadas = 0;
        $omitidas = 0;
        $fallidas = 0;
        $agotadoPorTiempo = false;

        foreach ($exploraciones as $exploracion) {
            if ($presupuestoMs > 0 && (microtime(true) - $inicio) * 1000 >= $presupuestoMs) {
                $agotadoPorTiempo = true;
                break;
            }

            if ($this->estaThrottled($exploracion)) {
                $omitidas++;

                continue;
            }

            try {
                if ($this->despacharConLock($exploracion)) {
                    $procesadas++;
                } else {
                    $omitidas++;
                }
            } catch (Throwable) {
                $fallidas++;
                $this->registrarFallo($exploracion);
            }
        }

        return new ResultadoProcesamientoPendiente(
            procesadas: $procesadas,
            omitidas: $omitidas,
            fallidas: $fallidas,
            agotadoPorTiempo: $agotadoPorTiempo,
        );
    }

    /**
     * Adquiere el lock no bloqueante y despacha el comando. El lock se libera
     * en `finally`, después de que el bus haya confirmado la transacción.
     */
    private function despacharConLock(ExploracionActiva $exploracion, bool $forzarRegreso = false): bool
    {
        $lock = Cache::lock($this->claveLock($exploracion), self::TTL_LOCK_SEGUNDOS);

        if ($lock->get() === false) {
            return false;
        }

        try {
            $this->bus->dispatch(new ProcesarExploracionCommand($exploracion, $forzarRegreso));

            return true;
        } finally {
            $lock->release();
        }
    }

    private function estaThrottled(ExploracionActiva $exploracion): bool
    {
        $ultimo = $exploracion->eventos?->get('ultimo_procesado');

        if (! is_string($ultimo) || trim($ultimo) === '') {
            return false;
        }

        try {
            $fecha = Carbon::parse($ultimo);
        } catch (Throwable) {
            // Valor corrupto: mejor procesar que bloquear la expedición.
            return false;
        }

        return $fecha->greaterThan(now()->subSeconds(self::THROTTLE_SEGUNDOS));
    }

    private function claveLock(ExploracionActiva $exploracion): string
    {
        return sprintf('exploracion:procesar:%d', $exploracion->id);
    }

    private function registrarFallo(ExploracionActiva $exploracion): void
    {
        Log::warning('exploraciones.procesar_pendientes.fallo', [
            'exploracion_id' => $exploracion->id,
            'user_id' => $exploracion->user_id,
        ]);
    }
}
