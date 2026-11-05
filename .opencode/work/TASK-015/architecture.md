# TASK-015 — Arquitectura (diseño técnico cerrado)

> Resultado del `architect` para TASK-015.

## Contexto

Tres frentes independientes sobre la Pokédex y el listado de hábitats:

1. **Listado `/pokedex`:** grid de 3/6/9 columnas con sprite de 96 px fijo dentro de una caja que crece. En pantallas anchas la tarjeta llega a ~197 px y el sprite sigue en 96 px, con hueco asimétrico (`mx-auto` no centra un `inline-block`).
2. **Listado `/habitats`:** `aspect-square` + `object-cover` recorta ~43 % del ancho de imágenes apaisadas (1,4–1,76:1).
3. **Modal de detalle:** `max-w-lg` en una columna, sin habilidades/learnset/metadatos. `PresentadorDetalleEspecie` ya los compone pero no está conectado a ninguna vista.

**Evidencia verificada en esta revisión** (consultas de solo lectura a `pgsql`, sin modificar datos):

| Dato | Valor | Consecuencia |
|---|---|---|
| Movimientos por Pokémon | media 58,9 · **máx 305** (id 151) | El learnset completo no es aceptable en el modal |
| Movimientos `level-up` | media 12,7 · **máx 26** · 0 con `level` nulo | Learnset acotado viable |
| Reparto por `source` | machine 44.947 · level-up 13.734 · tutor 3.110 · egg 1.838 | `level-up` es el subconjunto con significado de especie |
| Pokémon sin `level-up` | **3 de 1083** | Hace falta estado vacío |
| Habilidades por Pokémon | **máx 5** | Panel de habilidades acotado |
| Pokémon sin hábitat | **77** | `locations: []` es un caso real |
| Pokémon en >1 hábitat | **0** | `locations[]` no cambia el comportamiento actual |
| `PokemonStat` | expone `effort` pero el presentador solo mapea `base_stat` | El contrato actual **no** filtra EV: no los expone |

Correcciones al análisis previo (con evidencia):

- El análisis propone `wrapper-class="w-full h-full"` y a la vez advierte de no ampliar el raster de 128 px. Es contradictorio: `.sprite__img` mide `100%/100%` con `object-fit: contain`, así que `w-full h-full` **escala el asset por encima de 128 px** y lo difumina. Se descarta.
- `min-h-[3rem]` es insuficiente: `pt-1` (4) + `2xs` (14) + `xs` (16) + `2xs` (14) + `pb-2` (8) = **56 px = 3.5rem**.
- `md:grid-cols-3` con barra de stats en 1/3 deja la barra en ~50 px a 768 px de viewport. Se corrige a 3/5 + 2/5.
- "Pokémon en varios hábitats" no es un caso real hoy (0 filas). `locations[]` se mantiene como corrección de contrato, no como requisito funcional.

---

## Decisiones

### D1 — Listado de la Pokédex: densidad y sprite centrado a 128 px

```blade
{{-- pokedex/index.blade.php:179 --}}
class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 2xl:grid-cols-12 gap-2 sm:gap-3"
```

```blade
{{-- caja de imagen: el contenedor centra; el sprite se adapta con max-width:100% --}}
<div class="aspect-square relative bg-surface-sunken p-2 flex items-center justify-center">
    ...
    <x-sprite size="128" class="transition-transform group-hover:scale-110" ... />
```

- Se **elimina** `wrapper-class="mx-auto"` (inerte sobre `inline-block`). El centrado lo hace el contenedor `flex`.
- No se usa `w-full h-full` en el sprite: `max-width: 100%` de `.sprite` lo ajusta a la caja (95–129 px) sin ampliarlo nunca por encima de 128. A 1920 px la tarjeta mide ~145 px, la caja útil ~129 px: **hueco residual de 1 px**.
- `min-h-[3.5rem]` en `div.px-2.pb-2.pt-1` para que las tarjetas sin hábitat no desalineen la fila.
- Anchos resultantes: ~110 px de tarjeta por columna entre 640 y 1536 px; 145 px a 1920.
- `size="128"` y no mayor: el asset real es 128×128.

