# TASK-049 — Plan v1: modal de ruta, IV/EV colapsable y 3 columnas

- **Estado:** EJECUTADO (aprobado y verificado)
- **Versión:** v1
- **Fecha:** 2026-11-16
- **Tipo:** UI (Blade + Tailwind), sin backend.

## 1. Objetivo

Mejorar el modal de formación del combate de ruta:

1. Aumentar su tamaño para aprovechar el ancho de pantalla.
2. Hacer colapsable la leyenda IV/EV del rival.
3. Mostrar los 3 Pokémon del rival en 3 columnas en escritorio.

## 2. Alcance

- `resources/views/habitats/partials/modal-formacion.blade.php`
- `resources/views/partials/leyenda-ivs-evs.blade.php`

No se modifica `resources/views/gimnasios/show.blade.php`; su include usará los
defaults del partial.

## 3. Fuera de alcance

- Backend, contratos, datos, rutas, migraciones y tests.
- Rediseño del modal de gimnasio (se mantiene `max-w-md`, apilado y expandido).
- Persistencia del estado abierto/cerrado de la leyenda.
- Cambios en la lógica de comparación de PC o de perfiles IV/EV.

## 4. Decisiones de diseño

1. **Ancho:** `max-w-6xl` en el modal compartido de ruta/entrenadores (el mismo
   componente); el modal de gimnasio no se toca.
2. **Alto:** panel `flex max-h-[90vh] flex-col`; cabecera y botones fijos, cuerpo con
   scroll (`min-h-0 flex-1 overflow-y-auto`).
3. **Colapsable:** `<details>/<summary>` nativo, cerrado por defecto en el modal de
   formación. Sin `x-collapse` (plugin no instalado).
4. **Estado compacto:** el `<summary>` muestra el perfil actual, `Cargando…` o
   `No disponible` en un `aria-live="polite"`.
5. **Rejilla:** `grid grid-cols-1 gap-3 xl:grid-cols-3` para el desglose por Pokémon;
   cada tarjeta `min-w-0`. Las tablas mantienen `overflow-x-auto`.
6. **Parametrización:** `columnas` (default 1) y `colapsable` (default false) en la
   leyenda; el modal de formación pasa `3` y `true`.
7. **Gimnasio:** include sin parámetros → mismo DOM y comportamiento actual.

## 5. Archivos y responsables

| Archivo | Acción | Responsable |
|---|---|---|
| `resources/views/habitats/partials/modal-formacion.blade.php` | Ancho, estructura flex y include parametrizado | `frontend` |
| `resources/views/partials/leyenda-ivs-evs.blade.php` | Colapsable condicional y rejilla de 3 columnas | `frontend` |

No se autoriza tocar ningún otro archivo.

## 6. Orden de ejecución

1. Modificar la leyenda para aceptar `columnas` y `colapsable`, conservando el DOM
   actual cuando ambos son defaults.
2. Modificar el modal de formación: `max-w-6xl`, estructura flex y include con
   `['columnas' => 3, 'colapsable' => true]`.
3. `npm run build`.
4. Verificación manual en navegador (sin tests).
5. Revisión independiente.

## 7. Criterios de aceptación

1. El modal de ruta mide hasta `max-w-6xl`, no se recorta en viewports bajos y los
   botones quedan siempre visibles.
2. La leyenda IV/EV aparece cerrada por defecto en el modal de formación, con
   `<summary>` accesible por teclado y chevron que rota.
3. El desglose de los 3 rivales se muestra en 3 columnas a partir de `xl` (1280 px) y
   en 1 columna por debajo; no hay scroll horizontal de página.
4. El modal de gimnasio conserva `max-w-md`, el apilado vertical y la leyenda expandida.
5. El estado `pcCargando`/`pcError`/perfil actual es visible en el summary.
6. `npm run build` termina sin errores y las clases nuevas están en el CSS emitido.
7. Solo se modifican los dos archivos autorizados; no se tocan tests ni backend.
8. Verificación manual: 360, 768, 1024, 1440 px; teclado; red lenta; modal de gimnasio.

## 8. Verificación requerida

- `npm run build`.
- `grep` de `max-w-6xl`, `xl:grid-cols-3` y `group-open:rotate-180` en
  `public/build/assets/app-*.css`.
- Revisión visual/manual en navegador:
  - Hábitat → Combate de Ruta → modal de formación.
  - Mismo modal en modo entrenadores.
  - Gimnasio → modal de formación (regresión).
  - Teclado: Tab al summary, Enter/Espacio, Escape.
  - Red lenta: estados de carga/error.
- Sin tests.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Recorte vertical en pantallas bajas | `max-h-[90vh]` + cuerpo flexible con scroll |
| 3 columnas estrechas a 1024-1279 px | `xl:grid-cols-3`; tablas con scroll interno |
| `aria-live` no anunciado con la leyenda cerrada | Estado compacto con `aria-live` en el summary |
| Regresión en gimnasio | Defaults `columnas=1`, `colapsable=false`; no tocar su include |
| Marker nativo en Safari | `[&::-webkit-details-marker]:hidden` |
| Clases no compiladas | `npm run build` y comprobación del CSS |

## 10. Rollback

Revertir los dos archivos a su versión anterior (`git checkout --` solo con
autorización del desarrollador). No hay cambios de datos ni de esquema.

## 11. Aprobación

No se implementa nada hasta que el desarrollador responda `Aprobar plan v1` o una
fórmula equivalente que mencione la versión.
