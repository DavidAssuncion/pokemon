<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Gestiona el panel de preparación de ronda: posturas, prioridades y el
 * salto automático al jugar. Sin estado: los datos viven en el componente.
 */
final class GestorPreparacionBatalla
{
    use Concerns\ObjetivosBatalla;

    public function __construct(
        private readonly BattleSessionService $session,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAnimacionBatalla $gestorAnimacion,
        private readonly GestorPrioridadesBatalla $gestorPrioridades,
        private readonly ColaTurnosBatalla $colaTurnos,
    ) {
    }

    public function abrir(Combate $componente, AgregadoBatalla $battle): void
    {
        $componente->preparacionPendiente = true;
        $componente->prioridades = [];
        $componente->prioridadesDisponibles = [];
        $componente->posturas = [];
        $componente->prioridadesObjetivo = [];
        $componente->coberturas = [];

        foreach ($battle->team1->combatientesCollection()->vivos() as $combatiente) {
            $id = $combatiente->id();
            $componente->prioridades[$id] = '';
            $componente->posturas[$id] = $combatiente->postura()->value;
            $componente->prioridadesObjetivo[$id] = '';
            $componente->coberturas[$id] = $combatiente->postura() === PosturaCombate::CUBRIR
                ? $combatiente->objetivoCubiertoId()
                : '';

            $objetivos = $this->objetivosLegalesPara($battle, $combatiente);
            $objetivoForzado = $this->objetivoForzadoDesafio($battle, $combatiente);

            $disponibles = [];
            foreach ($combatiente->pokemon()->moves()->all() as $index => $movimiento) {
                if ($movimiento->priority <= 0 || ! $combatiente->movimientoDisponible($movimiento)) {
                    continue;
                }

                $disponibles[] = [
                    'index' => $index,
                    'nombre' => $movimiento->nombre,
                    'prioridad' => $movimiento->priority,
                    'autodirigido' => $this->esAutodirigido($movimiento),
                    'objetivoForzadoRefId' => $objetivoForzado?->id() ?? '',
                    'objetivoForzadoNombre' => $objetivoForzado?->nombre() ?? '',
                    'objetivos' => $objetivos,
                ];
            }

            if ($disponibles !== []) {
                $componente->prioridadesDisponibles[$id] = $disponibles;
            }
        }

        $this->gestorVista->sincronizar($componente, $battle);
        $componente->turnQueue = $this->colaTurnos->construir(
            $battle,
            $componente->actingRefId,
            $componente->team1,
            $componente->team2,
        );
        $this->gestorAnimacion->reiniciar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->processing = false;
    }

    public function confirmar(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $this->aplicar($componente, $battle, true);

        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación confirmada ---";

        $componente->phase = FaseCombate::INICIO->value;
        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    public function saltar(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $this->aplicar($componente, $battle, false);

        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida ---";

        $componente->phase = FaseCombate::INICIO->value;
        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();
    }

    public function autoSaltar(
        Combate $componente,
        AgregadoBatalla $battle,
        ContextoEjecucionBatalla $contexto,
    ): void {
        if (! $componente->preparacionPendiente) {
            return;
        }

        $this->aplicar($componente, $battle, false);
        $componente->preparacionPendiente = false;
        $componente->log[] = "--- Ronda {$battle->turnManager()->round()}: preparación omitida al jugar ---";

        $this->gestorVista->sincronizar($componente, $battle);
        $this->gestorPrioridades->resolver($componente, $battle, $contexto);
        $this->limpiar($componente);
        $this->session->guardar($componente->battleId, $battle);
    }

    public function cambiarPostura(Combate $componente, string $refId, string $postura): void
    {
        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null || ! $componente->preparacionPendiente
            || $componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        $combatiente = $battle->team1->findCombatantById($refId);
        if ($combatiente === null || ! $combatiente->estaVivo()) {
            return;
        }

        $nuevaPostura = PosturaCombate::tryFrom($postura) ?? PosturaCombate::ATACAR;
        $combatiente->setPostura($nuevaPostura);
        $battle->team1->normalizarCubridores();

        $componente->posturas[$refId] = $nuevaPostura->value;
        $componente->coberturas[$refId] = $nuevaPostura === PosturaCombate::CUBRIR
            ? $combatiente->objetivoCubiertoId()
            : '';

        if ($nuevaPostura !== PosturaCombate::ATACAR) {
            // Una postura defensiva no puede usar movimientos de prioridad.
            $componente->prioridades[$refId] = '';
            $componente->prioridadesObjetivo[$refId] = '';
        }

        $this->gestorVista->sincronizar($componente, $battle);
        $this->session->guardar($componente->battleId, $battle);
    }

    private function aplicar(Combate $componente, AgregadoBatalla $battle, bool $conPrioridades): void
    {
        $equipoJugador = $battle->team1;
        $vivosJugador = $equipoJugador->combatientesCollection()->vivos()->all();

        foreach ($vivosJugador as $combatiente) {
            $id = $combatiente->id();
            $postura = PosturaCombate::tryFrom($componente->posturas[$id] ?? '')
                ?? PosturaCombate::ATACAR;
            $combatiente->setPostura($postura);

            $prioridad = null;
            if ($conPrioridades) {
                $indicePrioridad = $componente->prioridades[$id] ?? '';
                if ($indicePrioridad !== '') {
                    $candidato = $combatiente->pokemon()->moves()->get((int) $indicePrioridad);
                    if ($candidato instanceof MovimientoBatalla
                        && $candidato->priority > 0
                        && $combatiente->movimientoDisponible($candidato)) {
                        $prioridad = $candidato;
                    }
                }
            }

            if ($prioridad !== null) {
                $this->validarObjetivoPrioridad($componente, $battle, $combatiente, $prioridad);

                // Usar una prioridad es una acción de ataque: anula Guardia/Cubrir.
                $combatiente->setPostura(PosturaCombate::ATACAR);
                $combatiente->setObjetivoCubiertoId('');
                $componente->coberturas[$id] = '';
                $combatiente->declararPrioridad($prioridad);

                continue;
            }

            $combatiente->limpiarPrioridad();
            $componente->prioridadesObjetivo[$id] = '';

            $this->aplicarCoberturaElegida($componente, $battle, $combatiente);
        }

        $equipoJugador->normalizarCubridores();

        foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
            $rival->limpiarPrioridad();
        }

        $battle->team2->normalizarCubridores();
        $this->gestorPrioridades->declararIa($battle);

        $battle->setRondaPreparada($battle->turnManager()->round());
    }

