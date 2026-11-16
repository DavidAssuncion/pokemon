# TASK-048 — Análisis de integración (solo lectura)

## RESUMEN

No existe ningún sistema de eventos ni de población: el único artefacto programado es `exploraciones:procesar` cada 5 minutos (`routes/console.php:13`) y el spawn es 100 % estático, recalculado en cada llamada desde `pokemon_habitat` con peso `capture_rate/hatch`. El evento de 3 días es viable sin cron resolviéndolo on-read con `Europe/Madrid` (`config/app.php:70`).

Tres hallazgos condicionan el diseño:

1. **El antecedente TASK-046 no contiene diseño.** `.opencode/work/TASK-046/architecture.md` son 6 líneas con «No se ha completado el diseño». Lo útil está en `analysis.md` Parte B, que investiga **contadores de población y misiones**, no un evento global ×3 de aparición. No hay contrato cerrado que respetar.
2. **La exploración no pondera por rareza.** `CargadorPoolHabitat` escribe siempre `peso` (= factor de refugio, `1.0` por defecto) y `PokemonDelPool::peso()` da prioridad al override, así que en exploración el pool es **uniforme** salvo refugio. La ruta sí pondera por `capture_rate/hatch`. Aplicar el mismo ×N en ambos produce efectos distintos.
3. **El árbol está sucio y dos archivos clave tienen cambios locales**: `resources/views/layouts/app.blade.php` (navbar reestructurada) y `src/CombateRuta/App/GeneradorEquipoRuta.php` (+37 líneas, `especiesPonderadas`, sin commit). `navbar-acciones.blade.php` está limpio.

## REQUISITOS

- **Funcionales:**
  - Icono `fa-binoculars` en navbar que abre `/avistamientos`, con estado activo.
  - Página que muestre el evento vigente: hábitat, familia, multiplicador, especies afectadas, nivel y tiempo restante.
  - Cada 3 días, un hábitat aleatorio (con pool) y una familia aleatoria de ese hábitat reciben un multiplicador de aparición «considerable».
  - Determinista y estable sin cron ni intervención manual.
- **No funcionales:** timezone `Europe/Madrid`; sin estado acumulativo (idempotente por tick/combate); sin N+1 en rutas calientes (ruta y tick de exploración); no romper cambios locales; sin tests.
- **Criterios de aceptación (derivados):** misma ventana ⇒ mismo hábitat/familia para todos los usuarios; el multiplicador se aplica una sola vez por especie y por cálculo; al caducar, el spawn vuelve al estado previo sin job; la página no expone datos de otros usuarios; el pool vacío o el hábitat 49 nunca se seleccionan.
- **Ambigüedades:** ver *PREGUNTAS BLOQUEANTES*.

## ALCANCE AFECTADO

- **Módulos:**
  - Nuevo `src/Avistamientos/` (Domain ventana/selección, App caso de uso, Infra controlador/repositorio) — alineado con `.ai/rules/ddd-modules.md`.
  - `CombateRuta` (factor en `GeneradorEquipoRuta`).
  - `Exploraciones` (factor en `CargadorPoolHabitat`; opcionalmente preview/estimador).
  - `Habitats` (solo lectura de familias/niveles, sin cambios).
  - `Shared` (posible VO de ventana reutilizable; `Reloj` ya existe).
- **Archivos:**
  - `resources/views/layouts/partials/navbar-acciones.blade.php` (icono).
  - `routes/web.php` + nuevo `routes/avistamientos.php`.
  - `src/CombateRuta/App/GeneradorEquipoRuta.php:199-213`.
  - `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php:44-67`.
  - `app/Providers/AppServiceProvider.php:48-61` (binding del repositorio nuevo).
  - `resources/views/avistamientos/index.blade.php` (nueva).
- **Tablas/contratos:**
  - Lectura: `pokemon`, `pokemon_habitat` (level), `habitats`, `pokedex`.
  - Nueva (si se persiste el evento): `eventos_avistamiento` (window_index único, starts_at, ends_at, habitat_id, evolution_chain_id, factor).
- **Vistas/endpoints:** `GET /avistamientos` dentro de `auth`; navbar; enlace a `/habitats/{id}`.

## EVIDENCIA

