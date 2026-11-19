# TASK-064 — Análisis

- **ID:** TASK-064
- **Estado:** COMPLETADO (con verificación acotada posterior)
- **Analista:** `analyst`
- **Fecha:** 2026-11-19

## Resumen

He inspeccionado el código real de /home/david/pokemon (Laravel 12, autoload Src\ => src/).
Existe ya un flag de QA (POKEDEX_UNLOCK_ALL / ?unlock=1 en local/testing) en
app/Support/DesbloqueoPokedex.php, un inventario canónico (player_inventory) con
ItemCatalogo, un flujo de reclutamiento (ReclutamientoController::recruit +
CrearReclutado + DatosNuevoReclutado) y patrones sólidos de consumo/entrega de
caramelos con lockForUpdate. NO existe /debug, ni endpoint que entregue recursos
arbitrarios, ni reclutamiento directo desde Pokédex.

El trabajo pendiente es pequeño y encaja en 1 controlador App + 1 vista + 3-4 rutas
+ 1 servicio/caso de uso. Falta: (a) página /debug con filas de experiencia (cuenta),
tipos y stats; (b) acciones QA en el modal de /pokedex (reclutar + dar caramelo de
familia). Requiere decidir el gating común y la ubicación de la lógica (app/Support vs
src/). No hay tests que ejecutar ni migraciones necesarias: todo el esquema ya existe.

## Requisitos

- Funcionales:
  * GET /debug: fila "experiencia de cuenta" con botones +100/-100, +255/-255,
    +3072/-3072; 18 filas de tipos (tipo:{slug}) y 6 filas de stats (ev:{1..6}) con
    los mismos botones; refleja cantidades actuales del inventario y la experiencia.
  * /pokedex con modo QA activo: en el modal de detalle, botón "Reclutar" que crea un
    Reclutado del usuario a partir del pokemon_id, y botones para otorgar caramelos de
    la familia evolutiva (familia:{chain_id}).
  * /debug inexistente (404) fuera de local/testing, sin depender de APP_DEBUG.
- No funcionales:
  * CSRF en todos los POST; autorización por usuario autenticado; sin IDOR.
  * Operaciones atómicas (transacción + lock) y cantidades acotadas (nunca < 0).
  * Sin nuevas tablas ni columnas.
  * Lógica de negocio en backend; Blade solo presentación.
- Criterios de aceptación (ver sección 8 del formato).
- Ambigüedades:
  * ¿El "modo QA" de /pokedex debe depender del mismo mecanismo que /debug? (recomiendo
    un único gate).
  * ¿"Reclutar" debe aplicar bonos de familia (nido/EV) como recruit normal? (recomiendo
    sí, reutilizar el flujo).
  * ¿Cuántos caramelos otorga el botón de familia desde Pokédex? (no definido: +1/+10/+100
    o los mismos ±100/±255/±3072).
  * ¿El reclutamiento directo debe impedir duplicados de la misma especie? (hoy no hay
    ninguna regla de duplicados).

## Alcance afectado

- Módulos: App\Http\Controllers (nuevo DebugController o similar), App\Support
  (servicio de QA), routes/, resources/views/ (nueva vista debug + edición de pokedex),
  src/Reclutamiento (reutilización de CrearReclutado/DTO).
- Archivos nuevos probables: routes/debug.php, app/Http/Controllers/DebugController.php,
  app/Support/Qa/..., resources/views/debug/index.blade.php.
- Archivos a modificar: routes/web.php (require debug), resources/views/pokedex/index.blade.php
  (acciones QA en modal), opcionalmente PlayerController::pokedex (pasar flag ya existe).
- Tablas/contratos: users.experiencia, player_inventory, reclutados, pokedex, pokemon
  (evolution_chain_id), pokemon_evolution.
- Vistas/endpoints: GET /debug, POST /debug/experiencia, POST /debug/caramelos,
  POST /pokedex/reclutar, POST /pokedex/caramelo-familia (nombres a decidir).

