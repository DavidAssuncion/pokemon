# ANÁLISIS FRONTEND — Ajustes UI: formación en equipos, hábitat sin preview, popup nunca automático, rewards ruta

Fecha: 2026-09-14
Rol: Frontend (Blade + Alpine.js + Tailwind 4). Solo UI: NO tests, NO backend.

## Contexto / fuentes leídas
- `active/RESUMEN_TAREA.md`, `docs/conventions.md`, `.ai/rules/index.md` (sin reglas de glob para `resources/views/**`).
- `src/CombateRuta/context.md` — contrato de ruta (formación popup > `teams.formacion` > automática; rewards `ResultadoRuta::aArray()`).
- `resources/views/equipos/index.blade.php` — Alpine `favoritosApp()`, select rol por miembro, editor formación persistente (`formacionDe`/`setFormacionSlot`/`guardarFormacion`), tabs Equipos/Favoritos, modal favoritos.
- `resources/views/habitats/show.blade.php` — Alpine `habitatShow()`, Ruta Panel con preview (`cargarRivalesRuta`), Teams Panel, Niveles Panel (pokémon/entrenadores), popup formación con chip "⚙️ Automática".
- `resources/views/exploraciones/index.blade.php` (~264-415) + `exploraciones/_caramelo.blade.php` — patrón de recompensas de ruta a replicar en modal Livewire.
- `resources/views/livewire/combate.blade.php` — modal victoria Bootstrap con `rewards` plano (`caramelos`, `medalla`).

## Qué tocar (3 ficheros)
1. `resources/views/equipos/index.blade.php`:
   - Quitar tab "Favoritos" (barra de tabs + bloque ~44-238 + modal ~640-698). Título "Equipos". `activeTab` por defecto 'equipos' → se elimina el estado (barra y wrappers fuera).
   - Sustituir `<select>` de rol por miembro por radio Vanguardia/Retaguardia (mini-botones, mismo criterio visual que bloques de formación). Marcar `// DEPRECATED:` `updateMemberRole` y `rolInicialDe` (ruta `/api/reclutado/{id}/rol` ya no se llama; endpoint se conserva).
   - Eliminar bloque inferior "Formación de combate" (redundante), conservando "Guardar formación" + `formacionSaveError`/`formacionSaveSuccess` reubicados bajo el grid de miembros.
   - Alpine: eliminar estado/lógica de favoritos (`favoritos`, `noFavoritos`, `allGestionables`, `availablePokemonsFav`, `togglingFavoritoId`, `showFavoritosModal`, `toggleFavorito`, `open/closeFavoritosModal`, normalize `r.favorito`).

2. `resources/views/habitats/show.blade.php`:
   - Mover Teams Panel antes del Ruta Panel (equipos arriba en ruta/entrenadores).
   - Niveles Panel visible también en `modo === 'ruta'`: mismas tarjetas (gating `min_lvl`, conteo, visto/desconocido); click setea `rutaNivel` vía `selectRutaNivel(level)` SIN fetch (se conserva `rutaNivel` separado de `selectedLevel`; CTA y `confirmarCombateRuta` ya usan `rutaNivel`).
   - Ruta Panel: quitar mini-selector de niveles + "Rivales salvajes"/preview; conservar cabecera 5v5 y nota azul. Eliminar Alpine `rutaRivales`, `rutaRivalesLoading`, `rutaRivalesError`, `cargarRivalesRuta()` y sus llamadas (init/setModoRuta/selectRutaNivel). CTA `:disabled="!selectedTeamId"`.

3. `resources/views/livewire/combate.blade.php`:
   - Rama ruta (`!empty($rewards['caramelos_familia'])`): recompensas estilo exploraciones (`_caramelo` por grupo familia/EV/tipo con `src/alt/cantidad/nombre` ya del backend), grid Tailwind `bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3` + `<hr>`, tarjeta "Capturados" si `capturas`, EXP intactas, `@php $candyFallback…` al inicio. Rama else (entrenador/gimnasio/medalla) se mantiene.

