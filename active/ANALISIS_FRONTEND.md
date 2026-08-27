# Análisis Frontend — Revert layout a nav horizontal + habitats/index con Tailwind/Alpine

## Vistas a tocar

### 1. `resources/views/layouts/app.blade.php` (REESCRIBIR)
- Reemplazar layout con sidebar (`components.sidebar` + `components.header` + `lg:ml-64`) por nav horizontal simple (sticky header, logo + 5 enlaces + toggle dark mode).
- **No borrar** `resources/views/components/sidebar.blade.php` ni `header.blade.php` (quedan sin uso en el repo).
- Flash messages: pasar de `x-alert` a divs inline con clases Tailwind (según markup del Arquitecto).

### 2. `resources/views/habitats/index.blade.php` (REESCRIBIR)
- Eliminar clases CSS custom (`.tabs`, `.tab-button`, `.habitat-button`, `.habitats-grid`, `.habitat-thumb`, `.habitat-name`) que viven en `public/css/app.css` (fallback legacy) y rompen dark mode.
- Tabs con Alpine.js `x-data`/`x-show` + `x-cloak` (consistente con el proyecto; se elimina el JS vanilla del `@push('scripts')`).
- Cards de hábitat: imagen arriba `aspect-square` (300x300) + nombre abajo, grid `1/2/3` columnas, link a `/habitats/{id}`.

## Assets / infraestructura (decisión clave)

- `public/css/app.css` es un fallback **sin utilidades Tailwind** (CSS custom legacy, fondo fijo `#121827`, clases `.tabs`, `.habitat-button`…).
- Tailwind + Alpine se sirven vía `@vite` (manifest en `public/build/`, Alpine arranca en `resources/js/bootstrap.js`).
- **Decisión**: en el layout se conserva `@vite(['resources/css/app.css', 'resources/js/app.js'])` en lugar del `<link href="/css/app.css">` del markup del Arquitecto. Sin `@vite`, las utilidades Tailwind de TODAS las vistas desaparecen y `x-data` (tabs) no funciona. Se mantiene el resto del markup del Arquitecto verbatim (nav, toggle, flash, `[x-cloak]`, script anti-flash dark mode).

## DTOs consumidos

- `$provincias` (`HabitatsController::index` → `ObtenerHabitatsPorProvincia::handle()->toArray()`): array con `name` + `habitats` (cada uno `id`, `name`). Mismo shape que ya consume la vista actual — **sin cambios backend**.

## Tests

- No hay Dusk instalado en el proyecto (solo PHPUnit). Cambio puramente de presentación: no se añaden tests nuevos.
- Verificación: `vendor/bin/pint --dirty` + correr tests existentes de Hábitats (`tests/Feature/Habitats*`, `HabitatsControllerTest`) para confirmar que la vista sigue renderizando con el shape de datos esperado.

## Estados UI a cubrir

- **Nav**: estado activo (`request()->is(...)` → azul), hover, `overflow-x-auto` para móvil, toggle dark (☀️/🌙).
- **Tabs**: tab activo (azul), tabs inactivos, panel oculto con `x-cloak` (sin flash al render).
- **Cards**: imagen rota → `onerror` oculta la imagen (queda el bloque aspect-square gris + nombre), hover scale + shadow, dark mode en todos los textos.
- **Flash**: success (verde) y error (rojo).

## Riesgos accesibilidad/UX

- `role="tablist"`/`role="tab"` sin `aria-selected` dinámico (se mantiene markup del Arquitecto; anotar para QA).
- El toggle dark usa clase `.dark` en `<html>`, pero Tailwind v4 (sin `@custom-variant dark` en `resources/css/app.css`) resuelve `dark:` por `prefers-color-scheme` → el toggle es cosmético (comportamiento ya existente; fuera de alcance, anotar para Arquitecto).
- `whitespace-nowrap` + `overflow-x-auto` evita desbordes en móvil.
- No se borran componentes del sidebar (referencias en otros archivos fuera de alcance).
