# Contexto del proyecto

## Resumen

Pokemon Battle Game es un juego de combate por turnos 3v3 con sistema de clima, objetos equipables y habilidades. Implementado como aplicación web Laravel 12 con arquitectura híbrida: `app/` (Laravel estándar) + `src/` (DDD/Hexagonal). El frontend usa Livewire 3 + Alpine.js + Tailwind CSS.

## Estado actual

El proyecto tiene funcionalidad base estable:

- **Batalla 3v3 funcional** — con datos mock. Incluye turnos por velocidad acumulada, posiciones (vanguardia/retaguardia), daño con cadena de responsabilidad (7 manejadores), clima (sequía, diluvio, niebla, granizo, tormenta arena, turbulencias), efectos de habilidad y objetos (Strategy), estados (quemadura, veneno, parálisis, sueño, congelación, confusión), estadísticas con etapas (-6 a +6), STAB, efectividad de tipos (TypeChart 18×18), golpes críticos 6.25%, barreras duales (defensa física/especial), animaciones vía Alpine.js. Ruta `/combate` activa dentro de `auth`. 28 tests de Battle (10 unit + 1 feature).
- **Catálogo de provincias y hábitats** — con seeders, vistas y API.
- **CRUD administrativo** — 13 módulos con vistas Blade (habilidades, evoluciones, tipos, stats, pokémon, provincias, hábitats, reclutados, equipos, exploraciones activas).
- **Reclutamiento y equipos** — captura de pokémon, formación de equipos de 3.
- **Datagrid JSON API** — subsistema de consulta de solo lectura (`app/Datagrid/`) con whitelist por modelo: `GET /datagrid/{model}` y `GET /datagrid/{model}/{id}/detalle`. Usado por la Pokédex y disponible para cualquier modelo registrado (pokemon, pokedex, reclutado, team, habitat, province).
- **Pokédex asíncrona** — pestañas server-side (Vistos/No vistos/Atrapados), filtros de tipo y esfuerzo (EV), búsqueda con debounce, scroll infinito y modal de detalle bajo demanda.
- **Iconos WebP** — 1032 iconos servidos como WebP desde `public/images/iconos_webp/` (generados con cwebp 1.3.2 `-q 80`, alfa preservado, −37 % de peso: 5,7 MiB → 3,6 MiB, 1032/1032 más ligeros; ~97 % menos que los PNG originales de 188 MB). Los PNG de `public/images/iconos/` se conservan como fuente y fallback, pero **no están versionados en este entorno de desarrollo** (solo queda `.htaccess`); los WebP de `iconos_webp/` son la fuente efectivamente servida (cache immutable) y se regeneran con `php artisan iconos:optimize-webp`.
- **Sistema de agentes OpenCode** — 5 agentes orquestados (analista, arquitecto, backend, frontend, bibliotecario).
- **Admin "Gestión" de familias en hábitats** — modal Alpine `habitatShow()` en `habitats/show.blade.php` para asignar familias sin hábitat, quitar familias completas y reordenar pokémon por nivel (1/2/3) vía API JSON. Endpoints: `GET/POST/DELETE /api/habitats/{id}/families`, `PATCH /api/habitats/{habitat}/pokemon/{pokemon}` (nuevo, `movePokemonLevel`) y `GET /api/habitats/unassigned-families`. Contrato aditivo `types[]` en los DTOs de familia.   Sin refresco pesado: la query inicial solo al abrir el modal; mutaciones locales tras 200 OK. Sustituye al modal Livewire (`FamilyModal` eliminado).
- **Caramelos con imagen en exploraciones y "primer integrante" = menor `species_id`** — en `/exploraciones` todos los caramelos muestran su asset (`candy_pokemon/{id}.webp`, `candy_ev/{slug}.webp`, `candy_type/{slug}.webp`) con fallback único al placeholder `candy_pokemon/0.webp`. Regla de negocio confirmada por el cliente: el primer integrante de una familia evolutiva es el de menor `species_id` (no el base evolutivo/bebé); afecta a la tarjeta "base" del Admin Gestión de hábitats, al orden de las familias y a `caramelos_familia[].pokemon_id` de exploraciones. El reparto de niveles por BFS evolutivo NO cambió (detalle y quirk en `docs/architecture.md`).
- **Eliminada la tabla `evolution_chains` (bug 23503)** — las familias evolutivas se agrupan por la columna `pokemon.evolution_chain_id` (entero, sin FK ni tabla): `FinalizarExploracionHandler::cargarMiembrosDeCadenas()` y `ReclutamientoController::miembrosDeLasCadenas()` cargan el mapa `chainId => Collection<Pokemon>` que consumen `NormalizadorPokemonDerrotado::fase()` (fase = miembros del mapa con species_id ≤ actual; sin cadena → fase 1) y `TransformadorResultadoExploracion::pokemonBaseDeCadena()` (min species_id del mapa). Solución con 2 migraciones nuevas (drop FK + drop tabla, down reversibles), modelo `EvolutionChain` eliminado y relaciones quitadas de `Pokemon`/`Caramelo`. `caramelos.evolution_chain_id` conserva columna + unique sin FK. Desvío documentado: `ReclutamientoController` sí usaba la relación (eager load + fase) y fue corregido con la columna; bonus: cadenas huérfanas → fase 1 (antes Error fatal). Módulos afectados: Exploraciones, Habitats (mapa ya existente), Reclutamiento.
- **Módulo de combate corregido y refactorizado (2026-08-30)** — 6 bugs de runtime que accedían a propiedades privadas de `PokemonEntity` (`$moves`, `$tiposCollection`) en lugar de sus getters (`moves()`, `tiposCollection()`): 2 en `AgregadoBatalla`, 4 en `Combate`. Ruta `/combate` reactivada (estaba comentada) dentro de `auth`, enlace "Combate" en el nav, weather banner corregido (comparaba valores ingleses con el enum español `TipoClima`). Refactor Cleaner: eliminados `BattleAggregate` (@deprecated, duplicado de `AgregadoBatalla`), `EfectoInvocadorTormentaArena` (reemplazado por `EfectoInvocadorClima` genérico con `TipoClima::TORMENTA_ARENA`), `src/Battle/App/` (casos de uso deprecados `IniciarBatalla`/`BattleSrv`); `FabricaEfectos` pasó de estática a instancia inyectable (singleton en `BattleEffectServiceProvider`, inyectada en `EquipoBatalla::fromData()` y `FabricaBatallaMock`); `DTOAccionBatalla::$move` tipado como `DTOMovimientoBatalla` (antes `array`). Tests: 28 de Battle verdes (10 unit + 1 feature, 48 assertions); suite completa 421 passed / 1 failed pre-existente (`ServicioCapturaTest` ajeno). QA PASS y Arquitecto APROBADO con deuda documentada (ver Pendientes conocidos y `src/Battle/context.md`).