**Descartado:** `per_page`, scroll infinito, `max-w` global en `layouts/app.blade.php`, grid con `auto-fill`.

**Decisión de producto pendiente (no bloquea):** en viewports > 1920 px quedan ~26 px simétricos por lado. Opción A: aceptarlo (full-bleed actual). Opción B: `mx-auto w-full max-w-[1920px]` en el `div` raíz de `pokedex/index.blade.php` (solo esa página; deja el listado "boxed" en ultra-wide). Recomiendo B, pero es visual.

### D2 — Imágenes de hábitat: `object-contain` con fondo y padding existentes

```blade
{{-- habitats/index.blade.php:44-49 --}}
class="w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300"
```

- El contenedor ya usa `bg-surface-sunken` (mismo token que la caja de las tarjetas de Pokémon): las bandas superior/inferior se leen como marco, no como error. **No** se cambia el fondo ni se añade `blur` de relleno.
- `p-2` es necesario: sin padding la imagen toca el borde en el eje largo.
- Hover 1.03 en lugar de 1.05: con `overflow-hidden`, un 5 % (~6,5 px) se acerca al padding de 8 px y puede recortar en tarjetas pequeñas.
- `box-sizing: border-box` de preflight hace que `h-full + p-2` no desborde.

### D3 — Contrato del detalle: `locations[]` aditivo y determinista

**Aprobado** con estas condiciones:

1. `PresentadorDetallePokemon::para()` ordena `habitats` por `id` **una sola vez** y deriva de ahí `habitats`, `location`, `locations`, `habitat_id` y `habitat_name`. Nada de `first()` sin orden.
2. `location` se conserva y pasa a ser **exactamente** `locations[0] ?? null`. Un solo origen de verdad, sin divergencia posible.
3. Todos los campos existentes se mantienen con el mismo nombre y tipo. Solo se añade `locations`.
4. Cambio de comportamiento admitido y documentado: con varios hábitats, la ruta elegida pasa de "orden del SGBD" a "menor `id`". Hoy es inocuo (0 Pokémon con >1 hábitat).

Firma sin cambios:

```php
public static function para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array
```

**Alternativa mínima** (si se prefiere YAGNI estricto): ordenar igual y **no** añadir `locations`; el modal pinta un único bloque con `location`. Coste ahorrado: ~12 líneas de backend y un `x-for` de un elemento. Coste asumido: el contrato sigue sin poder expresar varias rutas. No la recomiendo porque el coste es marginal y `location` ya es una limitación latente.

### D4 — Learnset del modal: solo `level-up`, con método de presentación propio

**No** se usa `PresentadorDetalleEspecie::para()` en el datagrid: devolvería hasta 305 movimientos (~40–60 KB) y hasta 305 filas renderizadas. Se añade un punto de entrada explícito:

```php
// PresentadorDetalleEspecie
private const FUENTE_NIVEL = 'level-up';

public static function para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array;
        // sin cambios: learnset completo (consumidor /api/habitats/...)

public static function paraPokedex(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array;
        // base + abilities + species + moves filtrados a FUENTE_NIVEL

private static function componer(Pokemon $pokemon, bool $soloNivel, ?bool $visto, ?bool $atrapado): array;
private static function moves(Pokemon $pokemon, bool $soloNivel): array; // filtra antes de mapear
```

- El filtrado es **de backend** (peso de respuesta), no de UI. El orden sigue siendo `level-up` por nivel asc y nombre (ya implementado en `moveOrder`).
- El item de `moves` conserva su shape completo (`source` incluido, siempre `level-up`) para no bifurcar el esquema.
- Payload esperado: 13 filas de media, 26 máximo (~2–4 KB).
- `/api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}` **no cambia** (sigue usando `para()` con learnset completo).

