# TASK-003 — QA

- **QA:** `qa`
- **Fecha:** 2026-11-01
- **Plan:** `plan.md` v2
- **Veredicto final:** `PASS WITH KNOWN ISSUES`

## Resumen

Los criterios funcionales 1-10 están verificados. El criterio 11 (seguridad) queda **no aplica**
por decisión explícita del desarrollador; no se realizó auditoría. Los cuatro fallos de tests que
quedaban abiertos se corrigieron y la suite relevante está en verde. El único rojo restante de
PHPStan está confinado a `SendasController.php` (WIP ajeno).

## Criterios

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Creación individual, `inicio_exploracion`, 201 con `inicio/fin/estado` | Cumple | `ExploracionesIndividualEndpointsTest` |
| 2 | UI individual con explorador, nombre, rol, nivel, EXP, shiny y HP | Cumple | `ExploracionesViewTest` / `ExploracionesPageTest` |
| 3 | Equipo editable; solo se bloquea al reclutado; combate exige equipo completo | Cumple con matiz | `ConstruirEquipoJugadorTest`, `EquiposControllerTest`; falta test end-to-end de los 4 flujos |
| 4 | Economía 50 %, EXP 50/50, caramelos ×0,5, captura ×0,5, ruta intacta | Cumple | `CalculadorRecompensasTest`, `EconomiaRecompensasTest`, `ProbabilidadCapturaTest`, `OtorgarRecompensasRutaTest` |
| 5 | `return_time` siguiente ocurrencia; 422 duración+hora | Cumple | `CalculadorFinExploracionTest`, endpoints |
| 6 | Diario agrupado, descanso 30/1, retirada/derrota/cancelada, hitos/ánimo, objetos perdidos | Cumple | Tests de presentación y `ExploracionesPageTest` |
| 7 | Preview con factor 0,5 y labels no nulos | Cumple | `ExploracionesPreviewTest` |
| 8 | `GET /exploraciones` procesa antes de renderizar; lock; fallos aislados | Cumple | `ExploracionesIndexProcesaTest`, `ExploracionesRecogerLockTest`, `ProcesarExploracionesCommandTest` |
| 9 | Tests relevantes, PHPStan y Pint | Cumple para TASK-003 | 456/2705; 26/114; PHPStan 0 en TASK-003; Pint pass |
| 10 | Sin migraciones/dependencias; WIP preservado | Cumple | `database/migrations` sin cambios |
| 11 | Seguridad | **No aplica** por decisión del desarrollador | `security.md` |

## Evidencia de ejecución

- Suite relevante: **456 tests / 2705 aserciones en verde**.
  Filtro: `Exploraciones|EquiposControllerTest|EquiposViewTest|EquiposPayloadTest|HabitatsViewTest|FrontendHabitatModalTest|ValidadorExploracionTest|ReclutadoLiberarTest|TeamTest|ConstruirEquipoJugadorTest|ProcesarExploraciones`.
- Fallos corregidos: **26 tests / 114 aserciones en verde** para
  `ProcesarExploracionCombateTest|CombateExploracionTest|ExploracionesAutoResolucionTest|EquiposPayloadTest`.
- `phpstan` completo: 8 errores preexistentes en `SendasController.php` (WIP ajeno); 0 en TASK-003.
- `pint --test`: pass en archivos de TASK-003.
- Sin migraciones ni dependencias nuevas.

## Correcciones post-QA

- `ProcesarExploracionCombateTest`: descanso esperado 40 (1 %/min × 1.75; `ceil(70/1.75)=40`).
- `CombateExploracionTest`: aserción determinista de daño con bonus, no de rondas.
- `ExploracionesAutoResolucionTest`: ya no exige `derrotados`; verifica `eventos['resultado']`.
- `EquiposPayloadTest`: `ReclutadoSerializer` usa el nivel derivado de `exp` para `nivel`;
  `stats_calculados`/`cp` siguen usando el nivel del jugador.
- `objetos_perdidos`: el presentador lo reenvía a `resultado` y la vista lo muestra, con test.
- Docblocks de `ProcesarExploracionHandler` actualizados a 1 %/min.

## Fallos restantes

- Ninguno en TASK-003.
- **Ajeno:** 8 errores de PHPStan en `SendasController.php` (WIP previo).
- Observación de QA: `ExploracionesAutoResolucionTest` ahora es determinista, pero su aserción de
  combate es más débil que la anterior; conviene reforzarla en Fase 2 con un combate inyectado.

## Huecos de cobertura

1. Guarda de combate sin test end-to-end de los cuatro flujos que confirme «no se crea batalla».
2. La respuesta 201 no asserta la ausencia del payload `equipo`.
3. Lock/throttle solo probados con `CACHE_STORE=array`; driver de producción sin validar.
4. Presupuesto de 2 s y límite de 10 no medidos con datos reales.
5. Captura ×0,5 cubierta en unitario, sin test de integración en el cierre de expedición.

## Riesgos residuales

- Seguridad no auditada por decisión del desarrollador.
- CI global en rojo por `SendasController` (ajeno).
- Lock dependiente del driver de cache.
- `GET /exploraciones` con efectos secundarios aceptado en Fase 1; latencia real sin medir.
- No ejecutar PHPUnit en paralelo sobre `laravel_test`.

## Conclusión

`PASS WITH KNOWN ISSUES` para TASK-003. El módulo queda funcionalmente cerrado; los puntos
restantes son deuda ajena o mejoras de Fase 2.
