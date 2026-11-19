# ADR-0005: Filtros de habilidad y movimiento en la Pokédex y descripciones canónicas por `effect_key`

- **Estado:** Aceptado
- **Fecha:** 2026-11-18
- **Ámbito:** `app/Providers`, `app/Support/Pokedex`, `app/Http/Controllers`,
  `resources/views`, `database/seeders`, `storage/data`
- **Tarea:** TASK-061 — plan v1
- **Implementación:** TASK-061 (filtros y catálogo, componente e integración, seeder y
  descripciones). El detalle de coordinación vive en
  [`TASK-061/execution.md`](../../.opencode/work/TASK-061/execution.md).
- **Estado de verificación (2026-11-18):** datos y contrato de filtros comprobados con
  `DatagridService`/tinker: **287 habilidades, 287 con `effect` no vacío (0 vacías)**, **244
  descripciones** en `storage/data/abilities_descripciones.json` y segunda ejecución del seeder
  con `287 habilidades · 287 con texto · 0 sin texto · 0 actualizadas` (idempotente).
  `php artisan view:cache` y `npm run build` terminan sin errores; `composer analyse` sigue en
  35 errores de baseline. La comprobación **visual y de teclado en navegador** de `/pokedex`
  queda pendiente del desarrollador; por eso el ADR se acepta con esa salvedad y no se declara
  cerrada la validación de UI.

## Contexto

La Pokédex solo permitía filtrar por nombre, tipo, esfuerzo y estado (visto/atrapado). Para
construir equipos faltaba responder a dos preguntas que el datagrid ya podía expresar pero no
exponía: «qué especies pueden llevar esta habilidad» y «qué especies aprenden este movimiento».

En paralelo, el detalle de especie mostraba descripciones vacías: de **287 habilidades**, **228
no tenían `effect`** y solo 59 lo conservaban. La causa estaba en `AbilitySeeder`: primero
cargaba el CSV (59 filas con texto), después recorría los tres lotes JSON con
`updateOrCreate`, y esos lotes traen `"effect": ""` en sus 201 definiciones. El vacío de la
última pasada pisaba el texto del CSV. Además, `abilities.effect` es `text NOT NULL` sin
default, así que omitir la clave en un `updateOrCreate` tampoco era una salida para nombres
nuevos.

El catálogo real de combate no coincide con el oficial en **23 habilidades** con efecto
personalizado (`demoledor`, `vacio`, `guardian_mares`, `crueldad`, `escamas`, `sombra_traviesa`,
`canones`, `prediccion`, `antidoto`, `ira`, `espinas`, `veleta`, `hipnotizador`, `enredaderas`,
`doble_ataque`/`triple_ataque`/`cuadruple_ataque`, `pantallista`, `refuerzos`, `dormilon`,
`meditacion`, `comealmas`, `divide_dolor`). Copiar el texto oficial en esas filas habría
documentado un comportamiento que el juego no aplica.

Restricciones de la tarea: sin tests, sin cambios de esquema, sin dependencias nuevas, sin
persistencia de filtros en la URL y sin tocar el motor de combate, `DatagridService`,
`RelationFilter`, `Ability`, `Move` ni `PresentadorDetalleEspecie`.

## Decisión

| ID | Decisión | Motivo |
|---|---|---|
| **D1** | Registrar `ability` y `move` como `RelationFilter` por **id**, con columna **cualificada** `abilities.id` / `moves.id` | `whereHas` sobre `BelongsToMany` genera un join con la pivote, que también tiene `id`; sin cualificar, PostgreSQL falla con `column reference "id" is ambiguous`. |
| **D2** | Solo se aceptan ids numéricos positivos: un valor no numérico ignora el filtro; un id numérico inexistente devuelve 0 resultados | Comportamiento determinista, sin consultas de validación ni semántica sorpresa para el cliente. |
| **D3** | `meta.counts` sigue siendo **global**; `meta.total` y `meta.last_page` reflejan los filtros | Los contadores de cabecera son progreso del jugador (vistos/atrapados/total), no el resultado de la búsqueda. |
| **D4** | Catálogos de opciones **embebidos** en la vista, solo con valores asignados (278 habilidades y 639 movimientos), sin AJAX | 917 opciones y ~31 KB crudos (~7 KB gzip): un endpoint con búsqueda parcial añadiría latencia, autorización y estados de carga sin beneficio. |
| **D5** | **Sin migración de índices**; contingencia documentada con umbral medido | El `EXISTS` está correlacionado por `pokemon_id`, primera columna de los `unique` existentes en las pivotes; el catálogo recorre 1.995 y 63.661 filas una vez por página. |
| **D6** | Descripciones en `storage/data/abilities_descripciones.json`, indexadas por **`effect_key`** (244 claves) | La clave es el comportamiento real (`FabricaEfectos`), no el nombre comercial: un texto sirve a todos los alias y las personalizadas se describen desde el código, salvo `vacio` e `hipnotizador`, cuyo texto de CSV ya era fiel al motor. |
| **D7** | `AbilitySeeder` reescrito a **una sola pasada**: fusiona CSV, asignaciones, lotes y descripciones **antes** de escribir, y nunca escribe `effect` vacío sobre un texto existente | Elimina la causa raíz del vaciado, respeta las compuestas y hace la segunda ejecución idempotente. |
| **D8** | Componente Blade reutilizable `<x-ui.combobox>` con buscador y factoría Alpine, cerrado con `@click.outside` | Dos comboboxes con el mismo markup y comportamiento serían duplicación; el cierre propio evita depender del handler global preexistente, que no cierra dentro de la página. |

