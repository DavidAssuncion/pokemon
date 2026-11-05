# ADR-0002: Catálogo de bayas con stock 0 y detalle de especie por familia

- **Estado:** Aceptado
- **Fecha:** 2026-11-04
- **Ámbito:** `src/Shared/Domain/Items`, `src/Granjas`, `src/Habitats`, `app/Support`,
  `resources/views/habitats/show.blade.php`, `routes/`
- **Tarea:** TASK-013 — plan v1 + addendum v1.1

## Contexto

El modal de granja mostraba únicamente las bayas con saldo positivo porque `resumenBayas()`
descarta las de cantidad 0. Eso impedía dos cosas: ver el catálogo completo de 36 bayas y
distinguir una baya **agotada** (existe, stock 0) de una baya **inexistente**. Además, la
selección de baya vivía dentro del mismo modal que la gestión de la granja, de modo que abrir el
selector no tenía un estado propio ni un cierre en capas.

En paralelo, la tarjeta de familia mostraba un solo sprite. Faltaba consultar la información
completa de cada especie —habilidades, learnset y metadatos— sin inflar `GET /farm` ni
`GET /api/habitats/{id}/families` con datos que el usuario no siempre va a mirar. El orden de
los miembros de una familia seguía el `species_id`, que no siempre coincide con la etapa
evolutiva real (ese orden se conserva para el contrato de detalle; la UI final no lo usa, ver
addendum v1.1).

Restricciones de la tarea: contratos aditivos, sin migraciones, sin tests, sin F6/F7, sin commit
ni push, y conservar `resumenBayas()` para el payload de granja.

## Decisión

| ID | Decisión | Motivo |
|---|---|---|
| **D1** | Cuatro categorías (`GrupoBaya`): Medicinas = `BAYAS_BASE` (6), Mejoras = `BAYAS_RARAS` (7), Reductores = `BAYAS_ESTADISTICA` (5), Resistencias = resto de bayas de tipo (18) | La clasificación ya existía implícita en `CatalogoItems`; se expone una única fuente de verdad sin duplicar listas. |
| **D2** | El catálogo de 36 bayas se sirve en un endpoint aparte (`GET /api/habitats/{habitat}/farm/berries`); `resumenBayas()` no cambia | Mantiene el contrato de granja y evita cargar 36 filas en cada refresco de la granja. |
| **D3** | El modal de bayas es hermano del modal de granja (`z-[60]`), con un único handler de Escape que cierra la capa superior | Evita el doble cierre y permite mantener la granja abierta tras plantar. |
| **D4** | Un clic en una baya con stock la planta directamente; una baya con cantidad 0 no es plantable | Reduce pasos en la acción más frecuente; el backend sigue validando el saldo. |
| **D5** | El orden de los miembros de una familia se calcula por `stage`, luego `species_id`, luego `id` (lo consume el contrato de detalle; la UI actual no muestra tabs por integrante) | El orden refleja la etapa evolutiva real; los desempates lo hacen determinista. |
| **D6** | El detalle de especie se sirve en un endpoint propio y se cachea por `chainId/pokemonId`; no se incrusta en el payload de granja. Queda disponible pero **no conectado** a la UI actual | Evita multiplicar el tamaño de `GET /farm` por el número de miembros de todas las familias. |
| **D7** | El detalle incluye habilidades, learnset y metadatos; se implementa en `PresentadorDetalleEspecie` (opt-in) y `PresentadorDetallePokemon` no cambia para la Pokédex | El detalle normal de la Pokédex conserva su contrato; la vista de familia podrá pedir el extra cuando se conecte. |
| **D8** | Una especie no vista devuelve el detalle completo con `visto=false`; no se bloquea la respuesta | El detalle de familia es información de gestión, no un desbloqueo de Pokédex. |
| **D9** | La autorización del detalle exige que la cadena esté asignada al hábitat y que el Pokémon pertenezca a `pokemon.evolution_chain_id = chain`; cualquier combinación inválida → 404 | Cierra un IDOR por IDs libres sin revelar si el Pokémon existe en otro hábitat. |