## Decisiones arquitectónicas clave

1. **Híbrido app/ + src/**: Separación clara entre infraestructura Laravel (`app/`) y dominio (`src/`). `app/` contiene **hoy** (estado actual, aún sin migrar) Livewire, Controllers, Models Eloquent, Providers — no es el destino de esos componentes. `src/` contiene la lógica de dominio pura (DDD). (Matizado por la decisión 10: desde 2026-08-30 la convención es acoplamiento ordenado — Domain puro y `App`/`Infra` con Laravel dentro de `src/{{Modulo}}/`.)
2. **Chain of Responsibility** para cálculo de daño: `CadenaDanio` compone 7 manejadores (base, tipo, STAB, crítico, posición, clima, orbe vida). Fácil de extender con nuevos modificadores.
3. **Observer** para eventos de batalla: `SujetoBatalla` notifica daño recibido, daño infligido, debilitamiento y fin de turno. Los efectos se suscriben.
4. **Efectos como Strategy**: `InterfazEfecto` con implementaciones para habilidades (perforación armadura, regeneración defensa, invocador clima) y objetos (restos, orbe vida). Registrados via `FabricaEfectos` en `BattleEffectServiceProvider`.
5. **Serialización en sesión con versionado**: Las batallas se guardan en sesión con prefijo `v{version}|`. La versión permite migrar datos serializados entre cambios estructurales.
6. **Batalla automática vs manual**: `AgregadoBatalla::ejecutarBatalla()` ejecuta batallas completas (IA vs IA) sin intervención. `App\Livewire\Combate` es el componente Livewire para batalla manual por turnos (jugador vs IA, con `pendingAction` + animación Alpine 700ms). Ambas comparten `ServicioEjecucionBatalla` para la lógica de daño/estado. `BattleAggregate` fue eliminado (era duplicado deprecado de `AgregadoBatalla`).
7. **Datagrid como API JSON de solo lectura con whitelist**: `app/Datagrid/` expone modelos vía `DatagridDefinition` (campos searchable/filterable/sortable/visible declarados por modelo). Parámetros no whitelisted se ignoran silenciosamente; modelo no registrado → 404 (nunca se revela la clase). `per_page` con clamp 1-200 (default 100). Registro centralizado en `DatagridServiceProvider` (composition root).
8. **Iconos WebP en carpeta propia**: los iconos se sirven como WebP desde `public/images/iconos_webp/` (con `Cache-Control: public, max-age=31536000, immutable` en Apache vía `.htaccess`); los PNG originales quedan en `public/images/iconos/` como fuente y fallback. Generados con `php artisan iconos:optimize-webp --dir --out` (idempotente, escribe solo en `--out`, no toca subdirectorios, guard realpath dir≠out). El contrato `icon` es `/images/iconos_webp/{id}.webp` (datagrid y `HabitatRepository`).
9. **Pokédex asíncrona con decisiones de contrato**: los booleans del leftJoin tratan NULL ≡ false (`filter[visto]=0`/`filter[atrapado]=0` incluyen `orWhereNull`); `meta.counts` son globales (independientes de filtros/paginación) para el header; el fallback webp→png→ocultar existe SOLO en la Pokédex (única vista con red); los no vistos no descargan imagen (placeholder CSS) ni datos de detalle (sin fetch).
10. **Convención DDD por módulos (`docs/ddd.md`, adoptada 2026-08-30)**: acoplamiento a Laravel aceptado y ordenado — capa `Domain` pura (sin Eloquent/HTTP/facades/`Request`) y `App`/`Infra` con Laravel libre, todo en `src/{{Modulo}}/` (controllers, modelos Eloquent, FormRequests, rutas y repos incluidos en el módulo). DTOs de presentación → `Domain/DataTransferObjects` (la capa `Presentation/` desaparece como destino). Excepciones de dominio genéricas (`DominioExcepcion` 400, `RecursoNoExiste` 404, `ViolacionReglaNegocio` 422, `PermisoDenegado` 403) + mapa HTTP en `bootstrap/app.php` (específicas primero). Contrato de repositorio en español: `obtenerPorId` no-null, `insertar` (DTO→entidad con id), `insertarColeccion` (sin ids), `actualizar` (id exigido), `upsertColeccion` (id exigido), `eliminar` (recupera→404→borra); sin `getCollection` genérico (listados = Datagrid). IDs por URL, nunca por body salvo POST. Rutas por módulo importadas desde `routes/web.php`. La migración del código existente es gradual por módulo (estrategia strangler).

## Módulos existentes

Ver `docs/architecture.md` para la descripción detallada de cada módulo.

## Sistema de combate

- **3v3 por turnos**: Cada equipo tiene 3 pokémon en posiciones de vanguardia (1-2) y retaguardia (1). Agregado raíz `AgregadoBatalla` (manual y automática comparten `ServicioEjecucionBatalla`).
- **Velocidad acumulada**: Cada ronda se acumula `velocidad` al contador. El que más velocidad acumulada tiene actúa primero (`GestorTurnos::getNextActor`).
- **Daño**: Fórmula base estilo Pokémon (nivel 50) × efectividad × STAB × crítico × posición × clima × objetos, vía `CadenaDanio` (7 manejadores).
- **Clima**: 7 valores del enum `TipoClima` (none, sequía, diluvio, niebla, granizo, tormenta arena, turbulencias) que modifican daño en ±25%. Invocado por `EfectoInvocadorClima` (parametrizado por clima) en battle start.
- **Estados**: Quemadura, veneno, parálisis, sueño, congelación, confusión (`EstadoPokemon`, 8 valores). Afectan capacidad de actuar y causan daño por ronda.
- **Objetos**: Life Orb (×1.3 + recoil 10%), Leftovers (1/16 HP/ronda). Registrados como efectos (Strategy) en `FabricaEfectos` (instancia inyectable, singleton en `BattleEffectServiceProvider`).
- **Habilidades**: Perforación de armadura (10% directo), regeneración de defensa (10% barrera/ronda), invocadores de clima (tormenta arena, sequía, diluvio, etc.).
- **Barreras duales**: cada combatiente tiene barreras de defensa física y especial que absorben daño antes que el HP (mecánica propia del juego, no estándar Pokémon). La perforación de armadura envía un % directo al HP ignorando barreras.
- **Persistencia en sesión**: batallas serializadas en sesión con prefijo `v{version}|` (`SESSION_VERSION=3` en `Combate.php`).

## Base de datos

PostgreSQL en el entorno de ejecución (Docker); los tests usan SQLite en memoria (`:memory:`).
31 migraciones. Esquema principal:

- `provinces` → `habitats` → `pokemon_habitat` ↔ `pokemon`
- `pokemon` → `pokemon_stats`, `pokemon_types`, `pokemon_evolution`
- `abilities`
- Familias evolutivas agrupadas por la columna `pokemon.evolution_chain_id` (sin tabla;
  `evolution_chains` eliminada en bug 23503; `caramelos.evolution_chain_id` conserva
  columna + unique, sin FK)
- `pokedex` (avistamientos/capturas por pokémon, 1:1 con `pokemon`)
- `reclutados` (pokémon capturados por el jugador)
- `teams` → `team_members` (equipos de 3)
- `exploraciones_activas` (misiones de exploración)

Seeders: provincias (8), hábitats, pokémon (151+), reclutados.

## Frontend

- **Livewire 3** para componentes interactivos (Combate).
- **Alpine.js** para animaciones de batalla (delay de 700ms entre acciones) y para la Pokédex asíncrona (`pokedexApp()` inline en `pokedex/index.blade.php`: pestañas, filtros, scroll infinito, modal).
- **Tailwind CSS 4** con Vite.
- **Vistas**: `habitats/index`, `habitats/show`, `combate` (con partials: turn-bar, battle-field, moves-panel, battle-log), `crud/dashboard` + 13 submódulos, `reclutados/index`, `pokedex/index` (asíncrona con datagrid).

## Decisiones y convenciones

- Código en español para nombres de dominio (movimientos, tipos, estados) y lógica de negocio.
- Nombres de tablas y columnas en inglés (convención Laravel).
- `MovimientoBatalla` usa el patrón DatosPokemonBatalla para desacoplar datos de combate de la entidad pokémon persistida.
- Los datos de batalla son mock (FabricaBatallaMock) hasta que se integre con datos reales de reclutamiento.
- Los iconos de pokémon se sirven como WebP desde `public/images/iconos_webp/` (fuente PNG en `public/images/iconos/`). Ver `docs/conventions.md`.

## Pendientes conocidos

1. Integrar datos reales de pokémon reclutados en lugar de datos mock en batalla.
2. Cache-busting de iconos: los WebP se sirven con `Cache-Control: immutable`; si un icono cambia con el mismo id, los clientes no lo refrescarán (pendiente de decisión/ticket).
3. `infection.json5` solo cubre `src/`; el código de `app/` (Datagrid, WebP, comandos) no genera mutaciones.
4. `phpstan.neon` líneas 26-27: baseline de errores tolerados preexistentes (categoría `staticMethod.dynamicCall` de asserts en tests y otros); ampliar limpieza gradualmente.
5. `HabitatRepository` vive en `src/Habitats/Infra/` pero recibe datos de `app/` (backlog de arquitectura: mover a infraestructura Laravel o definir interfaz en Domain). Es además una god-class de ~477 líneas pendiente de dividir (asignación de familias, niveles, tipos y detalle conviven en el mismo repositorio).
6. Tests: existen pocos tests, la cobertura es baja (aunque Datagrid/WebP/Pokédex ya tienen cobertura de feature/unit).
7. Manejar migración de sesiones de batalla cuando cambia la estructura serializada.
8. El módulo Pokemon (`src/Pokemon/Domain`) tiene entidades pero los App/ use cases están vacíos.
9. `MovimientoBatalla` vive en `src/Battle/Domain/` (no hay carpeta `src/Battle/Movement/`; los movimientos se definen inline en mock). El Cleaner eliminó `src/Battle/App/` (casos de uso deprecados `IniciarBatalla`/`BattleSrv`) y `BattleAggregate`; el módulo queda con `Domain/` (incl. Chain/Effects/Enums/Observer/ValueObjects), `Infrastructure/` y `Presentation/`. Deuda relacionada: mover `MovimientoBatalla` a `src/Pokemon/Domain/Movement/` (ver pendiente 12).
10. El módulo Crud tiene ServiceProviders pero los submódulos `src/Crud/Pokemon/Domain/` e `Infra/` están vacíos.
11. `OptimizeIconsToWebpTest` eliminado (decisión de cierre): los PNG fuente de 188 MB no están versionados en el entorno de desarrollo, por lo que el test no podía ejecutarse (fallaba siempre por fuente inexistente). `php artisan iconos:optimize-webp` y `app/Support/WebpConverter.php` se mantienen y siguen siendo la herramienta de generación; la cobertura del conversor queda en `tests/Unit/WebpConverterTest.php`.
12. **Deuda de arquitectura del módulo Battle (Arquitecto APROBADO, 2026-08-30)** — ver detalle en `src/Battle/context.md`:
    - Ciclo de dependencia Pokemon↔Battle: `MovimientoBatalla` (en `src/Battle/Domain`) debería moverse a `src/Pokemon/Domain/Movement/`, y `PokemonEntity` importa `MovimientoBatalla`/`ColeccionMovimientos` de Battle.
    - `DTOEquipoBatalla` (`src/Battle/Presentation/`) no se usa → eliminar.
    - `AgregadoBatalla` importa `DTOAccionBatalla` (Presentation) → inversión de dependencia Domain→Presentation.
    - 26 strings mágicos de stats (ej: `'attack'`, `'speed'`) sin enum tipado.
    - `max(1, floor(...))` en `CadenaDanio::calculate()` anula la inmunidad de tipos (×0 → mínimo 1).
    - `StatsValue` con propiedades `?float` nullable (deuda preexistente).
    - `Combate.php` es god-component (653 líneas); `Combatiente`/`AgregadoBatalla` son god-classes (606 y 327 líneas).
    - Cobertura Infection de `src/Battle` aún baja (MSI 42.33%).
13. **Refactor gradual de módulos a la convención DDD (`docs/ddd.md`)** — los módulos actuales
    (Battle, Pokemon, Habitats, Equipos, Reclutamiento, Exploraciones, Crud) usan aún
    `Presentation/`, métodos en inglés en alguna interfaz, entidades sin `toArray` uniforme,
    controllers en `app/Http/Controllers`, modelos Eloquent en `app/Models`, Livewire en
    `app/Livewire` y rutas en `routes/`. Se migran **por módulo** cuando se toque (estrategia
    strangler: `Presentation/` → DTOs en Domain, métodos de repositorio al español,
    controllers/modelos/Livewire/rutas por módulo); no hay refactor masivo inmediato.

## Referencias

- `docs/workflow.md` — Flujo de desarrollo con agentes
- `docs/architecture.md` — Descripción arquitectónica detallada
- `docs/conventions.md` — Convenciones del proyecto
- `docs/ddd.md` — Convención canónica de arquitectura DDD por módulos (Domain/App/Infra, DTOs de presentación en Domain, excepciones de dominio + mapa HTTP, contrato de repositorio en español, rutas por módulo, migración gradual)
- `app/Datagrid/` — Subsistema de consulta JSON con whitelist (4 clases)
- `app/Providers/DatagridServiceProvider.php` — Composition root del datagrid (registro de 6 modelos)
- `app/Console/Commands/OptimizeIconsToWebp.php` — `iconos:optimize-webp`
- `app/Support/WebpConverter.php` — Conversión PNG→WebP (GD/Imagick/CLI)
- `resources/views/pokedex/index.blade.php` — Pokédex asíncrona (componente Alpine `pokedexApp()`)
- `public/images/iconos_webp/` — 1032 iconos WebP servidos con cache immutable
- `app/Livewire/Combate.php` — Componente de batalla manual (653 líneas)
- `resources/views/habitats/show.blade.php` — Detalle de hábitat + modal "Admin - Gestión" (Alpine `habitatShow()`)
- `tests/Feature/Habitats/FamiliesTest.php` — 21 tests de la API de familias (GET/POST/DELETE/PATCH)
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` — Transformador de resultados de exploraciones (`caramelos_familia[].pokemon_id` = min species_id)
- `app/Http/Controllers/ExploracionActivaController.php` — Controlador de exploraciones; const `STATS` unificada (nombres + slugs de estadísticas)
- `tests/Feature/ExploracionesTransformadorTest.php` — Tests del transformador (pokemon_id min species_id / null)
- `public/images/candy_pokemon/`, `public/images/candy_ev/`, `public/images/candy_type/` — Assets de caramelos (fallback único `candy_pokemon/0.webp`)
- `src/Battle/Domain/` — Lógica de combate (11 archivos directos + subdirectorios: Chain 10, Effects 9, Enums 3, Observer 2, ValueObjects 2)
- `src/Battle/Domain/Chain/` — Cadena de daño (7 manejadores + base/interface)
- `src/Battle/Domain/Effects/` — Sistema de efectos (9 archivos, Strategy Pattern)
- `opencode.jsonc` — Configuración de agentes OpenCode