### D5 — Conexión del datagrid

```php
// DatagridServiceProvider: closure detail
use App\Support\PresentadorDetalleEspecie;
...
if (! DesbloqueoPokedex::activo(request()) && ! (bool) $pokemon->getAttribute('visto')) {
    return null;
}

return PresentadorDetalleEspecie::paraPokedex($pokemon);
```

Autorización intacta: la ruta vive en `routes/datagrid.php` bajo el grupo `Route::middleware('auth')` de `routes/web.php:18`, `DatagridService::detail()` aplica el `baseQuery` con el join de `pokedex` del usuario, y el guard `visto`/`unlock` devuelve `null` → 404. Habilidades, movimientos y metadatos son catálogo público: no hay fuga de datos de otros usuarios. El listado no cambia (sigue con `with: ['types','habitats']`); el coste son 2 consultas extra solo al abrir el modal.

### D6 — Modal: contenedor, breakpoints y estructura

- Tamaño: `w-full max-w-3xl lg:max-w-4xl` (768 → 896 px). Frente a los 90vw de `/equipos` (1728 px en 1920), es contenido de lectura sin desbordar.
- Estructura de scroll: contenedor `flex max-h-[90vh] flex-col overflow-hidden`; cabecera `shrink-0`; cuerpo `min-h-0 flex-1 overflow-y-auto`. El botón de cerrar queda **siempre visible** (hoy se desplaza con el contenido y desaparece con un learnset largo).
- Rejilla superior `grid grid-cols-1 gap-4 md:grid-cols-5`: izquierda `md:col-span-3`, derecha `md:col-span-2`. Es el 2/3 + 1/3 pedido, ajustado a 60/40 porque a 1/3 exacto la barra de stats queda en ~50 px a 768 px de viewport.
- **Movimientos va a ancho completo, debajo** de la rejilla. Motivos: (a) en móvil no empuja stats/especie detrás de 26 filas, (b) una tabla de 6 columnas necesita ancho, (c) equilibra las dos columnas superiores.

```
┌──────────────────────── max-w-3xl / lg:max-w-4xl ───────────────────────┐
│ [?]  #025 Pikachu  [Eléctrico] [Atrapado]                          [×]  │ fijo
├─────────────────────────────────────────────────────────────────────────┤
│ ┌────────────── 3/5 ──────────────┐ ┌──────── 2/5 ────────┐             │
│ │ Familia evolutiva               │ │ Estadísticas base    │             │
│ │ Habilidades                     │ │ Datos de especie     │             │
│ │                                 │ │ Ubicación (rutas)    │             │
│ └─────────────────────────────────┘ └──────────────────────┘             │
│ Movimientos por nivel (tabla, ancho completo)                            │ scroll
└─────────────────────────────────────────────────────────────────────────┘
```

### D7 — Secciones exactas y frontera de solo lectura

**Se renderiza (7 secciones, lista blanca cerrada):**

| Sección | Fuente | Contenido |
|---|---|---|
| Cabecera | `id`, `name`, `type_details`, `atrapado` | sprite 128, `#id`, nombre, badges de tipo, badge Atrapado |
| Familia evolutiva | `family.members` | agrupada por etapa (markup actual 373-408) |
| Habilidades | `abilities` | nombre + efecto (`line-clamp-3` + `title`), sin botones |
| Movimientos | `moves` | tabla: Nv., Movimiento, Tipo, Categoría, Pot., Prec. |
| Estadísticas base | `stats` | barras actuales (411-430), sin cambios |
| Datos de especie | `species` | altura (m), peso (kg), exp. base, ratio de captura |
| Ubicación | `locations` | una tarjeta por ruta con imagen 16/9 y enlace "Ir al hábitat" |

**Queda fuera, explícitamente prohibido en este modal:**

