# ANÁLISIS FRONTEND — Gestión-Admin de Gymnasios + Mazmorras + CP en reclutados

Fecha: 2026-09-05
Rol: Frontend (Blade + Alpine.js + Tailwind 4 + Vite/Livewire)
Base: sigue al agente Backend que está construyendo los endpoints (trabajo en árbol
de trabajo con commits `73c19e8 5vs5`, `3941b5d gym ia`, `77e1f96 gestion equipos`).

## Contexto / fuentes leídas

- `docs/context.md`, `.ai/rules/index.md`, `active/RESUMEN_TAREA.md`.
- `resources/views/habitats/show.blade.php` — modal Alpine `habitatShow()` con
  `openGestionModal()`, popup de formación (selección equipo + vanguardia/retaguardia),
  modal Admin-Gestión de familias, favoritos. **Patrón de referencia** del que parto.
- `resources/views/gimnasios/index.blade.php` y `show.blade.php` — vistas actuales de
  gyms, popup de formación, mapping `$tipoBadges` (int TipoPokemon 1-18 → badge Tailwind),
  endpoint `GET /api/gimnasios` (listado) y `GET /api/gimnasios/{slug}` (detalle).
- `resources/views/equipos/index.blade.php` — vista Alpine `favoritosApp()`, tarjetas de
  reclutados (icono + nombre + Nv), equipo con slots **hardcoded a 3** (`[1,2,3]`),
  modal de detalle con stats/evolución.
- `resources/views/reclutado/show.blade.php` — detalle individual (nivel + exp + evolución).
- `resources/views/livewire/_pokemon-card.blade.php` — tarjeta de combate (Livewire).
- `app/Support/ReclutadoSerializer.php` — serializador compartido de reclutado (nivel,
  exp_total, es_shiny, behavior, rol, stats). El backend ampliará con **CP**.

## Qué tocar

### BLOQUE A — Popup Gestión-Admin de gyms (vista NUEVA)
- **Nuevo**: `resources/views/gimnasios/admin.blade.php` — vista/alpine-modals de admin.
  No reutiliza `gimnasios/show` (es una vista nueva dentro del popup).
- 3 capas de modal Alpine con fetch API (patrón `habitatShow()`):
  1. Popup "Gestión-Admin" → lista TODOS los gyms (datagrid / endpoint backend).
  2. Popup detalle de gym → 4 pestañas (etapas 1-4, entrenadores).
  3. Zona inferior → pokémon filtrados por el tipo del gym (filtro aplicado por defecto)
     y a SOLO última evolución (flag `es_ultima_evolucion` del backend).
  4. Edición medalla/tipo/nivel_minimo + 4 etapas (vanguardia/retaguardia species_id).
- Botón de acceso: nuevo enlace/modal "Gestión-Admin" (p.ej. desde `/gimnasios`).

### BLOQUE B — Mazmorras en hábitat
- `resources/views/habitats/show.blade.php`: activar botón "Mazmorras" (hoy placeholder
  `alert('Función próximamente')`) → `openMazmorraModal()`.
- Nuevo modal Alpine "Mazmorra": pisos, al completar → siguiente, cooldown 1h si pierdes,
  no repetir ganados. Formación de equipo de 5 (vanguardia/retaguardia) — reutiliza patrón.

### BLOQUE C — CP en reclutados
- `resources/views/equipos/index.blade.php`: badge CP en las tarjetas de reclutado
  (favoritos + disponibles + asignados) y en el modal de detalle.
- `resources/views/reclutado/show.blade.php`: CP junto a nivel/exp.
- `resources/views/livewire/_pokemon-card.blade.php`: badge CP (tolerante a que no venga).
- Nombre por defecto CAPITALIZADO (`ucfirst`) cuando el reclutado no tiene nombre personalizado.

## DTOs Wireable / contrato JSON asumido (coordinar con Backend)

Bloque admin gyms (endpoints que debe crear el backend):
- `GET /api/gimnasios/admin` → `[{ id, slug, medalla, tipo, nivel_minimo }]`
- `GET /api/gimnasios/admin/{gym}` → detalle admin:
  ```
  {
    id, slug, medalla, tipo, nivel_minimo,
    etapas: [ // 4 entrenadores
      { etapa: 1, nombre, vanguardia_species_id, retaguardia_species_id },
      ...
    ],
    pokemon: [ // tipo == gym.tipo, SOLO última evolución (es_ultima_evolucion)
      { id, name, species_id, icon, es_ultima_evolucion }
    ]
  }
  ```
- `PATCH /api/gimnasios/admin/{gym}` → body `{ medalla?, tipo?, nivel_minimo?,
  etapas?: [{etapa, vanguardia_species_id, retaguardia_species_id}] }`
- (Alternativa compatible) `GET /datagrid/gyms` para la primera capa del listado.

Mazmorras:
- `GET /api/habitats/{id}/mazmorra` → `{ pisos: [{piso, nombre, ganado, cooldown_hasta}] }`
- `POST /api/habitats/{id}/mazmorra/{piso}/combatir` → body `{ team_id, formacion }`
  → `{ battle_id | redirect }`

