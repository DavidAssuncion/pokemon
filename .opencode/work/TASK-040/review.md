# TASK-040 — Revisión

- **Estado:** APPROVED
- **Revisor:** `reviewer` (sesión `ses_e3fde1bbdffe3YKSc1tNVyACth`)
- **Nota:** resultado del revisor, sin alterar sus conclusiones.

---

# Revisión independiente — TASK-040 (plan v1)

## 1. Veredicto

**APPROVED**

## 2. Resumen

Se revisó el diff completo contra `HEAD` de los 7 archivos autorizados, los artefactos de TASK-040 y los puntos de integración reales (flujo de ronda, resolución de acciones, serialización, datos de movimientos y catálogo de soporte).

La implementación coincide con el plan v1 aprobado, sin desviaciones de alcance ni cambios colaterales. El predicado, la clasificación, los ejecutores y los dos filtros de IA están correctamente colocados y con la semántica prevista (`>= ronda - 1`). No hay defectos CRITICAL/HIGH/MEDIUM atribuibles a TASK-040.

**Verificación estática:**
- `git diff` → solo los hunks previstos.
- `php -l` 7/7 sin errores.
- PHPStan filtrado a los 7 archivos → sin errores.
- PHPStan completo → 34 errores preexistentes, 0 en los archivos de TASK-040.
- `git diff --check` limpio.
- `grep setUltimaRondaProteccion` → solo en la rama válida de ambos ejecutores.
- `SESSION_VERSION` → 21.
- No se ejecutaron tests.

## 3. Hallazgos

### Defectos de TASK-040

- **Ninguno CRITICAL/HIGH/MEDIUM.**
- **[NIT] `MovimientoBatalla.php:99-105` — `esProteccion()` incluye `AGUANTE`, no solo Protección/Detección.** Solo claridad semántica; el plan fijó el nombre y no se usa fuera del filtro de IA. Opcional: docblock aclaratorio.
- **[NIT] `RegistroDanio.php:77-81` — predicado sin docblock de contrato.** La semántica queda documentada en `architecture.md`; opcional añadir un docblock corto.
- **[LOW] `SelectorAccionIA.php:220-221` — `$ronda`/`$puedeProteger` se calculan aunque el actor no tenga movimientos de protección.** Impacto despreciable; micro-optimización opcional.

### Deuda preexistente / cambios ajenos

- PHPStan global con 34 errores en archivos no tocados.
- `src/Battle/context.md:614` indica `SESSION_VERSION = 20` mientras el código está en 21; no modificado.
- Cambios ajenos de TASK-035/preexistentes; ignorados.

## 4. Criterios CA1–CA11

| CA | Estado | Evidencia |
|---|---|---|
| CA1 | Cumplido | `RegistroDanio.php:77-81`; `Combatiente.php:506-509`. |
| CA2 | Cumplido | `MovimientoBatalla.php:99-105` con import; enum `ClaveEfectoMovimiento.php:74-75`; datos de movimientos. |
| CA3 | Cumplido | `setUltimaRondaProteccion()` solo en `EjecutorAguante.php:32` y `EjecutorProteccion.php:32`, tras el guard. |
| CA4 | Cumplido | La rama bloqueada solo registra el log y retorna; el setter está después. |
| CA5 | Cumplido (estático) | `GestorPrioridadesBatalla.php:30-43`: `$ronda`, `$puedeProteger`, `continue`; `$mejor = null` conserva la acción normal. |
| CA6 | Cumplido | `SelectorAccionIA.php:220-234`: filtro antes de `esAutodirigido()`; soporte y legacy intactos. |
| CA7 | Cumplido (estático) | El ejecutor se invoca antes de consumir acción/PP; N+1 bloquea, N+2 permite. No verificado en runtime. |
| CA8 | Cumplido | Detección comparte `effect_key = proteccion` y el mismo ejecutor. |
| CA9 | Cumplido | `movimientoDisponible()` sin cambios. |
| CA10 | Cumplido | `SESSION_VERSION` 21; sin cambios de serialización, migraciones ni tests. |
| CA11 | Cumplido | Solo los 7 archivos autorizados. |

## 5. Riesgos residuales

- Sesiones muy antiguas sin `perfil`: `esProteccion()` no filtraría; el ejecutor evita el bucle perpetuo. Aceptado.
- `elegirMejorMovimiento()` legacy sin filtro; sin llamadas de producción. Aceptado.
- IA con solo protección restringida: cae en “Espera”. Aceptado.
- Preparación del jugador sigue mostrando el movimiento restringido; el intento falla con log y coste. Aceptado.
- `revivir()` no reinicia `ultimaRondaProteccion`; bloqueo de una ronda en un caso marginal. Preexistente.
- PHPStan global con 34 errores preexistentes.
- Documentación de `SESSION_VERSION` desactualizada en `src/Battle/context.md`.

## 6. Recomendación final

**Aprobado.** Corrige la causa raíz del bloqueo perpetuo de Aguante/Protección/Detección, mantiene el fallback natural de la IA, conserva el log y el coste del intento fallido para el jugador y no introduce cambios de serialización, sesión, migraciones ni tests. No se requieren correcciones; los NIT/LOW son opcionales.
