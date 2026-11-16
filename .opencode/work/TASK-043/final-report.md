# TASK-043 — Informe final

- **Fecha:** 2026-11-10
- **Estado:** COMPLETADO (análisis y diseño; sin implementación)
- **Tipo:** investigación de producto + diseño de sistema, sin cambios de código.

## 1. Resumen de la tarea

El desarrollador pidió analizar la implementación de un sistema de misiones para los hábitats (diarias, semanales, únicas), con recompensas, uso narrativo y desbloqueo progresivo de hábitats, además de un resumen a alto nivel del juego actual y propuestas para que resulte divertido.

Se ejecutaron dos análisis en paralelo (funcional y técnico) y un diseño posterior por el `architect`. El resultado es:

- Un resumen funcional del juego actual con evidencia.
- Un inventario técnico de datos, eventos, recompensas, tiempo, persistencia y UI.
- Una propuesta de diseño: **«El Cuaderno de la Expedición»**, con campaña de desbloqueo, encargos procedurales y diario de campo.
- Un plan v1 propuesto para un MVP de validación (no autorizado).

## 2. Estado final

`RECEIVED → ANALYZING → DESIGNING → COMPLETED`

No se solicitó implementación. `approval.md` permanece en `PENDIENTE`; el plan v1 es una propuesta y no autoriza cambios.

## 3. Archivos creados o modificados

Solo artefactos de coordinación en `.opencode/work/TASK-043/`:

| Archivo | Contenido |
|---|---|
| `request.md` | Petición literal y alcance. |
| `analysis.md` | Parte A: resumen funcional (analyst). Parte B: inventario técnico (analyst). |
| `architecture.md` | Propuesta de diseño del `architect`. |
| `plan.md` | Plan v1 propuesto para el MVP (no autorizado). |
| `approval.md` | Sin aprobación; no hay implementación. |
| `tasks.md` | Delegaciones y sesiones. |
| `execution.md` | Preflight y registro de ejecución. |
| `review.md` | No aplica (sin cambios de código). |
| `security.md` | No aplica (sin cambios de código). |
| `final-report.md` | Este informe. |

No se modificó ningún archivo de aplicación, migración, vista, configuración ni documentación del proyecto.

## 4. Decisiones y propuestas principales

### Resumen del juego actual (analistas)

- Bucle: elegir hábitat → combate de ruta 3v3 (ilimitado) → EXP/caramelos/capturas → reforzar equipo → sistemas laterales.
- Sistemas: ruta, entrenadores (9 victorias/día/hábitat), exploraciones 1v1, mazmorras, 18 gimnasios/medallas, granjas, forja, huevos, reclutamiento/evolución, Pokédex, equipos de 3, Sendas de tipo e inventario. No hay PvP.
- Economía sin moneda: EXP, caramelos de familia/EV/tipo, bayas/bonguris/objetos, capturas en cola, reclutados y medallas. Multiplicadores fijos por actividad.
- Progresión con ejes independientes: nivel de cuenta, medallas, pisos de mazmorra, Pokédex, colección, IV/EV/CP y Sendas.
- **Todos los hábitats están abiertos**; `min_lvl_1/2/3` existe pero ningún código lo escribe y solo afecta a exploración.
- Cadencia desigual: entrenadores diarios; ruta sin límite; mazmorra con cooldown; huevos/plantaciones on-read. No hay resets programados.
- No hay narrativa, NPCs ni trasfondo; solo nombres de provincias/hábitats, colores de bonguri y medallas.
- Existe un stub de vista `/misiones` sin rutas, controlador, modelo ni tabla; el enlace del nav está comentado.

### Propuesta del arquitecto

**«El Cuaderno de la Expedición»**, un sistema con tres capas:

1. **Campaña de la Expedición:** 8 capítulos por provincia; la «Bruma» cierra hábitats y los hitos los reabren. Los gimnasios son faros y cierran capítulo.
2. **Encargos de campo:** 3 diarios + 1 parte diaria, aceptación pasiva, progreso automático, recompensa automática.
3. **Diario de campo:** notas de una línea por hábitat, sin diálogos ramificados.

Decisiones clave:

- Cuatro tipos: encargo diario, parte diaria, expedición semanal (elegir 1 de 3) e hito de campaña.
- Sin moneda nueva: recompensas en caramelos, objetos, capturas y, excepcionalmente, huevo/reclutado.
- Generación determinista por usuario + periodo + hábitat + slot; cantidades por presupuesto de tiempo, no escaladas por nivel.
- «Capturar» cuenta en el momento de la captura (`reclutables` o reclutado directo), no al reclutar.
- Corte diario a medianoche `Europe/Madrid`; semanal lunes 00:00; todo on-read, sin depender del scheduler.
- Ledger de eventos idempotente + contadores de progreso; recompensa automática.
- Desbloqueo blando: campaña **o** nivel (lo que llegue antes); los hábitats bloqueados se ven; los jugadores existentes conservan todo abierto (`legacy`).
- Reescribir el stub de `/misiones`: conservar tarjeta, barra de progreso y estados; descartar la misión única con «pelear».
- MVP: solo diaria + 3 encargos + 6 arquetipos, sin campaña, semanal ni desbloqueo, detrás de un flag; validar el bucle antes de escribir la campaña.

## 5. Verificación ejecutada

- **Análisis documental y de código:** dos analistas con evidencia archivo:línea; informe del arquitecto basado en ellos.
- **Comprobaciones puntuales del coordinador (solo lectura):**
  - No hay rutas/controlador/modelo de misiones (`grep` en `routes/`, `app/Http`, `src/`).
  - El stub `resources/views/misiones/index.blade.php` existe y el enlace del nav está comentado.
  - Ningún writer de `min_lvl_1/2/3` en `app/`, `src/` ni seeders.
  - `storage/data/habitats.csv` tiene 57 líneas (cabecera + 56 hábitats).
  - El árbol de trabajo sigue con los cambios preexistentes del preflight; no se tocó.
- **Análisis estático:** no aplica; no hubo cambios de código.
- **Tests:** no se crearon, modificaron ni ejecutaron, conforme a la política del entorno.
- **Revisión y seguridad:** no aplica en esta fase; se requieren en la futura implementación.

## 6. Hallazgos de revisión y seguridad

- No hay revisión de código porque no hay código nuevo.
- No hay auditoría de seguridad porque no hay endpoints, permisos ni recompensas implementadas.
- En la futura implementación, la auditoría de seguridad deberá cubrir: autorización por usuario (evitar IDOR en `mission_id`), integridad de recompensas, idempotencia, validación server-side de objetivos y protección contra manipulación de periodo/flag.

## 7. Riesgos residuales

1. **Los dos analistas alcanzaron el límite de pasos.** Los informes son extensos y con evidencia, pero algunas cifras y estados de datos no se verificaron en la base de datos real (por ejemplo, valores efectivos de `min_lvl` o configuraciones de mazmorra).
2. **La propuesta económica no está probada en juego.** Los topes semanales y las cantidades son orientativos; requieren playtest y ajuste.
3. **El ledger de eventos es la pieza técnica más delicada.** Implica instrumentar ruta, entrenador, gimnasio, mazmorra, exploración, huevo, forja y granja sin duplicar ni perder eventos.
4. **El desbloqueo de hábitats afecta a jugadores existentes.** La regla de abuelo está propuesta, pero hay que validar el estado real de las cuentas y la percepción de los veteranos.
5. **La vista stub asume un contrato incompatible** (misión única, aceptar con reclutado/hábitat, pelear). Reutilizarla sin reescribirla generaría deuda y confusión.
6. **El árbol de trabajo está sucio.** Cualquier implementación futura debe partir de ese estado y delimitar archivos; no revertir cambios ajenos.
7. **La campaña narrativa es contenido de un solo uso.** Su coste de autoría y su ritmo deben validarse después del MVP.

## 8. Próximos pasos

1. **Decidir la dirección de producto.** El arquitecto dejó 8 preguntas cerradas en `architecture.md` (stub, aceptación pasiva, recompensa automática, semántica de captura, corte temporal, desbloqueo, alcance del MVP y tope semanal).
2. **Si se aprueba el MVP:** crear una tarea de implementación nueva, convertir `plan.md` v1 en plan aprobado, y ejecutar por fases (datos → dominio → aplicación → integración → UI → documentación) con revisión y seguridad.
3. **Si se prioriza la campaña:** escribir primero la biblia narrativa y el mapa de capítulos/hitos; después diseñar el desbloqueo y el ledger con ese contenido.
4. **Validar con 3-5 jugadores** antes de construir la campaña: que completen 2 misiones en la primera sesión sin fricción, que vuelvan al día siguiente sin streak y que visiten un hábitat o sistema que no habrían tocado.
5. **No implementar nada** hasta que exista una aprobación explícita con versión de plan.
