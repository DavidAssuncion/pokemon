# TASK-061 — Arquitectura

> Resultado de `architect` (sesión `ses_e104e4274ffe3y1GzxGFi6P6dm`). Se reproduce sin alterar
> sus conclusiones. El plan adapta únicamente la propiedad de las descripciones y el DTO de
> catálogo para cumplir `.ai/rules/arrays-dto-collections.md`; el resto del diseño se mantiene.

## 0. Decisiones (resumen ejecutable)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Dos `RelationFilter` nuevos `ability` y `move` en el registro `pokemon`, columna **cualificada** `abilities.id` / `moves.id` | `whereHas` sobre belongsToMany genera join con la pivote (que también tiene `id`) → `id` sin cualificar es **ambiguo en Postgres** |
| D2 | Valores = **solo id numérico**; no numérico ⇒ ignorado; numérico inexistente ⇒ 0 resultados | Determinista, sin queries de validación, sin semántica sorpresa |
| D3 | `meta.counts` **sin cambios** (global) | Semántica de progreso del jugador; `meta.total` sí refleja filtros |
| D4 | Catálogos **embebidos** (`@js`), no AJAX | ~31 KB crudos / ~7 KB gzip; 287 y 673 filas; cero latencia y cero endpoint nuevo |
| D5 | Solo se ofrecen valores **asignados** (278 habilidades, 639 movimientos) | Toda opción del combobox devuelve ≥1 especie; "todo el learnset" e "incluidas ocultas" quedan cubiertos por el pivote completo |
| D6 | **Sin migración de índices** en esta tarea (contingencia definida) | El `EXISTS` está correlacionado por `pokemon_id`, y los unique existentes cubren ese acceso |
| D7 | Descripciones en **`storage/data/abilities_descripciones.json` por `effect_key`**, aplicado al final y con el seeder reescrito a una sola pasada sin escribir vacío | Cubre alias con un solo texto, respeta las 23 personalizadas y elimina la causa raíz del vaciado |
| D8 | Componente Blade reutilizable `<x-ui.combobox>` + factoría Alpine global en partial | 4 bloques idénticos serían duplicación >5 líneas (regla `arrays-dto-collections.md`) |
| D9 | `PresentadorDetalleEspecie`, `Ability`, `Move`, `DatagridService` **sin cambios** | Ya exponen `effect` y ya soportan `whereHas` |

## 1. Contrato de filtros

### 1.1 Registro en `app/Providers/DatagridServiceProvider.php`

Insertar en `relationFilters` del `pokemon` (después de `effort`, líneas 53-61):

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

Helper privado (junto a `statId`/`tipoId`):

```php
private function idPositivo(mixed $value): ?int
{
    if (! is_numeric($value)) {
        return null;
    }
    $id = (int) $value;
    return $id > 0 ? $id : null;
}
```

**Por qué la columna va cualificada** (trampa no evidente): `applyRelationFilter()` hace
`$q->getQuery()->whereIn($filter->column, $mapped)` dentro del `whereHas`. Para
`BelongsToMany`, Eloquent construye:

```sql
exists (select * from "moves"
        inner join "pokemon_moves" on "moves"."id" = "pokemon_moves"."move_id"
        where "pokemon"."id" = "pokemon_moves"."pokemon_id"
          and "moves"."id" in (?))
```

Si se pasara `'id'`, Postgres falla con `column reference "id" is ambiguous` (ambas tablas
tienen `id`). No se usa `qualifyColumn` en esta ruta, así que la cualificación es
responsabilidad del registro.

`Pokemon::abilities(): BelongsToMany` y `Pokemon::moves(): BelongsToMany` ya existen
(`app/Models/Pokemon.php:57-77`). Las constraints de orden de la relación (`orderByPivot`)
**no** se arrastran al `EXISTS` (Eloquent solo fusiona `wheres`), así que no hay `ORDER BY`
inútil — *no verificado en ejecución, riesgo bajo*.

### 1.2 Contrato HTTP