## Estados UI cubiertos
- Equipos: loading/error de guardado formación, disabled en exploración, equipo vacío, slots vacíos.
- Hábitat: niveles bloqueados (`min_lvl`), popup formación siempre poblado (5 slots), ruta sin preview (sin estados de carga de rivales que eliminar).
- Combate: victoria ruta con/sin capturas, con grupos parciales de caramelos; victoria entrenador/gimnasio con medalla.

## Riesgos / decisiones
- `rutaNivel` se mantiene como estado separado (no se unifica con `selectedLevel`) para no tocar `confirmarCombateRuta` ni el CTA; `selectRutaNivel(level)` deja de hacer fetch.
- Popup formación: helper `formacionInicialDe(teamId)` (persistida > 'vanguardia') usado por `openRutaFormacionPopup`, `selectTrainer` y `openFormacionPopup`; se elimina el chip "⚙️ Automática" y el estilo dashed. `confirmarCombate` ya envía `formacion` completa en ambas ramas; con la inicialización nueva nunca habrá slots vacíos → backend nunca aplica clasificación automática.
- Barra de tabs de equipos: se elimina por completo (contenido directo), opción más limpia.
- Favoritos del HÁBITAT (exploración individual) se conservan intactos en `habitats/show.blade.php`.
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

---

## Combate de Ruta 5v5 — escalado global a 5v5 + modo ruta por defecto (2026-09-13)

Rol: Frontend (Blade + Alpine.js + Tailwind 4). Base: backend commiteado en `0195b04`
(combate ruta 5v5, formación persistida, gimnasios escalados; suite 1058 PASS).
Constraint: NO tocar `src/` ni `app/` de backend; solo vistas + Alpine + estilos.

### Contratos verificados (fuente)

- `GET /api/habitats/{habitat}/ruta/rivales?nivel=N` → `{rivales: [{id, nombre, posicion, species_id, nivel}]}`.
  `nivel` clamped 1-3 (`max(1,min(3,$nivel))`). Pool vacío → `rivales: []`.
- `POST /api/habitats/{habitat}/ruta/iniciar` → body `{team_id, formacion?, nivel?}`;
  `formacion` es `sometimes|array`, `formacion.*` ∈ {vanguardia, retaguardia} → **`{}` aceptado**
  (= usa formación persistida del backend). Respuesta `{battle_id, redirect}`.
- `ViolacionReglaNegocio` → 422 `{message}` (renderable en `bootstrap/app.php`).
  `IniciarCombateRuta` lanza `'El equipo de ruta debe tener exactamente 5 miembros.'` y
  `'No hay Pokémon salvajes disponibles en esta ruta para tu nivel.'`.
- `PATCH /teams/{team}/formacion` (`routes/player.php:43`) → body `{formacion: {slot: pos}}`;
  respuesta `{team: {id, formacion}}`; 404 equipo ajeno; en exploración → 422 `{error}` vía
  `TeamController::responderError`.
- `$teams` de la vista hábitat (`ObtenerEquipos` → `TeamAggregate`) NO expone `formacion`
  (solo id/name/members). `PlayerController::equipos()` tampoco → la vista equipos no puede
  pre-poblar la formación persistida en carga inicial; se puebla tras el primer PATCH local.

### Qué tocar

