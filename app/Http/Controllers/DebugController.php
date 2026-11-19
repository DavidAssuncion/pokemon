<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ItemCatalogo;
use App\Support\Qa\AjustesQa;
use App\Support\Qa\ModoQa;
use App\Support\Qa\PanelQa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de QA (/debug). Solo se alcanza en local/testing (middleware
 * `solo-local` + guarda redundante en cada acción). Formularios POST clásicos
 * con redirect y flash; sin estado de cliente.
 */
final class DebugController extends Controller
{
    public function __construct(
        private readonly PanelQa $panel,
        private readonly AjustesQa $ajustes,
    ) {
    }

    public function index(Request $request): View
    {
        ModoQa::asegurarEntornoPermitido();

        return view('debug.index', [
            'panel' => $this->panel->contenido((int) Auth::id()),
            'deltasExperiencia' => AjustesQa::deltasExperiencia(),
            'deltasCaramelos' => AjustesQa::deltasCaramelos(),
        ]);
    }

    public function ajustarExperiencia(Request $request): RedirectResponse
    {
        ModoQa::asegurarEntornoPermitido();

        $datos = Validator::make($request->all(), [
            'delta' => ['required', 'integer', Rule::in(AjustesQa::deltasExperiencia())],
        ])->validate();

        $resultado = $this->ajustes->ajustarExperiencia((int) Auth::id(), (int) $datos['delta']);

        if ($resultado->aplicado === 0) {
            return redirect()
                ->route('qa.debug.index')
                ->with('error', 'La experiencia ya es 0.');
        }

        return redirect()
            ->route('qa.debug.index')
            ->with('success', sprintf(
                'Experiencia: %d → %d (%+d).',
                $resultado->anterior,
                $resultado->actual,
                $resultado->aplicado,
            ));
    }

    public function ajustarInventario(Request $request): RedirectResponse
    {
        ModoQa::asegurarEntornoPermitido();

        $datos = Validator::make($request->all(), [
            'item_key' => ['required', 'string', Rule::in(PanelQa::clavesPermitidas())],
            'delta' => ['required', 'integer', Rule::in(AjustesQa::deltasCaramelos())],
        ])->validate();

        $clave = (string) $datos['item_key'];
        $delta = (int) $datos['delta'];
        $resuelto = ItemCatalogo::resolve($clave);

        $resultado = $this->ajustes->ajustarInventario((int) Auth::id(), $clave, $delta);

        if ($resultado->aplicado === 0) {
            return redirect()
                ->route('qa.debug.index')
                ->with('error', sprintf('No hay stock de %s.', $resuelto->nombre));
        }

        $mensaje = sprintf(
            '%s: %d → %d (%+d).',
            $resuelto->nombre,
            $resultado->anterior,
            $resultado->actual,
            $resultado->aplicado,
        );

        if ($resultado->aplicado !== $delta) {
            $mensaje .= ' Stock insuficiente: se aplicó solo lo disponible.';
        }

        return redirect()
            ->route('qa.debug.index')
            ->with('success', $mensaje);
    }
}
