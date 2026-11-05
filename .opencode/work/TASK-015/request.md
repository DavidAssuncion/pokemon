# TASK-015 — Solicitud

## Petición literal

> las imagenes de los habitats suelen ser rectangulares, pero nuestro formato es cuadrado, perdemos mucho detalle, debemos hacerlo mas fiel para no perder la esencia

> En la pokedex, puslar en el detalle del pokemon deberia permitirme ir a la ruta donde se captura y ver el detalle del pokemon y su familia, como en /equipos, pero sin quality o posibilidad de dar ev ni mostrar EV IV, tambien debes corrigir los tamaños, ahora mismos hay mucho espacio en blanco con

> No lances reviewer ni security

> Commitea sobre la rama ya en curso

## Interpretación acordada con el desarrollador

1. **Imágenes de hábitat:** mantener la tarjeta cuadrada, pero mostrar la imagen completa sin recorte (`object-contain`) en `/habitats`. Las imágenes de `resources/habitats_img/` son apaisadas (~1,4–1,5:1) y hoy se recortan con `object-cover`; las laterales pierden detalle.
2. **Listado de la Pokédex:** corregir el espacio en blanco/desaprovechado del grid de tarjetas.
3. **Detalle de la Pokédex:** ampliar el modal actual para que se parezca a `/equipos` en estructura (detalle de especie + familia evolutiva), pero en **solo lectura**:
   - sin “quality”/calidad por IV,
   - sin posibilidad de dar EV,
   - sin mostrar EV ni IV.
   - Debe permitir ir a la ruta/hábitat donde se captura.
4. **Exclusiones explícitas:** no lanzar `reviewer` ni `security`.
5. **Git:** commit sobre la rama ya en curso (`task-011`), solo con los archivos de esta tarea. Sin push.

## Contexto

- Proyecto: `/home/david/pokemon` — Laravel 12, PHP 8.4, Blade + Alpine.
- Rama actual: `task-011` con cambios locales preexistentes no relacionados (51 archivos modificados y varios untracked). No se deben sobrescribir ni commitear.
- `/habitats` (`resources/views/habitats/index.blade.php`) pinta cada hábitat en un contenedor `aspect-square` con `object-cover`.
- Las imágenes reales de `resources/habitats_img/` son WebP/PNG apaisados: la mayoría entre 456–528 × 240–360 px.
- La Pokédex (`resources/views/pokedex/index.blade.php`) es una vista Alpine con grid infinito y modal de detalle.
- El detalle de la Pokédex consume `/datagrid/pokemon/{id}/detalle`, servido por `DatagridServiceProvider` y construido por `App\Support\PresentadorDetallePokemon` (tipos, stats base, ubicación y familia).
- `/equipos` (`resources/views/equipos/index.blade.php`) tiene un modal ancho de detalle con evolución, habilidades, movimientos, stats, EV/IV y “quality”. Es la referencia visual, no la funcionalidad a copiar.
- Existe un read-model de especie (`App\Support\PresentadorDetalleEspecie`) con habilidades, learnset y metadatos, expuesto en `/api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`.

## Restricciones

- Sin tests: `tests/` fuera de alcance; no se crean, actualizan ni ejecutan.
- Sin `reviewer` ni `security` por indicación del desarrollador; se reflejará como `no aplica` en los artefactos.
- No tocar bases de datos de producción ni instalar/actualizar dependencias sin aprobación.
- No modificar archivos fuera del alcance aprobado.
- No hacer `git push`, `git reset --hard`, `git clean` ni `git checkout --`.
- El commit debe incluir únicamente archivos de TASK-015 y hacerse sobre `task-011`.
- UI en español; PHP nuevo con `declare(strict_types=1);`.
- No romper el detalle actual de `/equipos`, la navegación por hábitats ni la Pokédex.

## Criterio de cierre

- Imágenes de hábitat completas y sin recorte en `/habitats`, con tarjetas uniformes y sin bandas que parezcan un error visual.
- Grid de la Pokédex sin espacio muerto relevante en los tamaños habituales.
- Modal de la Pokédex con estructura de detalle + familia similar a `/equipos`, solo lectura, sin quality/EV/IV, con enlace operativo a la ruta del hábitat.
- Verificación estática y comprobación manual de criterios.
- Commit scoped en `task-011` e informe final.
