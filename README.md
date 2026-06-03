# Pokemon Battle Game

Juego por turnos 3v3 con sistema de combate, clima, objetos y habilidades. Construido con Laravel 12 + DDD.

## Stack tecnológico

- PHP 8.2+
- Laravel 12
- Livewire 3 + Alpine.js
- Tailwind CSS 4
- SQLite
- Vite + Laravel Vite Plugin
- Node.js + npm

## Arquitectura

Híbrida: `app/` (Laravel estándar: Models, Controllers, Livewire, Providers) + `src/` (DDD/Hexagonal).

```
app/                    → Laravel estándar (MVC)
  Livewire/Combate.php  → Componente de batalla manual
  Models/               → Eloquent models
  Http/Controllers/     → Controladores HTTP
  Providers/            → Service providers (efectos, crud)
src/                    → DDD / Hexagonal
  Battle/               → Sistema de combate (núcleo)
  Pokemon/              → Entidades de pokémon
  Habitats/             → Hábitats y provincias
  Equipos/              → Gestión de equipos
  Reclutamiento/        → Reclutamiento de pokémon
  Crud/                 → CRUD administrativo (13 sub-módulos)
  Shared/               → Tipos, colecciones, utilidades compartidas
docs/                   → Documentación del proyecto
```

### Módulos

| Módulo | Descripción |
|---|---|
| **Battle** | Sistema de combate por turnos 3v3. Chain of Responsibility para daño, Observer para eventos, sistema de efectos (habilidades/objetos), clima, posiciones (vanguardia/retaguardia), máquina de estados de turno |
| **Pokemon** | Entidades de dominio de pokémon (estadísticas, tipos, movimientos) |
| **Habitats** | Catálogo de provincias y hábitats con sus pokémon asociados |
| **Equipos** | Formación y gestión de equipos de 3 pokémon |
| **Reclutamiento** | Captura y reclutamiento de pokémon |
| **Crud** | Panel administrativo CRUD con 13 módulos (habilidades, evoluciones, tipos, stats, etc.) |
| **Shared** | Tipos de pokémon (enum), chart de efectividad, colecciones base |

## Requisitos previos

- PHP 8.2+
- Composer
- Node.js 18+ y npm

## Instalación rápida

```bash
composer setup
```

Este comando ejecuta: `composer install`, genera `.env`, `php artisan key:generate`, migraciones, `npm install` y `npm run build`.

## Comandos disponibles

```bash
composer dev    # Servidor + queue + logs + vite (concurrente)
composer test   # Ejecuta tests (PHPUnit)
```

## Sistema de agentes AI

El proyecto utiliza **OpenCode** con 5 agentes orquestados:

| Rol | Agente | Responsabilidad |
|---|---|---|
| Analista | `@analista` | Especificación funcional |
| Arquitecto | `@arquitecto` | Diseño técnico |
| Backend | `@backend` | Implementación Laravel |
| Frontend | `@frontend` | Implementación UI |
| Bibliotecario | `@bibliotecario` | Documentación |

Ver `docs/workflow.md` para el flujo completo de desarrollo.

## Estructura de directorios

```
├── app/
│   ├── Http/Controllers/   → Habitats, Reclutados, Teams
│   ├── Livewire/           → Combate (batalla manual)
│   ├── Models/             → 13 modelos Eloquent
│   └── Providers/          → App, BattleEffect, Crud
├── src/
│   ├── Battle/
│   │   ├── Domain/         → Agregado, Combatiente, Turnos, CadenaDaño, Efectos
│   │   ├── App/            → IniciarBatalla
│   │   ├── Infrastructure/ → FabricaBatallaMock
│   │   └── Presentation/   → DTOMovimientoBatalla
│   ├── Pokemon/Domain/     → PokemonEntity, Stats, Movement
│   ├── Habitats/           → App (3 casos de uso), Domain, Infra
│   ├── Equipos/            → TeamAggregate, TeamSrv
│   ├── Reclutamiento/      → ReclutamientoSrv
│   ├── Crud/               → 13 submódulos CRUD
│   └── Shared/             → Tipos, Collection
├── database/
│   ├── migrations/         → 18 migraciones
│   └── seeders/            → Database, Habitat, Pokemon, Province, Reclutados
├── docs/                   → workflow, context, architecture, conventions
├── resources/
│   ├── views/              → Blade (combate, habitats, crud, reclutados)
│   └── iconos/             → sprites pokémon
├── routes/
│   ├── web.php             → Rutas principales
│   ├── habitats.php        → Rutas hábitats
│   └── reclutados.php      → Rutas reclutamiento/equipos
└── tests/                  → Unit y Feature
```

## Contribución

Ver `docs/workflow.md` para el flujo de desarrollo con agentes OpenCode. La documentación de cada agente está en `docs/agents/`.