| Parámetro | Tipo | Valores | Mapeo | Inválido |
|---|---|---|---|---|
| `filter[ability]` | string/int | `abilities.id` de una habilidad asignada | `idPositivo` → `int` | no numérico ⇒ filtro ignorado (null descartado); numérico inexistente ⇒ `EXISTS` falso ⇒ 0 filas |
| `filter[move]` | string/int | `moves.id` de un movimiento del learnset | idem | idem |

- Los parámetros no registrados se siguen ignorando (`applyFilters` → whitelist).
- `filter[ability]=1,2` produciría `whereIn` (OR) por herencia de `splitValues`; la UI
  **nunca** lo envía (selección única). No se bloquea: es comportamiento heredado y no rompe
  nada.
- Combinación: cada clave es un `whereHas` independiente ⇒ **AND** entre `ability`, `move`,
  `types`, `effort`, `visto`, `atrapado` y `search` (`LIKE` sobre `pokemon.name`).
- `meta.counts`: **global**, calculado por `pokemonCounts()` e independiente de filtros
  (línea 70 de `DatagridService`). Sin cambios. `meta.total` y `meta.last_page` sí reflejan
  los filtros (paginador).
- Sin sesión: `baseQuery` ya deja `visto/atrapado` en NULL≡false; `filter[visto]=1` sigue
  devolviendo 0 filas. Comportamiento actual intacto.

### 1.3 Índices: análisis y decisión

Acceso real del filtro (correlado por `pokemon_id`):

| Tabla | Filas | Índices actuales | ¿Cubre el `EXISTS`? |
|---|---|---|---|
| `ability_pokemon` | 1.995 | PK, unique(`pokemon_id`,`slot`), FK `pokemon_id`, FK `ability_id` | Sí: nested loop 120 filas × unique(`pokemon_id`,`slot`) |
| `pokemon_moves` | 63.661 | PK, unique(`pokemon_id`,`slot`), unique(`pokemon_id`,`move_id`), idx(`pokemon_id`,`source`,`level`) | Sí: nested loop 120 × unique(`pokemon_id`,`move_id`) |

**Decisión: no crear índices ni migración.** La columna `ability_id`/`move_id` sola no
participa en la correlación; Postgres no crea índices para FKs, pero el acceso por
`pokemon_id` (primera columna de los unique) es el que usa el plan. La única consulta con
full scan es el catálogo (`Ability::whereHas('pokemons')`, 1.995 filas;
`Move::whereHas('pokemons')`, 63.661 filas ≈ 2-4 ms), una vez por carga de página.

**Contingencia** (solo si `EXPLAIN (ANALYZE, BUFFERS)` del filtro o del catálogo supera
20 ms): migración
`database/migrations/2026_11_18_000001_add_catalog_indexes_to_pokemon_pivots.php`,
responsable `database`, con `$table->index('move_id')` en `pokemon_moves` y
`$table->index('ability_id')` en `ability_pokemon` (en producción,
`CREATE INDEX CONCURRENTLY` fuera de transacción para `pokemon_moves`). No se ejecuta en esta
tarea.

## 2. Catálogos (embebido)

### 2.1 Clase de catálogo — `app/Support/CatalogosFiltrosPokedex.php` (crear)

```php
declare(strict_types=1);

namespace App\Support;

use App\Models\Ability;
use App\Models\Move;

/**
 * Opciones de los filtros de la Pokédex. El backend es dueño del orden
 * (`frontend-minimal-logic.md`): la vista solo filtra en cliente por texto.
 */
final class CatalogosFiltrosPokedex
{
    /** @return list<array{id: int, name: string}> */
    public static function habilidades(): array;   // Ability::query()->whereHas('pokemons')->orderByRaw('lower(name)')->get(['id','name'])
    /** @return list<array{id: int, name: string}> */
    public static function movimientos(): array;   // Move::query()->whereHas('pokemons')->orderByRaw('lower(name)')->get(['id','name'])
}
```

- `whereHas('pokemons')` = solo asignados (278 / 639). Excluye las 9 habilidades huérfanas
  compuestas y los 34 movimientos no asignados.
- Orden `lower(name)` (determinista; la imperfección con acentos es menor y el buscador del
  combobox es insensible a acentos).
