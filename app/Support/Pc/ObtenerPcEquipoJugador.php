<?php

declare(strict_types=1);

namespace App\Support\Pc;

use App\Models\Reclutado;
use App\Models\Team;
use App\Support\Piezas\FabricaPiezasReclutado;
use Src\Shared\Domain\ReglasEquipo;

/**
 * Carga el equipo del jugador y calcula su PC agregado al nivel actual.
 *
 * La consulta se acota explícitamente por `user_id` (además del scope global
 * de `BelongsToUser`) para impedir accesos a equipos ajenos.
 */
final class ObtenerPcEquipoJugador
{
    public function __construct(
        private readonly FabricaPiezasReclutado $fabricaPiezas,
    ) {
    }

    public function obtener(?int $teamId, int $userId, int $nivelJugador): PcEquipoJugador
    {
        if ($teamId === null) {
            return new PcEquipoJugador(miembros: 0, pc: null, completo: false);
        }

        $team = Team::query()
            ->with('members.reclutado.pokemon.stats')
            ->where('user_id', $userId)
            ->find($teamId);

        if ($team === null) {
            return new PcEquipoJugador(miembros: 0, pc: null, completo: false);
        }

        $reclutados = [];

        foreach ($team->members->sortBy('slot') as $member) {
            if ($member->reclutado instanceof Reclutado) {
                $reclutados[] = $member->reclutado;
            }
        }

        $piezas = $this->fabricaPiezas->paraListado($userId, $reclutados);

        $miembros = 0;
        $total = 0;

        foreach ($reclutados as $reclutado) {
            $cp = CalculadoraPcReclutado::calcular(
                $reclutado,
                $nivelJugador,
                $piezas->bonosDe($reclutado->id),
            );

            if ($cp === null) {
                continue;
            }

            $total += $cp;
            $miembros++;
        }

        $completo = $miembros === ReglasEquipo::MIEMBROS;

        return new PcEquipoJugador(
            miembros: $miembros,
            pc: $completo ? $total : null,
            completo: $completo,
        );
    }
}
