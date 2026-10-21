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

# ANÁLISIS FRONTEND — Animación de vitales en combate: barras progresivas, estela de daño y estado protegido (2026-10-21)

## Objetivo

Que el combate comunique los cambios de vitales (HP, DEF y DEF.ESP) sin saltos: el tramo perdido
queda marcado en un tono más claro (estela) y se drena después, la barra sube/baja progresivamente,
una pista flotante muestra el delta (`-N`/`+N`), y el golpe/curación se señala sobre la tarjeta
(temblor + destello rojo / destello verde). Además, mientras un pokémon conserva barreras activas
(no puede perder vida todavía) su tarjeta muestra un estilo distintivo de "protegido".

## Bug reportado

> "Hay ocasiones en las que se cancelan las animaciones y simplemente sucede todo de golpe y se
> cambia el turno."

## 1. Causa raíz del bug (verificada por lectura)

La única animación actual (`.anim-hit`: temblor + destello rojo) viaja **en el HTML del servidor**.
`Combate::setAnimState()` marca al defensor en `$animDefenderId` y `_pokemon-card.blade.php` pinta
la clase. Cualquier respuesta de Livewire durante la ventana de 700 ms vuelve a renderizar el
componente **sin** esa clase, y el morph borra la animación a media reproducción.

La ventana no está bloqueada: `$phase` sigue valiendo `player_move`/`player_target` mientras la
animación corre (tanto en la acción del jugador como en la del rival), así que en esos 700 ms:

- las tarjetas rivales siguen clicables (`previewTarget`) → render que **cancela** el temblor;
- los botones de ataque siguen activos (`selectMove`) → sobrescribe la acción pendiente y reinicia
  el temporizador (`animTick++` → el watcher limpia y reprograma).

Si el clic cae justo antes de que dispare el temporizador, el `commitAction` aplica el daño y el
siguiente actor en un único render: la animación se canceló y "todo sucede de golpe".

## 2. Estrategia elegida (sin estado de animación en PHP)

1. **Animación de vitales en cliente, en el momento del impacto**: el `x-init` de
   `combate.blade.php` toma una instantánea de los anchos de las tres barras antes de
   `$wire.commitAction()` (la promesa de Livewire resuelve después del morph) y, al volver,
   compara y anima. Aunque una ventana se saltara, el daño siempre se ve progresivo.
2. **Ventana no cancelable**: durante `$animAttackerId !== ''` el panel de ataques muestra el
   estado "atacando" (quién y con qué movimiento) y las tarjetas dejan de ser seleccionables.
   Sin renders intermedios no hay animaciones canceladas ni acciones pisadas.
3. **Telegrafía reubicada**: el temblor deja de aplicarse al defensor *antes* del golpe (700 ms de
   antelación) y pasa al **momento del impacto**; el atacante recibe una embestida (`.anim-ataque`)
   durante la ventana, que es el verdadero aviso previo.

## 3. Vistas/componentes a tocar

| Fichero | Cambio |
|---|---|
| `resources/views/livewire/_pokemon-card.blade.php` | `data-ref-id`, `con-barreras`, `anim-ataque`; estela + clases `vital-*` en las 3 barras; clases en los contadores; retira `$recibeGolpe`/`x-ref` muertos. |
| `resources/views/livewire/partials/moves-panel.blade.php` | Rama previa: con `$animAttackerId` activo muestra "atacando" (bloquea la interacción). |
| `resources/views/livewire/combate.blade.php` | `x-init`: instantánea → `await commitAction()` → animar; conserva temporizador de 700 ms. |
| `resources/views/combate_page.blade.php` | Script inline (convención de páginas del repo) con `instantaneaVitales()`/`animarVitales()`. |
| `resources/css/combate.css` | Transiciones de barras, estela, `con-barreras`, `.anim-ataque`, `.anim-cura`, `.vital-delta`, `prefers-reduced-motion`. |
| `app/Livewire/Combate.php` | 1 línea: `$this->animMoveNombre = $move->nombre` en `setAnimState()` (hoy nunca se rellena). |

## 4. Contrato consumido (verificado por lectura)

- `Combatiente::aArrayVista()` expone `hp/maxHp`, `defHp/maxDefHp`, `spDefHp/maxSpDefHp`, `alive`,
  `posicion`, `canTarget` (este último lo inyecta `Combate::syncViewData`). Nada nuevo que consumir.
- `Livewire\Features\SupportMorph` inserta `wire:id` en la raíz en **cada** render, por lo que la
  raíz del componente se morphea en el sitio (no se reemplaza) y `x-init` no se re-ejecuta.
- La promesa de `$wire.commitAction()` resuelve tras el morph (`.finally()`), punto válido para
  medir el DOM ya actualizado.

## 5. Estados UI a cubrir

| Estado | Comportamiento esperado |
|---|---|
| Daño que entra a HP | Barra baja progresiva; estela roja marca el tramo perdido y se drena; temblor + píldora `-N`. |
| Daño absorbido por barrera | Igual pero sobre DEF (`info`) o DEF.ESP (`primary`); la píldora suma el delta de barreras. |
| Sobrepaso directo (barrera llena y resto a HP) | Las dos barras bajan a la vez; la píldora suma ambos deltas. |
| Curación (restos, drenaje, movimiento) | Barra sube progresiva; destello verde + píldora `+N`; sin estela visible. |
| Cambio sin delta (movimiento de estado) | No hay animación de barras ni píldora; solo registro/etiquetas. |
| Barreras activas | Tarjeta con franja celeste en el borde izquierdo + tinte sutil; al agotarse ambas barreras, el estilo desaparece. |
| Ventana de acción (700 ms) | Panel en estado "atacando" y tarjetas no seleccionables; luego vuelve el control normal. |
| `prefers-reduced-motion` | Sin animaciones ni transiciones; la píldora no se muestra (valores igualmente actualizados). |