## Evidencia

- app/Support/DesbloqueoPokedex.php:18-26 — activo(request): config('pokedex.unlock_all')
  o (boolean('unlock') && environment('local','testing')). No mira APP_DEBUG.
- config/pokedex.php:17 — 'unlock_all' => (bool) env('POKEDEX_UNLOCK_ALL', false).
- bootstrap/app.php:15-17 — withMiddleware vacío, no hay alias de middleware propios.
- routes/web.php:19-26 — todo el área de juego vive dentro de Route::middleware('auth');
  routes/player.php se incluye en ese grupo (web.php:44).
- routes/player.php:11 — GET /pokedex => PlayerController@pokedex.
- app/Http/Controllers/PlayerController.php:44-66 — calcula $unlockAll y lo pasa a la vista.
- app/Http/Controllers/PlayerController.php:165-200 — inventarioCaramelos: lee
  player_inventory por user_id y ev:%, mapea StatEnum->slug().
- app/Support/ItemCatalogo.php:28-41 — keyFamilia/keyEv/keyTipo (familias: cadena;
  ev: id 1..6; tipo: SlugTipo).
- app/Support/ItemCatalogo.php:123-144 — resolverFamilia: primer integrante por
  species_id/id, imagen candy_pokemon/{id}.webp; chain < 1 => Desconocido/0.webp.
- app/Support/ItemCatalogo.php:146-161 — resolverEv usa StatEnum::fromId()->slug().
- app/Support/ItemCatalogo.php:163-182 — resolverTipo recorre TipoEnum y usa SlugTipo.
- app/Models/PlayerInventory.php:15-29 — BelongsToUser (global scope), casts integer,
  fillable user_id/item_key/cantidad.
- app/Models/Concerns/BelongsToUser.php:20-40 — global scope por Auth::id(); inactivo en CLI.
- database/migrations/2026_08_29_000007_create_player_inventory_table.php:18-26 —
  unique(user_id,item_key), cantidad unsignedInteger default 0.
- database/migrations/2026_08_28_000001_add_experiencia_to_users_table.php:18-20 —
  users.experiencia unsignedBigInteger default 0 (en PG es bigint, sin CHECK).
- app/Models/User.php:24-29,46-61 — experiencia fillable + cast integer; nivel() usa
  NivelHelper::nivelDesdeExperiencia.
- src/Shared/Domain/NivelHelper.php:20-37 — curva 10×nivel³, SIN tope de nivel (nivel 100
  = 10.000.000).
- src/Exploraciones/App/PersistirRecompensas.php:153-155 — único increment real:
  $usuario->increment('experiencia', $expTotal) (sin clamp negativo ni tope).
- app/Providers/AppServiceProvider.php:89-100 — nivelJugador/progresoNivel derivados en
  view composer: no hay columnas derivadas que recalcular.
- app/Http/Controllers/ReclutamientoController.php:29-73 — recruit: valida reclutable_id,
  transacción, findOrFail acotado por user_id, bonos familia, IVs reclutamiento, EVs de
  bonos, CrearReclutado, decrement/delete del Reclutable.
- app/Support/CrearReclutado.php:20-37 — crea Reclutado con exp 1500, es_shiny del DTO,
  movimientos/obj null, ivs/evs, save, y dispatch ActualizarPokedexJob(RECLUTADO).
- src/Reclutamiento/Domain/DataTransferObjects/DatosNuevoReclutado.php:15-25 —
  userId, pokemonId, ?ivs, esShiny.
- app/Models/Reclutado.php:22-36 — fillable y casts; behavior por defecto en BD
  ('COMBATIENTE'); habilidad_activa_id integer nullable.
- database/migrations/2026_09_05_000001_add_behavior_to_reclutados_table.php:112 —
  behavior default 'COMBATIENTE' NOT NULL.
