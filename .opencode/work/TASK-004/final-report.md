# TASK-004 — Informe final

- **Tarea:** TASK-004 — Rebalanceo de la economía de objetos, Sendas maestras, IV/EV y movesets de rivales.
- **Plan aprobado:** v5 («Aprobar plan v5», 2026-11-02).
- **Estado final:** **COMPLETADO con riesgos residuales** (tests desactivados por el desarrollador; seguridad omitida por el desarrollador; documentación de Fase 4 incompleta).
- **Fecha:** 2026-11-02.

## 1. Resumen

Se ha implementado el rebalanceo económico y de dificultad del juego:

- Contabilidad de EXP con pool único y reparto 50/50; mazmorra con caramelos ×70 y EXP ×10.
- Exploraciones con eventos por tiempo, hallazgos ≥1, emboscada evitada sin doble recompensa y derrota `intdiv(n,2)`.
- Perfiles IV/EV por tier (`Nulo`, `TopUno`, `TopDos`, `Completo`) para ruta, entrenador y gimnasio, con líder +1 tier.
- Multiplicadores de recompensa por dificultad (familia 3,30/3,35; EV 1,85/2,80; tipo 1,90/2,40).
- Sendas con ataques en niveles visibles 1-3, maestrías pasivas 4-5, costes 2/5/10/25/60 por tramo, umbrales 0/9.000/18.000/36.000/72.000 y `EXP_MAXIMA=72.000`.
- Pasivos de Senda N4 +10 % y N5 +20 % en captura, daño infligido, daño recibido y caramelos de tipo; solo jugador; sin mazmorra.
- Captura con base 25 %, factores de hábitat 1,0/0,5/0,3 y cap 30 % con Senda 5.
- Granja: nido 10/20/30/40/50 (150), refugio 15/25/35/45/60 (180), complemento familia 30/40/50/60/70 (250) + EV 51/nivel (255).
- Selector de 6 movimientos para rivales (jugador también 6), con pool de nivel + desbloqueados que la especie pueda aprender, topes por tier, sets manuales de gimnasio prioritarios y preferencia Showdown.
- Artefacto Showdown build-time (`resources/data/showdown_movesets.json`): 300/348 movimientos y 449/509 especies; sin Node en runtime; MIT.
- UI de Sendas con beneficios, maestrías y selector; aviso de movesets rivales.

## 2. Estado por fase

| Fase | Estado | Evidencia |
|---|---|---|
| 1 — Contabilidad y exploración | Completada | Revisión `APPROVED`; tests de Fase 1 verdes |
| 2A — IV/EV y multiplicadores | Completada | Revisión `APPROVED` tras correcciones |
| 2B — Estructura de Senda | Completada | Revisión `CHANGES_REQUESTED`; correcciones aplicadas; verificación estática |
| 2C — Selector y Showdown | Completada | `php -l`, PHPStan, comprobaciones manuales |
| 2D — Costes, captura y divisor | Completada | `php -l`, PHPStan, vectores manuales |
| 3 — UI | Completada | `php artisan view:cache` correcto |
| 4 — Documentación/ADR | **Incompleta** | El `documenter` agotó pasos; no se creó ADR-004 ni se actualizaron docs |
| 5 — Seguridad | **Omitida por el desarrollador** | Auditoría parcial con hallazgos medium; no se completó |
| 5 — Revisión final | **Parcial** | Fases 1, 2A y 2B revisadas; 2C/2D/3 sin revisión independiente final |
| QA/tests | **No aplica** | Tests desactivados por el desarrollador (2026-11-02) |

## 3. Decisiones clave

- **EXP:** `pool = floor(Σe × multiplicadorExp × factor)`; `cuenta = floor(pool × fraccionCuenta)`; `destino = intdiv(pool − cuenta, miembros)`; invariante `cuenta + destino×miembros ≤ pool`.
- **Mazmorra:** caramelos ×70, EXP ×10; sin IV/EV ni pasivos de Senda.
- **Senda visible:** `nivelVisible = min(3, required_level)`; `tramo` 1-5 para coste; los tramos 4-5 se desbloquean a nivel 3.
- **Pasivos:** absolutos, no compuestos: N4 = 0,10; N5 = 0,20.
- **Captura:** `pBase = min(0.25, capture_rate/255 × factor_habitat)`; `p = min(0.30, pBase × refugio × (1 + bonoSenda))`.
- **Tipo:** divisor 250; un solo `floor`.
- **Movesets:** 6 movimientos; pool = nivel disponible ∪ (aprendible por MT/tutor/huevo/nivel superior ∩ desbloqueado); caps por tier; manuales de gimnasio primero.
- **Configuración:** `config/sendas.php` y `config/combate.php`; adaptadores `SendaProgreso`, `SendaCostes`, `ConfiguracionCombate`.
- **IV/EV:** `PerfilIvsEvs` y `PoliticaIvsEvs`; `DistribuidorStatsValor::distribuirPorRanking`.

## 4. Archivos principales

**Dominio nuevo**
- `src/Shared/Domain/Sendas/{NivelSenda,BeneficiosSenda,CurvaSenda,MapaSendaJugador,PoliticaSenda}.php`
- `src/Shared/Domain/{PerfilIvsEvs,PoliticaIvsEvs,MultiplicadoresDificultad,ReglasEv}.php`
- `src/Exploraciones/Domain/{CadenciaEventos,DificultadHabitat}.php`
- `src/Battle/Domain/Selector/PerfilMovesetRival.php`
- `src/Battle/Domain/Chain/{ManejadorSendaOfensiva,ManejadorSendaDefensiva}.php`