`quality` / `calidad` / `calidadClase()` / `pokemon-modal-quality` / `--quality-*`, IV, EV (`effort`), stats calculados, CP, nivel del reclutado, shiny, equipo, loadout, movimientos asignados, entrenamiento, evolución interactiva, Chart.js, y cualquier botón de acción (`Liberar`, `Asignar`, `Enviar`, `Subir nivel`). Únicos elementos accionables: cerrar y los `<a href>` de ruta.

**Garantías de solo lectura:**

- No hay `<form>`, ni `method="POST"`, ni `@click` que mute `detail`. El estado se llena solo con el `GET /datagrid/pokemon/{id}/detalle`.
- `stats` sigue mapeando exclusivamente `base_stat` (`PresentadorDetallePokemon:81-88`); `effort` existe en el modelo pero nunca sale en el JSON.
- Cero cambios en `pokedexApp()`: no se añade estado ni lógica JS, solo markup.

**Datos de especie:** `height` está en decímetros y `weight` en hectogramos (PokéAPI; `MapeadorPokemonBatalla:102` ya divide por 10). Para no hacer conversiones en la vista, `species` añade `height_m` y `weight_kg` (`round(x/10, 1)`), omitidos si el valor crudo es nulo. Se conservan `height`/`weight` crudos por compatibilidad. `capture_rate` se muestra crudo (sin calcular %); `hatch` no se pinta (unidad de ciclos no autoexplicativa) pero se mantiene en el payload.

---

## Contrato JSON

Respuesta de `GET /datagrid/pokemon/{id}/detalle` tras el cambio. Aditivo: ningún campo existente se renombra, elimina ni cambia de tipo.

```jsonc
{
  "id": 25,
  "name": "pikachu",
  "visto": true,
  "atrapado": false,

  "types": ["Eléctrico"],
  "type_details": [
    { "id": 13, "label": "Eléctrico", "slug": "electrico", "icon": "/images/type/electrico.webp" }
  ],

  "stats": [                                  // SOLO base_stat, 6 filas, orden por enum
    { "name": "PS (HP)", "value": 35 }
  ],

  "habitat_name": "Bosque de ...",            // = locations[0].habitat_name | null
  "habitat_id": 3,                            // = locations[0].habitat_id | null
  "habitats": ["Bosque de ..."],              // nombres, orden por habitat_id
  "location": {                               // NUNCA diverge de locations[0]
    "habitat_id": 3,
    "habitat_name": "Bosque de ...",
    "habitat_image": "/habitats-img/3.webp?v=1",
    "province_id": 2,
    "province_name": "Kanto",
    "url": "/habitats/3"
  },
  "locations": [ /* NUEVO: mismo shape que location, list<>, orden por habitat_id, [] si no hay */ ],

  "family": {
    "evolution_chain_id": 10,
    "members": [
      { "id": 172, "name": "pichu", "species_id": 172, "stage": 1, "parent_id": null,
        "minimum_level": null, "branch_index": 0, "icon": "/images/iconos_webp/172.webp",
        "is_current": false }
    ]
  },

  "abilities": [                              // NUEVO en este endpoint; 1..5 filas
    { "id": 9, "name": "Electricidad estática", "effect": "...", "slot": 1, "source": "..." }
  ],

  "moves": [                                  // NUEVO en este endpoint; SOLO source = "level-up"
    { "slug": "impactrueno", "name": "Impactrueno", "type_id": 13,
      "tipo_nombre": "Eléctrico", "tipo_slug": "electrico",
      "category": "especial", "category_label": "Especial",
      "base_power": 40, "accuracy": 100, "level": 1, "source": "level-up" }
  ],

  "species": {                                // claves nulas OMITIDAS (comportamiento actual)
    "species_id": 25, "evolution_chain_id": 10,
    "capture_rate": 190, "base_experience": 112,
    "height": 4, "weight": 60,
    "height_m": 0.4, "weight_kg": 6.0         // NUEVO: derivados para presentación
  }
}
```