### Addendum v1.1 — UI por familia (2026-11-04)

El addendum aprobado de TASK-013 corrige la presentación final del modal de granja:

- Las tabs son **por familia**, no por integrante. Cada tab representa una familia del hábitat
  y, al pulsarla, se muestra el panel con **nido, refugio, complementos y plantaciones** de esa
  familia (`familiaActiva` en `resources/views/habitats/show.blade.php`).
- **No hay tablist interno por integrante ni panel de especie visible.** El detalle de especie
  (`GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`), su presentador y la caché
  JS `detallePokemonCache` siguen implementados y disponibles para una fase posterior, pero
  **no están conectados a la UI actual**.
- El modal de bayas, el catálogo de las 36 y el resto de decisiones D1–D9 no cambian.

Este addendum **no revierte** D6/D7/D8: el endpoint y su contrato se conservan; solo se
contextualiza que la UI vigente no los consume.

### D1 — Clasificación única en `Shared`

`GrupoBaya` (`src/Shared/Domain/Items/GrupoBaya.php`) es un enum de cadena con `label()` y
`orden()`:

| Grupo | Valor | Fuente en `CatalogoItems` | Nº |
|---|---|---|---|
| Medicinas | `medicina` | `BAYAS_BASE` | 6 |
| Mejoras | `mejora` | `BAYAS_RARAS` | 7 |
| Reductores | `reductor` | `BAYAS_ESTADISTICA` | 5 |
| Resistencias | `resistencia` | resto (bayas de tipo) | 18 |

`CatalogoItems::grupoDeBaya(ClaveBaya): GrupoBaya` es la única fuente de verdad y reutiliza las
listas privadas existentes. `BayaGranja` incorpora `GrupoBaya $grupo` y su `toArray()` añade
`grupo` y `grupo_nombre` de forma aditiva.

### D2 — Catálogo con stock 0 fuera del payload de granja

`InventarioGranja::catalogoBayas(int $userId)` recorre las 36 `ClaveBaya::cases()`, lee los
saldos en una sola llamada y devuelve cantidad 0 cuando no hay fila. `resumenBayas()` sigue
filtrando cantidad `<= 0` y sigue siendo el que alimenta `GranjaDetalle`.

`ObtenerCatalogoBayas::handle(int $userId, int $habitatId)` valida el hábitat con
`AsegurarGranja::para()` y devuelve la colección. `GranjaController::bayas()` responde
`{ "bayas": [...] }` en `GET /api/habitats/{habitat}/farm/berries`.

### D3/D4 — Modal separado y plantado directo

- La vista abre el selector con `abrirSelectorBaya(chainId, slot)`, que guarda el hueco y carga el
  catálogo la primera vez (`bayasCatalogoCargado`).
- `cerrarCapaSuperior()` comprueba `showBayaModal`: si está abierto lo cierra y, si no, cierra la
  granja. El modal de bayas no registra un `@keydown.escape.window` propio.
- El buscador normaliza mayúsculas y diacríticos; los chips filtran por `grupo`; la lista ordena
  con criterio estable las de `cantidad > 0` primero.
- Plantar reutiliza el endpoint existente, refresca `granja` con la respuesta completa y
  sincroniza las cantidades del catálogo local; no hace una petición adicional.

### D5 — Orden de miembros por etapa (contrato del detalle)

`MiembroCadena` añade `parentId`; `ResolvedorCadenasEvolutivas` lo rellena desde
`FamiliaEvolutiva::porId(...)->parentId`. `DTOPokemonFamilia` añade `stage` y `parentId` con
defaults compatibles y `toArray()` expone `stage`, `parent_id` y `species_id`. La vista construye
`[base, ...evolutions]`, deduplica por id y ordena por `stage`, `species_id`, `id`.

