# TASK-058 — Revisión

- **Revisor:** `reviewer` independiente.
- **Fecha:** 2026-11-18.
- **Veredicto:** **APPROVED**.
- **Alcance revisado:** los 4 archivos de TASK-058. Excluidos los cambios
  preexistentes de TASK-049..057 y del tema.

## Criterios de aceptación

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | «Reclutar» habilitado al cargar | PASS (estático) | `_grid.blade.php:35`; `'' !== ''` → `false` → `removeAttribute('disabled')` |
| 2 | POST al pulsar; deshabilitado solo durante la petición | PASS (estático) | `reclutamiento.js:105` fija el string; `finally` `:121` lo limpia |
| 3 | «Confirmar descarte» habilitado | PASS | `_modal-descartar.blade.php:80` |
| 4 | «Descartar todos» habilitado | PASS | `_modal-descartar-todos.blade.php:35` |
| 5 | Modal de navbar igual | PASS | `modal-reclutamiento.blade.php:64` reutiliza `_grid`; los modales de descarte son includes globales |
| 6 | Ningún `:disabled` con string sin comparar | PASS | `grep` → 3 coincidencias, las 3 con `!== ''` |
| 7 | Terminada → `TERMINADA` + «Archivar» | PASS (diseño) | `paraUsuario` filtra `regreso IS NULL`; `puede_archivar = $estado->esTerminada()` |
| 8 | Detalle consistente con el panel | PASS | `detalle()` procesa y hace `refresh()` antes de presentar |
| 9 | Contratos JSON y HTML intactos | PASS | `{expediciones, total, generado_en}` y fragmento HTML con `no-store, private` |
| 10 | No procesa expediciones ajenas | PASS | `abort_unless` antes de `una()`; `paraUsuario` acota por `user_id` |
| 11 | Throttle y lock vigentes | PASS | `ProcesarExploracionesPendientes` no se modifica |
| 12 | Solo 4 archivos | PASS | `git diff --stat`: 4 files, +20/−9 |

## Hallazgos

### LOW 1 — `una()` también se invoca sobre expediciones cerradas

`src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php:97`.
El handler corta si `regreso !== null`, por lo que no hay recompensas duplicadas
ni corrupción; solo un dispatch no-op en el detalle de una expedición ya
terminada o archivada. Se recomienda como seguimiento añadir
`if ($exploracion->regreso === null)` antes de `una()`. **No bloquea.**

### LOW 2 — Comentario obsoleto en `routes/exploraciones.php:9`

Decía «el resto son solo lectura»; ahora el panel y el detalle procesan. Es deuda
documental y queda fuera del alcance autorizado. **No bloquea.**

### NIT 3 — `contenido()` no procesa

El modal de historial abierto de forma aislada puede mostrar una fila pendiente.
Es una consecuencia del alcance aprobado, no un defecto. **No bloquea.**

### NIT 4 — Docblock de `index()`

La advertencia de que procesa está a nivel de clase; es suficiente.

## Verificación ejecutada

- `git diff` de los 4 archivos: coincide con el plan y con `approval.md`.
- `php -l`: sin errores.
- `git diff --check`: limpio.
- `grep` de `accionEnCurso` y `:disabled`: sin strings sin comparar.
- Lectura de Alpine 3.16.3 (`module.esm.js:1445-1471, 1500-1535, 1557-1591`).
- Lectura de `ProcesarExploracionesPendientes`, handlers, `LaravelCommandBus`,
  `ConstructorExpedicionesResumen`, `ConsultarExpedicionesUsuario`,
  `CalculadorEstadoExpedicion` y `PresentadorExploraciones`.
- Sin tests, sin escrituras en BD.

## Riesgos residuales

- Verificación manual en navegador y BD pendiente (expedición 29).
- GET con efectos secundarios: idempotente, con throttle y lock; aceptado por
  precedente de `GET /exploraciones`.
- Ventana transitoria si el lock está ocupado por el comando programado.
- Latencia del panel acotada a 2 s y 10 expediciones.

## Recomendación

Aprobar y cerrar TASK-058. Seguimiento opcional: guard de `regreso === null`,
comentario de rutas y comprobación manual.