1. `resources/views/habitats/show.blade.php`:
   - `modo: 'pokemon'` (línea 1081) → `modo: 'ruta'` (default). Persistencia del estado:
     la vuelta del combate recarga la página y el default sigue siendo 'ruta'.
   - Botones izquierda: quitar enlace "Favoritos" (líneas 51-62); añadir botón "Combate de ruta"
     (arriba, activo si `modo === 'ruta'`); añadir botón "Exploraciones" debajo de "Entrenadores"
     (activo si `modo === 'pokemon'`); respetar `$bloqueadoConstruccion`.
   - `toggleEntrenadores()` → toggle entre 'entrenadores' y 'ruta' (NO a 'pokemon');
     nuevo `toggleExploraciones()` entre 'pokemon' y 'ruta'; nuevo `setModoRuta()`.
   - Nuevo **Ruta Panel** (x-show `modo === 'ruta'`): nota obligatoria "Combate 5v5 contra
     pokémon salvajes. Sin límite diario. Al ganar puedes capturar.", selector nivel 1-3
     (`rutaNivel`), preview de 5 rivales (loading/error/empty/success; `isSighted(species_id)`),
     aviso empty "No hay Pokémon salvajes disponibles...". CTA "Combate de Ruta"
     (`openRutaFormacionPopup`) deshabilitado sin equipo o sin rivales.
   - Panel Equipos: `x-show="modo === 'entrenadores' || modo === 'ruta'"`.
   - Niveles Panel: `x-show="modo === 'pokemon' || modo === 'entrenadores'"` (oculto en ruta).
   - Botón "Iniciar Exploración": `x-show="modo === 'pokemon'"` (oculto en ruta).
   - Popup formación (661-715): `formacionContexto` ('entrenadores'|'ruta').
     Entrenadores: inicializa todos 'vanguardia' (existente). Ruta: `formacion = {}` →
     backend usa persistida; chip "⚙️ Automática" cuando slot sin elegir. Error 422 inline
     (`rutaCombatError`) en el popup, sin redirigir (requisito).
   - `confirmarCombate()` → branch: ruta → `confirmarCombateRuta()` (POST iniciar);
     entrenadores → flujo existente.
   - Team cards preview (línea 287): `@for($i=0;$i<3;$i++)` → 5 slots en `grid grid-cols-5`
     (imágenes `w-full h-14` en vez de `w-24 h-24` para no desbordar).
   - `selectTeam()`: `checkAndOpenModal()` solo si `modo === 'pokemon'` (evita re-abrir el
     modal de exploración con favorito previamente seleccionado).
   - `init()`: `cargarRivalesRuta()` al cargar (modo ruta por defecto).
   - Estados Alpine nuevos: `rutaNivel`, `rutaRivales`, `rutaRivalesLoading`, `rutaRivalesError`,
     `rutaCombatiendo`, `rutaCombatError`, `formacionContexto` + `cargarRivalesRuta()`,
     `selectRutaNivel()`, `openRutaFormacionPopup()`, `confirmarCombateRuta()`.
   - No crear archivos Blade nuevos (todo en secciones dentro de `show.blade.php`).

2. `resources/views/equipos/index.blade.php`:
   - Slots 3 → 5: `slot in [1,2,3]` (línea 342) y `addToTeam` emptySlot `[1,2,3]` (línea 1534).
   - Badge INVÁLIDO `team.members.length < 3` (línea 310): regla de producto, SE MANTIENE.
   - `composicionBadge` (línea 1427): solo para 3 miembros → null; SE MANTIENE (documentado).
   - Editor de formación por tarjeta de equipo: toggle vanguardia/retaguardia por slot
     (2 botones), estado `formacionDraft` (por teamId), `formacionDe(team, slot)` =
     draft ?? `team.formacion?.[slot]` ?? 'vanguardia', botón "Guardar formación" → PATCH,
     feedback inline (error/success); deshabilitado en exploración.
   - Estado Alpine nuevo en `favoritosApp()`: `formacionDraft`, `formacionSavingTeamId`,
     `formacionSaveError`, `formacionSaveSuccess` + `formacionDe()`, `setFormacionSlot()`,
     `guardarFormacion()`.

3. Partials de combate (`battle-field`, `turn-bar`, `moves-panel`, `_pokemon-card`): verificados
   dinámicos (`@foreach` por posicion / turnQueue) — **sin cambios**; ya soportan 5v5.

### Estados UI cubiertos

- Rivales: loading (spinner), error (aviso), empty (pool vacío → aviso), success (grid 5).
- Popup ruta: 422 inline (`rutaCombatError`), botón deshabilitado mientras `rutaCombatiendo`.
- Equipos: formación sin cambios → guardar deshabilitado; exploración activa → editor disabled.
- `bloqueadoConstruccion` → todos los botones de construcción (incluido ruta) disabled.

### Riesgos / decisiones

- Widget "⚙️ Automática" solo en contexto ruta; entrenadores conserva su comportamiento.
- `isSighted(rival.species_id)` reutiliza el helper existente (misma lógica que niveles).
- Widget toggle del popup: `toggleFormacionSlot()` desde undefined → 'vanguardia' (sin
  ciclo automática↔vanguardia; decisión aceptada).
- Nota: `docs/context.md` desactualizado (describe 3v3); NO se toca (constraint).

### Verificación

