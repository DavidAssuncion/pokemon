# TASK-012 — Ejecución

## Estado

`CORRECTING`

## Preflight

- Rama: `task-011`.
- `git status`: cambios de F1 sin commitear (18 archivos modificados + nuevos), más `.githooks/pre-commit` y `MVP.txt` preexistentes.
- No hay cambios ajenos a F1 salvo los dos preexistentes citados; se mantienen fuera de alcance.
- `git diff --stat`: 519 inserciones y 27 eliminaciones en archivos de F1.
- No se hace commit ni push en esta tarea.

## Historial

- 2026-11-04: tarea creada a partir de la petición «Adelante f2, f3, f4 y f5».
- 2026-11-04: preflight registrado; se inicia análisis de F2–F5.
- 2026-11-04: `analyst` entrega análisis verificado; guardado en `analysis.md`.
- 2026-11-04: `architect` entrega diseño F2–F5 y addendum de recetas/bayas; guardado en `architecture.md`.
- 2026-11-04: plan v1 redactado; pendiente de aprobación explícita.

## Implementación

- Bloque 0: puerto `InventarioJugador`, `StockInsuficiente`, adaptador Eloquent y delegación de `InventarioGranja`.
- F2: migración `nest_plantings` (batch 21), modelo, reglas, DTOs, `PlantarBaya`, `CosecharPlantacion`, endpoints y UI de huecos.
- F3: `ConsultorPlantaciones`, `EsDelHabitat`, factores de plantación, bonus de hábitat, hito “Conoce el terreno”.
- F4: 39 claves nuevas, 46 recetas, `src/Forja`, endpoints y página `/forja` con 4 categorías colapsables.
- F5: `AccionObjeto`, 44 efectos, `ServicioUsoObjetos`, `UsarObjetoEnCombate`, resistencias temporales, `restaurarUsos`, fases Livewire y panel de objetos.
- Correcciones: `DTOAccionObjeto` movido a Domain, `ForjaController` sin base `Controller`, `ObtenerGranja` filtrando por `nests.id`.

## Verificación estática

- `php -l`: sin errores en archivos nuevos/modificados.
- `phpstan`: 34 errores preexistentes, ninguno nuevo en F2–F5.
- `pint --test --dirty`: `pass`.
- `deptrac`: 113 violaciones preexistentes, sin nuevas.
- `php artisan view:cache` / `view:clear`: OK.
- Rutas: `forja` (3) y `farm` (7) registradas.
- Catálogo: 82 definiciones, 44 objetos de combate, 46 recetas, 39 resultados, 44 efectos.
- `tests/`: sin cambios.


## Cierre

- Revisión independiente: `APPROVED` (`review.md`).
- Auditoría de seguridad: `APPROVED` (`security.md`).
- Documentación: ADR `docs/adr/0001-inventario-compartido-f2-f5.md` y `docs/architecture.md` actualizados.
- Informe final: `final-report.md`.
- Sin commit ni push; cambios en el working tree.


## Corrección posterior (2026-11-04)

- Reporte del desarrollador: al seleccionar/plantar una baya el modal de granja se rompe; además, el modal debe ser más grande, como el detalle de Pokémon de `/equipos`.
- Diagnóstico: backend `PlantarBaya`/`CosecharPlantacion` verificado en local con rollback; el fallo está en la UI. El modal usa `max-w-5xl` y el selector de baya usa `x-model` con clave dinámica calculada por función, patrón frágil en Alpine.
- Corrección: modal a `w-[90vw] max-w-[90vw] max-h-[94vh]` con cuerpo `min-h-0 overflow-y-auto`; selector de baya con `:checked` + `@change` en lugar de `x-model` dinámico.
- Corrección aplicada por frontend; `view:cache` OK, sin `x-model` dinámico y assets compilados. Revisión focalizada: `APPROVED`.
- Ajuste posterior: `puedePlantar()` deshabilita el botón si la baya ya no tiene stock; `plantarBaya()` limpia la selección tras éxito.


## Segunda corrección del modal (2026-11-04)

- El problema persistía tras el primer fix. El backend responde 200 con JSON correcto; el fallo es de UI.
- Cambios: el contenido del modal ya no se oculta si falla una acción (`x-if="!granjaLoading && granja"`); `accionGranja`/`loadGranja` tienen timeout de 15 s con `AbortController`; se sustituyó la rejilla de 36 bayas por hueco por un único selector compartido en el pie del modal (`slotPlantando` + `bayaSeleccionada`).
- Verificación: `view:cache` OK; `npm run build` OK; un solo `x-for` de bayas; sin `claveHueco`/`puedePlantar` obsoletos.
- Pendiente: confirmación del desarrollador en navegador; si persiste, recoger error de consola y respuesta de red del POST.
