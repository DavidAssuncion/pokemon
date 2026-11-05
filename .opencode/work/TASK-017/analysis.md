# TASK-017 — Análisis

> Resultado del `analyst` para TASK-017.

## Resumen

El cambio se resuelve **en un único archivo**, `resources/views/habitats/index.blade.php`, usando solo utilidades Tailwind v4 ya disponibles (`object-fill`, `line-clamp-2`), sin tocar `resources/css/*`.

- **Imagen a tope de marco:** sustituir `object-contain p-2` por `object-fill` (estira en ambos ejes), mantener `w-full h-full`, añadir `block` y **quitar `p-2`**.
- **Marco:** quitar `flex items-center justify-center` (es redundante con `w-full h-full` y el centrado flex puede encoger el ítem); conservar `aspect-[3/2] bg-surface-sunken overflow-hidden`.
- **Uniformidad real:** el marco 3:2 ya iguala la zona de imagen, pero **la banda del nombre rompe la altura**: con 21 caracteres máximos, el nombre pasa a 2 líneas en base (`<640px`), `md` y `lg`. Se fija la banda a `h-11` (44 px, la altura actual de una línea) con `flex items-center justify-center` y `line-clamp-2`, de modo que toda tarjeta mide exactamente lo mismo en cualquier breakpoint y provincia. **No hace falta `auto-rows-fr`** (solo iguala filas dentro de un grid, no entre paneles, y no compensa el contenido desalineado).
- **Hover:** recomiendo **eliminar** `group-hover:scale-[1.03] transition-transform` (y la clase `group`, que queda muerta): con `object-fill` la imagen ya toca los cuatro bordes y el zoom recorta ~1,5 % por lado, contradiciendo "llegar a los máximos". La tarjeta conserva `hover:shadow-md hover:border-primary` como feedback.
- **Alcance:** no afecta a `/habitats/{id}`, `/pokedex` ni `/equipos`; no hay bloque compartido ni componente reutilizado.

## Evidencia

- `resources/views/habitats/index.blade.php:30` — grid actual `grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3` (no se toca).
- `resources/views/habitats/index.blade.php:32` — `<div class="group">`: `group` solo lo consume el `group-hover` de la imagen (línea 46); al quitarlo queda sin uso.
- `resources/views/habitats/index.blade.php:33-36` — `<a class="block bg-surface rounded-card shadow-sm border border-line overflow-hidden hover:shadow-md transition-all hover:border-primary">`; el `overflow-hidden` del ancla ya recorta el radio.
- `resources/views/habitats/index.blade.php:38` — marco `w-full aspect-[3/2] bg-surface-sunken flex items-center justify-center overflow-hidden`.
- `resources/views/habitats/index.blade.php:43-48` — imagen `class="w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300"` con `onerror="this.style.display='none'"`.
- `resources/views/habitats/index.blade.php:51-52` — banda del nombre `px-4 py-3 bg-surface border-t border-line` + `<p class="text-sm font-medium text-center text-ink">`; altura actual = 12+12+20 = **44 px con una línea** y **64 px con dos** → origen de la desigualdad.
- `resources/views/layouts/app.blade.php:133` — `<main class="px-4 sm:px-6 py-6">`; sin `max-w`, el ancho de columna depende solo del viewport.
- `resources/css/tokens.css:53-56` — solo se definen `text-2xs`/`text-3xs`; `text-sm` es el valor por defecto de Tailwind (14 px / 20 px).
- `resources/views/pokedex/index.blade.php:486-497` — bloque de "Ubicación" propio con `aspect-[16/9] object-contain`; **no comparte** markup con el grid de hábitats.
- `resources/views/habitats/show.blade.php:1-20,1522` — usa su propio hero/imágenes y solo incluye `partials.tipos-badge-script`; **no incluye** `habitats.index`.
- `grep` en `resources/views`: `object-contain` aparece en otras vistas con imágenes de ítems/tipos; `aspect-[3/2]` y `object-fill` **solo** en este grid (o en ningún sitio, para `object-fill`).
- `package.json` y `node_modules/tailwindcss/package.json` — Tailwind **4.3.3** (`^4.0.0`).
- `node_modules/tailwindcss/dist/lib.js` — `object-fill` → `object-fit: fill`; `line-clamp-<n>` → `overflow:hidden; display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:n`; `auto-rows-fr` → `grid-auto-rows: minmax(0,1fr)`. Utilidades confirmadas en el binario instalado.
- `storage/data/habitats.csv` — 56 hábitats, 7 provincias visibles (se filtra `Vacio` en `HabitatsController::index`). Nombres más largos: `Bosque Transformacion` y `Estrecho de Gibraltar` (21 caracteres); 6 hábitats por provincia en la más poblada (id 6, 10 tarjetas).
- `resources/habitats_img/` — 56 imágenes numéricas servidas por `/habitats-img/{id}.webp`; ratios **1,333 – 2,011** (mediana 1,462); 17/56 fuera de 1,4–1,7. Distorsión con marco 1,5: hasta ~12 % de estiramiento horizontal (id 9/55) y ~25 % de compresión horizontal (id 1).
- `git status` — `resources/views/habitats/index.blade.php` **limpio en HEAD** (`531dc32`). Cambios preexistentes ajenos que **no** deben entrar en el commit: `resources/css/app.css` (imports de FontAwesome, líneas 5-6), `resources/views/habitats/show.blade.php`, `routes/habitats.php`, `package.json`, hook `pre-commit` (solo cambio de modo).
- `.githooks/pre-commit` — solo valida whitespace y Pint de PHP staged; un commit de Blade no se ve afectado.

