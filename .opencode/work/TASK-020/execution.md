# TASK-020 — Ejecución y handoff

- **Estado:** `COMPLETED` — Fase 1 implementada, verificada y aprobada por `reviewer`; cambios consolidados en un commit único local.
- **Fecha de cierre:** 2026-11-06
- **Proyecto:** `/home/david/pokemon`
- **Rama:** `task-011`
- **HEAD:** `0317179` (checkpoint TASK-019)
- **Plan aprobado:** `plan.md` v1 — Fase 1
- **Aprobación:** `approval.md` — «Aprobar plan v1 (Fase 1)»

> La Fase 1 quedó cerrada el 2026-11-06 con veredicto `APPROVED`. Las fases 2-6 no están autorizadas y requieren plan y aprobación propios.

---

## 1. Qué se ha completado

### 1.1 Puertos compartidos

- `src/Shared/Domain/Ports/GeneradorAleatorio.php` — `entero(int,int): int`, `flotante(): float`.
- `src/Shared/Domain/Ports/Reloj.php` — `ahora(): DateTimeImmutable`.
- `src/Shared/Infrastructure/Random/GeneradorAleatorioPhp.php` — adaptador con `mt_rand`.
- `src/Shared/Infrastructure/Time/RelojSistema.php` — adaptador con `CarbonImmutable::now()`.
- `src/Shared/Domain/Random/GeneradorAleatorioPredeterminado.php` — default para Domain sin depender de Infrastructure.
- `src/Shared/Domain/Time/RelojPredeterminado.php` — default para Domain/App sin depender de Infrastructure.

Los defaults en Domain se añadieron para corregir dos violaciones de deptrac introducidas por instanciar adaptadores de `Shared\Infrastructure` desde `Battle\Domain` y `Exploraciones\App`.

### 1.2 Battle — cálculo puro

Nuevos componentes en `src/Battle/Domain/Ejecucion/`:

- `CalculadorGolpes`
- `AplicadorDrenaje`
- `AplicadorRecoil`
- `AplicadorCosteVelocidad`
- `AplicadorCuracion`
- `GeneradorLogMovimiento`

`src/Battle/Domain/ServicioEjecucionBatalla.php` conserva sus métodos públicos y delega en los componentes. El constructor admite `?GeneradorAleatorio $aleatorio = null` al final. Se sustituyeron los `mt_rand` del camino tocado por el puerto, sin añadir ni quitar tiradas.

### 1.3 Exploraciones — cálculo puro

Nuevos componentes:

- `src/Exploraciones/Domain/Tiempos/VentanaExploracion.php`
- `src/Exploraciones/Domain/Tiempos/CalculadoraDuracionExploracion.php`
- `src/Exploraciones/Domain/Incidentes/IncidentesExploracion.php`
- `src/Exploraciones/Domain/Incidentes/CalculadoraIncidentes.php`
- `src/Exploraciones/Domain/Perdidas/ObjetoPerdido.php`
- `src/Exploraciones/Domain/Perdidas/ColeccionObjetosPerdidos.php`
- `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php`

`src/Exploraciones/App/FinalizarExploracionHandler.php` conserva las firmas privadas `duracionReal`, `factorTiempo`, `incidentes`, `objetosPerdidos` y `restarPerdidas`, y delega en los calculadores. El constructor admite `?Reloj $reloj = null` al final. No se tocó el shape de `eventos`, `resultado`, `derrotados`, `objetos_perdidos` ni `bonos.del_habitat`.

### 1.4 Correcciones de la primera revisión

- `RelojPredeterminado` y `RelojSistema` usan `CarbonImmutable::now()` para respetar `Carbon::setTestNow`/`travelTo`.
- `FinalizarExploracionHandler::ventanaExploracion()` usa `Carbon::instance(...)` mutable para no perder `addDay()`.
- `CalculadoraPerdidas::calcular()` y `restar()` se dividieron en helpers de ≤40 líneas.

---

## 2. Revisión independiente

- **Ronda 1:** `CHANGES_REQUESTED` — reloj sin `setTestNow`, métodos >40 líneas y `CarbonImmutable::instance`.
- **Ronda 2:** `CHANGES_REQUESTED` — las correcciones de fondo se aceptan, pero quedan dos incumplimientos formales:
  1. `CalculadoraPerdidas.php` tenía 281 líneas (límite 200).
  2. Importaba `Illuminate\Support\Collection` en Domain (prohibido por el criterio 6).

Detalle en `review.md`.

---

## 3. Estado de la corrección

