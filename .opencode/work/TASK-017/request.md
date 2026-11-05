# TASK-017 — Solicitud

## Petición literal

> Añade que las imagenes se estiren para llegar a los maximos aunque se deformen, que tengan el mismo tamaño todas las secciones

## Interpretación

1. **Imágenes estiradas:** en las tarjetas de `/habitats`, la imagen debe ocupar todo el marco disponible, aunque se deforme. Esto implica `object-fill` (estirar ancho y alto al 100 %) en lugar de `object-contain`.
2. **Sin margen interno:** quitar `p-2` de la imagen para que llegue a los bordes del marco.
3. **Tamaño uniforme:** todas las tarjetas/secciones deben tener el mismo tamaño. El marco de imagen ya es `aspect-[3/2]` y el ancho lo fija el grid; se debe garantizar además que la zona del nombre no rompa la uniformidad (una línea, altura estable) y que las filas del grid sean homogéneas.

## Contexto

- Proyecto `/home/david/pokemon`, rama `task-011`.
- TASK-016 commiteó (`531dc32`) el marco 3:2 y la limpieza de favoritos.
- `resources/views/habitats/index.blade.php` está limpio en HEAD; solo `routes/habitats.php` conserva cambios preexistentes ajenos.
- Grid actual: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3`.
- Marco actual: `w-full aspect-[3/2] bg-surface-sunken flex items-center justify-center overflow-hidden`.
- Imagen actual: `w-full h-full object-contain p-2 group-hover:scale-[1.03] transition-transform duration-300`.

## Restricciones

- No cambiar el número de columnas ni el `gap` (6 columnas y `gap-3`).
- No tocar `/habitats/{id}`, `/pokedex`, `/equipos`, CSS compartido, rutas, migraciones ni tests.
- Sin tests.
- Commit scoped en `task-011`, sin push.
- Continuidad: no se lanzan `reviewer` ni `security` salvo indicación contraria.

## Criterio de cierre

- Imagen con `object-fill` y sin padding, ocupando todo el marco 3:2.
- Todas las tarjetas con la misma altura y la misma zona de imagen.
- Verificación estática, build y commit en `task-011`.
