# TASK-020 — Informe final

- **Estado:** `COMPLETED`
- **Fecha:** 2026-11-06
- **Plan:** v1 — Fase 1 (aprobado en `approval.md`)
- **Alcance:** deuda estructural del backend de dominio; sin tests, sin migraciones, sin dependencias nuevas, sin `git push`.

---

## 1. Resumen

Se completó la Fase 1 de la descomposición incremental del backend:

- Se introdujeron puertos mínimos de azar y reloj.
- Se extrajo cálculo puro de `ServicioEjecucionBatalla` y `FinalizarExploracionHandler`.
- Los métodos originales se conservaron como delegaciones compatibles.
- No se modificó el comportamiento, la serialización, `SESSION_VERSION`, `Combatiente` ni `AgregadoBatalla`.

La revisión independiente final emitió **APPROVED**.

---

## 2. Archivos creados

### Puertos y adaptadores

- `src/Shared/Domain/Ports/GeneradorAleatorio.php`
- `src/Shared/Domain/Ports/Reloj.php`
- `src/Shared/Domain/Random/GeneradorAleatorioPredeterminado.php`
- `src/Shared/Domain/Time/RelojPredeterminado.php`
- `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php`
- `src/Shared/Infrastructure/Time/RelojSistema.php`

### Battle — cálculo puro

- `src/Battle/Domain/Ejecucion/CalculadorGolpes.php`
- `src/Battle/Domain/Ejecucion/AplicadorDrenaje.php`
- `src/Battle/Domain/Ejecucion/AplicadorRecoil.php`
- `src/Battle/Domain/Ejecucion/AplicadorCosteVelocidad.php`
- `src/Battle/Domain/Ejecucion/AplicadorCuracion.php`
- `src/Battle/Domain/Ejecucion/GeneradorLogMovimiento.php`

### Exploraciones — cálculo puro

- `src/Exploraciones/Domain/Tiempos/VentanaExploracion.php`
- `src/Exploraciones/Domain/Tiempos/CalculadoraDuracionExploracion.php`
- `src/Exploraciones/Domain/Incidentes/IncidentesExploracion.php`
- `src/Exploraciones/Domain/Incidentes/CalculadoraIncidentes.php`
- `src/Exploraciones/Domain/Perdidas/ObjetoPerdido.php`
- `src/Exploraciones/Domain/Perdidas/ColeccionObjetosPerdidos.php`
- `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php`

## 3. Archivos modificados

- `src/Battle/Domain/ServicioEjecucionBatalla.php`
  - Delega golpes, drenaje, recoil, coste de velocidad, curación y log.
  - Usa `GeneradorAleatorio`; sin `mt_rand` en el camino tocado.
  - Conserva métodos públicos y orden de tiradas.

- `src/Exploraciones/App/FinalizarExploracionHandler.php`
  - Delega duración, factor de tiempo, incidentes, objetos perdidos y resta de pérdidas.
  - Usa `Reloj`; sin `now()` en los métodos delegados.
  - Conserva firmas privadas, claves y shape de `eventos`/`resultado`/`objetos_perdidos`.

---

## 4. Decisiones

1. **Fachada + delegación:** no se reescribieron las clases grandes; se extrajo cálculo puro y se mantuvieron las APIs existentes.
2. **Puertos solo con justificación:** `GeneradorAleatorio` y `Reloj` aíslan efectos no deterministas. No se crearon interfaces para calculadores de una sola implementación.
3. **Defaults en Domain:** `GeneradorAleatorioPredeterminado` y `RelojPredeterminado` evitan que `Domain`/`App` dependan de `Shared\Infrastructure`, corrigiendo dos violaciones de deptrac.
4. **Reloj compatible con tests existentes:** se usa `CarbonImmutable::now()` para respetar `Carbon::setTestNow`/`travelTo`.
5. **Serialización intacta:** no se movió estado de `AgregadoBatalla`; `Combatiente::__serialize`, `__unserialize`, `__clone` y `SESSION_VERSION` no se tocaron.
6. **Sin tests:** no se crearon, modificaron ni ejecutaron tests.

---

## 5. Verificación

| Comprobación | Resultado |
|---|---|
| `php -l` en archivos nuevos/modificados | Sin errores |
| `vendor/bin/pint --test` | Pass |
| `vendor/bin/phpstan analyse -c phpstan.neon` | 34 errores (baseline 34; 0 en `CalculadoraPerdidas`) |
| `vendor/bin/deptrac analyse --no-progress` | 105 violaciones (baseline TASK-019: 113; sin violaciones nuevas) |
| `git diff --check` | Sin errores |
| Componentes nuevos ≤200 líneas | Máximo: 199 (`CalculadoraPerdidas`) |
| Métodos ≤40 líneas | Máximo verificado: 15 (`restar`) |
| `grep mt_rand` en `ServicioEjecucionBatalla` | 0 |
| `grep Illuminate` en `CalculadoraPerdidas` | 0 |
| Comportamiento de pérdidas | Idéntico al original en 11 escenarios (revisor) y 8 (implementador) |
| Tests | **No ejecutados** (fuera de alcance) |

---

## 6. Revisión y seguridad

- **Revisión:** `APPROVED` en tercera ronda (`review.md`).
  - Ronda 1: reloj sin `Carbon::setTestNow`, métodos >40 líneas y `CarbonImmutable::instance`.
  - Ronda 2: `CalculadoraPerdidas` con 281 líneas e import de `Illuminate`.
  - Ronda 3: corregidos ambos; comportamiento idéntico; sin hallazgos críticos/altos/medios.
- **Seguridad:** `NO APLICA` (`security.md`). No se tocaron autenticación, autorización, entradas de usuario, SQL, sesiones, cookies, secretos ni endpoints públicos.

---

## 7. Riesgos residuales

- No se ejecutaron tests ni smoke en entorno levantado; la equivalencia se sostiene por comparación contra el original y análisis estático.
- `RelojPredeterminado` y `RelojSistema` son idénticos; unificar en la fase de composition root.
- Los calculadores se instancian dentro de `FinalizarExploracionHandler`; inyectarlos en la Fase 3.
- Líneas largas con tuplas posicionales en `CalculadoraPerdidas` (legibilidad, no comportamiento).
- Violaciones preexistentes de deptrac hacia `Presentation` siguen fuera del alcance.
- `DTOObjetoPerdido` e `IncidentesExploracion::aArray()` quedan como limpieza futura.
- Cambios consolidados en un único commit local; no se ha hecho `git push`.

---

## 8. Próximos pasos

1. Commit único local creado con todos los cambios (código y artefactos); no se ha hecho `git push`.
2. No hacer `git push`.
3. Para continuar con la deuda estructural, preparar plan y aprobación nuevos:
   - Fase 2: `Combatiente` en componentes.
   - Fase 3: `ServicioEjecucionBatalla` + orquestación de handlers de Exploraciones.
   - Fase 4: `AgregadoBatalla`.
   - Fase 5: serialización v22 / retirada de `SESSION_VERSION`.
   - Fase 6: limpieza de `app/Crud`, código muerto y frontend.

---

## 9. Artefactos

- `request.md`
- `analysis.md`
- `architecture.md`
- `plan.md` (v1)
- `approval.md`
- `tasks.md`
- `execution.md`
- `review.md`
- `security.md`
- `final-report.md` (este documento)