`base` sigue siendo el miembro de menor `species_id` por compatibilidad con el contrato anterior;
la tab inicial es la de menor `stage`.

Este orden alimenta la lista de miembros que consumiría el detalle de especie. La UI actual de
TASK-013 muestra **una tab por familia**, así que no hay tabs de integrante ni orden visible entre
ellos; el cálculo se conserva para cuando se conecte el detalle.

### D6/D7/D8 — Detalle de especie disponible (diferido en UI)

`GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}` lo resuelve
`DetallePokemonFamiliaQuery::obtener(int $habitatId, int $chainId, int $pokemonId, int $userId)`:

1. Comprueba la cadena asignada al hábitat (`pokemon_habitat` join `pokemon.evolution_chain_id`).
2. Carga el `Pokemon` por `id` + `evolution_chain_id = chain`.
3. Lee `pokedex` del usuario para `visto`/`atrapado`.
4. Delega en `PresentadorDetalleEspecie::para($pokemon, $visto, $atrapado)`.

`PresentadorDetalleEspecie` (`app/Support/`) carga `abilities` y `moves`, compone el shape de
`PresentadorDetallePokemon::para()` y añade:

- `abilities[]`: `id`, `name`, `effect`, `slot`, `source` (ordenadas por `slot`).
- `moves[]`: `slug`, `name`, `type_id`, `tipo_nombre`, `tipo_slug`, `category`,
  `category_label`, `base_power`, `accuracy`, `level`, `source` (level-up primero, luego nivel y
  nombre).
- `species`: `species_id`, `evolution_chain_id` y metadatos no nulos (`capture_rate`,
  `base_experience`, `height`, `weight`, `hatch`).

`PresentadorDetallePokemon::para()` acepta overrides opcionales `?bool $visto`/`?bool $atrapado`;
sin ellos conserva el comportamiento anterior, por lo que `GET /datagrid/pokemon/{id}/detalle`
no gana claves nuevas.

Un Pokémon no visto devuelve el detalle completo con `visto=false`; la respuesta no se bloquea ni
se degrada.

**Estado actual (addendum v1.1):** el endpoint, `PresentadorDetalleEspecie` y la caché de cliente
por `chainId/pokemonId` existen y están verificados, pero **la UI de tabs por familia no los
invoca**. Quedan como capacidad disponible para una fase posterior; no deben documentarse como
parte del flujo visible.

### D9 — Autorización por pertenencia

El detalle no confía en los IDs de la URL. La cadena debe estar representada en
`pokemon_habitat` para ese hábitat y el Pokémon debe pertenecer a esa misma cadena. Cualquier
combinación inválida lanza `RecursoNoExiste` (404) sin distinguir entre "no existe" y "no
pertenece".

## Alternativas consideradas

| Alternativa | Resultado | Motivo |
|---|---|---|
| Ampliar `resumenBayas()` para devolver las 36 | Descartada | Duplicaría el payload de granja en cada refresco y rompería la semántica actual (solo existencias). |
| Incrustar el detalle de especie en `GET /api/habitats/{id}/families` | Descartada | Multiplica el coste por familia y miembro aunque el usuario no abra ninguna tab. |
| Un único modal que alterne entre granja y bayas | Descartada | Complica el estado y el cierre por Escape; dos capas hermanas son más simples. |
| Abrir una ficha de Pokédex al pulsar la tab | Descartada | Rompe el contexto de la granja y obliga a salir del hábitat. |
| Ordenar solo por `species_id` | Descartada | No refleja la etapa real en familias ramificadas o con `species_id` no monotónico. |
| Confiar en el `chain` de la URL para cargar el Pokémon | Descartada | Permite leer especies de cadenas no asignadas al hábitat (IDOR). |
| Mostrar habilidades ocultas desde `ability_pokemon` | Descartada | El esquema no guarda `hidden`; inventarlo sería incorrecto. |