Reglas del contrato:

- `location === locations[0]` cuando hay hábitats; ambos `null`/`[]` cuando no hay (77 Pokémon).
- `locations` ordenado por `habitat_id` ascendente. Determinista.
- `moves` siempre `source === "level-up"` en este endpoint; `level` nunca nulo (evidencia: 0 filas). El item conserva el shape íntegro para no bifurcar el esquema.
- `abilities` ordenadas por `slot`; `slot`/`source` sin interpretar (`ability_pokemon` no guarda `hidden`).
- `species` es un objeto con claves opcionales; el cliente debe comprobar presencia (`x-if`), nunca asumir.
- **Compatibilidad:** `PresentadorDetallePokemon` solo lo consumen `DatagridServiceProvider` y `PresentadorDetalleEspecie` (grep global). El endpoint de hábitat mantiene su contrato (learnset completo). Ningún otro consumidor lee este JSON.

Impacto en el modal: se añaden `abilities`, `moves`, `species`, `locations`; todo lo demás se consume igual. Ningún campo actual desaparece de lo que la vista ya pinta.

---

## Diseño UI

### Listado (D1)

- Grid 3/5/6/8/10/12 con `gap-2 sm:gap-3`; tarjeta ~110 px de 640 a 1536, 145 px a 1920.
- Caja de imagen: `aspect-square relative bg-surface-sunken p-2 flex items-center justify-center`; `<x-sprite size="128">` sin `wrapper-class`.
- Info: `px-2 pb-2 pt-1 text-center min-h-[3.5rem]`.
- Badge "Atrapado" y placeholder `?` sin cambios.

### Tarjetas de hábitat (D2)

- Cuadradas, `object-contain`, `p-2`, bandas `bg-surface-sunken`, hover 1.03.

### Modal (D6)

```blade
<div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-panel bg-surface-raised shadow-modal lg:max-w-4xl">
    <button class="absolute top-3 right-3 z-10 ..." aria-label="Cerrar">×</button>
    <div class="shrink-0 border-b border-line p-5 pr-12 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="w-20 h-20 sm:w-32 sm:h-32 shrink-0 rounded-card bg-surface-sunken flex items-center justify-center">
                <x-sprite size="128" loading="eager" ... />   {{-- max-width:100% lo ajusta a 80/128 --}}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-ink-subtle" x-text="'#' + selectedPokemon.id"></p>
                <h2 id="pokedex-modal-title" class="text-xl font-bold text-ink" ...></h2>
                <div class="mt-2 flex flex-wrap items-center gap-2"><!-- tipos + badge --></div>
            </div>
        </div>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6" :aria-busy="detailLoading ? 'true' : 'false'">
        <!-- loading / error / no avistado (markup actual) -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <div class="space-y-4 md:col-span-3"><!-- familia, habilidades --></div>
            <div class="space-y-4 md:col-span-2"><!-- stats, especie, ubicación --></div>
        </div>
        <!-- movimientos, ancho completo -->
    </div>
</div>
```

- Paneles: `rounded-panel border border-line bg-surface-sunken p-4`; títulos `text-sm font-bold uppercase tracking-wide text-ink-muted` (patrón de `/equipos`, sin `pokemon-quality`).
- **Móvil (<md):** una columna, cabecera apilada visualmente por el sprite de 80 px, secciones en orden: familia → habilidades → stats → especie → ubicación → movimientos. Tabla de movimientos con `overflow-x-auto` y `hidden sm:table-cell` en Categoría/Precisión.
- **Estados vacíos:** `locations.length === 0` → "Sin hábitat asignado." (texto actual, 77 casos); `moves.length === 0` → "Sin movimientos por nivel." (3 casos); secciones sin datos se ocultan con `x-if` (nunca paneles vacíos).
- Accesibilidad: `role="dialog"`, `aria-modal`, `aria-labelledby="pokedex-modal-title"`, escape y click en backdrop ya existentes; `focus-ring` en cerrar y enlaces. Focus trap no se implementa (fuera de alcance, se declara).

