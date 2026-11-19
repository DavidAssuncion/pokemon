# TASK-067 — Revisión

- **ID:** TASK-067
- **Estado:** APROBADO
- **Revisor:** `reviewer`
- **Fecha:** 2026-11-19
- **Veredicto final:** APPROVED

## Resumen

La implementación cumple el plan v1: filtra las familias de la granja por
avistamiento, proyecta `base`/`evolutions`/`types` solo con miembros vistos,
filtra `inventario.familia`, bloquea las acciones con 404 neutro, prefiltra la
cosecha global y propaga el flag QA en el modal. Los contratos de admin y de
`/families` permanecen intactos.

La primera revisión solicitó cambios por una discrepancia menor: `harvest-all`
aceptaba `?unlock=1` mientras el plan y `context.md` decían que solo usaba
`POKEDEX_UNLOCK_ALL`. La corrección eliminó `Request` de `cosecharTodo` y pasa
solo `(bool) config('pokedex.unlock_all')`. La re-revisión confirma la
corrección y emite APPROVED.

## Hallazgos

### Resuelto

| Severidad | Hallazgo | Estado |
|---|---|---|
| Medio | `harvest-all` aceptaba `?unlock=1` y la documentación decía lo contrario. | Resuelto: `GranjaController::cosecharTodo()` ya no recibe `Request`; usa solo configuración. `context.md` coincide. |

### Notas no bloqueantes

| Severidad | Nota | Estado |
|---|---|---|
| Bajo | El dominio de Granjas depende de DTOs de `Habitats\Presentation` (`TiposPokemon`, `ProyectorFamiliasVisibles`). Es un patrón preexistente (`ObtenerGranja`, `FamiliaGranja`) y está autorizado por el plan. | Sin cambio; deuda potencial. |
| Bajo | `inventario.familia` se filtra a las cadenas visibles del hábitat actual, no a todas las del jugador. El modal solo usa saldos de familias del hábitat. | Sin cambio; verificado el consumidor. |

## Estado de criterios de aceptación

| # | Criterio | Estado |
|---|---|---|
| 1 | `/farm` oculta familias sin ningún visto y no expone sus datos | Cumple (estático) |
| 2 | Con un visto: base visto, evolutions vistos, types solo vistos | Cumple |
| 3 | Base no visto + evolución vista → base = evolución vista | Cumple |
| 4 | `/families` y `/unassigned-families` intactos | Cumple |
| 5 | Payload `/farm` sin campos nuevos y mismo shape | Cumple |
| 6 | `inventario.familia` solo visibles; `ev` intacto | Cumple |
| 7 | Acciones sobre cadena no vista → 404 sin mutación | Cumple |
| 8 | Mismo 404 para inexistente/no asignada/no vista | Cumple |
| 9 | `harvest-all` sin nidos/cadenas ocultas | Cumple |
| 10 | QA por entorno y `?unlock=1` coherente; `harvest-all` solo env | Cumple tras corrección |
| 11 | Una consulta de vistos + una de tipos, sin N+1 | Cumple |
| 12 | Infraestructura existente se conserva y reaparece | Cumple |
| 13 | Sin lógica de avistamiento en Blade/JS | Cumple |
| 14 | `strict_types`, PHPStan sin errores nuevos, Pint limpio | Cumple |

## Verificación ejecutada

- `php -l` de los archivos backend: sin errores.
- `vendor/bin/phpstan analyse --memory-limit=1G` dirigido a `src/Granjas`,
  `src/Habitats/Presentation/DTOFamiliasDisponibles.php` y
  `app/Providers/AppServiceProvider.php`: `[OK] No errors`.
- PHPStan del repo completo: 35 errores preexistentes en archivos ajenos a
  TASK-067; ninguno en el alcance.
- `vendor/bin/pint --test --format agent` sobre el alcance: `pass`.
- `php artisan route:list --path=farm`: 9 rutas intactas.
- `git diff --check`: sin errores de whitespace.
- Tests: no se crearon, modificaron ni ejecutaron.

## Riesgos residuales

- Criterios validados estáticamente, sin ejecución de escenarios runtime.
- Endpoints admin de familias sin autorización (deuda fuera de alcance).
- HTML de la página y endpoint de Pokémon del hábitat siguen exponiendo datos
  no avistados (fuera de alcance).
- PHPStan global no está limpio por deuda preexistente de otras tareas.
