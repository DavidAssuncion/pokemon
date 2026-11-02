<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use App\Support\BattleSessionService;
use App\Support\CargarMapaSendaJugador;
use Illuminate\Support\Facades\Auth;
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
     * (entrenador, gimnasio o mazmorra).
     *
     * @return array{log: list<string>, rewards: array<string, mixed>, habitatId: int}
     */
    public static function procesar(
        AgregadoBatalla $battle,
        string $battleId,
        BattleSessionService $session,
    ): array {
        $meta = $session->cargarMeta($battleId);
        if ($meta === null) {
            return ['log' => [], 'rewards' => [], 'habitatId' => 0];
        }

        $tipo = $meta['tipo'] ?? null;

        $resultado = match ($tipo) {
            'gimnasio' => self::procesarGimnasio($battle, $meta),
            'mazmorra' => self::procesarMazmorra($battle, $meta),
            'ruta' => self::procesarRuta($battle, $meta),
            default => self::procesarEntrenador($battle, $meta),
        };

        $session->limpiarMeta($battleId);

        return $resultado;
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

            $datosModal = self::otorgarRecompensas(
                userId: $userId,
                teamId: (int) ($meta['team_id'] ?? 0),
                battle: $battle,
                nivel: (int) ($meta['nivel'] ?? 0),
                dificultad: MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilEntrenador((int) ($meta['nivel'] ?? 1))),
                senda: $senda,
            );

            if ($datosModal !== null) {
                $rewards = $datosModal->toArray();
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

        $repositorio = app(GymCatalogoRepositoryInterface::class);

        try {
            $gimnasio = $repositorio->obtenerPorSlugOrFail($gymId);
            $dificultad = MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilGimnasio(
                $repositorio->indiceDe($gymId),
                $stage === 4,
            ));
        } catch (GimnasioNoExiste) {
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

            $datosModal = self::otorgarRecompensas(
                userId: $userId,
                teamId: $teamId,
                battle: $battle,
                nivel: $nivelRival,
                multiplicador: OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO,
                dificultad: $dificultad,
                senda: $senda,
            );

            if ($datosModal !== null) {
                if ($resultado->medalla !== null) {
                    $datosModal = $datosModal->conMedalla($resultado->medalla);
                }
                $rewards = $datosModal->toArray();
            }
        }

        return ['log' => [], 'rewards' => $rewards, 'habitatId' => 0];
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
            $datosModal = app(OtorgarRecompensasMazmorra::class)->otorgar(
                userId: $userId,
                teamId: (int) ($meta['team_id'] ?? 0),
                battle: $battle,
                nivel: (int) ($meta['nivel_rival'] ?? 0),
            );

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

                $rewards = [
                    'exp_total' => $datosModal->expTotal,
                    'exp_miembro' => $datosModal->expMiembro,
                    'caramelos' => $caramelos,
                ];
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

        $resultado = app(RegistrarResultadoRuta::class)->registrar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesRival,
            won: $won,
            shinySlots: $shinyRival,
            dificultad: DificultadHabitat::para((int) ($meta['nivel'] ?? 1)),
            nivelHabitat: (int) ($meta['nivel'] ?? 1),
        );

        return [
            'log' => [],
            'rewards' => $resultado !== null ? $resultado->aArray() : [],
            'habitatId' => $habitatId,
        ];
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
