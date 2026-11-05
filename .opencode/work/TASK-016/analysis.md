# TASK-016 — Análisis

> Resultado del `analyst` para TASK-016.

## Resumen

El cambio es pequeño y de bajo riesgo, limitado a una vista y a un docblock. No hay consumidores del endpoint legacy fuera de `resources/views/habitats/index.blade.php` (el `habitatsApp()` de la Pokédex/detalle es otro scope Alpine y usa el endpoint por reclutado). La corrección del grid se resuelve con `aspect-[3/2]` en el marco y `w-full h-full object-contain p-2` en la imagen: con las 56 imágenes reales usadas (ratio 1,33–2,01, mediana 1,46), un marco 3:2 elimina las bandas superior/inferior en 48/56 imágenes y deja relleno medio del ~91 %. `aspect-[16/10]` elimina más bandas verticales pero genera bandas laterales de hasta ~19 px en el grupo mayoritario (relleno ~86 %), por lo que no conviene.

---

## Evidencia

- `resources/views/habitats/index.blade.php:30` — grid actual del desarrollador: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3` (se conserva).
- `resources/views/habitats/index.blade.php:32` — `<div class="relative group">`: `relative` solo daba contexto al botón absoluto ya eliminado; `group` sí se usa (`group-hover:scale-[1.03]` en la imagen).
- `resources/views/habitats/index.blade.php:36` — `:class="{ 'ring-2 ring-warning': isFavorito(...) }"`: estado muerto; el controlador ya no envía `favoritosIds`.
- `resources/views/habitats/index.blade.php:38-39` — comentario obsoleto “fixed 300x300” y contenedor `w-full aspect-square bg-surface-sunken flex items-center justify-center overflow-hidden`.
- `resources/views/habitats/index.blade.php:47` — imagen con `class=" object-contain p-2 ..."` (espacio inicial, sin `w-full h-full`). Con `border-box` y sin altura explícita, una imagen 1,36:1 en un marco 3:2 desbordaría ~9 px por arriba/abajo y el `overflow-hidden` la recortaría.
- `resources/views/habitats/index.blade.php:56` — comentario vacío `<!-- Favorite toggle button (corner) -->`.
- `resources/views/habitats/index.blade.php:66-108` — `habitatsApp()` con `favoritosIds`, `togglingHabitat`, `isFavorito()` y `toggleFavoritoHabitat()` con `fetch('/api/habitats/.../toggle-favorito')`. `activeTab` (línea 68) es lo único vivo.
- `app/Http/Controllers/HabitatsController.php:40-46` — `index()` pasa solo `provincias`; `$favoritosIds` no existe (el `?? []` de la vista lo oculta).
- `app/Http/Controllers/HabitatsController.php:165-174` — `toggleFavorito(Habitat $habitat)` no-op; docblock ya describe el legacy pero **no** tiene `@deprecated`.
- `routes/habitats.php:33-34` — comentario “Legacy no-op” y `Route::post('/api/habitats/{habitat}/toggle-favorito', ...)`.
- `routes/habitats.php:7,27-28` — cambios preexistentes sin commit (import y ruta de `DetallePokemonFamiliaController`); el bloque 33-34 está en HEAD.
- `app/Http/Controllers/ReclutadoController.php:459-466` — alternativa real: `toggleFavorito(Request, Reclutado)`, valida `habitat_id` (`nullable|integer|exists:habitats,id`) y aplica límite de 6 por hábitat.
- `routes/player.php:72` — `POST /api/reclutados/{reclutado}/toggle-favorito`.
- `src/Reclutamiento/context.md:77-78` — contrato documentado de favoritos por reclutado.
- `grep` global — únicos restos de `favoritosIds`/`toggleFavoritoHabitat`: la propia vista y su compilado en `storage/framework/views/398983b2...php`. `show.blade.php:2575` define su propio `toggleFavoritoHabitat(pokemon)` contra `/api/reclutados/...`, sin relación.
- `src/Habitats/Infra/HabitatLecturaRepository.php:111` — el detalle construye la imagen como `habitats_img/{$habitat->id}.webp`; solo se usan imágenes numéricas.
- Dimensiones (`getimagesize`, 56 imágenes numéricas = 56 hábitats en BD): mín 1,33 (ids 9,55), p25 1,40, mediana 1,46, p75 1,54, máx 2,01 (ids 1, 18, 43 ≈ 2:1). Distribución: <1,40 = 13; 1,40–1,44 = 14; 1,45–1,49 = 9; 1,50–1,54 = 11; 1,55–1,59 = 3; 1,60–1,69 = 2; ≥1,70 = 4.
- Las imágenes verticales (`Pico_Arco_Iris.webp` 0,90; `Cueva_Enigm%3Ftica.webp` 1,00; `Isla_Recluida.webp` 1,18) **no** se usan en el grid: la ruta `/habitats-img/{id}.webp` exige `int` y el repositorio usa el id. Son assets con nombre no numérico sin consumidor.
- `resources/views/pokedex/index.blade.php:496` — ya existe precedente de `aspect-[16/9]`; `aspect-[3/2]` es sintaxis válida en Tailwind v4.

Cálculo con ancho de tarjeta real (viewport 1280, `main` px-6, 6 columnas, `gap-3` → W ≈ 195 px):

- `aspect-square` (actual) + p-2: contenido 179×179; imagen 1,43 → 179×125 → **~27 px de banda arriba y abajo** (el defecto reportado).
- `aspect-[3/2]` + `p-2`: marco 195×130, contenido 179×114 (ratio 1,57) → 48/56 sin bandas verticales; 8/56 con banda superior/inferior, máximo ~12 px por lado (solo ids 1, 18, 43). Relleno medio 91,3 %.
- `aspect-[16/10]` + `p-2`: marco 195×122, contenido 179×106 (ratio 1,69) → solo 4/56 con banda vertical, pero laterales de hasta ~19 px por lado en las 1,33. Relleno medio 86,1 %.
- `aspect-[3/2]` + `p-1`: 18/56 con banda vertical y relleno 92,8 % (peor en el criterio prioritario).
- `object-cover` en 3:2 recortaría del 9 % (1,36) al 25 % (2,0) del ancho: descartado por “sin recorte significativo”.

---

## Propuesta técnica

### 1. Limpieza de favoritos en la vista

- Eliminar `:class="{ 'ring-2 ring-warning': isFavorito(...) }"` del `<a>`.
- Dejar `habitatsApp()` reducido a:
  ```js
  function habitatsApp() {
      return {
          activeTab: 0,
      };
  }
  ```
  (se eliminan `favoritosIds`, `togglingHabitat`, `isFavorito`, `toggleFavoritoHabitat`; `activeTab` sigue siendo imprescindible para tabs/paneles).
- Eliminar el comentario vacío `<!-- Favorite toggle button (corner) -->`.
- Quitar `relative` del wrapper: `<div class="group">`.

### 2. Clases exactas del grid (tarjeta)

```blade
<div class="group">
    <a
        href="/habitats/{{ $habitat['id'] }}"
        class="block bg-surface rounded-card shadow-sm border border-line overflow-hidden hover:shadow-md transition-all hover:border-primary"
    >
        <!-- Marco de imagen 3:2: encaja las imágenes apaisadas sin bandas verticales -->
        <div class="w-full aspect-[3/2] bg-surface-sunken flex items-center justify-center overflow-hidden">
            @php
                $habitatImagePath = resource_path('habitats_img/'.$habitat['id'].'.webp');
                $habitatImageVersion = is_file($habitatImagePath) ? filemtime($habitatImagePath) : 0;
            @endphp
            <img
                src="/habitats-img/{{ $habitat['id'] }}.webp?v={{ $habitatImageVersion }}"
                alt="{{ $habitat['name'] }}"
                class="w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300"
                onerror="this.style.display='none'"
            >
        </div>
        <div class="px-4 py-3 bg-surface border-t border-line">
            <p class="text-sm font-medium text-center text-ink">{{ $habitat['name'] }}</p>
        </div>
    </a>
