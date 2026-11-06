<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use App\Livewire\Combate\Soporte\DTO\SnapshotCombatiente;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Captura instantáneas del agregado y construye los eventos de animación que
 * consume el cliente. No guarda estado: tick y secuencia los aporta el
 * componente, que sigue siendo dueño de `eventos` y `eventoTick`.
 */
final class GrabadorEventosBatalla
{
    public function capturar(AgregadoBatalla $battle): SnapshotBatalla
    {
        $estado = [];

        foreach ($battle->turnManager()->allCombatants() as $combatiente) {
            $estado[$combatiente->id()] = new SnapshotCombatiente(
                hp: $combatiente->hpActual(),
                defHp: $combatiente->defensaHpActual(),
                spDefHp: $combatiente->defensaEspHpActual(),
                alive: $combatiente->estaVivo(),
                status: $combatiente->estado()->value,
                statusTurns: $combatiente->turnosEstado(),
            );
        }

        return new SnapshotBatalla($estado);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>|null
     */
    public function registrar(
        string $tipo,
        SnapshotBatalla $antes,
        SnapshotBatalla $despues,
        int $tick,
        int $secuencia,
        array $extra = [],
    ): ?array {
        $cambios = [];
        $muertos = [];

        foreach ($despues->todos() as $refId => $actual) {
            $previo = $antes->obtener($refId);
            if ($previo === null) {
                continue;
            }

            $delta = [
                'hp' => round($actual->hp - $previo->hp, 2),
                'def' => round($actual->defHp - $previo->defHp, 2),
                'spdef' => round($actual->spDefHp - $previo->spDefHp, 2),
            ];

            $cambioVida = abs($delta['hp']) > 0.001
                || abs($delta['def']) > 0.001
                || abs($delta['spdef']) > 0.001;
            $cambioEstado = $actual->status !== $previo->status
                || $actual->statusTurns !== $previo->statusTurns;

            if (! $cambioVida && ! $cambioEstado) {
                continue;
            }

            $rotas = [];
            if ($previo->defHp > 0 && $actual->defHp <= 0) {
                $rotas[] = 'def';
            }
            if ($previo->spDefHp > 0 && $actual->spDefHp <= 0) {
                $rotas[] = 'spdef';
            }

            $cambios[$refId] = [
                'previo' => $previo->toArray(),
                'actual' => $actual->toArray(),
                'delta' => $delta,
                'rotas' => $rotas,
            ];

            if ($previo->alive && ! $actual->alive) {
                $muertos[] = $refId;
            }
        }

        $forzar = (bool) ($extra['forzar'] ?? false);
        unset($extra['forzar']);

        if ($cambios === [] && ! $forzar) {
            return null;
        }

        return array_merge([
            'id' => $tick.'.'.$secuencia,
            'tipo' => $tipo,
            'cambios' => $cambios,
            'muertos' => $muertos,
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function eventoFin(SnapshotBatalla $estado, int $tick, int $secuencia): array
    {
        return [
            'id' => $tick.'.'.$secuencia,
            'tipo' => 'fin',
            'cambios' => [],
            'muertos' => [],
            'estado' => $estado->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventoBatallaTerminada(string $texto, bool $victoria, int $tick, int $secuencia): array
    {
        return [
            'id' => $tick.'.'.$secuencia,
            'tipo' => 'battle_over',
            'cambios' => [],
            'muertos' => [],
            'victoria' => $victoria,
            'texto' => $texto,
        ];
    }

    /**
     * Genera el texto de log de los cambios de estado alterado entre dos
     * instantáneas. Ignora `statusTurns` para no narrar el avance del sueño.
     */
    public function cambiosDeEstadoEnLog(
        AgregadoBatalla $battle,
        SnapshotBatalla $antes,
        string $prefijo = '',
    ): ?string {
        $despues = $this->capturar($battle);
        $mensajes = [];

        foreach ($battle->turnManager()->allCombatants() as $combatiente) {
            $refId = $combatiente->id();
            $previo = $antes->obtener($refId);
            $actual = $despues->obtener($refId);
            if ($previo === null || $actual === null || $previo->status === $actual->status) {
                continue;
            }

            $estadoPrevio = EstadoPokemon::tryFrom($previo->status);
            $estadoActual = EstadoPokemon::tryFrom($actual->status);
            if ($estadoActual === null) {
                continue;
            }

            $mensajes[] = $prefijo.match (true) {
                $estadoPrevio === EstadoPokemon::NONE => "{$combatiente->nombre()} sufre {$estadoActual->label()}!",
                $estadoActual === EstadoPokemon::NONE && $estadoPrevio !== null => "{$combatiente->nombre()} se recupera de {$estadoPrevio->label()}!",
                default => "{$combatiente->nombre()} sufre {$estadoActual->label()}!",
            };
        }

        return $mensajes === [] ? null : implode(' ', $mensajes);
    }
}
