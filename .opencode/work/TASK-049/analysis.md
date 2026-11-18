# TASK-049 — Análisis

## 1. Archivo y bloque objetivo

- Modal: `resources/views/habitats/partials/modal-formacion.blade.php`.
  - Raíz Alpine: `x-if="showFormacionPopup"` (`:2`).
  - Panel: `w-full max-w-md p-6` (`:5`) → ancho a aumentar.
  - Cuerpo con scroll: `max-h-[70vh] overflow-y-auto` (`:11`).
  - Incluye `partials.comparacion-pc` y `partials.leyenda-ivs-evs` (`:42-43`).
- Se abre para ruta (`formacionContexto = 'ruta'`, `show.blade.php:169-181`) y para
  entrenadores (`:200-222`); el título cambia en `modal-formacion.blade.php:6`.
- La leyenda IV/EV está en `resources/views/partials/leyenda-ivs-evs.blade.php` y
  también se incluye en el modal de gimnasio (`gimnasios/show.blade.php:222`).
- Los rivales son como máximo 3 (`ConstruirLeyendaIvsEvs.php:22`), por eso el desglose
  actual son 3 bloques apilados.

## 2. Estructura y geometría actual

- Ancho útil del modal: ~400 px (`max-w-md` 448 px menos `p-6`).
- Tabla de perfiles: `min-w-[22rem]` + `overflow-x-auto` (`leyenda:39-76`).
- Desglose por Pokémon: `x-for` con 3 bloques `space-y-1.5`, cada uno con tabla de
  5 columnas `min-w-[20rem]` + `overflow-x-auto` (`leyenda:79-109`).
- El panel no tiene `max-h` propio; en viewports bajos el conjunto puede recortarse.

## 3. Propuesta

### Ancho y estructura
- `max-w-md` → `max-w-6xl` (1152 px; ~360 px por columna con `gap-3`).
- Panel: `flex max-h-[90vh] flex-col`; cabecera `shrink-0 px-6 pt-6`, cuerpo
  `min-h-0 flex-1 overflow-y-auto px-6 py-4`, botones `shrink-0 border-t px-6 py-4`.
- Se aplica al modal compartido de ruta/entrenadores; el modal de gimnasio es otro
  markup y se queda en `max-w-md`.

### Leyenda colapsable
- `<details class="group">` + `<summary>` nativo, cerrado por defecto en el modal de
  formación. Sin `x-collapse` (el plugin no está instalado).
- `<summary>`: título, estado compacto (`Cargando…`, `No disponible` o perfil actual)
  en un `aria-live="polite"`, chevron `group-open:rotate-180`, `focus-ring`.
- Cuerpo actual dentro del `<details>`; el `aria-live` interior se conserva.
- Para gimnasio: `colapsable = false` → se renderiza el bloque actual sin `<details>`.

### 3 columnas
- Envolver el `x-for` en `grid grid-cols-1 gap-3 xl:grid-cols-3`.
- Tarjetas con `min-w-0`; tablas siguen con `overflow-x-auto` y `min-w-[20rem]`.
- `xl` (1280 px) para garantizar ~360 px por columna con `max-w-6xl`.

### Parametrización
- En `modal-formacion`: `@include('partials.leyenda-ivs-evs', ['columnas' => 3, 'colapsable' => true])`.
- En `gimnasios/show.blade.php`: include sin parámetros.
- En el partial: `$columnas = (int) ($columnas ?? 1); $colapsable = (bool) ($colapsable ?? false);`.

## 4. Impacto en gimnasio y móvil

- Gimnasio: defaults `columnas=1`, `colapsable=false` → mismo DOM y mismo apilado;
  su modal sigue `max-w-md`.
- Móvil: 1 columna por debajo de `xl`; el modal sigue `w-full` dentro de `p-4`.
- El `max-h-[90vh]` con cuerpo flexible evita el recorte actual en pantallas bajas.

## 5. Accesibilidad y Alpine

- `<details>/<summary>` es nativo, focusable y anuncia expandido/colapsado; no requiere JS.
- El partial no introduce `x-data`; las expresiones siguen en el scope de `habitatShow()`.
- El `aria-live` interior no se anuncia con el bloque cerrado; se mitiga con el estado
  compacto del `<summary>` con `aria-live="polite"`.
- `Escape` sigue cerrando el modal (`@keydown.escape.window`).
- No hay `wire:` en estos partials; no hay dependencia de Livewire.

## 6. Riesgos

| Sev. | Riesgo | Mitigación |
|---|---|---|
| Alto | Panel sin `max-h` propio en viewports bajos | `max-h-[90vh] flex flex-col` + cuerpo con scroll |
| Medio | 3 columnas por debajo de 1280 px | `xl:grid-cols-3`; tablas con scroll interno |
| Medio | `aria-live` dentro de `<details>` cerrado | Estado compacto con `aria-live` en el summary |
| Medio | Cambiar `space-y-3` por grid sin `gap-3` | `gap-3` explícito |
| Bajo | Marker nativo en Safari | `[&::-webkit-details-marker]:hidden` |
| Bajo | Regresión en gimnasio | Defaults `columnas=1`, `colapsable=false`; no tocar su include |
| Bajo | Clases no compiladas | `npm run build` y revisar CSS emitido |

## 7. Decisiones pendientes para el plan

1. El modal compartido se ensancha para ruta y entrenadores; el de gimnasio no.
2. La leyenda arranca cerrada en el modal de formación (objetivo: ocupar menos).
3. 3 columnas desde `xl` (1280 px), no desde `lg`.
4. `max-w-6xl` para que las tablas de 5 columnas quepan sin scroll horizontal.

## 8. Archivos consultados

`modal-formacion.blade.php`, `leyenda-ivs-evs.blade.php`, `comparacion-pc.blade.php`,
`comparacion-pc-script.blade.php`, `habitats/show.blade.php`,
`gimnasios/show.blade.php`, `components/ui/modal.blade.php`, `modal-baya.blade.php`,
`_tarjeta-activa.blade.php`, `resources/css/*`, `package.json`, `bootstrap.js`,
`app/Support/Pc/*`, `public/build/assets/app-S-0YB6LG.css`.