- Forma `{id,name}`: payload de presentación serializado en la frontera de vista (excepción
  tolerada de `arrays-dto-collections.md`); no cruza capas.

### 2.2 Entrega — `PlayerController::pokedex()`

```php
return view('pokedex.index', [
    // ...
    'habilidades' => CatalogosFiltrosPokedex::habilidades(),
    'movimientos' => CatalogosFiltrosPokedex::movimientos(),
]);
```

### 2.3 Tamaño y caché (medido sobre `storage/data`)

| Catálogo | Opciones | JSON |
|---|---|---|
| Habilidades | 278 | ≈ 9,5 KB |
| Movimientos | 639 | ≈ 21,7 KB |
| **Total** | | **≈ 31 KB crudos (~7 KB gzip)** |

Coste: 2 consultas por carga de página (~1-4 ms estimados), sin medir. **Sin caché**: añadir
`Cache::remember` obliga a invalidar tras cada `db:seed` (riesgo de textos viejos) a cambio
de milisegundos. Si la medición supera 50 ms, `Cache::rememberForever('pokedex:catalogo:filtros')`
+ `Cache::forget` al final de `AbilitySeeder`.

### 2.4 Alternativa descartada (AJAX)

`/datagrid/ability` y `/datagrid/move` con búsqueda parcial: 2 endpoints, 2 entradas de
registry, autorización, debounce, estados de carga y error, y un round-trip antes de poder
teclear. Para 917 opciones totales no aporta nada. Se descarta.

## 3. UI

### 3.1 Componente — `resources/views/components/ui/combobox.blade.php` (crear)

```blade
@props([
    'name',                        // 'ability' | 'move' (viaja en el evento)
    'label',                       // 'Habilidad' | 'Movimiento'
    'options' => [],               // list<array{id:int,name:string}>
    'placeholder' => 'Buscar…',
    'icono' => null,               // path SVG `d`; null = sin icono
    'ancho' => 'w-56',
])
```

Estructura (sin estado de selección en el padre salvo el id):

```html
<div class="relative"
     x-data="comboboxFiltro(@js($options), @js($name), @js($label))"
     @click.outside="cerrar(false)"
     @keydown.escape.stop="cerrar(true)">

  <button type="button" x-ref="trigger" @click="alternar()"
          aria-haspopup="listbox" :aria-expanded="abierto"
          aria-controls="{{ $baseId }}-panel" aria-label="{{ $label }}">
     <span x-text="seleccion ? seleccion.name : @js($label)"></span>
     <span x-show="seleccion" @click.stop="limpiar()" role="button" tabindex="0"
           @keydown.enter.prevent.stop="limpiar()"
           aria-label="Quitar filtro de {{ mb_strtolower($label) }}">✕</span>
  </button>

  <div x-show="abierto" x-transition x-ref="panel" id="{{ $baseId }}-panel"
       class="absolute left-0 top-full mt-1 z-40 ... {{ $ancho }}">
      <input type="search" x-ref="buscador" x-model="consulta" @input="resaltado = 0"
             role="combobox" aria-autocomplete="list" aria-expanded="true"
             aria-controls="{{ $baseId }}-listbox"
             :aria-activedescendant="opcionActivaId"
             aria-label="Buscar {{ mb_strtolower($label) }}"
             @keydown.down.prevent="mover(1)" @keydown.up.prevent="mover(-1)"
             @keydown.enter.prevent="confirmar()" @keydown.home.prevent="resaltado = 0"
             @keydown.end.prevent="resaltado = filtradas.length - 1"
             @keydown.tab="cerrar(false)">

      <ul role="listbox" id="{{ $baseId }}-listbox" aria-label="{{ $label }}">
        <template x-for="(opcion, i) in filtradas" :key="opcion.id">
          <li role="option" :id="'{{ $baseId }}-op-' + opcion.id"
              :aria-selected="seleccion && seleccion.id === opcion.id"
              :class="i === resaltado ? 'bg-surface-sunken text-primary-ink' : 'text-ink-muted'"
              @click="elegir(opcion)" @mousemove="resaltado = i"
              x-text="opcion.name"></li>
        </template>
      </ul>

      <p x-show="filtradas.length === 0" role="status">Sin resultados</p>
  </div>
</div>
```

