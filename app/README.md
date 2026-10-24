# app/ — Infraestructura Laravel

Capa Laravel estándar. Contiene modelos Eloquent, controladores HTTP, Livewire,
jobs, providers y utilidades transversales. La lógica de dominio vive en `src/`.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Models/` | Modelos Eloquent (`Pokemon`, `Team`, `Reclutado`, `Gym`, `Huevo`, `ExploracionActiva`…) y casts (`StatsReclutado`, `ExpReclutado`) |
| `Http/Controllers/` | Controladores de vistas/API no migrados a `src/*/Infra` |
| `Livewire/` | `Combate.php` (batalla manual) y `Presenters/` (payloads de vista) |
| `Support/` | `ItemCatalogo`, `ReclutadoSerializer`, `BattleSessionService`, `CreadorBatallaSesion`, `CadenasEvolutivas`, `WebpConverter` |
| `Bus/` | `DatabaseUnitOfWork` (transacciones para el bus de `Shared`) |
| `Datagrid/` | Consultas JSON de solo lectura con whitelist (`DatagridService`, `DatagridDefinition`, filtros) |
| `Crud/Base/` | Generadores del CRUD admin (`PostgreSqlSchemaReader`, `ViewGenerator`, `RequestGenerator`) |
| `Enums/` | `TipoEnum`, `StatEnum` (incluyen `options()` para Blade) |
| `Jobs/` | `ActualizarPokedexJob`, `RecompilarHabitatJsonJob`, etc. |
| `Providers/` | `AppServiceProvider`, `BattleEffectServiceProvider`, `DatagridServiceProvider` |
| `Console/Commands/` | Comandos artisan (`iconos:optimize-webp`, generación CRUD) |

## Límites arquitectónicos

- `app/` puede depender de `src/`; nunca al revés.
- Los modelos Eloquent no contienen lógica de dominio (solo relaciones/scopes/casts).
- Los controladores delegan en casos de uso de `src/*/App` o `src/*/Infra`.
- Las respuestas JSON y los payloads de Livewire/Blade son frontera: pueden usar
  `array`/`toArray()`. El dominio no los devuelve.
- El mapa HTTP de excepciones está en `bootstrap/app.php` (específicas primero).

## Rutas

`routes/web.php` importa los ficheros de módulo (`habitats.php`, `gimnasios.php`,
`mazmorras.php`, `huevos.php`, `ruta.php`, `entrenadores.php`, `exploraciones.php`,
`player.php`, `datagrid.php`).

## Tests

`tests/Feature/` cubre controladores, Livewire, Datagrid y flujos de integración;
`tests/Unit/` cubre reglas de dominio de `src/`.