</div>
```

- Contenedor de imagen: `w-full aspect-[3/2] bg-surface-sunken flex items-center justify-center overflow-hidden`.
- Imagen: `w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300`.
- Se conservan `bg-surface-sunken`, `overflow-hidden`, hover (`group-hover:scale`, `hover:shadow-md`, `hover:border-primary`) y el nombre.
- `p-2` se mantiene: es precisamente lo que reduce las bandas superior/inferior (sube el ratio efectivo del área de dibujo a 1,57). No tocarlo.

### 3. `@deprecated` en `HabitatsController::toggleFavorito()`

Reemplazar el docblock actual (líneas 165-170) por:

```php
/**
 * Legacy no-op conservado por compatibilidad de API.
 *
 * @deprecated Desde TASK-016. No tiene efecto: responde siempre
 *             `{"favorito": false, "count": 0}`. Alternativa:
 *             `POST /api/reclutados/{reclutado}/toggle-favorito` con body
 *             `{"habitat_id": <id>}` (favorito por reclutado dentro del hábitat).
 *
 * @see \App\Http\Controllers\ReclutadoController::toggleFavorito()
 */
public function toggleFavorito(Habitat $habitat): JsonResponse
```

- `routes/habitats.php`: **no es necesario** tocarlo; el comentario de línea 33 ya lo describe y la fuente autoritativa es el docblock. Si se quiere señalizar en ruta, cambiar solo la línea 33 a `// @deprecated Legacy no-op: ...` sin tocar la línea 34 ni el hunk preexistente (import y ruta 27-28). Recomendación: no tocarlo para evitar mezclar hunks con cambios sin commit.
- No se elimina ni la ruta ni el método (contrato).