### 3.2 Factoría Alpine — `resources/views/partials/combobox-filtro-script.blade.php` (crear)

Se incluye con `@include` en el `@push('scripts')` de la Pokédex (mismo patrón que
`tipos-badge-script`). Se declara como **función global** (`function comboboxFiltro(...)`),
por la misma razón que `pokedexApp()`: los scripts inline del body se ejecutan antes del
módulo diferido que arranca Alpine (`resources/js/bootstrap.js` → `Livewire.start()`).

```js
function comboboxFiltro(opciones, nombre, etiqueta) {
    return {
        nombre, etiqueta, opciones: Array.isArray(opciones) ? opciones : [],
        abierto: false, consulta: '', seleccion: null, resaltado: 0,

        get filtradas() { /* normalizar(consulta) vacío → opciones; si no, includes */ },
        get opcionActivaId() { /* null si !abierto o filtradas vacío */ },

        normalizar(texto) {  // insensible a acentos y mayúsculas
            return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        },
        abrir() { /* abierto = true; resaltado 0; $nextTick(() => $refs.buscador.focus()) */ },
        cerrar(devolverFoco) { /* abierto = false; consulta = ''; si devolverFoco → $refs.trigger.focus() */ },
        alternar() { /* abierto ? cerrar(true) : abrir() */ },
        mover(delta) { /* clamp sobre filtradas.length - 1 */ },
        confirmar() { /* elegir(filtradas[resaltado]) */ },
        elegir(opcion) { /* seleccion = opcion; $dispatch('combobox-seleccionado', { name: this.nombre, id: opcion.id, label: opcion.name }); cerrar(true) */ },
        limpiar() { /* seleccion = null; $dispatch('combobox-seleccionado', { name, id: null, label: null }); resetAndFetch lo hace el padre */ },
    };
}
```

Contrato del evento (única interfaz componente ↔ página):

```ts
'combobox-seleccionado' → detail: { name: string, id: number|null, label: string|null }
```

### 3.3 Integración en `resources/views/pokedex/index.blade.php`

| Punto | Línea actual | Cambio |
|---|---|---|
| Raíz | 6 | `@combobox-seleccionado.window="onComboboxSeleccionado($event)"` |
| Inserción UI | entre 149 (`</div>` del `role="tablist"`) y 150 (buscador) | nuevo `<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtros de habilidad y movimiento">` con los dos `<x-ui.combobox>` |
| Include script | 599 | `@include('partials.combobox-filtro-script')` |
| Estado | tras 610 | `abilityFilterId: null, moveFilterId: null,` |
| `emptyMessage` | 628 | `if (this.searchQuery.trim() || this.typeFilter \|\| this.effortFilter \|\| this.abilityFilterId \|\| this.moveFilterId)` |
| Handler | tras 905 | `onComboboxSeleccionado(event)` (asigna `abilityFilterId`/`moveFilterId` y llama `resetAndFetch()`) |
| `buildParams` | tras 795 | `if (this.abilityFilterId) params.set('filter[ability]', String(this.abilityFilterId));` y análogo `move` |

Uso:

```blade
<x-ui.combobox name="ability" label="Habilidad" :options="$habilidades" />
<x-ui.combobox name="move" label="Movimiento" :options="$movimientos" placeholder="Buscar movimiento…" />
```

Notas de diseño:

- **Cierre**: `@click.outside` de Alpine 3.16 (instalada) en lugar del
  `document.addEventListener` existente. El handler actual (líneas 747-753) compara
  `closest('[x-data]')` con el target y, al existir la raíz `x-data`, nunca cierra dentro de
  la página (**bug preexistente**). No se toca para no ampliar el diff; los comboboxes nuevos
  no dependen de él. *Opcional aprobable aparte*: migrar tipo/esfuerzo a `@click.outside` y
  eliminar `clickHandler`/`destroy`.
