# ANÁLISIS FRONTEND — Refuerzo tolerancia UI en `/equipos` (2026-09-16)

## Objetivo

Blindar el frontend de `/equipos` para que el listado de reclutados no se rompa ni quede
vacío silenciosamente cuando algún campo del serializer falte o venga null. El backend está
diagnosticando si la causa es 500 por migraciones o serializer. Este análisis es previo al
código y se centra en tolerancia, no en ocultar.

## 1. Vistas/componentes a tocar

- `resources/views/equipos/index.blade.php` — único archivo modificado.

## 2. Contrato consumido (ReclutadoSerializer::serializar)

Campos que el frontend consume de cada reclutado:

| Campo | Tipo contrato | Riesgo null | ¿Ya tolerado? |
|-------|---------------|-------------|----------------|
| `id` | int | Bajo (PK) | Sí |
| `nombre` | string | Medio (nullable column) | Parcial (fallback en serializer, pero JS no blindado) |
| `pokemon_id` | int | Medio | NO — genera URL rota |
| `pokemon.types` | array | Medio | Sí (guard `r.pokemon && r.pokemon.types`) |
| `es_shiny` | bool | Bajo | Sí |
| `nivel` | int | Bajo (calculado) | Sí |
| `cp` | int | Bajo (calculado, 0) | Sí (null check) |
| `ivs` | object/array | Medio | Parcial — `ivsDe` tolera, pero template no |
| `evs` | object/array | Medio | Parcial — `evsDe` tolera, pero template no |
| `habilidades` | array | Bajo (siempre []) | Sí |
| `stats` | array | Bajo | Sí (guard en `statsDe`) |
| `exp_total` | int | Bajo | Sí |
| `base_experience` | int/null | Medio | Sí |

## 3. Comportamientos a cubrir (testing futuro, NO ejecutado)

- Nombre null → fallback a `pokemon.name` → fallback a `?`
- `pokemon_id` null → icono no se rompe, se oculta limpiamente
- `ivs`/`evs` ausentes (serializer sin migración) → modal detalle no truena
- `habilidades` no-array → template no truena
- Lista vacía de reclutados → mensaje informativo visible, no grid vacío
- Flujos de huevos y equipos no afectados

## 4. Estados UI a cubrir

| Estado | Antes | Después |
|--------|-------|---------|
| `reclutados.length === 0` | Grid vacío silencioso | Mensaje "No hay Pokémon disponibles" |
| `pokemon_id` null | `<img src="...undefined.webp">` roto | Icono oculto, sin error visible |
| `nombre` null | Podría mostrar "null" o vacío | Fallback a `pokemon.name` → `?` |
| `ivs`/`evs` null en modal | Posible error Alpine | Template con fallbacks |

## 5. Cambios propuestos (sin código)

1. **`availablePokemonsTeams`** (línea ~1101): blindar `r.nombre.toLowerCase()` con `String(r.nombre || '').toLowerCase()`.
2. **`assignedPokemons`** (línea ~1120): mismo blindaje.
3. **`nombreDe`** (línea ~1177): ya tolera null; verificar fallback a `pokemon.pokemon.name` vacío.
4. **Icono** (líneas ~299, ~370, ~752): añadir helper `iconoDe(p)` que devuelva URL vacía si `pokemon_id` ausente.
5. **Modal detalle** (líneas ~838-885): IVs/EVs con fallback `(p.ivs || {})`, `Array.isArray()` checks.
6. **Estado vacío** (línea ~353): condición mejorada para distinguish filter vs data.

## 6. Riesgos accesibilidad/UX

- El estado vacío ya tiene `x-if` — no afecta lectores de pantalla.
- El icono roto con `onerror="this.style.display='none'"` es aceptable; mejor con helper que devuelva string vacío.
- No se ocultan errores reales: si el backend falla, la UI muestra lo que tiene (tolerante).

## 7. Campos del serializer que puedo asumir que pueden faltar

- `ivs` / `evs` — podrían no existir si la migración de columnas no se ejecutó.
- `habilidades` — siempre `[]` según el serializer (TODO en el código).
- `pokemon.types` — podría no estar en el nested `pokemon` si la relación no se eager-load.
- `cp` — calculado; podría no estar si `pokemon` es null.
- `pokemon_id` — debería existir siempre (columna PK FK), pero si la migración falla, podría no estar en `toArray()`.

---

# ANÁLISIS FRONTEND — Logs de depuración en `/equipos` (2026-09-16)

## Objetivo

Diagnosticar por qué los reclutados no se muestran en `/equipos` aunque el backend los envía.
**Solo se añaden logs de depuración; NO se cambia lógica de negocio ni estructura.**

## Bug y contexto

- El usuario recluta pokémon (inserta en `reclutados`), pero en `/equipos` no se muestra ningún
  pokémon (ni disponibles ni asignados a equipos).
