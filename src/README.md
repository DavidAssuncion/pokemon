# src/ — Módulos de dominio

Contenedor de los módulos DDD/Hexagonal. Cada carpeta es un módulo con su propio
`README.md` y, cuando aplica, `Domain/`, `App/` e `Infra/`.

| Carpeta | Qué encontrarás |
|---|---|
| `Battle/` | Motor de combate: agregado, turnos, cadena de daño, efectos, IA, jefes |
| `Exploraciones/` | Simulación y resolución de expediciones, riesgo, recompensas |
| `CombateEntrenadores/` | Combate contra entrenadores de hábitat |
| `CombateRuta/` | Combate de ruta con rivales salvajes |
| `Equipos/` | Agregado de equipo y persistencia |
| `Gimnasios/` | Catálogo de gimnasios, etapas y progreso |
| `Habitats/` | Provincias, hábitats y cadenas evolutivas |
| `Huevos/` | Gestación/incubación de huevos |
| `Mazmorras/` | Pisos, jefes y progreso de mazmorra |
| `Pokemon/` | Entidad de pokémon y stats de batalla |
| `Reclutamiento/` | Captura y evolución |
| `Shared/` | Infraestructura transversal (colecciones, bus, tipos, VOs) |

## Regla de oro

`Domain` es puro (sin Laravel). `App` orquesta casos de uso. `Infra` adapta
Eloquent/HTTP/rutas. Los contratos públicos usan DTOs y colecciones tipadas;
los `array` solo aparecen en fronteras (`toArray`, serialización, Blade, Eloquent).

Índice detallado: [`docs/modulos/README.md`](../docs/modulos/README.md).