### D1–D3 — Contrato de filtros

Registro en `app/Providers/DatagridServiceProvider.php`:

```php
'ability' => new RelationFilter(
    relation: 'abilities',
    column: 'abilities.id',
    map: fn (mixed $value): ?int => $this->idPositivo($value),
),
'move' => new RelationFilter(
    relation: 'moves',
    column: 'moves.id',
    map: fn (mixed $value): ?int => $this->idPositivo($value),
),
```

`idPositivo()` devuelve `null` si el valor no es numérico o no es mayor que cero; el mapeo nulo
descarta el filtro antes del `whereIn`. Cuando el id no existe, el `EXISTS` es falso y el
resultado es 0 filas.

| Parámetro | Tipo | Valores | Mapeo | Inválido |
|---|---|---|---|---|
| `filter[ability]` | string/int | `abilities.id` de una habilidad asignada | `idPositivo()` → `int` | no numérico ⇒ filtro ignorado; inexistente ⇒ 0 resultados |
| `filter[move]` | string/int | `moves.id` de cualquier fuente del learnset | `idPositivo()` → `int` | idem |

Por qué la columna va cualificada (trampa no evidente): para `BelongsToMany`, el `whereHas`
construye un `EXISTS` con join a la pivote:

```sql
exists (select * from "moves"
        inner join "pokemon_moves" on "moves"."id" = "pokemon_moves"."move_id"
        where "pokemon"."id" = "pokemon_moves"."pokemon_id"
          and "moves"."id" in (?))
```

Cada filtro es un `whereHas` independiente, así que se combinan con **AND** entre sí y con
`types`, `effort`, `visto`, `atrapado` y `search`. La UI es de selección única y nunca envía
listas; el `whereIn` heredado de `splitValues` aceptaría `1,2`, pero no se usa ni se bloquea.
`DatagridService` no cambió: `pokemonCounts()` sigue calculando `meta.counts` al margen de los
filtros.

### D4 y D8 — Catálogos y UI

- `app/Support/Pokedex/OpcionFiltro.php`: DTO `final readonly` con `id` y `name` que implementa
  `JsonSerializable`. Evita que un método público devuelva `list<array{id,name}>` y mantiene el
  contrato tipado hasta la serialización de vista.
- `app/Support/Pokedex/CatalogoFiltrosPokedex.php`: `habilidades()` y `movimientos()` consultan
  `Ability::whereHas('pokemons')` y `Move::whereHas('pokemons')`, ordenan por `name` en el
  backend y devuelven `Collection<int, OpcionFiltro>`. Solo se ofrecen valores asignados: toda
  opción del combobox devuelve al menos una especie.
  - **Nota de implementación:** el diseño pedía `lower(name)`, pero `orderByRaw('lower(name)')`
    dispara un falso positivo de PHPStan/Larastan que no se puede acotar sin `@phpstan-ignore`.
    Se usa `orderBy('name')`: el orden pasa a ser sensible a mayúsculas y por code point, una
    diferencia imperceptible en el combobox (cuya búsqueda normaliza para el usuario) y sin
    impacto funcional en los filtros.
- `PlayerController::pokedex()` pasa `habilidades` y `movimientos` a la vista; la serialización
  a JavaScript se hace con `@js()` en el componente, nunca con interpolación de cadenas.

| Catálogo | Opciones | Origen |
|---|---|---|
| Habilidades | 278 | `Ability::whereHas('pokemons')` |
| Movimientos | 639 | `Move::whereHas('pokemons')` |

