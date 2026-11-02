# TASK-004 — Ejecución y preflight

## Preflight (2026-11-02)

- Directorio: `/home/david/pokemon`
- Rama: `master`, al día con `origin/master`.
- Estado del árbol: **117 archivos modificados, 9 borrados, 73 sin seguimiento**.
- Hay WIP preexistente que afecta directa o indirectamente a la economía:
  - Modificados: `app/Http/Controllers/ReclutadoController.php`,
    `app/Http/Controllers/SendasController.php`, `app/Support/ArbolSendas.php`,
    `app/Support/SendaProgreso.php`, `src/Exploraciones/Domain/CalculadorRecompensas.php`,
    `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php`,
    `src/Exploraciones/App/PersistirRecompensas.php`,
    `resources/views/sendas/index.blade.php`, `resources/views/sendas/show.blade.php`.
  - Sin seguimiento: `src/Exploraciones/Domain/EconomiaRecompensas.php`,
    `src/Mazmorras/App/OtorgarRecompensasMazmorra.php`,
    `tests/Unit/Exploraciones/EconomiaRecompensasTest.php`.
- Decisión: **no revertir, no limpiar, no commitear** ese WIP. Se toma como línea
  base del análisis. Cualquier implementación futura se limitará a los archivos
  autorizados en el plan aprobado.

## Registro