**Navbar**
- `resources/views/layouts/app.blade.php:25-28` — `<nav class="relative flex items-center gap-2 h-14" aria-label="Navegación principal">`.
- `resources/views/layouts/app.blade.php:29-66` — contenedor con `min-w-0 flex-1 overflow-x-auto`; `$navItems` en 46-56; activo con `request()->is(trim($route,'/').'*')` (60-62).
- `resources/views/layouts/app.blade.php:68` — `@include('layouts.partials.navbar-acciones')` **fuera** del contenedor con scroll (para que el panel no se recorte).
- `resources/views/layouts/app.blade.php:70-92` — bloque usuario + `Nv` + tema, `shrink-0`.
- `resources/views/layouts/partials/navbar-acciones.blade.php:9-12` — `$claseBoton` (`flex h-9 w-9 ... rounded-full border border-line ... focus-ring`), `$claseInactivo`, flags `request()->is('reclutamiento*')` / `('exploraciones*')`.
- `resources/views/layouts/partials/navbar-acciones.blade.php:15-40` — grupo `relative flex items-center gap-1 shrink-0`; botones con `aria-label`, `title` y clases condicionales.
- `resources/views/layouts/partials/panel-expediciones.blade.php:13-24` — panel absoluto que depende de que el include esté fuera del `overflow-x-auto`.
- `resources/css/app.css:6-7` y CSS compilado — Font Awesome 6 Free; `fa-binoculars` **existe** en el build (`--fa:"\f1e5"`). No requiere plan Pro.

**Rutas/controlador/vista**
- `routes/web.php:18-49` — todo el juego bajo `auth`; patrones `require __DIR__.'/xxx.php'`.
- `routes/habitats.php:9` — `Route::get('/habitats/{id}', ...)` sin `whereNumber`.
- `app/Http/Controllers/HabitatsController.php:49-92` — patrón de página: DTO + `sightedPokemonIds` + `unlockAll`.
- `app/Http/Controllers/HabitatsController.php:74-79` — `Pokedex::whereIn(...)` sin `user_id` explícito (depende del global scope).
- `src/Habitats/Infra/HabitatLecturaRepository.php:78-123` — detalle por niveles desde el pivote (`level` 1-3) e icono `/images/iconos_webp/{id}.webp`.
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php:116-148` — carga de miembros de una cadena (`Pokemon::where('evolution_chain_id', $chainId)`).
- `resources/views/habitats/index.blade.php:33-35` — tarjeta enlazada a `/habitats/{id}`.
- `resources/views/components/sprite.blade.php:21-52` — componente `x-sprite` (id → `/images/iconos_webp/{id}.webp`, fallback `0.webp`).
- `resources/views/components/ui/card.blade.php`, `button.blade.php`, `alert.blade.php` — componentes reutilizables.
- `app/Providers/AppServiceProvider.php:76-89` — `nivelJugador` y `progresoNivel` compartidos con todas las vistas.
- `.ai/rules/ddd-modules.md` — «todo vive en `src/{{Modulo}}/` … controllers incluidos»; `.ai/rules/infra-controllers.md` — sin try-catch, IDs por URL.

**Datos de selección**
- `storage/data/habitats.csv` — 56 hábitats; id 49 = “Legendarios”.
- `storage/data/pokemon_habitat.csv` — 787 filas, 779 pares únicos.
- `database/seeders/PokemonHabitatSeeder.php:19-48` — `ESPECIES_NO_SALVAJES = [132,201,235]`; `upsert` por `(pokemon_id, habitat_id)`.
- `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:15-21` — unique `(pokemon_id, habitat_id)`, `level` 1-3.
- `database/migrations/2026_08_29_000011_add_performance_indexes.php:17-23` — índice `pokemon.evolution_chain_id` y `pokemon_habitat(habitat_id, level)`.
- Agregado propio sobre CSV: 44 hábitats con pool; vacíos = `[12,17,30,50,51,52,53,54,55,56,57,58]`; 7 solo niveles 1/2 (`[1,8,15,16,20,45,47]`); 49 tiene 100 especies / 92 familias; tamaños de familia dentro de hábitat: 51 de 1, 149 de 2, 98 de 3, 5 de 4, 3 de 5, 1 de 9; **101 de 122 pools de nivel (excl. 49) solo tienen familias de 1 especie** y 21 tienen alguna de ≥2.
- `database/migrations/2026_02_24_171456_create_pokemon_table.php:24` — `evolution_chain_id` nullable.
- `database/seeders/PokemonSeeder.php:104-127` — cadenas regionales con bloques 11000-14000; formas no regionales se omiten.

**Ventana / tiempo**
- `config/app.php:70` — `'timezone' => 'Europe/Madrid'`.
- `routes/console.php:13` — único schedule.
- `src/Shared/Domain/Ports/Reloj.php:15-21` + `src/Shared/Domain/Time/RelojPredeterminado.php:17-24` — puerto de tiempo disponible (Carbon, respeta `travelTo`).
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:77` — patrón determinista existente: `crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}")`.