- database/migrations/2026_10_22_000005_add_loadout_to_reclutados_table.php:13 —
  habilidad_activa_id FK nullable.
- database/migrations/2026_05_27_000001_create_reclutados_table.php:20-31 — nombre
  nullable, pokemon_id FK, exp json, es_shiny default false, obj/movimientos null.
- app/Models/Pokemon.php:17-38 — evolution_chain_id fillable; tabla pokemon con
  evolution_chain_id nullable (migración 2026_02_24_171456:22).
- app/Support/CadenasEvolutivas.php — miembrosDe/etapasDe por evolution_chain_id,
  con fallback fase 1 para cadenas huérfanas.
- app/Http/Controllers/ReclutadoController.php:87-110 — patrón lockForUpdate + decrement de
  PlayerInventory y corte por stock (consumo atómico).
- app/Http/Controllers/ReclutadoController.php:159-198 — lockForUpdate del Reclutado y del
  caramelo EV antes de aplicar.
- app/Http/Controllers/SendasController.php:132-180 — análogo exacto de "gastar N
  caramelos de tipo": valida cantidad in:1,10,100, lockForUpdate, min(consumidos, stock).
- app/Http/Controllers/ReclutamientoController.php:161-166 — firstOrCreate + increment para
  otorgar familia:{chain} (patrón reutilizable de entrega).
- src/Exploraciones/App/PersistirRecompensas.php:95-115 — insertOrIgnore + increment
  (patrón atómico de entrega).
- src/Granjas/App/InventarioGranja.php:130-160 — consumir* centraliza claves familia/ev.
- app/Providers/DatagridServiceProvider.php:52-116 — definición 'pokemon': itemFields solo
  icon/types/habitat/habitat_id (NO evolution_chain_id), detail con
  PresentadorDetalleEspecie::paraPokedex y gate DesbloqueoPokedex.
- app/Support/PresentadorDetalleEspecie.php:158-175 — species incluye evolution_chain_id.
- app/Support/PresentadorDetallePokemon.php:30-88 — family.evolution_chain_id y members
  {id,name,species_id,stage,parent_id,minimum_level,icon,is_current}.
- resources/views/pokedex/index.blade.php:15-20 — badge "Modo test" si unlockAll.
- resources/views/pokedex/index.blade.php:53-62 — pestaña "Todos" solo con unlockAll.
- resources/views/pokedex/index.blade.php:273-609 — modal de detalle (template
  x-if="showModal && selectedPokemon"); el cuerpo termina en 604-605.
- resources/views/pokedex/index.blade.php:616-621 — pokedexApp() con
  unlockAll: @json($unlockAll ?? false).
- resources/views/pokedex/index.blade.php:961-980 — openDetail/closeDetail.
- resources/views/pokedex/index.blade.php:982-1014 — loadDetail hace fetch a
  /datagrid/pokemon/{id}/detalle + ?unlock=1.
- resources/views/pokedex/index.blade.php:841-844 — fetch del listado a
  /datagrid/pokemon (headers Accept JSON).
- resources/views/layouts/app.blade.php:48-67 — $navItems del menú (Pokédex, Hábitats,
  Equipos, Sendas, Gimnasios, Forja); /debug NO debe añadirse aquí.
- resources/views/layouts/app.blade.php:32 — ejemplo de fetch POST con
  X-CSRF-TOKEN desde meta[name=csrf-token].
- resources/views/reclutado/show.blade.php:45-58 y 85-110 — patrón de botones x1/x10/x100
  con fetch POST + CSRF + reload (reutilizable como referencia de UI).
- resources/views/components/ui/{button,card,input,modal}.blade.php — componentes UI
  disponibles (variant primary/secondary/danger/success; modal con $store.modales).
- resources/js/stores/comun.js:101-150 — store Alpine 'modales' (pila, foco, escape).
- resources/views/exploraciones/_caramelo.blade.php:2 — fallback de imagen
  candy_pokemon/0.webp.
