# TASK-015 — Análisis

> Resultado del `analyst` para TASK-015.

> Verificación realizada en modo solo lectura: inspección estática de vistas, componentes, CSS, presentadores, proveedor del datagrid y rutas. No se modificó ningún archivo, no se ejecutaron tests ni consultas a base de datos. Los recuentos de hábitats por Pokémon son hipótesis no verificadas contra datos.

---

## Resumen

Los tres frentes tienen causas distintas y localizadas:

1. **Espacio en blanco del listado de la Pokédex.** No es la paginación ni el `gap`. La causa principal es la combinación de un `<main>` sin ancho máximo, un grid que llega a 9 columnas y un sprite con tamaño fijo de 96 px (`.sprite--96` → `--sprite-96: 6rem`). En pantallas anchas la tarjeta crece en cuadrado, pero el sprite no, dejando una superficie muerta grande. Además, `wrapper-class="mx-auto"` no centra el sprite porque `.sprite` es `inline-block` y los márgenes automáticos no aplican a elementos inline-level.
2. **Imágenes de hábitat.** Confirmado `aspect-square` + `object-cover` en `/habitats`, que recorta los laterales de imágenes apaisadas (~1,4–1,76:1). El modal de la Pokédex usa `h-24 object-cover`, que recorta arriba/abajo por la relación ancha del contenedor. `/habitats/{id}` ya muestra la imagen completa, así que hoy no es coherente con el listado.
3. **Modal de detalle.** El contrato actual (`PresentadorDetallePokemon::para()`) ya entrega tipos, stats base, ubicación navegable y familia evolutiva completa. Faltan habilidades, movimientos y metadatos de especie. `PresentadorDetalleEspecie` ya compone todo eso sobre el contrato actual, pero hoy está efectivamente sin uso visible en la UI. Ampliar el closure del datagrid para usarlo es la vía de menor duplicación; el endpoint de hábitat no aporta y añade acoplamiento.

---

## Evidencia por frente

### 1. Listado de la Pokédex

**Contenedor de página sin tope de ancho**

- `resources/views/layouts/app.blade.php` — el `<main>` usa únicamente `px-4 sm:px-6 py-6`, sin `max-w-*` ni `mx-auto`. El contenido ocupa todo el viewport.
- `resources/views/pokedex/index.blade.php:179` — `grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-9 gap-3`.

Con 1920 px de viewport: ancho útil ≈ 1920 − 48 (padding) = 1872; menos 8 gaps de 12 px = 1776; /9 ≈ **197 px por tarjeta**. Con 2560 px: ≈ **272 px por tarjeta**. La tarjeta crece, el sprite no.

**Sprite de tamaño fijo**

- `resources/views/pokedex/index.blade.php:192` — `aspect-square relative bg-surface-sunken p-2`: la caja de imagen crece con la columna.
- `resources/views/pokedex/index.blade.php:202-209` — `<x-sprite size="96" wrapper-class="mx-auto" ...>`.
- `resources/css/components.css:9-15` — `.sprite { display:inline-block; aspect-ratio:1; width:var(--sprite-size); max-width:100%; }`.
- `resources/css/tokens.css:74` — `--sprite-96: 6rem` (96 px).
- Assets: los 1200 WebP de `public/images/iconos_webp/` son **128×128** (verificado por cabecera WebP de la muestra completa), así que se puede subir el render hasta 128 px sin pérdida, pero no llenar 200–270 px.

Con tarjeta de 197 px: caja de imagen 197 px, padding `p-2` (16 px) → 181 px útiles; sprite 96 px → **~85 px de hueco**. A 272 px de tarjeta, el hueco supera los **150 px**.

**Alineación del sprite**

`.sprite` es `inline-block` (`components.css:9-11`). `mx-auto` solo centra elementos de bloque con ancho definido; en `inline-block` los márgenes automáticos se resuelven a 0. El sprite queda pegado a la izquierda dentro de una caja cuyo texto inferior sí está centrado (`pokedex/index.blade.php:219`). Eso agrava la sensación de espacio desaprovechado y desequilibrio.