- El backend funciona: los tests pasan y el HTML ya incluye `@json($reclutados ?? [])` (línea 1019),
  `@json($teams ?? [])` (1021), `@json($teamIds ?? [])` (1022).
- `browser.log` muestra un error del bundle JS viejo en otras páginas:
  `Uncaught TypeError: Cannot redefine property: $persist`, que rompe el arranque de Alpine.
  `/equipos` es 100 % Alpine (`x-data="favoritosApp()"`): si Alpine no arranca, no se pinta nada.
- Tras reconstruir la imagen Docker (`docker compose up -d --build`), los logs deben confirmar:
  (a) Alpine arranca, (b) los datos llegan al componente, (c) algún error JS bloquea el render.

## 1. Vistas/componentes a tocar

- `resources/views/equipos/index.blade.php` — único archivo modificado.

## 2. DTOs/contratos consumidos

- No se consume ningún DTO nuevo. Se leen los datos ya inyectados en `favoritosApp()`:
  `reclutados`, `teams`, `teamPokemonIds` (arrays serializados a JSON en Blade).

## 3. Comportamientos a cubrir (testing futuro, NO ejecutado)

- `init()` se ejecuta ⇒ Alpine arrancó (`[equipos:init]` encontrado en consola).
- `reclutados` es array y su longitud coincide con lo que envía el backend.
- Primer reclutado serializado en JSON (recorte 500 chars) para detectar campos faltantes/null.
- Si existe un reclutado con `pokemon_id === 436`, log explícito de su `id`/`nombre`/`pokemon`.
- `reclutados` vacío o no-array ⇒ log de error con el JSON crudo.
- Error JS global capturado con `window.addEventListener('error')` para ver si Alpine muere.
- `[equipos:script-load]` se imprime aunque Alpine falle ⇒ confirma que el `<script>` se ejecutó.

## 4. Estados UI a cubrir

| Estado | Cómo se diagnostica |
|--------|---------------------|
| Alpine no arranca | Ausencia de `[equipos:init]` + presencia de `[equipos:script-load]` |
| Error JS bloqueante | `[equipos:global-error]` con mensaje + archivo:línea |
| `reclutados` vacío | `console.error` con JSON crudo recortado |
| `reclutados` con datos pero no renderiza | `[equipos:init]` con longitud > 0 + primer reclutado serializado |

## 5. Logs a añadir (sin lógica nueva)

1. Al inicio de `init()` (línea 1137): conteos de `reclutados`, `teams`, `teamPokemonIds`.
2. En `init()`, tras el log anterior: listener global `error` una sola vez (guard
   `window.__equiposOnErrorInstalado`).
3. Al final del `<script>` de `@push('scripts')`, antes de `</script>` (línea 2206), fuera de
   `favoritosApp()`: log de carga con estado de `window.Alpine`.

## 6. Riesgos accesibilidad/UX

- Los logs son solo consola; no alteran DOM, accesibilidad ni UX.
- El listener global se instala una única vez (guard) para no acumular handlers.
- No se exponen datos sensibles: solo longitud, JSON recortado y campos de reclutado.

## 7. Alcance y no-alcance

- **Alcance**: añadir los 3 bloques de logs descritos.
- **No-alcance**: no tocar getters, templates, endpoints, ni la lógica de render. Los logs son
  temporales de diagnóstico; se retirarán al cerrar el bug (fuera de esta tarea).

---

# ANÁLISIS FRONTEND — Equipos de 3 y turnos con `effectiveSpeed` (2026-10-15)

## Objetivo

Adaptar las vistas Blade (y sus tests por lectura) a los commits backend `3039027d89` +
`f846f1ca5e`:

1. Equipos estándar de **3 miembros** (máximo de asignación; "exactamente 3" al combatir).
2. Contrato de la barra de turnos: `aArrayVista` expone `effectiveSpeed` (float); `accumulatedSpeed`
   ya no existe (clave indefinida en Blade).
3. Textos de UI "5v5" → "3v3" y slots de equipo 1-5 → 1-3.

Testing diferido: NO se ejecutan tests; los adaptations de tests de vistas se hacen por lectura.

## 1. Vistas/componentes a tocar

| Archivo | Cambio |
|---|---|
| `resources/views/livewire/partials/turn-bar.blade.php` | `$p['accumulatedSpeed']` → `$p['effectiveSpeed']` (mantiene `round()`). |
| `resources/views/equipos/index.blade.php` | Grid de slots del equipo `grid-cols-5`→`grid-cols-3`; `x-for="slot in [1,2,3,4,5]"`→`[1,2,3]`; `addToTeam` busca hueco en `[1,2,3]`; `guardarFormacion` recorre `slot <= 3`; comentarios `(combate 5v5)`→`(combate 3v3)`. |
| `resources/views/habitats/show.blade.php` | Tarjetas de equipo: `grid-cols-5`→`grid-cols-3`, `@for $i < 5`→`$i < 3`; textos `(Ruta 5v5)`→`(Ruta 3v3)`; comentarios `(hasta 5 miembros)`/`(combate 5v5)`/`(5 slots)`→3; `formacionInicialDe` slots 1-3. |
| `resources/views/gimnasios/show.blade.php` | `formacionInicialDe` slots 1-3. |
| `tests/Feature/FrontendHabitatModalTest.php` | Comentarios 5v5→3v3; assert obsoleto L86 reconciliado; grid `grid-cols-3`; `Vacío` ×3; nombre del test `..._five_slots`→`..._three_slots`. |
| `tests/Feature/EquiposViewTest.php` | Asserts `[1,2,3]`; comentarios/docblock/nombre `five_slots`→`three_slots`. |

