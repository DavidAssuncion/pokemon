# Convenciones del proyecto

## Estilo de código

- PHP 8.2+ con tipado estricto (`declare(strict_types=1)`).
- PSR-4 autoloading: `App\` → `app/`, `Src\` → `src/`.
- Sigue el estilo de Laravel Pint (PSR-12).
- Sin comentarios triviales. El código debe ser autoexplicativo.

## Nombrado

| Elemento | Convención | Ejemplo |
|---|---|---|
| Clases de `src/` (Domain, App e Infra del módulo) | Español, PascalCase | `AgregadoBatalla`, `UsuarioRepositoryInterface`, `UsuarioController` |
| Clases de `app/` (Laravel genérico, no migrado a módulo) | Inglés, PascalCase | `Combate`, `HabitatsController` |
| Métodos/funciones | camelCase | `calcularYAplicarDaño()`, `elegirObjetivo()` |
| Propiedades | camelCase | `hpActual`, `velocidadAcumulada` |
| Constantes | UPPER_SNAKE_CASE | `SESSION_VERSION` |
| Tablas | snake_case, plural, inglés | `pokemon_stats`, `team_members` |
| Columnas | snake_case, inglés | `capture_rate`, `base_experience` |
| Rutas | kebab-case, inglés | `/habitats/{id}`, `/datagrid/{model}`, `/iconos/shiny/{filename}` |
| Migraciones | `{fecha}_{orden}_{descripcion}.php` | `2026_02_24_171456_create_pokemon_table.php` |

### Idioma

- **`src/` (todo el módulo, inc. Infra)**: español — clases, métodos, propiedades, mensajes y
  código de dominio.
- **`app/` (Laravel genérico, no migrado a un módulo)**: inglés (convención Laravel/Framework).
- **Tablas y columnas**: inglés.
- **URLs de rutas**: kebab-case inglés.
- **Mensajes de log de batalla**: español (visibles al usuario).
- **Traducciones**: las etiquetas de tipos (ej: "Fuego", "Agua") se resuelven via `TipoEnum::label()`.

## Estructura de carpetas

### Módulo (en `src/{{Modulo}}/` — convención canónica, ver `docs/ddd.md`)
```
src/<Modulo>/
  Domain/
    Entities/            → entidades con identidad SIEMPRE (int $id NO nullable), props public, toArray(): array
    ValueObjects/        → value objects (si aplica)
    Collections/         → extienden Src\Shared\Domain\Collection (NUNCA Illuminate\Support\Collection)
    DataTransferObjects/ → DTOs de datos (creación/actualización/filtros) y DTOs de presentación
    Exceptions/          → excepciones específicas del módulo
    Repositories/        → interfaces de repositorio (contrato en español)
  App/                   → casos de uso / comandos (Src\Shared\Bus + UnitOfWork para flujos complejos)
  Infra/
    Repositories/        → implementaciones Eloquent de las interfaces
    Models/              → modelos Eloquent (solo relaciones y scopes, sin lógica de dominio)
    Factories/           → factorías de mapeo modelo → entidad (patrón desdeArray)
    Requests/            → FormRequests (uno por rol: save/index)
    Controllers/         → controladores HTTP (sin try-catch)
    Livewire/            → componentes Livewire (si aplica)
    routes.php           → rutas del módulo, IMPORTADAS desde routes/web.php (el "genérico")
```

La antigua capa `Presentation/` deja de ser canónica: sus DTOs migran a
`Domain/DataTransferObjects` (los módulos actuales la conservan hasta migrar).

Excepción: `Crud/` contiene submódulos planos sin esta estructura completa (algunos tienen `Domain/` e `Infra/` vacíos).

### Infraestructura transversal compartida (en `app/`)

Solo la infraestructura Laravel **transversal** que no pertenece a un módulo concreto permanece
aquí; **no es el destino** de Controllers, Models Eloquent ni Livewire (ver nota y `docs/ddd.md`).

```
app/
  Datagrid/          → Consulta JSON de solo lectura (whitelist por modelo)
  Support/           → Conversión PNG→WebP (WebpConverter)
  Console/Commands/  → Comandos artesanos (iconos:optimize-webp)
  Providers/         → Service providers
  Enums/             → Enums PHP compartidos (TipoEnum, StatEnum)
  Http/Controllers/Controller.php → Base Controller (infra global; los controladores de módulo lo extienden)
  Crud/Base/         → Clases base para CRUD