**Altura variable de la información**

- `resources/views/pokedex/index.blade.php:219-229` — el bloque de info tiene `px-2 pb-2` y pinta hasta tres líneas: `#id`, nombre y hábitat condicional (`x-if="pokemon.habitat ..."`).
- Las tarjetas sin hábitat quedan más bajas y desalinean la fila, generando huecos verticales irregulares.

**Paginación (factor menor)**

- `resources/views/pokedex/index.blade.php:644` — `per_page: '120'`.
- Con 9 columnas, 120 tarjetas son 13 filas completas + 3 tarjetas: la última fila deja 6 celdas vacías al final de cada página. Es un efecto real pero secundario; no explica el hueco dentro de cada tarjeta.

**Descartado como causa principal:** el `gap-3`, el número de páginas y la carga infinita. El problema dominante es ancho de tarjeta frente a sprite fijo.

---

### 2. Imágenes de hábitat

**Listado `/habitats` (recorte confirmado)**

- `resources/views/habitats/index.blade.php:30` — grid `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-6`.
- `resources/views/habitats/index.blade.php:39` — `<div class="w-full aspect-square bg-surface-sunken flex items-center justify-center overflow-hidden">`.
- `resources/views/habitats/index.blade.php:44-49` — `<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" ...>`.

Con una imagen de 528×300 (ratio 1,76) dentro de un cuadrado y `object-cover`: se escala por altura hasta llenar el cuadrado y el ancho resultante es 1,76× el contenedor, por lo que **se recorta ~43 % del ancho total** (≈21,5 % por lado). A 8 columnas en pantalla ancha, la pérdida es máxima.

**Modal de la Pokédex**

- `resources/views/pokedex/index.blade.php:440-446` — `<img ... class="w-full h-24 object-cover">`.
- Contenedor de 96 px de alto por ~460 px de ancho útil (`max-w-lg` menos padding). Al ser mucho más ancho que la imagen (ratio 4,8 vs 1,76), `object-cover` escala por ancho y **recorta arriba/abajo**.

**Detalle `/habitats/{id}`**

- `resources/views/habitats/show.blade.php:23-30` — imagen con `w-auto h-auto max-w-full rounded-control` dentro de un contenedor `w-fit`.
- No recorta: respeta la relación de aspecto intrínseca. **No es coherente** con el listado, pero funcionalmente ya cumple “no perder detalle”.

**Fondos y padding**

- El contenedor ya usa `bg-surface-sunken`, el mismo token que la caja de imagen de las tarjetas de Pokémon. Es un fondo neutro y consistente; no hace falta cambiarlo.
- Con `object-contain` aparecerán bandas del color de superficie (en una tarjeta cuadrada con imagen apaisada, arriba y abajo). No parecerán un error si el fondo es el token de superficie hundida y hay un padding mínimo uniforme.

---

### 3. Modal de detalle de la Pokédex

**Estructura actual**

- `resources/views/pokedex/index.blade.php:272` — modal `w-full max-w-lg max-h-[90vh] overflow-y-auto` (32rem ≈ 512 px).
- `resources/views/pokedex/index.blade.php:274-282` — botón cerrar absoluto.
- `resources/views/pokedex/index.blade.php:284-311` — cabecera centrada: sprite 128, `#id`, nombre, badge “Atrapado”.
- `resources/views/pokedex/index.blade.php:314-462` — cuerpo en una columna:
  - tipos con `typePills` (350-371),
  - familia evolutiva agrupada por etapa (373-408),
  - barras de stats base (411-430),
  - ubicación con imagen y botón “Ir al hábitat” (433-461).
- `resources/views/pokedex/index.blade.php:786-839` — `openDetail()` y `loadDetail()`: solo hace fetch si `pokemon.visto || unlockAll`.
- `resources/views/pokedex/index.blade.php:818` — `GET /datagrid/pokemon/{id}/detalle` (+ `?unlock=1` en modo QA).