- `npm run build` (clases Tailwind nuevas: grid-cols-5, chips formación, etc.).
- `php -l` / `php artisan view:cache` (Blade compila).
- Tests: ampliar `FrontendHabitatModalTest` (ruta panel + nota + popup ruta) y
  `EquiposViewTest` (5 slots + formation editor strings). Suite completa al final.

## ✅ Verificación final (2026-09-13)

- `php artisan view:cache` OK (plantillas compilan).
- `FrontendHabitatModalTest` + `EquiposViewTest`: 9/9 pass (79 asserts) — incluye
  `test_habitat_defaults_to_ruta_mode_with_ruta_panel`, `test_habitat_team_cards_render_five_slots`
  y `test_equipos_renders_five_slots_and_formation_editor`.
- Suite completa: **1061 passed, 7 skipped, 0 failed** (sin regresiones).
- `vendor/bin/pint --dirty` OK (solo EOF en `EquiposViewTest`).
- `npm run build` OK (Tailwind compilado, `combate-*.css` regenerado).
- Pendiente manual en navegador: popup de ruta, editor de formación, chips 5v5.

## 🧾 Trazabilidad aplicada (2026-09-13)

1. `resources/views/habitats/show.blade.php`:
   - Panel izquierdo: se elimina el enlace Favoritos; se añaden "Combate de ruta"
     (activo por defecto, `modo: 'ruta'`, ring `bg-blue-600`) y "Exploraciones" (icono).
   - Panel ruta 5v5 (nota obligatoria, badge, selector de nivel, aviso pool vacío,
     CTA sin formación, preview rivales 5 columnas, no-pool).
   - Popup de formación contextual (`formacionContexto` 'ruta'|'entrenadores'|'pokémon') con
     widget "⚙️ Automática" cuando el slot usa la formación persistida; `confirmarCombateRuta()`
     con error 422 inline (`rutaCombatError`) sin redirigir tras éxito.
   - Tarjetas de equipo a 5 slots (`@for 0..5`, `grid grid-cols-5`).
2. `resources/views/equipos/index.blade.php`:
   - Slots 3 → 5 (`slot in [1,2,3,4,5]`) en grid y `addToTeam` (busca hueco 1..5).
   - Editor de formación persistente por tarjeta: draft local `formacionDraft`
     (draft > `team.formacion` > 'vanguardia'), toggle vanguardia/retaguardia por slot,
     "Guardar formación" → `PATCH /teams/{team}/formacion` (contrato `{team:{id, formacion}}`),
     feedback inline ok/error, deshabilitado en exploración activa.
3. Tests nuevos en `FrontendHabitatModalTest` y `EquiposViewTest` (ver IA arriba).

## Riesgos/notas abiertas

- `PATCH /teams/{id}/formacion` puede devolver 422 sin `error` para equipos ajenos/inactivos
  (RespuestaApi sin responderError) → la UI mostraría el mensaje genérico. El editor solo opera
  sobre equipos propios desde `/equipos`, así que el caso realista no se dispara.
- La formación guardada se refleja en el hábitat si el backend expone `team.formacion` en su API
  de equipos/rivales; el primer PATCH cierra el `{formacion: {}}` pendiente.
- `docs/context.md` sigue describiendo 3v3 (constraint: no se toca).

---

# ANÁLISIS FRONTEND — Refactor de gimnasios (eliminar lógica de dominio duplicada + medalla)

Fecha: 2026-09-14
Rol: Frontend (Blade + Alpine.js + Tailwind 4). Solo UI: NO tests (indicación explícita del brief).

