<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use App\Models\Habitat;
use App\Support\BattleSessionService;
use Illuminate\Support\Facades\Auth;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador;
use Src\CombateEntrenadores\App\RegistrarResultadoEntrenador;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;
use Src\Gimnasios\App\RegistrarResultadoGimnasio;
use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Mazmorras\App\RegistrarResultadoMazmorra;
use Src\Mazmorras\Domain\ConfiguracionMazmorra;

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
        $won = ! $battle->team1->todosDebilitados();
        $rewards = [];

        app(RegistrarResultadoEntrenador::class)->registrar(
            habitatId: $habitatId,
            nivel: (int) ($meta['nivel'] ?? 0),
            trainerIndex: (int) ($meta['trainer_index'] ?? 0),
            userId: (int) ($meta['user_id'] ?? 0),
            fecha: (string) ($meta['fecha'] ?? today()->toDateString()),
            won: $won,
        );

        if ($won) {
            $datosModal = self::otorgarRecompensas(
                userId: (int) ($meta['user_id'] ?? 0),
                teamId: (int) ($meta['team_id'] ?? 0),
                battle: $battle,
                nivel: (int) ($meta['nivel'] ?? 0),
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

        $gimnasio = app(CatalogoGimnasios::class)->porSlug($gymId);

        $resultado = app(RegistrarResultadoGimnasio::class)->registrar(
            gymId: $gymId,
            etapaCompletada: $stage,
            userId: $userId,
            won: $won,
            authUserId: (int) Auth::id(),
            nombreMedalla: $gimnasio?->medalla,
        );

        if ($won && $resultado->avance) {
            $datosModal = self::otorgarRecompensas(
                userId: $userId,
                teamId: $teamId,
                battle: $battle,
                nivel: $nivelRival,
                multiplicador: OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO,
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

        $habitat = Habitat::query()->find($habitatId);
        $config = $habitat !== null
            ? ConfiguracionMazmorra::desdeJson($habitat->mazmorra)
            : null;

        $totalPisos = $config !== null ? $config->totalPisos() : 0;

        $resultado = app(RegistrarResultadoMazmorra::class)->registrar(
            habitatId: $habitatId,
            piso: $floor,
            totalPisos: $totalPisos,
            userId: $userId,
            won: $won,
            authUserId: (int) Auth::id(),
        );

        if ($won && $resultado->completado) {
            $log[] = '¡Has completado la mazmorra!';
        } elseif ($won) {
            $log[] = '¡Piso superado! El siguiente piso ya está disponible.';
        } elseif (! $resultado->avance && $userId === (int) Auth::id()) {
            $log[] = 'Has perdido. Podrás reintentar el piso en 1 hora.';
        }

        return ['log' => $log, 'rewards' => [], 'habitatId' => 0];
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
        );
    }
}