**Contrato que recibe**

`app/Support/PresentadorDetallePokemon.php:65-95`:

- `id`, `name`, `visto`, `atrapado`
- `types` (lista de nombres) y `type_details` (id, label, slug, icon)
- `stats` (name, value) ordenados por stat
- `habitat_name`, `habitat_id`, `habitats` (lista de nombres)
- `location` (`habitat_id`, `habitat_name`, `habitat_image`, `province_id`, `province_name`, `url`) o `null`
- `family` (`evolution_chain_id`, `members[]` con id, name, species_id, stage, parent_id, minimum_level, branch_index, icon, is_current)

Resolución:

- `PresentadorDetallePokemon.php:69` — `$habitat = $pokemon->habitats->first()`.
- `PresentadorDetallePokemon.php:131-146` — `location()` construye la URL `/habitats/{id}` con imagen `/habitats-img/{id}.webp?v=1`.
- `PresentadorDetallePokemon.php:165-196` — `family()` delega en `FamiliaPokemon::para()` y garantiza al menos el Pokémon actual.
- `app/Providers/DatagridServiceProvider.php:98-106` — el closure `detail` devuelve `null` si el Pokémon no está visto y no hay desbloqueo → 404.

**Comparación con `/equipos`**

- `resources/views/equipos/index.blade.php:852` — modal `w-[90vw] max-w-[90vw] max-h-[94vh] overflow-y-auto pokemon-modal-quality`.
- `resources/views/equipos/index.blade.php:854-903` — cabecera horizontal: sprite 128 a la izquierda, nombre, tipos, nivel/CP/equipo y acciones.
- `resources/views/equipos/index.blade.php:906` — `grid grid-cols-1 xl:grid-cols-3 gap-4`; columna principal `xl:col-span-2`.
- `resources/views/equipos/index.blade.php:908-1077` — evolución interactiva, habilidades, loadout, movimientos disponibles.
- `resources/views/equipos/index.blade.php:1080-1173` — stats calculados (Chart.js), entrenamiento EV, stats base, IV y quality.

**Qué se puede reutilizar visualmente sin copiar lógica:**

- Cabecera horizontal con sprite + nombre + tipos.
- Paneles `rounded-panel border border-line bg-surface-sunken p-4`.
- Títulos `text-sm font-bold uppercase tracking-wide text-ink-muted`.
- Lista de habilidades en tarjetas de solo lectura.
- Barras de stats base ya presentes en la Pokédex (no requiere Chart.js).
- Familia evolutiva ya presente y correcta.

**Qué no debe copiarse:** `pokemon-modal-quality`, `calidadClase()`, gráficos Chart.js, EV, IV, evolución interactiva, loadout y botones de acción.

**Datos de especie y familia: qué existe y qué falta**

- Existe: tipos, stats base, familia completa, ubicación.
- Falta para “detalle de especie”: habilidades, learnset y metadatos (alto, peso, ratio de captura, experiencia base, hatch).
- `app/Support/PresentadorDetalleEspecie.php:26-36` ya devuelve `...PresentadorDetallePokemon::para()` + `abilities` + `moves` + `species`.
- `PresentadorDetalleEspecie.php:76-118` mapea **todo el learnset** de la especie y lo ordena (nivel primero, luego nivel ascendente y nombre).
- `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php:26-51` expone ese read-model vía `/api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`, autorizando por pertenencia hábitat↔cadena.

**¿Está usado `PresentadorDetalleEspecie`?** Solo por `DetallePokemonFamiliaQuery` (grep global). En `resources/views/habitats/show.blade.php` existen `seleccionarPokemonFamilia()` (2042-2088) y los helpers `detalleDe()`, `cargandoDetalle()`, `errorDetalle()`, `detalleActivoFamilia()` (2153-2172), pero **no hay markup que los consuma**. Es decir, el detalle ampliado está construido pero no se pinta en ninguna vista.