    /**
     * Valida el objetivo declarado para una prioridad y lo limpia si no es un
     * rival vivo y legal por fila. Los movimientos autodirigidos lo ignoran.
     */
    private function validarObjetivoPrioridad(
        Combate $componente,
        AgregadoBatalla $battle,
        Combatiente $actor,
        MovimientoBatalla $movimiento,
    ): void {
        $id = $actor->id();

        if ($this->esAutodirigido($movimiento)) {
            $componente->prioridadesObjetivo[$id] = '';

            return;
        }

        $declarado = $this->textoMapa($componente->prioridadesObjetivo[$id] ?? null);
        if ($declarado === '') {
            return;
        }

        $objetivo = $battle->equipoRivalDe($actor)?->findCombatantById($declarado);
        $legal = $objetivo !== null
            && $objetivo->estaVivo()
            && $battle->objetivosRivalesLegalesPara($actor)
                ->contains(fn (Combatiente $rival): bool => $rival->id() === $objetivo->id());

        if (! $legal) {
            $componente->prioridadesObjetivo[$id] = '';
        }
    }

    /**
     * Aplica el aliado cubierto elegido en preparación. Un valor vacío o
     * inválido se limpia para que normalizarCubridores() asigne el fallback.
     */
    private function aplicarCoberturaElegida(
        Combate $componente,
        AgregadoBatalla $battle,
        Combatiente $combatiente,
    ): void {
        $id = $combatiente->id();

        if ($combatiente->postura() !== PosturaCombate::CUBRIR) {
            $combatiente->setObjetivoCubiertoId('');
            $componente->coberturas[$id] = '';

            return;
        }

        if (! array_key_exists($id, $componente->coberturas)) {
            return;
        }

        $ref = $this->textoMapa($componente->coberturas[$id]);
        if ($ref === '') {
            $combatiente->setObjetivoCubiertoId('');

            return;
        }

        $objetivo = $battle->team1->findCombatantById($ref);
        if ($objetivo === null || $objetivo->id() === $id || ! $objetivo->estaVivo()) {
            $combatiente->setObjetivoCubiertoId('');

            return;
        }

        $combatiente->setObjetivoCubiertoId($objetivo->id());
    }

    /**
     * Rivales vivos legales por fila, en el formato que consume la vista.
     *
     * @return list<array{refId: string, nombre: string, posicion: string}>
     */
    private function objetivosLegalesPara(AgregadoBatalla $battle, Combatiente $actor): array
    {
        return $battle->objetivosRivalesLegalesPara($actor)->map(
            static fn (Combatiente $objetivo): array => [
                'refId' => $objetivo->id(),
                'nombre' => $objetivo->nombre(),
                'posicion' => $objetivo->posicion()->value,
            ],
        );
    }

    private function limpiar(Combate $componente): void
    {
        $componente->prioridades = [];
        $componente->prioridadesDisponibles = [];
        $componente->posturas = [];
        $componente->prioridadesObjetivo = [];
        $componente->coberturas = [];
    }
}