- **x-cloak**: no necesario (panel cerrado por `x-show` inicial).
- **ARIA**: se sacan los comboboxes del `role="tablist"` (que solo debe contener `role="tab"`),
  evitando empeorar una violación preexistente de tipo/esfuerzo.
- **Etiqueta del filtro de movimiento**: `title`/`aria-description` con «Incluye todos los
  movimientos del learnset, no solo los aprendidos por nivel» para evitar la incoherencia con
  el modal (que solo pinta `level-up`).
- **Selección única e independiente**: cada combobox tiene su propio `seleccion`; el padre
  guarda solo ids. No hay exclusión mutua entre habilidad y movimiento.

## 4. Descripciones y datos

### 4.1 Alternativas

**(a) Rellenar `effect` en los 3 JSON + cubrir 27 + endurecer seeder**

- Pro: un solo artefacto, el texto vive junto a `effect_keys`.
- Contra: los JSON son artefactos de importación (`identifier` de PokeAPI, lotes); regenerarlos
  borra el trabajo. Las 27 restantes no tienen JSON y obligarían a inventar un cuarto fichero
  o a editar el CSV de origen (dato raspado). Duplica texto entre alias
  (`Veleta`/`Actualizacion software`, `Regeneracion`/`Sintesis`/`Bendicion`) y permite
  divergencias.

**(b) `storage/data/abilities_descripciones.json` por `effect_key`, aplicado al final** ←
**elegida**

- Pro: clave = comportamiento real (`FabricaEfectos`), no nombre comercial ⇒ un texto sirve a
  todos los alias y las 23 personalizadas se describen desde sus argumentos, no desde el
  oficial. Idempotente por construcción, índice de cobertura trivial.
- Contra: dos fuentes de texto durante la transición (CSV para las 9 huérfanas). Mitigado: el
  mapa solo aplica a habilidades con **una** clave; las compuestas conservan el CSV.

### 4.2 Formato

`storage/data/abilities_descripciones.json` (claves ordenadas alfabéticamente, una por línea):

```json
{
  "version": 1,
  "fuente": "BattleEffectServiceProvider::registrarHabilidades",
  "descripciones": {
    "absorcion_agua": "Inmune a los movimientos de tipo Agua; al alcanzarlo uno de daño recupera un 25 % de sus PS máximos.",
    "demoledor": "Inflige un 25 % más de daño a los escudos del objetivo. No ignora habilidades defensivas.",
    "escamas": "Recibe un 12,5 % menos de daño mientras tiene los PS al máximo.",
    "veleta": "Al inicio de cada turno sube una estadística aleatoria un 25 % (1 etapa)."
  }
}
```

### 4.3 Seeder — reescritura a una sola pasada (`database/seeders/AbilitySeeder.php`)

Causa raíz actual: `updateOrCreate` de los lotes escribe `effect=''`. Además,
`abilities.effect` es `text NOT NULL` sin default
(`2026_02_24_171454_create_abilities_table.php:18`), así que **no basta con omitir la clave**
en el `updateOrCreate`: un nombre nuevo del JSON fallaría al insertar. Por eso la fusión se
hace antes de escribir.

```php
public function run(): void
{
    $csv        = $this->leerCsv(storage_path('data/poke skills - habilidades.csv'));
    $asignados  = $this->leerCsv(storage_path('data/poke habilidades.csv'));
    $lotes      = $this->definicionesDeLotes();          // name => ['effect_keys' => list<string>]
    $textos     = $this->descripciones();                // effect_key => string (lanza si falta/está corrupto)

    $fusion = [];                                        // name => ['effect'=>string,'effect_keys'=>list<string>]
    // 1) CSV: nombre => efecto (fuente de respaldo, incluidas las 9 compuestas)
    // 2) asignaciones: placeholders (effect '', effect_keys por CatalogoHabilidades)
    // 3) lotes: sobrescriben SOLO effect_keys (nunca el texto)
    // 4) effect final = (count(keys) === 1 && isset(textos[keys[0]])) ? textos[keys[0]] : (csv ?? '')

    foreach ($fusion as $nombre => $datos) { Ability::updateOrCreate(['name' => $nombre], $datos); }

    // Resumen: total, actualizadas, sin texto, claves sin descripción
}
```