### Anchos reales por breakpoint (tarjeta → banda del nombre con `px-4`)

| Breakpoint | Columnas | Ancho tarjeta | Ancho texto |
|---|---|---|---|
| base 375 px | 2 | ≈165 px | ≈134 px |
| sm 640 px | 3 | ≈189 px | ≈157 px |
| md 768 px | 4 | ≈171 px | ≈139 px |
| lg 1024 px | 6 | ≈153 px | ≈121 px |
| xl 1280 px | 6 | ≈195 px | ≈163 px |

Un nombre de 21 caracteres a 14 px ocupa ≈145–150 px: **envuelve a 2 líneas en base, `md` y `lg`**, no en `sm`/`xl`. Por eso la banda de altura fija es imprescindible.

## Propuesta técnica

### 1. Imagen: clase exacta

```blade
class="block w-full h-full object-fill"
```

- **`object-fill`** es la clase correcta (`object-fit: fill`): estira el bitmap a las dos dimensiones del marco, sin respetar proporción. `object-cover` no sirve (recorta) y `object-contain` es lo que hay que retirar.
- **Mantener `w-full h-full`**: son las que dan al `<img>` el 100 % del marco. Con ellas, el `object-fit` se aplica sobre un viewport ya igual al marco.
- **Quitar `p-2`**: el padding interno impide tocar los bordes. La imagen llega al máximo con el padding a 0.
- **Quitar `flex items-center justify-center` del marco**: con `w-full h-full` no aportan nada y, al ser `<img>` un flex item, el centrado impide el estiramiento por defecto (`align-items: stretch`) y puede encogerlo. El `flex` deja de ser necesario. `overflow-hidden` se conserva (recorte del radio heredado y salvaguarda).
- Añadir **`block`** al `<img>`: elimina el hueco de línea base del elemento inline (4 px) y evita depender de `overflow-hidden` para absorberlo.
- Mantener `bg-surface-sunken` como fondo de respaldo cuando `onerror` oculta la imagen.

### 2. Uniformidad de tarjetas

**Recomendado (determinista):** fijar la banda del nombre.

```blade
<div class="h-11 px-4 bg-surface border-t border-line flex items-center justify-center">
    <p class="w-full text-sm font-medium text-center text-ink line-clamp-2">{{ $habitat['name'] }}</p>
</div>
```