- .ai/rules/arrays-dto-collections.md — arrays prohibidos como contrato; constantes;
  frontend sin reglas; app/ delgada y src/ reutilizable.
- .ai/rules/frontend-minimal-logic.md — backend dueño de la lógica; frontend mínimo.
- .ai/rules/ddd-modules.md — src puro en Domain, español en src; app/ = infra Laravel.
- .ai/rules/infra-controllers.md — sin try-catch, IDs por URL salvo POST.
- .ai/rules/model-policy.md — modelo fijo opencode-go/deepseek-v4.1-flash.
- Verificación: `php artisan route:list --except-vendor` OK, 109 rutas, no existe /debug
  ni endpoint de entrega de recursos. Tinker read-only: pokemon=1083, users=8,
  reclutados=40, player_inventory=158. No se ha leído .env.

## Dependencias

- Internas: DesbloqueoPokedex (gate QA), ItemCatalogo, TipoEnum, StatEnum, SlugTipo,
  NivelHelper, CrearReclutado, DatosNuevoReclutado, ResolverBonosFamilia,
  GeneradorIvs, CadenasEvolutivas, ActualizarPokedexJob, BelongsToUser.
- Externas: Laravel 12, Livewire 4 + Alpine, Tailwind tokens propios, PostgreSQL/SQLite.
- Recursos estáticos: public/images/candy_type (18/18 tipos), candy_ev (6/6),
  candy_pokemon (647 archivos para 1083 pokémon → fallback 0.webp necesario).

## Duplicidades y deuda técnica

- Tres patrones de entrega de items: firstOrCreate+increment (ReclutamientoController),
  insertOrIgnore+increment (PersistirRecompensas), InventarioGranja::consumir. Un
  servicio único de inventario QA debería reutilizar uno, no crear un cuarto.
- player_inventory no tiene CHECK cantidad >= 0 (unsigned en PG = bigint sin CHECK).
- users.experiencia no tiene CHECK >= 0 ni tope.
- El patrón de UI "botones de cantidad" está repetido en reclutado/show y
  equipos/modal (fetch + CSRF + alert/reload); no hay helper compartido.
- No existe middleware de entorno; el gate se implementa ad hoc en
  DesbloqueoPokedex y DatagridServiceProvider debería seguir el mismo.

## Riesgos

- [alto] Endpoint que otorga experiencia/caramelos sin gate correcto en producción.
  Impacto: economía rota. Mitigación: abort_unless(app()->environment('local','testing'), 404)
  en el controlador y en cada acción POST, además de middleware/ruta; nunca usar APP_DEBUG.
- [alto] Cantidades negativas o desbordamiento: decrement(-N) en users.experiencia o
  cantidad < 0. Mitigación: validar cantidad en lista cerrada, clamp en transacción
  (lockForUpdate + max(0, ...)), rechazar 422 si no hay stock.
- [alto] Reclutamiento directo sin reutilizar CrearReclutado: pierde IVs, EVs, exp 1500,
  dispatch de Pokédex y bonos de familia. Mitigación: reutilizar CrearReclutado +
  DatosNuevoReclutado + ResolverBonosFamilia, igual que recruit.
- [medio] Concurrencia en /debug (doble clic, dos pestañas) → incrementos perdidos o
  dobles. Mitigación: transacción + lockForUpdate de la fila de inventario/usuario, o
  increment atómico para sumas.
- [medio] CSRF ausente si se usa fetch sin token. Mitigación: X-CSRF-TOKEN desde
  meta[name=csrf-token] o formularios @csrf.
- [medio] IDOR si el POST acepta user_id del body. Mitigación: usar auth()->id() siempre.
- [bajo] Pokémons sin cadena (evolution_chain_id null/0): no deben otorgar caramelo de
  familia; devolver 422/omitir (ItemCatalogo ya cae a Desconocido).
- [bajo] Duplicados ilimitados de reclutados vía /pokedex en QA (sin regla existente).
  Aceptable en local/testing; documentarlo.