Reglas de precedencia:

1. `effect_keys`: lote JSON si trae ≥1; si no, `CatalogoHabilidades::clavesDe($nombre)`; si
   tampoco, `[NormalizadorNombre::normalizar($nombre)]` (igual que hoy).
2. `effect`: **descripciones** si la habilidad tiene exactamente **una** clave y hay entrada;
   si no, texto del CSV; si no, `''`. Nunca se escribe vacío sobre un texto existente.
3. **Compuestas (≥2 claves)**: no se tocan; conservan el CSV. Esto cubre las 9 filas huérfanas
   (`Enjambre/Mar Llamas/Espesura`, etc.) y evita concatenar tres descripciones sin sentido.
   Siguen sin asignarse y son inalcanzables en la UI.
4. Idempotencia: segunda ejecución actualiza 0 filas.

Salida del seeder (verificación sin tests):

```text
287 habilidades · 228 con descripción aplicada · 0 sin texto · 0 claves sin descripción
```

### 4.4 Cobertura

- 228 habilidades vacías, **todas de una sola clave** ⇒ 227 claves distintas (dos filas
  comparten clave: `Espsura`/`Espesura` → `espesura`).
- El mapa debe tener **227 entradas**, de las cuales 50 reutilizan/auditan el texto del CSV y
  177 son nuevas.
- Las 23 personalizadas son lote prioritario: `demoledor, vacio, guardian_mares, crueldad,
  escamas, sombra_traviesa, canones, prediccion, antidoto, ira, espinas, veleta, hipnotizador,
  enredaderas, doble/triple/cuadruple_ataque, pantallista, refuerzos, dormilon, meditacion,
  comealmas, divide_dolor`.

### 4.5 Protocolo de redacción

Artefacto de coordinación `.opencode/work/TASK-061/descripciones.md`, generado por `backend`
a partir de `BattleEffectServiceProvider.php:139-487` + clases:

| effect_key | clase(s) y args | comportamiento observable | texto |
|---|---|---|---|
| `absorcion_agua` | `EfectoInmunidadTipo(AGUA, 0.25)` | inmune; cura 25 % | … |
| `demoledor` | `EfectoModificadorDanioEscudo(1.25)` | +25 % a escudos | … |
| `ensañamiento` | `EfectoMarcador` + `ManejadorCritico.php:35-41` | crítico garantizado vs envenenado | … |

Reglas de estilo y fidelidad:

- Fuente = código. Prohibido copiar el texto oficial en las 23 personalizadas.
- Estructura: disparador → magnitud → condición → límite. Números siempre explícitos (`25 %`,
  `1 etapa`, `1/8`).
- Español neutro, sin sujeto elidido ambiguo («Aumenta…», «Es inmune…»).
- Longitud objetivo **120-240 caracteres** (la vista usa `line-clamp-3` + `title`): el núcleo
  en la primera frase.
- Para los 28 marcadores (`EfectoMarcador`) hay que localizar el punto de consumo
  (`grep -rn "tieneEfecto('clave'" src/Battle`) antes de redactar: el provider no describe
  nada.

Lotes (≈45 claves cada uno, secuenciales para evitar conflictos en el JSON):

1. Personalizadas/escudos/STAB (23 + ~20).
2. Inmunidades, absorciones, redirecciones.
3. Clima, multiplicadores de estadística, invocadores.
4. Curación y daño de fin de turno, drenaje.
5. Contacto/estados.
6. Pasivas, reacciones, marcadores.
7. Resto.

## 5. Archivos y responsables

