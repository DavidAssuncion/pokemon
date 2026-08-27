<?php

declare(strict_types=1);

namespace App\Livewire\Habitats;

use Livewire\Attributes\On;
use Livewire\Component;
use Src\Habitats\App\AsignarFamiliaAHabitat;
use Src\Habitats\App\EliminarFamiliaDeHabitat;
use Src\Habitats\App\ObtenerFamiliasDisponibles;
use Src\Habitats\App\ObtenerFamiliasSinHabitat;

class FamilyModal extends Component
{
    public int $habitatId = 0;

    public bool $showModal = false;

    public string $activeTab = 'add';

    /** @var array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string, level: int}, evolutions: array<int, array{id: int, name: string, icon: string, level: int}}> */
    public array $assignedFamilies = [];

    /** @var array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string}, evolutions: array<int, array{id: int, name: string, icon: string}}> */
    public array $availableFamilies = [];

    /** @var array<int, array{evolution_chain_id: int, base: array{id: int, name: string, icon: string}, evolutions: array<int, array{id: int, name: string, icon: string}}> */
    public array $unassignedFamilies = [];

    public bool $loading = false;

    public ?string $toastMessage = null;

    public string $toastType = 'success';

    /** @var array<int, array<int, array{id: int, name: string, isNew: bool, isRemoved: bool}>> */
    public array $levelPreview = [1 => [], 2 => [], 3 => []];

    private ObtenerFamiliasDisponibles $obtenerFamiliasDisponibles;

    private ObtenerFamiliasSinHabitat $obtenerFamiliasSinHabitat;

    private AsignarFamiliaAHabitat $asignarFamiliaAHabitat;

    private EliminarFamiliaDeHabitat $eliminarFamiliaDeHabitat;

    public function boot(
        ObtenerFamiliasDisponibles $obtenerFamiliasDisponibles,
        ObtenerFamiliasSinHabitat $obtenerFamiliasSinHabitat,
        AsignarFamiliaAHabitat $asignarFamiliaAHabitat,
        EliminarFamiliaDeHabitat $eliminarFamiliaDeHabitat,
    ): void {
        $this->obtenerFamiliasDisponibles = $obtenerFamiliasDisponibles;
        $this->obtenerFamiliasSinHabitat = $obtenerFamiliasSinHabitat;
        $this->asignarFamiliaAHabitat = $asignarFamiliaAHabitat;
        $this->eliminarFamiliaDeHabitat = $eliminarFamiliaDeHabitat;
    }

    public function mount(int $habitatId): void
    {
        $this->habitatId = $habitatId;
    }

    #[On('openFamilyModal')]
    public function openModal(): void
    {
        $this->showModal = true;
        $this->loadFamilies();
        $this->loadUnassignedFamilies();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['activeTab', 'toastMessage', 'levelPreview']);
        $this->levelPreview = [1 => [], 2 => [], 3 => []];
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function loadFamilies(): void
    {
        $this->loading = true;

        try {
            $data = $this->obtenerFamiliasDisponibles->handle($this->habitatId)->toArray();

            $this->assignedFamilies = array_map(fn ($f) => [
                'evolution_chain_id' => $f['evolution_chain_id'],
                'base' => $f['base'],
                'evolutions' => $f['evolutions'] ?? [],
                'total_stages' => 1 + count($f['evolutions'] ?? []),
            ], $data);

            // La tab "available" ya no aplica: el backend distingue asignadas vs sin hábitat
            $this->availableFamilies = [];

            $this->updateLevelPreview();
        } catch (\Throwable $e) {
            $this->showToast('Error al cargar familias: ' . $e->getMessage(), 'error');
        } finally {
            $this->loading = false;
        }
    }

    public function loadUnassignedFamilies(): void
    {
        try {
            $data = $this->obtenerFamiliasSinHabitat->handle()->toArray();

            $this->unassignedFamilies = array_map(fn ($f) => [
                'evolution_chain_id' => $f['evolution_chain_id'],
                'base' => $f['base'],
                'evolutions' => $f['evolutions'] ?? [],
                'total_stages' => 1 + count($f['evolutions'] ?? []),
            ], $data);
        } catch (\Throwable $e) {
            $this->showToast('Error al cargar familias disponibles: ' . $e->getMessage(), 'error');
        }
    }

    public function assign(int $chainId): void
    {
        $this->loading = true;

        try {
            $this->asignarFamiliaAHabitat->handle($this->habitatId, $chainId);
            $this->showToast('Familia asignada correctamente', 'success');
            $this->loadFamilies();
            $this->loadUnassignedFamilies();
            $this->dispatch('families-updated');
        } catch (\Throwable $e) {
            $this->showToast('Error al asignar: ' . $e->getMessage(), 'error');
        } finally {
            $this->loading = false;
        }
    }

    public function remove(int $chainId): void
    {
        $this->loading = true;

        try {
            $this->eliminarFamiliaDeHabitat->handle($this->habitatId, $chainId);
            $this->showToast('Familia eliminada correctamente', 'success');
            $this->loadFamilies();
            $this->loadUnassignedFamilies();
            $this->dispatch('families-updated');
        } catch (\Throwable $e) {
            $this->showToast('Error al eliminar: ' . $e->getMessage(), 'error');
        } finally {
            $this->loading = false;
        }
    }

    public function refresh(): void
    {
        $this->loadFamilies();
    }

    private function updateLevelPreview(): void
    {
        $preview = [1 => [], 2 => [], 3 => []];

        foreach ($this->assignedFamilies as $family) {
            $base = $family['base'];
            $evolutions = $family['evolutions'] ?? [];

            // Base pokemon at its level
            $baseLevel = $base['level'] ?? 2;
            if (isset($preview[$baseLevel])) {
                $preview[$baseLevel][] = [
                    'id' => $base['id'],
                    'name' => $base['name'],
                    'isNew' => false,
                    'isRemoved' => false,
                ];
            }

            // Evolutions at their levels
            foreach ($evolutions as $evo) {
                $level = $evo['level'] ?? 2;
                if (isset($preview[$level])) {
                    $preview[$level][] = [
                        'id' => $evo['id'],
                        'name' => $evo['name'],
                        'isNew' => false,
                        'isRemoved' => false,
                    ];
                }
            }
        }

        $this->levelPreview = $preview;
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->toastMessage = $message;
        $this->toastType = $type;

        $this->dispatch('show-toast', message: $message, type: $type);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.habitats.family-modal');
    }
}