**Multiplicador**
- `src/CombateRuta/App/GeneradorEquipoRuta.php:64-91` — 3 tiradas **con reemplazo** por combate, sin semilla.
- `src/CombateRuta/App/GeneradorEquipoRuta.php:170-190` — pool por `wherePivot('level', $nivel)`, exige stats y tipos.
- `src/CombateRuta/App/GeneradorEquipoRuta.php:199-213` — `ponderar()` construye contratos **sin** `peso` → `PoolHabitat::ponderado()`.
- `src/CombateRuta/App/GeneradorEquipoRuta.php:138-165` — `especiesPonderadas()`: **cambio local sin commit** (no es código base).
- `src/Exploraciones/App/Procesamiento/CargadorPoolHabitat.php:44-67` — único punto con `peso` externo; línea 62 `evolution_chain_id`, línea 63 `'peso' => factorAparicion`.
- `src/Exploraciones/Domain/ValueObjects/PokemonDelPool.php:56-68` — `peso()` devuelve `pesoOverride` si existe (ignora `capture_rate/hatch`); `capture_rate <= 0` excluye.
- `src/Exploraciones/Domain/ValueObjects/PoolHabitat.php:63-83` — `ponderado()` descarta peso ≤ 0.
- `src/Exploraciones/Domain/ValueObjects/ColeccionPoolPonderado.php:55-78` — única selección ponderada.
- `src/Granjas/App/ResolverBonosFamilia.php:83-101` — refugio exclusivo 1.0/0.0; sin refugio → 1.0.
- `src/Granjas/Domain/CalculadoraBonosGranja.php:142-150` — 1.25/1.5/2.0/2.5; insalubres 0.8/0.6667/0.5/0.4.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:79` — `new CargadorPoolHabitat($bonosFamilia)` (no hay binding de contenedor).
- `src/Exploraciones/Domain/Tick/GeneradorEventosTick.php:91,117` — usa `SimuladorEncuentros::generarEventosDesdePool` con el pool cargado.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php:201` y `src/Exploraciones/App/Finalizacion/GeneradorTiradaCapturaExpedicion.php:55` — Senda solo modifica **captura**, no aparición.

**Página / datos**
- `app/Support/CadenasEvolutivas.php:31-49` — `miembrosDe(chainIds)` devuelve todos los miembros por cadena (nombre, species_id).
- `app/Jobs/ActualizarPokedexJob.php:35-53` — `visto` siempre, `atrapado` solo `RECLUTADO`; no degrada.
- `app/Support/DesbloqueoPokedex.php:16-26` — flag QA `unlock`.
- `resources/views/habitats/partials/columna-derecha.blade.php:258-272` — patrón «N pokémon» por nivel + sprite oculto si no avistado.
- `resources/views/habitats/show.blade.php:50,1206-1208` — `sightedPokemonIds` + `isSighted()`.
- `app/Jobs/RecompilarHabitatJsonJob.php:40-55` — escribe `habitats.pokemons`; **no hay lector** de esa columna en `app/`/`src/` (solo `fillable`/cast en `app/Models/Habitat.php:23,30`).
- `app/Http/Controllers/ExploracionActivaController.php:110-128,189-227` — segunda construcción del pool (preview/estimador) **sin** refugio ni evento.

## DEPENDENCIAS

- `PoolHabitat` / `PokemonDelPool` / `ColeccionPoolPonderado` los comparten ruta, entrenador y exploración; cualquier factor nuevo debe entrar por `PokemonDelPool::peso()` (override) o por el contrato de `ponderar()`.
- `GeneradorEquipoRuta` se resuelve por contenedor en `CombateRutaController` (sin `new`), así que admite dependencia nueva opcional.
- `CargadorPoolHabitat` se instancia con `new` en `ProcesarExploracionHandler`; una dependencia nueva exige pasarla ahí o refactorizar.
- `ResolverBonosFamilia` ya establece el patrón «factor por cadena evolutiva» y ya se aplica en exploración.
- `Reloj` disponible; `Auth`/`BelongsToUser` acota `Pokedex`.
- `$nivelJugador` llega a cualquier vista vía composer.

