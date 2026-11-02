# TASK-008 — Plan v1

- **ID:** TASK-008 (renumerada desde TASK-007 por colisión de artefactos)
- **Versión:** v1
- **Fecha:** 2026-11-02
- **Estado:** aprobado
- **Basado en:** `analysis.md` y `architecture.md`
- **Decisiones de producto:** familia completa con formas regionales, badges genéricos migrando los pills Tailwind (sin gimnasios ni Bootstrap), ubicación provincia + hábitat + botón, guard backend de no avistados.

## 1. Objetivo

Completar el detalle de Pokémon de la Pokédex con:

1. **Familia evolutiva completa:** pre-evoluciones, evoluciones, ramas y formas regionales enlazadas, con `minimum_level` y el Pokémon actual destacado.
2. **Ubicación:** provincia, imagen y nombre del hábitat, y botón para ir a `/habitats/{id}`.
3. **Tipos con el span de movimientos:** pill con icono `/images/type/{slug}.webp` y la paleta de `movimiento-card`, mediante una fuente única reutilizable.
4. **Guard de visibilidad:** el endpoint de detalle no devuelve datos de Pokémon no avistados salvo con `unlockAll`.

## 2. Alcance

### A. Familia evolutiva (backend + vista)

- Dominio puro en `src/Pokemon/Domain/Evolution/`:
  - `PokemonFamilia`, `AristaEvolutiva`, `MiembroFamiliaEvolutiva`, `FamiliaEvolutiva`.
  - `ResolvedorFamiliaEvolutiva`: cierre conectado por `pokemon_evolution`, multi-raíz, sin límite de etapas, `visited`, `stage`, `parent_id`, `minimum_level`, `branch_index`.
- `app/Support/FamiliaPokemon.php`: miembros por `evolution_chain_id` (1 consulta), aristas (1), endpoints cross-chain faltantes (1 condicional); traducción `species_id → pokemon.id`.
- `ResolvedorCadenasEvolutivas` (Habitats) delega en modo compatibilidad (`multiRaiz=false`, `maxEtapas=3`), conservando API y `FaseEvolutivaTest`.
- `PresentadorDetallePokemon` añade `family`.
- Vista: sección «Familia evolutiva» agrupada por etapa, con icono, nombre, `Nv X`, actual destacado y `aria-current`.
- Singleton: solo el Pokémon actual, sin ramas.
- Formas regionales: aristas cross-chain incluidas.

### B. Ubicación (backend + vista)

- `PresentadorDetallePokemon` añade `location`: `habitat_id`, `habitat_name`, `habitat_image`, `province_id`, `province_name`, `url`; `null` sin hábitat.
- Vista: provincia, imagen, nombre y botón «Ir al hábitat».
- Sin hábitat: «Sin hábitat asignado», sin botón.
- Se conservan `habitat_name`, `habitat_id`, `habitats`.

### C. Badge de tipo genérico (backend + frontend)

- `TipoBadges` como fuente única por slug ASCII, con `label` de `TipoPokemon`, clases literales de la paleta de movimientos e icono.
- Componente `<x-tipo-badge>` (30×30 por defecto; `xs` 16, `sm` 20).
- Helper JS global `tipoBadge`/`tipoBadgePorId` en el layout, antes de `@stack('scripts')`.
- `@source "../../src";` en `app.css`.
- Migración de pills: Pokédex, equipos (`index` y `movimiento-card`), exploraciones (`_explorador`) y hábitats (`show`).
- No se migran gimnasios (solo etiquetas), Bootstrap ni iconos sueltos.

### D. Guard de visibilidad (backend + vista)

- En la closure `detail`: `DesbloqueoPokedex::activo(request()) || visto`; si no, `null` → 404.
- `DatagridController` no cambia.
- `pokedex/index.blade.php`: `loadDetail()` propaga `?unlock=1` cuando `unlockAll`.
- Test de no visto a 404 y caso unlock.

### E. Documentación

- Actualizar `src/Pokemon/context.md`, `src/Pokemon/README.md` y `src/Shared/context.md` (sin crear documentos nuevos).
- ADR en `architecture.md`; no se crea archivo ADR nuevo.

## 3. Fuera de alcance

- Migrar badges de gimnasio o panel Bootstrap.
- Cambiar iconos de sendas, caramelos, eventos o badges de estado.
- Modificar `TipoPokemon::color()`.
- Migraciones, índices, dependencias o datos.
- Hacer clicables los miembros de la familia.
- Cambiar stats, tipos, hábitats o recompensas.

## 4. Contrato

Se conservan `id`, `name`, `visto`, `atrapado`, `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`.

Se añaden:

- `type_details`: `list<{id,label,slug,icon}>` por `slot`.
- `location`: `null` o `{habitat_id,habitat_name,habitat_image,province_id,province_name,url}`.
- `family`: `{evolution_chain_id,members}`; cada miembro `{id,name,species_id,stage,parent_id,minimum_level,branch_index,icon,is_current}`; siempre ≥1, plano y ordenado.

Presupuesto: ≈8 consultas constantes.

## 5. Archivos y responsables

### Crear