| # | Archivo | Acción | Responsable |
|---|---|---|---|
| 1 | `app/Providers/DatagridServiceProvider.php` | modificar (`ability`, `move`, `idPositivo`) | backend |
| 2 | `app/Support/CatalogosFiltrosPokedex.php` | crear | backend |
| 3 | `app/Http/Controllers/PlayerController.php` | modificar (`pokedex()`) | backend |
| 4 | `resources/views/components/ui/combobox.blade.php` | crear | frontend |
| 5 | `resources/views/partials/combobox-filtro-script.blade.php` | crear | frontend |
| 6 | `resources/views/pokedex/index.blade.php` | modificar (una sola vez) | frontend |
| 7 | `storage/data/abilities_descripciones.json` | crear (227 entradas) | backend (lotes secuenciales) |
| 8 | `database/seeders/AbilitySeeder.php` | modificar (una pasada) | backend |
| 9 | `.opencode/work/TASK-061/descripciones.md` | crear (protocolo y cobertura) | backend |
| — | `database/migrations/*` | **no** (contingencia D1.3) | database |
| — | `app/Support/PresentadorDetalleEspecie.php` | **no** | — |

Sin cambios: `app/Datagrid/DatagridService.php`, `app/Datagrid/RelationFilter.php`,
`app/Models/Ability.php`, `app/Models/Move.php`, `src/Battle/**`, los 3 JSON de lotes, el CSV,
`tests/**`.

## 6. Secuencia

1. **B1 (backend)**: #1 → verificación con `GET /datagrid/pokemon?filter[ability]=…` por curl
   autenticado o `php artisan tinker` con `DatagridService::list`.
2. **B2 (backend)**: #2 + #3. Requiere #1 registrado para que el contrato sea coherente, pero
   no lo bloquea técnicamente.
3. **F1 (frontend)**: #4 + #5 (archivos nuevos, sin dependencia de B2 más allá de los nombres
   de props).
4. **F2 (frontend)**: #6 (archivo compartido; **no** en paralelo con otra tarea sobre la misma
   vista).
5. **D1 (backend)**: #8 con el mapa vacío tolerado (el seeder arranca sin entradas y avisa).
   Debe ir **antes** de #7 para que el JSON no se aplique con el seeder viejo.
6. **D2 (backend)**: #9 y #7 por lotes; tras cada lote:
   `php artisan db:seed --class=AbilitySeeder` + consulta de cobertura.
7. **R1 (reviewer)**: análisis estático y criterios de aceptación.

Dependencias duras: #6 depende de #4/#5; #7 depende de #8; #2/#3 dependen de #1 para que el
filtro exista cuando el catálogo ya se pinta.

## 7. Riesgos y mitigaciones

| Riesgo | Sev. | Mitigación |
|---|---|---|
| `id` ambiguo en el `EXISTS` | alto | Columna cualificada `abilities.id` / `moves.id` + prueba manual del endpoint |
| Reintroducir el vaciado de `effect` | alto | Seeder de una pasada; `effect` siempre con valor; resumen «0 sin texto»; re-seed idempotente verificado |
| Descripciones no fieles (copiar oficial) | alto | Protocolo desde provider/clases; lote 1 con validación de `backend`; `reviewer` muestrea 10 claves |
| Textos > 240 caracteres truncados por `line-clamp-3` | medio | Límite en el protocolo; `title` muestra el texto completo |
| Filtro de movimiento vs modal (`level-up`) | medio | `title` explicativo en el combobox + nota en el informe final |
| Payload +31 KB en `/pokedex` | bajo | gzip ~7 KB; medir `Content-Length` antes/después |
| Combobox atrapado por el `clickHandler` roto | bajo | `@click.outside` propio; handler existente intacto |
| Regresión de `counts` | bajo | No se toca `pokemonCounts`; criterio de aceptación comparativo |
| Migración de índices innecesaria / lock | bajo | No se crea; contingencia documentada con umbral medido |
| Conflicto de merge en el JSON de 227 claves | bajo | Orden alfabético, una clave por línea, lotes secuenciales |
| Deriva futura: nueva habilidad sin descripción | bajo | El seeder avisa con «N claves sin descripción»; chequeo SQL en verificación |
| Inyección/XSS al inyectar nombres en JS | bajo | `@js()` para catálogos y props (no interpolación `'{{ }}'`) |

## 8. Verificación (sin tests)

**Estática**

- `vendor/bin/pint --dirty --format agent`.
- `php -l` sobre los PHP nuevos.
- `php artisan view:cache` (compila el componente y el partial) y `php artisan view:clear`.
- `git diff --stat` (solo los archivos de la tabla).

**Contrato de filtros**