---

## Archivos y propiedad

| Archivo | Propietario | Cambio |
|---|---|---|
| `app/Support/PresentadorDetallePokemon.php` | backend | `locations[]`, orden por `id`, `location` derivado. **Conservar** el diff preexistente de `visto`/`atrapado` |
| `app/Support/PresentadorDetalleEspecie.php` | backend | `paraPokedex()`, `componer()`, `moves(..., bool)`, `FUENTE_NIVEL`, `height_m`/`weight_kg` |
| `app/Providers/DatagridServiceProvider.php` | backend | closure `detail` → `PresentadorDetalleEspecie::paraPokedex()` + `use` |
| `resources/views/pokedex/index.blade.php` | frontend | grid + sprite + `min-h` + modal completo (D1, D6) |
| `resources/views/habitats/index.blade.php` | frontend | `object-contain` + `p-2` + hover |
| `resources/css/*` | — | **sin cambios**: tokens y utilidades existentes cubren todo |
| `routes/`, migraciones, `/equipos`, `/habitats/{id}` | — | **sin tocar** |

Ficheros no autorizados con deuda detectada: `docs/architecture.md` (~línea 180) y `docs/adr/0002...` (D7) afirman "la Pokédex no cambia" y "`PresentadorDetallePokemon` no cambia para la Pokédex". Tras este cambio es falso y debe corregirse en el mismo commit o en uno inmediato; requiere ampliación de autorización.

---

## Orden de implementación

Secuencia recomendada (no paralela dentro del mismo archivo):

1. **B1** `PresentadorDetallePokemon` — `locations` + orden determinista. Verificable aislado.
2. **B2** `PresentadorDetalleEspecie` — `paraPokedex` + derivados de especie.
3. **B3** `DatagridServiceProvider` — cambio de closure. Hasta aquí el JSON nuevo existe y el modal antiguo lo ignora (compatible).
4. **F0** `habitats/index.blade.php` — independiente; puede ir en paralelo con B1–B3 sin conflictos.
5. **F1** `pokedex/index.blade.php` — grid/sprite/`min-h`.
6. **F2** `pokedex/index.blade.php` — modal. **Después de F1** (mismo archivo, evitar conflicto de hunks).
7. `vendor/bin/pint --dirty --format agent` y `php artisan view:cache`.

Backend y frontend **pueden** ir en paralelo porque el contrato queda fijado aquí, pero no aporta: el backend son ~40 líneas y evita depurar dos variables a la vez. Recomiendo B1→B3 y después F1→F2.

---

## Verificación

**Análisis estático**

```bash
vendor/bin/phpstan analyse app/Support/PresentadorDetallePokemon.php \
  app/Support/PresentadorDetalleEspecie.php app/Providers/DatagridServiceProvider.php
vendor/bin/pint --dirty --format agent
php artisan view:cache && php artisan view:clear     # valida compilación Blade
php artisan route:list --path=datagrid               # la ruta no cambia
```

**Comprobaciones dirigidas (grep sobre el bloque del modal)**

- Prohibidos: `quality`, `calidad`, `pokemon-modal-quality`, `--quality-`, `chart`, `iv`, `effort`, `cp`, `loadout`, `Liberar`.
- Presentes y únicos accionables: `@click="closeDetail()"`, `@click="closeDetail"` (backdrop), `<a :href="loc.url">`.
- `detail.stats` sigue renderizando `stat.value` (base), nunca un cálculo.
- `location` sigue existiendo en el JSON y el bloque de ubicación funciona con payload antiguo (`detail.locations || (detail.location ? [detail.location] : [])`).

**Criterios de aceptación**

