# Contexto del proyecto

## Resumen

Pokemon Battle Game es un juego de combate por turnos 3v3 con sistema de clima, objetos equipables y habilidades. Implementado como aplicación web Laravel 12 con arquitectura híbrida: `app/` (Laravel estándar) + `src/` (DDD/Hexagonal). El frontend usa Livewire 3 + Alpine.js + Tailwind CSS.

## Estado actual

El proyecto tiene funcionalidad base estable:

- **Batalla 3v3 funcional** — con datos mock. Incluye turnos por velocidad acumulada, posiciones (vanguardia/retaguardia), daño con cadena de responsabilidad, clima (sequía, diluvio, niebla, granizo, tormenta arena, turbulencias), efectos de habilidad y objetos, estados (quemadura, veneno, parálisis, sueño, congelación, confusión), estadísticas con etapas (-6 a +6), STAB, efectividad de tipos, golpes críticos, animaciones vía Alpine.js.
- **Catálogo de provincias y hábitats** — con seeders, vistas y API.
- **CRUD administrativo** — 13 módulos con vistas Blade (habilidades, evoluciones, tipos, stats, pokémon, provincias, hábitats, reclutados, equipos, exploraciones activas).
- **Reclutamiento y equipos** — captura de pokémon, formación de equipos de 3.
- **Datagrid JSON API** — subsistema de consulta de solo lectura (`app/Datagrid/`) con whitelist por modelo: `GET /datagrid/{model}` y `GET /datagrid/{model}/{id}/detalle`. Usado por la Pokédex y disponible para cualquier modelo registrado (pokemon, pokedex, reclutado, team, habitat, province).
- **Pokédex asíncrona** — pestañas server-side (Vistos/No vistos/Atrapados), filtros de tipo y esfuerzo (EV), búsqueda con debounce, scroll infinito y modal de detalle bajo demanda.
- **Iconos WebP** — 1032 iconos servidos como WebP desde `public/images/iconos_webp/` (generados con cwebp 1.3.2 `-q 80`, alfa preservado, −37 % de peso: 5,7 MiB → 3,6 MiB, 1032/1032 más ligeros; ~97 % menos que los PNG originales de 188 MB). Los PNG de `public/images/iconos/` se conservan como fuente y fallback.
- **Sistema de agentes OpenCode** — 5 agentes orquestados (analista, arquitecto, backend, frontend, bibliotecario).
- **Admin "Gestión" de familias en hábitats** — modal Alpine `habitatShow()` en `habitats/show.blade.php` para asignar familias sin hábitat, quitar familias completas y reordenar pokémon por nivel (1/2/3) vía API JSON. Endpoints: `GET/POST/DELETE /api/habitats/{id}/families`, `PATCH /api/habitats/{habitat}/pokemon/{pokemon}` (nuevo, `movePokemonLevel`) y `GET /api/habitats/unassigned-families`. Contrato aditivo `types[]` en los DTOs de familia. Sin refresco pesado: la query inicial solo al abrir el modal; mutaciones locales tras 200 OK. Sustituye al modal Livewire (`FamilyModal` eliminado).

## Decisiones arquitectónicas clave

