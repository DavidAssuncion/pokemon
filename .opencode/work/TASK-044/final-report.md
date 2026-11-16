# TASK-044 — Informe final

- **Fecha:** 2026-11-12
- **Estado:** COMPLETADO (análisis y diseño; sin implementación)
- **Tipo:** investigación de producto + diseño de facciones, sin cambios de código.

## 1. Resumen de la tarea

El desarrollador preguntó cómo meter a «todos los villanos» (Team Rocket, Magma, Aqua, etc.) con sus historias dentro de las misiones y del mundo. Se analizó el material existente (`Tareas_pdte/mejoras`), se verificó el roster y la infraestructura real, y se diseñó una propuesta completa de facciones.

## 2. Estado final

`RECEIVED → ANALYZING → DESIGNING → COMPLETED`

No se solicitó implementación. `approval.md` permanece en `PENDIENTE`.

## 3. Archivos creados o modificados

Solo artefactos de coordinación en `.opencode/work/TASK-044/`:

| Archivo | Contenido |
|---|---|
| `request.md` | Petición literal y alcance. |
| `analysis.md` | Parte A: inventario de facciones, roster y geografía. Parte B: viabilidad narrativa y técnica. |
| `architecture.md` | Diseño del `architect`: 8 expedientes, mundo, legendarios, arquitectura. |
| `plan.md` | Plan v1 propuesto para el MVP de Yell (no autorizado). |
| `approval.md` | Sin aprobación; no hay implementación. |
| `tasks.md` | Delegaciones y sesiones. |
| `execution.md` | Preflight y registro. |
| `review.md` | No aplica (sin cambios de código). |
| `security.md` | No aplica (sin cambios de código). |
| `final-report.md` | Este informe. |

No se modificó ningún archivo de aplicación, migración, vista, configuración ni documentación del proyecto.

## 4. Decisiones y propuestas principales

### Modelo

- **8 expedientes autorados de 3 actos**, uno por capítulo de la campaña de TASK-043, en secuencia. No son un quinto tipo de misión: son hitos de campaña + encargos de facción dentro de la pestaña de campaña.
- **Yell primero** (MVP sin legendario); después Skull, Aqua+Magma, Flare, Galaxia, Rocket y Plasma.
- **Equipos 3v3**: los 5 Pokémon de `mejoras` se reparten entre líder (3) y lugarteniente (3). Ningún líder repite el trío del gimnasio de su provincia.
- **Bases de 3 pisos** con entidad propia (`faction_base_progress`), no reutilizando `habitats.mazmorra`; piso 1 y 2 son 3v1, piso 3 es 3v3 contra el líder.
- **Skull y Yell sin legendario**: su clímax es humano; no es un hueco, es una decisión de tono.

### Mundo

- La Bruma es la cicatriz de un **ancla legendaria fuera de su sitio**. Los gimnasios usan **proyecciones**, el hábitat 49 contiene **ecos** y las facciones buscan o protegen **anclas reales**. Una sola mitología explica las tres apariciones del mismo legendario.
- Cada facción tiene tres estados visibles: **activa**, **derrotada** y **cicatriz**. La cicatriz es un cambio de texto y tinte en el hábitat; no da poder, da memoria.
- Solo el líder del acto III bloquea el hito. Sabotaje, rescate, patrullas y ancla son opcionales y cambian el mundo o el combate (por ejemplo, sabotear el altavoz evita el buff del líder).

### Legendarios

- **Una ancla por facción**, con elección en Flare, Galaxia y Plasma; máximo 7 por cuenta (6 + Rayquaza).
- Captura garantizada al aceptar, IV mínimo 10, shiny 1/512 **fijado en la primera victoria**.
- Rechazo máximo 5: +1 al suelo de IV y +10 % a las stats del **jefe** (tope +50 %), no del reclutado. Evita el farmeo infinito y el reroll de shiny.
- La regla literal de `mejoras` (+10 % acumulable al reclutado, sin tope) se descarta por power creep y gambling.

### Arquitectura

- Módulo `src/Facciones/` con catálogo en código (8 facciones y 8 bases) y estado en tres tablas: `faction_progress`, `faction_base_progress` y `legendary_ledger` (esta última en v1).
- Nuevos tipos de batalla `faccion` y `faccion_base` con ramas explícitas en `PresentadorResultadoBatalla`; equipos normalizados a 3; el `log` de batalla no se usa para narrativa.
- Recompensas: grunt ×2, lugarteniente/piso ×10, líder ×20 una vez; emblema, nota y una recompensa única por facción. Sin moneda nueva ni caramelos repetibles.
- MVP: Yell completo, sin sistema de anclas, detrás de flag.

## 5. Verificación ejecutada

- **Análisis documental y de código:** dos analistas con evidencia archivo:línea; informe del arquitecto basado en ellos.
- **Comprobaciones puntuales (solo lectura):** material de `Tareas_pdte/mejoras`, roster en CSV, hábitats asignados, gimnasios con legendarios, ausencia de mazmorras configuradas y de capa narrativa.
- **Análisis estático:** no aplica; no hubo cambios de código.
- **Tests:** no se crearon, modificaron ni ejecutaron.
- **Revisión y seguridad:** no aplica en esta fase; se requieren en la futura implementación.

## 6. Hallazgos de revisión y seguridad

- No hay revisión de código porque no hay código nuevo.
- No hay auditoría de seguridad porque no hay endpoints, permisos ni recompensas implementadas.
- En la futura implementación, la auditoría deberá cubrir: autorización por usuario, IDOR en `faction_slug`/`base_slug`, idempotencia de recompensas, manipulación de acto/flag y acceso al ancla.

## 7. Riesgos residuales

1. **Carga de autoría:** 24 actos, 8 notas y ~40 encargos; el mayor coste es contenido en español, no técnica.
2. **IP:** nombres, líderes y equipos son material oficial. La propuesta usa nombres originales en UI y slugs canónicos internos, con emblemas propios; conviene revisar licencia si se publica.
3. **Canibalización:** gimnasio, mazmorra y facción compiten por atención; la separación propuesta (medalla / botín / historia) debe validarse en juego.
4. **Legendarios:** la regla acotada evita el power creep, pero requiere persistencia y una decisión de producto sobre el tope de rechazos.
5. **Datos no verificados en runtime:** los analistas no consultaron la BD real (por ejemplo, mazmorras configuradas manualmente o valores de `min_lvl`).
6. **Árbol de trabajo sucio:** 28 archivos modificados y 183 entradas sin seguimiento; cualquier implementación futura debe delimitarlos.

## 8. Próximos pasos

1. **Decidir** las 8 preguntas cerradas de `architecture.md` (nombres originales vs canónicos, una ancla vs todas, rechazo al jefe vs al reclutado, nivel de expediente, Yell como MVP, ruta propia vs dentro de `/misiones`, Skull/Yell sin legendario, 3 vs 5 pisos).
2. **Si se aprueba el MVP:** crear una tarea de implementación nueva, convertir `plan.md` v1 en plan aprobado y ejecutar por fases con revisión y seguridad.
3. **Si se prioriza contenido:** escribir primero las fichas de los 8 expedientes y los textos de una línea; después implementar el motor.
4. **No implementar nada** hasta que exista una aprobación explícita con versión de plan.