| Archivo | Responsable |
|---|---|
| `src/Pokemon/Domain/Evolution/PokemonFamilia.php` | `backend` |
| `src/Pokemon/Domain/Evolution/AristaEvolutiva.php` | `backend` |
| `src/Pokemon/Domain/Evolution/MiembroFamiliaEvolutiva.php` | `backend` |
| `src/Pokemon/Domain/Evolution/FamiliaEvolutiva.php` | `backend` |
| `src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php` | `backend` |
| `app/Support/FamiliaPokemon.php` | `backend` |
| `app/Support/PresentadorDetallePokemon.php` | `backend` |
| `resources/views/components/tipo-badge.blade.php` | `frontend` |
| `resources/views/partials/tipos-badge-script.blade.php` | `frontend` |
| `tests/Unit/Pokemon/Evolution/ResolvedorFamiliaEvolutivaTest.php` | `backend`/`qa` |
| `tests/Unit/Shared/TipoBadgesTest.php` | `backend`/`qa` |
| `tests/Feature/TipoBadgeBladeTest.php` | `frontend`/`qa` |

### Modificar

| Archivo | Responsable |
|---|---|
| `app/Providers/DatagridServiceProvider.php` | `backend` |
| `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php` | `backend` |
| `src/Shared/UI/TipoBadges.php` | `backend` |
| `resources/css/app.css` | `frontend` |
| `resources/views/layouts/app.blade.php` | `frontend` |
| `resources/views/pokedex/index.blade.php` | `frontend` |
| `resources/views/equipos/index.blade.php` | `frontend` |
| `resources/views/equipos/partials/movimiento-card.blade.php` | `frontend` |
| `resources/views/exploraciones/_explorador.blade.php` | `frontend` |
| `resources/views/habitats/show.blade.php` | `frontend` |
| `resources/views/gimnasios/{index,show,admin}.blade.php` | `frontend` (solo etiquetas) |
| `tests/Feature/DatagridTest.php` | `backend`/`qa` |
| `tests/Feature/PokedexViewTest.php` | `frontend`/`qa` |
| `src/Pokemon/context.md`, `src/Pokemon/README.md`, `src/Shared/context.md` | `documenter` |

## 6. Orden de ejecución

1. Dominio de evolución + refactor Hábitats + test unitario.
2. Presentador + guard + `DatagridTest`.
3. `TipoBadges` + test.
4. Componente + helper + layout + CSS + gimnasios + test.
5. Vistas Pokédex/equipos/exploraciones/hábitats.
6. QA, revisión, seguridad y documentación.

## 7. Criterios de aceptación

1. Familia completa con pre-evoluciones, evoluciones, ramas y formas regionales, sin duplicados.
2. Miembros con icono/nombre; hijos con «Nv X»; actual destacado y `aria-current`.
3. Singleton correcto.
4. Ubicación con provincia, imagen, nombre y botón a `/habitats/{id}`.
5. Sin hábitat: mensaje y sin botón.
6. Tipos con el pill del span de movimientos e icono; `getTypeClass` eliminado.
7. Pills migrados usan `TipoBadges`; sin mapas locales en esas vistas.
8. Gimnasios y Bootstrap sin cambio visual.
9. No visto → 404; `?unlock=1` en local/testing → 200.
10. Contrato actual conservado.
11. ≤8 consultas constantes.
12. Sin migraciones, dependencias ni cambios en `TipoPokemon::color()`.
13. PHPUnit/PHPStan/Pint sin errores nuevos.

## 8. Pruebas requeridas

```bash
php artisan test tests/Unit/Pokemon/Evolution/ResolvedorFamiliaEvolutivaTest.php
php artisan test tests/Unit/Shared/TipoBadgesTest.php
php artisan test tests/Unit/Shared/FaseEvolutivaTest.php
php artisan test tests/Feature/DatagridTest.php
php artisan test tests/Feature/TipoBadgeBladeTest.php
php artisan test tests/Feature/PokedexViewTest.php
php artisan test tests/Feature/EquiposViewTest.php tests/Feature/ExploracionesViewTest.php
php artisan test tests/Feature/HabitatsViewTest.php tests/Feature/Habitats/FamiliesTest.php
php artisan test tests/Feature/Gimnasios
npm run build
```

Casos: lineal, ramas, singleton, multi-raíz, ciclo, compatibilidad Hábitats, 18 tipos/assets, familia con niveles, forma regional, ubicación, sin hábitat, type_details, 404/unlock, componente por slug/id/label, vista Pokédex sin `getTypeClass`.

## 9. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Refactor Hábitats altera etapas | Alto | Modo compat, API intacta, `FaseEvolutivaTest` + tests de hábitats. |
| Cambio visual de badges | Medio | Paleta aprobada; gimnasios no cambian. |
| Tailwind no genera clases | Medio | Clases literales + `@source` + build. |
| Guard 404 rompe vista/tests | Medio | Vista no fetch para no vistos; propagar unlock; test. |
| Familia cross-chain amplia | Medio | Solo endpoints alcanzados; test. |
| N+1 | Bajo | ≤8 consultas. |
| WIP local | Medio | `git diff` por archivo; no revertir. |
| Fuga de no avistados | Medio | Guard 404. |
| Accesibilidad | Bajo | `alt=""`, texto visible, `aria-current`, no depender solo del color. |

## 10. Rollback

- Revertir hunks de vistas/dominio/tests. Sin migraciones ni datos.
- Si el refactor de Hábitats es arriesgado, revertir solo ese archivo y mantener el resolvedor nuevo para la Pokédex, documentando la duplicidad temporal.
- El guard no se desactiva salvo decisión expresa.

## 11. Aprobación

Aprobado con «Aprobar plan v1 (Recommended)»; ver `approval.md`.