`resources/views/components/ui/combobox.blade.php` es presentación pura: recibe
`name`, `label`, `options`, `placeholder`, `ancho` y `ayuda`; no calcula catálogo ni orden y no
conoce el estado de la página. Emite un único evento,
`combobox-seleccionado`, con
`{ name: 'ability'|'move', id: int|null, label: string|null }`, y la vista guarda solo los ids en
`abilityFilterId`/`moveFilterId`, los añade a `buildParams()` y relanza `resetAndFetch()`.
Limpiar con ✕ emite `id: null` y refetchea.

La factoría `comboboxFiltro()` vive en
`resources/views/partials/combobox-filtro-script.blade.php` como función global, por la misma
razón que `pokedexApp()`: los scripts inline se ejecutan antes del módulo diferido que arranca
Alpine. El buscador normaliza con NFD y elimina diacríticos, de modo que `electricid` encuentra
«Electricidad Estática».

Accesibilidad implementada: `aria-haspopup`, `aria-expanded`, `aria-controls`,
`role="combobox"` en el buscador, `role="listbox"`/`role="option"`, `aria-activedescendant`,
anuncio «Sin resultados» con `role="status"`, teclado ↑/↓/Enter/Esc/Tab/Home/End, devolución de
foco al trigger al cerrar y `@click.outside` para el cierre. Los comboboxes se insertan fuera
del `role="tablist"` de tipo/esfuerzo. El filtro de movimiento lleva
`title`/`aria-description` con «Incluye todos los movimientos del learnset, no solo los
aprendidos por nivel», porque el modal de detalle solo pinta movimientos `level-up`.

### D5 — Índices y rendimiento

| Tabla | Filas | Índices y constraints existentes | Acceso del filtro |
|---|---|---|---|
| `ability_pokemon` | 1.995 | PK, unique(`pokemon_id`,`slot`), FKs de `pokemon_id` y `ability_id` | nested loop por `pokemon_id` con el unique |
| `pokemon_moves` | 63.661 | PK, unique(`pokemon_id`,`slot`), unique(`pokemon_id`,`move_id`), idx(`pokemon_id`,`source`,`level`) | nested loop por `pokemon_id` con el unique |

Las FKs no crean índice en PostgreSQL; el acceso del `EXISTS` no filtra por `ability_id` ni
`move_id` de forma aislada, sino por la correlación con `pokemon_id`, que sí es la primera
columna de los `unique`.

No se crea migración. **Contingencia** si `EXPLAIN (ANALYZE, BUFFERS)` del filtro o del catálogo
supera 20 ms: añadir `index('ability_id')` en `ability_pokemon` e `index('move_id')` en
`pokemon_moves` (en producción, `CREATE INDEX CONCURRENTLY` para la tabla grande). No hay
medición de `EXPLAIN` registrada en esta tarea; el umbral es el disparador, no una comprobación
hecha.

### D6–D7 — Descripciones y seeder

Formato de `storage/data/abilities_descripciones.json` (claves ordenadas, una por línea):

```json
{
  "version": 1,
  "fuente": "BattleEffectServiceProvider::registrarHabilidades",
  "descripciones": {
    "absorcion_agua": "Es inmune a los movimientos de tipo Agua: no recibe daño y, al absorberlos, recupera un 25 % de sus PS máximos.",
    "demoledor": "Inflige un 25 % más de daño a los escudos del objetivo. No ignora habilidades defensivas."
  }
}
```

- **244 claves** en total, con este desglose:
  - **227** cubren las habilidades que estaban vacías; dos filas comparten clave
    (`Espsura`/`Espesura` → `espesura`), así que el mapa por `effect_key` las cubre sin
    ambigüedad.
  - **16** redactadas para habilidades personalizadas que hasta entonces mostraban el texto del
    CSV (`antidoto`, `comealmas`, `crueldad`, `doble_ataque`, `triple_ataque`,
    `cuadruple_ataque`, `divide_dolor`, `dormilon`, `enredaderas`, `escamas`, `guardian_mares`,
    `ira`, `meditacion`, `pantallista`, `refuerzos`, `sombra_traviesa`). El CSV describía el
    efecto oficial, no el del motor.
  - **1** para `canones`, que describe el overflow de barreras a PS igual que `demoledor`
    (`BarrerasVidaManager::desglosarDaño`).
- `vacio` e `hipnotizador` **conservan el texto del CSV**: en esos dos casos el texto ya era
  fiel al comportamiento implementado y sustituirlo no aportaba precisión.