## Consecuencias

### Positivas

- El catálogo completo es visible, con agotadas incluidas y sin tocar el contrato de granja.
- El detalle de especie no penaliza la carga inicial de la granja: una petición al abrir el
  selector y una por Pokémon consultado, cacheada en cliente. **En la UI actual de tabs por
  familia no se invoca**, así que la carga inicial no incluye ninguna petición de detalle.
- `resumenBayas()` y `GET /datagrid/pokemon/{id}/detalle` mantienen su contrato exacto; los
  cambios en `BayaGranja`, `DTOPokemonFamilia` y `families` son claves aditivas.
- La autorización por cadena asignada + `evolution_chain_id` elimina el IDOR sin filtrar
  existencia.
- Sin migraciones: la clasificación y el catálogo son código.

### Negativas y deuda asumida

- **Una petición extra al abrir el selector** y **una por Pokémon consultado** *cuando se conecte
  el detalle*. Hoy solo aplica la del selector; la caché por `chainId/pokemonId` está implementada
  pero sin uso en la UI y no se invalida si el usuario gana o pierde estados de Pokédex en otra
  pestaña.
- **`ability_pokemon` no guarda `hidden`.** Se expone `slot`/`source` y no se inventa la bandera;
  si el esquema cambia, el presentador deberá ampliarse.
- **`stage` con fallback 3** en `ResolvedorCadenasEvolutivas` cuando la familia no resuelve la
  etapa. El orden por `species_id`/`id` mantiene el resultado determinista, pero una familia sin
  datos de etapa puede quedar después de sus evoluciones.
- **Formas regionales con cadena propia** quedan fuera del detalle salvo que su cadena esté
  asignada al hábitat (misma regla que el resto de la familia).
- **Caché de cliente sin TTL.** Mientras no se conecte el detalle no tiene efecto; cuando se use,
  un cambio de `visto`/`atrapado` no se reflejará hasta recargar la página.
- **Sin tests.** La verificación es estática y manual (36 bayas, reparto 6/7/5/18, orden de
  miembros del contrato de detalle, caché, 404 de autorización).

## Referencias

- Plan aprobado: [`TASK-013/plan.md`](../../.opencode/work/TASK-013/plan.md)
- Addendum v1.1 (UI por familia):
  [`TASK-013/plan.md` § Addendum v1.1](../../.opencode/work/TASK-013/plan.md#addendum-v11--ajuste-de-ui-aprobado-2026-11-04)
- Diseño técnico: [`TASK-013/architecture.md`](../../.opencode/work/TASK-013/architecture.md)
- Análisis previo: [`TASK-013/analysis.md`](../../.opencode/work/TASK-013/analysis.md)
- Arquitectura del proyecto: [`docs/architecture.md`](../architecture.md)
- Convención de módulos: [`docs/ddd.md`](../ddd.md)
- ADR previo (F2–F5): [`ADR-0001`](0001-inventario-compartido-f2-f5.md)
- Implementación principal:
  - `src/Shared/Domain/Items/GrupoBaya.php`
  - `src/Shared/Domain/Items/CatalogoItems.php` (`grupoDeBaya`)
  - `src/Granjas/App/InventarioGranja.php` (`catalogoBayas`)
  - `src/Granjas/App/ObtenerCatalogoBayas.php`
  - `src/Granjas/Infra/Controllers/GranjaController.php` (`bayas`)
  - `routes/granjas.php`
  - `src/Habitats/Presentation/DTOPokemonFamilia.php`
  - `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php`
  - `src/Habitats/Infra/Controllers/DetallePokemonFamiliaController.php`
  - `app/Support/PresentadorDetallePokemon.php`
  - `app/Support/PresentadorDetalleEspecie.php`
  - `routes/habitats.php`
  - `resources/views/habitats/show.blade.php`
