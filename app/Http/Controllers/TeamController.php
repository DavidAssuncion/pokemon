<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Equipos\App\ActualizarFormacionEquipo;
use Src\Equipos\App\ActualizarObjetosEquipo;
use Src\Equipos\App\ActualizarPosturasEquipo;
use Src\Equipos\App\ActualizarRolMiembro;
use Src\Equipos\App\AgregarMiembroEquipo;
use Src\Equipos\App\EliminarEquipo;
use Src\Equipos\App\QuitarMiembroEquipo;
use Src\Equipos\Domain\ValueObjects\ObjetosEquipo;
use Src\Shared\Domain\ReglasEquipo;

/**
 * Orquestador HTTP del módulo Equipos. La validación de entrada es del
 * controlador; las reglas de negocio viven en src/Equipos/App.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly AgregarMiembroEquipo $agregarMiembro,
        private readonly QuitarMiembroEquipo $quitarMiembro,
        private readonly ActualizarRolMiembro $actualizarRol,
        private readonly ActualizarFormacionEquipo $actualizarFormacion,
        private readonly ActualizarPosturasEquipo $actualizarPosturas,
        private readonly ActualizarObjetosEquipo $actualizarObjetosEquipo,
        private readonly EliminarEquipo $eliminarEquipo,
    ) {
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create(['name' => $data['name'], 'user_id' => Auth::id()]);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => [],
                ],
            ]);
        }

        return redirect()->back();
    }

    public function update(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        // El route-model-binding + global scope de Team ya devuelve 404 para equipos ajenos.
        $data = $request->validate(['name' => 'required|string|max:255']);
        $team->update(['name' => $data['name']]);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        $this->eliminarEquipo->ejecutar($team);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    public function addMember(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
            // El reclutado debe ser del usuario autenticado (anti-IDOR).
            'reclutado_id' => ['required', Rule::exists('reclutados', 'id')->where('user_id', Auth::id())],
            // Equipos estándar de 3 (combates 3v3 y mazmorra 3v1).
            'slot' => 'required|integer|min:'.ReglasEquipo::SLOT_MIN.'|max:'.ReglasEquipo::SLOT_MAX,
            'behavior' => 'required|in:VANGUARDIA,COMBATIENTE,RECOLECTOR,RASTREADOR',
            // Permite sustituir al miembro que ocupa el slot cuando el equipo está completo.
            'replace' => 'sometimes|boolean',
        ]);

        // El global scope de Team hace que un equipo ajeno resuelva a null:
        // findOrFail devuelve 404 (misma semántica que update/destroy).
        $team = Team::findOrFail($data['team_id']);

        $resultado = $this->agregarMiembro->ejecutar(
            team: $team,
            reclutadoId: (int) $data['reclutado_id'],
            slot: (int) $data['slot'],
            behavior: (string) $data['behavior'],
            replace: (bool) ($data['replace'] ?? false),
        );

        if ($resultado->esRechazado()) {
            return $this->responderError($request, $resultado->error ?? 'No se pudo añadir el miembro');
        }

        $member = $resultado->miembro;

        if ($request->wantsJson()) {
            return response()->json([
                'member' => [
                    'id' => $member->id,
                    'team_id' => $member->team_id,
                    'pokemon_id' => $member->pokemon_id,
                    'slot' => $member->slot,
                    'behavior' => $member->behavior,
                ],
                'replaced_member_id' => $resultado->miembroReemplazadoId,
            ]);
        }

        return redirect()->back();
    }

    public function removeMember(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:team_members,id',
        ]);

        $member = TeamMember::findOrFail($data['member_id']);

        // El belongsTo Team hereda el global scope: para un miembro de un equipo ajeno
        // la relación resuelve null y abort(404) (anti-IDOR, mismo status que update/destroy).
        abort_unless($member->team?->user_id === Auth::id(), 404);

        $this->quitarMiembro->ejecutar($member);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Actualiza la formación persistida del equipo (PATCH /teams/{team}/formacion).
     *
     * Contrato: { formacion: { slot: 'vanguardia'|'retaguardia', ... } }; sin slots
     * se limpia → vuelve a la clasificación automática por stats.
     */
    public function updateFormacion(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        // Route-model binding + global scope de Team: 404 para equipos ajenos.
        $data = $request->validate([
            'formacion' => 'sometimes|array',
            'formacion.*' => 'in:vanguardia,retaguardia',
        ]);

        $this->actualizarFormacion->ejecutar($team, $data['formacion'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'formacion' => $team->formacion ?? [],
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Actualiza la postura por slot (PATCH /teams/{team}/posturas).
     *
     * Contrato: { posturas: { slot: 'atacar'|'guardia'|'cubrir', ... } }.
     */
    public function updatePosturas(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        $data = Validator::make($request->all(), [
            'posturas' => 'sometimes|array',
            'posturas.*' => 'in:atacar,guardia,cubrir',
        ])->validate();

        $this->actualizarPosturas->ejecutar($team, $data['posturas'] ?? null);

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'posturas' => $team->posturas ?? [],
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Actualiza el objeto equipado por slot (PATCH /teams/{team}/items).
     *
     * Contrato: { items: { slot: 'life_orb'|'leftovers'|...|'' } }. Se guarda
     * en `reclutados.obj_equipados` como lista de una sola clave.
     */
    public function updateItems(Request $request, Team $team): RedirectResponse|JsonResponse
    {
        $data = Validator::make($request->all(), [
            'items' => 'sometimes|array',
            'items.*' => ['nullable', 'string', Rule::in([...CatalogoObjetos::claves()->toList(), ''])],
        ])->validate();

        $itemsActuales = $this->actualizarObjetosEquipo->actualizar(
            $team,
            ObjetosEquipo::desde($data['items'] ?? []),
            (int) Auth::id(),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'team' => [
                    'id' => $team->id,
                    'items' => $itemsActuales->toArray(),
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Actualiza el behavior de un miembro (PATCH /teams/member/{member}/role).
     *
     * Usa route-model binding (TeamMember). Anti-IDOR; la exploración es
     * individual por reclutado y no bloquea la edición del equipo.
     */
    public function updateMemberRole(Request $request, TeamMember $member): RedirectResponse|JsonResponse
    {
        return $this->ejecutarCambioBehavior($request, $member);
    }

    /**
     * Actualiza el behavior de un miembro vía POST /teams/update-member-role.
     *
     * Contrato del frontend: body { member_id, behavior }.
     */
    public function updateMemberRoleViaPost(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|exists:team_members,id',
        ]);

        $member = TeamMember::findOrFail($data['member_id']);

        return $this->ejecutarCambioBehavior($request, $member);
    }

    /**
     * Lógica compartida de cambio de behavior: anti-IDOR en el controlador
     * (autorización HTTP) y regla de negocio en ActualizarRolMiembro.
     */
    private function ejecutarCambioBehavior(Request $request, TeamMember $member): RedirectResponse|JsonResponse
    {
        // Anti-IDOR: el belongsTo Team hereda el global scope; para un miembro de un equipo ajeno
        // la relación resuelve null y abort(404) (mismo patrón que removeMember).
        abort_unless($member->team?->user_id === Auth::id(), 404);

        $data = $request->validate([
            'behavior' => 'required|in:VANGUARDIA,COMBATIENTE,RECOLECTOR,RASTREADOR',
        ]);

        $this->actualizarRol->ejecutar($member, (string) $data['behavior']);

        if ($request->wantsJson()) {
            return response()->json([
                'member' => [
                    'id' => $member->id,
                    'behavior' => $member->behavior,
                ],
            ]);
        }

        return redirect()->back();
    }

    /**
     * Respuesta de error compartida: JSON 422 para API, flash 'error' + back para web.
     */
    private function responderError(Request $request, string $error): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['error' => $error], 422);
        }

        return redirect()->back()->with('error', $error);
    }
}
