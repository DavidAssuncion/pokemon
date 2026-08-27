# Convenciones del proyecto

## Estilo de código

- PHP 8.2+ con tipado estricto (`declare(strict_types=1)`).
- PSR-4 autoloading: `App\` → `app/`, `Src\` → `src/`.
- Sigue el estilo de Laravel Pint (PSR-12).
- Sin comentarios triviales. El código debe ser autoexplicativo.

## Nombrado

| Elemento | Convención | Ejemplo |
|---|---|---|
| Clases de dominio | Español, PascalCase | `AgregadoBatalla`, `ServicioEjecucionBatalla` |
| Clases de app/ | Inglés, PascalCase | `Combate`, `HabitatsController` |
| Métodos/funciones | camelCase | `calcularYAplicarDaño()`, `elegirObjetivo()` |
| Propiedades | camelCase | `hpActual`, `velocidadAcumulada` |
| Constantes | UPPER_SNAKE_CASE | `SESSION_VERSION` |
| Tablas | snake_case, plural, inglés | `pokemon_stats`, `team_members` |
| Columnas | snake_case, inglés | `capture_rate`, `base_experience` |
| Rutas | kebab-case, inglés | `/habitats/{id}`, `/datagrid/{model}`, `/iconos/shiny/{filename}` |
| Migraciones | `{fecha}_{orden}_{descripcion}.php` | `2026_02_24_171456_create_pokemon_table.php` |

### Idioma

- **Dominio** (`src/`): español (nombres de clases, métodos, comentarios de dominio).
- **Infraestructura** (`app/`): inglés (convención Laravel/Framework).
- **Tablas y columnas**: inglés.
- **Mensajes de log de batalla**: español (visibles al usuario).
- **Traducciones**: las etiquetas de tipos (ej: "Fuego", "Agua") se resuelven via `TipoEnum::label()`.

## Estructura de carpetas

### Módulo DDD (en `src/`)
```
src/<Modulo>/
  Domain/       → Entidades, Value Objects, Agregados, Servicios de dominio
  App/          → Casos de uso (Application Services)
  Infra/        → Repositorios, adaptadores de infraestructura
  Presentation/ → DTOs, transformadores para la vista
```

Excepción: `Crud/` contiene submódulos planos sin esta estructura completa (algunos tienen `Domain/` e `Infra/` vacíos).

### Capa Laravel (en `app/`)
```
app/
  Livewire/     → Componentes Livewire
  Http/Controllers/ → Controladores HTTP
  Models/       → Modelos Eloquent
  Providers/    → Service providers
  Enums/        → Enums PHP
  Crud/Base/    → Clases base para CRUD
```

## Arquitectura

- **Controladores ligeros**: la lógica de negocio va en servicios de dominio (`src/`), no en controladores.
- **Livewire**: los componentes pueden orquestar lógica de dominio directamente (ej: `Combate` usa `ServicioEjecucionBatalla`).
- **Models Eloquent**: solo definen relaciones y scopes. Sin lógica de dominio.
- **Sin repositorios Eloquent**: se accede a los modelos directamente desde controladores o servicios `app/`. Los repositorios en `src/` se usan solo cuando hay mapeo a entidades de dominio (ej: `HabitatRepository`).

## Dependencias

- Los módulos `src/` **no dependen de Laravel** ni de `app/`.
- `app/` depende de `src/` (importación directa vía `use Src\...`).
- `src/Battle/Domain/` depende de `src/Pokemon/Domain/` y `src/Shared/Tipos/`.
- `src/Habitats/App/` depende de `src/Habitats/Domain/` e `src/Habitats/Infra/`.

## Base de datos

- SQLite en desarrollo y testing.
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
