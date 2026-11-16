# TASK-042 — Petición

- **Fecha:** 2026-11-10
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon` (Laravel 12, PHP 8.4, Alpine + Bootstrap/Tailwind, FontAwesome).
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Reformular nav bar. Exploraciones y reclutamiento pasan a ser fa icons, luego ajustamos el correcto
>
> La llamada a reclutamiento y exploraciones las cacheamos,
> cuando se añada un posible recluta o exploracion, se añade a la coleccion y reindexamos
>
> Esta informacion no suele actualizarse, y no es necesario que ocupe tanto espacio en el menu
>
> Pulsar en expediciones muestra un tool tipo a 3 columnas
> Pokemon | progreso (barra horizontal) | tiempo restante  - fa icon de flecha para ver detalle
> Despues se mostrara en la derecha, abajo, ver historial para cargar todas, aqui se cargara un modal con la misma informacion que la pagina
>
> Para reclutamiento, se abrira el modal al pulsar

## Interpretación preliminar (no vinculante)

1. **Navbar compacta:** sustituir las entradas de texto «Reclutamiento» y «Exploraciones» por iconos FontAwesome. La elección definitiva de iconos queda para ajuste posterior.
2. **Datos bajo demanda y cacheados:** evitar que la navbar ocupe espacio permanente con listados; cargar reclutamiento y expediciones activas en una colección cacheada y reindexable, incorporando nuevos elementos cuando se añadan sin recargar toda la colección.
3. **Panel de expediciones:** al pulsar el icono, mostrar un panel tipo popover/tooltip de tres columnas: Pokémon, progreso (barra horizontal) y tiempo restante, con icono de flecha para abrir el detalle.
4. **Historial:** en la parte inferior derecha del panel, un enlace «Ver historial» que carga todas las expediciones y abre un modal con la misma información que la página.
5. **Reclutamiento:** al pulsar el icono, abrir directamente un modal con la información/acciones equivalentes a la página de reclutamiento.

## Contexto técnico conocido

- Navbar actual: `resources/views/layouts/app.blade.php`; `$navItems` incluye `/reclutamiento` y `/exploraciones` como enlaces de texto.
- La página `resources/views/exploraciones/index.blade.php` recibe `activas` y `terminadas` desde `ExploracionActivaController::index()`; incluye un JS Alpine `exploracionesPage()` con acciones POST y recarga completa.
- La página `resources/views/reclutamiento/index.blade.php` recibe `$reclutables` desde `PlayerController::reclutamiento()`; usa Alpine `reclutamientoApp()` con estado local y llamadas POST a `/reclutamiento/recruit`, `/reclutamiento/discard` y `/reclutamiento/discard-all`.
- Las rutas relevantes están en `routes/player.php` y `routes/exploraciones.php`.
- FontAwesome está instalado (`@fortawesome/fontawesome-free`) y ya se usa en la navbar (cosecha) y otras vistas.
- El proyecto usa Alpine.js, Bootstrap y Tailwind en las vistas. No hay un store global evidente para estos datos.
- No existe un endpoint JSON específico de reclutamiento para la navbar ni un endpoint de historial de expediciones identificado en el contexto inicial.

## Restricciones conocidas

- No implementar sin plan aprobado explícitamente por el desarrollador.
- No modificar tests ni ejecutar la suite.
- No cambiar dependencias sin autorización.
- No leer ni exponer secretos.
- Respetar los cambios locales preexistentes de la rama `task-011`; no sobrescribirlos.
- Mantener el comportamiento actual de las páginas y de las acciones POST.
- No introducir lógica de negocio en Blade ni consultas en la navbar.
- La información mostrada debe respetar autenticación y propiedad de los datos (global scopes/policies existentes).

## Ambigüedades a resolver por el analista

- Si «expediciones» es sinónimo de «exploraciones activas» o si existe otro concepto en el repositorio.
- Qué significa exactamente «cachear la llamada»: caché de servidor, caché HTTP, o store/colección en cliente; y cuál es el punto de invalidación.
- Cómo se detectan los nuevos reclutas/exploraciones para insertarlos en la colección y reindexar (respuesta de los POST, evento de dominio, polling, etc.).
- Si el modal de historial debe reutilizar la página completa o solo el listado de expediciones terminadas.
- Si el panel debe ser hover, click o ambos, y cómo encaja en la navbar responsive/overflow actual.
- Si «archivar» una expedición completada la oculta, la elimina o la mueve a un historial separado, y si las archivadas siguen visibles en «Ver historial».

## Actualización del desarrollador (2026-11-10)

> las expediciones completadas tendran la opcion de archivarse a la derecha

- La opción de archivar aparece en la fila de una expedición completada, a la derecha.
- Queda pendiente concretar si archivar oculta, elimina o conserva la expedición, y cómo afecta a «Ver historial».
