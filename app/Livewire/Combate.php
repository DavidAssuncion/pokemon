<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Combate\Soporte\OrquestadorBatalla;
use Livewire\Component;
use Src\Battle\Domain\Enums\FaseCombate;

class Combate extends Component
{
    public string $battleId = '';
    public array $team1 = [];
    public array $team2 = [];
    public array $turnQueue = [];
    public array $currentMoves = [];
    public ?int $selectedMoveIdx = null;
    public string $phase = FaseCombate::INICIO->value;
    public int $round = 0;
    public array $log = [];
    public string $actingRefId = '';
    public bool $processing = false;
    public string $animAttackerId = '';
    public string $animDefenderId = '';
    public string $animAttackerNombre = '';
    public string $animDefenderNombre = '';
    public string $animMoveNombre = '';
    public int $animTick = 0;
    public string $weather = 'none';
    public array $rewards = [];
    public int $habitatId = 0;
    /** @var list<array<string, mixed>> */
    public array $eventos = [];
    public int $eventoTick = 0;
    public ?int $selectedTargetTeam = null;
    public ?int $selectedTargetIdx = null;
    public string $selectedTargetRefId = '';
    /** @var array<string, string> refId => índice del movimiento prioritario elegido */
    public array $prioridades = [];
    /** @var array<string, string> refId actor => refId del rival objetivo de su prioridad ('' = Automático) */
    public array $prioridadesObjetivo = [];
    /** @var array<string, string> refId cubridor => refId del aliado cubierto ('' = Automático) */
    public array $coberturas = [];
    /** @var array<string, string> refId => postura elegida para la ronda ('atacar'|'guardia'|'cubrir') */
    public array $posturas = [];
    /** Panel de preparación de ronda visible (no bloquea el turno: jugar lo omite). */
    public bool $preparacionPendiente = false;
    /** @var array<string, list<array{index: int, nombre: string, prioridad: int, autodirigido: bool, objetivoForzadoRefId: string, objetivoForzadoNombre: string, objetivos: list<array{refId: string, nombre: string, posicion: string}>}>> */
    public array $prioridadesDisponibles = [];
    /** @var list<array<string, mixed>> Opciones del panel de objetos de combate. */
    public array $objetosCombate = [];
    /** Clave de inventario del objeto seleccionado para usar. */
    public string $objetoSeleccionado = '';
    /** Objetivo de item activo (`ObjetivosItem`->value) o cadena vacía. */
    public string $objetivosItem = '';

    private OrquestadorBatalla $orquestador;

    private bool $grabandoEventos = false;

    public function nuevaBatalla(): void
    {
        $this->orquestador->nuevaBatalla($this);
    }

    public function boot(): void
    {
        $this->orquestador ??= app(OrquestadorBatalla::class);
    }

    public function mount(): void
    {
        $this->orquestador->montar($this);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.combate', $this->orquestador->datosVista($this))
            ->extends('layouts.app')
            ->section('content');
    }

    public function nextActor(): void
    {
        $this->orquestador->nextActor($this, $this->grabandoEventos);
    }

    public function confirmarPreparacion(): void
    {
        $this->orquestador->confirmarPreparacion($this);
    }

    public function saltarPreparacion(): void
    {
        $this->orquestador->saltarPreparacion($this);
    }

    public function cambiarPostura(string $refId, string $postura): void
    {
        $this->orquestador->cambiarPostura($this, $refId, $postura);
    }

    public function commitAction(): void
    {
        $this->orquestador->commitAction($this, $this->grabandoEventos);
    }

    public function previewTarget(int $teamIdx, int $pokemonIdx): void
    {
        $this->orquestador->previewTarget($this, $teamIdx, $pokemonIdx);
    }

    public function selectMove(int $index): void
    {
        $this->orquestador->selectMove($this, $index);
    }

    public function cancelTarget(): void
    {
        $this->orquestador->cancelTarget($this);
    }

    public function seleccionarObjeto(string $clave): void
    {
        $this->orquestador->seleccionarObjeto($this, $clave);
    }

    public function cancelarObjeto(): void
    {
        $this->orquestador->cancelarObjeto($this);
    }

    public function confirmarObjetivoItem(int $teamIdx, int $pokemonIdx): void
    {
        $this->orquestador->confirmarObjetivoItem($this, $teamIdx, $pokemonIdx);
    }

    public function swapTurno(): void
    {
        $this->orquestador->swapTurno($this);
    }

    public function retrasarTurno(): void
    {
        $this->orquestador->retrasarTurno($this);
    }

    public function pasarTurno(): void
    {
        $this->orquestador->pasarTurno($this);
    }
}
