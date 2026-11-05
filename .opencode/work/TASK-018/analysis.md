# TASK-018 — Análisis

> Resultado del `analyst` para TASK-018, con addendum de la decisión rectificada (miniatura en cabecera).

## Resumen

El cambio es exclusivamente de presentación y cabe en **un solo archivo**: `resources/views/pokedex/index.blade.php`. La ubicación se mueve a la cabecera como **tarjeta compacta a la derecha** en escritorio, con miniatura del hábitat (máx. 128 px), provincia, nombre y botón “Ir al hábitat”. En móvil la tarjeta se apila bajo la identidad para no comprimir sprite/nombre/tipos. Se elimina el bloque “Ubicación” del cuerpo y el estado vacío “Sin hábitat asignado.” pasa a la cabecera. El modal adopta el ancho de `/equipos` (`w-[90vw] max-w-[90vw] max-h-[94vh]`), manteniendo cabecera fija y cuerpo con scroll. No hay cambios de backend, contrato, rutas ni CSS compartido.

## Evidencia

- `resources/views/pokedex/index.blade.php:271` — contenedor actual `relative flex max-h-[90vh] w-full max-w-3xl ... lg:max-w-4xl`; hay que sustituirlo por `w-[90vw] max-w-[90vw] max-h-[94vh]`.
- `resources/views/pokedex/index.blade.php:273-281` — cierre absoluto `top-3 right-3 z-10`, 32×32 px: ocupa de 12 a 44 px desde el borde derecho.
- `resources/views/pokedex/index.blade.php:284-327` — cabecera `shrink-0 border-b border-line p-5 pr-12 sm:p-6 sm:pr-12`, con sprite 80/128 y columna de identidad.
- `resources/views/pokedex/index.blade.php:330` — cuerpo `min-h-0 flex-1 overflow-y-auto p-5 sm:p-6`; ya soporta cabecera fija.
- `resources/views/pokedex/index.blade.php:366-368` — grid `grid-cols-1 md:grid-cols-5` con columna izquierda `md:col-span-3`.
- `resources/views/pokedex/index.blade.php:424-425` — comentario “stats, especie y ubicación” y columna derecha `md:col-span-2`.
- `resources/views/pokedex/index.blade.php:481-514` — bloque “Ubicación” del cuerpo con provincia, imagen `aspect-[16/9] object-contain`, nombre y botón; incluye el estado vacío. Se elimina.
- `resources/views/pokedex/index.blade.php:518-566` — movimientos a ancho completo; no se tocan.
- `resources/views/pokedex/index.blade.php:604-660` — getters existentes (`typePills`, `familyStages`); se añade `ubicacionesDetalle`.
- `resources/views/pokedex/index.blade.php:892-903` y `919-925` — `detail` es `null` durante la carga; la cabecera debe tolerarlo.
- `app/Support/PresentadorDetallePokemon.php:83-108` — `locations[]` ordenado y `location` como fallback.
- `app/Support/PresentadorDetallePokemon.php:146-160` — `province_name`, `habitat_name`, `habitat_image`, `url`.
- `resources/views/equipos/index.blade.php:852` — referencia de ancho `w-[90vw] max-w-[90vw] max-h-[94vh]`.
- `resources/views/habitats/show.blade.php:1130-1143` — precedente del patrón 90vw + `overflow-hidden flex flex-col` + cabecera `shrink-0` + cuerpo con scroll.
- `resources/habitats_img/*.webp` — relaciones de aspecto 0,90–2,01; mayoría 1,33–1,58.
- `resources/css/components.css:60` — `focus-ring` disponible.
- `git status` — `resources/views/pokedex/index.blade.php` limpio en HEAD (`b3cb0b2`).

## Propuesta técnica

### 1. Ancho del modal

```blade
class="relative flex max-h-[94vh] w-[90vw] max-w-[90vw] flex-col overflow-hidden rounded-panel bg-surface-raised shadow-modal"
```

- Eliminar `w-full`, `max-w-3xl` y `lg:max-w-4xl`.
- Mantener `flex flex-col overflow-hidden`; cabecera `shrink-0` y cuerpo `min-h-0 flex-1 overflow-y-auto`.
- No añadir tope en píxeles: 90vw coincide con `/equipos`.

