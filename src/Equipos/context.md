# Contexto del módulo `Equipos`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Gestión del equipo del jugador: composición, formación de combate, posturas, objetos equipados y
onboarding de los tres equipos iniciales. El equipo es la unidad que consumen Ruta, Entrenadores,
Gimnasios y Mazmorras para construir el bando del jugador.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/TeamAggregate.php` | Agregado: id, nombre, `userId`, miembros y formación persistida. |
| `Domain/TeamRepositoryInterface.php` | Contrato de persistencia (`obtenerTodos`, `obtenerPorId`, `guardar`, `eliminar`). |
| `Domain/Collections/TeamCollection.php` | Colección tipada de agregados. |
| `Domain/DataTransferObjects/EquipoDefinido.php` | Definición de equipo inicial (onboarding). |
| `Domain/DataTransferObjects/EquipoInicialResumen.php` | Resumen de equipo inicial para la vista. |
| `Domain/ValueObjects/ObjetosEquipo.php` | Mapa slot → `item_key` de objetos equipados. |
| `App/ObtenerEquipos.php` | Listado para vistas/controladores. |
| `App/AgregarMiembroEquipo.php` | Añade/reemplaza un miembro en un slot (reglas + transacción). |
| `App/QuitarMiembroEquipo.php` | Quita un miembro validando exploración activa. |
| `App/ActualizarRolMiembro.php` | Cambia el behavior validando exploración activa. |
| `App/ActualizarFormacionEquipo.php` | Persiste la formación validando exploración activa. |
| `App/ActualizarPosturasEquipo.php` | Persiste las posturas validando exploración activa. |
| `App/EliminarEquipo.php` | Borra el equipo validando exploración activa. |
| `App/ResultadoMiembroEquipo.php` | Resultado tipado de `AgregarMiembroEquipo`. |
| `App/ActualizarObjetosEquipo.php` | Actualiza objetos por slot con validación de propiedad. |
| `App/CrearEquipoInicial.php` | Onboarding: tres equipos predefinidos en transacción. |
| `Infra/EloquentTeamRepository.php` | Persistencia y mapeo a `TeamAggregate`. |

## Decisiones de producto y funcionamiento

1. **Tamaño estándar: exactamente 3 miembros.** Slots `1-3`. La constante canónica es
   `Src\Shared\Domain\ReglasEquipo::MIEMBROS` (3), con `SLOT_MIN=1` y `SLOT_MAX=3`.
2. **La UI puede guardar equipos incompletos**, pero **no se puede combatir** con menos o más de 3.
   La validación de combate vive en `ConstruirEquipoJugador::desdeEquipo()` (422 «exactamente 3
   miembros»), punto único de Ruta, Entrenadores, Gimnasios y Mazmorra.
3. **Formación (vanguardia/retaguardia)** por slot. Prioridad:
   `popup del modal` > `teams.formacion` persistida > clasificación automática por stats
   (`ClasificadorOfensivaDefensiva`: ofensiva = atk + speed > defensiva = def + spDef + hp →
   retaguardia; empate → vanguardia).
4. **Posturas** por slot: `atacar`, `guardia`, `cubrir` (`teams.posturas`). El combate las consume
   al construir el combatiente.
5. **Objetos equipados** por reclutado (`obj_equipados`); `ObjetosEquipo` normaliza el mapa por
   slot y `ActualizarObjetosEquipo` valida propiedad del reclutado/equipo.
6. **Onboarding**: `CrearEquipoInicial` crea tres equipos predefinidos con miembros de la cuenta.
7. **Anti-IDOR**: `Team` tiene global scope por usuario; el route-model binding devuelve 404 para
   equipos ajenos. `TeamController` refuerza la pertenencia en operaciones con `member_id`.
8. **Bloqueo por exploración**: no se puede borrar ni modificar un equipo con exploraciones
   activas (`isExploring()`).
9. **El agregado aún arrastra modelos Eloquent** (`TeamAggregate::$members` es
   `array<int, TeamMember>`). Es deuda de migración, no un contrato de dominio deseado.

## Contratos y puntos de entrada

Rutas (`routes/player.php`, bajo `auth`):

```text
GET    /equipos
POST   /teams
PUT    /teams/{team}
DELETE /teams/{team}
POST   /teams/add-member
POST   /teams/remove-member
POST   /teams/update-member-role
PATCH  /teams/member/{member}/role
PATCH  /teams/{team}/formacion
PATCH  /teams/{team}/posturas
PATCH  /teams/{team}/items
```

- `TeamRepositoryInterface` es el puerto de persistencia; la implementación Eloquent vive en
  `Infra/`.
- Tablas: `teams` (`formacion`, `posturas` JSON), `team_members` (`slot`, `behavior`).
- El payload de `/equipos` se construye en `PlayerController::equipos()` y se serializa con
  `ReclutadoSerializer`; el frontend consume `@json($reclutados)`.

## Dependencias

- `Src\Shared\Domain\ReglasEquipo` — tamaño/slots.
- `Src\Shared\Domain\ClasificadorOfensivaDefensiva` — formación automática.
- `Src\CombateEntrenadores\App\ConstruirEquipoJugador` — construcción del bando del jugador.
- `App\Models\Team`, `App\Models\TeamMember`, `App\Models\Reclutado`.
- `App\Support\ReclutadoSerializer` — contrato de reclutados para la vista.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Resuelto 2026-10-31] `TeamController` ya no concentra reglas de negocio**: las operaciones
  viven en `src/Equipos/App/` (`AgregarMiembroEquipo`, `QuitarMiembroEquipo`, `ActualizarRolMiembro`,
  `ActualizarFormacionEquipo`, `ActualizarPosturasEquipo`, `EliminarEquipo`). El controlador conserva
  solo validación HTTP, anti-IDOR y la forma de la respuesta (contrato JSON intacto).
- **[Media] `TeamAggregate` usa arrays y modelos Eloquent** para miembros/formación. Migrar a
  `TeamMemberCollection` + DTO de miembro y `FormacionEquipo` (VO existente en
  `CombateEntrenadores`) para eliminar el array como contrato.
- **[Media] `PlayerController::equipos()` construye arrays anidados** para equipos/miembros.
  Extraer un presentador/DTO (`EquipoVista`) y reutilizarlo en la vista.
- **[Media] Contrato `nivel` de `ReclutadoSerializer`**: al pasar `nivelUsuario`, el campo `nivel`
  del payload toma el nivel del usuario (no el del reclutado), mientras `EquiposPayloadTest` espera
  el nivel del reclutado. Decidir el contrato (nivel mostrado vs nivel escalado) y alinear
  serializer, tests y UI.
- **[Baja] `resources/views/equipos/index.blade.php`**: se eliminó el cálculo duplicado de nivel
  (`nivelDe` ahora consume `pokemon.nivel` del backend). Quedan cálculos de eje de gráficas y
  formato (presentación); no deben crecer con reglas de negocio.
- **Constante transversal**: el «3» de equipo ya está centralizado en `ReglasEquipo`; no volver a
  introducir literales en validaciones, mensajes o constructores.

## Guía de búsqueda (grep)

- Clases: `TeamAggregate`, `TeamCollection`, `TeamRepositoryInterface`, `ObjetosEquipo`,
  `ActualizarObjetosEquipo`, `CrearEquipoInicial`, `ObtenerEquipos`, `ConstruirEquipoJugador`.
- Constantes: `ReglasEquipo::MIEMBROS`, `ReglasEquipo::SLOT_MIN`, `ReglasEquipo::SLOT_MAX`.
- Rutas/términos: `/equipos`, `/teams`, `formacion`, `posturas`, `slot`, `obj_equipados`,
  `update-member-role`.
- Tablas: `teams`, `team_members`.
- Tests: `tests/Feature/EloquentTeamRepositoryTest.php`, `tests/Feature/Equipos*`,
  `tests/Feature/OnboardingTest.php`, `tests/Feature/CombateEntrenadores/ConstruirEquipoJugadorTest.php`.