- Las **9 habilidades compuestas huérfanas** (`Enjambre`/`Mar Llamas`/`Espesura`, etc.) no se
  tocan: conservan el texto del CSV y siguen sin asignarse, por lo que son inalcanzables en la
  UI. Evita concatenar tres descripciones sin sentido.
- El resto de personalizadas se redactó desde `BattleEffectServiceProvider` y las clases de
  `src/Battle/Domain/Effects/**` (disparador → magnitud → condición → límite), no desde el texto
  oficial. El protocolo y los lotes están en
  [`TASK-061/descripciones-lotes.md`](../../.opencode/work/TASK-061/descripciones-lotes.md).

`AbilitySeeder` construye un mapa `nombre normalizado => datos` sin tocar la base de datos y
aplica esta precedencia:

1. `effect_keys`: lote JSON si trae al menos una; si no, `CatalogoHabilidades::clavesDe()`; si
   tampoco, el nombre normalizado.
2. `effect`: descripción por `effect_key` cuando la habilidad tiene **exactamente una** clave;
   si no hay entrada, texto del CSV; si no, el `effect` actual de la base de datos; si no, `''`.
3. Las compuestas (≥2 claves) conservan el CSV.

El seeder nunca escribe vacío sobre un texto existente, informa del total, de las habilidades con
texto y de las claves de una sola habilidad sin descripción, y una segunda ejecución reporta 0
actualizadas: el mapa se construye de forma determinista y no depende del orden de ejecución.

## Alternativas descartadas

| Alternativa | Motivo del descarte |
|---|---|
| Endpoints AJAX de búsqueda parcial para los catálogos | 917 opciones no justifican dos endpoints, autorización, debounce, estados de carga y un round-trip antes de poder teclear. |
| Filtrar por nombre de habilidad/movimiento en vez de por id | El nombre es texto libre, admite alias y acentos, y obligaría a resolverlo en cada petición; el id es estable y ya viaja en el catálogo. |
| Pasar `id` sin cualificar al `RelationFilter` | PostgreSQL falla por ambigüedad entre `abilities.id`/`moves.id` y el `id` de la pivote. |
| Crear índices ahora «por si acaso» | El acceso ya está cubierto por los `unique` que empiezan por `pokemon_id`; un índice nuevo en producción tiene coste y lock sin medición que lo justifique. |
| Rellenar `effect` en los tres lotes JSON y endurecer el seeder | Los lotes son artefactos de importación: regenerarlos borra el trabajo, duplica texto entre alias y no cubre las 27 habilidades que no están en ningún lote. |
| Un texto por nombre de habilidad | Repite el mismo texto en alias (`Veleta`/`Actualizacion software`, `Regeneracion`/`Sintesis`/`Bendicion`) y permite divergencias entre filas con el mismo comportamiento. |
| Migrar tipo/esfuerzo a `@click.outside` y borrar el handler global | El handler tiene un bug preexistente (compara `closest('[x-data]')` con la raíz y nunca cierra dentro de la página), pero corregirlo amplía el alcance; los comboboxes nuevos no dependen de él. |
| Persistir los filtros en la URL | Útil, pero fuera de alcance: hoy la selección vive solo en el estado Alpine y se pierde al recargar. |

## Consecuencias

**Positivas**

- Las 287 habilidades del catálogo tienen `effect` no vacío: 244 claves cubren las que estaban
  vacías, las que conservaban texto del CSV siguen descritas y las personalizadas reflejan el
  comportamiento real del motor (`demoledor` y `canones`, incluido el overflow de barreras a
  PS). `vacio` e `hipnotizador` mantienen su texto de CSV por fidelidad.
- La Pokédex permite filtrar por habilidad (incluidas las ocultas) y por movimiento (todo el
  learnset: `level-up`, `machine`, `egg`, `tutor`, `custom`), combinables con el resto de filtros.
- No hay endpoint nuevo ni migración: los filtros son aditivos y el rollback es de código y
  datos.
- El seeder dejó de ser destructivo: una segunda ejecución no cambia nada y avisa de claves sin
  descripción.
- El combobox es reutilizable y accesible, y no arrastra el bug de cierre del handler antiguo.

**Negativas / costes**

- `/pokedex` crece ~31 KB de catálogos en el HTML (~7 KB gzip), calculado sobre el tamaño de los
  catálogos y no medido en HTTP. Es el precio de no añadir red adicional; si el tiempo de render
  supera 50 ms, la salida es cachear el catálogo e invalidarlo al sembrar.