## DUPLICIDADES Y DEUDA TÉCNICA

- **Dos construcciones del pool**: `CargadorPoolHabitat` (con refugio) y `ExploracionActivaController::poolParaEstimador` (sin refugio). El preview ya miente respecto al tick real; el evento heredaría la divergencia.
- **Ruta y exploración ponderan distinto** (rareza vs uniforme) por el override de `peso`.
- **`GeneradorEquipoRuta::especiesPonderadas` no está commiteado**; no debe tratarse como base estable.
- **`habitats.pokemons` es caché muerta** (se escribe, no se lee) y mezcla datos por usuario en una fila global.
- **Sin autorización de administración**: no hay `Gate`, `authorize` ni `abort(403)` en controladores; cualquier usuario autenticado puede asignar/quitar familias y mover Pokémon de nivel (`routes/habitats.php:24-29`), lo que altera el pool del evento global.
- `DungeonProgress` sin scope de usuario (deuda ya documentada en TASK-046), irrelevante aquí pero indica el patrón.
- Formas regionales/huérfanas: 15 filas del CSV podrían no existir en `pokemon` (TASK-046); hoy el CSV sí mapea contra `pokemon.csv` (0 filas huérfanas en mi comprobación de `pokemon_id`), pero las especies regionales usan cadenas sintéticas.

## RIESGOS

- **[alto] Árbol sucio y archivos solapados.** `app.blade.php` y `GeneradorEquipoRuta.php` tienen cambios locales; un `checkout`/`reset` o un parche sobre HEAD los destruye. Impacto: pérdida de trabajo y navbar rota. Mitigación: editar el árbol de trabajo y diff acotado; no tocar `especiesPonderadas` salvo que se use.
- **[alto] Doble aplicación del multiplicador.** Si se aplica a la vez en `CargadorPoolHabitat` (`peso`) y en `PoolHabitat::ponderado()`, o se suma en dos capas (refugio × evento × evento), el factor se eleva al cuadrado. Impacto: spawn descontrolado. Mitigación: una única función `factorEvento(habitat, chain)` invocada una vez por construcción de pool y multiplicada una sola vez.
- **[alto] Cambio del pool a mitad de ventana.** `routes/habitats.php:24-29` permite a cualquier usuario quitar la familia elegida; con selección on-read el evento puede cambiar de familia o quedarse sin efecto sin aviso. Impacto: evento incoherente entre usuarios. Mitigación: persistir la selección por ventana (unique) o congelar candidatos y mostrar aviso «sin efecto».
- **[medio] Hábitat 49 y pools vacíos.** Incluirlos da eventos con 92 familias o sin ninguna especie; 12 hábitats no tienen pool. Mitigación: excluir por `province_id=8` (o lista/config) y validar `count(pool)>0` antes de fijar la ventana.
- **[medio] Familia con una sola especie o nivel de 1 especie.** 51 familias tienen 1 especie en su hábitat y 101 de 122 pools de nivel solo contienen familias de 1 especie; el ×3 puede ser poco perceptible. Impacto: evento invisible. Mitigación: priorizar familias con ≥2 especies en el hábitat o subir el factor; decidir con datos.
- **[medio] Exploración uniforme.** El override de `peso` anula la rareza: aplicar ×N sobre 1.0 da un efecto muy distinto al de ruta. Impacto: expectativas inconsistentes. Mitigación: documentar y decidir si se corrige el override (cambio de balance).
- **[medio] Rendimiento.** El evento se resolvería en cada `generar()` de ruta y en cada tick de exploración (cada 5 min por expedición). Sin caché, son 1-2 consultas extra por llamada. Mitigación: cachear la selección (request/TTL) o persistirla.
- **[medio] Concurrencia al persistir.** Dos primeras lecturas simultáneas pueden insertar dos eventos para la misma ventana. Mitigación: `unique(window_index)` + `insertOrIgnore`/`updateOrCreate`.
- **[medio] Integridad global editable por usuarios.** No hay rol admin; cualquier autenticado altera el dato del que depende un evento global. Impacto: manipulación del evento. Mitigación: fuera de alcance, pero conviene congelar la selección y registrar origen.
- **[bajo] DST.** Ventanas ancladas a medianoche local cruzan cambios de hora (71/73 h reales). Mitigación: calcular en local y mostrar fecha/hora absoluta con offset.
- **[bajo] IDOR.** La página es global y no recibe ids; el riesgo aparece si se añade detalle por hábitat o por usuario. Mitigación: no aceptar `user_id` por URL; filtrar `Pokedex` por `Auth::id()`.
- **[bajo] `evolution_chain_id` nulo o cadena sin miembros.** Mitigación: filtrar `whereNotNull` y validar miembros en hábitat antes de fijar la ventana.
- **[bajo] Caché `habitats.pokemons` obsoleta/mezclada.** No usarla como fuente del evento ni de la página.