CP reclutados:
- Campo `cp` (int) en `ReclutadoSerializer::serializar()` y en `/equipos` payload.

## Estados UI a cubrir
- Loading (spinner), Error (mensaje + retry), Empty (sin gyms / sin pokémon / sin equipo),
  Success. Para mazmorra: sin piso disponible / cooldown / ganado.

## Riesgos accesibilidad/UX
- Modales: `@keydown.escape.window`, overlay click-to-close, `aria-label`, focus en el
  header. Background scroll bloqueado mientras el modal abierto (no toco; hereda patrón).
- Alternancia pestañas con `role="tablist"`/`role="tab"`/`aria-selected`.
- Toggle formación usa botones con texto visible (no solo color).

## Tests a escribir (framework: feature view assertions + Alpine-fetch, ver
FrontendHabitatModalTest / EquiposViewTest)
- `tests/Feature/Gimnasios/GimnasioAdminViewTest.php` — render de la vista admin,
  markers Alpine (`gimnasioAdminApp`, 4 pestañas, `es_ultima_evolucion`, PATCH).
- `tests/Feature/Habitats/HabitatMazmorraViewTest.php` — botón Mazmorras activo
  (`openMazmorraModal`), popup de formación de 5 (`[1,2,3,4,5]`), cooldown/ganado.
- `tests/Feature/EquiposCpViewTest.php` — badge CP en tarjetas y detalle, nombre cap.
- Ajustar `EquiposViewTest` si hace asserts de slots=3.

## Restricciones
- NO tocar UI de exploraciones ni `behavior/RolExploracion`.
- Tras editar Tailwind → indicar `npm run build`.
- No lógica de negocio en Blade; solo consumir DTOs/contrato JSON.

---

## Handoff — RF-A Cancelar exploración · RF-D sin Indefinido + preview · RF-C rol individual · RF-E Misiones (Frontend)

Fecha: 2026-09-06
Prioridad: alta (contrato backend en paralelo)

### Cambios aplicados (frontend únicamente)

- **RF-A — Cancelar exploración** (`resources/views/exploraciones/index.blade.php`)
  - Tarjeta ACTIVA: botón secundario "Cancelar" (todas), "Recoger resultados" SOLO en
    indefinidas (`@if(!empty($exp['indefinido']))`).
  - `cancelarExploracion(id)` → `postAction('/exploraciones/{id}/cancelar', '¿Cancelar la
    exploración? No recibirás recompensas.')` (confirm + POST + reload; mismo patrón que
    `recogerResultados`/`cerrarResultados`).

- **RF-D — Sin modo Indefinido + preview de recompensas** (`resources/views/habitats/show.blade.php`)
  - Radio `value="indefinite"` eliminado; el branch `else { data.indefinido = true; }` de
    `confirmExploration()` eliminado (solo envía `duracion_horas`/`return_time`).
  - `loadPreview()` recarga al abrir el modal y en cada `@change` de radios/inputs de
    duración, pasando `duracion_horas` o `return_time` a `GET /exploraciones/preview`.
  - Bloque "Ganarás esto": `por_horas`, `aviso`, y `items` con `'entre {min} y {max}'`
    (tolerante con `x-show="previewLoaded && preview?.recompensas_esperadas"`).
    Badge de rol del reclutado: `preview.rol || preview.rol_sugerido`.
  - El mismo cambio de envío sin `indefinido` se aplicó al flujo individual de
    `resources/views/equipos/index.blade.php` (`postExploracionIndividual`).

- **RF-C — Rol individual vía `/api/reclutado/{id}/rol`** (`resources/views/equipos/index.blade.php`)
  - `updateMemberRole()` → `POST /api/reclutado/{reclutadoId}/rol` con `{ behavior }`
    (reclutadoId = `member.reclutado?.id ?? member.pokemon_id`); ya no usa
    `/teams/update-member-role`.
  - Valor inicial: `rolInicialDe()` = `member.reclutado?.behavior || member.behavior || 'VANGUARDIA'`.
  - 4 roles disponibles en el selector (VANGUARDIA/COMBATIENTE/RECOLECTOR/RASTREADOR).

- **RF-E — Página de Misiones** (`resources/views/misiones/index.blade.php`, NUEVO)
  - Alpine `misionesPage()`: carga `GET /misiones`, `GET /misiones/activa`,
    `GET /api/reclutados`, `GET /datagrid/habitat?per_page=200`.
  - Aceptar: modal con `reclutado_id` + `habitat_id` → `POST /misiones/{id}/aceptar`.
  - Activa: progreso (`progresoPorcentaje`), `POST /misiones/{activa.id}/cancelar`,
    botón ¡Pelea! → `misionPeleaRuta` (parámetro de vista) o placeholder "La pelea de
    misiones está en preparación."
  - Link del nav a `/misiones` ya presente en `resources/views/layouts/app.blade.php`.