1. `GET /datagrid/pokemon?filter[ability]=<id>` ⊂ sin filtro; 2-3 ids verificados contra
   `/datagrid/pokemon/<id>/detalle` (`abilities[].id`).
2. `filter[ability]=X&filter[move]=Y` ⊂ intersección de ambos por separado.
3. Combinado con `filter[visto]=1`, `filter[types]=Fuego`, `filter[effort]=Ataque`,
   `search=pika`.
4. `filter[ability]=abc` ⇒ idéntico a sin filtro. `filter[ability]=999999` ⇒ `total: 0`.
5. `meta.counts` idéntico antes/después de aplicar filtros.

**Rendimiento**

- `EXPLAIN (ANALYZE, BUFFERS)` de las dos consultas con filtro y de las dos de catálogo;
  umbral 20 ms.

**Datos**

```sql
-- debe devolver 0
SELECT count(*) FROM abilities a
JOIN ability_pokemon ap ON ap.ability_id = a.id
WHERE coalesce(a.effect, '') = '';

-- claves huérfanas: debe devolver 0 filas
SELECT DISTINCT (a.data->'effect_keys'->>0) AS clave
FROM abilities a JOIN ability_pokemon ap ON ap.ability_id = a.id
WHERE coalesce(a.effect, '') = '';
```

- `php artisan db:seed --class=AbilitySeeder` dos veces: la segunda debe reportar 0 actualizadas
  y `select md5(string_agg(id||effect, ',' order by id)) from abilities` idéntico.
- `select count(*) from abilities` = 287 (no se crean filas nuevas).

**UI (manual)**

- 4 controles; teclado (↑↓/Enter/Esc/Tab/Home/End); foco devuelto al trigger al cerrar con Esc;
  clic fuera cierra; buscador insensible a acentos (`electricid` → «Electricidad Estática»); ✕
  limpia y refetcha; `aria-expanded` alterna; lista vacía muestra «Sin resultados».
- `emptyMessage` menciona filtros al combinarlos.

## 9. Borrador de ADR

**Título:** Filtros de habilidad/movimiento y descripciones canónicas de habilidades en la
Pokédex
**Estado:** Propuesto (pendiente de aprobación del plan)
**Contexto:** 228 de 287 habilidades sin `effect` por sobrescritura del seeder; la Pokédex solo
filtra por tipo/esfuerzo; el dominio de combate diverge del oficial en 23 habilidades.
**Decisión:** (1) filtros como `RelationFilter` sobre `BelongsToMany` con columna cualificada e
ids; catálogos embebidos; sin índices nuevos; `counts` global. (2) descripciones keyed por
`effect_key` en JSON de almacenamiento, aplicadas por un seeder de una pasada que nunca escribe
vacío.
**Consecuencias:** +31 KB de HTML en `/pokedex`; dos fuentes de texto durante la transición
(CSV solo para 9 compuestas huérfanas); las descripciones deben regenerarse si cambian los
multiplicadores del provider; el bug preexistente de cierre de dropdowns sigue ahí (fuera de
alcance).

## 10. No verificado / preguntas abiertas

- **No verificado:** que Eloquent no arrastre `orderByPivot` al `EXISTS` (riesgo bajo; se
  comprobará con `EXPLAIN` en implementación).
- **No verificado:** tiempos reales de las 4 consultas (estimados 1-4 ms; sin medir).
- **No verificado:** tamaño exacto del payload en HTTP (calculado sobre `storage/data`:
  ~31 KB).
- **No auditadas línea a línea** las 59 descripciones del CSV; el lote 1-2 debe revisarlas al
  pasar al mapa.
- **Abierta (producto):** ¿los contadores de cabecera deben seguir siendo globales al filtrar?
  Diseño asume **sí** (semántica de progreso del jugador).
- **Abierta (opcional):** ¿migrar tipo/esfuerzo a `@click.outside` y eliminar el
  `clickHandler` roto? Fuera de alcance; no bloquea.
- **Abierta (opcional):** ¿persistir los filtros en la URL (`?ability=…`)? No implementado: la
  selección vive solo en Alpine.
