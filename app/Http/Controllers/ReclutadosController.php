<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reclutado;
use App\Models\Team;
use Illuminate\View\View;
use Src\Equipos\Domain\TeamRepositoryInterface;
use Src\Reclutamiento\Domain\ReclutamientoRepositoryInterface;

class ReclutadosController extends Controller
{
    public function __construct(
        public readonly TeamRepositoryInterface $teamRepository,
        public readonly ReclutamientoRepositoryInterface $reclutamientoRepository,
    ) {
    }

    public function index(): View
    {
        // Use the repositories for domain access
        $this->teamRepository->obtenerTodos();
        $this->reclutamientoRepository->obtenerTodos();

        // For view compatibility, use Eloquent models directly
        return view('reclutados.index', [
            'reclutados' => Reclutado::with('pokemon')->get(),
            'teams' => Team::with('members.reclutado.pokemon')->get(),
        ]);
    }
}