- [bajo] Imagen de caramelo de familia inexistente (647/1083): usar onerror a
  candy_pokemon/0.webp como en exploraciones.

## Opciones

1. Gate único compartido (recomendado): extender DesbloqueoPokedex o crear
   App\Support\ModoQa::activo(Request) = config('pokedex.unlock_all') ||
   (environment('local','testing') && (request->boolean('unlock') || ruta /debug)).
   /debug se protege con abort_unless(ModoQa::permiteDebug(), 404) y el modal de
   /pokedex muestra acciones cuando $unlockAll ya es true. Menor superficie, un solo
   concepto.
2. Dos flags separados (POKEDEX_UNLOCK_ALL para ver todo; nuevo DEBUG_PANEL para /debug).
   Más explícito pero duplica configuración y permite estados incoherentes.
3. Middleware 'solo-local' alias en bootstrap/app.php + rutas en routes/debug.php.
   Limpio y reutilizable; requiere tocar bootstrap/app.php (hoy vacío) y es el patrón
   Laravel 12 correcto. Combinable con la opción 1.

## Recomendación

Adoptar 3 + 1: middleware de entorno (abort 404 fuera de local/testing) aplicado al
grupo de rutas /debug y a las acciones QA de Pokédex, y un único gate de modo QA
reutilizando el flag existente. Reutilizar CrearReclutado + DatosNuevoReclutado y el
patrón transaccional de ReclutadoController/SendasController para entregar caramelos.
Mantener /debug fuera de $navItems. Crear un servicio App\Support\Qa\AjustesQa (o
src/Shared) con métodos: sumarExperiencia(int userId, int delta), sumarItem(int userId,
string itemKey, int delta) con clamp y transacción; DTO readonly para el resultado.
No sobreingeniería: un controlador, un servicio, una vista, cuatro rutas.

## Suposiciones

- APP_ENV en despliegue no es local/testing (no se ha leído .env).
- Se acepta que /debug solo exista en local/testing y devuelva 404 real.
- "Caramelos de stats" = claves ev:{1..6}; "de tipo" = tipo:{slug}.
- Reclutar desde Pokédex debe comportarse como un reclutamiento normal (IVs, EVs de
  bonos, exp inicial 1500, pokedex RECLUTADO).
- No se requiere auditoría/log persistente de acciones QA.

## Preguntas bloqueantes

- ¿El botón "Reclutar" en /pokedex debe respetar bonos de granja (nido/EV) o crear un
  Reclutado neutral?
- ¿Cuántos caramelos de familia otorga el botón de /pokedex y qué límites?
- ¿Se permite reclutar la misma especie repetidamente?
- ¿/debug debe compartir el mismo flag que el modo QA de Pokédex o activarse solo por
  entorno (local/testing)?

## Inventario de tipos/stats/claves

- TipoEnum: 1 Normal(normal), 2 Lucha(lucha), 3 Volador(volador), 4 Veneno(veneno),
  5 Tierra(tierra), 6 Roca(roca), 7 Bicho(bicho), 8 Fantasma(fantasma),
  9 Acero(acero), 10 Fuego(fuego), 11 Agua(agua), 12 Planta(planta),
  13 Eléctrico(electrico), 14 Psíquico(psiquico), 15 Hielo(hielo), 16 Dragón(dragon),
  17 Siniestro(siniestro), 18 Hada(hada) → clave tipo:{slug}; 18/18 imágenes.
- StatEnum: 1 PS (slug hp, key hp), 2 Ataque (atk/atk), 3 Defensa (def/def),
  4 Ataque Especial (atksp/spAtk), 5 Defensa Especial (defsp/spDef),
  6 Velocidad (spd/speed) → clave ev:{1..6}; 6/6 imágenes candy_ev.
- Familia: familia:{evolution_chain_id}; nombre/imagen = primer miembro por species_id
  e id; fallback candy_pokemon/0.webp.