| Fecha | Estado | Acción | Resultado |
|-------|--------|--------|-----------|
| 2026-11-02 | RECEIVED | Recepción, preflight y creación de artefactos | TASK-004 creada |
| 2026-11-02 | ANALYZING | Delegación de análisis A (sumideros) y B (fuentes) a `analyst` | ambos completados |
| 2026-11-02 | ANALYZING | Resultados guardados en `analysis.md` | 639 nodos, costes y fórmulas inventariados; 15 inconsistencias documentadas |
| 2026-11-02 | ANALYZING | Consulta al desarrollador sobre alcance y ritmo objetivo | Balance + fallos estructurales; ritmo por sistema |
| 2026-11-02 | DESIGNING | Arquitecto entrega modelo v1 | `architecture.md` con precios, fases y criterios |
| 2026-11-02 | DESIGNING | Ampliación: roles de niveles de hábitat (1 captura, 2-3 farmeo) | Addendum con multiplicadores y captura por nivel |
| 2026-11-02 | DESIGNING | Segunda ampliación: política de IV/EV por ruta, entrenador y gimnasio | Addendum II con perfiles, tiempos y multiplicadores |
| 2026-11-02 | AWAITING_APPROVAL | Plan v1 redactado y presentado | Pendiente de aprobación explícita |
| 2026-11-02 | AWAITING_APPROVAL | Feedback del desarrollador: granja correcta; «los otros son los bajos»; no limitar combates salvajes, incentivar subir de rango | Requiere aclaración y plan v2; no se implementa |
| 2026-11-02 | AWAITING_APPROVAL | Aclaración: subir costes de Sendas y recompensas de combate; sin límites nuevos en ruta/exploración/descarte; entrenadores mantienen renovación diaria | Plan v2 redactado; pendiente de aprobación |
| 2026-11-02 | AWAITING_APPROVAL | Desarrollador aprueba plan v2 con «Aprobar plan v2» | Registrado en `approval.md` |
| 2026-11-02 | ANALYZING | Tercera ampliación: beneficios por nivel de Senda, ataques solo niveles 1-3, movesets de rivales con Showdown | v3 en preparación; **no se implementa v2** |
| 2026-11-02 | ANALYZING | Analista A: estructura de Sendas, loadout y puntos de aplicación | 639 nodos (187/114/160/104/74); 178 en 4-5; captura/daño/caramelos localizados |
| 2026-11-02 | ANALYZING | Analista B: datos de Showdown y match con el juego | 509 especies, 348 movimientos, 300 mapeables (86,2 %), 435 especies (85,5 %) |
| 2026-11-02 | DESIGNING | Addendum III del arquitecto: Sendas maestras y selector de 4 movimientos | Guardado en `architecture.md`; 187/114/338; total 9.584; pasivos 10/20 %; caps por tier |
| 2026-11-02 | AWAITING_APPROVAL | Plan v3 redactado y presentado | Pendiente de aprobación explícita; **no se implementa** |
| 2026-11-02 | AWAITING_APPROVAL | Feedback del desarrollador: pool = permitidos + desbloqueados; rival con 6 movimientos; valores en constantes/config | Plan v4 en preparación; v3 no aprobado |
| 2026-11-02 | AWAITING_APPROVAL | Aclaración: el rival usa sus movimientos por nivel + los desbloqueados por el jugador que su especie aprenda por MT/tutor/huevo (o nivel superior) | Plan v5 redactado; v4 no aprobado |
| 2026-11-02 | AWAITING_APPROVAL | Plan v5 mostrado por pantalla y aprobado con «Aprobar plan v5» | Registrado en `approval.md`; alcance fases 1-5 |
| 2026-11-02 | IMPLEMENTING | Inicio de fase 1: estabilización contable y cadencia de exploración | Delegación a `backend` |
| 2026-11-02 | IMPLEMENTING | Primer intento de fase 1 sin cambios: el agente agotó pasos en la lectura | Se divide la fase en 1A (contabilidad EXP/mazmorra), 1B (exploración) y 1C (EV/captura) |
| 2026-11-02 | IMPLEMENTING | Fase 1A implementada y aprobada por `reviewer` | Pool único de EXP, mazmorra 70/10, tests verdes |
| 2026-11-02 | IMPLEMENTING | Fase 1B1 implementada: hallazgos ≥1, ceros, emboscada, derrota `intdiv` | Tests verdes |
| 2026-11-02 | IMPLEMENTING | Fase 1B2 implementada: cadencia por tiempo con contador y tope 12 | Tests verdes |
| 2026-11-02 | IMPLEMENTING | Fase 1C implementada: `ReglasEv` 255 y captura unificada | Tests verdes |
| 2026-11-02 | REVIEWING | Revisión de fase 1: `CHANGES_REQUESTED` | 2 mayores: backlog en exploraciones antiguas sin contador; captura de exploración ×0,5 no declarada |
| 2026-11-02 | CORRECTING | Correcciones de los 2 mayores y limpieza de constantes/docblocks | Backfill determinista; captura sin 0,5; tests verdes |
| 2026-11-02 | REVIEWING | Re-revisión de fase 1 | `APPROVED`; quedan notas documentales para Fase 4 |
| 2026-11-02 | TESTING | QA de fase 1 | `PASS` en el alcance; suite global con 21 fallos WIP ajenos; faltan 2 tests de cobertura |
| 2026-11-02 | IMPLEMENTING | Fase 1 cerrada; inicio de Fase 2A (IV/EV por tier) | Delegación 2A1: perfiles y distribuidor |
| 2026-11-02 | IMPLEMENTING | 2A1: `PerfilIvsEvs`, `PoliticaIvsEvs`, top-N y tests | Completado |
| 2026-11-02 | IMPLEMENTING | 2A2a: `MultiplicadoresDificultad`, `DificultadHabitat`, calculador | Completado |
| 2026-11-02 | IMPLEMENTING | 2A2b1: ruta y entrenador con IV/EV por tier | Completado |
| 2026-11-02 | IMPLEMENTING | 2A2b2: gimnasios con índice y perfil; `indiceDe` | Completado |
| 2026-11-02 | IMPLEMENTING | 2A2b3: call sites de recompensas con dificultad | Producción completa; tests corregidos y verdes |
| 2026-11-02 | CORRECTING | Un subagente creó un commit local no autorizado (`a75290f`) con el test de gimnasios | Deshecho con `git reset --mixed HEAD~1`, conservando los cambios; **no hubo push** |
| 2026-11-02 | REVIEWING | Fase 2A completa: perfiles, multiplicadores, ruta/entrenador/gimnasio y recompensas | Revisión: `CHANGES_REQUESTED` (2 mayores en el presentador) |
| 2026-11-02 | CORRECTING | Corregir presentador de gimnasio (repositorio BD) y entrenador (`perfilEntrenador`) | Re-revisión: `APPROVED` |
| 2026-11-02 | IMPLEMENTING | 2B1: Senda visible 1-3, tramos 1-5, costes/umbrales, config, 422 | Tests verdes; catálogo limpio 632 nodos / 9.432 |
| 2026-11-02 | IMPLEMENTING | 2B2a1: `MapaSendaJugador`, `PoliticaSenda`, carga y serialización | Tests verdes |
| 2026-11-02 | IMPLEMENTING | 2B2a2: manejadores de daño ofensivo/defensivo | Tests verdes |
| 2026-11-02 | IMPLEMENTING | 2B2b1: captura y caramelos de tipo en ruta/exploración | Tests verdes |
| 2026-11-02 | IMPLEMENTING | 2B2b2: Senda en entrenador/gimnasio; mazmorra sin Senda | Tests verdes |
| 2026-11-02 | REVIEWING | Fase 2B completa | Revisión: `CHANGES_REQUESTED`; correcciones en curso |
| 2026-11-02 | CORRECTING | Corregidos mazmorra/exploración/mapa/cap de captura; helpers de tests actualizados | Pendiente parte 2 (config beneficios, experiencia, vector de daño) |
| 2026-11-02 | IMPLEMENTING | **Decisión del desarrollador:** desactivar generación y mantenimiento de tests por coste de tokens | No se crearán/corregirán tests nuevos; se conservan los existentes |
| 2026-11-02 | IMPLEMENTING | 2C1-2C4: selector de 6, perfiles, config, Showdown, integración y fallback | Verificado con php -l/PHPStan y comprobaciones manuales |
| 2026-11-02 | IMPLEMENTING | 2D1-2D2: costes de granja, EV 51/nivel, captura 25/30 %, divisor 250 | Verificado con vectores manuales |
| 2026-11-02 | IMPLEMENTING | Fase 3 UI: beneficios, maestrías, selector y aviso de movesets | `view:cache` correcto |
| 2026-11-02 | DOCUMENTING | Fase 4: ADR y documentación | Delegación a `documenter` |
| 2026-11-02 | REVIEWING | Fase 5: seguridad y revisión final | Auditoría parcial; 2 medium corregidos |
| 2026-11-02 | COMPLETED | **Decisión del desarrollador:** omitir seguridad y cerrar el desarrollo | Tests desactivados; documentación Fase 4 incompleta; informe final redactado |
| 2026-11-02 | COMPLETED | Estado final: COMPLETADO con riesgos residuales | HEAD `efa5cfd`; sin push; WIP intacto |