1. **Híbrido app/ + src/**: Separación clara entre infraestructura Laravel (`app/`) y dominio (`src/`). `app/` contiene Livewire, Controllers, Models Eloquent, Providers. `src/` contiene la lógica de dominio pura (DDD) sin dependencias del framework.
2. **Chain of Responsibility** para cálculo de daño: `CadenaDanio` compone 7 manejadores (base, tipo, STAB, crítico, posición, clima, orbe vida). Fácil de extender con nuevos modificadores.
3. **Observer** para eventos de batalla: `SujetoBatalla` notifica daño recibido, daño infligido, debilitamiento y fin de turno. Los efectos se suscriben.
4. **Efectos como Strategy**: `InterfazEfecto` con implementaciones para habilidades (perforación armadura, regeneración defensa, invocador clima) y objetos (restos, orbe vida). Registrados via `FabricaEfectos` en `BattleEffectServiceProvider`.
5. **Serialización en sesión con versionado**: Las batallas se guardan en sesión con prefijo `v{version}|`. La versión permite migrar datos serializados entre cambios estructurales.
6. **Batalla automática vs manual**: `BattleAggregate` ejecuta batallas completas sin intervención. `App\Livewire\Combate` es el componente Livewire para batalla manual por turnos. Comparten `ServicioEjecucionBatalla` para lógica de daño.
7. **Datagrid como API JSON de solo lectura con whitelist**: `app/Datagrid/` expone modelos vía `DatagridDefinition` (campos searchable/filterable/sortable/visible declarados por modelo). Parámetros no whitelisted se ignoran silenciosamente; modelo no registrado → 404 (nunca se revela la clase). `per_page` con clamp 1-200 (default 100). Registro centralizado en `DatagridServiceProvider` (composition root).
8. **Iconos WebP en carpeta propia**: los iconos se sirven como WebP desde `public/images/iconos_webp/` (con `Cache-Control: public, max-age=31536000, immutable` en Apache vía `.htaccess`); los PNG originales quedan en `public/images/iconos/` como fuente y fallback. Generados con `php artisan iconos:optimize-webp --dir --out` (idempotente, escribe solo en `--out`, no toca subdirectorios, guard realpath dir≠out). El contrato `icon` es `/images/iconos_webp/{id}.webp` (datagrid y `HabitatRepository`).
9. **Pokédex asíncrona con decisiones de contrato**: los booleans del leftJoin tratan NULL ≡ false (`filter[visto]=0`/`filter[atrapado]=0` incluyen `orWhereNull`); `meta.counts` son globales (independientes de filtros/paginación) para el header; el fallback webp→png→ocultar existe SOLO en la Pokédex (única vista con red); los no vistos no descargan imagen (placeholder CSS) ni datos de detalle (sin fetch).

## Módulos existentes

Ver `docs/architecture.md` para la descripción detallada de cada módulo.

## Sistema de combate

- **3v3 por turnos**: Cada equipo tiene 3 pokémon en posiciones de vanguardia (1-2) y retaguardia (1).
- **Velocidad acumulada**: Cada ronda se acumula `velocidad` al contador. El que más velocidad acumulada tiene actúa primero.
- **Daño**: Fórmula base estilo Pokémon (nivel 50) × efectividad × STAB × crítico × posición × clima × objetos.
- **Clima**: 6 tipos (sequía, diluvio, niebla, granizo, tormenta arena, turbulencias) que modifican daño en ±25%.
- **Estados**: Quemadura, veneno, parálisis, sueño, congelación, confusión. Afectan capacidad de actuar y causan daño por ronda.
- **Objetos**: Life Orb, Leftovers, Focus Sash. Registrados como efectos.
- **Habilidades**: Perforación de armadura, regeneración de defensa, invocador de clima (tormenta arena, sequía, diluvio, etc.).

## Base de datos

PostgreSQL en el entorno de ejecución (Docker); los tests usan SQLite en memoria (`:memory:`).
29 migraciones. Esquema principal:

- `provinces` → `habitats` → `pokemon_habitat` ↔ `pokemon`
- `pokemon` → `pokemon_stats`, `pokemon_types`, `pokemon_evolution`
- `abilities`, `evolution_chains`
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
9. `src/Battle/Movement/` está vacío (movimientos se definen inline en mock).
10. El módulo Crud tiene ServiceProviders pero los submódulos `src/Crud/Pokemon/Domain/` e `Infra/` están vacíos.

## Referencias

- `docs/workflow.md` — Flujo de desarrollo con agentes
- `docs/architecture.md` — Descripción arquitectónica detallada
- `docs/conventions.md` — Convenciones del proyecto
- `app/Datagrid/` — Subsistema de consulta JSON con whitelist (4 clases)
- `app/Providers/DatagridServiceProvider.php` — Composition root del datagrid (registro de 6 modelos)
- `app/Console/Commands/OptimizeIconsToWebp.php` — `iconos:optimize-webp`
- `app/Support/WebpConverter.php` — Conversión PNG→WebP (GD/Imagick/CLI)
- `resources/views/pokedex/index.blade.php` — Pokédex asíncrona (componente Alpine `pokedexApp()`)
- `public/images/iconos_webp/` — 1032 iconos WebP servidos con cache immutable
- `app/Livewire/Combate.php` — Componente de batalla manual (686 líneas)
- `resources/views/habitats/show.blade.php` — Detalle de hábitat + modal "Admin - Gestión" (Alpine `habitatShow()`)
- `tests/Feature/Habitats/FamiliesTest.php` — 21 tests de la API de familias (GET/POST/DELETE/PATCH)
- `src/Battle/Domain/` — Lógica de combate (14 archivos)
- `src/Battle/Domain/Chain/` — Cadena de daño (10 manejadores)
- `src/Battle/Domain/Effects/` — Sistema de efectos (10 archivos)
- `opencode.jsonc` — Configuración de agentes OpenCode
