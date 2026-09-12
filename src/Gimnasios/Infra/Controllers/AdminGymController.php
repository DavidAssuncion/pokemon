<?php

declare(strict_types=1);

namespace Src\Gimnasios\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Tipos\TipoPokemon;

/**
 * CRUD admin de gimnasios. NO existe borrado ni desactivación: solo crear,
 * editar datos fijos y editar etapas (requisito 1.4).
 */
class AdminGymController extends Controller
{
    public function __construct(
        private readonly GymCatalogoRepositoryInterface $catalogo,
    ) {
    }

    public function index(): JsonResponse
    {
        $gimnasios = array_map(
            fn ($gimnasio): array => $this->serializar($gimnasio),
            $this->catalogo->obtenerTodos(),
        );

        return response()->json($gimnasios);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json($this->serializar($this->catalogo->obtenerPorSlugOrFail($slug)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/'],
            'medalla' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'integer', Rule::in(array_map(fn ($t) => $t->value, TipoPokemon::cases()))],
            'nivel_minimo' => ['required', 'integer', 'min:1'],
            'etapas' => ['sometimes', 'array'],
            'etapas.*.vanguardia' => ['sometimes', 'array'],
            'etapas.*.vanguardia.*' => ['integer', 'min:1'],
            'etapas.*.retaguardia' => ['sometimes', 'array'],
            'etapas.*.retaguardia.*' => ['integer', 'min:1'],
        ]);

        $gimnasio = $this->catalogo->insertar(
            slug: (string) $data['slug'],
            medalla: (string) $data['medalla'],
            tipo: TipoPokemon::from((int) $data['tipo']),
            nivelMinimo: (int) $data['nivel_minimo'],
            equipos: $this->equiposDesdeValidated((array) ($data['etapas'] ?? [])),
        );

        return response()->json($this->serializar($gimnasio), 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'medalla' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'integer', Rule::in(array_map(fn ($t) => $t->value, TipoPokemon::cases()))],
            'nivel_minimo' => ['required', 'integer', 'min:1'],
        ]);

        $gimnasio = $this->catalogo->actualizarDatos(
            slug: $slug,
            medalla: (string) $data['medalla'],
            tipo: TipoPokemon::from((int) $data['tipo']),
            nivelMinimo: (int) $data['nivel_minimo'],
        );

        return response()->json($this->serializar($gimnasio));
    }

    public function updateStage(Request $request, string $slug, int $etapa): JsonResponse
    {
        $data = $request->validate([
            'vanguardia' => ['sometimes', 'array'],
            'vanguardia.*' => ['integer', 'min:1'],
            'retaguardia' => ['sometimes', 'array'],
            'retaguardia.*' => ['integer', 'min:1'],
        ]);

        $etapa = max(1, min(4, $etapa));
        $gimnasio = $this->catalogo->obtenerPorSlugOrFail($slug);
        $equipos = $gimnasio->equipos;

        $equipos[$etapa] = new EquipoEtapaGimnasio(
            vanguardia: new IntCollection(array_map('intval', (array) ($data['vanguardia'] ?? []))),
            retaguardia: new IntCollection(array_map('intval', (array) ($data['retaguardia'] ?? []))),
        );

        $this->catalogo->actualizarEtapas($slug, $equipos);

        return response()->json($this->serializarEquipoEtapa($etapa, $equipos[$etapa]));
    }

    /**
     * @param  array<int, array{vanguardia?: list<int>, retaguardia?: list<int>}>  $etapas
     * @return array<int, EquipoEtapaGimnasio>
     */
    private function equiposDesdeValidated(array $etapas): array
    {
        $equipos = [];

        foreach ($etapas as $etapa => $datos) {
            $etapaInt = (int) $etapa;
            if ($etapaInt < 1 || $etapaInt > 4) {
                continue;
            }

            $equipos[$etapaInt] = new EquipoEtapaGimnasio(
                vanguardia: new IntCollection(array_map('intval', $datos['vanguardia'] ?? [])),
                retaguardia: new IntCollection(array_map('intval', $datos['retaguardia'] ?? [])),
            );
        }

        return $equipos;
    }

    private function serializar(\Src\Gimnasios\Domain\Gimnasio $gimnasio): array
    {
        $etapas = [];
        foreach ($gimnasio->equipos as $etapa => $equipo) {
            $etapas[$etapa] = [
                'vanguardia' => $equipo->vanguardia->all(),
                'retaguardia' => $equipo->retaguardia->all(),
            ];
        }

        return [
            'slug' => $gimnasio->slug,
            'medalla' => $gimnasio->medalla,
            'tipo' => $gimnasio->tipo->value,
            'nivel_minimo' => $gimnasio->nivelMinimo,
            'etapas' => $etapas,
        ];
    }

    private function serializarEquipoEtapa(int $etapa, EquipoEtapaGimnasio $equipo): array
    {
        return [
            'etapa' => $etapa,
            'vanguardia' => $equipo->vanguardia->all(),
            'retaguardia' => $equipo->retaguardia->all(),
        ];
    }
}