**Aplicación**
- `app/Support/{ArbolSendas,ReclutadoSerializer,SendaProgreso,SendaCostes,CargarMapaSendaJugador,CargarMovimientosDesbloqueados,ConfiguracionCombate,PreferenciasShowdown}.php`
- `app/Console/Commands/BuildShowdownMovesets.php`
- `src/CombateEntrenadores/App/SelectorMovimientosRival.php`
- Generadores de ruta/entrenador/gimnasio y `IniciarCombate*`.
- `PresentadorResultadoBatalla`, `OtorgarRecompensas*`, `CalculadorRecompensas`, `FinalizarExploracionHandler`, `ProcesarExploracionHandler`.

**Configuración y datos**
- `config/sendas.php`, `config/combate.php`
- `resources/data/showdown_movesets.json` (283 KB)
- `THIRD_PARTY_NOTICES.md`

**UI**
- `resources/views/sendas/{index,show}.blade.php`
- `resources/views/equipos/index.blade.php` y parciales
- `resources/views/livewire/combate.blade.php`

## 5. Verificación ejecutada

- `php -l` sin errores en los archivos clave de 2C/2D/3 y en los controladores de seguridad.
- `vendor/bin/pint` sobre los archivos modificados: pass en los lotes revisados.
- `vendor/bin/phpstan` sobre archivos nuevos/modificados de 2A/2B/2C/2D: sin errores en esos archivos; el proyecto mantiene errores preexistentes de baseline (~33) en archivos ajenos.
- `php artisan view:clear` y `php artisan view:cache`: correctos.
- `php artisan showdown:build-movesets`: artefacto generado; `--check` correcto.
- Comprobaciones manuales: vectores de daño 150/180/144, captura 25/14,65/6,44 % y Senda 5 30/17,58/7,73 %, caramelos 10/12, perfiles IV/EV, granja 150/180/250+255, selector con 6 movimientos y fallback ≥4.
- **Tests:** no se generaron ni mantuvieron; no se ejecutó la suite como parte del flujo, por decisión del desarrollador.
- **Seguridad:** auditoría parcial; no se completó por decisión del desarrollador.

## 6. Hallazgos y riesgos residuales

1. **Tests desactivados:** no hay evidencia de suite verde para 2B/2C/2D/3. Los contratos cambiados (constructores de `IniciarCombate*`, `ProbabilidadCaptura`, costes de granja, `nivel_senda` visible) pueden romper tests existentes. Es el riesgo principal.
2. **Seguridad omitida:** la auditoría parcial detectó carreras en `discard`/`discardAll` y `darEvs`; se aplicaron locks en esos flujos, pero quedan pendientes: saturación de `player_type_paths.exp` a 72.000 en recompensas, validación de configuración (`por_caramelo=0`, costes negativos), `--out` del comando Showdown, `unserialize` de sesión y rate limiting.
3. **Documentación incompleta:** falta `docs/adr/ADR-004-economia-sendas.md` y la actualización de `docs/architecture.md`, `docs/conventions.md` y `context.md` de módulos. Hay afirmaciones obsoletas (captura ×0,5, mazmorra 7×, reparto 80/3, `m_tipo` antiguo).
4. **Revisión final parcial:** 2C/2D/3 no tienen revisión independiente final; solo verificación estática/manual.
5. **Datos:** 632 nodos en seeder limpio (186/114/332, coste 9.432) vs 639 en BD actual (187/114/338, 9.584) por 7 movimientos de formas no disponibles. Showdown: 449/509 especies (no 435) por el fallback de `pokemon.csv`.
6. **Incidente de commit:** un subagente creó el commit local `a75290f` con un test; el orchestrator lo deshizo con `git reset --mixed HEAD~1`. **No hubo push**. HEAD es `efa5cfd`; el WIP preexistente sigue sin commitear.
7. **Constructores:** `IniciarCombateRuta`, `IniciarCombateEntrenador` e `IniciarCombateGimnasio` añaden `CargarMovimientosDesbloqueados`; las instanciaciones manuales (tests) deben actualizarse si se reactivan.
8. **Despliegue:** ejecutar `php artisan config:cache` y `php artisan view:cache` tras añadir `config/sendas.php`/`config/combate.php`.
9. **Dependencia de datos:** el selector y la captura dependen del catálogo de movimientos/especies; un cambio de seeders puede alterar los vectores.
10. **PHPStan:** baseline con errores preexistentes; no se certifica el análisis global.

## 7. Próximos pasos recomendados

1. Smoke test manual: Senda (desbloqueo, coste, 422 al tope, beneficios), selector de movimientos, ruta/entrenador/gimnasio (6 moves, IV/EV), mazmorra (sin Senda), granja (costes), captura.
2. Si se reactivan tests: actualizar expectativas de `ProbabilidadCaptura`, costes de granja, `nivel_senda`/`tramo_senda`, constructores de `IniciarCombate*` y `CalculadorRecompensas`.
3. Completar ADR-004 y documentación de módulos; corregir afirmaciones obsoletas.
4. Si se retoma seguridad: saturación de EXP de Senda en recompensas, validación de config, `--out` restringido, `unserialize` con `allowed_classes` y rate limiting.
5. Ejecutar `php artisan config:cache` y `view:cache` en despliegue.
6. Revisar el artefacto Showdown si se actualiza la fuente; `showdown:build-movesets --check` en CI.

## 8. Conclusión

El desarrollo funcional está implementado y verificado estáticamente. La entrega queda **COMPLETADA con riesgos residuales** por las decisiones explícitas del desarrollador de desactivar tests y omitir seguridad, y por la documentación de Fase 4 pendiente. No se ha hecho `git push` ni se ha alterado el WIP preexistente.
