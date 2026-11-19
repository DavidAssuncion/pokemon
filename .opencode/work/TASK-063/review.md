# TASK-063 — Revisión

- **ID:** TASK-063
- **Estado:** APROBADO
- **Revisor:** `reviewer`
- **Fecha:** 2026-11-19

## Veredicto

**APROBADO.**

El fix cumple el plan v1 aprobado (`approval.md`, texto literal "Aprobar plan
v1"). El diff se limita a los tres hunks autorizados en los dos archivos y hace
exactamente lo previsto: elimina la variable `$nuevosDebilitados` y el `if (...)
{ $actor->otorgarAccionExtra(); }`, conservando `$batalla->procesarDebilitamientos();`
en los tres puntos originales y en `aplicarMovimientoAliado()`. No quedan
invocaciones de `otorgarAccionExtra()` en `src/` ni `app/` (solo definiciones y sus
delegaciones internas), no hay variables muertas, no se toca serialización,
`SESSION_VERSION` ni las guardas de TASK-040. Verificación estática dirigida en
verde. Único punto abierto: la comprobación manual de criterios 2 y 3 (KO real en
batalla y objeto que debilita), que corresponde al desarrollador.

## Hallazgos

- **[NIT] `.opencode/work/TASK-063/execution.md`** — el artefacto cerraba con
  `Estado: DESIGNING` y no registraba la implementación del `backend`. Impacto: solo
  trazabilidad; no afecta al código. Corregido por el orchestrator.
- **[NIT] `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:126`** — la
  llamada interna a `procesarDebilitamientos()` sigue siendo redundante con
  `MotorRondas::ejecutarTurnosDeRonda()` (línea 159). Es intencional
  (`architecture.md`, riesgos) para no alterar el orden respecto a los efectos de
  fin de turno; idempotente. Sin acción.
- **[NIT] `ResolutorAccionBatalla.php:138,237` y `EjecutorAccion.php:126`** — el
  retorno `int` de `procesarDebilitamientos()` queda sin usar. Cosmético; cambiar la
  firma está fuera de alcance y afectaría a otros consumidores. Sin acción.

## Fortalezas

- Corrección mínima y quirúrgica: 3 líneas insertadas y 12 eliminadas, sin
  abstracciones nuevas ni condiciones por habilidad.
- Elimina la regla de dominio de la capa de presentación (coherente con
  `.ai/rules/arrays-dto-collections.md:69`) sin duplicarla en Livewire.
- Conserva íntegros los efectos de `procesarDebilitamientos()`:
  `notificarDebilitamiento()` mantiene `dispararDebilitamiento`, `notifyFainted` y
  `sincronizarGasReactivo`.
- Capacidad de turno extra preservada (`EstadoTurno::otorgarAccionExtra`,
  `accionesExtra`/`extraOtorgada`, pool de `GestorTurnos`) para el futuro efecto
  `ReaccionaAlDebilitarRival`, cuyo despacho compartido ya existe
  (`AplicadorEfectosPostImpacto.php:164`, `ColeccionEfectos.php:373-377`).
- Sin invocaciones residuales en `tests/` ni `resources/`.
- Sin cambios en `RegistroDanio::puedeUsarProteccion` ni en las guardas de
  TASK-040/Truant/Inicio Lento.

## Verificación ejecutada

- `git diff -U0`: exactamente 3 hunks (`ResolutorAccionBatalla.php:138,240`;
  `EjecutorAccion.php:126`), sin cambios fuera de ellos.
- `grep -rn "otorgarAccionExtra" src app`: 5 líneas, todas definiciones/delegaciones.
- `grep -rn "nuevosDebilitados" src app`: sin referencias.
- `grep -rn "procesarDebilitamientos" src app`: los tres puntos + `aplicarMovimientoAliado`.
- `php -l`: OK en ambos archivos.
- PHPStan dirigido: `[OK] No errors`.
- Pint dirigido: `pass`.
- `composer analyse`: 35 errores preexistentes; ninguno menciona los archivos de
  TASK-063.
- `composer arch`: 105 violaciones preexistentes; ninguna menciona los archivos de
  TASK-063.
- Serialización: `SESSION_VERSION = 21` intacto; claves `accionesExtra`/`extraOtorgada`
  presentes.
- `git status`: solo los 2 archivos de TASK-063 además de cambios ajenos
  preexistentes (TASK-061/TASK-064).
- Sin ejecutar tests (fuera de alcance).

## Riesgos residuales

- Batallas ya serializadas con `accionesExtra = 1` conservan un extra hasta
  consumirlo o abrir ronda. Aceptado, sin cambio de esquema.
- `otorgarAccionExtra()` sin llamadores de producción hasta que exista la habilidad
  futura. API pública intencional.
- Menos acciones por ronda en batallas multijugador; validación manual 3v3
  pendiente del desarrollador.
- Reintroducción futura de la regla en Livewire: mitigada por el criterio 1 y el
  contrato de `architecture.md`.