## Contexto / fuentes leídas
- `docs/context.md`, `docs/architecture.md`, `docs/conventions.md`, `active/RESUMEN_TAREA.md`.
- `.ai/rules/index.md` (sin reglas de glob para `resources/views/**`) + `grep` de keywords (blade/view/gimnasio/medalla/tipoBadge): sin coincidencias relevantes.
- `src/Gimnasios/context.md` — catálogo, contrato público.
- `src/Shared/UI/TipoBadges.php` — `MAP` (int→[label, tailwind]) y `DEFAULT` (single source of truth).
- `src/Shared/Tipos/TipoPokemon.php` — `label()` (nombre ES) y `slug()` (`strtolower($this->name)`: `bicho`, `lucha`, `electrico`, `psiquico`…).
- `src/Gimnasios/Domain/DataTransferObjects/{GimnasioResumen,DetalleGimnasio}.php` — `toArray()` con `tipo_nombre` + `tipo_slug`.
- `src/Gimnasios/Infra/Controllers/GimnasioController.php` — endpoints públicos `/api/gimnasios` y `/api/gimnasios/{gym}`.
- `public/images/medallas/` — 19 assets por **slug** (`normal.webp`, `electrico.webp`, `psiquico.webp`…), sin `0.webp`; el nombre de la medalla NO coincide con el archivo.
- Las 3 vistas `resources/views/gimnasios/{index,show,admin}.blade.php`.

## Qué tocar (3 ficheros, sin crear componentes nuevos)
1. `index.blade.php`: sustituir el `@php` inline (líneas 6-29) por `\Src\Shared\UI\TipoBadges::MAP` / `::DEFAULT`; corregir la medalla a `gym.tipo_slug` con `onerror`; badge de tipo `x-text="gym.tipo_nombre"`.
2. `show.blade.php`: mismo `@php` + `$cardPanelClass`; misma corrección de medalla (tamaño `text-4xl`); mismo badge.
3. `admin.blade.php`: mismo `@php` + `$cardPanelClass`; medalla emoji 🏅 (sin cambio); `<select>` usa `tipoBadges` (sin cambio); badge de card con `gym.tipo_nombre`.

## DTOs/API consumidos
- `GET /api/gimnasios` → `GimnasioResumen::toArray()` (incluye `tipo`, `tipo_nombre`, `tipo_slug`).
- `GET /api/gimnasios/{slug}` → `DetalleGimnasio::toArray()` (incluye `tipo`, `tipo_nombre`, `tipo_slug`, `etapas`).
- `GET /api/admin/gyms` y `/api/admin/gyms/{slug}` → `AdminGymController::serializar()` (**NO** incluye `tipo_nombre`/`tipo_slug`; solo `tipo` int).

## Estados UI a preservar
- Loading, error, empty y success ya existen en las 3 vistas; el refactor no los altera.
- Imagen de medalla: `onerror="this.style.display='none'"` (mismo patrón anti-loop del proyecto) evita icono roto si el slug no tuviera asset.
- `tipoBadge()` se mantiene para el color (`:class`) y como fallback de texto.

## Riesgos / desvío justificado
- **Discrepancia de contrato (P1)**: el brief pide `x-text="gym.tipo_nombre"` en la card del **admin**, pero `/api/admin/gyms` no expone `tipo_nombre`. Aplicarlo literal dejaría el badge **vacío** (regresión visible). Desvío mínimo y justificado: `x-text="gym.tipo_nombre || tipoBadge(gym.tipo)[0]"` — usa el campo nuevo cuando exista y conserva el texto actual mientras el backend no lo añada. El `<select>` y `tipoBadge(detail.tipo)` siguen igual.
- La medalla depende del slug del tipo (no del nombre): el backend ya lo entrega en el contrato público; no se duplica lógica de mapeo en Blade.

## Tests
- No se crean tests (indicación explícita del brief). Verificación: `npm run build`, `php artisan view:cache`, greps de `gym->medalla` y de arrays inline `$tipoBadges = [`.

## Verificación final (2026-09-14)
- `npm run build` OK (`app-BWbftYQV.css` + `app-CfKhHCJo.js` generados, 63 módulos).
- `php artisan view:cache` OK (las 3 plantillas compilan; `TipoBadges` resuelve).
- `grep -rn 'gym->medalla' resources/views/gimnasios/` → 0 resultados.
- `grep -rn '$tipoBadges = [' resources/views/gimnasios/` → 0 resultados.
- Tests existentes (no nuevos): `GimnasioAdminViewTest` + `GimnasioApiTest` → 20 passed (202 assertions).
- Pendiente (deuda backend, fuera de alcance Frontend): añadir `tipo_nombre`/`tipo_slug` a `AdminGymController::serializar()`; mientras, el badge admin usa el fallback `gym.tipo_nombre || tipoBadge(gym.tipo)[0]`.