## Flujo actual de reclutamiento

recruit → validación → transacción → bonos familia → IVs → EVs → CrearReclutado
(exp 1500, es_shiny, ivs/evs) → ActualizarPokedexJob → decremento/borrado del
Reclutable. Reutilizar CrearReclutado, DatosNuevoReclutado, ResolverBonosFamilia,
GeneradorIvs::reclutamiento.

## Propuesta mínima de archivos

- routes/debug.php (backend) con middleware de entorno + auth.
- app/Http/Middleware/SoloEntornoLocal.php o closure (backend).
- app/Http/Controllers/DebugController.php (backend): index + acciones.
- app/Support/Qa/AjustesQa.php (backend/servicio) + DTO readonly ResultadoAjuste.
- app/Http/Controllers/PokedexQaController.php o métodos en PlayerController
  (backend): reclutar + caramelo familia.
- resources/views/debug/index.blade.php (frontend).
- resources/views/pokedex/index.blade.php (frontend): sección QA en modal.
- bootstrap/app.php + routes/web.php (backend): registro.

## Criterios de aceptación verificables

- 404 fuera de local/testing.
- POST con CSRF.
- Experiencia e inventario reflejan delta exacto y nunca < 0.
- Reclutar crea Reclutado con IVs/EVs y pokedex RECLUTADO.
- Caramelo familia suma en familia:{chain}; chain null → 422.
- /debug no aparece en el menú.
- `route:list` no muestra /debug (matiz: la ruta existe pero el middleware debe
  devolver 404; el criterio correcto es 404 en request, no ausencia en route:list).

## Verificación ejecutada

Lectura estática, `git status`/`git diff`, `php artisan route:list --except-vendor`
(OK, 109 rutas), COUNT de solo lectura vía tinker. No se ejecutaron tests.

## Verificación acotada posterior (2026-11-19)

### 1. Ubicación exacta en el modal de Pokédex

`resources/views/pokedex/index.blade.php` (1037 líneas, modificado por TASK-061 en
el working tree).

| Bloque | Líneas |
|---|---|
| `<template x-if="showModal && selectedPokemon">` | 274 |
| Overlay + backdrop | 275-283 |
| Contenedor del modal | 285 |
| Botón cerrar | 287-295 |
| Header | 298-400 |
| Body con scroll | 402 |
| Skeleton / error / "no avistado" | 404-433 |
| Template de detalle | 436 |
| Columna izquierda: familia, habilidades | 440-494 |
| Columna derecha: stats base, especie | 497-552 |
| Bloque movimientos por nivel | 556-603 |
| Cierres | 604-608 |

- **Punto de inserción recomendado:** bloque QA nuevo después de la línea 603 y
  antes del cierre de la línea 604, dentro de `space-y-4` y del template que ya
  garantiza `detail != null`.
- `selectedPokemon` incluye `id`, `name`, `visto`, `atrapado`, `icon`, `types`,
  `habitat`, `habitat_id`; **no** incluye `evolution_chain_id`.
- `detail.family = { evolution_chain_id: int|null, members: [...] }`
  (`PresentadorDetallePokemon.php:207-237`); `detail.species.evolution_chain_id`
  también existe (`PresentadorDetalleEspecie.php:158-175`).
- `pokemon_id` para reclutar = `selectedPokemon.id`; cadena para caramelo de
  familia = `detail.family.evolution_chain_id` (puede ser `null`).
- Métodos Alpine: `openDetail` 961-972, `closeDetail` 974-980, `loadDetail`
  982-1014 (añade `?unlock=1` solo si `unlockAll`), `retryDetail` 1016-1020.
- **Patrón UI recomendado:** `resources/views/equipos/index.blade.php::darEv()`
  1152-1205 y `equipos/partials/modal-detalle-pokemon.blade.php:270-300`:
  `fetch` POST con `X-CSRF-TOKEN`, `Accept: application/json`, feedback inline
  `role="status"`, actualización local del estado sin recargar.