## 2. Contratos consumidos (verificados por lectura en backend)

- `src/Battle/Domain/Combatiente::aArrayVista()` → `effectiveSpeed` (float, con multiplicadores y
  parálisis), sin `accumulatedSpeed`. La vista ya resuelve `$p` desde `$team1/$team2` (mismo shape).
- `TeamController::addMember` valida `slot: max:3`; `ConstruirEquipoJugador::desdeEquipo` exige
  `count === 3`; la migración recorta `slot > 3`. La UI ya tenía `team.members.length < 3` (badge
  INVÁLIDO) y `length !== 3` (composición), por lo que solo quedaban los límites de 5 residuales.
- `teams.formacion` / payloads de combate: `{slot: 'vanguardia'|'retaguardia'}` con slots 1-3.
  `formacionInicialDe` de ambas vistas genera ahora solo 1-3 (claves 4-5 fuera del contrato).
- La UI de equipos ya no ofrece un 4º slot (grid de 3) y `addToTeam` no encuentra hueco fuera de
  1-3 (alerta "Este equipo está lleno"); no hay endpoint nuevo que consumir.

## 3. Comportamientos a cubrir en testing futuro (NO ejecutado)

- Barra de turnos: renderiza la velocidad efectiva redondeada (sin índice indefinido → sin
  warnings PHP) tras cambios de stages/parálisis.
- Equipos: un equipo de 3 muestra 3 slots; nunca un 4º placeholder ni opción de añadir 4º.
- `guardarFormacion` no envía claves de slot 4-5 aunque existieran en `team.formacion` legacy
  (el bucle corta en 3 y `getMember` descarta).
- Hábitats: tarjetas de equipo con 3 slots y exactamente 3 placeholders "Vacío" si el equipo
  está vacío; `formacionInicialDe` de ruta/entrenadores 1-3 y `mazmorra` data-driven.
- Gimnasios: `formacionInicialDe` 1-3; el sed del payload no incluye slots 4-5.

## 4. Estados UI a cubrir

| Estado | Comportamiento |
|---|---|
| Equipo con 0-2 miembros | 3 slots visibles; los vacíos como placeholder (sin añadir 4º). |
| Equipo con 3 miembros | Sin hueco para añadir; `addToTeam` bloquea con alerta. |
| `team.formacion` legacy con slots 4-5 | Se ignora en UI y no se reenvía al PATCH. |
| Turno con velocidad efectiva alterada | Badge muestra el valor efectivo redondeado. |

## 5. Riesgos accesibilidad/UX

- El grid de 3 columnas mantiene tamaño de iconos (`h-16`) y no rompe lectores (mismo markup).
- No se cambian textos visibles más allá de 5v5→3v3; sin nuevas interacciones.

## 6. Verificación (sin tests)

- Grep final en `resources/views` y `tests/` de `5v5|hasta 5|1,2,3,4,5|grid-cols-5|Vacío`.
- Revisión del diff completo; `vendor/bin/pint --dirty` para los PHP de tests.

## 7. Nota de estado del working tree (WIP preexistente)

`resources/views/equipos/index.blade.php` y `active/ANALISIS_FRONTEND.md` ya traían cambios sin
commitear de la tarea de frontend previa (tolerancia UI + logs de diagnóstico, documentados en
las secciones anteriores de este archivo). No se revierten: se conservan y este commit los
absorbe junto con los cambios de 3v3/`effectiveSpeed`. Pendiente de QA decidir la retirada de
los logs de diagnóstico (son temporales por diseño).

### Addendum (2026-10-15) — limpieza autorizada por QA O2b

Retirado el andamiaje de diagnóstico `[equipos:*]` de `resources/views/equipos/index.blade.php`:
`console.log` de `init()`/script-load, `console.error` de reclutados vacío, forEach condicional
del pokemon 436 y listener global de `error` con `window.__equiposOnErrorInstalado` (verificado
que no se usaba en otro sitio). Se conservan el listener de click del `init()` y todos los
`console.error` funcionales sin prefijo (fetch/formularios). Grep final `[equipos:` = 0 restos;
ningún test de vistas asertaba esos logs. Resto del WIP del archivo intacto.