### 2. Cabecera responsive

La cabecera pasa de una fila única a **columna en móvil / fila en escritorio**:

- Base (`< lg`): identidad (sprite + nombre/tipos) arriba; tarjeta de ubicación debajo, a ancho completo.
- `lg` (≥1024 px): identidad a la izquierda con `flex-1`; tarjeta de ubicación a la derecha con ancho fijo `lg:w-72` (288 px) y `lg:ml-auto`.

Se usa `lg` y no `md`/`sm` porque a 768 px una tarjeta de 288 px comprimiría demasiado la identidad.

| Elemento | Clases |
|---|---|
| Fila de cabecera | `flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6` |
| Identidad | `flex min-w-0 flex-1 items-start gap-4` |
| Bloque ubicación | `flex flex-col gap-3 lg:ml-auto lg:mr-1 lg:shrink-0` + `lg:w-72` condicional |
| Fila de cada ubicación | `flex min-w-0 items-start gap-3` |
| Miniatura | `w-24 max-w-32 sm:w-28 lg:w-32 shrink-0 aspect-[4/3] rounded-card border border-line bg-surface-sunken object-cover` |
| Provincia | `truncate text-3xs uppercase tracking-wide text-ink-subtle` |
| Nombre hábitat | `truncate text-sm font-semibold text-ink` |
| Botón “Ir al hábitat” | `inline-flex items-center gap-1 self-start rounded-control bg-primary px-2.5 py-1.5 text-xs font-medium text-on-primary transition-colors hover:bg-primary-hover focus-ring` |
| Enlace secundario | `inline-flex items-center gap-1 self-start text-2xs font-medium text-primary-ink hover:underline focus-ring` |
| Estado vacío | `text-xs italic text-ink-muted` |

**Miniatura:** `aspect-[4/3]` + `object-cover`; es la proporción más cercana a la mayoría de imágenes y evita letterboxing a 128 px. `alt=""` por ser decorativa: el nombre del hábitat está al lado y el enlace tiene `aria-label` completo.

### 3. Varias ubicaciones

Se muestran todas, pero solo la primera lleva miniatura y botón primario; las adicionales se apilan como filas compactas (provincia + nombre + enlace de texto), sin imagen. Hoy el caso real es 0–1.

### 4. Botón cerrar

La cabecera ya reserva `pr-12` (48 px) y el cierre ocupa de 12 a 44 px; la tarjeta termina 4 px antes. Se añade `lg:mr-1` para dejar 8 px. La miniatura queda a la izquierda de la tarjeta, a 128 px del borde derecho, sin poder invadir el cierre. El texto va con `truncate`.

### 5. Cuerpo y estado vacío

- Eliminar por completo el bloque `481-514` y actualizar el comentario de la columna derecha a “stats y especie”.
- La columna derecha queda con “Estadísticas base” y “Datos de especie”.
- El estado vacío “Sin hábitat asignado.” pasa a la cabecera, visible solo cuando `detail` cargó, no está cargando y el Pokémon es avistado (o `unlockAll`).
- Getter `ubicacionesDetalle` con fallback a `detail.location`.

### 6. Markup de referencia