- Una sola descripción por comportamiento: los alias comparten texto aunque el nombre comercial
  sugiera matices distintos. Es deliberado, pero exige redactar pensando en el efecto, no en el
  nombre.
- Las descripciones son una proyección del código: si cambian los multiplicadores o las
  condiciones de `BattleEffectServiceProvider`/`src/Battle`, hay que regenerarlas o quedan
  desactualizadas.
- Coherencia parcial con el modal: el filtro de movimiento cubre todo el learnset, mientras el
  detalle solo muestra `level-up`. Se mitiga con `title`/`aria-description`, no con datos.
- Durante la transición conviven dos fuentes de texto: el JSON por `effect_key` para las
  habilidades de una clave y el CSV para las 9 compuestas huérfanas.
- El filtro de movimiento recorre 63.661 filas de pivote; sin índices nuevos, el rendimiento
  depende de los `unique` existentes y de que el plan siga siendo nested loop.

## Deuda y seguimiento

- **Verificación manual pendiente:** buscador insensible a acentos, selección, limpiar con ✕,
  combinación de ambos filtros, estado vacío, teclado y cierre al hacer clic fuera en `/pokedex`.
  Hasta que el desarrollador la haga, la validación de UI no está cerrada.
- **`EXPLAIN` no medido:** el umbral de 20 ms es la condición de la contingencia D5; falta
  registrar `EXPLAIN (ANALYZE, BUFFERS)` de las dos consultas de filtro y de las dos de catálogo.
- **Convención de dropdowns desalineada:** `docs/conventions.md` describe un listener global con
  `init()`/`destroy()`; el combobox nuevo usa `@click.outside`. Conviene unificar la convención
  cuando se arregle el handler de tipo/esfuerzo.
- **Caché de catálogo:** no implementada. Añadir `Cache::rememberForever('pokedex:catalogo:filtros')`
  y `Cache::forget` al final de `AbilitySeeder` solo si la medición supera 50 ms.
- **Persistencia en URL:** los filtros se pierden al recargar. Sería el siguiente paso natural
  (`?ability=…&move=…`) y no requiere tocar el contrato del datagrid.
- **Deriva de datos:** una habilidad nueva sin `effect_key` en el JSON se queda sin texto; el
  seeder lo avisa, pero no lo impide. El chequeo SQL de cobertura sigue siendo la red de
  seguridad.

## Riesgos residuales (revisión)

Ninguno bloquea la aceptación del ADR; se registran para que no se redescubran como hallazgos
nuevos:

| ID | Riesgo | Consideración |
|---|---|---|
| **L1** | `filter[ability][]` (array) llega a `splitValues()` y puede provocar un 500 por conversión de array a string | Preexistente en `DatagridService` y común a todos los filtros; fuera del alcance de TASK-061. La UI solo envía escalares. |
| **L2** | Combinar filtros permite inferir pertenencia especie↔habilidad/movimiento de especies no avistadas | Se acepta como riesgo bajo de producto: es coherente con la exposición previa de tipos y hábitats en la misma vista. |
| **I1** | `idPositivo()` acepta `1.5` o `1e3` (es `is_numeric` + cast a `int`) | Inalcanzable desde la UI, que solo emite ids enteros del catálogo; el resultado sería un filtro válido por truncamiento, no un error. |
| **L3** | Advisories de dependencias preexistentes | Fuera de alcance: no se añadieron ni actualizaron dependencias en esta tarea. |

## Referencias

- Plan: [`TASK-061/plan.md`](../../.opencode/work/TASK-061/plan.md)
- Diseño: [`TASK-061/architecture.md`](../../.opencode/work/TASK-061/architecture.md)
- Análisis: [`TASK-061/analysis.md`](../../.opencode/work/TASK-061/analysis.md)
- Lotes de descripciones: [`TASK-061/descripciones-lotes.md`](../../.opencode/work/TASK-061/descripciones-lotes.md)
- Coordinación y estado: [`TASK-061/execution.md`](../../.opencode/work/TASK-061/execution.md)
- Convenciones del proyecto: [`docs/conventions.md`](../conventions.md)
- Código: `app/Providers/DatagridServiceProvider.php`,
  `app/Support/Pokedex/OpcionFiltro.php`, `app/Support/Pokedex/CatalogoFiltrosPokedex.php`,
  `app/Http/Controllers/PlayerController.php`,
  `resources/views/components/ui/combobox.blade.php`,
  `resources/views/partials/combobox-filtro-script.blade.php`,
  `resources/views/pokedex/index.blade.php`, `database/seeders/AbilitySeeder.php`,
  `storage/data/abilities_descripciones.json`.
