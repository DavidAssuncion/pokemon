# Análisis Frontend — Filtro tipo Pokédex + Descartar individual + AJAX Equipos sin reload

## Vistas a tocar

### 1. `resources/views/pokedex/index.blade.php` — filtro por TIPO
- Añadir dropdown "Tipo" junto a botones Todos/Vistos/Atrapados, con el mismo patrón visual del filtro de tipo de equipos (`bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg`).
- `@php $tipos = \App\Enums\TipoEnum::options(); @endphp` → `[id => nombre]` (18 tipos, labels en español, verificado).
- Estado Alpine: `typeFilter: null`, `showTypeFilter: false`. Getter `filteredPokemons`: filtrar con `p.types && p.types.includes(this.typeFilter)`.
- Cierre al hacer click fuera con el patrón de `equiposApp.init()` (copiar tal cual, instrucción del Arquitecto).
- Indicador "✕" dentro del botón cuando `typeFilter` está activo (`@click.stop` sobre un `<span>`, HTML válido button>span), con `x-text="typeFilter || 'Tipo'"`.
- Datos verificados: `PlayerController::pokedex()` ya pasa `types` = array de nombres (`['Fuego']`) en cada pokemon.

### 2. `resources/views/reclutamiento/index.blade.php` — botón Descartar + modal con cantidad
- Botón rojo "Descartar" bajo "Reclutar" en cada card (`openDiscardModal(item)`).
- Modal individual `x-if="showDiscardModal && discardItem"`: título "Descartar [nombre]", input number (min 1, max = item.cantidad, default 1), botones rápidos 25/50/75/Todos vía `setDiscardPercent(pct)`, texto "Se convertirán en caramelos de la familia [nombre]", Cancelar/Confirmar (rojo).
- `confirmDiscard()`: `POST /reclutamiento/discard` con `{ reclutable_id, cantidad }`; update de estado local (quitar item si cantidad agotada, sino restar).
- **Conflicto detectado**: `showDiscardModal` YA existe para el modal de "Descartar todos". Para evitar que ambos modales se rendericen a la vez, renombro el flag del modal de "Descartar todos" a `showDiscardAllModal` (comportamiento del modal intacto, "se mantiene") y uso `showDiscardModal` para el modal individual, tal como pide el snippet de `openDiscardModal`.
- Estados nuevos: `discardItem: null`, `discardQuantity: 1`.
- Ruta backend `POST /reclutamiento/discard` aún no existe en `routes/player.php` (la añadirá el backend en paralelo; patrón idéntico a `/reclutamiento/recruit` y `/reclutamiento/discard-all`, ambos `JsonResponse` con csrf-token meta ya presente en layout).
- Guard extra en `confirmDiscard`: `Math.max(1, Math.min(this.discardQuantity || 1, this.discardItem.cantidad))` (protege input vacío/NaN).

### 3. `resources/views/equipos/index.blade.php` — AJAX sin recarga
- `createTeam`: push `{ ...data.team, members: [] }` al array `teams`; cerrar form.
- `deleteTeam`: `teams.filter(...)` + liberar `teamPokemonIds` recorriendo `teamToDelete.members` con `m.pokemon_id`.
- `addToTeam`: push `data.member` a `team.members` (`id`, `team_id`, `pokemon_id`, `slot`, `behavior`, `reclutado: pokemon`) + `teamPokemonIds.push(pokemon.id)`.
- `removeMember`: filtrar miembro del team + liberar `teamPokemonIds`.
- `removeFromTeam`: **fix necesario** — `reclutado.team_id` NO existe en el JSON (verificado por tinker: `Reclutado::with('pokemon')` no serializa `team_id`; el modelo no tiene accessor ni columna). Buscar team por `t.members.some(m => m.pokemon_id === pokemon.id)` (OJO del Arquitecto: `member.pokemon_id` es id de Reclutado, igual que `pokemon.id` — verificado: `team_members.pokemon_id` → FK a `reclutados.id`).
- `startRename`: `PUT /teams/{id}` → `data.team.name` actualiza el objeto local.
- Errores: helper `handleError(response)` que ante 422 parsea `{ error }` y hace `alert(error)`; usado en `else` de cada operación.
- El filtro de tipo de equipos (getter `availablePokemons`) NO se toca (fuera de alcance; `tipo_nombre` no se serializa en `pokemon.types` pero es comportamiento existente del backend, no de esta tarea).
- Backend devolverá JSON (`team`, `member`) — hoy los endpoints devuelven `RedirectResponse`; el backend en paralelo los cambia (no toco controladores).

## DTOs consumidos
- Ninguno nuevo. `$pokemons` (array con `types`), `$reclutables` (`{id, pokemon_id, nombre, cantidad}`), `$teams` (`members[].pokemon_id` = id Reclutado, `members[].reclutado`), `$reclutados`, `$teamIds`, `$equiposEnExploracion`.

## Tests
- Sin Dusk en el proyecto. Verificación: `php artisan view:cache` (compila sin errores), `vendor/bin/pint --dirty --format agent`, render real de las 3 vistas con tinker.

## Estados UI cubiertos
- Pokédex: dropdown tipo con hover/active, filtro combinado con búsqueda y Todos/Vistos/Atrapados, ✕ limpiar filtro, empty state existente.
- Reclutamiento: card con 2 botones, modal individual (input, rápidos, cancelar, confirmar), item eliminado vs cantidad reducida, modal discard-all coexistente.
- Equipos: alta/borrado/rename/add/remove sin recarga; badge INVÁLIDO y slots se actualizan en vivo; 422 → alert.

## Riesgos accesibilidad/UX
- Span ✕ con `@click.stop` dentro de button: válido, `aria-label` incluido.
- Input number sin validación manual estricta → guard `Math.max(1, ...)` en confirm.
- Conflicto de flags de modal resuelto por renombrado interno (sin cambio visual en discard-all).
- `removeFromTeam` dependía de `team_id` inexistente → el fix restaura el botón naranja "→" (antes muerto).