- No existe sistema de toasts; `$store.modales` solo gestiona foco/pila.

### 2. Solapamiento con tareas en curso

| Tarea | Estado | Archivos | Solapa |
|---|---|---|---|
| TASK-061 | `REVIEWING` | `PlayerController.php`, `pokedex/index.blade.php`, `DatagridServiceProvider.php`, `AbilitySeeder.php`, `app/Support/Pokedex/`, combobox, ADR-0005 | **Sí, alto** |
| TASK-062 | `RECEIVED` | `Dockerfile` / `docker/**` | No |
| TASK-063 | `COMPLETED` | `ResolutorAccionBatalla.php`, `EjecutorAccion.php` | No |

Precauciones:
1. Trabajar sobre el working tree; prohibido `checkout --`, `reset`, `stash`.
2. **No modificar `PlayerController.php`**: TASK-061 está en review y `$unlockAll`
   ya llega a la vista.
3. En la vista, editar solo el bloque de detalle (556-604) y añadir estado/métodos
   al final de `pokedexApp()`; no tocar las líneas que TASK-061 modificó.
4. Reanclar por marcadores, no por número de línea si TASK-061 cambia.
5. Implementar después de que TASK-061 salga de `REVIEWING`, o declarar que se
   parte de su estado actual sin revertirlo.

### 3. Firmas y contratos exactos

- `CrearReclutado` (final, sin constructor):
  `public function crear(DatosNuevoReclutado $datos, ?StatsValue $evs = null): Reclutado`
  (línea 20). Fija `exp = new ExpReclutado(total: 1500)` (26),
  `ivs = $datos->ivs ?? GeneradorIvs::aleatorios()` (30),
  `evs = $evs ?? StatsValue::ceros()` (31), y hace
  `ActualizarPokedexJob::dispatch(..., 'RECLUTADO')` (34).
- `DatosNuevoReclutado::__construct(int $userId, int $pokemonId, ?StatsValue $ivs = null, bool $esShiny = false)`.
- `ResolverBonosFamilia`: `paraFamilia(int $userId, int $evolutionChainId): BonosFamilia`
  (27), `evsIniciales(...)` (66), `nidoNivel(...)` (71); `BonosFamilia` expone
  `nido`, `refugio`, `evsFamilia`.
- `GeneradorIvs::reclutamiento(int $nivelNido = 0, ?callable $aleatorio = null): StatsValue` (49).
- Patrón de `recruit`: `ReclutamientoController.php:36-61` (transacción, bonos si
  `evolution_chain_id > 0`, IVs con nivel de nido, EVs de bonos, `crear(...)`).
- `ActualizarPokedexJob` **no implementa `ShouldQueue`** (12-20): `dispatch()` es
  síncrono. `RecompilarHabitatJsonJob` también. El dispatch dentro de transacción
  ya se hace en `recruit`; es seguro pero costoso.
- `User`: `experiencia` fillable (24-29), cast integer (51), `nivel()` (58-61).
  BD `unsignedBigInteger default 0`; en PostgreSQL es `bigint` sin CHECK.
- `PlayerInventory`: tabla `player_inventory`, fillable `user_id,item_key,cantidad`,
  cast integer; unique `(user_id,item_key)`; `cantidad unsignedInteger default 0`
  sin CHECK en PostgreSQL.
- `Reclutado`: fillable y casts; `behavior` default `'COMBATIENTE'`;
  `habilidad_activa_id` nullable.
- `bootstrap/app.php`: solo `web`, `console`, `health`; `withMiddleware` vacío.
  **No existe `app/Http/Middleware/`**. `Middleware::alias()` está disponible.
- Si `/debug` vive dentro de `auth`, un anónimo en producción recibirá 302 antes
  del middleware de entorno. Para 404 siempre, añadir además
  `abort_unless(app()->environment('local','testing'), 404)` en cada método.
