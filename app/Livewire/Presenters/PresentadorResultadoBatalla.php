<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use App\Support\BattleSessionService;
use App\Support\CargarMapaSendaJugador;
use App\Support\ProgresoRecompensas;
use App\Support\ProgresoRecompensasVista;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador;
use Src\CombateEntrenadores\App\RegistrarResultadoEntrenador;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;
use Src\CombateRuta\App\RegistrarResultadoRuta;
use Src\Exploraciones\Domain\DificultadHabitat;
use Src\Gimnasios\App\RegistrarResultadoGimnasio;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\HabitatsTemporales\App\RegistrarResultadoHabitatTemporal;
use Src\HabitatsTemporales\Domain\CatalogoFaccionesRivales;
use Src\HabitatsTemporales\Domain\Exceptions\FaccionNoExiste;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\Mazmorras\App\ObtenerConfiguracionMazmorra;
use Src\Mazmorras\App\OtorgarRecompensasMazmorra;
use Src\Mazmorras\App\RegistrarResultadoMazmorra;
use Src\Mazmorras\Domain\ReglasMazmorra;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;
use Src\Shared\Domain\MultiplicadoresDificultad;
use Src\Shared\Domain\PoliticaIvsEvs;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

/**
 * Presentador de presentación: procesa el resultado de una batalla terminada
 * y retorna los datos de vista (log, recompensas, habitatId).
 *
 * No acoplado a Livewire: recibe los datos de entrada y devuelve arrays
 * con las mismas claves byte a byte que consume la vista blade.
 */
final class PresentadorResultadoBatalla
{
    /**
     * Procesa el resultado de una batalla terminada según su tipo
     * (entrenador, gimnasio, mazmorra o hábitat temporal).
     *
     * @return array{log: list<string>, rewards: array<string, mixed>, habitatId: int, returnUrl: string, returnLabel: string}
     */
    public static function procesar(
        AgregadoBatalla $battle,
        string $battleId,
        BattleSessionService $session,
    ): array {
        $meta = $session->cargarMeta($battleId);
        if ($meta === null) {
            return [
                'log' => [],
                'rewards' => [],
                'habitatId' => 0,
                'returnUrl' => '/gimnasios',
                'returnLabel' => 'Volver a gimnasios',
            ];
        }

        $tipo = $meta['tipo'] ?? null;

        $resultado = match ($tipo) {
            'gimnasio' => self::procesarGimnasio($battle, $meta),
            'mazmorra' => self::procesarMazmorra($battle, $meta),
            'habitat_temporal' => self::procesarHabitatTemporal($battle, $meta),
            'ruta' => self::procesarRuta($battle, $meta),
            default => self::procesarEntrenador($battle, $meta),
        };

        $completada = (bool) ($resultado['completada'] ?? false);
        $devolucion = self::devolucion($tipo, $meta, (int) ($resultado['habitatId'] ?? 0), $completada);
        $resultado['returnUrl'] = $devolucion['url'];
        $resultado['returnLabel'] = $devolucion['label'];

        $session->limpiarMeta($battleId);

        return $resultado;
    }

    /**
     * URL y etiqueta del botón de vuelta del modal de victoria.
     *
     * @param  array<string, mixed>  $meta
     * @return array{url: string, label: string}
     */
    private static function devolucion(?string $tipo, array $meta, int $habitatId, bool $completada): array
    {
        if ($tipo === 'habitat_temporal') {
            if ($completada) {
                return [
                    'url' => '/habitats',
                    'label' => 'Volver a hábitats',
                ];
            }

            $slug = (string) ($meta['faccion_slug'] ?? '');

            return [
                'url' => '/habitats/temporales/'.$slug,
                'label' => 'Volver al hábitat',
            ];
        }

        if ($habitatId > 0) {
            return [
                'url' => '/habitats/'.$habitatId,
                'label' => 'Volver al hábitat',
            ];
        }

        return [
            'url' => '/gimnasios',
            'label' => 'Volver a gimnasios',
        ];
    }