El subagente `backend` terminó la compactación de `CalculadoraPerdidas` sin crear archivos nuevos:

- **Session ID:** `ses_e52716434ffe6TvYz5I1wxFwMo`
- **Archivo modificado:** solo `src/Exploraciones/Domain/Perdidas/CalculadoraPerdidas.php`
- **Resultado:** 199 líneas; sin `Illuminate`; `calcular()` y `restar()` conservan firma, orden, fórmulas y shape; todos los métodos ≤40 líneas.
- **Comportamiento:** el implementador comparó la salida original y la nueva en 8 escenarios (derrota/no derrota, resultado vacío, pérdidas vacías, pérdidas ajenas, exceso de pérdida, pérdidas duplicadas y resta sobre resultado vacío) con `diff` → idénticas.

Verificación independiente del orchestrator reproducida sobre el archivo actual:

- `wc -l` → **199**.
- `grep -n "Illuminate"` → **0**.
- `ReflectionMethod` → `calcular` 13, `restar` 15, `perdidasDe` 12, `reducirElementos` 12; el resto entre 4 y 8.
- `php -l` → sin errores.
- `vendor/bin/pint --test` → pass.
- `git diff --check` → OK.

---

## 4. Verificación

| Comprobación | Resultado |
|---|---|
| `vendor/bin/pint --test` | Pasa |
| `vendor/bin/phpstan analyse -c phpstan.neon` | 34 errores (baseline: 34; sin errores en `CalculadoraPerdidas`) |
| `vendor/bin/deptrac analyse --no-progress` | 105 violaciones (baseline TASK-019: 113; sin violaciones nuevas) |
| `php -l` | Sin errores |
| `wc -l CalculadoraPerdidas.php` | 199 |
| `grep "Illuminate"` | 0 |
| `git diff --check` | Sin errores |
| Tests | **No ejecutados** (fuera de alcance) |

---

## 5. Estado Git

```text
Rama: task-011
HEAD: 0317179
```

Modificados:

- `src/Battle/Domain/ServicioEjecucionBatalla.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`

Sin rastrear (nuevos):

- `src/Battle/Domain/Ejecucion/`
- `src/Exploraciones/Domain/Incidentes/`
- `src/Exploraciones/Domain/Perdidas/`
- `src/Exploraciones/Domain/Tiempos/`
- `src/Shared/Domain/Ports/`
- `src/Shared/Domain/Random/`
- `src/Shared/Domain/Time/`
- `src/Shared/Infrastructure/`
- `.opencode/work/TASK-019/`
- `.opencode/work/TASK-020/`

- **No hay commits nuevos.**
- **No se ha hecho `git push`.**
- No se han tocado `Combatiente`, `AgregadoBatalla`, `SESSION_VERSION`, `BattleSessionService`, tests, rutas ni migraciones.

---

## 6. Cierre y siguientes pasos

1. **Fase 1 cerrada:** revisión final `APPROVED`; ver `review.md`.
2. **Commit único:** los 43 archivos de la fase (código y artefactos de coordinación) están consolidados en un solo commit local. **No se ha hecho `git push`.**
3. **Siguiente decisión:** continuar con una fase nueva requiere plan y aprobación explícitos.
4. **No iniciar las fases 2-6** sin un plan nuevo y aprobación explícita:
   - Fase 2: `Combatiente` en componentes.
   - Fase 3: `ServicioEjecucionBatalla` + orquestación de handlers de Exploraciones.
   - Fase 4: `AgregadoBatalla`.
   - Fase 5: serialización v22 / retirada de `SESSION_VERSION`.
   - Fase 6: limpieza de `app/Crud`, código muerto y frontend.

---

## 7. Restricciones vigentes

- No crear, modificar ni ejecutar tests.
- No cambiar dependencias ni `composer.json`.
- No tocar migraciones, rutas ni esquema.
- No modificar `Combatiente`, `AgregadoBatalla`, serialización ni `SESSION_VERSION` en esta fase.
- No `git push`.
- No implementar las fases 2-6 sin plan y aprobación nuevos.

---

## 8. Artefactos

- `request.md` — petición y aclaraciones.
- `analysis.md` — mapa del backend objetivo.
- `architecture.md` — arquitectura de componentes, puertos, serialización y fases.
- `plan.md` — plan v1 de la Fase 1.
- `approval.md` — aprobación explícita.
- `tasks.md` — seguimiento.
- `execution.md` — este handoff.
- `review.md` — revisión independiente.
- `security.md` — no aplica (sin cambios de seguridad).
- `final-report.md` — pendiente de completar al cerrar.
