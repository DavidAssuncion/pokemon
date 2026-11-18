# TASK-058 — Plan v1: reclutamiento bloqueado y expediciones sin resolver

- **ID:** TASK-058
- **Versión:** v1
- **Fecha:** 2026-11-17
- **Proyecto:** `/home/david/pokemon`
- **Estado:** EJECUTADO (v1 aprobado, implementado y revisado)

## 1. Objetivo

Corregir dos fallos funcionales:

1. **Reclutamiento:** los botones «Reclutar», «Confirmar descarte» y «Descartar
   todos» están deshabilitados desde la carga y nunca ejecutan la acción.
2. **Expediciones:** una expedición terminada se queda en «Pendiente de procesar»
   al abrir el panel y el modal de detalle, sin acciones disponibles.

## 2. Causa raíz

### 2.1 Reclutamiento

`accionEnCurso` es un **string** que vale `''` cuando no hay acción
(`resources/js/stores/reclutamiento.js:24`). Las tres vistas lo bindean
directamente a `disabled`:

```blade
:disabled="$store.reclutamientoNavbar.accionEnCurso"
```

Alpine 3.16.3 (`node_modules/alpinejs/dist/module.esm.js`) trata `disabled` como
atributo booleano en `bindAttribute()` (`:1513-1524`): solo elimina el atributo
para `null`, `undefined` o `false`; cualquier otro valor —incluido `''`— hace
`setAttribute('disabled', 'disabled')`. `bind()` (`:1445-1471`) solo usa la
propiedad para `checked`/`selected`, no para `disabled`.

Resultado: con `accionEnCurso = ''` los botones quedan deshabilitados al cargar.
Como el estado solo se limpia dentro de la acción que el botón bloquea, se
produce un bloqueo mutuo: no se puede reclutar ni confirmar un descarte.

Evidencia adicional:

- `grep` de `:disabled` en reclutamiento devuelve exactamente las tres vistas
  afectadas.
- El resto de `:disabled` del layout usa booleanos (`cargando`,
  `historialCargando`) y no tiene el problema.
- La página y la API devuelven datos correctos (13 reclutables para el usuario 2),
  por lo que no hay fallo de backend.

### 2.2 Expediciones

`ExpedicionesPanelController` declara en su docblock que sus GET no procesan
(`:20-27`). El panel se abre con `GET /api/exploraciones` (`index()`), que solo
consulta el resumen; el modal de detalle usa `detalle()`, que tampoco procesa.
`CalculadorEstadoExpedicion` devuelve `PENDIENTE_RESOLUCION` mientras `regreso`
sea `null`, y `PresentadorExploraciones:786` lo pinta como «Pendiente de
procesar». Para una expedición no indefinida no hay «Recoger» (`puede_recoger`
exige `indefinido`) ni «Archivar» (`puede_archivar` exige `TERMINADA`): la fila
queda sin acciones.

Evidencia en BD: expedición `id=29`, `user_id=2`, `fin=2026-11-17 21:38:46`,
`regreso=null`, `archived_at=null`, `eventos.ultimo_procesado=null`; a fecha
2026-11-18 sigue en `pendiente_resolucion`.

La opción «detectar que ya terminó sin procesar» se rechaza: rompería `recoger`
y permitiría archivar sin recompensas. Se adopta la opción que el desarrollador
planteó primero: procesar al abrir el panel/modal.

## 3. Alcance

### Incluido

1. **Fallo 1 (frontend, 3 líneas):**
   - `resources/views/reclutamiento/partials/_grid.blade.php:35`
   - `resources/views/reclutamiento/partials/_modal-descartar.blade.php:80`
   - `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php:35`

   Cambiar `:disabled="$store.reclutamientoNavbar.accionEnCurso"` por
   `:disabled="$store.reclutamientoNavbar.accionEnCurso !== ''"`.
   Sin cambios en el store: `accionEnCurso` sigue siendo el string que ya usa la
   lógica de concurrencia.

2. **Fallo 2 (backend, 1 archivo):**
   - `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`
     - Inyectar `ProcesarExploracionesPendientes` como
       `private readonly ProcesarExploracionesPendientes $pendientes`.
     - `index()`: obtener `$userId = (int) Auth::id()` y llamar
       `$this->pendientes->paraUsuario($userId)` **antes** de
       `$this->resumenes->panel($userId)`.
     - `detalle(ExploracionActiva $exploracion)`: tras las comprobaciones de
       propiedad y cancelación, llamar `$this->pendientes->una($exploracion)` y
       `$exploracion->refresh()` antes de cargar relaciones y presentar.
     - Actualizar el docblock de la clase: los GET del panel ahora disparan el
       mismo procesamiento bajo demanda, con throttle y lock, que
       `GET /exploraciones`.

### Fuera de alcance

- El error global `Cannot redefine property: $persist` (doble arranque de Alpine).
  Es un problema de arranque de la aplicación, no de reclutamiento; se registra
  como deuda separada y no se toca aquí.