**¿Reutilizar el endpoint de hábitat en la Pokédex?** No recomendado: exige conocer `habitat_id` y `chain_id`, autoriza por pertenencia y no cubre Pokémon sin hábitat asignado. Duplicaría fuente de verdad y añadiría una dependencia cruzada desde la Pokédex al contexto Habitats.

**Rendimiento y autorización**

- El listado del datagrid no carga `abilities`/`moves` (`with: ['types','habitats']`, `DatagridServiceProvider.php:69`), así que ampliar el **detalle** no afecta al listado.
- El coste se paga solo al abrir un modal: `loadMissing(['abilities','moves'])` son 2 consultas extra; la familia ya se resuelve hoy con varias consultas dentro de `FamiliaPokemon`. Aceptable para una acción bajo demanda.
- El guard de autorización actual (`visto` o desbloqueo) se mantiene; habilidades y movimientos son datos de catálogo, no información privada del usuario.

---

### 4. Navegación a la ruta

- `resources/views/pokedex/index.blade.php:433-461` — bloque “Ubicación”: solo se pinta si `detail.location` es truthy; el botón “Ir al hábitat” usa `detail.location.url`.
- `app/Support/PresentadorDetallePokemon.php:131-146` — la URL es `/habitats/{habitat_id}`.
- `app/Models/Pokemon.php:35-39` — `habitats()` es `belongsToMany` **sin `orderBy`**; `first()` depende del orden que devuelva la consulta, no de una regla de negocio.
- `app/Support/PresentadorDetallePokemon.php:89-91` — se exponen `habitat_name`, `habitat_id` y la lista de **nombres** `habitats`, pero no una lista de ubicaciones navegables.
- `app/Providers/DatagridServiceProvider.php:75-76` — el listado también usa `habitats->first()` para nombre e id del hábitat.

**Conclusión:** el enlace funciona en el caso normal (Pokémon con al menos un hábitat). Los fallos posibles son:

1. Pokémon sin hábitat → `location = null` → se muestra “Sin hábitat asignado.” y no hay enlace.
2. Pokémon en varios hábitats → solo se ofrece uno, elegido de forma no determinista.
3. Pokémon con hábitat pero sin fila en `pokemon_habitat` o con relación no cargada → mismo caso que 1.

No he verificado contra la base de datos cuántos Pokémon caen en cada caso. Para el criterio literal (“ir a la ruta donde se captura”), mostrar la primera ubicación es suficiente; para ser fiel cuando hay varias, conviene exponer todas.

---

### 5. Tamaños del modal

- Actual: `max-w-lg` (32rem ≈ 512 px), una columna, cabecera centrada con sprite 128.
- Referencia `/equipos`: `w-[90vw] max-w-[90vw]` (90 % del viewport, sin tope útil; en 4K supera los 2000 px) y 3 columnas desde `xl`.

Problema del actual: con una sola columna y 512 px, los bloques de familia y stats quedan estrechos y el modal crece en vertical, generando scroll y espacio poco aprovechado. El extremo de `/equipos` es excesivo para contenido de solo lectura.

---

## Propuesta técnica

### Frente 1 — Listado de la Pokédex (cambio mínimo)

Solo en `resources/views/pokedex/index.blade.php`:

1. **Grid más denso y con más breakpoints** (línea 179):

```blade
class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 2xl:grid-cols-12 gap-2 sm:gap-3"
```

   - Móvil: 3 columnas (se mantiene).
   - `sm` 5 en lugar de 6: a 640 px, 6 columnas dejan tarjetas de ~88 px, demasiado pequeñas.
   - `lg` 8 y `xl` 10 reducen el ancho por tarjeta en portátiles y monitores grandes.
   - `2xl` 12: a 1920 px, tarjeta ≈ 145 px, caja útil ≈ 129 px, encaja con el sprite de 128 px.

2. **Sprite que ocupe la caja sin pixelarse** (líneas 202-209):