- Validación: los controladores App usan `$request->validate([...])`
  (`ReclutamientoController:31-33`, `ReclutadoController:45-49`,
  `SendasController:133-136`). No existe `app/Http/Requests/`; seguir
  `$request->validate()`.
- `TipoEnum`: `label()`, `options()`, `fromId()`, `getNombreEspanol()`; **no tiene
  `slug()`** → usar `Src\Shared\Domain\SlugTipo::de($label)`.
- `StatEnum`: `label()`, `key()`, `slug()`, `options()`, `fromId()`,
  `getNombreEspanol()`, `fromKey()`. Slugs: `hp, atk, def, atksp, defsp, spd`.
- 18/18 imágenes de tipo y 6/6 de EV existen en `public/images/`.

### Correcciones al análisis previo

1. **Experiencia:** botones `±1000`, `±10000`, `±100000`, `±1000000` (texto
   literal). El informe anterior aplicó por error `100/255/3072` a la experiencia;
   esas cantidades son solo para caramelos.
2. `TipoEnum` no expone `slug()`; usar `SlugTipo::de(...)`.
3. No existe `app/Http/Middleware/`; hay que crearlo o usar guardas en controlador.
4. TASK-061 sigue en `REVIEWING`; evitar `PlayerController.php`.
5. `ActualizarPokedexJob` es síncrono; dispatch dentro de transacción seguro pero
   costoso.
6. No hay toasts; usar banner inline `role="status"`.

### Riesgos/decisiones que el plan debe fijar

- **Stock inicial de caramelos de familia en el modal:** el payload del datagrid no
  lo incluye. Opción mínima: el POST devuelve `cantidad` y la UI la muestra tras
  la acción. Si se quiere stock inicial, requiere un `GET` adicional.
- **`evolution_chain_id` nulo/0:** deshabilitar u ocultar el botón de familia y
  devolver 422 en servidor.
- **"Idempotente":** los incrementos no son idempotentes por naturaleza;
  interpretarlo como acotado y validado en servidor.
- **Retirada de inventario:** `lockForUpdate` + `max(0, ...)`; no hay CHECK en BD.
- **Duplicados:** permitidos sin límite (confirmado).

### Lista definitiva de archivos propuesta por el analista

**Nuevos**

| Archivo | Responsabilidad | Agente |
|---|---|---|
| `app/Http/Middleware/SoloEnEntornoDePruebas.php` | `abort(404)` si no es `local`/`testing` | backend/security |
| `routes/debug.php` | `GET /debug`, `POST /debug/experience`, `POST /debug/inventory` | backend |
| `app/Http/Controllers/DebugController.php` | index + ajustar experiencia + ajustar inventario | backend |
| `app/Support/Qa/AjustesQa.php` | transacción + `lockForUpdate`, clamp `>= 0` | backend |
| `app/Support/Qa/FilaAjuste.php` | DTO readonly para la vista | backend |
| `resources/views/debug/index.blade.php` | vista de debug | frontend |
| `app/Http/Controllers/PokedexQaController.php` | reclutar + caramelo de familia | backend/security |
| `app/Support/Qa/ReclutarDesdePokedex.php` | caso de uso de reclutamiento | backend |

**Modificados**

| Archivo | Cambio | Agente |
|---|---|---|
| `bootstrap/app.php` | alias `solo-local` | backend |
| `routes/web.php` | `require routes/debug.php` | backend |
| `resources/views/pokedex/index.blade.php` | bloque QA y métodos Alpine | frontend |

**No modificar:** `app/Http/Controllers/PlayerController.php` ni archivos de TASK-063.

### Verificación de la continuación

Lectura estática, `git status`, `git diff PlayerController.php`,
`php artisan route:list --except-vendor` (109 rutas, sin `/debug`), `grep` de firmas
y de `Middleware::alias` en `vendor/`. Sin tests, sin migraciones, sin leer `.env`.
Archivos modificados: ninguno.