### 4. Nada más que ajustar

- `/habitats/{id}` (show) y `/pokedex` usan sus propios `aspect-square`/imágenes y no comparten este bloque; no se ven afectados.
- No tocar CSS compartido: `aspect-[3/2]` y `object-contain` son utilidades Tailwind disponibles (Tailwind v4).
- Tras el cambio, si el navegador sirve la vista compilada antigua, `php artisan view:clear` (no destructivo).

---

## Archivos a modificar

1. `resources/views/habitats/index.blade.php`
   - Líneas 32-57: quitar `relative`, `:class` del ancla, comentario vacío; `aspect-square` → `aspect-[3/2]`; imagen `w-full h-full object-contain p-2`.
   - Líneas 66-108: reducir `habitatsApp()` a `activeTab`.
2. `app/Http/Controllers/HabitatsController.php`
   - Solo docblock de `toggleFavorito()` (165-170): añadir `@deprecated`, motivo y `@see`.
3. (Opcional, no recomendado) `routes/habitats.php:33` — solo el comentario.

No se modifican: `routes/player.php`, `src/Reclutamiento/context.md`, `resources/css/*`, `show.blade.php`, `pokedex/*`, migraciones ni tests.

---

## Riesgos

- **[bajo] Eliminar `favoritosIds`/`isFavorito`:** no hay otros consumidores verificados (`grep` global). Impacto: ninguno. Mitigación: comprobar que `x-data="habitatsApp()"` conserva `activeTab`.
- **[bajo] El ancla pierde el `ring` de favorito:** comportamiento intencional; ya no había estado real.
- **[medio] `object-contain` sin `h-full` recortaría las imágenes 1,33–1,56 al reducir la altura del marco (hasta ~9 px por lado).** Impacto: recorte sutil e inconsistente. Mitigación: `w-full h-full` explícitos (propuesta).
- **[bajo] Las 3 imágenes ~2:1 (ids 1, 18, 43) conservan ~12 px de banda superior/inferior con 3:2.** Impacto: cosmético y acotado. Mitigación: aceptarlo; `object-cover` recortaría hasta 25 % y `16/10` generaría bandas laterales en la mayoría.
- **[bajo] Tocar `routes/habitats.php` arrastra un archivo con cambios preexistentes sin commit.** Impacto: commit contaminado. Mitigación: no tocarlo, o limitar el cambio al comentario y revisar el diff por hunks.
- **[bajo] Vista compilada en `storage/framework/views` obsoleta.** Impacto: no se ve el cambio. Mitigación: `php artisan view:clear`.
- **[bajo] Regresión en `/habitats/{id}` o Pokédex:** no comparten el bloque ni el JS. Impacto: nulo.

---

## Preguntas abiertas

- Ninguna bloqueante. Decisión menor pendiente: si se desea también marcar el comentario de `routes/habitats.php:33` como `@deprecated` (recomendación: no, para no mezclar con el cambio preexistente).