```blade
<x-sprite
    size="128"
    wrapper-class="w-full h-full"
    class="transition-transform group-hover:scale-110"
    ...
/>
```

   - `size="128"` coincide con el asset real (128×128).
   - `wrapper-class="w-full h-full"` hace que `.sprite` llene la caja; los utilities de Tailwind están en capa superior a `@layer components`, por lo que `w-full`/`h-full` prevalecen sobre `width: var(--sprite-size)`.
   - El `<img>` interno ya tiene `width/height:100%` y `object-fit:contain` (`components.css:17-22`).
   - Si se prefiere no arriesgar el override de capas, alternativa conservadora: mantener `size="128"` y añadir `wrapper-class="flex items-center justify-center w-full h-full"`; el flex centra aunque el sprite no llene.
   - **No estirar por encima de 128 px** para no ampliar un raster de 128.

3. **Altura mínima de la información** (línea 219):

```blade
<div class="px-2 pb-2 pt-1 text-center min-h-[3rem]">
```

   Evita que las tarjetas sin hábitat encojan y desalineen la fila.

**Descartado:**

- Cambiar `per_page` de 120: no ataca el hueco dentro de la tarjeta y altera el comportamiento de scroll infinito.
- Poner un `max-w` global en `layouts/app.blade.php`: afectaría a todas las páginas.
- Rehacer el grid con `auto-fill`: rompe el estilo de breakpoints del proyecto y puede producir 20+ columnas en 4K.

---

### Frente 2 — Imágenes de hábitat

**Listado `/habitats`** — `resources/views/habitats/index.blade.php:44-49`:

```blade
<img
    src="/habitats-img/{{ $habitat['id'] }}.webp?v={{ $habitatImageVersion }}"
    alt="{{ $habitat['name'] }}"
    class="w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300"
    onerror="this.style.display='none'"
>
```

- `object-contain` muestra la imagen completa; las bandas superior/inferior usan el `bg-surface-sunken` del contenedor (línea 39), que ya es coherente con el resto de la UI.
- `p-2` separa la imagen del borde; sin padding, la imagen toca el borde en los lados largos.
- Bajar el hover de `scale-105` a `scale-[1.03]`: con `object-contain` y `overflow-hidden`, un 5 % puede recortar la imagen en el eje corto. Si se prefiere mantener el efecto actual, no es incorrecto, solo más agresivo.
- **No cambiar** `bg-surface-sunken` ni añadir fondo negro: el token de superficie hundida evita que las bandas parezcan un error.
- Alternativa opcional (no mínima): duplicar la imagen de fondo con `absolute inset-0 object-cover blur-sm opacity-40` y la imagen nítida con `object-contain` encima. Rellena las bandas, pero añade DOM y ruido visual; no la recomiendo para el cambio mínimo.

**Modal de la Pokédex** — `resources/views/pokedex/index.blade.php:440-446`:

```blade
<img
    :src="detail.location.habitat_image"
    :alt="'Hábitat ' + detail.location.habitat_name"
    loading="lazy"
    decoding="async"
    class="w-full aspect-[16/9] object-contain bg-surface-sunken"
>
```

- Sustituir `h-24 object-cover` por un contenedor con relación `16/9` y `object-contain`. Con `h-24` fijo y ancho completo, `contain` dejaría bandas laterales enormes; con `16/9` la imagen apaisada ocupa casi todo el marco y se sigue viendo completa.
- Si se quiere mantener altura fija, `h-28 object-contain` es aceptable, pero el resultado es más “letterbox”.

**Detalle `/habitats/{id}`** — `resources/views/habitats/show.blade.php:23-30`:

- Ya usa tamaño intrínseco (`w-auto h-auto max-w-full`) y no recorta. **No requiere cambio** para el criterio de “no perder detalle”.
- Si se busca coherencia visual estricta con las tarjetas, se puede envolver en el mismo marco `aspect-[16/9] bg-surface-sunken` con `object-contain`. Es opcional y no forma parte del mínimo; el contenedor actual `w-fit` ya respeta la relación de aspecto.

---

### Frente 3 — Modal de detalle (solo lectura, sin quality/EV/IV)