    /**
     * Combate contra entrenador estándar.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function procesarEntrenador(AgregadoBatalla $battle, array $meta): array
    {
        $habitatId = (int) ($meta['habitat_id'] ?? 0);
        $userId = (int) ($meta['user_id'] ?? 0);
        $won = ! $battle->team1->todosDebilitados();
        $rewards = [];

        app(RegistrarResultadoEntrenador::class)->registrar(
            habitatId: $habitatId,
            nivel: (int) ($meta['nivel'] ?? 0),
            trainerIndex: (int) ($meta['trainer_index'] ?? 0),
            userId: $userId,
            fecha: (string) ($meta['fecha'] ?? today()->toDateString()),
            won: $won,
        );

        if ($won) {
            $senda = app(CargarMapaSendaJugador::class)->para($userId);

            [$datosModal, $progreso] = self::medirProgreso($userId, fn () => self::otorgarRecompensas(
                userId: $userId,
                teamId: (int) ($meta['team_id'] ?? 0),
                battle: $battle,
                nivel: (int) ($meta['nivel'] ?? 0),
                dificultad: MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilEntrenador((int) ($meta['nivel'] ?? 1))),
                senda: $senda,
            ));

            if ($datosModal !== null) {
                $rewards = self::adjuntarProgreso(self::modalAVista($datosModal), $progreso);
            }
        }

        return ['log' => [], 'rewards' => $rewards, 'habitatId' => $habitatId];
    }

    /**
     * Combate de gimnasio.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function procesarGimnasio(AgregadoBatalla $battle, array $meta): array
    {
        $gymId = (string) ($meta['gym_id'] ?? '');
        $stage = (int) ($meta['stage'] ?? 0);
        $userId = (int) ($meta['user_id'] ?? 0);
        $teamId = (int) ($meta['team_id'] ?? 0);
        $nivelRival = (int) ($meta['nivel_rival'] ?? 0);

        $won = ! $battle->team1->todosDebilitados();
        $rewards = [];
        $log = [];

        $repositorio = app(GymCatalogoRepositoryInterface::class);

        try {
            $gimnasio = $repositorio->obtenerPorSlugOrFail($gymId);
            $dificultad = MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(
                $repositorio->indiceDe($gymId),
                $stage === 4,
            ));
        } catch (GimnasioNoExiste $e) {
            // La meta de la batalla debería apuntar a un gimnasio existente. Si
            // no, no se puede registrar la medalla: se deja rastro en vez de
            // perderla silenciosamente.
            Log::error('Resultado de gimnasio con gym_id inexistente.', [
                'gym_id' => $gymId,
                'stage' => $stage,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            $log[] = 'No se pudo recuperar el gimnasio: la medalla no se ha registrado.';
            $gimnasio = null;
            $dificultad = null;
        }

        $resultado = app(RegistrarResultadoGimnasio::class)->registrar(
            gymId: $gymId,
            etapaCompletada: $stage,
            userId: $userId,
            won: $won,
            authUserId: (int) Auth::id(),
            nombreMedalla: $gimnasio?->medalla,
        );

        if ($won && $resultado->avance) {
            $senda = app(CargarMapaSendaJugador::class)->para($userId);

            [$datosModal, $progreso] = self::medirProgreso($userId, fn () => self::otorgarRecompensas(
                userId: $userId,
                teamId: $teamId,
                battle: $battle,
                nivel: $nivelRival,
                multiplicador: OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO,
                dificultad: $dificultad,
                senda: $senda,
            ));

            if ($datosModal !== null) {
                if ($resultado->medalla !== null) {
                    $datosModal = $datosModal->conMedalla($resultado->medalla);
                }
                // Se adjunta después de conMedalla(): el DTO no puede perder el progreso.
                $rewards = self::adjuntarProgreso(self::modalAVista($datosModal), $progreso);
            }
        }

        return ['log' => $log, 'rewards' => $rewards, 'habitatId' => 0];
    }

    /**
     * Combate de mazmorra.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function procesarMazmorra(AgregadoBatalla $battle, array $meta): array
    {
        $habitatId = (int) ($meta['habitat_id'] ?? 0);
        $floor = (int) ($meta['floor'] ?? 0);
        $userId = (int) ($meta['user_id'] ?? 0);
        $won = ! $battle->team1->todosDebilitados();
        $log = [];

        $totalPisos = app(ObtenerConfiguracionMazmorra::class)
            ->deHabitat($habitatId)?->totalPisos() ?? 0;

        $rewards = [];

        $resultado = app(RegistrarResultadoMazmorra::class)->registrar(
            habitatId: $habitatId,
            piso: $floor,
            totalPisos: $totalPisos,
            userId: $userId,
            won: $won,
            authUserId: (int) Auth::id(),
        );

        if ($won) {
            [$datosModal, $progreso] = self::medirProgreso($userId, fn () => app(OtorgarRecompensasMazmorra::class)->otorgar(
                userId: $userId,
                teamId: (int) ($meta['team_id'] ?? 0),
                battle: $battle,
                nivel: (int) ($meta['nivel_rival'] ?? 0),
            ));

            if ($datosModal !== null) {
                $caramelos = [];
                foreach ($datosModal->caramelos as $caramelo) {
                    if ($caramelo instanceof ItemCaramelo) {
                        $caramelos[] = [
                            'nombre' => $caramelo->nombre,
                            'imagen' => $caramelo->imagen,
                            'cantidad' => $caramelo->cantidad,
                        ];
                    }
                }

                $rewards = self::adjuntarProgreso([
                    'exp_total' => $datosModal->expTotal,
                    'exp_miembro' => $datosModal->expMiembro,
                    'caramelos' => $caramelos,
                ], $progreso);
            }
        }

        if ($won && $resultado->completado) {
            $log[] = '¡Has completado la mazmorra!';
        } elseif ($won) {
            $log[] = '¡Piso superado! El siguiente piso ya está disponible.';
        } elseif (! $resultado->avance && $userId === (int) Auth::id()) {
            $log[] = 'Has perdido. Podrás reintentar el piso en '.ReglasMazmorra::HORAS_COOLDOWN.' hora.';
        }

        return ['log' => $log, 'rewards' => $rewards, 'habitatId' => 0];
    }

    /**
     * Combate de hábitat temporal de facciones rivales.
     *
     * @param  array<string, mixed>  $meta
     * @return array{log: list<string>, rewards: array<string, mixed>, habitatId: int, completada: bool}
     */
    private static function procesarHabitatTemporal(AgregadoBatalla $battle, array $meta): array
    {
        $faccionSlug = (string) ($meta['faccion_slug'] ?? '');
        $oleada = (int) ($meta['oleada'] ?? 0);
        $combate = (int) ($meta['combate'] ?? 0);
        $esJefe = (bool) ($meta['es_jefe'] ?? ($combate === ReglasHabitatTemporal::COMBATES_POR_OLEADA));
        $userId = (int) ($meta['user_id'] ?? 0);
        $teamId = (int) ($meta['team_id'] ?? 0);
        $nivelRival = (int) ($meta['nivel_rival'] ?? 0);

        $won = ! $battle->team1->todosDebilitados();
        $log = [];
        $rewards = [];

        try {
            $faccion = app(CatalogoFaccionesRivales::class)->porSlug($faccionSlug);
        } catch (FaccionNoExiste $e) {
            Log::error('Resultado de hábitat temporal con facción inexistente.', [
                'faccion_slug' => $faccionSlug,
                'oleada' => $oleada,
                'combate' => $combate,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'log' => ['No se pudo recuperar la facción: el progreso no se ha registrado.'],
                'rewards' => [],
                'habitatId' => 0,
                'completada' => false,
            ];
        }

        $resultado = app(RegistrarResultadoHabitatTemporal::class)->registrar(
            faccionSlug: $faccionSlug,
            oleada: $oleada,
            combate: $combate,
            userId: $userId,
            won: $won,
            authUserId: (int) Auth::id(),
        );

        if ($won && $resultado->avance) {
            $senda = app(CargarMapaSendaJugador::class)->para($userId);

            [$datosModal, $progreso] = self::medirProgreso($userId, fn () => self::otorgarRecompensas(
                userId: $userId,
                teamId: $teamId,
                battle: $battle,
                nivel: $nivelRival,
                multiplicador: ReglasHabitatTemporal::MULTIPLICADOR_RECOMPENSA,
                dificultad: MultiplicadoresDificultad::para(
                    ReglasHabitatTemporal::perfilIvsEvs($oleada, $esJefe),
                ),
                senda: $senda,
            ));

            if ($datosModal !== null) {
                $rewards = self::adjuntarProgreso(self::modalAVista($datosModal), $progreso);
            }
        }

        if ($won && $resultado->completada) {
            $log[] = "¡Has desmantelado a {$faccion->nombre}! El hábitat ha desaparecido.";
        } elseif ($won && $combate === ReglasHabitatTemporal::COMBATES_POR_OLEADA) {
            $log[] = '¡Jefe derrotado! Oleada completada.';
        } elseif ($won) {
            $log[] = '¡Recluta derrotado! Siguiente combate disponible.';
        } elseif ($userId === (int) Auth::id()) {
            $log[] = 'Has perdido. Puedes reintentar este combate.';
        }

        return [
            'log' => $log,
            'rewards' => $rewards,
            'habitatId' => 0,
            'completada' => $resultado->completada,
        ];
    }

    /**
     * Combate de ruta (pokémon salvajes): sin log ni bloqueo; la victoria
     * devuelve el modal con recompensas y capturas.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function procesarRuta(AgregadoBatalla $battle, array $meta): array
    {
        $habitatId = (int) ($meta['habitat_id'] ?? 0);
        $userId = (int) ($meta['user_id'] ?? 0);
        $teamId = (int) ($meta['team_id'] ?? 0);
        $won = ! $battle->team1->todosDebilitados();

        $speciesRival = $battle->team2->combatientesCollection()->map(
            fn (Combatiente $c): int => $c->speciesId(),
        );
        // Mismo orden y claves que $speciesRival: el índice del slot indica si es shiny.
        $shinyRival = $battle->team2->combatientesCollection()->map(
            fn (Combatiente $c): bool => $c->shiny(),
        );

        $operacion = fn () => app(RegistrarResultadoRuta::class)->registrar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesRival,
            won: $won,
            shinySlots: $shinyRival,
            dificultad: DificultadHabitat::para((int) ($meta['nivel'] ?? 1)),
            nivelHabitat: (int) ($meta['nivel'] ?? 1),
        );

        if ($won) {
            [$resultado, $progreso] = self::medirProgreso($userId, $operacion);
        } else {
            // Derrota: no se persisten recompensas, así que no hay snapshots.
            $resultado = $operacion();
            $progreso = null;
        }

        $rewards = $resultado !== null ? $resultado->aArray() : [];

        return [
            'log' => [],
            'rewards' => self::adjuntarProgreso($rewards, $progreso),
            'habitatId' => $habitatId,
        ];
    }

    /**
     * Captura el estado de EXP antes y después de la operación que persiste
     * las recompensas. No duplica la concesión: solo mide.
     *
     * @template T
     * @param  callable(): T  $operacion
     * @return array{0: T, 1: ?ProgresoRecompensasVista}
     */
    private static function medirProgreso(int $userId, callable $operacion): array
    {
        $builder = app(ProgresoRecompensas::class);

        $antes = $builder->capturar($userId);
        $resultado = $operacion();
        $despues = $builder->capturar($userId);

        return [$resultado, $builder->comparar($antes, $despues)];
    }

    /**
     * Adjunta `progreso` solo si hay ganancia real y hay recompensas que
     * mostrar. Si no, devuelve el array intacto (compatibilidad legacy).
     *
     * @param  array<string, mixed>  $rewards
     * @return array<string, mixed>
     */
    private static function adjuntarProgreso(array $rewards, ?ProgresoRecompensasVista $progreso): array
    {
        if ($progreso === null || $rewards === []) {
            return $rewards;
        }

        $rewards['progreso'] = $progreso->aArray();

        return $rewards;
    }

    /**
     * Serializa el modal de victoria al shape de la vista.
     *
     * @return array{exp_total: int, exp_miembro: int, caramelos: list<array{nombre: string, imagen: string, cantidad: int}>, medalla?: string}
     */
    private static function modalAVista(DatosModalVictoria $datos): array
    {
        $caramelos = [];
        foreach ($datos->caramelos as $caramelo) {
            if ($caramelo instanceof ItemCaramelo) {
                $caramelos[] = [
                    'nombre' => $caramelo->nombre,
                    'imagen' => $caramelo->imagen,
                    'cantidad' => $caramelo->cantidad,
                ];
            }
        }

        $data = [
            'exp_total' => $datos->expTotal,
            'exp_miembro' => $datos->expMiembro,
            'caramelos' => $caramelos,
        ];

        if ($datos->medalla !== null) {
            $data['medalla'] = $datos->medalla;
        }

        return $data;
    }

    /**
     * Otorga recompensas y las devuelve en formato modal (null si no hay
     * rivales válidos que recompensar → la vista no muestra el modal).
     */
    private static function otorgarRecompensas(
        int $userId,
        int $teamId,
        AgregadoBatalla $battle,
        int $nivel,
        float $multiplicador = OtorgarRecompensasEntrenador::MULTIPLICADOR_ENTRENADOR,
        ?MultiplicadoresDificultad $dificultad = null,
        ?MapaSendaJugador $senda = null,
    ): ?DatosModalVictoria {
        $speciesRival = $battle->team2->combatientesCollection()->map(
            fn (Combatiente $c): int => $c->speciesId(),
        );

        return app(OtorgarRecompensasEntrenador::class)->otorgar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesRival,
            nivelEntrenador: $nivel,
            multiplicador: $multiplicador,
            dificultad: $dificultad,
            senda: $senda,
        );
    }
}