1. A 1920 px la tarjeta mide ~145 px y el sprite ocupa ~129 px: sin hueco asimétrico.
2. A 640/768/1024/1280/1536 las columnas son 5/6/8/10/12 y la tarjeta nunca baja de ~108 px.
3. Las tarjetas sin hábitat tienen la misma altura que las que lo tienen.
4. `/habitats` muestra la imagen completa; las bandas usan `surface-sunken` y el hover no recorta.
5. El detalle devuelve `locations`, `abilities`, `moves` (solo level-up), `species`; `location === locations[0]`.
6. El modal mide 768/896 px, tiene cabecera fija y cuerpo con scroll; el botón cerrar no desaparece.
7. No aparece ningún dato de quality/EV/IV ni acción de escritura.
8. El enlace "Ir al hábitat" navega a `/habitats/{id}`.
9. Con `visto=false` y sin `unlock`, el endpoint responde 404 y el modal muestra "aún no avistado" (sin cambios).
10. `/equipos` y `/habitats/{id}` no cambian.

**Datos de referencia para validar en QA manual:** id 151 (305 movimientos totales, 26 level-up), id 645 (5 habilidades), cualquier Pokémon de los 77 sin hábitat, cualquiera de los 3 sin `level-up`.

---

## Riesgos y rollback

| Riesgo | Nivel | Mitigación |
|---|---|---|
| `PresentadorDetallePokemon.php` tiene **cambios preexistentes sin commit** (overrides `?bool $visto/atrapado` de TASK-013) | Alto | No usar `git checkout --` sobre este archivo. Guardar `git diff` antes de editar y revertir con parche inverso |
| `PresentadorDetalleEspecie.php` está **untracked** (TASK-013 sin commit) | Medio | Rollback = eliminar a mano los métodos añadidos; `git checkout` no aplica |
| Dos ediciones en `pokedex/index.blade.php` (862 líneas) | Medio | F1 y F2 en secuencia, no en paralelo |
| Payload del learnset | Bajo (ya mitigado) | `level-up` acota a 13/26 filas; el endpoint de hábitat conserva el completo |
| Cambio de orden de hábitats (`first()` → `id`) | Bajo | Corrección de determinismo; 0 Pokémon con >1 hábitat hoy |
| Contradicción documental en `docs/architecture.md` / ADR-0002 | Medio | Actualizar esos 3 puntos; requiere autorización de archivos |
| 5 habilidades con efectos largos desequilibran la columna | Bajo | `line-clamp-3` + `title` con el texto completo |
| Modal sin focus trap | Bajo | Declarado fuera de alcance; escape y click en backdrop funcionan |
| `object-contain` con hover 1.05 recorta | Bajo | Bajar a 1.03 |
| Sin verificación en navegador real | Medio | La verificación es estática; validar visualmente a 360/768/1024/1920 antes del commit |

**Rollback:** `pokedex/index.blade.php`, `habitats/index.blade.php` y `DatagridServiceProvider.php` están limpios en HEAD → `git checkout -- <archivo>` es seguro. Los otros dos exigen reversión manual (ver tabla). El backend es aditivo: revertir el closure devuelve el modal al contrato antiguo y el markup nuevo degrada de forma segura con los fallbacks indicados.

---

## Fuera de alcance

- `/equipos`, `ReclutadoSerializer`, datagrid `reclutado`, `pokemon-modal-quality`, quality/EV/IV, Chart.js, loadout, shiny, combate.
- `resources/views/habitats/show.blade.php`: ya muestra la imagen completa; la unificación de marco es cosmética y no se hace.
- `per_page: 120`, paginación e scroll infinito.
- Rutas nuevas o cambios de rutas: se reutiliza `/datagrid/pokemon/{id}/detalle`.
- CSS compartido y tokens nuevos.
- Tests (desactivados por política del proyecto).
- Focus trap y `aria-describedby` del modal.
- Pivot `pokemon_habitat.level` en `locations` (nivel mínimo por ruta): dato disponible, pero es alcance nuevo.
- `max-w` de página en ultra-wide: decisión visual del desarrollador (D1).
