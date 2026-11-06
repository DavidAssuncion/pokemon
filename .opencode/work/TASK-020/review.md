# TASK-020 — Revisión independiente

- **Estado:** APPROVED (tercera ronda)
- **Revisor:** `reviewer`
- **Alcance:** Fase 1 del plan v1
- **Fecha:** 2026-11-06

## Ronda 1 — CHANGES_REQUESTED

Hallazgos corregidos:

1. **MEDIO — `RelojPredeterminado` no respetaba `Carbon::setTestNow`.** Corregido usando `CarbonImmutable::now()` en `RelojPredeterminado` y `RelojSistema`.
2. **MEDIO — `CalculadoraPerdidas::calcular()`/`restar()` superaban 40 líneas.** Corregido dividiendo en helpers.
3. **BAJO — `CarbonImmutable::instance()` en `ventanaExploracion()`.** Corregido con `Carbon::instance()` mutable.

## Ronda 2 — CHANGES_REQUESTED

La re-revisión confirma que las correcciones de fondo son correctas:

- El reloj por defecto respeta `Carbon::setTestNow`/`travelTo`.
- `Carbon::instance` restaura el comportamiento mutable de `addDay()`.
- `CalculadoraPerdidas` conserva exactamente el comportamiento original; la comparación contra la réplica literal del `HEAD` no encontró diferencias.

Quedan dos incumplimientos formales del plan v1, ambos en el mismo archivo y corregibles sin crear archivos nuevos:

1. **MEDIO — `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php` tiene 281 líneas.** El criterio de aceptación 9 limita los componentes nuevos a 200 líneas.
2. **MEDIO — `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php:7` importa `Illuminate\Support\Collection`.** El criterio de aceptación 6 prohíbe imports de `Illuminate` en componentes nuevos de `Domain`.

## Confirmaciones

- Extracción literal de RNG, daño, drenaje, recoil, coste, curación y log.
- Duración, factor de tiempo, incidentes y pérdidas sin cambios de comportamiento.
- Firmas públicas/privadas y constructores compatibles.
- Sin cambios en `Combatiente`, `AgregadoBatalla`, serialización, `SESSION_VERSION`, tests, rutas ni migraciones.
- Deptrac bajó de 113 a 105; las dos violaciones nuevas de `Shared\Infrastructure` desaparecieron.
- Pint pasa; PHPStan se mantiene en 34 errores.

## Riesgos residuales

- Los `new Calculadora...()` dentro de `FinalizarExploracionHandler` limitan la sustitución, pero no bloquean la Fase 1.
- Las violaciones preexistentes de deptrac hacia `Presentation` siguen fuera del alcance.

## Verificación del revisor

- Diff contra `HEAD`, lectura de los archivos nuevos y comparación con `git show HEAD:`.
- Comprobación de Pint/PHPStan/deptrac y de `Carbon::setTestNow`.
- Sin ejecución de tests.

## Ronda 3 — APPROVED

La re-revisión final confirma:

- `CalculadoraPerdidas.php` tiene **199 líneas** (≤200).
- **0** referencias a `Illuminate` en Domain.
- `calcular()` y `restar()` conservan firma, orden, fórmulas y shape.
- Comportamiento idéntico al original en **11 escenarios** comparados contra la réplica literal del `HEAD`.
- Todos los métodos ≤15 líneas.
- Sin archivos nuevos ni cambios fuera del alcance en esta ronda.
- Pint pass; PHPStan 34 (0 en `CalculadoraPerdidas`); deptrac 105 (sin violaciones nuevas).
- Criterios de aceptación 1-14 del plan v1: cumplen.

### Hallazgos restantes (no bloqueantes)

- **BAJO:** líneas largas con tuplas posicionales en `CalculadoraPerdidas` (legibilidad, no comportamiento).
- **BAJO:** `RelojPredeterminado` y `RelojSistema` son idénticos; unificar en composition root o hacer que uno delegue en el otro.
- **BAJO:** los calculadores se instancian dentro de `FinalizarExploracionHandler`; inyectarlos en la Fase 3.
- **NIT:** `perdidasDe()` evalúa el closure de datos antes de `intdiv`; no observable por la validación previa de `RecompensaItem`.
- **NIT:** `IncidentesExploracion::aArray()` sin uso; `DTOObjetoPerdido` huérfano; artefactos de coordinación a actualizar.

### Riesgos residuales

- Sin tests ni smoke en entorno levantado (fuera del flujo permitido).
- Duplicación de adaptadores de reloj/azar y ausencia de bindings: deuda conocida, sin impacto actual.
- Los wrappers de una línea con tuplas posicionales son más frágiles ante cambios de campos.

### Verificación del revisor

- Diff y comparación contra `HEAD`; 11 escenarios de pérdidas idénticos.
- `git status`, `git diff --check`, Pint, PHPStan, deptrac, `php -l`, `wc -l` y Reflection.
- Sin ejecución de tests ni modificación de archivos.