**Backend: ampliación aditiva del contrato**

Opción recomendada (mínima y sin duplicar lógica):

1. En `app/Support/PresentadorDetallePokemon.php`, añadir `locations` al array de `para()` y un método privado `locations()`:

```php
'locations' => self::locations($pokemon->habitats),
```

```php
/**
 * @return list<array{
 *     habitat_id: int,
 *     habitat_name: string,
 *     habitat_image: string,
 *     province_id: int|null,
 *     province_name: string|null,
 *     url: string
 * }>
 */
private static function locations(Collection $habitats): array
{
    return $habitats
        ->sortBy(fn (Habitat $habitat): int => (int) $habitat->getAttribute('id'))
        ->map(fn (Habitat $habitat): array => self::location($habitat))
        ->filter()
        ->values()
        ->all();
}
```

   - Mantener `location` como el primer elemento de `locations` (o `null` si no hay) para no romper el modal actual ni el contrato histórico.
   - Ordenar por `id` hace determinista la elección, corrigiendo el `first()` sin orden de `Pokemon::habitats()`.

2. En `app/Providers/DatagridServiceProvider.php:98-106`, cambiar el closure `detail` para usar el presentador ampliado:

```php
use App\Support\PresentadorDetalleEspecie;
// ...
return PresentadorDetalleEspecie::para($pokemon);
```

   - `PresentadorDetalleEspecie::para()` ya hace spread de `PresentadorDetallePokemon::para()` (`PresentadorDetalleEspecie.php:31`), así que hereda `locations` y añade `abilities`, `moves` y `species` sin duplicar código.
   - El guard de `visto`/desbloqueo se mantiene igual antes de llamar al presentador.

3. **Contrato resultante** (aditivo, compatible hacia atrás):

```text
id, name, visto, atrapado,
types, type_details, stats,
habitat_name, habitat_id, habitats,
location, locations[],
family,
abilities[]  (id, name, effect, slot, source),
moves[]      (slug, name, type_id, tipo_nombre, tipo_slug, category,
              category_label, base_power, accuracy, level, source),
species      (species_id?, evolution_chain_id?, capture_rate?,
              base_experience?, height?, weight?, hatch?)
```

   - Impacto: solo el endpoint de detalle del datagrid. Ningún consumidor más usa `PresentadorDetallePokemon` (grep global: `DatagridServiceProvider` y `PresentadorDetalleEspecie`).
   - No cambia el listado ni los filtros.

**Alternativa descartada:** llamar desde la Pokédex a `/api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`. Requiere `habitat` y `chain`, no cubre Pokémon sin hábitat, autoriza por pertenencia y obliga a dos peticiones (una para saber hábitat/cadena). Se descarta.

**Frontend: estructura del modal**

Mantener Alpine y los datos actuales; reorganizar el markup:

- **Contenedor** (línea 272):

```blade
<div class="relative bg-surface-raised rounded-panel shadow-modal w-full max-w-3xl lg:max-w-4xl max-h-[90vh] overflow-y-auto">
```

- **Cabecera horizontal** tipo `/equipos` (`equipos/index.blade.php:854-903`), adaptada a solo lectura:

```blade
<div class="flex items-start gap-4 p-6 border-b border-line">
    <div class="w-32 h-32 shrink-0 rounded-card bg-surface-sunken flex items-center justify-center">
        <x-sprite size="128" ... />
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-xs text-ink-subtle" x-text="'#' + selectedPokemon.id"></p>
        <h2 id="pokedex-modal-title" class="text-xl font-bold text-ink" ...></h2>
        <!-- tipos + badge Atrapado -->
    </div>
</div>
```

- **Cuerpo** en dos zonas desde `md`:

```blade
<div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2 space-y-4">
        <!-- Familia evolutiva (markup actual 373-408) -->
        <!-- Habilidades -->
        <!-- Movimientos -->
    </div>
    <div class="space-y-4">
        <!-- Stats base (markup actual 411-430) -->
        <!-- Datos de especie -->
        <!-- Ubicación (locations) -->
    </div>
</div>
```