- `CalculadorEstadoExpedicion`, `EstadoExpedicion`, `PresentadorExploraciones`,
  `ConsultorEstadoExpedicion` y el contrato `{etapa, nombre}` de gimnasios.
- El store `reclutamiento.js` y el store `expediciones.js`.
- Migraciones, esquema, seeders y datos.
- `resources/css/*` (TASK-051), `resources/views/equipos/*` (TASK-050),
  `gimnasios/show.blade.php` (TASK-050/053) y el resto de tareas en curso.
- Tests. No se crean, modifican ni ejecutan.

## 4. Archivos y responsables

| Archivo | Acción | Responsable |
|---|---|---|
| `resources/views/reclutamiento/partials/_grid.blade.php` | `:disabled` booleano | `frontend` |
| `resources/views/reclutamiento/partials/_modal-descartar.blade.php` | `:disabled` booleano | `frontend` |
| `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php` | `:disabled` booleano | `frontend` |
| `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` | Procesar en `index()` y `detalle()` | `backend` |

No se crea ningún archivo. No se toca ningún otro.

## 5. Orden de ejecución y dependencias

1. **`frontend`**: las 3 vistas de reclutamiento. Independiente.
2. **`backend`**: `ExpedicionesPanelController`. Independiente.
3. **`reviewer`**: revisión del diff completo.
4. **Cierre**: informe final.

Ambos pasos pueden ejecutarse en paralelo: no comparten archivos.

## 6. Criterios de aceptación

### Reclutamiento

1. Al cargar `/reclutamiento`, el botón «Reclutar» de cada tarjeta está
   habilitado.
2. Al pulsar «Reclutar», se ejecuta el POST y la lista se actualiza; el botón se
   deshabilita solo durante la petición y se rehabilita al terminar.
3. «Descartar» abre el modal y «Confirmar descarte» está habilitado.
4. «Descartar todos» abre su modal y el botón de confirmación está habilitado.
5. El modal de la navbar (`navbar-reclutamiento`) se comporta igual.
6. Ningún `:disabled` de reclutamiento queda bindeado a un string sin comparar.

### Expediciones

7. Al abrir el panel con una expedición terminada, deja de mostrarse
   «Pendiente de procesar» y pasa a `TERMINADA`, con la acción «Archivar»
   disponible.
8. Al abrir el modal de detalle de una expedición terminada, el estado es
   consistente con el panel.
9. `GET /api/exploraciones` sigue devolviendo `{expediciones, total, generado_en}`
   y `GET /exploraciones/{id}/panel/detalle` sigue devolviendo el fragmento HTML.
10. No se procesan expediciones de otro usuario: `paraUsuario()` acota por
    `user_id` y `detalle()` mantiene `abort_unless($exploracion->user_id === $userId, 404)`.
11. El throttle de 30 s y el lock por expedición siguen vigentes: abrir el panel
    varias veces no reprocesa ni duplica recompensas.
12. No se modifica ningún archivo fuera de los 4 autorizados.

## 7. Verificación requerida (sin tests)

- `php -l src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`.
- `vendor/bin/pint --dirty --format agent`.
- `php artisan view:cache` y `php artisan view:clear`.
- `git diff --check`.
- `git status --short`: solo los 4 archivos autorizados (más los cambios
  preexistentes ajenos).
- Comprobación manual:
  - `/reclutamiento`: reclutar 1, descartar 1 y descartar todos.
  - Modal de navbar: reclutar y descartar.
  - Panel de expediciones: abrir con la expedición 29 (ya terminada) y comprobar
    el cambio de estado y la acción «Archivar».
  - Modal de detalle de la misma expedición.
  - Consola del navegador: sin errores nuevos.
- Comprobación en BD (solo lectura): tras abrir el panel, `regreso` de la
  expedición 29 deja de ser `null` y `eventos.ultimo_procesado` queda informado.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Procesar en un GET muta estado | Ya existe el precedente en `GET /exploraciones`; se mantiene throttle y lock. El procesamiento es idempotente por expedición. |
| Latencia al abrir el panel | `paraUsuario` usa presupuesto de 2 s y límite de 10; el lock es no bloqueante. |
| Instancia obsoleta tras procesar en `detalle()` | `refresh()` inmediato antes de presentar. |
| Lock ocupado por el comando programado | `una()`/`paraUsuario()` devuelven `false`/omiten; la fila se resolverá en la siguiente apertura. |
| Regresión en reclutamiento | Solo cambia la expresión de `disabled`; la lógica del store no se toca. |
| Conflicto con el árbol sucio | Solo se tocan 4 archivos; los cambios de TASK-049..057 no se editan. |

## 9. Rollback

- Revertir las 3 expresiones `:disabled` a su forma anterior.
- Revertir `ExpedicionesPanelController` a su versión sin inyección ni llamadas
  de procesamiento.
- No hay cambios de datos, esquema ni assets.

## 10. Aprobación

No se implementa nada hasta que el desarrollador responda `Aprobar plan v1` o una
fórmula equivalente que mencione la versión.
