# Índice de módulos

Documentación de indexación del proyecto **Pokemon Battle Game** (Laravel 12 + DDD).
Cada módulo tiene su README canónico dentro de su carpeta en `src/`; esta página es el
mapa de navegación y las reglas transversales.

## Cómo navegar

| Módulo | Carpeta | README | Responsabilidad |
|---|---|---|---|
| Battle | `src/Battle` | [Battle](../../src/Battle/README.md) | Motor de combate 3v3: turnos, daño, efectos, clima, IA, jefes |
| Exploraciones | `src/Exploraciones` | [Exploraciones](../../src/Exploraciones/README.md) | Simulación, riesgo, recompensas y resolución de exploraciones |
| CombateEntrenadores | `src/CombateEntrenadores` | [CombateEntrenadores](../../src/CombateEntrenadores/README.md) | Combates contra entrenadores de hábitat |
| CombateRuta | `src/CombateRuta` | [CombateRuta](../../src/CombateRuta/README.md) | Combates de ruta (rivales salvajes por nivel) |
| Equipos | `src/Equipos` | [Equipos](../../src/Equipos/README.md) | Agregado de equipo, formación y objetos equipados |
| Gimnasios | `src/Gimnasios` | [Gimnasios](../../src/Gimnasios/README.md) | Catálogo de gimnasios, etapas y progreso |
| Habitats | `src/Habitats` | [Habitats](../../src/Habitats/README.md) | Provincias, hábitats y familias evolutivas |
| Huevos | `src/Huevos` | [Huevos](../../src/Huevos/README.md) | Gestación, incubación y eclosión |
| Mazmorras | `src/Mazmorras` | [Mazmorras](../../src/Mazmorras/README.md) | Pisos, jefes y progreso de mazmorra |
| Pokemon | `src/Pokemon` | [Pokemon](../../src/Pokemon/README.md) | Entidad de pokémon y cálculo de stats |
| Reclutamiento | `src/Reclutamiento` | [Reclutamiento](../../src/Reclutamiento/README.md) | Captura y evolución por caramelos |
| Shared | `src/Shared` | [Shared](../../src/Shared/README.md) | Colecciones, bus, tipos, excepciones y utilidades |
| app/ | `app/` | [Infraestructura Laravel](../../app/README.md) | Modelos, controladores, Livewire, Datagrid, Support, Crud |

## Dependencias entre módulos

```
Shared  ←  todos
Pokemon ←  Battle, Exploraciones, Combate*, Gimnasios, Habitats, Reclutamiento
Battle  ←  Exploraciones, CombateEntrenadores, CombateRuta, Gimnasios, Mazmorras
Exploraciones ← CombateEntrenadores, CombateRuta (recompensas y pool ponderado)
CombateEntrenadores ← CombateRuta, Gimnasios, Mazmorras (mapeador y equipo jugador)
```

Regla: `Domain` no importa Eloquent/HTTP. `App` e `Infra` sí pueden usar Laravel.
`src/Shared` no depende de ningún módulo.

## Política de arrays (post-refactor)

Contrato vigente: **ninguna capa devuelve `array` como contrato público**. Se usan DTOs
`readonly`, `Collection` tipadas (`Src\Shared\Domain\Collection`) y Value Objects.

Excepciones toleradas, siempre en frontera y documentadas en el código:

- `toArray()` / `aArray()` / `aArrays()` / `jsonSerialize()` de DTOs y entidades.
- `__serialize()` / `__unserialize()` de estado de batalla (sesión PHP).
- `Collection::all()`, `toList()`, `map()`, `pluck()` (puente a PHP/Blade).
- Eloquent (`casts()`, `toArray()`), Livewire (`toLivewire()`), Blade y `JsonResponse`.
- Factories `desdeArray()` que reconstruyen VOs desde JSON/sesión/BD.

Deuda conocida (pendiente, no bloquea):

- `src/Exploraciones/Domain`: `CalculadorRiesgo::evaluar()`, `CalculadorMatchups::calcular()`,
  `CalculadorRecompensas`, `EvaluadorExploracion::resolverContratiempo()` y los wrappers
  `@deprecated` de `SimuladorEncuentros` mantienen shapes de preview/BC.
- `src/Exploraciones/Presentation` y `app/Livewire/Presenters`: payloads de vista.
- `app/Datagrid`, `app/Crud/Base`, `app/Enums::options()`: contratos de Laravel/Blade.

## Documentación relacionada

- [Arquitectura](../architecture.md) — capas, patrones y flujos.
- [Convenciones](../conventions.md) — estilo, nombrado y reglas DDD.
- [DDD](../ddd.md) — estructura canónica de módulos.
- [Testing](../testing.md) — PHPUnit y base de datos de test.
- [Contexto](../context.md) — estado y deuda técnica del proyecto.
- [Agentes](../agent-tooling.md) — herramientas y flujo de trabajo asistido.
