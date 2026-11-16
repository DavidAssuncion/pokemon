<?php

declare(strict_types=1);

namespace Src\Exploraciones\Infra\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExploracionActiva;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Src\Exploraciones\App\ArchivarExpedicion;
use Src\Exploraciones\App\ConstructorExpedicionesResumen;
use Src\Exploraciones\App\ConsultarExpedicionesUsuario;
use Src\Exploraciones\App\ConsultorEstadoExpedicion;
use Src\Exploraciones\Presentation\PresentadorExploraciones;

/**
 * Endpoints de solo lectura y archivado del panel de expediciones.
 *
 * Los GET no ejecutan el procesamiento pendiente ni mutan estado: el
 * procesamiento sigue ocurriendo en `GET /exploraciones`, en `recoger` y en el
 * comando programado. Todas las rutas cuelgan del grupo `auth` y las consultas
 * acotan por `user_id`, además del global scope del modelo.
 */
final class ExpedicionesPanelController extends Controller
{
    public function __construct(
        private readonly ConsultarExpedicionesUsuario $expediciones,
        private readonly ConstructorExpedicionesResumen $resumenes,
        private readonly ArchivarExpedicion $archivar,
        private readonly ConsultorEstadoExpedicion $consultor,
        private readonly PresentadorExploraciones $presentador,
    ) {
    }

    /**
     * Colección compacta del panel: activas y terminadas pendientes, sin
     * archivadas ni canceladas, en el orden canónico del backend.
     */
    public function index(): JsonResponse
    {
        $panel = $this->resumenes->panel((int) Auth::id());

        return response()->json([
            'expediciones' => $panel->toArray(),
            'total' => $panel->count(),
            'generado_en' => now()->toIso8601String(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * Fragmento HTML del historial, compartido con la página de exploraciones.
     * `incluir_archivadas=1` añade la sección de archivadas; `contexto=pagina`
     * adapta los identificadores al render de la página (evita ids duplicados
     * cuando la página y el modal de historial coexisten).
     */
    public function contenido(Request $request): Response
    {
        $incluirArchivadas = $request->boolean('incluir_archivadas');
        $contexto = $request->query('contexto') === 'pagina' ? 'pagina' : 'historial';
        $grupos = $this->expediciones->para((int) Auth::id(), $incluirArchivadas);

        $datos = $this->presentador->toView(
            $grupos->activas,
            $grupos->terminadas,
            Auth::user()?->nivel(),
            $incluirArchivadas ? $grupos->archivadas : null,
        );

        return response()
            ->view('exploraciones.partials._contenido', $datos + ['contexto' => $contexto])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Tarjeta completa de una expedición (activa, terminada o archivada).
     * 404 si es de otro usuario, no existe o está cancelada.
     */
    public function detalle(ExploracionActiva $exploracion): Response
    {
        $userId = (int) Auth::id();

        abort_unless($exploracion->user_id === $userId, 404);
        abort_if($this->consultor->estaCancelada($exploracion), 404);

        $exploracion->loadMissing('reclutado.pokemon.types', 'habitat');

        $nombres = $exploracion->regreso === null
            ? $this->presentador->nombresPokemon(new Collection([$exploracion]), new Collection())
            : $this->presentador->nombresPokemon(new Collection(), new Collection([$exploracion]));

        $vista = $exploracion->regreso === null
            ? $this->presentador->toActiva($exploracion, $nombres)
            : $this->presentador->toTerminada($exploracion, $nombres);

        return response()
            ->view('exploraciones.partials._detalle', [
                'expedicion' => $vista,
                'contexto' => 'detalle',
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Archiva una expedición completada y devuelve su resumen junto con la
     * colección canónica del panel ya actualizada.
     */
    public function archivar(ExploracionActiva $exploracion): JsonResponse
    {
        $userId = (int) Auth::id();

        abort_unless($exploracion->user_id === $userId, 404);

        $archivada = $this->archivar->ejecutar($userId, $exploracion->id);

        return response()->json([
            'ok' => true,
            'expedicion' => $this->resumenes->resumen($archivada)->toArray(),
            'expediciones' => $this->resumenes->panel($userId)->toArray(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