```

> **En destino**, Controllers, Models Eloquent y Livewire **NO viven en `app/`**: pertenecen a
> `src/{{Modulo}}/Infra/` (`Controllers/`, `Models/`, `Livewire/`). Su presencia actual en `app/`
> es deuda de migración por módulo (estado actual). El base Controller
> (`App\Http\Controllers\Controller`) se mantiene en `app/` como infraestructura transversal y
> los controladores del módulo lo extienden.

## Arquitectura

- **Capa `Domain` pura por módulo**: sin Eloquent, sin HTTP, sin facades, sin `Request`.
- **`App` e `Infra` usan Laravel libremente** (Eloquent, FormRequests, controladores, rutas, jobs).
- **Controladores** en `src/{{Modulo}}/Infra/Controllers/`, **sin try-catch** (el handler global
  de `bootstrap/app.php` resuelve las excepciones de dominio). **IDs por parámetro de URL, NUNCA
  por body — salvo POST**. Prohibido `array_merge(validated, ['id' => ...])`: en update se
  construye la entidad con el id del path + campos del FormRequest.
- **FormRequests** separados por rol (ej: `UsuarioSaveRequest` y `UsuarioIndexRequest`) con reglas
  adecuadas a cada uno (required en save, nullable/sometimes en index).
- **Repositorios Eloquent** con contrato en español en `Domain/Repositories/` (`obtenerPorId`
  no-null, `insertar`, `insertarColeccion`, `actualizar`, `upsertColeccion`, `eliminar`). Sin
  `getCollection` genérico: los listados paginados/filtrados los hace Datagrid.
- **Rutas por módulo** en `src/{{Modulo}}/Infra/routes.php`, importadas desde `routes/web.php`
  con `require` (ficheros existentes bajo `routes/` se conservan y migran gradualmente).
- **CRUD simple** → repositorio directo; **flujos complejos** (transacciones, eventos) →
  `Src\Shared\Bus` + `UnitOfWork` (patrón ya usado en Exploraciones).
- **Models Eloquent** (`Infra/Models`): solo relaciones y scopes, sin lógica de dominio.
- **Livewire** en `src/{{Modulo}}/Infra/Livewire/` (destino; actualmente `app/Livewire/Combate.php` hasta migrar el módulo Battle).
- **Excepciones de dominio**: genéricas en `src/Shared/Domain/Exceptions/` (`DominioExcepcion`
  400, `RecursoNoExiste` 404, `ViolacionReglaNegocio` 422, `PermisoDenegado` 403) + específicas
  por módulo; mapa HTTP en `bootstrap/app.php` con las específicas PRIMERO.
- **Se mantiene**: testing con PHPUnit (`tests/Unit` y `tests/Feature`), Datagrid (`app/Datagrid`,
  listados de solo lectura con whitelist), transacciones/UnitOfWork
  (`app/Bus/DatabaseUnitOfWork`), iconos WebP (`app/Support/WebpConverter` +
  `iconos:optimize-webp`) y Frontend (Livewire 3 + Alpine + Tailwind 4).

## Dependencias

- **`Domain` de cada módulo es puro**: sin dependencias de Laravel ni de `app/` ni de otros módulos.
- **`App` e `Infra` del módulo usan Laravel libremente** (sustituye a la regla antigua "los módulos `src/` no dependen de Laravel").
- `app/` depende de `src/` (importación directa vía `use Src\...`).
- `src/Shared` no depende de módulos (infraestructura transversal).
- Estado actual (se mantiene hasta migrar): `src/Battle/Domain/` depende de `src/Pokemon/Domain/` y `src/Shared/Tipos/`; `src/Habitats/App/` depende de `src/Habitats/Domain/` e `src/Habitats/Infra/`.

## Base de datos

- PostgreSQL en el entorno de ejecución (Docker); SQLite en memoria (`:memory:`) para tests.
- Migraciones con `Schema::create()` y métodos fluidos de Blueprint.
- Seeders con datos reales de pokémon (151+ especies, 8 provincias, hábitats).

## Frontend

- **Livewire 3** para componentes interactivos.
- **Alpine.js** para animaciones y transiciones, y para estados de UI complejos (Pokédex asíncrona, dropdowns).
- **Tailwind CSS 4** con Vite. Sin CSS personalizado (todo utility-first).
- **Blade**: layouts via `@extends('layouts.app')`, partials via `@include()`.
- Vistas organizadas por módulo: `resources/views/habitats/`, `resources/views/crud/<modulo>/`, etc.

### Iconos de pokémon (WebP)

- Los iconos se **sirven en WebP** desde `public/images/iconos_webp/{id}.webp` (ruta pública estática; `.htaccess` con `Cache-Control: public, max-age=31536000, immutable` — solo Apache).
- Los **PNG originales** quedan en `public/images/iconos/{id}.png` como fuente y fallback de la Pokédex.
- El contrato `icon` de cualquier API/vista es `/images/iconos_webp/{id}.webp`.
- **Al añadir iconos nuevos, correr `php artisan iconos:optimize-webp --dir public/images/iconos --out public/images/iconos_webp`** (paso de deploy): convierte solo la raíz de `--dir`, escribe únicamente en `--out` (guard realpath dir≠out), es idempotente contra la salida y conserva los PNG.

### Dropdowns Alpine (patrón consolidado)

- Un único listener `click` global por componente para cerrar dropdowns al hacer click fuera: se registra en `init()` (`document.addEventListener('click', handler)`) y se **elimina en `destroy()`** (`removeEventListener` + reset a `null`), junto con observer/abort de fetches pendientes (patrón de `pokedexApp()`).
- Estado de apertura con flag booleano por dropdown (`showTypeFilter`, `showEffortFilter`, ...); `@click.stop` en acciones internas para no disparar el cierre.

## Datagrid (consultas JSON)

- Para consultas JSON de solo lectura usar el subsistema `app/Datagrid/` (ver `docs/architecture.md`).
- Registrar el modelo en `DatagridServiceProvider` con su `DatagridDefinition` (whitelist explícita): nada de lo que envíe el cliente se aplica al query sin pasar por la definición.
- Los slugs se registran en minúscula; modelo no registrado → 404; parámetros no whitelisted → ignorados silenciosamente.
- `RelationFilter` para filtros de relación (whereHas); usar `$constraint` (Closure) solo cuando el whereHas necesita lógica custom (ej. `effort > 0`).
- `meta.counts` solo si la UI necesita contadores globales (independientes de filtros/paginación).

## Tests

- PHPUnit con `tests/Unit/` y `tests/Feature/`.
- Base de datos SQLite en memoria para tests (`:memory:`).
- Sin framework específico de testing adicional.

## Versionado de sesión

Las batallas serializadas en sesión incluyen un prefijo de versión: `v{numero}|{serializado}`. Al cambiar la estructura de `AgregadoBatalla` o `Combatiente`, incrementar `SESSION_VERSION` en `Combate.php` y añadir lógica de migración.