## OPCIONES

1. **Selección on-read pura (sin migración).** `windowIndex` desde ancla fija + `crc32` sobre listas ordenadas por id. Mínimo código; riesgo de deriva si cambia el pool. La página y el spawn comparten el mismo cálculo.
2. **Selección persistida por ventana (tabla + unique).** Cálculo determinista en la primera lectura y `upsert`; el resto lee la fila. Estable, auditable, resistente a cambios de pool; añade migración y punto de concurrencia.
3. **Híbrido:** determinista con fallback; si la familia persistida ya no está en el hábitat, se mantiene la ventana y se muestra «sin efecto» en la página.
4. **Factor en el contrato** (`'factor' => ...` en `PokemonDelPool`, multiplicando `peso()`), frente a **inyectar el factor ya multiplicado** en `peso` desde cada llamador. La primera centraliza y evita dobles aplicaciones; la segunda no toca el VO.

## SUPOSICIONES

- «Cada 3 días» = ventanas consecutivas de 72 h ancladas a una fecha fija, no 72 h desde la primera conexión.
- El evento es **global** (mismo hábitat/familia para todos), no por usuario.
- «Familia» = `pokemon.evolution_chain_id`; el nombre visible se deriva del miembro con menor `species_id`.
- El multiplicador afecta a **aparición salvaje** (ruta y exploración), no a captura, recompensas ni probabilidad de shiny.
- La página es de solo lectura: no hay acción de usuario.
- No se corrige el override de rareza de exploración en esta tarea.

## PREGUNTAS BLOQUEANTES

- ¿El evento es global o por usuario? Determina tabla, concurrencia y si la página necesita identidad.
- ¿El multiplicador aplica a **ruta y exploración**, solo a exploración, o también a equipos de entrenador? Cambia los puntos de integración.
- ¿El ×N debe respetar la rareza (`capture_rate/hatch`) o basta multiplicar el pool actual? En exploración hoy es uniforme.
- ¿Se acepta una migración para persistir la ventana o se prefiere cálculo puro?
- ¿El hábitat 49 se excluye por id, por provincia 8 o por regla de «≥2 familias»?
- ¿El factor debe verse en el preview de recompensas (`ExploracionActivaController::preview`) o solo en el spawn real?

## RECOMENDACIÓN

Crear `src/Avistamientos/` con un VO de ventana (ancla fija + índice + `starts_at/ends_at` en `Europe/Madrid`), un selector determinista sobre candidatos **ordenados por id** (44 hábitats con pool menos 49; familias distintas del hábitat) y un único método `factorEvento(habitatId, chainId): float`. Inyectarlo una sola vez por construcción de pool: en `GeneradorEquipoRuta::ponderar()` y en `CargadorPoolHabitat` (multiplicando el `peso` existente, no añadiendo otra capa). Persistir la selección por `window_index` con unique para que un cambio de pool no altere el evento a mitad de ventana; si no se quiere migración, aceptar explícitamente la deriva y mostrar aviso. En navbar, añadir un `<a>` en `navbar-acciones.blade.php` con `fa-binoculars` (icono libre verificado), sin tocar la estructura de `app.blade.php` más allá del include ya existente. La página debe recibir del backend hábitat, familia, especies con nivel e icono, flags de visto y `termina_en`; nada de cálculo en Blade.

**Decisiones para el arquitecto (máx. 6):**

1. Global vs por usuario.
2. Modos afectados: ruta, exploración, entrenador (y coherencia con el preview).
3. Persistir la ventana (migración + unique) vs on-read puro.
4. Semántica del factor: multiplicar el pool actual (exploración uniforme) vs corregir el override para respetar `capture_rate/hatch`.
5. Regla de elegibilidad: exclusión del 49 (id/provincia), mínimo de familias y mínimo de especies de la familia elegida.
6. Ubicación del módulo y del controlador: `src/Avistamientos/Infra/Controllers` (regla DDD vigente) vs `app/Http/Controllers` (patrón legado de páginas).