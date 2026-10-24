# Equipos

Gestión del equipo del jugador: agregado `TeamAggregate`, formación persistida,
miembros y objetos equipados.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/TeamAggregate.php` | Agregado: id, nombre, userId, miembros, formación |
| `Domain/TeamRepositoryInterface.php` | Contrato (`obtenerTodos`, `obtenerPorId`, `guardar`, `eliminar`) |
| `Domain/Collections/TeamCollection.php` | Colección tipada de agregados |
| `Domain/DataTransferObjects/` | `EquipoDefinido`, `EquipoInicialResumen` (onboarding) |
| `Domain/ValueObjects/ObjetosEquipo.php` | Mapa slot → `item_key` de objetos equipados |
| `App/ObtenerEquipos.php` | Listado para vistas/controladores |
| `App/ActualizarObjetosEquipo.php` | Actualiza objetos por slot con validación de propiedad |
| `App/CrearEquipoInicial.php` | Onboarding: 3 equipos predefinidos en transacción |
| `Infra/EloquentTeamRepository.php` | Persistencia y mapeo a `TeamAggregate` |

## Puntos de entrada

- `ObtenerEquipos::run(): TeamCollection` (Blade itera la colección).
- `ActualizarObjetosEquipo::actualizar(Team, ObjetosEquipo, userId): ObjetosEquipo`.
- `CrearEquipoInicial::equiposDefinidos(): EquipoDefinidoCollection`,
  `equiposConNombres(): EquipoInicialResumenCollection`, `crear(userId, teamKey)`.
- `TeamRepositoryInterface` implementado por `EloquentTeamRepository`.

## Notas

- `TeamAggregate::$members` sigue conteniendo modelos Eloquent (`TeamMember`) por
  compatibilidad con las vistas; es deuda conocida documentada en la clase.
- `equiposConNombres()` resuelve nombres desde `pokemon` con fallback `Pokémon #id`.
- La formación se persiste en `teams.formacion`; la lógica de posición vive en
  `CombateEntrenadores\ConstruirEquipoJugador`.

## Tests

`tests/Feature/EloquentTeamRepositoryTest.php`, `tests/Feature/Equipos*`,
`tests/Feature/OnboardingTest.php`.
