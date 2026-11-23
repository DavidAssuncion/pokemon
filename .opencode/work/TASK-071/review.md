# TASK-071 — Revisión

- **Fecha:** 2026-11-23
- **Plan:** v3
- **Tipo:** autorevisión + verificación estática.
- **Nota:** en esta sesión no hay un revisor independiente separado; se documenta la
  revisión técnica realizada por el agente implementador. Si el flujo exige revisión
  independiente, queda pendiente de un `reviewer` externo.

## Alcance revisado

- Retirada completa de Torres.
- Módulo `src/HabitatsTemporales` (Domain/App/Infra).
- Migración y modelo de progreso.
- API, rutas y vistas.
- Integración con `PresentadorResultadoBatalla`.

## Checklist

| Comprobación | Resultado |
|---|---|
| Domain puro (sin Eloquent/HTTP/facades) | OK |
| App usa Laravel sin depender de Presentation | OK (`ResolutorAssetsFaccion` en App) |
| Infra usa Eloquent/DB solo en repositorios | OK |
| Sin rutas de Torres ni referencias de producción | OK |
| Progreso con `oleada_actual` y `combate_actual` | OK |
| 3 oleadas × 4 combates por facción | OK |
| Jefes secundarios en oleadas 1-2 y capitán en la 3 | OK |
| Nivel rival = nivel del jugador | OK |
| Perfiles IV/EV por oleada y jefe +1 tier | OK |
| Anti-IDOR en el registro del resultado | OK |
| Validación de `team_id` del usuario | OK |
| Sin cooldown por derrota | OK |
| Provincia oculta sin facciones pendientes | OK |
| Detalle 404 al completar la facción | OK |
| Facción con oleada gated no se lista; detalle 404 | OK |
| UI sin «Oleada», «bloqueada» ni texto de medallas | OK |
| Detalle solo con oleada/combate actuales; futuro oculto | OK |
| Equipo solo en el modal; rival nunca visible | OK |
| Modal de formación y PC compartidos con gimnasios | OK |
| Endpoint PC valida `team_id` y no expone composición rival | OK |
| Rutas sin colisión (`whereNumber('id')`) | OK |
| Blade compila y renderiza | OK |
| Imágenes reales de `public/images/rivales` | OK |
| Fallback de imágenes y sprites | OK |
| Sin cambios destructivos en BD | OK |
| PHPStan nivel 6 | 0 errores |
| Pint | pass |
| `php -l` | OK |
| Deptrac | 128 violaciones preexistentes de Habitats; 0 de `HabitatsTemporales` |

## Hallazgos

No hay hallazgos bloqueantes.

Observaciones:

1. Los rosters son provisionales hasta la confirmación del desarrollador; se ajustan
   solo en `CatalogoFaccionesRivales`.
2. Los sprites `magno`, `aquiles`, `ciro` y `guzman` usan el fallback hasta que se
   añadan los PNG.
3. El volumen es de 60 combates (5 × 3 × 4); el catálogo reutiliza plantillas para no
   inflar el contenido.
4. La BD local conserva las tablas de torres por decisión del desarrollador.
5. No se ejecutan tests (política del proyecto).

## Veredicto

Implementación aceptada en autorevisión. Pendiente de confirmación de equipos y
assets de capitanes.
