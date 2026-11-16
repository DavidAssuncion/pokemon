# TASK-032 — Plan v1 — Marco dorado continuo en el modal de detalle de `/equipos`

- **ID:** TASK-032
- **Versión:** v1
- **Estado:** APROBADO v1 — IMPLEMENTADO (commit `37f665b`)
- **Fecha:** 2026-11-09
- **Base:** HEAD `05c5a3d` (rama `task-011`)
- **Análisis:** `.opencode/work/TASK-032/analysis.md`
- **Diseño:** no aplica (cambio de presentación acotado)

---

## 1. Objetivo

Evitar que la barra de scroll del modal de detalle de Pokémon rompa el marco/glow de calidad IV (especialmente el dorado de `perfect`), conservando el scroll nativo, la accesibilidad y el comportamiento actual del modal.

## 2. Alcance

- Separar el marco exterior (sin scroll) del contenedor interior que scrollea, siguiendo el patrón ya usado en Pokedex y en los modales de habitats.
- Mantener la cabecera (título, tipos, nivel/CP, Liberar, Cerrar) fija como hija `shrink-0` del marco exterior.
- Convertir el cuerpo existente (`modal-detalle-pokemon.blade.php:58`) en el único scroller: `flex-1 min-h-0 overflow-y-auto`.
- Añadir una scrollbar fina y tematizada con el color de calidad, encapsulada en una clase nueva (`.pokemon-modal-scroll`) y limitada al scroller del modal.
- Verificar que el marco dorado se ve continuo en las cuatro esquinas, en claro y oscuro, sin duplicar scroll.

## 3. Fuera de alcance

- Otros modales y otros componentes con scroll.
- Cambios en la lógica de cálculo de calidad (`calidadIvs`, `calidadClase`) o en los tokens de color.
- Cambios en backend, Rutas, Controladores, migraciones, datos o `SESSION_VERSION`.
- Cambios en dependencias (`package.json`, `composer.json`).
- Tests: no se crean, no se modifican, no se ejecutan.
- `bugs.txt` (cambio local preexistente del desarrollador).
- Rediseño general del modal o de la cabecera más allá de dejarla fuera del scroller.

## 4. Arquitectura y contratos

- **No aplica** diseño arquitectónico: el cambio es de presentación y reutiliza un patrón existente.
- No hay contratos nuevos ni cambios de API.
- Se mantienen `role="dialog"`, `aria-modal="true"`, `aria-labelledby="detail-modal-title"`, cierre con Escape y clic en backdrop.
- Regla de proyecto respetada: Blade solo presenta; no se añade lógica de negocio ni cálculos.

## 5. Archivos a crear o modificar

| Archivo | Tipo de cambio | Responsable |
|---|---|---|
| `resources/views/equipos/partials/modal-detalle-pokemon.blade.php` | Estructura de clases: exterior `overflow-hidden flex flex-col`; cabecera `shrink-0`; cuerpo `flex-1 min-h-0 overflow-y-auto pokemon-modal-scroll` | `frontend` |
| `resources/css/app.css` | Reglas aditivas `.pokemon-modal-scroll` (`scrollbar-width`, `scrollbar-color`, `::-webkit-scrollbar*`) | `frontend` |

No se crean archivos nuevos.

### Detalle del cambio Blade

- **Línea 5 (marco exterior):** sustituir `overflow-y-auto` por `overflow-hidden flex flex-col`; conservar `relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] pokemon-modal-quality` y `:class="calidadClase(detailPokemon)"`.
- **Línea 7 (cabecera):** añadir `shrink-0` a `flex items-start gap-4 p-6 border-b border-line`. No se mueve de sitio.
- **Línea 58 (cuerpo):** reutilizar el div existente y cambiar `class="p-6"` por `class="flex-1 min-h-0 overflow-y-auto p-6 pokemon-modal-scroll"`. No hace falta añadir un wrapper nuevo: este div ya envuelve todo el contenido hasta la línea 329.
- **Cierres:** no se altera el anidamiento; el cierre de línea 330 sigue cerrando el marco exterior.

### Detalle del cambio CSS

Añadir después de `.pokemon-modal-quality` (`resources/css/app.css:149`), sin tocar `.pokemon-quality` ni `.pokemon-card-quality`:

```css
.pokemon-modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(var(--quality-rgb), 0.45) transparent;
}

.pokemon-modal-scroll::-webkit-scrollbar {
    width: 8px;
}

.pokemon-modal-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.pokemon-modal-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(var(--quality-rgb), 0.45);
}

.pokemon-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--quality-rgb), 0.7);
}
```

`--quality-rgb` se hereda del marco exterior, por lo que la barra adopta el color de la calidad (dorado en `perfect`) sin duplicar clases.

## 6. Orden de ejecución y dependencias

1. **F1 — Blade:** cambiar las tres clases del partial. Depende de la aprobación de este plan.
2. **F2 — CSS:** añadir `.pokemon-modal-scroll`. Depende de F1 para poder aplicar la clase al scroller.
3. **F3 — Verificación estática:** `git diff --check`, `php artisan view:cache` + `view:clear`, `npm run build`. Depende de F1+F2.
4. **F4 — Revisión independiente:** `reviewer` contrasta diff con plan y criterios. Depende de F3.
5. **F5 — Cierre:** informe final y smoke manual del desarrollador. Depende de F4.

## 7. Criterios de aceptación verificables

1. Con un Pokémon de IV perfectos y contenido que desborde, el borde dorado y el glow se ven continuos en las cuatro esquinas, en tema claro y oscuro.
2. Existe un único scroll vertical en el modal; no aparecen dos barras ni scroll horizontal.
3. La cabecera (título, tipos, nivel/CP, Liberar, Cerrar) permanece visible mientras se desplaza el cuerpo.
4. La scrollbar es fina y del color de la calidad (o, como mínimo, no gris/blanca sobre el panel oscuro) y sigue siendo visible.
5. Scroll funcional con rueda, trackpad, teclado y táctil; Escape y clic en backdrop siguen cerrando.
6. No hay regresión visual en el retrato de calidad (`pokemon-quality`) ni en las tarjetas (`pokemon-card-quality`).
7. `php artisan view:cache` compila sin error y `npm run build` termina correctamente.
8. `git diff` solo contiene los dos archivos autorizados.

## 8. Verificación requerida

- **Estática:** `git diff --check`; `php artisan view:cache` y `view:clear`; `npm run build`; revisión del diff contra este plan.
- **Manual (desarrollador):** `/equipos` con un Pokémon perfect y uno normal, claro/oscuro, 360px/768px/1440px, zoom 200%; Chrome y Firefox; Tab/Shift+Tab, Escape y backdrop.
- **Sin tests:** no se ejecuta la suite ni se crean/modifican archivos en `tests/`.

## 9. Riesgos y mitigaciones

| Riesgo | Prob. | Mitigación |
|---|---|---|
| Doble scroll por dejar `overflow-y-auto` en el exterior | Media | Exterior `overflow-hidden`; único scroller en el cuerpo. Verificar visualmente. |
| El modal supera `94vh` por falta de `min-h-0` | Media | `flex-1 min-h-0` en el cuerpo; probar con contenido largo. |
| `overflow-hidden` recorta el glow del retrato | Baja | El retrato está a 24px del borde y su glow máximo es 22px; verificar en navegador. |
| Tematización inconsistente entre Chrome/Firefox/Safari | Baja | Estrategia conservadora: propiedades estándar + pseudo-elementos WebKit; degradación a barra fina nativa. |
| Regresión en tarjetas/retrato por tocar clases compartidas | Baja | No se modifica `.pokemon-quality` ni `.pokemon-card-quality`; solo se añade `.pokemon-modal-scroll`. |
| El bug no se reproduce con un Pokémon de contenido corto | Baja | Probar con un Pokémon con muchas evoluciones/movimientos que desborde. |
| Scroll horizontal accidental | Baja | Opcional `overflow-x-hidden` en el scroller si la verificación lo detecta. |

## 10. Rollback

- Revertir los cambios de los dos archivos autorizados (`resources/views/equipos/partials/modal-detalle-pokemon.blade.php`, `resources/css/app.css`).
- Cualquier `git restore`/`git checkout --` requiere aprobación explícita del desarrollador.
- No hay migraciones ni datos que revertir.

## 11. Aprobación

Este plan requiere aprobación explícita que mencione la versión (`v1`) antes de delegar la implementación. `approval.md` permanece `PENDIENTE` hasta entonces.
