# TASK-018 — Solicitud

## Petición literal

> En el detalle en la pokedex debe ponerse la ubicacion en la cabecera

> Ampliamos el ancho del modal, como en detalle de /equipos

## Interpretación

1. **Ubicación en la cabecera:** en el modal de detalle de la Pokédex, la ubicación (provincia + hábitat + enlace “Ir al hábitat”) debe mostrarse en la cabecera, junto al nombre/tipos, en lugar de quedar solo en la columna derecha del cuerpo.
2. **Ancho del modal:** ampliar el modal al estilo del detalle de `/equipos` (`w-[90vw] max-w-[90vw]`, `max-h-[94vh]`), manteniendo la cabecera fija y el cuerpo con scroll.
3. **Sin duplicar:** retirar el bloque “Ubicación” del cuerpo si la información ya está en la cabecera. Mantener el estado vacío “Sin hábitat asignado.”

## Contexto

- Proyecto `/home/david/pokemon`, rama `task-011`.
- `resources/views/pokedex/index.blade.php` está limpio en HEAD (`b3cb0b2`).
- Modal actual: `relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-panel bg-surface-raised shadow-modal lg:max-w-4xl`.
- Referencia `/equipos` (`resources/views/equipos/index.blade.php:852`): `relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-y-auto`.
- El contrato del detalle ya devuelve `locations[]` (y `location` como fallback) con `province_name`, `habitat_name`, `habitat_image`, `url`.
- La cabecera actual muestra sprite, `#id`, nombre, tipos y badge Atrapado.
- El bloque “Ubicación” actual está en la columna derecha del cuerpo, con imagen, provincia, nombre y botón.
- Cambio local ajeno del desarrollador: `resources/views/habitats/index.blade.php` tiene `gap-3` → `gap-6`; no se tocará.

## Restricciones

- Solo se modifica `resources/views/pokedex/index.blade.php`.
- No tocar backend, rutas, contrato, `/equipos`, `/habitats`, CSS compartido, migraciones ni tests.
- Mantener el modal solo lectura: sin quality/EV/IV ni acciones de escritura.
- Sin tests.
- Commit scoped en `task-011`, sin push.
- Continuidad: no se lanzan `reviewer` ni `security` salvo indicación contraria.

## Aclaración posterior

> No se cancela, en el header debe ir la miniatura de la ubicacion, a 128px max, a la derecha, y el boton de ir al habitat

Se rectifica el alcance: la cabecera debe incluir la **miniatura del hábitat** (máx. 128 px) a la derecha y el **botón “Ir al hábitat”**, además de la provincia y el nombre. En móvil la tarjeta de ubicación puede apilarse bajo la identidad para no comprimir el nombre ni los tipos; en escritorio (`lg`) va a la derecha.

## Criterio de cierre

- La cabecera muestra la miniatura del hábitat a la derecha (máx. 128 px), provincia, nombre y botón “Ir al hábitat”.
- La imagen no se duplica en el cuerpo; el bloque de ubicación del cuerpo se retira.
- El modal usa el ancho de `/equipos` (90vw) y mantiene cabecera fija + cuerpo con scroll.
- Verificación estática, build y commit en `task-011`.
