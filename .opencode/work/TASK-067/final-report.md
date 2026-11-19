# TASK-067 — Informe final

- **ID:** TASK-067
- **Estado final:** COMPLETED
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`
- **Plan aprobado:** v1 (`Aprobar plan v1`)

## 1. Resumen

Se corrigió la fuga de información del modal de granjas de hábitats: la API
`GET /api/habitats/{habitat}/farm` ya no devuelve familias sin ningún Pokémon
avistado. Para las familias visibles, el backend proyecta un representante visto
en `base`, limita `evolutions` y `types` a los miembros vistos y filtra
`inventario.familia` a las cadenas visibles. Las acciones de granja sobre
familias no avistadas se bloquean en backend con un 404 neutro, y la cosecha
global prefiltra nidos ocultos sin exponer sus cadenas.

La regla se resolvió en backend (no en Blade/JS), respetando la arquitectura del
proyecto y sin cambios de esquema, rutas ni contrato JSON.

## 2. Decisiones

- Una familia es visible si el jugador ha avistado al menos un miembro
  (`pokedex.visto = true`).
- `base` = miembro visto de menor `stage`, luego `species_id`, luego `id`.
- `evolutions` y `types` solo con miembros vistos.
- `inventario.familia` solo con cadenas visibles; `inventario.ev` intacto.
- Guard único en `FamiliaEnHabitat` (avistamiento + asignación) con
  `RecursoNoExiste` y mensaje `Familia no disponible en este hábitat` (404),
  idéntico para oculta, no asignada e inexistente.
- `CosecharTodo` prefiltra nidos por cadenas visibles antes de candidatos; no
  lanza excepciones por nido para no exponer `evolution_chain_id` en errores.
- QA: `POKEDEX_UNLOCK_ALL=true` mantiene el comportamiento completo; `?unlock=1`
  se propaga a las URLs del modal en local/testing. `harvest-all` usa solo la
  variable de entorno.
- Admin, `/api/habitats/{id}/families` y `/unassigned-families` quedan intactos.

## 3. Archivos creados

- `src/Granjas/Domain/Ports/PokedexLecturaInterface.php`
- `src/Granjas/Domain/DataTransferObjects/TiposPokemon.php`
- `src/Granjas/Domain/Collections/TiposPokemonCollection.php`
- `src/Granjas/Domain/ProyectorFamiliasVisibles.php`
- `src/Granjas/Infra/PokedexLectura.php`

## 4. Archivos modificados

- `app/Providers/AppServiceProvider.php`
- `src/Habitats/Presentation/DTOFamiliasDisponibles.php`
- `src/Granjas/App/ObtenerGranja.php`
- `src/Granjas/App/InventarioGranja.php`
- `src/Granjas/App/FamiliaEnHabitat.php`
- `src/Granjas/App/AccionesGranja.php`
- `src/Granjas/App/MejorarNido.php`
- `src/Granjas/App/MejorarRefugio.php`
- `src/Granjas/App/MejorarComplemento.php`
- `src/Granjas/App/AlternarCondicionesInsalubres.php`
- `src/Granjas/App/PlantarBaya.php`
- `src/Granjas/App/CosecharPlantacion.php`
- `src/Granjas/App/CosecharTodo.php`
- `src/Granjas/Infra/Controllers/GranjaController.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/habitats/partials/modal-granja.blade.php`
- `src/Granjas/context.md`

## 5. Documentación y ADR

- Actualizado `src/Granjas/context.md` con la regla de visibilidad, proyección,
  guard 404, inventario, cosecha global, QA y deuda de seguridad.
- No se creó un ADR nuevo: la decisión queda registrada en el contexto del
  módulo y en los artefactos de TASK-067. Si se desea un ADR formal, puede
  abrirse una tarea de documentación.

## 6. Verificación ejecutada (sin tests)

- `php -l` de los archivos backend: sin errores.
- `vendor/bin/phpstan analyse --memory-limit=1G` dirigido a `src/Granjas`,
  `src/Habitats/Presentation/DTOFamiliasDisponibles.php` y
  `app/Providers/AppServiceProvider.php`: `[OK] No errors`.
- PHPStan del repo completo: 35 errores preexistentes en archivos ajenos a
  TASK-067; ninguno en el alcance.
- `vendor/bin/pint --test --format agent` sobre el alcance: `pass`.
- `php artisan route:list --path=farm`: 9 rutas intactas.
- `git diff --check`: sin errores de whitespace.
- Revisión manual de contratos: `FamiliaGranja`, `GranjaDetalle`,
  `ObtenerFamiliasDisponibles`, `HabitatLecturaRepository` y
  `HabitatsController` sin cambios.
- No se crearon, modificaron ni ejecutaron tests.

## 7. Revisión y seguridad

- **Reviewer:** APPROVED tras corregir la discrepancia de `harvest-all`
  (`?unlock=1` vs `POKEDEX_UNLOCK_ALL`). Sin hallazgos bloqueantes.
- **Security:** PASS_CON_HALLAZGOS. Sin hallazgos críticos/altos nuevos. Se
  registran como deuda/riesgo:
  - endpoints admin de familias sin rol/policy (alto, preexistente, fuera de
    alcance);
  - `?unlock=1` en entornos no productivos mal configurados (medio,
    condicionado);
  - `POKEDEX_UNLOCK_ALL` como kill-switch global (bajo);
  - advisories de dependencias preexistentes (15, fuera de alcance).

## 8. Riesgos residuales

- Los criterios se validaron estáticamente; no hubo ejecución de escenarios
  runtime ni tests.
- Los endpoints admin de familias siguen permitiendo enumerar todas las
  familias a cualquier usuario autenticado. Requiere tarea de autorización.
- El HTML de la página de hábitat, `/api/habitats/{h}/pokemon` y el detalle de
  especie siguen exponiendo datos de Pokémon no avistados. Fuera del alcance de
  TASK-067.
- `inventario.familia` se filtra a las cadenas visibles del hábitat actual; el
  modal solo consume saldos de esas familias.
- `POKEDEX_UNLOCK_ALL` desbloquea también `harvest-all`; documentado.
- El PHPStan global no está limpio por deuda preexistente de otras tareas.

## 9. Próximos pasos

1. Abrir tarea de seguridad para autorizar (rol/policy) los endpoints admin de
   familias.
2. Decidir si se añade una segunda barrera a `POKEDEX_UNLOCK_ALL`.
3. Abrir tarea para ocultar datos no avistados en el HTML/endpoint de hábitat y
   detalle de especie, si el producto lo exige.
4. Planificar actualización de dependencias por los advisories reportados.
5. Opcional: limpiar la entrada obsoleta de PHPStan para `DesbloqueoPokedex`.