- **Habilidades** (solo lectura): repetir el patrón visual de `/equipos` sin botones:

```blade
<div class="rounded-panel border border-line bg-surface-sunken p-4">
    <h4 class="text-sm font-bold uppercase tracking-wide text-ink-muted mb-2">Habilidades</h4>
    <template x-for="hab in (detail.abilities || [])" :key="'hab-' + hab.id">
        <div class="rounded-card border border-line bg-surface p-3">
            <p class="text-sm font-semibold text-ink" x-text="hab.name"></p>
            <p class="mt-1 text-xs leading-snug text-ink-muted" x-text="hab.effect || 'Sin descripción.'"></p>
        </div>
    </template>
</div>
```

- **Movimientos**: agrupar por `source` (`level-up` primero) y mostrar tipo, categoría, potencia, precisión y nivel. Sin botones de loadout ni “asignar”.
- **Datos de especie**: alto, peso, ratio de captura, experiencia base y hatch desde `detail.species`, omitiendo claves ausentes.
- **Ubicación**: iterar `detail.locations` (con fallback a `detail.location`) y pintar un botón por ruta:

```blade
<template x-for="loc in (detail.locations || (detail.location ? [detail.location] : []))" :key="loc.habitat_id">
    <div class="rounded-control border border-line overflow-hidden">
        <img :src="loc.habitat_image" :alt="'Hábitat ' + loc.habitat_name"
             loading="lazy" decoding="async"
             class="w-full aspect-[16/9] object-contain bg-surface-sunken">
        <div class="p-3 flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-ink" x-text="loc.habitat_name"></p>
            <a :href="loc.url"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-control bg-primary text-on-primary text-xs font-medium hover:bg-primary-hover transition-colors focus-ring">
                Ir al hábitat
            </a>
        </div>
    </div>
</template>
```

- **Eliminar del modal** cualquier referencia a quality, EV o IV. El modal actual no las tiene; solo hay que evitar copiarlas de `/equipos`.
- Mantener el estado de carga, error y reintento actuales (316-345).

**Sin cambios en `/equipos`:** la vista y el datagrid `reclutado` no se tocan.

---

### Frente 4 — Navegación a la ruta

- Mínimo: dejar `location` como está y usar el botón actual. Cumple el criterio para Pokémon con hábitat.
- Recomendado (incluido arriba): exponer `locations[]` y pintar todas las rutas. Corrige el `first()` no determinista y cubre Pokémon con varios hábitats.
- Para Pokémon sin hábitat, mantener el mensaje “Sin hábitat asignado.” (`pokedex/index.blade.php:458-460`). No inventar una ruta alternativa.

---

### Frente 5 — Tamaños del modal

Recomendación:

- Ancho: `w-full max-w-3xl lg:max-w-4xl` (768 px / 896 px). Es más ancho que el actual (`max-w-lg` = 512 px) y mucho menor que el 90vw de `/equipos`.
- Breakpoints:
  - `< md` (hasta 768 px): una columna, scroll vertical, cabecera apilada.
  - `md` (≥ 768 px): `grid-cols-3` con contenido principal `md:col-span-2`.
  - `lg` (≥ 1024 px): se mantiene 2/3 + 1/3 y aumenta el ancho a `max-w-4xl`.
- Altura: `max-h-[90vh]` con `overflow-y-auto`; la cabecera puede quedarse dentro del scroll (como hoy) para no añadir complejidad.

---

## Archivos a modificar

