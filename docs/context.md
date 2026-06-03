# Contexto del proyecto

## Resumen

Pokemon Battle Game es un juego de combate por turnos 3v3 con sistema de clima, objetos equipables y habilidades. Implementado como aplicación web Laravel 12 con arquitectura híbrida: `app/` (Laravel estándar) + `src/` (DDD/Hexagonal). El frontend usa Livewire 3 + Alpine.js + Tailwind CSS.

## Estado actual

El proyecto tiene funcionalidad base estable:

- **Batalla 3v3 funcional** — con datos mock. Incluye turnos por velocidad acumulada, posiciones (vanguardia/retaguardia), daño con cadena de responsabilidad, clima (sequía, diluvio, niebla, granizo, tormenta arena, turbulencias), efectos de habilidad y objetos, estados (quemadura, veneno, parálisis, sueño, congelación, confusión), estadísticas con etapas (-6 a +6), STAB, efectividad de tipos, golpes críticos, animaciones vía Alpine.js.
- **Catálogo de provincias y hábitats** — con seeders, vistas y API.
- **CRUD administrativo** — 13 módulos con vistas Blade (habilidades, evoluciones, tipos, stats, pokémon, provincias, hábitats, reclutados, equipos, exploraciones activas).
- **Reclutamiento y equipos** — captura de pokémon, formación de equipos de 3.
- **Sistema de agentes OpenCode** — 5 agentes orquestados (analista, arquitecto, backend, frontend, bibliotecario).

## Decisiones arquitectónicas clave

1. **Híbrido app/ + src/**: Separación clara entre infraestructura Laravel (`app/`) y dominio (`src/`). `app/` contiene Livewire, Controllers, Models Eloquent, Providers. `src/` contiene la lógica de dominio pura (DDD) sin dependencias del framework.
2. **Chain of Responsibility** para cálculo de daño: `CadenaDanio` compone 7 manejadores (base, tipo, STAB, crítico, posición, clima, orbe vida). Fácil de extender con nuevos modificadores.
3. **Observer** para eventos de batalla: `SujetoBatalla` notifica daño recibido, daño infligido, debilitamiento y fin de turno. Los efectos se suscriben.
4. **Efectos como Strategy**: `InterfazEfecto` con implementaciones para habilidades (perforación armadura, regeneración defensa, invocador clima) y objetos (restos, orbe vida). Registrados via `FabricaEfectos` en `BattleEffectServiceProvider`.
5. **Serialización en sesión con versionado**: Las batallas se guardan en sesión con prefijo `v{version}|`. La versión permite migrar datos serializados entre cambios estructurales.
6. **Batalla automática vs manual**: `BattleAggregate` ejecuta batallas completas sin intervención. `App\Livewire\Combate` es el componente Livewire para batalla manual por turnos. Comparten `ServicioEjecucionBatalla` para lógica de daño.

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

SQLite con 18 migraciones. Esquema principal:

- `provinces` → `habitats` → `pokemon_habitat` ↔ `pokemon`
- `pokemon` → `pokemon_stats`, `pokemon_types`, `pokemon_evolution`
- `abilities`, `evolution_chains`
- `reclutados` (pokémon capturados por el jugador)
- `teams` → `team_members` (equipos de 3)
- `exploraciones_activas` (misiones de exploración)

Seeders: provincias (8), hábitats, pokémon (151+), reclutados.

## Frontend

- **Livewire 3** para componentes interactivos (Combate).
- **Alpine.js** para animaciones de batalla (delay de 700ms entre acciones).
- **Tailwind CSS 4** con Vite.
- **Vistas**: `habitats/index`, `habitats/show`, `combate` (con partials: turn-bar, battle-field, moves-panel, battle-log), `crud/dashboard` + 13 submódulos, `reclutados/index`.

## Decisiones y convenciones

- Código en español para nombres de dominio (movimientos, tipos, estados) y lógica de negocio.
- Nombres de tablas y columnas en inglés (convención Laravel).
- `MovimientoBatalla` usa el patrón DatosPokemonBatalla para desacoplar datos de combate de la entidad pokémon persistida.
- Los datos de batalla son mock (FabricaBatallaMock) hasta que se integre con datos reales de reclutamiento.
- Los iconos de pokémon están en `resources/iconos/` (formato PNG).

## Pendientes conocidos

1. Integrar datos reales de pokémon reclutados en lugar de datos mock en batalla.
2. Añadir iconos de pokémon faltantes (muchos aún no existen como PNG).
3. Manejar migración de sesiones de batalla cuando cambia la estructura serializada.
4. Tests: existen pocos tests, la cobertura es baja.
5. El módulo Pokemon (`src/Pokemon/Domain`) tiene entidades pero los App/ use cases están vacíos.
6. `src/Battle/Movement/` está vacío (movimientos se definen inline en mock).
7. El módulo Crud tiene ServiceProviders pero los submódulos `src/Crud/Pokemon/Domain/` e `Infra/` están vacíos.

## Referencias

- `docs/workflow.md` — Flujo de desarrollo con agentes
- `docs/architecture.md` — Descripción arquitectónica detallada
- `docs/conventions.md` — Convenciones del proyecto
- `app/Livewire/Combate.php` — Componente de batalla manual (686 líneas)
- `src/Battle/Domain/` — Lógica de combate (14 archivos)
- `src/Battle/Domain/Chain/` — Cadena de daño (10 manejadores)
- `src/Battle/Domain/Effects/` — Sistema de efectos (10 archivos)
- `opencode.jsonc` — Configuración de agentes OpenCode