- `h-11` = 44 px = altura actual de una línea (`py-3` + 20 px). Con `line-clamp-2` caben dos líneas de 20 px (40 px) centradas, sin desbordar. Todas las tarjetas miden **marco (ancho/1,5) + 44 px**, en todos los breakpoints y en todas las provincias.
- `line-clamp-2` mantiene los nombres completos actuales (los dos de 21 caracteres caben en dos líneas incluso a 121 px de ancho: "Estrecho de" / "Gibraltar", "Bosque" / "Transformacion") y, si en el futuro aparece un nombre más largo, corta con elipsis en lugar de romper la altura.
- `auto-rows-fr` **no es necesario y sería peor**: solo iguala las filas de un mismo grid (cada panel de provincia es un grid distinto, con distinto nombre más largo), y al estirar el `<a>` dejaría hueco muerto bajo el nombre salvo que se reestructurase a `flex flex-col`. Se descarta.
- `h-full` en el `<a>` tampoco hace falta: la altura de la tarjeta ya es intrínseca y constante.
- No usar `truncate` (una línea): truncaría "Estrecho de Gibraltar" en `md`/`lg`, que es justo donde hoy envuelve.

### 3. Hover

**Quitar** `group-hover:scale-[1.03] transition-transform duration-300` y la clase `group` del contenedor.

Motivo: con `object-fill` la imagen ocupa exactamente el marco; `scale(1.03)` la agranda y `overflow-hidden` recorta ~1,5 % por borde (≈3 px horizontales y ≈2 px verticales en la tarjeta), es decir, deja de "llegar a los máximos" y oculta contenido en el instante de hover. La tarjeta ya tiene `hover:shadow-md hover:border-primary`, feedback suficiente. Si el desarrollador prefiere conservar el zoom, la alternativa es mantener `group` + `group-hover:scale-[1.03]`, asumiendo el recorte; no se recomienda.

### 4. Impacto en otras rutas

- `/habitats/{id}`: no usa este bloque (hero propio en `show.blade.php:36`). Sin cambios.
- `/pokedex`: tiene su propio bloque de ubicación (`pokedex/index.blade.php:486-497`, `aspect-[16/9] object-contain`). Sin cambios.
- `/equipos`: sin imágenes de hábitat ni grid equivalente. Sin cambios.
- `resources/css/app.css`, `components.css`, `tokens.css`: **no se tocan**. `object-fill`, `line-clamp-2` y `block` se generan por JIT al detectar las clases en la Blade; requieren `npm run build` (o `npm run dev`) para reflejarse en producción.

### 5. Bloque final propuesto (referencia para el implementador)

```blade
@foreach($province['habitats'] as $habitat)
    <div>
        <a
            href="/habitats/{{ $habitat['id'] }}"
            class="block bg-surface rounded-card shadow-sm border border-line overflow-hidden hover:shadow-md transition-all hover:border-primary"
        >
            <div class="w-full aspect-[3/2] bg-surface-sunken overflow-hidden">
                @php
                    $habitatImagePath = resource_path('habitats_img/'.$habitat['id'].'.webp');
                    $habitatImageVersion = is_file($habitatImagePath) ? filemtime($habitatImagePath) : 0;
                @endphp
                <img
                    src="/habitats-img/{{ $habitat['id'] }}.webp?v={{ $habitatImageVersion }}"
                    alt="{{ $habitat['name'] }}"
                    class="block w-full h-full object-fill"
                    onerror="this.style.display='none'"
                >
            </div>
            <div class="h-11 px-4 bg-surface border-t border-line flex items-center justify-center">
                <p class="w-full text-sm font-medium text-center text-ink line-clamp-2">{{ $habitat['name'] }}</p>
            </div>
        </a>
    </div>
@endforeach
```

Cambios respecto a HEAD: línea 32 `<div class="group">` → `<div>`; línea 38 sin `flex items-center justify-center`; línea 46 nuevas clases; línea 51 `px-4 py-3` → `h-11 px-4 ... flex items-center justify-center`; línea 52 `w-full` + `line-clamp-2`. Nada más. Se puede actualizar el comentario de la línea 37 para reflejar `object-fill`.