| Archivo | Cambio | Necesario |
|---|---|---|
| `resources/views/pokedex/index.blade.php` | Grid y breakpoints; sprite 128 con wrapper a tamaño completo; `min-h` en info; ancho del modal; cabecera horizontal; cuerpo en 3 columnas; secciones habilidades/movimientos/especie; `locations` en ubicación; imagen del hábitat `object-contain` | Sí |
| `resources/views/habitats/index.blade.php` | `object-cover` → `object-contain` + `p-2`; hover suave | Sí |
| `app/Support/PresentadorDetallePokemon.php` | Añadir `locations` (aditivo) y orden determinista; mantener `location` | Sí |
| `app/Providers/DatagridServiceProvider.php` | Closure `detail` → `PresentadorDetalleEspecie::para()` | Sí |
| `resources/views/habitats/show.blade.php` | Opcional: unificar marco de imagen del hábitat | No (opcional) |
| `resources/views/equipos/index.blade.php` | Sin cambios | No |
| Rutas | Sin cambios: se reutiliza `/datagrid/pokemon/{id}/detalle` | No |
| CSS compartido | Sin cambios: tokens y componentes existentes cubren el diseño | No |

---

## Riesgos

- **[Medio] Peso del learnset en el modal.** `PresentadorDetalleEspecie::moves()` devuelve todo el catálogo de movimientos de la especie (`PresentadorDetalleEspecie.php:76-118`); en especies con muchos movimientos puede ser una respuesta grande y un render largo. *Impacto:* modal lento en especies concretas. *Mitigación:* es una petición bajo demanda; si se detecta, limitar a `source = level-up` o paginar en cliente. No afecta al listado.
- **[Medio] Contrato aditivo.** Añadir campos no rompe consumidores que lean claves, pero rompería comparaciones exactas de JSON. *Impacto:* bajo en este repo (solo la Pokédex consume el detalle). *Mitigación:* mantener `location` y todos los campos actuales; no renombrar ni eliminar claves.
- **[Bajo] `locations` y `location` divergentes.** Si se calculan por separado podrían desincronizarse. *Mitigación:* derivar `location` del primer elemento de `locations`.
- **[Bajo] Cambio de orden de hábitats.** Pasar de `first()` sin orden a orden por `id` puede cambiar la ruta mostrada para Pokémon con varios hábitats. *Impacto:* es una corrección de determinismo, no una regresión funcional; el enlace sigue siendo válido.
- **[Bajo] Bandas visibles con `object-contain` en tarjetas cuadradas.** Es el comportamiento esperado de la decisión “cuadrada + imagen completa”. *Mitigación:* `bg-surface-sunken`, `p-2` y hover suave; no usar fondos oscuros ni bordes que parezcan error.
- **[Bajo] Densidad excesiva del grid en pantallas medianas.** 8/10/12 columnas pueden dejar tarjetas pequeñas. *Mitigación:* breakpoints propuestos (`sm` 5, `md` 6, `lg` 8, `xl` 10, `2xl` 12) y sprite que se adapta.
- **[Bajo] Regresión en `/equipos`.** No se modifica su vista, su datagrid (`reclutado`) ni `ReclutadoSerializer`. El cambio en `PresentadorDetallePokemon` no lo consume `/equipos`.
- **[Bajo] Autorización.** El closure `detail` mantiene el guard actual (`visto` o desbloqueo). Los datos añadidos son catálogo público; no se expone información de la Pokédex de otros usuarios.

---

## Preguntas abiertas

1. **¿Mostrar todas las rutas o solo la primera?**
   - Recomendado: todas (`locations[]`), con un botón “Ir al hábitat” por ruta.
   - Mínimo aceptable: solo la primera, manteniendo `location` actual.
   - No es bloqueante: el criterio literal se cumple con la primera; la propuesta incluye todas porque corrige el `first()` no determinista.

2. **¿Incluir el learnset completo o solo movimientos por nivel?**
   - Recomendado para “detalle de especie como /equipos”: completo, agrupado por fuente.
   - Alternativa si preocupa el rendimiento: solo `level-up`.
   - No es bloqueante: el presentador ya entrega ambos y el filtrado es de UI.

3. **¿Unificar el marco de imagen en `/habitats/{id}`?**
   - Hoy ya muestra la imagen completa; solo cambiaría el marco visual.
   - No es bloqueante ni necesario para los criterios de aceptación.

Ninguna de las tres impide iniciar la implementación con los valores recomendados.
