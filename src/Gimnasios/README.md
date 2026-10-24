# Gimnasios

Catálogo de los 18 gimnasios, sus etapas (1-4), equipos por etapa y progreso del
jugador. Incluye combate y CRUD administrativo.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/Gimnasio.php` | Entidad: slug, medalla, tipo, nivel mínimo, equipos por etapa |
| `Domain/CatalogoGimnasios.php` | Catálogo en código (semilla y fallback del presentador) |
| `Domain/DataTransferObjects/` | `EquipoEtapaGimnasio`, `GimnasioResumen`, `DetalleGimnasio`, `EtapaGimnasio`, `ResultadoGimnasio` |
| `Domain/Collections/` | `GimnasioCollection`, `EquipoEtapaGimnasioCollection`, `EtapaGimnasioCollection`, `GimnasioResumenCollection` |
| `Domain/Repositories/` | `GymCatalogoRepositoryInterface`, `GymProgressRepositoryInterface` |
| `Domain/EvsRangoEntrenador.php` | Reparto de EVs por etapa |
| `Domain/Exceptions/` | `GimnasioBloqueado`, `GimnasioCompletado`, `GimnasioNoExiste` |
| `App/ObtenerGimnasios.php` | Listado con estado/progreso |
| `App/ObtenerGimnasioDetalle.php` | Detalle de un gimnasio |
| `App/IniciarCombateGimnasio.php` | Inicio de combate por etapa/líder |
| `App/GeneradorPokemonGimnasio.php` | Equipo rival escalado a 3v3 |
| `App/RegistrarResultadoGimnasio.php` | Progreso y recompensas |
| `Infra/` | Repositorios Eloquent, `GimnasioController`, `AdminGymController`, rutas |

## Puntos de entrada

- `GET /api/gimnasios` → `ObtenerGimnasios::obtener()`.
- `POST /api/gimnasios/{slug}/combatir` → `IniciarCombateGimnasio`.
- CRUD admin en `AdminGymController` (crear, editar datos, reescribir etapas).
- `GymSeeder` migra `CatalogoGimnasios` a `gyms` + `gym_stages`.

## Contratos tipados

- `Gimnasio::$equipos` es `EquipoEtapaGimnasioCollection` (clave = etapa 1-4).
- `EquipoEtapaGimnasio::todos()` → `IntCollection` (vanguardia + retaguardia).
- `obtenerTodos()` → `GimnasioCollection`.
- `GeneradorPokemonGimnasio::generar()` → `DatosPokemonBatallaCollection`.

## Reglas

- Sin borrado/desactivación de gimnasios (solo crear/editar).
- Etapas 1-3 = entrenadores; etapa 4 = líder.
- Progreso por usuario en `gym_progress`; se bloquea por nivel mínimo.

## Tests

`tests/Feature/Gimnasios/` (API, repositorio, generador, seeder) y
`tests/Unit/Gimnasios/` (catálogo y equipos).