## Archivos a modificar

1. `resources/views/habitats/index.blade.php` — único archivo (líneas 32, 38, 46, 51-52 y comentario 37).

No se modifica: `resources/css/*`, `resources/views/habitats/show.blade.php`, `resources/views/pokedex/index.blade.php`, `resources/views/equipos/*`, rutas, controladores, migraciones, tests.

Commit scoped sugerido: `git add resources/views/habitats/index.blade.php` y commit en `task-011` (sin push). Evitar arrastrar los cambios preexistentes de `app.css`, `show.blade.php`, `routes/habitats.php`, `package.json` y `.githooks/pre-commit`.

## Riesgos

- **[alto] Deformación visible.** Marco 1,5 frente a ratios 1,333–2,011: hasta ~12 % de estiramiento horizontal (ids 9, 55) y ~25 % de compresión (id 1). Es el requisito explícito ("aunque se deformen"); impacto estético, mitigación: ninguna si se mantiene el requisito. Documentarlo en el informe final.
- **[medio] Nombres largos con elipsis.** `line-clamp-2` recorta si un futuro nombre necesita 3 líneas (hoy ninguno lo hace). Impacto: pérdida parcial del nombre. Mitigación: mantener el `alt` (ya informa) o añadir `title="{{ $habitat['name'] }}"` si el desarrollador lo autoriza (fuera del alcance CSS estricto).
- **[bajo] `line-clamp` en hijo de flex.** Aplica `display:-webkit-box` sobre un flex item; soportado en Chrome/Firefox/Safari actuales, pero es la única pieza con comportamiento de display no trivial. Mitigación si fallara: cambiar el contenedor del nombre a `grid place-items-center` (misma altura fija). Verificación visual en el navegador.
- **[bajo] Caché de vistas/build.** Si no se ejecuta `npm run build`/`npm run dev` o el navegador sirve el CSS anterior, no se verá `object-fill`. Mitigación: build + `php artisan view:clear` (no destructivo).
- **[bajo] Regresión de hover percibida.** Al quitar el zoom, el hover solo cambia borde y sombra. Es una decisión de diseño justificada; reversible en una línea si el desarrollador la rechaza.
- **[bajo] Accesibilidad de zoom de texto.** `h-11` y `text-sm` son `rem`; escalan juntos con el tamaño de fuente del navegador. Con "tamaño mínimo de fuente" forzado, `line-clamp-2` evita el desbordamiento (elipsis), no rompe la altura.

## Criterios de verificación

1. `grep -n "object-contain\|p-2\|scale-\[1.03\]\|group" resources/views/habitats/index.blade.php` → sin coincidencias en el grid (la palabra `group` puede aparecer en otros contextos de la vista; comprobar que no queda en la tarjeta).
2. `grep -n "object-fill\|h-11\|line-clamp-2" resources/views/habitats/index.blade.php` → las tres presentes una vez.
3. `git diff --stat` → solo `resources/views/habitats/index.blade.php` en el commit.
4. `npm run build` y comprobar en el CSS compilado `.object-fill{object-fit:fill}` y `.line-clamp-2`.
5. Visual en `/habitats`: a 375, 640, 768, 1024 y 1280 px, (a) imagen sin márgenes ni bandas, (b) todas las tarjetas de una provincia con idéntica altura, (c) cambiar de pestaña de provincia y verificar que la altura de tarjeta no cambia, (d) nombres completos o elipsis solo en casos extremos, (e) hover sin recorte de la imagen.
6. Confirmar que `/habitats/{id}`, `/pokedex` y `/equipos` no cambian (sus imágenes siguen con `object-contain`).

## Preguntas abiertas

Ninguna bloqueante. Dos decisiones ya resueltas en este análisis y reversibles en una línea si el desarrollador prefiere lo contrario:

- Zoom en hover: se elimina (recomendado) frente a conservarlo asumiendo recorte.
- Banda del nombre: `h-11` + `line-clamp-2` (44 px, dos líneas completas) frente a `truncate` (una línea, elipsis antes).