### Tests (verdes)
- `tests/Feature/FrontendExploracionesCancelarTest.php`
- `tests/Feature/FrontendHabitatModalTest.php`
- `tests/Feature/FrontendMisionesTest.php`
- `tests/Feature/FrontendEquiposRolTest.php`
- 6 tests / 54 assertions PASS.

### Pendientes de contrato backend (NO implementar aquí)
- Rutas de misiones: `GET /misiones`, `GET /misiones/activa`, `POST /misiones/{mision}/aceptar`,
  `POST /misiones/{activa}/cancelar`, `POST /misiones/{activa}/pelear` (backend en paralelo;
  la vista tolera su ausencia).
- El controlador/validación de `POST /exploraciones/{id}/cancelar` (backend en paralelo).
- El preview debe poder devolver `recompensas_esperadas` y `rol`/`rol_sugerido` (tolerante).

### Fallos pre-existentes NO imputables al frontend
- `tests/Feature/ExploracionesPageTest.php` (10 fallos: `QueryException` NOT NULL
  `reclutado_id` en `exploraciones_activas`): la migración backend
  `2026_09_02_000003_change_equipo_id_to_reclutado_id_in_exploraciones.php` hace
  `reclutado_id` NOT NULL pero el test crea filas sin ese campo → lo corrige el backend.

### Notas
- `vendor/bin/pint --dirty` aplicado (solo ajustó archivos de otros agentes; mis tests quedaron pass).
- No se ejecutó `npm run build` (no hubo cambios Tailwind/CSS fuera del HTML en línea).

## BLOQUE A — Gestión-Admin de Gimnasios (wired)
- `resources/views/gimnasios/admin.blade.php` (NUEVO): dos popups encadenados.
  - Capa 1: listado de todos los gyms (`GET /api/admin/gyms`).
  - Capa 2: detalle con 4 pestañas (etapas 1-4), pokémon por tipo SOLO última evolución
    (A.3/A.4), edición de medalla/tipo/nivel_minimo + vanguardia/retaguardia, guardado
    secuencial `PUT` datos básicos + `PUT` por-etapa.
  - Última evolución (A.4): respeta `es_ultima_evolucion` del backend si viene; fallback
    local excluyendo species_id que aparecen como `evolves_from_species_id` de otra fila.
  - Filtros por tipo (A.3): aplicados por defecto (types del payload o `filter[types]` del
    Datagrid en el fallback).
  - Estados cubiertos: loading / error / empty / success para ambas capas y el guardado.
- `app/Http/Controllers/GimnasiosViewController.php`: método `admin()` → `view`.
- `routes/web.php`: `GET /gimnasios/admin` registrada ANTES de `/{slug}` (para no ser absorbida).
- `resources/views/gimnasios/index.blade.php`: botón "Admin" (cosmético, cualquier user authed).
- Build: `npm run build` ejecutado (nuevas clases Tailwind).
- Test: `tests/Feature/Gimnasios/GimnasioAdminViewTest.php` (6 tests PASS).

### Fix bug rutas admin (2026-09-10)

**Problema**: la vista hacía fetch contra `/api/gimnasios/admin` y `/api/gimnasios/admin/{slug}` con `PATCH`, rutas que NO existen. El contrato real es `/api/admin/gyms` con `PUT`.

**Cambios aplicados** en `resources/views/gimnasios/admin.blade.php`:

1. **`loadGyms()`**: eliminado intento-fallback. Ahora `GET /api/admin/gyms` directamente.
2. **`loadDetail()`**: `GET /api/admin/gyms/{slug}`. Parse de `etapas` adaptado de array
   (`[{etapa, vanguardia_species_id, ...}]`) a objeto map (`{1: {vanguardia, retaguardia}, ...}`)
   — coincide con `AdminGymController::serializar()`.
3. **`guardarGym()`**: secuencia de 2 pasos:
   - `PUT /api/admin/gyms/{slug}` con `{medalla, tipo, nivel_minimo}` (datos básicos).
   - `PUT /api/admin/gyms/{slug}/stages/{etapa}` × 4 etapas, con `{vanguardia: int[], retaguardia: int[]}`.
   - Si falla el PUT de datos → aborta, muestra error.
   - Si falla una etapa concreta → muestra error con detalle; las demás quedan guardadas.
   - Se usa `idsDe()` existente para normalizar strings a arrays de ints.

**Deuda de tests** (`GimnasioAdminViewTest`):
- `test_admin_view_has_list_modal` línea 31: `assertSee('/api/gimnasios/admin')` → ya no existe.
- `test_admin_view_has_detail_modal` línea 46: `assertSee('/api/gimnasios/admin/')` → ya no existe.
- `test_admin_view_edit_fields_are_present` línea 56-57: `vanguardia_species_id` / `retaguardia_species_id` → desaparecieron del parse de carga.
- `test_admin_view_edit_fields_are_present` línea 58: `method: 'PATCH'` → ahora es `PUT`.
  Estos tests requieren actualización por QA/Backend (no tocar por orden vigente).