## 6. Comportamientos a cubrir en testing futuro (NO ejecutado)

- El temblor ya no se pinta desde el servidor: `grep anim-hit` en `resources/views` = 0.
- `setAnimState()` rellena `animMoveNombre` y `resetAnimState()` lo limpia (contrato del panel).
- Con `$animAttackerId !== ''` no se emite `wire:click` en tarjetas seleccionables.
- La instantánea/animación es degradación progresiva: si el script de página no carga, el combate
  sigue funcionando (temporizador en la vista del componente), solo sin animación.

## 7. Riesgos accesibilidad/UX

- `.vital-delta` es decorativa (`aria-hidden`) y no duplica información: los contadores numéricos
  siguen en el DOM. Las barras ya tienen `role="progressbar"` + `aria-valuenow` actualizado.
- El bloqueo de la ventana no añade un modo de fallo nuevo: la batalla ya depende del temporizador
  cliente para invocar `commitAction()` (si no dispara, la partida ya estaba detenida).
- `prefers-reduced-motion` desactiva animaciones/transiciones y oculta la píldora.

## 8. Verificación (sin tests, testing diferido)

- `npm run build` (los assets servidos salen de `public/build`).
- `php artisan view:cache` + `php artisan view:clear` como smoke de sintaxis Blade.
- `vendor/bin/pint --dirty --format agent` para los PHP tocados.
- Grep final: `anim-hit` fuera de CSS/JS = 0; `recibeGolpe` = 0.

## 9. Nota de estado del working tree (WIP preexistente)

`app/Http/Controllers/PlayerController.php` y `resources/views/equipos/index.blade.php` ya venían
modificados sin commitear (más ficheros sin trackear de soporte/datos). No se tocan; este cambio
solo afecta a los ficheros de la tabla de la sección 3.

## Addendum (2026-10-21) — cola de eventos de combate, impacto y cambio de turno

### Objetivo

Completar el feedback de combate: separar daño a HP y a barreras, mostrar la rotura de barrera y
el golpe letal en la vista previa; reproducir la reacción al impacto (hit-stop + sacudida/zoom
según daño); animar estados, curaciones, KO y baja vida; y permitir adelantar el turno cuando la
cola muestra dos pokémon del jugador consecutivos.

### Implementación

| Fichero | Cambio |
|---|---|
| `src/Battle/Domain/BarrerasVidaManager.php` | `desglosarDaño()` puro (HP directo + excedente / absorbido) reutilizado por `recibirDaño()`. |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | Expone `danoHp`, `danoBarrera`, `barreraLabel` y `letal`; la tarjeta muestra `N DEF` / `N PS` y el badge `LETAL`. |
| `app/Livewire/Combate.php` | Journal público `$eventos` con snapshots antes/después (`damage`, `status`, `effect`, `round`, `battle_over`, `fin`); `commitAction()` envuelve la resolución; la cola de turnos pone al actor actual primero; `swapTurno()` fuerza al siguiente jugador en la misma petición. |
| `resources/views/combate_page.blade.php` | `reproducirEventos()` secuencial, hit-stop de 60 ms, píldoras divididas, barrera rota, estado/cura, KO, baja vida y restauración de barras. |
| `resources/views/livewire/combate.blade.php` | Bloqueo con `inert` de panel/barra/campo durante la cola; restauración del log, banner y modal al final; clave raíz por `battleId` para no reiniciar `x-init` al cambiar de ronda. |
| `resources/views/livewire/_pokemon-card.blade.php` | Clases `hp-bajo`/`hp-critico`, `anim-ko`, sprite y estado final oculto. |
| `resources/css/combate.css` | Hit-stop, zoom, barreras, KO, estado, baja vida, banner/modal y `prefers-reduced-motion`. |

La cola se reproduce con la interfaz bloqueada y el estado final se revela al terminar: el log
mantiene el HTML previo durante la reproducción, el banner de victoria/derrota y el modal de
recompensas esperan al evento `battle_over`/al `finally` (con fallback de 8000 ms), y las tarjetas
recién debilitadas ocultan su insignia/opacidad final hasta el cierre.

### Cambio de turno

`swapTurno()` solo actúa en `player_target`/`player_move`, sin animación pendiente y con dos
turnos propios consecutivos en `turnQueue`. El actor actual sigue pendiente (no se consume dos
veces): el siguiente pokémon ataca ahora y el actual queda para después. El forzado es privado y
se consume en la misma petición; un refresco antes de atacar pierde la selección, pero no la
acción pendiente.

### Verificación (sin tests; testing diferido)

`php -l` de los PHP tocados, `vendor/bin/pint --dirty --format agent`, `node --check` del script
inline, `php artisan view:cache`/`view:clear`, `git diff --check` y `npm run build`. No se pudo
hacer QA en navegador (barreras/sobrepaso, curación, KO/victoria, movimiento reducido, swap).