```blade
<!-- Header -->
<div class="shrink-0 border-b border-line p-5 pr-12 sm:p-6 sm:pr-12">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
        <div class="flex min-w-0 flex-1 items-start gap-4">
            <!-- sprite + #id + nombre + tipos + Atrapado sin cambios -->
        </div>

        <!-- Ubicación -->
        <div
            class="flex flex-col gap-3 lg:ml-auto lg:mr-1 lg:shrink-0"
            :class="ubicacionesDetalle.length ? 'lg:w-72' : ''"
            x-show="detail && !detailLoading && (selectedPokemon.visto || unlockAll)"
        >
            <template x-for="(loc, index) in ubicacionesDetalle" :key="'ubicacion-' + loc.habitat_id">
                <div class="flex min-w-0 items-start gap-3">
                    <template x-if="index === 0">
                        <img
                            :src="loc.habitat_image"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="w-24 max-w-32 sm:w-28 lg:w-32 shrink-0 aspect-[4/3] rounded-card border border-line bg-surface-sunken object-cover"
                        >
                    </template>
                    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                        <p class="truncate text-3xs uppercase tracking-wide text-ink-subtle" x-show="loc.province_name" x-text="loc.province_name"></p>
                        <p class="truncate text-sm font-semibold text-ink" x-text="loc.habitat_name"></p>
                        <template x-if="index === 0">
                            <a
                                :href="loc.url"
                                :aria-label="'Ir al hábitat: ' + loc.habitat_name + (loc.province_name ? ' (' + loc.province_name + ')' : '')"
                                :title="'Ir al hábitat: ' + loc.habitat_name"
                                class="inline-flex items-center gap-1 self-start rounded-control bg-primary px-2.5 py-1.5 text-xs font-medium text-on-primary transition-colors hover:bg-primary-hover focus-ring"
                            >
                                Ir al hábitat
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H8M17 7v9"/>
                                </svg>
                            </a>
                        </template>
                        <template x-if="index > 0">
                            <a
                                :href="loc.url"
                                :aria-label="'Ir al hábitat: ' + loc.habitat_name + (loc.province_name ? ' (' + loc.province_name + ')' : '')"
                                class="inline-flex items-center gap-1 self-start text-2xs font-medium text-primary-ink hover:underline focus-ring"
                            >
                                Ir al hábitat
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H8M17 7v9"/>
                                </svg>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
            <p x-show="ubicacionesDetalle.length === 0" class="text-xs italic text-ink-muted">Sin hábitat asignado.</p>
        </div>
    </div>
</div>
```

### 7. Getter

```js
get ubicacionesDetalle() {
    if (!this.detail) {
        return [];
    }
    if (Array.isArray(this.detail.locations) && this.detail.locations.length > 0) {
        return this.detail.locations;
    }
    return this.detail.location ? [this.detail.location] : [];
},
```

## Archivos a modificar

- `resources/views/pokedex/index.blade.php`: ancho del modal, cabecera responsive, tarjeta de ubicación con miniatura y botón, getter, eliminación del bloque del cuerpo y actualización de un comentario.
- No se modifica ningún otro archivo.

## Riesgos

- **[medio] En móvil la cabecera crece ~84 px** y reduce el cuerpo visible. Mitigación: miniatura de 96 px y textos compactos.
- **[medio] Salto de altura al aparecer la tarjeta tras el fetch** en móvil. Mitigación: aceptarlo o reservar placeholder; no se incluye en v1.
- **[bajo] `object-cover` recorta imágenes retrato/cuadradas y muy panorámicas.** Mitigación: `object-contain` si QA prioriza fidelidad; el enlace lleva a la ficha con la imagen completa.
- **[bajo] Nombres largos a 360 px:** `truncate` + `aria-label`/`title`.
- **[bajo] Texto a 8 px del cierre:** `pr-12` + `lg:mr-1`.
- **[bajo] Muchas ubicaciones:** solo la primera lleva miniatura y botón; las extra son filas compactas.
- **[bajo] Imagen rota:** no se añade fallback JS; el cuerpo anterior tampoco lo tenía.
- **[bajo] Si no se eliminan `max-w-3xl`/`lg:max-w-4xl`, el ancho queda limitado.** Eliminarlas explícitamente.

## Verificación

- 360×640, 375×667, 390×844: identidad sin compresión, tarjeta debajo, sin scroll horizontal, botón táctil ≥32 px.
- 768×1024: tarjeta debajo a ancho completo; miniatura `sm:w-28`.
- 1024×768, 1280×800, 1440×900, 1920×1080: tarjeta a la derecha, miniatura 128 px, sin solape con el cierre, identidad con espacio para dos tipos y Atrapado.
- Estados: cargando (sin tarjeta), 1 hábitat (miniatura + provincia + nombre + botón), 0 (“Sin hábitat asignado.”), no avistado sin `unlockAll` (sin tarjeta ni mensaje), error (sin tarjeta, reintento en cuerpo).
- Varias ubicaciones simuladas: primera con miniatura y botón; siguientes compactas.
- Teclado: Tab alcanza “Ir al hábitat” con `focus-ring`; Enter navega; Esc cierra.
- Cuerpo sin “Ubicación”; cabecera fija durante el scroll; consola sin errores de Alpine.
