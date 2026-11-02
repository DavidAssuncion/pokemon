# TASK-008 — Informe final

- **ID:** TASK-008 (renumerada desde TASK-007 por colisión de artefactos con otra sesión)
- **Estado final:** `COMPLETED` (con issues conocidos)
- **Fecha:** 2026-11-02
- **Plan aprobado:** v1
- **Nota:** por indicación del desarrollador, se desactiva la generación y mantenimiento de tests a partir de este cierre. Los tests ya creados en esta tarea se dejan intactos; no se eliminan sin autorización.

## 1. Resumen

Se completó el detalle de Pokémon de la Pokédex con:

1. **Familia evolutiva completa:** pre-evoluciones, evoluciones, ramas y formas regionales enlazadas, con `minimum_level`, `parent_id`, `stage`, `branch_index` y el Pokémon actual destacado.
2. **Ubicación:** provincia, imagen y nombre del hábitat, con botón «Ir al hábitat»; mensaje «Sin hábitat asignado» cuando no existe.
3. **Badges de tipo genéricos:** `TipoBadges` como fuente única (18 tipos, paleta del span de movimientos, icono `/images/type/{slug}.webp`), componente `<x-tipo-badge>` y helper Alpine `tipoBadge`/`tipoBadgePorId`, cargado solo en las vistas que lo usan.
4. **Guard de visibilidad:** el detalle devuelve 404 para Pokémon no vistos sin `unlockAll`; `?unlock=1` solo se honra en `local`/`testing` o con `POKEDEX_UNLOCK_ALL=true`.

## 2. Archivos creados

- `src/Pokemon/Domain/Evolution/PokemonFamilia.php`
- `src/Pokemon/Domain/Evolution/AristaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/MiembroFamiliaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/FamiliaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php`
- `app/Support/FamiliaPokemon.php`
- `app/Support/PresentadorDetallePokemon.php`
- `resources/views/components/tipo-badge.blade.php`
- `resources/views/partials/tipos-badge-script.blade.php`
- `tests/Unit/Pokemon/Evolution/ResolvedorFamiliaEvolutivaTest.php`
- `tests/Unit/Shared/TipoBadgesTest.php`
- `tests/Feature/TipoBadgeBladeTest.php`

## 3. Archivos modificados

- `app/Support/DesbloqueoPokedex.php` (endurecimiento: sin bypass por `APP_DEBUG`)
- `app/Providers/DatagridServiceProvider.php`
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`
- `src/Shared/UI/TipoBadges.php`
- `resources/css/app.css`
- `resources/views/layouts/app.blade.php`
- `resources/views/pokedex/index.blade.php`
- `resources/views/equipos/index.blade.php`
- `resources/views/equipos/partials/movimiento-card.blade.php`
- `resources/views/exploraciones/_explorador.blade.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/gimnasios/index.blade.php`
- `resources/views/gimnasios/show.blade.php`
- `resources/views/gimnasios/admin.blade.php`
- `tests/Feature/DatagridTest.php`
- `tests/Feature/PokedexViewTest.php`
- `src/Pokemon/context.md`, `src/Pokemon/README.md`, `src/Shared/context.md`

## 4. Decisiones

- Familia = cierre conectado por `pokemon_evolution`, sin restringir a `evolution_chain_id`; incluye las 14 aristas cross-chain (formas regionales).
- `ResolvedorCadenasEvolutivas` de Hábitats delega en el nuevo resolvedor en modo compatibilidad (`multiRaiz=false`, `maxEtapas=3`), sin duplicar el BFS.
- Contrato aditivo: se conservan `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`; se añaden `type_details`, `location`, `family`.
- Badges: se migran solo los pills Tailwind de Pokédex, equipos, exploraciones y hábitats. Gimnasios conservan su badge hex y Bootstrap no se toca.
- Helper JS acotado a Pokédex, equipos y hábitats para no inyectar etiquetas de tipo en páginas que no lo usan (corrección de la regresión detectada por QA).
- Guard 404 en la closure `detail` del registro `pokemon`; no se modificó el controlador genérico.

## 5. Pruebas ejecutadas

- Unitarias (familia, `TipoBadges`, `FaseEvolutiva`): **25 passed / 492 assertions**.
- `DatagridTest` + `TipoBadgeBladeTest`: **36 passed / 171 assertions**.
- `ExploracionesViewTest` + `PokedexViewTest` + `EquiposViewTest` + `HabitatsViewTest` + `Habitats/FamiliesTest`: **52 passed / 417 assertions**.
- `DatagridTest` tras el endurecimiento de `DesbloqueoPokedex`: **32 passed / 159 assertions**.
- `npm run build`: **OK** (Vite 7.3.6, 67 módulos).
- PHPStan del alcance: sin errores; el global sigue con errores en `SendasController.php` y `FinalizarExploracionHandler.php` (WIP ajeno).
- Presupuesto de consultas: **7 del presentador + 1 find del endpoint = 8**.

## 6. Revisión, QA y seguridad

- **Reviewer:** `APPROVED` tras corregir 3 errores de PHPStan, unificar mapas de etiquetas y actualizar documentación.
- **QA:** `PASS WITH KNOWN ISSUES`. Regresión del helper global corregida; los 3 fallos de gimnasios son WIP ajeno (EVs/medalla). Suite completa inestable por concurrencia sobre `laravel_test`.
- **Security:** `PASS CON HALLAZGOS` (0 CRITICAL, 0 HIGH). El bypass `?unlock=1` con `APP_DEBUG` fue corregido. Queda como deuda la exposición del catálogo de no vistos en el listado/APIs y el rate limiting/caché.

## 7. Issues conocidos y riesgos residuales

- **Listado/APIs de hábitat** siguen exponiendo nombre, tipos y hábitat de Pokémon no avistados; el 404 del detalle no garantiza confidencialidad global. Requiere decisión de producto (redactar o aceptar catálogo público).
- **Gimnasios:** 3 tests en rojo por refactors ajenos (`PerfilIvsEvs`, `GymCatalogoRepositoryInterface`).
- **Suite completa:** no reproducible por concurrencia sobre `laravel_test`; requiere BD aislada.
- **PHPStan global:** rojo por WIP ajeno en `SendasController` y `FinalizarExploracionHandler`.
- **`config/pokedex.php`:** el docblock aún menciona «local/debug»; el código ya solo permite `local/testing`.
- **Cobertura:** `habitats` sin aserción explícita; presupuesto de consultas medido una vez, sin test automatizado.
- **Deuda de dependencias:** 8 advisories en 5 paquetes; ninguno explotable en el flujo auditado.

## 8. Próximos pasos

- Decidir el modelo de confidencialidad del catálogo de no avistados.
- Aislar la BD de test y repetir la suite completa cuando se resuelva la concurrencia.
- Resolver los tests de gimnasios y el PHPStan global en sus tareas de origen.
- Valorar rate limiting/caché para el detalle y las aristas de evolución.
- Actualizar `config/pokedex.php` (docblock) si se autoriza tocar configuración.
- **Testing:** generación y mantenimiento desactivados por indicación del desarrollador; los tests existentes quedan intactos.

## 9. Estado

`COMPLETED` con issues conocidos. Sin commit ni `git push`.
