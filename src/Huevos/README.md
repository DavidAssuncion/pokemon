# Huevos

Gestión de huevos: creación, gestación, incubación, eclosión y listado para la UI.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/CalculadorEstadoHuevo.php` | Estado (`gestando`/`incubando`/`listo`) y tiempo restante |
| `Domain/CalculadorTiemposHuevo.php` | Horas totales a partir de `capture_rate` y `hatch` |
| `Domain/EstadoHuevo.php` / `EstadoCalculadoHuevo.php` | Enum y resultado del cálculo |
| `Domain/DataTransferObjects/` | `HuevoResumen`, `HuevoDisponible`, `LimitesHuevos`, `ResumenHuevos` |
| `Domain/Collections/` | `HuevoResumenCollection`, `HuevoDisponibleCollection` |
| `App/CrearHuevo.php` | Crea huevo si la familia cumple requisitos |
| `App/EclosionarHuevo.php` | Eclosión y creación del reclutado |
| `App/ListarHuevos.php` | Contrato de `GET /api/huevos` |
| `Infra/Controllers/HuevoController.php` | Endpoints + rutas (`routes/huevos.php`) |

## Contrato de la API

`GET /api/huevos` → `ResumenHuevos::toArray()`:

- `huevos[]`: `id`, `estado`, `tiempo_total_horas`, `tiempo_restante`, `progreso`,
  `puede_eclosionar`, `pokemon {id, name, icon}`.
- `disponibles[]`: `evolution_chain_id`, `pokemon`, `capture_rate`, `hatch`.
- `limites`: `max_gestacion`, `max_incubando`, `gestacion_activa`, `incubacion_activa`.

`POST /api/huevos` devuelve `HuevoResumen`; `POST /api/huevos/{huevo}/eclosionar`
devuelve el reclutado serializado.

## Reglas

- Solo 1 huevo gestando y 1 incubando a la vez.
- Una familia necesita ≥2 reclutados distintos y no tener huevo activo.
- El representante es el de menor `species_id` con `hatch` definido.
- Shiny de huevo usa IVs máximos (`GeneradorIvs::maximos()`).

## Tests

`tests/Feature/Huevos/` y pruebas de `ListarHuevos`/eclosión.
