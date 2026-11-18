# TASK-058 — Informe final

## Resumen

Se corrigieron dos fallos funcionales:

1. **Reclutamiento:** «Reclutar», «Confirmar descarte» y «Descartar todos» estaban
   deshabilitados desde la carga de la página porque `accionEnCurso` (string, `''`
   en reposo) estaba bindeado directamente a `disabled`. Alpine 3.16.3 trata
   `disabled` como atributo booleano y lo aplica para cualquier valor que no sea
   `null`, `undefined` o `false`; con `''` el botón nacía bloqueado y el estado
   solo se limpiaba dentro de la acción bloqueada.
2. **Expediciones:** el panel y el modal de detalle no disparaban el
   procesamiento bajo demanda, así que una expedición terminada se quedaba en
   «Pendiente de procesar» y, al no ser indefinida, no ofrecía «Recoger» ni
   «Archivar». Ahora `index()` y `detalle()` procesan con el throttle y el lock
   ya existentes.

## Estado final

**COMPLETED** — revisión independiente **APPROVED**, sin BLOCKER/HIGH/MEDIUM.
La verificación manual en navegador y BD queda pendiente del desarrollador.

## Causa raíz

### Reclutamiento

`resources/js/stores/reclutamiento.js:24` inicializa `accionEnCurso: ''`.
Las tres vistas lo bindeaban como `:disabled="$store.reclutamientoNavbar.accionEnCurso"`.
En Alpine 3.16.3, `bindAttribute()` (`node_modules/alpinejs/dist/module.esm.js:1513-1524`)
elimina el atributo solo para `null`, `undefined` o `false`; para `''` ejecuta
`setAttribute('disabled', 'disabled')`. `bind()` solo usa la propiedad para
`checked`/`selected` (`:1445-1471`). Resultado: deadlock.

### Expediciones

`ExpedicionesPanelController` solo consultaba. `CalculadorEstadoExpedicion`
devuelve `PENDIENTE_RESOLUCION` mientras `regreso` sea `null`, y
`PresentadorExploraciones:786` lo pinta como «Pendiente de procesar».
Evidencia: expedición `id=29`, `user_id=2`, `fin=2026-11-17 21:38:46`,
`regreso=null`, `eventos.ultimo_procesado=null`, aún pendiente el 2026-11-18.

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `resources/views/reclutamiento/partials/_grid.blade.php` | `:disabled="accionEnCurso !== ''"` |
| `resources/views/reclutamiento/partials/_modal-descartar.blade.php` | idem |
| `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php` | idem |
| `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` | `paraUsuario()` en `index()`; `una()` + `refresh()` en `detalle()`; docblock |

Sin cambios en stores JS, dominio, migraciones, datos ni assets.

## Verificación

- `php -l` del controlador: OK.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache`: OK; `view:clear`: OK.
- `git diff --check`: limpio.
- `git status --short`: solo los 4 archivos autorizados (más los cambios
  preexistentes de TASK-049..057 y el tema).
- `grep` de `:disabled` en reclutamiento: 3 usos, los 3 con `!== ''`.
- `route:list --path=exploraciones`: 11 rutas sin cambios.
- Revisión independiente: **APPROVED**; 12/12 criterios PASS.
- Sin tests. No se procesó ninguna expedición durante la verificación.

## Revisión y seguridad

- `reviewer`: APPROVED. Hallazgos LOW: `una()` se invoca también sobre
  expediciones cerradas (no-op) y el comentario de `routes/exploraciones.php:9`
  quedó obsoleto. No bloquean.
- `security`: no aplica. Sin entradas, permisos, endpoints ni secretos nuevos.
  Se mantiene la autorización por `user_id` antes de procesar.

## Riesgos residuales

- **Verificación manual pendiente:** reclutar/descartar en página y navbar;
  abrir el panel con la expedición 29 y comprobar el cambio a terminada y la
  acción «Archivar»; abrir su detalle.
- **GET con efectos secundarios:** idempotente, con throttle de 30 s y lock de
  60 s; mismo patrón que `GET /exploraciones`.
- **Ventana transitoria:** si el comando programado tiene el lock, el panel puede
  mostrar «Pendiente» una vez; se resuelve en la siguiente apertura.
- **Deuda menor:** guard `regreso === null` antes de `una()` y comentario de
  rutas.
- **Commit:** los 4 archivos están sin commitear; no se hizo push.

## Próximos pasos

1. Probar `/reclutamiento`: los botones deben estar habilitados; reclutar y
   descartar deben actualizar la lista.
2. Probar el modal de la navbar.
3. Abrir el panel de expediciones: la expedición 29 debe pasar a terminada.
4. Abrir su detalle y comprobar la coherencia.
5. Confirmar en BD que `regreso` ya no es `null` y que
   `eventos.ultimo_procesado` está informado.
