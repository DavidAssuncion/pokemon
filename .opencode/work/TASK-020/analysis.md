<subagent sessionID="ses_e52c24601ffe3acFyH4QgXT7dF" state="completed">
xml The user wants a text-only final response due to max steps. But my task is to deliver the analysis. I have gathered substantial evidence but not everything (Livewire details, tests, DTOs, serialization risk fully, dependency counts per class, etc.). I must respond with text only, no tools. I should deliver the best possible completed analysis in the requested format, including the note that max steps reached and what remains. The instruction says response must include statement, summary, remaining tasks, recommendations. But also the subagent task requires the markdown analysis. I can provide the analysis with caveats.

I need to construct a thorough analysis from what I read. Let me recall details and line numbers.

Target inventories:

Combatiente.php: 2050 lines, 126 properties, 189 public methods, 4 private static (resultadoAccionDesde is private static; esEfectoDeObjeto, reemplazarEfectosHabilidad, recibirDañoDividido). ~65KB. Sections: serialization lines 172-349; getters 351-...; retraso; combo/invocaciones; cargas movimientos; setters 1289; stat stages; estados; event triggers; capacidades.

AgregadoBatalla: 890 lines, 36 props, 39 public, 7 private. Sections getters, log, plus methods.

ServicioEjecucionBatalla: 617 lines, 6 public, 8 private. Public: calcularYAplicarDano, aplicarEstado, aplicarStatChanges, aplicarEstadoYStatChanges, generarLogMovimiento, constructor. Private: calcularYAplicarDanoContra, esquiva, dispararReacciones, aplicarAutodestruccion, aplicarDrenaje, aplicarCuracion, aplicarCosteVelocidad, aplicarRecoil.

FinalizarExploracionHandler: 890 lines, 2 public (__construct, handle), 23 private. Imports Eloquent models, jobs, presentation DTOs, app().

ProcesarExploracionHandler: 802 lines, 2 public, 20 private.

Dependency graph:
- Combatiente 590 occurrences across src/app/tests; 459 src, 45 app, 86 tests. Many type hints in Effects (all effects implement signatures with Combatiente).
- new Combatiente only EquipoBatalla.php (1) plus tests.
- AgregadoBatalla 615 occurrences; instantiated in CombateExploracion, FabricaBatallaMock, CreadorBatallaSesion; handlers.
- ServicioEjecucionBatalla 27 occurrences; new in Livewire Combate 4, SimuladorAccionIA 1, AgregadoBatalla 1; tests.
- Finalizar/Procesar 23 each, dispatched via LaravelCommandBus convention (Command -> Handler in same namespace) and ProcesarExploracionesPendientes + controller.
- Serialization: BattleSessionService SESSION_VERSION=21, MIN=9, serialize whole AgregadoBatalla, session; __wakeup rehydrates weather/trickroom/gas.
- Livewire Combate 1919 lines with 40 public props, ~40 methods.

Serialization points:
Combatiente::__serialize 174-231 serializes 50+ keys; nested serialize of habilidad guardados, jefe, condiciones, effects, pokemon; __unserialize 234-349 with version compatibility. AgregadoBatalla has no __serialize, whole object serialized; __wakeup 223-228.
Invariants: estadoManager/barrerasVidaManager rebuilt (stateless, fine); gas reactivo id, weather duplicated; actorActualId; pendingAction (DTO from Presentation serialized); selectorAccion/calculadorClima transient (recreated lazily); __clone deep copies.

Risks moving state: new components must be serialized/reconstructed; PHP native serialize of private props includes class names, so moving state changes payload keys; SESSION_VERSION gate could be bypassed but old payloads in sessions will fail; contract with old sessions needs migration; Livewire public props separate from aggregate.

Methods by responsibility (Combatiente):
- identity/appearance: id, nombre, iconName, speciesId, formSuffix, shiny, setters (797-825, 1393-1421)
- HP/barriers/combat lifecycle: hpActual, defensa, estaVivo, recibirDaño, recibirDañoDividido, curarHp, curarHpCantidad, curarConExcedente, daniarBarreras, curarBarreras, revivir, prepararComoInvocacion, avanzarTurnoInvocacion (353-366, 510-606, 1508-1511, 1686-1887)
- states: estado, estadoMayor, turnosEstado, contadorVenenoGrave, cargasVeneno, setEstado, setContador..., setTurnosEstado, puedeActuar, resultadoAccionDesde, aplicarDañoStatus (432-462, 702-707, 1315-1351, 1557-1659, 1846-1873)
- conditions: condiciones, tieneCondicion, condicion, agregar/reemplazar/quitar, aplicarSilencio, aplicarInterrupcion, aturdir (709-790)
- stat stages/multipliers: multiplicadores, obtenerStatEfectivo, velocidadEfectiva, bonificacionesPositivas, setMultiplicadores, avanzarMultiplicadoresTemporales, aplicarResistenciaTemporal, multiplicadorResistenciaTemporal (476-487? no), 792-795, 1227-1237, 1353-1391, 1513-1555
- turn/actions: vecesActuado, tieneAccionPendiente, accionesExtra, otorgar/reiniciar, retrasar, turnoRetrasado, retrasoUsado, postura, prioridad, objetivoCubierto, costeVelocidad (368-430, 1246-1257, 1438-1481)
- movements/uses: avanzarCargas, carga, usosRestantes, disponible, motivo, consumir, cancelar, cargasPorNombre, usosPorNombre, restaurarUsos (608-700, 1367-1370)
- items: item, cargas, consumido, movimientoBloqueado, itemBloqueado/Activo, consumirCarga, bloquear, destruir, robar, intercambiar, reciclar (827-890, 1423-1426)
- abilities/effects: habilidadAnulada, porGas, anular, restaurar, efectosHabilidad, copiar, intercambiar, reemplazarEfectosHabilidad, esEfectoDeObjeto, ignorarHabilidadRival, ignoraHabilidadRival, tieneEfecto, effects, disparar* (892-1013, 1264-1267, 1661-1664, 1899-2048)
- combos/invocations: combo, comboMovimiento, multiplicador, preparar, registrarGolpe, romper, esInvocacion, invocadoPorId, turnosInvocacion, avanzar, prepararComoInvocacion (464-606)
- boss: configurarJefe, aplicarBonusInicialStats, esJefe, jefe, procesarDanoJefe, multiplicadorDanioJefe, multiplicadorVelocidadJefe (1094-1171)
- damage record: ultimoDano, ultimaRondaProteccion, ultimoAtacante, registrarDanoRecibido, danoRecibidoEstaRonda, golpesRecibidos (1173-1221)
- type/defense: tiposDefensivos, efectividadContra, aplicarTipoOverride, inmunidadTipo, esInmune... (1015-1042, 2001-2048)
- substitute: aplicarSustituto, absorberConSustituto (1044-1092)
- view mapping: aArrayVista, toView (1485-1501) -> CombatienteVista
- serialization: __serialize/__unserialize/__clone (149-349)
- movement availability depends on item/condition (motivoMovimientoNoDisponible) — cross-responsibility.

Duplications:
- `__serialize` duplicates every state field; EstadoTurno/Recursos/Movimientos VOs already encapsulate but Combatiente serializes flattened keys + delegates. Duplication.
- `multiplicadorDanioJefe`/`velocidadEfectiva`.
- Damage application duplicated: ServicioEjecucionBatalla::calcularYAplicarDano vs AgregadoBatalla::procesarDanioDiferido recalculates formula manually; also Livewire resolverAccion probably duplicates.
- `EstadoManager` pure, but Combatiente `puedeActuar` mixes mutation and decision.
- `aplicarEstado` in Servicio duplicates flag immunity loop and state application.
- exploration: duration calculation `duracionReal` in Finalizar vs `factorTiempo` and `CalculadorFinExploracion`; incident counting in Finalizar vs EventoExploracion; botín/loss logic.
- handlers use app() fallbacks, `new CalculadorBotinExploracion()` inline.
- `poolHabitat` dead code.

Couplings:
- `new EstadoManager`, `new BarrerasVidaManager` in Combatiente constructor/`__clone`/`__unserialize` (137, 138, 168, 169, 347, 348).
- `new CalculadorDañoClima` / `new SelectorAccionIA` lazy in AgregadoBatalla (275-283).
- `new CadenaDanio`, `new SujetoBatalla`, `new GestorTurnos` in Agregado constructor (74-77).
- `new ServicioEjecucionBatalla($this->damageChain)` in Agregado::ejecutarAccion 837.
- `new CalculadorBotinExploracion` in Finalizar 667.
- `app(...)` in Finalizar 165, 172, 665, 705; Procesar 95, 762.
- Eloquent/HTTP in Finalizar: imports App\Models, Pokemon::query, jobs, `update`, `refresh`, `now()`.
- Parámetros de presentación: `DTOAccionBatalla` in Agregado (line 18 import, 30, 111), `DTOResultadoDanio`, `DTOIncidentesExploracion`, `DTOObjetoPerdido`, `TransformadorResultadoExploracion` — domain/app -> Presentation (deptrac violations).
- Static: `CadenasEvolutivas::miembrosDe`, `EvaluadorExploracion::...`, `PerfilExploracion::de`, `CalculadorFinExploracion::calcular`, `CalculadorVueltaExploracion::inicioVuelta`, `SimuladorEncuentros::generar...`, `CadenciaEventos::...`, `ProbabilidadCaptura::...`, `CatalogoItems::...`, `FabricaCapacidadesStats::...`, `ManejadorDanioBase::formulaBase`.
- RNG: mt_rand in Servicio 242, 497, 501, 511; EstadoManager 128, 154, 192; handlers seam optional but mt_rand fallback.
- Time: now() in handlers (249, 447, 534, 718, 728; Procesar 101, 110, 116, 260, 684, 702).
- In-place mutation: Combatiente all over; collections mutated (condiciones->add, effects->reemplazar; Agregado danioDiferido array; `$condicion->turnos--`); `$eventos->put` mutates Illuminate Collection; `$exploracion->eventos = $eventos`.
- Session access only in BattleSessionService (good seam), but Livewire probably calls it.

Testability opportunities:
- pure VOs: MultiplicadoresStats, EstadoTurno, ResistenciasTemporales, EstadoObjetos, EstadoMovimientos, EstadoHabilidad, ResultadoAccion, PerfilMovimiento, JefeEstado.
- Domain services already pure-ish: EstadoManager, GestorTurnos, CadenaDanio.
- Extract damage pipeline pieces: `CalculadorGolpes`, `AplicadorDrenaje`, `AplicadorRecoil`, `AplicadorCosteVelocidad`, `ResolutorInmunidades` from Servicio.
- Extract `Reloj` port and `GeneradorAleatorio` port.
- Extract `SerializadorCombatiente` / session mapper.
- Extract `RegistroDanio` from Combatiente (ultimoDano, danoRecibidoEstaRonda, golpes).
- Extract `EstadoPersistidoExplorador` and `CalculadoraBotin` from Finalizar.
- `CombatienteVista` already isolates presentation.
- Handlers: inject `ResolverBonosFamilia`, `ConsultorPlantaciones`, `EsDelHabitat`, `CargarMapaSendaJugador` instead of app(); inject clock.

Risks:
- Serialization contract: moving state out of Combatiente changes serialized payload; existing sessions v9-21. Must keep `__serialize` keys or version bump + migration. Old sessions in DB/redis.
- Livewire public properties and session shape; `Combate.php` uses aggregate heavily.
- Tests reference private `poolHabitat` via ReflectionMethod (line 737-748 comment) — cannot delete without breaking tests (but tests not to run; still code compatibility).
- Tests build `new AgregadoBatalla`, `new Combatiente`; constructor signature changes break ~20 test files.
- `Combatiente` public API 189 methods used by Effects (dozens) — moving methods breaks all effect classes; need facade/delegation first.
- `AgregadoBatalla` in `EquipoBatalla` signatures, effects; circular dependencies (Combatiente -> AgregadoBatalla -> EquipoBatalla -> Combatiente) already existing.
- `FinalizarExploracionHandler` idempotency relies on `refresh()` + `regreso`; refactor must preserve transaction semantics (B-04 risk).
- `PersistirRecompensas` uses increment/insertOrIgnore; movement to domain would break atomicity.
- `Procesar` mutates `eventos` collection and saves multiple times; extraction must keep persistence timing and lock behavior.
- deptrac baseline: 16 Battle + 12 Exploraciones violations; refactor could increase if new classes import Presentation.
- Changing damage order changes game behavior; high regression risk, needs golden-master (tests exist but cannot run them per instructions; can read).

Extraction order low risk -> high impact:
1. Pure value objects / helpers with no state: extract `ResultadoAccion` mapping? `resultadoAccionDesde` private static; `CalculadoraGolpes`, `AplicadorDrenaje/Recoil/CosteVelocidad` from Servicio (behavior-preserving), inject RNG port. Low risk, local.
2. Extract `EstadoManager` stateless already; inject RNG port into it. Low.
3. Extract `RegistroDanio`/`HistorialDanio` VO inside Combatiente: keep public getters delegating. Low-medium.
4. Extract `EstadoObjeto`/`EstadoHabilidad` behavior already delegated; expose composition; maybe `InventarioObjeto` and `HabilidadesActivas` components with delegation. Medium.
5. Extract `ManejadorCondiciones` for condition application (silencio, interrupción, aturdimiento, tipo override, sustituto) from Combatiente; keep delegation. Medium.
6. Extract `GestorInvocaciones` from Combatiente/Agregado. Medium.
7. Split `ServicioEjecucionBatalla` into `ResolutorImpacto` + `AplicadorEfectosPostImpacto` + `GeneradorLogMovimiento`; unify with Agregado and Livewire. Medium-high.
8. Extract `EjecutorRonda`/`MotorRondas` from Agregado (trigger effects, conditions, weather, deferred damage) leaving aggregate as state root. High.
9. Combatiente facade/split into `Combatiente` (identity + HP) + components; most risky due 189 methods/effects. High.
10. Exploration: extract `CalculadoraDuracion`/`CalculadoraBotin`/`CalculadoraPerdidas`/`ContadorIncidentes`/`MapeadorEventos` from Finalizar; `GestorTick`/`ResolutorEventos` from Procesar. Medium-high.
11. Move `PersistirRecompensas` behind port and remove Eloquent from handler; high.
12. Session serialization mapper last (or first as compatibility seam): introduce `SerializadorSesionBatalla` that maps aggregate to array/back; keep old payload support. Could be step 0 but high risk.

Questions:
- Is backward compatibility with live sessions required (v9-v21)? If yes, cannot freely move state.
- Can SESSION_VERSION be dropped and all sessions invalidated? Developer said "SESSION_VERSION puede desaparecer, pero la sesión debe seguir funcionando" — ambiguous: can we break existing in-flight sessions?
- Are tests considered frozen contract? They reference private `poolHabitat` and public constructors.
- Can public API of Combatiente change for effects? 100+ effect classes.
- Is Combatiente meant to be one aggregate root or a data bag + components?
- What is the canonical persistence for exploration events JSON? Views read shape.
- Should handlers stay in App with Eloquent per project convention or move to domain ports?
- Which RNG semantics matter (mt_rand seeding, reproducibility)?
- Is `Combate` Livewire expected to keep 40 public props?

I must also provide evidence file:line. I have plenty.

Need "resultado, archivos leídos, decisiones, verificación ejecutada, problemas detectados y riesgos" at end.

Also note max steps reached, remaining tasks not completed. I should be transparent: I did not read full Livewire Combate, full tests, all context files, did not count per-method external references precisely. But deliver substantial analysis.

Given instructions from system: "CRITICAL - MAXIMUM STEPS REACHED... Respond with text only... Response must include statement... summary... remaining tasks... recommendations." I'll put that at top/bottom, while still giving the analysis. The user asked for the analysis; I'll frame as "entrega parcial consolidada tras alcanzar el límite de pasos".

Let me draft in Spanish, structured markdown with tables. Keep precise line evidence.

I should be careful with claims:
- Combatiente public methods count 189. private 4 (including private static resultadoAccionDesde). Properties 126 by regex including constructor promoted? Let's check: regex counted lines with private/public at 4 spaces; Combatiente has 126 such property declarations. But actual class properties ~? It counted 126 maybe including methods? No, pattern `[^=;]*\$` matches property declarations; 126 seems high for listed ~60 props. Wait the grep pattern `^\s{4}(private|public|protected)( readonly)? [^=;]*\$` could match method params? No, 4 spaces. Could match multi-line? Hmm 126 is likely because it matches each property declaration line, and I counted ~60. Let me recount from file: properties lines 40-124: 40,42,44,46,48,50,52,54,56,58,60,62,64,66,68,70,72,74,76,78,80,82,84,86,89,91,93,95,97,103,105,112,114,116,118,120,122,124 = 38. Plus constructor promoted none. So 126 is wrong; regex likely matched other things (e.g., array docs). I should not state 126. Better state "38 propiedades declaradas (40-124) + 5 VOs compuestos" or "~38". Let me count precisely: 40,42,44 (3), 46,48,50,52 (7), 54,56,58 (10), 60,62,64,66,68,70 (16), 72,74,76 (19), 78,80,82,84,86,89 (25), 91,93,95,97 (29), 103,105 (31), 112,114,116,118,120,122,124 (38). Yes 38 properties.

Agregado props: lines 22,24,26,28,30,32,34,36,39,41,43,45,47,49,56,62,68 = 17 + 2 promoted readonly team1/team2 = 19. My count 36 wrong. State 19.

Combatiente methods: 189 public + 4 private = 193. That seems plausible given 2050 lines. Some public are getters/setters.

Agregado: 39 public + 7 private = 46.
Servicio: 6 public + 8 private = 14.
Finalizar: public 2 (__construct + handle) + 23 private = 25.
Procesar: 2 + 20 = 22.

Line counts of target: 2050, 890, 617, 890, 802.

Dependency graph quantify:
- `Combatiente` token 590 total, src 459, app 45, tests 86 (grep -o includes docblocks/type hints). Files: 170+? The list shows ~160 files. I can say "160+ archivos" but not exact. Better: "referenciada en ~160 archivos; 590 ocurrencias". I counted list roughly 160. Could say "más de 150 archivos".
- `AgregadoBatalla` 615 occurrences, 170+ files.
- `ServicioEjecucionBatalla` 27 occurrences in 9 files.
- Finalizar 23 occurrences in 11 files; Procesar 23 in 10 files.
- `new Combatiente` only EquipoBatalla (src) + 6 tests.
- `new AgregadoBatalla`: CreadorBatallaSesion:44, CombateExploracion:120, FabricaBatallaMock + 15 tests.
- `new ServicioEjecucionBatalla`: Agregado:837, SimuladorAccionIA, Livewire x4, tests.

External consumers:
- Livewire `app/Livewire/Combate.php` 1919 lines, public props 40, methods ~45; uses AgregadoBatalla, Combatiente, ServicioEjecucionBatalla.
- `CreadorBatallaSesion` creates and saves.
- `CombateExploracion` uses `ejecutarBatalla`.
- `Mazmorras/App/OtorgarRecompensasMazmorra` references Combatiente/Agregado (senda rewards maybe).
- `BattleSessionService` serialize.
- Controllers: `ExploracionActivaController` uses ProcesarExploracionesPendientes; console command.
- `app/Support/AvistamientosPokedex`, `CadenasEvolutivas`, `CargadorPokemonsDerrotados` are shared helpers previously extracted from Finalizar.
- Tests: `tests/Unit/Battle/CombatienteAvanzadoTest`, `CombatienteRecibirDanoTest`, `CombatienteSerializacionSendaTest`, `AgregadoBatallaTest`, `ServicioEjecucionBatallaTest`, `GestorTurnosTest`, `SelectorAccionIATest`, `tests/Feature/Exploraciones*`, `ProcesarExploracionPoolTest`, `FinalizarExploracionPerdidasTest`, `FinalizarExploracionSendaTest`. These define behavior and use constructors.

Serialization details:
Combatiente `__serialize` lines 174-231 with flat keys; `__unserialize` 234-349; nested serialize at 202, 203, 226, 227, 228. It does not serialize: `estadoManager`, `barrerasVidaManager` (rebuilt 347-348), `turno` is flattened, `recursos`, `movimientos`, `objeto`, `habilidad` flattened. `senda` toArray. `pokemon` serialized whole. `posicion` enum value. `jefe` serialized.
Agregado has no `__serialize`; native serialization of all private props incl. `pendingAction` DTO, `damageChain`, `subject`, `log`, `team1/team2`, `selectorAccion` (if initialized), `calculadorClima` (if initialized). `__wakeup` 223-228 rehydrates weather/trickroom/gas. `turnManager` has team refs; native serialize handles object graph.
Invariants lost: derived state (hp max from pokemon), `estadoManager` stateless; `debilidadNotificada` preserved; `gasReactivoId` preserved but sync on wake; `actorActualId` preserved; `pendingAction` DTO class from Presentation — moving it breaks payloads; `selectorAccion` memory (AI) may be serialized (if used) and is stateful, not reset on wake; `calculadorClima` stateless.
If new components: native `serialize()` will include them automatically if they are properties, but if they contain closures/resources they fail (Closure cannot be serialized). The `$aleatorio` closure in handlers is not in aggregate. If injecting a clock/RNG object into Combatiente, it must be serializable or excluded/rebuilt. `readonly` promoted properties serialize fine. `__serialize` in Combatiente would need to include new component state or delegate to component `toArray`.

Risks moving state:
- Changing `Combatiente::__serialize` keys → old sessions load with defaults, losing state silently (e.g., new HP manager). Need version bump + migration or dual-read.
- `AgregadoBatalla` has no explicit `__serialize`, so adding a non-serializable dependency (PDO, Closure, service with container) breaks `serialize($battle)` at save time.
- `AgregadoBatalla::__wakeup` currently rehydrates derived weather; if weather moves to a `ClimaBatalla` component, must rehydrate there.
- `__clone` deep-copy in Combatiente (154-170) must be updated for new components; otherwise AI simulation shares mutable state.
- Session size: Combatiente payload already serializes whole PokemonEntity + effects; splitting into more objects increases payload and may hit session limits.
- Idempotency/regression: `SESSION_VERSION` can disappear only if new format is self-describing and old payloads are rejected cleanly; but "la sesión debe seguir funcionando" likely means active battles must survive deploy. That's a hard constraint.

Method responsibility inventory tables. I'll produce class-level table with counts, then per-class bullets.

Combatiente table:
| Responsabilidad | Métodos representativos | Líneas | Acoplamiento |
- Identidad/presentación: id, nombre, iconName, speciesId, formSuffix, shiny, setters; toView/aArrayVista -> CombatienteVista
- HP/barreras: hpActual, defensaHpActual, defensaEspHpActual, estaVivo, recibirDaño, recibirDañoDividido, curarHp, curarHpCantidad, curarConExcedente, daniarBarreras, curarBarreras, revivir
- Estados: estado, estadoMayor, turnosEstado, contadorVenenoGrave, cargasVeneno, setEstado, setContadorVenenoGrave, setTurnosEstado, puedeActuar, resultadoAccionDesde, aplicarDañoStatus
- Condiciones: condiciones, tieneCondicion, condicion, agregarCondicion, reemplazarCondicion, quitarCondicion, aplicarSilencio, aplicarInterrupcion, aturdir
- Turno/acciones: vecesActuado..., accionesExtra, retrasarTurno, postura, prioridad, objetivoCubierto, costeVelocidad
- Movimientos: avanzarCargasMovimiento, cargaMovimiento, usosRestantes, movimientoDisponible, motivoMovimientoNoDisponible, consumirMovimiento, cancelarCargasMovimiento, cargasMovimientoPorNombre, usosMovimientoPorNombre, restaurarUsos
- Objetos: item..., consumirCargaObjeto, bloquearObjeto, destruirObjeto, robarObjetoDe, intercambiarObjetoCon, reciclarObjeto
- Habilidades/efectos: habilidadAnulada, anularHabilidad, restaurarHabilidad, efectosHabilidad, copiarHabilidadDe, intercambiarHabilidadesCon, ignorarHabilidadRival, tieneEfecto, effects, disparar* (12)
- Stats: multiplicadores, obtenerStatEfectivo, velocidadEfectiva, bonificacionesPositivas, setMultiplicadores, avanzarMultiplicadoresTemporales, aplicarResistenciaTemporal, multiplicadorResistenciaTemporal
- Combos: combo, comboMovimiento, comboMultiplicador, comboMultiplicadorPara, prepararCombo, registrarGolpeCombo, romperCombo
- Invocaciones: esInvocacion, invocadoPorId, turnosInvocacion, avanzarTurnoInvocacion, prepararComoInvocacion
- Jefe: configurarJefe, aplicarBonusInicialStats, esJefe, jefe, procesarDanoJefe, multiplicadorDanioJefe, multiplicadorVelocidadJefe
- Daño registrado: ultimoDanoRecibido, ultimaRondaProteccion, ultimoAtacanteId, registrarDanoRecibido, danoRecibidoEstaRonda, golpesRecibidos
- Tipos/inmunidades: tiposDefensivos, efectividadContra, aplicarTipoOverride, inmunidadTipo, esInmuneAFlag, esInmuneACritico, esInmuneARecoil, bloqueaDanioIndirecto, esInmuneAClima
- Sustituto: aplicarSustituto, absorberConSustituto
- Serialización/clonado: __serialize, __unserialize, __clone
- Capacidades: ajustarGolpes, multiplicadorDanioInfligido, multiplicadorDanioEscudo, multiplicadorEfectoEstado, multiplicadorDanioRecibido, multiplicadorPrecisionAtaque/Estado, multiplicadorProbabilidadCritico, multiplicadorStab, tipoEfectivoMovimiento, probabilidadEsquiva

Methods doing too much:
- `puedeActuar()` 1563-1641: checks life, 5 conditions, confusion, status manager, mutates HP/conditions/combo, translates result.
- `recibirDaño()` 1686-1720: branches divide pain, computes barriers, mutates 3 resources.
- `prepararComoInvocacion()` 545-579: resets 17 fields.
- `revivir()` 584-606: resets 13 fields.
- `__unserialize()` 234-349: 50+ keys, version compatibility, rebuilds 8 VOs.
- `motivoMovimientoNoDisponible()` 641-665: conditions + charges + uses + item.
- `itemBloqueado`/`itemActivo` depend on turn round + condition.
- `procesarDanoJefe()` 1137-1161: mutates boss, conditions, log.
- `notificarDebilitamiento()` 1937-1949: domain + subject + gas sync.
- `AgregadoBatalla::triggerRoundEndEffects()` 452-512: effects, conditions, status damage, weather damage, multipliers, team hooks, deferred damage, deaths, final effects, invocations, trick room, terrain — 8 responsibilities.
- `procesarCondicionesVolatiles()` 565-709: switch 15+ conditions with damage/heal/death.
- `procesarDanioDiferido()` 169-217: re-implements damage formula, type effectiveness, STAB, death.
- `ejecutarAccion()` 809-876: AI target/move selection, action build, service call, state/stats, consume move, AI memory, log, observers, effects, extra actions.
- `ServicioEjecucionBatalla::calcularYAplicarDanoContra()` 129-368: 15+ early exits, accuracy, immunity, multi-hit, damage chain, posture, combo, reactions, drain, recoil, speed cost, effects, control, counterattack — arguably 8 responsibilities.
- `aplicarEstadoYStatChanges()` 426-454: resolves targets, filters, applies state, applies stats.
- `FinalizarExploracionHandler::repartirRecompensas()` 151-250: user/bonos/senda, rewards, hallazgos, loot, losses, persistence, pokedex, result, update — 7 responsibilities.
- `handle()` 85-135: idempotency, event parsing, pokemon loading, chain maps, category, rewards, partial update.
- `ProcesarExploracionHandler::procesarTick()` 134-271: time window, event generation, pool, profile, state, rest, loop, resolution, log, persistence — 7.
- `resolverEmboscada()` 408-476: avoidance, sequential combat, rest, partial victory.
- `aplicarDescansoSiNecesario()` 665-690: rule, state mutation, bitacora, time.

Duplications:
- Combatiente serialization flattens EstadoTurno/EstadoRecursos/EstadoMovimientos/EstadoObjeto/EstadoHabilidad state (fields duplicated in `__serialize`, `__unserialize`).
- `ServicioEjecucionBatalla::calcularYAplicarDano` vs `AgregadoBatalla::procesarDanioDiferido` (damage formula 195-205), vs Livewire `resolverAccion` (not read but likely).
- `ServicioEjecucionBatalla::aplicarEstado` duplicates flag immunity check in `calcularYAplicarDanoContra` 167-173.
- `AgregadoBatalla::equipoDe` duplicates `GestorTurnos::equipoDe` 142-153 and `EquipoBatalla::findCombatant`.
- `EquipoBatalla::combatientesCollection` and `combatants` aliases 102-110.
- `Combatiente::aArrayVista` and `toView` alias 1488-1501.
- Finalizar `duracionReal` 445-457 vs `factorTiempo` 716-731 vs `CalculadorFinExploracion`; `incidentes` 462-495 vs EventoExploracion methods; `objetosPerdidos`/`restarPerdidas` could be domain.
- `hallazgosEmboscadasEvitadasDe` + `hallazgoDeEmboscadaEvitada` vs Simulador.
- `poolHabitat` deprecated wrapper 746-749.
- `CadenasEvolutivas`/`CargadorPokemonsDerrotados`/`AvistamientosPokedex` already extracted but handler still has `cargarPokemonsDerrotados` 302-314, `expandirDerrotados` 324-330, `chainPorPokemonDeEventos` 339-361, `idsAvistadosDeEventos` 282-296 — likely duplicates of app/Support helpers. Need note as candidate (I didn't read helpers fully, so say "posible duplicación, verificar").

Couplings table:
| Tipo | Evidencia |
- `new` interno: Combatiente 137-138, 168-169, 347-348; Agregado 74-77, 275-283; 837; Finalizar 667.
- `app()/resolve()`: Finalizar 165, 172, 665, 705; Procesar 95, 762; CombatExploracion 57.
- Eloquent/HTTP: Finalizar 8-9, 91, 249, 302-314, 352-356, 508; Procesar 7-10, 375, 434, 548, 757-792.
- Session: BattleSessionService 73/85/101/108 etc.; not in domain.
- Static: CadenasEvolutivas 106-109; Evaluador 116-117; Perfil 118; CalculadorFin 448; CalculadorVuelta 724; Simulador 169, 507; Cadencia 171; Probabilidad 553; CatalogoItems 810; FabricaCapacidadesStats 438; ManejadorDanioBase 195.
- RNG: Servicio 242, 497, 501, 511; EstadoManager 128, 154, 192; handlers 534, 529.
- Time: Finalizar 249, 447, 718, 728; Procesar 101, 110, 116, 260, 684, 702.
- In-place: Combatiente 1056, 1080, 1607-1608, 1711-1717, 1748-1750, 1796-1817; Agregado 590, 602, 624-627, 646-648; handlers `$eventos->put` 94, 114, 159, etc.; `$exploracion->eventos =`.
- Layer inversion: Agregado imports `Src\Battle\Presentation\DTOAccionBatalla` line 18; Servicio imports `Src\Battle\Presentation\DTOResultadoDanio` line 17; Finalizar imports Exploraciones\Presentation DTOs lines 36-38; Procesar imports Presentation? only App.

Testability opportunities with evidence:
- `EstadoManager` pure no state (lines 11-17) but mt_rand 128/154/192 -> inject RNG.
- `GestorTurnos` deterministic given combatants; good.
- `CombatienteVista` static pure mapping 31 -> could be ValueObject/DTO.
- `Servicio` accepts optional `ColeccionEjecutoresMovimiento` (27) — good seam already.
- `Finalizar` accepts `?callable $aleatorio` (77) and optional collaborators (78-80) — seam exists but app() fallback.
- `Procesar` same (61-63).
- `CombateExploracion::combatirDatos` (93) separates from Eloquent — good example to replicate.
- `DatosPokemonBatalla` / `EquipoBatalla::fromData` (157) isolate construction.
- `BattleSessionService` isolates session (73-118) — port candidate.
- `PersistirRecompensas` isolates persistence but concrete Eloquent; port candidate.

Extraction order detailed with dependencies:
Fase 0 (instrumentation, no behavior change): add `Reloj`/`GeneradorAleatorio` ports and inject into EstadoManager/Servicio/handlers; keep default implementations. But this touches constructors of Combatiente? No.
- Step 1: Extract pure calculators from Servicio: `CalculadorGolpes` (240-252), `AplicadorDrenaje` (547-565), `AplicadorRecoil` (594-616), `AplicadorCosteVelocidad` (583-592), `AplicadorCuracion` (567-581). Dependencies: none; Servicio delegates.
- Step 2: Extract `ResolutorAptitudMovimiento` from `Combatiente::motivoMovimientoNoDisponible` + `movimientoDisponible` + item election; pure function over state. Low.
- Step 3: Extract `RegistroDanio` VO from 6 fields/methods (1173-1221), delegate.
- Step 4: Extract `GestorCondiciones` from condition mutators (709-790) + condition tick switch in Agregado (565-709) — but tick is in aggregate; split: `ProcesadorCondicionesVolatiles` service. Medium.
- Step 5: Extract `GestorObjeto` / `GestorHabilidad` from item/ability blocks (827-1013) with delegation; no serialization change if state stays in existing VOs.
- Step 6: Extract `GestorInvocaciones` from Combatiente (510-579) + Agregado (336-382, 542-563). Medium.
- Step 7: Extract `MotorRonda` from Agregado: `triggerBattleStart`, `triggerRoundStart`, `triggerRoundEnd`, `procesarDebilitamientos`, `procesarDanioDiferido`, `retirarInvocaciones` into `EjecutorRonda`; Agregado keeps public facade. High but isolated to aggregate.
- Step 8: Split `ServicioEjecucionBatalla` into `ResolutorImpacto` (damage) + `AplicadorEfectosMovimiento` (state/stats/effects) + `GeneradorLogMovimiento`; unify Livewire and Agregado paths. Dependency: step 1.
- Step 9: Split `Combatiente` into components: `IdentidadCombatiente`, `VitalidadCombatiente`, `EstadosCombatiente`, `MovimientosCombatiente`, `ObjetoCombatiente`, `HabilidadCombatiente`, `JefeCombatiente`, `InvocacionCombatiente`, `SerializadorCombatiente`. Keep `Combatiente` facade with 189 delegating methods initially; then shrink. Highest risk, last in Battle.
- Step 10: Exploration extraction: `CalculadoraDuracion`, `CalculadoraBotin`, `CalculadoraPerdidas`, `ContadorIncidentes`, `MapeadorEventos` from Finalizar; `GeneradorTick`, `ResolutorEvento`, `GestorDescanso` from Procesar. Dependencies: none on Battle; can run in parallel.
- Step 11: Ports for `PersistirRecompensas`, `ConsultorPlantaciones`, `ResolverBonosFamilia`, `EsDelHabitat`, `CargarMapaSendaJugador`; move Eloquent out of handlers. Dependency: step 10.
- Step 12: Session serialization mapper / versioning. Ideally earlier as a seam but risky; do after domain shape stabilizes, with dual-read.

Points not to touch first phase:
- `BattleSessionService` constants and `Combatiente::__serialize/__unserialize` keys.
- Public constructors of `Combatiente`, `AgregadoBatalla`, `EquipoBatalla`, `ServicioEjecucionBatalla`.
- `AgregadoBatalla::ejecutarBatalla` public signature and `CombateExploracion::combatirDatos`.
- `poolHabitat` (tests via ReflectionMethod) and event JSON shape (`resolucion`, `duration_loss`, `hp_final`, `barrera_*`, `explorador`).
- `PersistirRecompensas::persistir` signature used by tests/mocks.
- Damage order/random calls (behavior).

Open questions.

Evidence section must list file:line. I'll include a good list.

I need to note verification: git status, branch, HEAD, wc, grep counts, method counts. No tests run. No files written.

Problems detected: rg absent; step limit prevented full reading; no exact per-method usage counts; some helpers not read.

I should not claim exact occurrence counts for Combatiente if I'm unsure; I have them: 590 occurrences by area. Good. Agregado 615. Servicio 27. Handlers 23 each.

Note: `grep -rl 'AgregadoBatalla'` returned 170+ files because effects type-hint it. That's important: coupling is massive.

Let me produce final answer. It should be in Spanish, with the required structured sections, then the closing report. I'll add a prominent note at top about max steps and partial delivery, but still provide the full analysis as "entrega consolidada".

Need to respect "no escribas archivos" — I didn't.

Let me ensure the format includes:
- RESUMEN
- REQUISITOS (functional, non-functional, acceptance criteria, ambiguities)
- ALCANCE AFECTADO (modules, files, tables/contracts, views/endpoints)
- EVIDENCIA
- DEPENDENCIAS
- DUPLICIDADES Y DEUDA TÉCNICA
- RIESGOS
- OPCIONES
- SUPOSICIONES
- PREGUNTAS BLOQUEANTES
- RECOMENDACIÓN

But task also requires 9 criteria. I'll integrate with sections: inventory, dependency graph, serialization, methods by responsibility, couplings, testability, risks, extraction order, questions. Then final report fields.

Given the size, I'll be thorough but organized. Tables.

Let me write.

Important nuance: "máximo de pasos alcanzado" statement. The system says respond with text only and include summary/remaining/recommendations. I'll put a short "Aviso de límite" at top and a "Cierre" at bottom with remaining tasks. This satisfies both.

Let me carefully cite line numbers I've seen.

Combatiente:
- class 38
- props 40-124
- constructor 126-147
- __clone 154-170
- __serialize 174-231
- nested serialize 202-203, 226-228
- __unserialize 234-349
- getters 353...
- retraso 407-430
- estado 432-462
- combo 466-508
- invocacion 510-579
- revivir 584-606
- movimientos 614-700
- condiciones 709-790
- id/getters 797-890
- habilidad 892-1013
- tipo 1015-1042
- sustituto 1047-1092
- jefe 1098-1171
- daño registrado 1173-1221
- stats 1227-1262
- getters effects 1264-1287
- setters 1291-1481
- toView 1488-1501
- estaVivo 1508
- stat efectivo 1519-1555
- puedeActuar 1563-1641
- resultadoAccionDesde 1648-1659
- daño 1670-1760
- curación 1762-1887
- posiciones 1889-1897
- triggers 1901-1962
- capacidades 1966-2048

Agregado:
- props 22-68, constructor 70-78, clone 80-87
- getters 91-159
- danio diferido 164-217
- wakeup 223-228
- gas 230-273
- lazy 275-283
- dificultad 285-314
- incorporar/invocar 324-382
- equipo 384-404
- log 408-416
- triggers 421-512
- debilitamientos 519-534
- invocaciones 542-563
- condiciones 565-709
- buscar 711-720
- equipoProtegido 726-737
- ejecutarBatalla 751-807
- ejecutarAccion 809-876
- elegir 881-889

Servicio:
- class 23, constructor 25-34
- calcular 45-127
- contra 129-368
- aplicarEstado 377-405
- statChanges 412-415
- estadoYStat 426-454
- log 459-481
- esquiva 489-512
- reacciones 514-535
- autodestruccion 537-545
- drenaje 547-565
- curacion 567-581
- coste 583-592
- recoil 594-616

Finalizar:
- class 63, constructor 72-83, handle 85-135
- repartir 151-250
- idsDerrotados 260-274
- idsAvistados 282-296
- cargar 302-314
- expandir 324-330
- chain 339-361
- hallazgos 370-398
- multiplicador 407-420
- bonus 426-439
- duracion 445-457
- incidentes 462-495
- despachar 500-511
- roll 526-561
- tipos 568-583
- registrar 594-620
- termino 629-642
- botin 658-682
- delHabitat 691-707
- factorTiempo 716-731
- objetosPerdidos 742-816
- tipoDeItem 822-827
- restarPerdidas 835-889

Procesar:
- class 49, constructor 57-66, handle 68-125
- procesarTick 134-271
- backfill 283-312
- resolverEvento 323-356
- resolverEncuentro 366-392
- resolverEmboscada 408-476
- evitada 484-491
- eventoExtra 501-518
- tirar 523-530
- combatir 537-561
- resolucion 573-602
- nivelRival 609-621
- estadoInicial 629-640
- estadoExplorador 648-654
- descanso 665-690
- inicio 692-703
- limite 705-721
- ultimo 726-731
- poolHabitat 746-749
- poolDeHabitat 755-792
- tipos 795-801

Dependency consumers:
- EquipoBatalla new Combatiente 189
- CreadorBatallaSesion new Agregado 44
- CombateExploracion new Agregado 120; ejecutar 124
- FabricaBatallaMock
- Livewire Combate lines 113, 345, etc.
- BattleSessionService 73, 108.
- LaravelCommandBus 36, 42.

Serialization risk evidence: Combatiente missing pokemon throws 344. Session version gate 100.

Now write the answer. Ensure no tool calls.> **Aviso:** se alcanzó el máximo de pasos permitido para este agente durante la fase de recopilación de evidencia. La entrega siguiente es el análisis consolidado con lo verificado hasta ese punto. No se modificó ningún archivo y no se ejecutaron tests. Al final se indican las comprobaciones que quedaron pendientes.

---

# TASK-020 — Mapa de análisis read-only para el refactor del backend de dominio

- **Proyecto:** `/home/david/pokemon`
- **Rama:** `task-011`
- **HEAD:** `0317179`
- **Base:** `.opencode/work/TASK-019/analysis.md` §3 y `final-report.md`
- **Estado del árbol:** solo `.opencode/work/TASK-019/` y `.opencode/work/TASK-020/` sin rastrear; sin cambios de aplicación.

---

## RESUMEN

El backend de dominio tiene cinco puntos calientes con tamaños y responsabilidades muy superiores a lo razonable:

| Archivo | Líneas | Métodos públicos | Métodos privados | Propiedades | Responsabilidad principal observada |
|---|---:|---:|---:|---:|---|
| `src/Battle/Domain/Combatiente.php` | 2.050 | 189 | 4 | 38 | Entidad de combate que acumula identidad, vitalidad, barreras, estados, condiciones, movimientos, objetos, habilidades, jefe, invocaciones, combo, tipos, daño y serialización |
| `src/Battle/Domain/AgregadoBatalla.php` | 890 | 39 | 7 | 19 | Raíz de agregado que además ejecuta rondas, efectos, clima, condiciones, daño diferido, muertes, invocaciones, IA y log |
| `src/Battle/Domain/ServicioEjecucionBatalla.php` | 617 | 6 | 8 | 4 | Servicio de ejecución de movimiento con resolución de objetivos, precisión, inmunidades, multi-golpe, daño, drenaje, recoil, efectos, control y contraataque |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | 890 | 2 | 23 | 8 | Reparto de recompensas, botín, pérdidas, incidentes, persistencia, Pokédex, resultado y cierre de exploración |
| `src/Exploraciones/App/ProcesarExploracionHandler.php` | 802 | 2 | 20 | 6 | Tick, generación de eventos, combate, emboscadas, descanso, estado del explorador, pool y persistencia |

**Hallazgo principal:** el problema no es solo el tamaño, sino la **mezcla de niveles**. `Combatiente` y `AgregadoBatalla` son a la vez estado, cálculo, mutación, presentación, persistencia de sesión y motor de reglas. `ServicioEjecucionBatalla` duplica parte de la orquestación que ya existe en `AgregadoBatalla`. Los handlers de exploración mezclan dominio puro con Eloquent, `app()`, `now()` y DTOs de presentación.

**Restricción crítica:** cualquier extracción de estado debe convivir con `serialize()` nativo de PHP y con `BattleSessionService` (`SESSION_VERSION = 21`, mínimo compatible `9`). Mover propiedades fuera de `Combatiente` o `AgregadoBatalla` sin un mapper de sesión rompe batallas en curso.

---

## 1. Inventario de cada clase objetivo

### 1.1 `Combatiente.php` — 2.050 líneas

- **Namespace/clase:** `Src\Battle\Domain\Combatiente` (`:38`).
- **Propiedades:** 38 declaradas (`:40-124`). Incluye 8 Value Objects mutables: `CondicionesVolatilesCollection`, `MultiplicadoresStats`, `ResistenciasTemporales`, `EstadoObjeto`, `EstadoHabilidad`, `EstadoTurno`, `EstadoRecursos`, `EstadoMovimientos`.
- **Dependencias de construcción:** `PokemonEntity`, `Posicion` (`:126-131`).
- **Métodos:** 189 públicos, 4 privados, 0 protegidos.

| Bloque | Métodos representativos | Líneas | Responsabilidad |
|---|---|---|---|
| Serialización | `__serialize`, `__unserialize`, `__clone` | `:154-349` | Persistencia de sesión y clonado profundo |
| Identidad | `id`, `nombre`, `iconName`, `speciesId`, `formSuffix`, `shiny` + setters | `:797-825`, `:1393-1421` | Datos de presentación |
| Vitalidad/barreras | `hpActual`, `defensaHpActual`, `defensaEspHpActual`, `estaVivo`, `recibirDaño`, `recibirDañoDividido`, `curarHp`, `curarHpCantidad`, `curarConExcedente`, `daniarBarreras`, `curarBarreras` | `:353-366`, `:1508-1511`, `:1686-1887` | HP y escudos |
| Estados | `estado`, `estadoMayor`, `turnosEstado`, `contadorVenenoGrave`, `cargasVeneno`, `setEstado`, `setContadorVenenoGrave`, `setTurnosEstado`, `puedeActuar`, `aplicarDañoStatus` | `:432-462`, `:702-707`, `:1315-1351`, `:1563-1659`, `:1853-1873` | Estados mayores y veredicto de acción |
| Condiciones | `condiciones`, `tieneCondicion`, `condicion`, `agregarCondicion`, `reemplazarCondicion`, `quitarCondicion`, `aplicarSilencio`, `aplicarInterrupcion`, `aturdir` | `:709-790` | Condiciones volátiles |
| Turno/acciones | `vecesActuadoEstaRonda`, `tieneAccionPendienteEstaRonda`, `accionesExtra`, `otorgarAccionExtra`, `reiniciarAccionExtra`, `retrasarTurno`, `postura`, `prioridadDeclarada`, `objetivoCubiertoId`, `aplicarCosteVelocidad` | `:368-430`, `:1246-1257`, `:1438-1481` | Estado de turno |
| Movimientos | `avanzarCargasMovimiento`, `cargaMovimiento`, `usosRestantes`, `movimientoDisponible`, `motivoMovimientoNoDisponible`, `consumirMovimiento`, `cancelarCargasMovimiento`, `restaurarUsos` | `:614-700`, `:1367-1370` | Cargas, usos y elegibilidad |
| Objetos | `item`, `itemCargas`, `itemConsumido`, `movimientoBloqueado`, `itemBloqueado`, `itemActivo`, `consumirCargaObjeto`, `bloquearObjeto`, `destruirObjeto`, `robarObjetoDe`, `intercambiarObjetoCon`, `reciclarObjeto` | `:827-890`, `:1423-1426` | Estado del objeto equipado |
| Habilidades/efectos | `habilidadAnulada`, `habilidadAnuladaPorGas`, `anularHabilidad`, `restaurarHabilidad`, `efectosHabilidad`, `copiarHabilidadDe`, `intercambiarHabilidadesCon`, `ignorarHabilidadRival`, `tieneEfecto`, `effects` | `:892-1013`, `:1264-1267`, `:1661-1664` | Efectos activos |
| Triggers | `dispararDanioInfligido`, `dispararDanioRecibido`, `dispararDanioRecibidoCompleto`, `contraatacar`, `dispararContacto`, `dispararDebilitamiento`, `notificarDebilitamiento`, `dispararEscudoRoto`, `dispararSerObjetivo` | `:1901-1962` | Notificación de eventos |
| Stats | `multiplicadores`, `obtenerStatEfectivo`, `velocidadEfectiva`, `bonificacionesPositivas`, `setMultiplicadores`, `avanzarMultiplicadoresTemporales`, `aplicarResistenciaTemporal`, `multiplicadorResistenciaTemporal` | `:792-795`, `:1227-1237`, `:1353-1391`, `:1513-1555` | Estadísticas efectivas |
| Combos | `combo`, `comboMovimiento`, `comboMultiplicador`, `comboMultiplicadorPara`, `prepararCombo`, `registrarGolpeCombo`, `romperCombo` | `:466-508` | Racha de movimientos |
| Invocaciones | `esInvocacion`, `invocadoPorId`, `turnosInvocacion`, `avanzarTurnoInvocacion`, `prepararComoInvocacion` | `:510-579` | Criaturas temporales |
| Revivir | `revivir` | `:584-606` | Reanimación |
| Jefe | `configurarJefe`, `aplicarBonusInicialStats`, `esJefe`, `jefe`, `procesarDanoJefe`, `multiplicadorDanioJefe`, `multiplicadorVelocidadJefe` | `:1098-1171` | Fases y puntos débiles |
| Daño registrado | `ultimoDanoRecibido`, `ultimaRondaProteccion`, `ultimoAtacanteId`, `registrarDanoRecibido`, `danoRecibidoEstaRonda`, `golpesRecibidos` | `:1173-1221` | Memoria de daño |
| Tipos | `tiposDefensivos`, `efectividadContra`, `aplicarTipoOverride`, `inmunidadTipo`, `esInmuneAFlag`, `esInmuneACritico`, `esInmuneARecoil`, `bloqueaDanioIndirecto`, `esInmuneAClima` | `:1015-1042`, `:2001-2048` | Tipo e inmunidades |
| Sustituto | `aplicarSustituto`, `absorberConSustituto` | `:1047-1092` | Sustituto |
| Vista | `aArrayVista`, `toView` | `:1488-1501` | Delegación a `CombatienteVista` |
| Capacidades | `ajustarGolpes`, `multiplicadorDanioInfligido`, `multiplicadorDanioEscudo`, `multiplicadorEfectoEstado`, `multiplicadorDanioRecibido`, `multiplicadorPrecisionAtaque`, `multiplicadorPrecisionEstado`, `multiplicadorProbabilidadCritico`, `multiplicadorStab`, `tipoEfectivoMovimiento`, `probabilidadEsquiva` | `:1966-2048` | Consulta a `ColeccionEfectos` |

**Métodos candidatos a extraer de `Combatiente`:**

| Candidato | Evidencia | Motivo |
|---|---|---|
| `RegistroDanio` (VO) | `:1173-1221` | 6 campos y 3 métodos cohesivos; hoy mezclados con HP |
| `GestorCondiciones` | `:709-790` | Aplicar/quitar/refrescar silencio, interrupción, aturdimiento, tipo alterado y sustituto |
| `GestorObjetoCombate` | `:827-890`, `:1423-1426` | Delega casi todo en `EstadoObjeto`, pero añade reglas de elección/embargo |
| `GestorHabilidadCombate` | `:892-1013` | Anulación, copia, intercambio y filtrado de efectos de objeto |
| `GestorMovimientosCombate` | `:614-700` | Cargas, usos, recarga y bloqueo por objeto |
| `GestorInvocacion` | `:510-579` | Preparar copia y avanzar duración |
| `GestorJefe` | `:1098-1171` | Fases, puntos débiles y multiplicadores |
| `ResolutorVitalidad` | `:1686-1887` | Daño, curación y barreras |
| `SerializadorCombatiente` | `:174-349` | Mapeo de estado a/desde array de sesión |
| `AptitudMovimiento` | `:636-665` | Función pura: cargas + usos + silencio + objeto de elección |

**Métodos que hacen demasiadas cosas:**

- `puedeActuar()` `:1563-1641`: comprueba vida, 5 condiciones, confusión, estado mayor, muta HP/condiciones/combo y traduce el veredicto.
- `__unserialize()` `:234-349`: 50+ claves, compatibilidad v9-v21, reconstruye 8 VOs.
- `prepararComoInvocacion()` `:545-579`: reinicia 17 campos.
- `revivir()` `:584-606`: reinicia 13 campos.
- `recibirDaño()` `:1686-1720`: ramifica Divide Dolor, calcula barreras y muta 3 recursos.
- `motivoMovimientoNoDisponible()` `:641-665`: condiciones + cargas + usos + objeto.
- `notificarDebilitamiento()` `:1937-1949`: dominio + observer + sincronización de gas.
- `procesarDanoJefe()` `:1137-1161`: jefe + condiciones + log.

### 1.2 `AgregadoBatalla.php` — 890 líneas

- **Propiedades:** 19 (17 privadas + 2 promovidas `team1`, `team2`) (`:22-72`).
- **Métodos:** 39 públicos, 7 privados.

| Bloque | Métodos | Líneas |
|---|---|---|
| Construcción/clon | `__construct`, `__clone` | `:70-87` |
| Getters de estado | `turnManager`, `damageChain`, `subject`, `log`, `pendingAction`, `actorActualId`, `weather`, `terreno`, `turnosTerreno` | `:91-159` |
| Daño diferido | `agregarDanioDiferido`, `procesarDanioDiferido` | `:164-217` |
| Rehidratación | `__wakeup` | `:223-228` |
| Gas reactivo | `activarGasReactivo`, `sincronizarGasReactivo` | `:230-273` |
| Clima/IA perezosos | `getCalculadorClima`, `getSelectorAccion` | `:275-283` |
| Dificultad/Trick Room | `setDificultad`, `rondaPreparada`, `setRondaPreparada`, `setTrickRoom`, `trickRoomActivo`, `trickRoomTurnos` | `:285-314` |
| Composición | `incorporarCombatiente`, `invocarCombatiente`, `equipoDe`, `equipoRivalDe` | `:324-404` |
| Log | `agregarLog`, `limpiarLog` | `:408-416` |
| Efectos de ronda | `triggerBattleStartEffects`, `triggerRoundStartEffects`, `triggerRoundEndEffects` | `:421-512` |
| Muertes | `procesarDebilitamientos` | `:519-534` |
| Invocaciones | `retirarInvocacionesExpiradas` | `:542-563` |
| Condiciones | `procesarCondicionesVolatiles` | `:565-709` |
| Búsqueda interna | `buscarCombatientePorId`, `equipoProtegido` | `:711-737` |
| Motor de batalla | `ejecutarBatalla`, `ejecutarAccion` | `:751-876` |
| IA pública | `elegirObjetivoPara`, `elegirMejorMovimiento` | `:881-889` |

**Métodos candidatos a extraer:**

| Candidato | Evidencia | Motivo |
|---|---|---|
| `GestorClimaTerreno` | `:131-159`, `:502-511` | Clima, terreno, Trick Room y duración |
| `GestorGasReactivo` | `:230-273` | Estado global de habilidad anulada |
| `GestorInvocaciones` | `:336-382`, `:542-563` | Crear, limitar y retirar invocaciones |
| `ProcesadorDanioDiferido` | `:164-217` | Recalcula fórmula de daño dentro del agregado |
| `ProcesadorCondicionesRonda` | `:565-709` | Switch de 15+ condiciones con daño/curación/muerte |
| `MotorRondas` | `:421-512`, `:751-807` | Bucles de ronda, efectos y observadores |
| `EjecutorAccion` | `:809-876` | Selección IA + servicio + log + observers + acciones extra |

**Métodos que hacen demasiadas cosas:**

- `triggerRoundEndEffects()` `:452-512`: efectos, condiciones, daño de estado, daño de clima, multiplicadores, hooks de equipo, daño diferido, muertes, efectos finales, invocaciones, Trick Room y terreno.
- `procesarCondicionesVolatiles()` `:565-709`: 15+ ramas con daño, curación, muerte y log.
- `procesarDanioDiferido()` `:169-217`: reconstruye fórmula base, efectividad, STAB, daño y muerte.
- `ejecutarAccion()` `:809-876`: IA, construcción de acción, servicio, estado, stats, consumo, memoria IA, log, observers, efectos y acción extra.

### 1.3 `ServicioEjecucionBatalla.php` — 617 líneas

- **Propiedades:** 4 (`:25-34`).
- **Métodos:** 6 públicos, 8 privados.

| Método | Líneas | Responsabilidad |
|---|---:|---|
| `__construct` | `:25-34` | Inyección de `CadenaDanio`, ejecutores, aplicador de postura y gestor de efectos |
| `calcularYAplicarDano` | `:45-127` | Resolver objetivos, iterar defensores, acumular daño y aplicar efectos globales |
| `calcularYAplicarDanoContra` | `:129-368` | Núcleo: perfil, combo, humedad, terreno, sueño, flags, telepatía, daño diferido, efecto, esquiva, inmunidad, multi-golpe, cadena, postura, reacciones, drenaje, recoil, coste, efectos, control, contraataque |
| `aplicarEstado` | `:377-405` | Cuerpo áureo, inmunidad de flags, Espejo Mágico, estado |
| `aplicarStatChanges` | `:412-415` | Delegación |
| `aplicarEstadoYStatChanges` | `:426-454` | Resolver objetivos, filtrar, aplicar estado y stats |
| `generarLogMovimiento` | `:459-481` | Texto de log |
| `esquiva` | `:489-512` | Precisión, clima, estados, esquiva |
| `dispararReacciones` | `:514-535` | Daño recibido, contacto, escudo roto, debilitar rival |
| `aplicarAutodestruccion` | `:537-545` | HP a 0 |
| `aplicarDrenaje` | `:547-565` | Drenaje o lodo líquido |
| `aplicarCuracion` | `:567-581` | Curación de movimientos |
| `aplicarCosteVelocidad` | `:583-592` | Coste de velocidad |
| `aplicarRecoil` | `:594-616` | Recoil |

**Candidatos a extraer:** `CalculadorGolpes` (`:240-252`), `AplicadorDrenaje` (`:547-565`), `AplicadorRecoil` (`:594-616`), `AplicadorCosteVelocidad` (`:583-592`), `AplicadorCuracion` (`:567-581`), `ResolutorInmunidades` (`:145-181`, `:212-238`), `GeneradorLogMovimiento` (`:459-481`), `ResolutorObjetivosMovimiento` (ya existe `ResolutorObjetivosDanio`).

### 1.4 `FinalizarExploracionHandler.php` — 890 líneas

- **Propiedades:** 8, incluyendo `?Closure $aleatorio` (`:65-83`).
- **Métodos:** 2 públicos (`__construct`, `handle`), 23 privados.

| Bloque | Métodos | Líneas |
|---|---|---|
| Entrada/idempotencia | `handle` | `:85-135` |
| Reparto | `repartirRecompensas` | `:151-250` |
| Eventos | `idsDerrotados`, `idsAvistadosDeEventos`, `hallazgosEmboscadasEvitadasDe`, `hallazgoDeEmboscadaEvitada` | `:260-296`, `:370-398` |
| Carga Eloquent | `cargarPokemonsDerrotados`, `expandirDerrotados`, `chainPorPokemonDeEventos` | `:302-361` |
| Multiplicadores | `multiplicadorCaramelosEquipo`, `bonusCaramelosRecoleccion` | `:407-439` |
| Tiempo | `duracionReal` | `:445-457` |
| Incidentes | `incidentes` | `:462-495` |
| Pokédex | `despacharAvistados` | `:500-511` |
| Captura | `rollAleatorio`, `tiposPokemon` | `:526-583` |
| Resultado | `registrarResultado` | `:594-620` |
| Derrota | `terminoPorDerrota` | `:629-642` |
| Botín | `botin`, `delHabitat`, `factorTiempo` | `:658-731` |
| Pérdidas | `objetosPerdidos`, `tipoDeItem`, `restarPerdidas` | `:742-889` |

**Candidatos:** `CalculadoraDuracionExploracion`, `CalculadoraBotin` (ya existe `CalculadorBotinExploracion`), `CalculadoraPerdidas`, `ContadorIncidentes`, `MapeadorEventosRecompensa`, `MapeadorPokemonsDerrotados`, `CalculadoraCaptura`, `RepartidorRecompensas` (orquestación sin persistencia).

**Método que hace demasiadas cosas:** `repartirRecompensas()` `:151-250`: resuelve usuario, bonos, refugios, economía, senda, cálculo base, hallazgos, botín, pérdidas, persistencia, Pokédex, resultado, duración e incidentes, y actualiza el modelo.

### 1.5 `ProcesarExploracionHandler.php` — 802 líneas

- **Propiedades:** 6 (`:57-66`).
- **Métodos:** 2 públicos, 20 privados.

| Bloque | Métodos | Líneas |
|---|---|---|
| Entrada | `handle` | `:68-125` |
| Tick | `procesarTick` | `:134-271` |
| Backfill | `eventosGeneradosConBackfill` | `:283-312` |
| Resolución | `resolverEvento`, `resolverEncuentro`, `resolverEmboscada`, `emboscadaEvitada`, `eventoExtra` | `:323-518` |
| Azar | `tirarAleatorio` | `:523-530` |
| Combate | `combatirEvento`, `resolucionCombate`, `nivelRival`, `estadoInicialCombate` | `:537-640` |
| Estado | `estadoExplorador`, `aplicarDescansoSiNecesario` | `:648-690` |
| Tiempo | `inicioExploracion`, `limiteTick`, `ultimoProcesado` | `:692-731` |
| Pool | `poolHabitat`, `poolDeHabitat`, `tiposDe` | `:746-801` |

**Candidatos:** `GeneradorEventosTick`, `ResolutorEventoExploracion` (encuentro/emboscada/contratiempo), `GestorDescansoExplorador`, `CalculadoraVentanaTick`, `LectorEstadoExplorador`, `CargadorPoolHabitat`.

**Métodos que hacen demasiadas cosas:** `procesarTick()` `:134-271` (ventana, generación, pool, perfil, estado, descanso, bucle, resolución, bitácora, persistencia) y `resolverEmboscada()` `:408-476` (evasión, descanso, combate secuencial, derrota parcial, contrato de sub-combates).

---

## 2. Grafo de dependencias

### 2.1 Referencias cuantificadas

| Clase | Ocurrencias del token | Archivos | `new` en producción | Consumidores externos |
|---|---:|---:|---|---|
| `Combatiente` | 590 (459 `src`, 45 `app`, 86 `tests`) | >150 | `EquipoBatalla.php:189` | `Livewire/Combate`, `BattleSessionService`, `CombatienteVista`, ~120 efectos, IA, cadenas de daño, tests |
| `AgregadoBatalla` | 615 | >170 | `CreadorBatallaSesion.php:44`, `CombateExploracion.php:120`, `FabricaBatallaMock` | `Livewire/Combate`, `BattleSessionService`, `ServicioEjecucionBatalla`, `EquipoBatalla`, ~120 efectos, `OtorgarRecompensasMazmorra`, tests |
| `ServicioEjecucionBatalla` | 27 | 9 | `AgregadoBatalla.php:837`, `SimuladorAccionIA`, `Livewire/Combate` (4) | `Livewire/Combate`, `AgregadoBatalla`, IA, tests |
| `FinalizarExploracionHandler` | 23 | 11 | Vía `LaravelCommandBus` | `ProcesarExploracionHandler:121`, `ProcesarExploracionesPendientes`, tests |
| `ProcesarExploracionHandler` | 23 | 10 | Vía `LaravelCommandBus` | `ProcesarExploracionesPendientes:162`, `ExploracionActivaController`, tests |

### 2.2 Quién instancia/llama

- **`Combatiente`:** solo `EquipoBatalla::fromData()` lo construye en producción (`:189`); el resto son type-hints y llamadas. Esto es una buena noticia: el constructor tiene un único punto de creación en producción.
- **`AgregadoBatalla`:** tres puntos de producción:
  - `app/Support/CreadorBatallaSesion.php:44` — batallas Livewire de ruta/entrenador/gimnasio/mazmorra.
  - `src/Exploraciones/App/CombateExploracion.php:120` — combate 1v1 de exploración.
  - `src/Battle/Infrastructure/FabricaBatallaMock.php` — mock.
- **`ServicioEjecucionBatalla`:** `AgregadoBatalla::ejecutarAccion()` (`:837`) y `app/Livewire/Combate.php` (4 instancias); `SimuladorAccionIA` también lo crea.
- **Handlers:** el `LaravelCommandBus` resuelve por convención `FooCommand → FooHandler` y `app->make()` (`app/Bus/LaravelCommandBus.php:36-42`). No hay binding explícito; renombrar o mover handlers rompe la convención.

### 2.3 Dependencias salientes relevantes

| Clase | Depende de |
|---|---|
| `Combatiente` | `PokemonEntity`, `Posicion`, `EstadoManager`, `BarrerasVidaManager`, `ColeccionEfectos`, `CondicionesVolatilesCollection`, 8 VOs, `MovimientoBatalla`, `AccionBatalla`, `AgregadoBatalla` (en triggers), `MapaSendaJugador` |
| `AgregadoBatalla` | `EquipoBatalla`, `GestorTurnos`, `CadenaDanio`, `SujetoBatalla`, `BattleLog`, `SelectorAccionIA`, `CalculadorDañoClima`, `ServicioEjecucionBatalla`, `DTOAccionBatalla` (Presentation), `ObservadorEjecucionBatalla` |
| `ServicioEjecucionBatalla` | `CadenaDanio`, `ResolutorObjetivosDanio`, `AplicadorPosturaDanio`, `GestorEfectosMovimiento`, `ColeccionEjecutoresMovimiento`, `DTOResultadoDanio` (Presentation) |
| `FinalizarExploracionHandler` | Eloquent (`ExploracionActiva`, `Pokemon`), jobs, `CadenasEvolutivas`, `CargarMapaSendaJugador`, `CalculadorRecompensas`, `PersistirRecompensas`, `TransformadorResultadoExploracion`, `ResolverBonosFamilia`, `ConsultorPlantaciones`, `EsDelHabitat`, DTOs de Presentation |
| `ProcesarExploracionHandler` | Eloquent (`ExploracionActiva`, `Pokemon`, `PokemonStat`, `PokemonType`), `CombateExploracion`, `EscaladorNivelRival`, `ResolverBonosFamilia`, `EsDelHabitat`, `SimuladorEncuentros`, `CadenciaEventos`, `CommandBus` |

### 2.4 Consumidores externos que condicionan el refactor

- **Livewire `app/Livewire/Combate.php`** (1.919 líneas, 40 propiedades públicas): usa directamente `AgregadoBatalla`, `Combatiente`, `ServicioEjecucionBatalla`, `EquipoBatalla`, `BattleSessionService`. Cualquier cambio de firma pública impacta aquí.
- **`BattleSessionService`:** serializa el agregado completo (`:73`) y lo deserializa (`:108`).
- **`CreadorBatallaSesion`:** construcción canónica 3v3 (`:41-50`).
- **`CombateExploracion::combatirDatos()`:** construcción 1v1 sin Eloquent (`:93-145`), ejemplo de seam ya existente.
- **Controladores/comandos:** `ExploracionActivaController` → `ProcesarExploracionesPendientes` → `CommandBus` → handlers.
- **Tests existentes (solo como documentación):** `tests/Unit/Battle/CombatienteAvanzadoTest.php`, `CombatienteRecibirDanoTest.php`, `CombatienteSerializacionSendaTest.php`, `AgregadoBatallaTest.php`, `ServicioEjecucionBatallaTest.php`, `GestorTurnosTest.php`, `SelectorAccionIATest.php`, `tests/Feature/ExploracionesTest.php`, `ProcesarExploracionPoolTest.php`, `FinalizarExploracionPerdidasTest.php`, `FinalizarExploracionSendaTest.php`. Los tests construyen `new Combatiente` y `new AgregadoBatalla` directamente.

---

## 3. Puntos de serialización y estado

### 3.1 Qué se serializa

| Nivel | Mecanismo | Evidencia |
|---|---|---|
| Sesión | `SESSION_VERSION . '|' . serialize($battle)` | `app/Support/BattleSessionService.php:73` |
| Carga | `unserialize($payload)` + gate de versión mínima 9 | `app/Support/BattleSessionService.php:100-108` |
| `AgregadoBatalla` | Serialización nativa de todas sus propiedades privadas; sin `__serialize` explícito | `src/Battle/Domain/AgregadoBatalla.php:22-68` |
| `AgregadoBatalla` | `__wakeup` rehidrata clima, Trick Room y gas reactivo | `src/Battle/Domain/AgregadoBatalla.php:223-228` |
| `Combatiente` | `__serialize` explícito con ~50 claves planas | `src/Battle/Domain/Combatiente.php:174-231` |
| `Combatiente` | Serialización anidada de `habilidad->guardados`, `jefe`, `condiciones`, `effects`, `pokemon` | `src/Battle/Domain/Combatiente.php:202-203`, `:226-228` |
| `Combatiente` | `__unserialize` reconstruye VOs y aplica compatibilidad v9-v21 | `src/Battle/Domain/Combatiente.php:234-349` |

### 3.2 Estado que se serializa y estado que se reconstruye

**Se serializa explícitamente en `Combatiente`:**
- HP, defensas, estado, contadores de veneno, turnos de estado.
- `multiplicadores`, `resistencias`.
- Identidad (`id`, `nombre`, `iconName`, `speciesId`, `formSuffix`, `shiny`).
- Objeto (`item`, cargas, consumido, bloqueo, movimiento bloqueado).
- Habilidad (`habilidadAnulada`, `habilidadAnuladaPorGas`, efectos guardados).
- Jefe serializado.
- Daño recibido (`ultimoDanoRecibido`, `ultimoAtacanteId`, `danoRecibidoEstaRonda`, `golpesRecibidos`, `ultimaRondaProteccion`).
- Turno (`vecesActuado`, `accionesExtra`, `extraOtorgada`, `costeVelocidad`, `rondaActual`, `postura`, `objetivoCubiertoId`, `prioridadDeclarada`, retraso).
- Movimientos (cargas y usos como `MapaConteo`).
- Recursos (combo, comboMovimiento).
- Invocación (`turnosInvocacion`, `invocadoPorId`).
- `debilidadNotificada`.
- Condiciones, efectos, Pokémon, posición, senda.

**No se serializa y se reconstruye:**
- `estadoManager` y `barrerasVidaManager` (`:347-348`); son stateless, correcto.
- En `AgregadoBatalla`: `calculadorClima` y `selectorAccion` son perezosos (`:275-283`); si se inicializan, la serialización nativa los incluye. `selectorAccion` contiene memoria de IA (`MemoriaCombateIA`), que sí es estado relevante.

**Invariantes que se pierden o dependen de reconstrucción:**

| Invariante | Riesgo |
|---|---|
| `estadoManager`/`barrerasVidaManager` sin estado | Bajo si siguen siendo stateless |
| `weather` duplicado en agregado y en cada `Combatiente` | `__wakeup` lo re-sincroniza (`:225`); si se mueve a un componente, hay que replicar |
| `trickRoomTurnos` y `turnManager->invertirVelocidad` | `setTrickRoom` re-sincroniza (`:300-304`); mover el estado exige rehidratar |
| `gasReactivoId` y habilidades anuladas por gas | `__wakeup` llama `sincronizarGasReactivo()` (`:227`); si el gestor cambia, puede dejar habilidades anuladas |
| `actorActualId` | Se serializa; es autorización de turno en Livewire (`:51-56`) |
| `pendingAction` | Es un DTO de `Presentation` (`:18`, `:30`); moverlo o cambiar su namespace rompe payloads |
| `prioridadDeclarada` | Se serializa como objeto `MovimientoBatalla` dentro de `EstadoTurno` (`:314`); no se reconstruye si cambia la clase |
| `debilidadNotificada` | Evita doble notificación (`:107-112`); si se mueve a un componente, debe seguir serializándose |

### 3.3 Dónde se reconstruye el agregado

1. `BattleSessionService::cargar()` (`:80-120`): `unserialize` nativo del payload completo.
2. `AgregadoBatalla::__wakeup()` (`:223-228`): rehidrata clima, Trick Room y gas.
3. `Combatiente::__unserialize()` (`:234-349`): reconstruye VOs y aplica migraciones.
4. `Combatiente::__clone()` (`:154-170`): clonado profundo para simulación IA; reconstruye managers y clona VOs.
5. `EquipoBatalla::__clone()` (`:33-41`): clona combatientes y efectos de equipo.
6. `AgregadoBatalla::__clone()` (`:80-87`): clona equipos, reconstruye `GestorTurnos`, `SujetoBatalla` y anula `selectorAccion`.

### 3.4 Riesgo de mover estado a nuevos componentes

| Riesgo | Detalle | Evidencia |
|---|---|---|
| Payload roto | `Combatiente::__serialize` es explícito; si el estado se mueve a un componente y no se añade al array, se pierde silenciosamente al cargar | `:174-231` |
| Objeto no serializable | Inyectar un reloj/RNG/servicio con `Closure`, PDO o contenedor en `Combatiente`/`AgregadoBatalla` rompe `serialize()` en `guardar()` | `BattleSessionService.php:73` |
| `__clone` incompleto | Un componente nuevo compartido entre original y clon rompe las simulaciones IA | `Combatiente.php:154-170` |
| Rehidratación incompleta | `__wakeup` solo rehidrata clima/Trick Room/gas; un `GestorClima` nuevo debe añadirse | `AgregadoBatalla.php:223-228` |
| DTO de Presentation | `pendingAction` es `DTOAccionBatalla` de `Src\Battle\Presentation`; moverlo cambia el payload | `AgregadoBatalla.php:18`, `:30` |
| Tamaño de sesión | Ya se serializa `PokemonEntity` + efectos + condiciones; dividir en más objetos aumenta el payload | `Combatiente.php:226-228` |
| Compatibilidad v9-v21 | El gate solo comprueba versión; no hay migrador por clave | `BattleSessionService.php:100-108` |

**Conclusión:** `SESSION_VERSION` puede desaparecer si se sustituye por un formato autodescriptivo y se decide invalidar sesiones, pero mientras existan batallas en curso, la única forma segura es mantener las claves actuales o introducir un `SerializadorSesionBatalla` con lectura dual antes de mover estado.

---

## 4. Inventario de métodos por responsabilidad

### 4.1 Battle

| Responsabilidad | Dónde vive hoy | Duplicaciones / exceso |
|---|---|---|
| Daño | `Combatiente::recibirDaño` (`:1686`), `ServicioEjecucionBatalla::calcularYAplicarDano*` (`:45-368`), `CadenaDanio`, `AplicadorPosturaDanio`, `AgregadoBatalla::procesarDanioDiferido` (`:169-217`) | La fórmula base se reconstruye en `AgregadoBatalla` (`:195-203`); `Servicio` aplica daño y reacciones; `Livewire` tiene su propio flujo |
| Estados | `Combatiente::puedeActuar` (`:1563`), `EstadoManager` (`:28-216`), `AgregadoBatalla::procesarCondicionesVolatiles` (`:565`) | `EstadoManager` es puro, pero `Combatiente` mezcla decisión y mutación; el tick de condiciones está en el agregado |
| Barreras | `Combatiente::recibirDaño`, `curarHp`, `curarConExcedente`, `daniarBarreras`, `curarBarreras`; `BarrerasVidaManager` | `Combatiente` muta 3 recursos en varios métodos; `BarrerasVidaManager` ya existe pero no absorbe toda la política |
| Objetos | `Combatiente:827-890`; `EstadoObjeto`; `ServicioUsoObjetos`; `CatalogoObjetos` | `itemBloqueado`/`itemActivo` consultan ronda + embargo fuera del VO |
| Habilidades | `Combatiente:892-1013`; `ColeccionEfectos`; ~120 efectos | `anularHabilidad` mezcla filtrado de efectos, VO y condición |
| Movimientos | `Combatiente:614-700`; `EstadoMovimientos`; `PerfilMovimiento` | `motivoMovimientoNoDisponible` mezcla 4 reglas |
| Turnos | `GestorTurnos`; `Combatiente` (estado de turno); `AgregadoBatalla::ejecutarBatalla` | `Combatiente` expone 15 métodos de turno que podrían ser un VO |
| IA | `SelectorAccionIA`, `SimuladorAccionIA`, `EvaluadorAccionIAImpl`, `MemoriaCombateIA`; `AgregadoBatalla::elegir*` | El agregado crea el selector perezosamente (`:280-283`) y lo usa en `ejecutarAccion` |
| Efectos | `ColeccionEfectos`, efectos, `Combatiente::disparar*` | 12 métodos de trigger en `Combatiente` que solo delegan |
| Eventos/log | `BattleLog`, `SujetoBatalla`, `AgregadoBatalla::agregarLog` | `Servicio` genera log (`:459`), `Agregado` genera log inline (`:847-851`), `Livewire` también |
| Persistencia de batalla | `BattleSessionService` | Correctamente aislada; único punto de sesión |
| Serialización | `Combatiente::__serialize/__unserialize`; `AgregadoBatalla::__wakeup` | Muy acoplada a claves internas |

### 4.2 Exploraciones

| Responsabilidad | Dónde vive hoy | Duplicaciones / exceso |
|---|---|---|
| Recompensas | `FinalizarExploracionHandler::repartirRecompensas` (`:151`), `CalculadorRecompensas` | Orquestación + cálculo + persistencia en un método |
| Botín | `FinalizarExploracionHandler::botin` (`:658`), `CalculadorBotinExploracion` | `new CalculadorBotinExploracion()` inline (`:667`) |
| Pérdidas | `objetosPerdidos` (`:742`), `restarPerdidas` (`:835`) | Lógica de dominio en handler |
| Incidentes | `incidentes` (`:462`) | Reimplementa conteo sobre arrays |
| Persistencia | `PersistirRecompensas` (`:35-237`) | Eloquent concreto, sin puerto |
| Pokédex | `despacharAvistados` (`:500`) | Job + `UnitOfWork::afterCommit` |
| Duración | `duracionReal` (`:445`), `factorTiempo` (`:716`) | Dos cálculos de tiempo distintos |
| Tick | `ProcesarExploracionHandler::procesarTick` (`:134`) | Generación + estado + combate + persistencia |
| Encuentros | `resolverEncuentro` (`:366`), `resolverEmboscada` (`:408`) | Lógica de secuencia y descanso |
| Descanso | `aplicarDescansoSiNecesario` (`:665`) | Regla + mutación + bitácora |
| Pool | `poolDeHabitat` (`:755`) | Eloquent + bonos + pesos |
| Estado explorador | `estadoExplorador` (`:648`), `EstadoExplorador` | Serialización manual a array |

---

## 5. Acoplamientos peligrosos

| Tipo | Evidencia | Impacto |
|---|---|---|
| `new` interno en entidad | `Combatiente.php:137-138`, `:168-169`, `:347-348` crean `EstadoManager` y `BarrerasVidaManager` | Impide sustituirlos por dobles; obliga a reconstruirlos en `__unserialize`/`__clone` |
| `new` interno en agregado | `AgregadoBatalla.php:74-77` crea `GestorTurnos`, `CadenaDanio`, `SujetoBatalla`; `:275-283` crea `CalculadorDañoClima` y `SelectorAccionIA` | Difícil inyectar reloj/RNG/IA; el estado del selector se serializa sin control |
| `new` en servicio | `AgregadoBatalla.php:837` crea `ServicioEjecucionBatalla($this->damageChain)` | Duplica la orquestación y evita reutilizar una instancia configurada |
| `app()`/`resolve()` | `FinalizarExploracionHandler.php:165`, `:172`, `:665`, `:705`; `ProcesarExploracionHandler.php:95`, `:762`; `CombateExploracion.php:57` | Dependencias ocultas, no testeables sin contenedor |
| Eloquent en App | `FinalizarExploracionHandler.php:8-9`, `:91`, `:249`, `:302-314`, `:352-356`; `ProcesarExploracionHandler.php:7-10`, `:375`, `:434`, `:548`, `:757-792` | Dominio/aplicación acoplados a BD; difícil test unitario |
| DTOs de Presentation en Domain | `AgregadoBatalla.php:18` (`DTOAccionBatalla`), `ServicioEjecucionBatalla.php:17` (`DTOResultadoDanio`), `FinalizarExploracionHandler.php:36-38` | Inversión de capas (deptrac: 16 Battle, 12 Exploraciones) |
| Acceso a sesión | Solo `BattleSessionService.php:73-144` | Bien aislado; no introducir sesión en dominio |
| Llamadas estáticas | `FinalizarExploracionHandler.php:106-109` (`CadenasEvolutivas`), `:116-118` (`EvaluadorExploracion`, `PerfilExploracion`), `:448` (`CalculadorFinExploracion`), `:724` (`CalculadorVueltaExploracion`); `ProcesarExploracionHandler.php:169` (`SimuladorEncuentros`), `:171` (`CadenciaEventos`), `:347` (`EvaluadorExploracion`); `Combatiente.php:989` (`CatalogoObjetos::clavesDeEfecto`) | Difícil sustituir en pruebas |
| RNG no inyectado en dominio | `ServicioEjecucionBatalla.php:242`, `:497`, `:501`, `:511`; `EstadoManager.php:128`, `:154`, `:192` | Resultados no deterministas; los seams de handlers no cubren Battle |
| Tiempo no inyectado | `FinalizarExploracionHandler.php:249`, `:447`, `:718`, `:728`; `ProcesarExploracionHandler.php:101`, `:110`, `:116`, `:260`, `:684`, `:702` | Tests dependientes del reloj; difícil simular ticks |
| Mutación in-place | `Combatiente.php:1056`, `:1080`, `:1607-1608`, `:1711-1717`, `:1748-1750`, `:1796-1817`; `AgregadoBatalla.php:590`, `:602`, `:624-627`, `:646-648`; handlers `$eventos->put` y `$exploracion->eventos =` | Efectos secundarios ocultos; difícil razonar sobre invariantes |
| Dependencia circular | `Combatiente` → `AgregadoBatalla` (triggers) → `EquipoBatalla` → `Combatiente` | Ya existe; extraer componentes puede agravarla si no se invierten dependencias |
| Serialización nativa | `AgregadoBatalla` sin `__serialize`; `Combatiente` con claves planas | Cualquier propiedad nueva entra o sale del payload sin contrato explícito |

---

## 6. Oportunidades de testabilidad

| Oportunidad | Evidencia | Comentario |
|---|---|---|
| `EstadoManager` ya es puro | `EstadoManager.php:11-17` | Solo falta inyectar RNG; hoy usa `mt_rand` (`:128`, `:154`, `:192`) |
| `GestorTurnos` determinista | `GestorTurnos.php:81-129` | Depende solo de combatientes; buen candidato a tests puros |
| `CombatienteVista` puro | `CombatienteVista.php:31` | Mapper estático; podría ser VO/DTO sin tocar dominio |
| `ServicioEjecucionBatalla` con ejecutores inyectables | `ServicioEjecucionBatalla.php:27` | Ya acepta `?ColeccionEjecutoresMovimiento`; extender el patrón a RNG y reloj |
| `CombateExploracion::combatirDatos` sin Eloquent | `CombateExploracion.php:93-145` | Ejemplo de seam; replicar en handlers |
| `BattleSessionService` aislado | `BattleSessionService.php:73-144` | Convertirlo en puerto `AlmacenBatalla` permitiría dobles |
| `PersistirRecompensas` aislado | `PersistirRecompensas.php:35-50` | Ya es un colaborador; falta interfaz |
| Seam de azar en handlers | `FinalizarExploracionHandler.php:77`; `ProcesarExploracionHandler.php:61` | Existe, pero el fallback `mt_rand` sigue dentro |
| `CalculadorFinExploracion`/`CalculadorVueltaExploracion` | `FinalizarExploracionHandler.php:448`, `:724` | Ya extraídos; usar en todos los caminos |
| `EstadoExplorador` VO | `ProcesarExploracionHandler.php:648-654` | Ya encapsula HP/barreras; extraer persistencia |
| `ResultadoRecompensas`/colecciones tipadas | `PersistirRecompensas.php:35-49` | Buen contrato para separar cálculo/persistencia |
| `DatosPokemonBatalla` + `EquipoBatalla::fromData` | `EquipoBatalla.php:157-236` | Construcción testeable sin Eloquent |
| `CombateExploracion::combatirDatos` | `:93` | Permite tests de batalla sin BD |

**Seams que no requieren crear tests ahora:**
- Puerto `GeneradorAleatorio` (`int(int,int)`, `float()`).
- Puerto `Reloj` (`ahora(): CarbonInterface`).
- Puerto `AlmacenBatalla` para sustituir `BattleSessionService`.
- Puerto `RepositorioExploracion` para sustituir Eloquent en handlers.
- Puerto `PersistenciaRecompensas` para sustituir `PersistirRecompensas`.
- `CalculadoraGolpes`, `AplicadorDrenaje`, `AplicadorRecoil`, `AplicadorCosteVelocidad` como funciones puras.

---

## 7. Riesgos de refactor

| Riesgo | Nivel | Impacto | Mitigación |
|---|---|---|---|
| Romper serialización de sesión | **Alto** | Batallas en curso pierden estado o no cargan | No cambiar claves de `Combatiente::__serialize`; introducir mapper con lectura dual; subir versión solo con migrador |
| Cambiar constructores usados por tests | **Alto** | ~20 archivos de test construyen `new Combatiente`/`new AgregadoBatalla` | Mantener constructores y añadir factories/overloads; no tocar en fase 1 |
| Cambiar API pública de `Combatiente` | **Alto** | ~120 efectos y IA dependen de 189 métodos | Fachada con delegación; extraer implementación detrás, no mover firmas |
| Cambiar orden de daño/RNG | **Alto** | Regresión funcional difícil de detectar | Extraer sin alterar secuencia de llamadas; congelar comportamiento con los tests existentes como oráculo (sin ejecutarlos) |
| Mover estado a componentes no serializables | **Alto** | `serialize($battle)` falla en `guardar()` | Todo componente persistido debe ser serializable o reconstruible en `__wakeup` |
| `__clone` incompleto | **Medio** | Simulaciones IA comparten estado mutable | Actualizar `__clone` en cada extracción; test de identidad de objetos |
| Duplicar lógica en Livewire | **Alto** | Dos motores de ejecución divergen | Unificar `ServicioEjecucionBatalla` antes de tocar `Combatiente` |
| `poolHabitat` usado por Reflection | **Medio** | Eliminarlo rompe `ProcesarExploracionPoolTest` | Mantener wrapper hasta que el test se retire (fuera de alcance) |
| `PersistirRecompensas` sustituido por mock parcial | **Medio** | Tests existentes lo mockean | No declararlo `final`; conservar firma `persistir()` |
| Idempotencia de finalización | **Alto** | Doble reparto de recompensas (B-04) | No mover `refresh()`/`regreso` sin lock; el refactor no debe relajar la transacción |
| Forma del JSON `eventos` | **Alto** | Vistas y tests leen `resolucion`, `duration_loss`, `hp_final`, `barrera_*`, `explorador` | Extraer cálculo, no cambiar el shape persistido |
| deptrac | **Medio** | Nuevas clases pueden aumentar violaciones | Los nuevos componentes de dominio no deben importar `Presentation`; mover DTOs o mapear en App |
| Tamaño de sesión | **Medio** | Más objetos serializados | Medir payload antes/después; evitar duplicar `PokemonEntity` |
| `SESSION_VERSION` eliminada | **Medio** | Sesiones antiguas no cargan | Decidir política: invalidar o migrar; no ambas a medias |

**Puntos que no deben tocarse en la primera fase:**
- `BattleSessionService` (`SESSION_VERSION`, gate, `serialize`/`unserialize`).
- `Combatiente::__serialize`, `__unserialize`, `__clone`.
- Constructores públicos de `Combatiente`, `AgregadoBatalla`, `EquipoBatalla`, `ServicioEjecucionBatalla`.
- `AgregadoBatalla::ejecutarBatalla` y `CombateExploracion::combatirDatos`.
- `poolHabitat` y el shape de `eventos`.
- `PersistirRecompensas::persistir`.
- Secuencia exacta de `mt_rand` en `ServicioEjecucionBatalla` y `EstadoManager`.

---

## 8. Orden de extracción propuesto (bajo riesgo → alto impacto)

| Paso | Extracción | Depende de | Riesgo | Impacto | Evidencia |
|---:|---|---|---|---|---|
| 0 | Puertos `Reloj` y `GeneradorAleatorio` con implementaciones por defecto; inyectar en `EstadoManager` y `ServicioEjecucionBatalla` sin cambiar firmas públicas | — | Bajo | Alto (testabilidad) | `EstadoManager.php:128-192`; `ServicioEjecucionBatalla.php:242-511` |
| 1 | `CalculadorGolpes`, `AplicadorDrenaje`, `AplicadorRecoil`, `AplicadorCosteVelocidad`, `AplicadorCuracion` extraídos de `Servicio` | 0 | Bajo | Medio | `ServicioEjecucionBatalla.php:240-252`, `:547-616` |
| 2 | `AptitudMovimiento` (función pura) desde `Combatiente::movimientoDisponible/motivoMovimientoNoDisponible` | — | Bajo | Medio | `Combatiente.php:636-665` |
| 3 | `RegistroDanio` VO desde los 6 campos/métodos de daño recibido | — | Bajo | Medio | `Combatiente.php:1173-1221` |
| 4 | `GestorCondiciones` (aplicar/quitar/refrescar) con delegación desde `Combatiente` | 2, 3 | Medio | Alto | `Combatiente.php:709-790` |
| 5 | `ProcesadorCondicionesRonda` desde el switch de `AgregadoBatalla` | 4 | Medio | Alto | `AgregadoBatalla.php:565-709` |
| 6 | `GestorObjetoCombate` y `GestorHabilidadCombate` con delegación | 3 | Medio | Medio | `Combatiente.php:827-1013` |
| 7 | `GestorInvocaciones` desde `Combatiente` + `AgregadoBatalla` | 3 | Medio | Medio | `Combatiente.php:510-579`; `AgregadoBatalla.php:336-382`, `:542-563` |
| 8 | `GestorClimaTerreno` y `GestorGasReactivo` desde `AgregadoBatalla` | 7 | Medio | Alto | `AgregadoBatalla.php:131-159`, `:230-273`, `:502-511` |
| 9 | `ProcesadorDanioDiferido` desde `AgregadoBatalla`; reutilizar `CadenaDanio`/`ManejadorDanioBase` en vez de fórmula inline | 1 | Medio | Alto | `AgregadoBatalla.php:164-217` |
| 10 | `MotorRondas`/`EjecutorAccion` desde `AgregadoBatalla`, manteniendo fachada pública | 5, 8, 9 | Alto | Muy alto | `AgregadoBatalla.php:421-512`, `:751-876` |
| 11 | `ServicioEjecucionBatalla` → `ResolutorImpacto` + `AplicadorEfectosMovimiento` + `GeneradorLogMovimiento`; unificar Livewire y agregado | 1, 10 | Alto | Muy alto | `ServicioEjecucionBatalla.php:45-368`; `Livewire/Combate.php` |
| 12 | `SerializadorCombatiente` y `SerializadorSesionBatalla` con lectura dual; después mover estado interno | 3-11 | Alto | Muy alto | `Combatiente.php:174-349`; `BattleSessionService.php:73-118` |
| 13 | División final de `Combatiente` en componentes (`Vitalidad`, `Estados`, `Movimientos`, `Objeto`, `Habilidad`, `Jefe`, `Invocacion`) con fachada de 189 métodos | 12 | Muy alto | Muy alto | `Combatiente.php` completo |
| 14 | Exploraciones: `CalculadoraDuracion`, `CalculadoraBotin`, `CalculadoraPerdidas`, `ContadorIncidentes`, `MapeadorEventos` desde `Finalizar` | — (paralelo a Battle) | Medio | Alto | `FinalizarExploracionHandler.php:445-889` |
| 15 | Exploraciones: `GeneradorEventosTick`, `ResolutorEventoExploracion`, `GestorDescanso`, `CargadorPoolHabitat` desde `Procesar` | 14 | Medio | Alto | `ProcesarExploracionHandler.php:134-801` |
| 16 | Puertos `PersistenciaRecompensas`, `ConsultorPlantaciones`, `ResolverBonosFamilia`, `EsDelHabitat`, `CargarMapaSendaJugador`; sacar `app()`/Eloquent de handlers | 14, 15 | Alto | Muy alto | `FinalizarExploracionHandler.php:165-705`; `ProcesarExploracionHandler.php:95-762` |

**Dependencias clave:**
- Los pasos 0-3 son independientes y de bajo riesgo; pueden ejecutarse primero.
- Los pasos 4-9 preparan el terreno para el paso 10; no saltar a `MotorRondas` antes de extraer condiciones/clima/invocaciones.
- El paso 11 depende de 1 y 10; sin él, `Livewire` y `AgregadoBatalla` seguirán duplicando ejecución.
- El paso 12 es el guardián de la compatibilidad; puede hacerse antes (como seam) o después, pero no durante una extracción de estado.
- El paso 13 es el último de Battle y el de mayor riesgo.
- Los pasos 14-16 son paralelizables con Battle y no comparten estado de sesión.

---

## EVIDENCIA

- `src/Battle/Domain/Combatiente.php:38` — clase `Combatiente`.
- `src/Battle/Domain/Combatiente.php:40-124` — 38 propiedades de estado.
- `src/Battle/Domain/Combatiente.php:126-147` — constructor con `PokemonEntity` y `Posicion`; crea 8 VOs y 2 managers.
- `src/Battle/Domain/Combatiente.php:154-170` — `__clone` profundo; reconstruye `EstadoManager` y `BarrerasVidaManager`.
- `src/Battle/Domain/Combatiente.php:174-231` — `__serialize` con ~50 claves planas y 5 `serialize()` anidados.
- `src/Battle/Domain/Combatiente.php:234-349` — `__unserialize` con compatibilidad v9-v21.
- `src/Battle/Domain/Combatiente.php:344` — lanza `RuntimeException` si falta `pokemon`.
- `src/Battle/Domain/Combatiente.php:636-665` — elegibilidad de movimiento con 4 reglas.
- `src/Battle/Domain/Combatiente.php:709-790` — mutadores de condiciones.
- `src/Battle/Domain/Combatiente.php:1173-1221` — memoria de daño.
- `src/Battle/Domain/Combatiente.php:1563-1641` — `puedeActuar` con múltiples responsabilidades.
- `src/Battle/Domain/Combatiente.php:1686-1720` — `recibirDaño` y rama Divide Dolor.
- `src/Battle/Domain/Combatiente.php:1901-1962` — 9 triggers que delegan en `ColeccionEfectos`.
- `src/Battle/Domain/AgregadoBatalla.php:18` — importa `Src\Battle\Presentation\DTOAccionBatalla`.
- `src/Battle/Domain/AgregadoBatalla.php:70-78` — constructor crea `GestorTurnos`, `CadenaDanio`, `SujetoBatalla`, `BattleLog`.
- `src/Battle/Domain/AgregadoBatalla.php:164-217` — daño diferido con fórmula inline.
- `src/Battle/Domain/AgregadoBatalla.php:223-228` — `__wakeup` rehidrata clima, Trick Room y gas.
- `src/Battle/Domain/AgregadoBatalla.php:275-283` — `new CalculadorDañoClima()` y `new SelectorAccionIA()`.
- `src/Battle/Domain/AgregadoBatalla.php:452-512` — `triggerRoundEndEffects` con 8+ responsabilidades.
- `src/Battle/Domain/AgregadoBatalla.php:565-709` — switch de condiciones volátiles.
- `src/Battle/Domain/AgregadoBatalla.php:751-807` — bucle de batalla completo.
- `src/Battle/Domain/AgregadoBatalla.php:809-876` — `ejecutarAccion` con IA, servicio, log, observers y efectos.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:17` — importa `Src\Battle\Presentation\DTOResultadoDanio`.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:25-34` — constructor con dependencias opcionales.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:45-127` — resolución multiobjetivo.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:129-368` — núcleo de daño con 15+ ramas.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:242` — `mt_rand` para golpes.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:489-512` — `esquiva` con 3 `mt_rand`.
- `src/Battle/Domain/EstadoManager.php:11-17` — servicio puro sin estado.
- `src/Battle/Domain/EstadoManager.php:128`, `:154`, `:192` — RNG no inyectado.
- `src/Battle/Domain/GestorTurnos.php:81-129` — selección de actor por velocidad.
- `src/Battle/Domain/EquipoBatalla.php:157-236` — `fromData` y único `new Combatiente` de producción.
- `src/Battle/Domain/EquipoBatalla.php:102-110` — alias `combatientesCollection`/`combatants`.
- `src/Battle/Domain/CombatienteVista.php:31` — mapper estático a vista.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:63` — clase `final`.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:72-83` — constructor con 5 colaboradores y seam de azar.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:85-135` — `handle` con idempotencia, carga y reparto.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:151-250` — `repartirRecompensas` con 7+ responsabilidades.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:165`, `:172`, `:665`, `:705` — `app()` como fallback.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:249` — `now()` y `update`.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:445-457` — `duracionReal`.
- `src/Exploraciones/App/FinalizarExploracionHandler.php:716-731` — `factorTiempo` con otro cálculo de tiempo.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:57-66` — constructor con seam de azar.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:68-125` — `handle` con tick y despacho.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:134-271` — `procesarTick` con 7+ responsabilidades.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:408-476` — `resolverEmboscada` con evasión y combate secuencial.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:665-690` — descanso con mutación y bitácora.
- `src/Exploraciones/App/ProcesarExploracionHandler.php:746-749` — `poolHabitat` deprecated, usado por tests vía Reflection.
- `src/Exploraciones/App/PersistirRecompensas.php:35-50` — contrato de persistencia.
- `src/Exploraciones/App/CombateExploracion.php:93-145` — combate 1v1 sin Eloquent.
- `app/Support/BattleSessionService.php:50` — `SESSION_VERSION = 21`.
- `app/Support/BattleSessionService.php:64` — mínimo compatible 9.
- `app/Support/BattleSessionService.php:73` — `session()->put(..., serialize($battle))`.
- `app/Support/BattleSessionService.php:100-108` — gate de versión y `unserialize`.
- `app/Support/CreadorBatallaSesion.php:41-50` — construcción 3v3 y guardado.
- `app/Bus/LaravelCommandBus.php:36-42` — resolución de handler por convención.
- `app/Livewire/Combate.php:37-107` — 40 propiedades públicas.
- `app/Livewire/Combate.php:113` — `?ServicioEjecucionBatalla`.
- `app/Livewire/Combate.php:864-1027` — `resolverAccion`.

---

## DEPENDENCIAS

- **Internas de Battle:** `EquipoBatalla`, `GestorTurnos`, `CadenaDanio`, `ColeccionEfectos`, `EstadoManager`, `BarrerasVidaManager`, `SelectorAccionIA`, `CalculadorDañoClima`, `ServicioEjecucionBatalla`, VOs de `ValueObjects/`.
- **Internas de Exploraciones:** `CalculadorRecompensas`, `CalculadorBotinExploracion`, `CalculadorFinExploracion`, `CalculadorVueltaExploracion`, `EvaluadorExploracion`, `PerfilExploracion`, `EconomiaRecompensas`, `PersistirRecompensas`, `TransformadorResultadoExploracion`, `EstadoExplorador`, `PoolHabitat`, `SimuladorEncuentros`, `CadenciaEventos`.
- **Shared:** `CadenasEvolutivas`, `CargarMapaSendaJugador`, `ProbabilidadCaptura`, `MapaSendaJugador`, `PoliticaSenda`, `CatalogoItems`, `CategoriaItem`, `ClaveBonguri`, `EscaladorNivelRival`, `NivelHelper`, `TipoPokemon`.
- **Framework:** Laravel 12.67, PHP 8.4.18, Eloquent, Jobs, Session, `CommandBus`/`UnitOfWork`, Livewire 4.4.2.
- **Externas:** Carbon, PostgreSQL/SQLite según entorno.
- **Restricción de capas:** `deptrac.yaml` prohíbe Domain → Presentation; ya hay 16 violaciones en Battle y 12 en Exploraciones.

---

## DUPLICIDADES Y DEUDA TÉCNICA

1. **Serialización plana vs VOs:** `Combatiente::__serialize` (`:174-231`) duplica el estado que ya encapsulan `EstadoTurno`, `EstadoRecursos`, `EstadoMovimientos`, `EstadoObjeto`, `EstadoHabilidad`.
2. **Daño duplicado:** `ServicioEjecucionBatalla::calcularYAplicarDanoContra` (`:129-368`) vs `AgregadoBatalla::procesarDanioDiferido` (`:169-217`) vs flujo de `Livewire/Combate` (`:864-1027`).
3. **Inmunidades duplicadas:** `ServicioEjecucionBatalla.php:167-173` y `:387-391`.
4. **Búsqueda de equipo duplicada:** `AgregadoBatalla::equipoDe` (`:384-395`), `GestorTurnos::equipoDe` (`:142-153`) y `EquipoBatalla::findCombatant` (`:272-281`).
5. **Alias:** `EquipoBatalla::combatientesCollection`/`combatants` (`:102-110`); `Combatiente::aArrayVista`/`toView` (`:1488-1501`).
6. **Tiempo duplicado en Finalizar:** `duracionReal` (`:445-457`) y `factorTiempo` (`:716-731`) recalculan ventanas; `CalculadorFinExploracion` ya existe.
7. **Incidentes:** `FinalizarExploracionHandler::incidentes` (`:462-495`) reimplementa conteo que podría vivir en `EventoExploracion`.
8. **Pérdidas:** `objetosPerdidos`/`restarPerdidas` (`:742-889`) son reglas de dominio dentro de App.
9. **Código muerto:** `ProcesarExploracionHandler::poolHabitat` (`:746-749`) deprecated y usado por tests vía Reflection.
10. **Posible duplicación con helpers ya extraídos:** `cargarPokemonsDerrotados` (`:302-314`), `expandirDerrotados` (`:324-330`), `idsAvistadosDeEventos` (`:282-296`) frente a `app/Support/CargadorPokemonsDerrotados` y `AvistamientosPokedex` (no leídos completos; verificar antes de eliminar).
11. **Números mágicos:** `999` turnos de gas (`AgregadoBatalla.php:244`), `0.5` de retirada (`ProcesarExploracionHandler.php:235`), `0.125`/`0.0625` de condiciones (`AgregadoBatalla.php:571`, `:583`), `100`/`32` de veneno (`EstadoManager.php:64-65`).
12. **Mezcla App/Dominio:** handlers con Eloquent, `now()`, `app()` y DTOs de Presentation.

---

## RIESGOS

- **[alto] Serialización de sesión.** Mover estado de `Combatiente`/`AgregadoBatalla` cambia el payload nativo; las sesiones v9-v21 pueden cargar con valores por defecto o fallar. Impacto: pérdida de batallas en curso. Mitigación: no tocar claves; mapper con lectura dual; versión + migrador.
- **[alto] API pública de 189 métodos.** ~120 efectos, IA y Livewire dependen de `Combatiente`. Mover firmas rompe compilación. Mitigación: fachada con delegación, extracción interna.
- **[alto] Duplicación Livewire/Agregado.** Dos motores de ejecución pueden divergir si solo se refactoriza uno. Mitigación: unificar `ServicioEjecucionBatalla` antes de dividir `Combatiente`.
- **[alto] Idempotencia de finalización.** `refresh()` + `regreso` (`:91-96`) es la única barrera; el refactor no debe alterar la transacción ni el lock. Mitigación: conservar el orden y no mover la comprobación fuera del handler.
- **[alto] Shape de `eventos`.** Vistas y tests leen `resolucion`, `duration_loss`, `hp_final`, `barrera_*`, `explorador`. Mitigación: extraer cálculo, no contrato.
- **[medio] `__clone` incompleto.** Componentes nuevos compartidos en simulación IA. Mitigación: actualizar `__clone` en cada paso.
- **[medio] DTOs de Presentation en Domain.** Nuevos componentes pueden aumentar deptrac. Mitigación: mover DTOs a Domain o mapear en App.
- **[medio] Tests acoplados a constructores y Reflection.** `poolHabitat` y `new Combatiente`/`new AgregadoBatalla`. Mitigación: mantener compatibilidad hasta que se autorice su retirada.
- **[medio] Tamaño de sesión.** Más objetos serializados. Mitigación: medir payload; evitar duplicar `PokemonEntity`.
- **[bajo] RNG/reloj.** Extraer puertos no cambia comportamiento si los valores por defecto son idénticos. Mitigación: no alterar secuencia de llamadas.

---

## OPCIONES

1. **Refactor incremental con fachada (recomendada).** Extraer componentes internos y mantener `Combatiente`/`AgregadoBatalla` como fachadas con las mismas firmas. Bajo riesgo de compilación, alto coste de delegación temporal. Permite pasos 0-9 sin tocar Livewire ni serialización.
2. **Refactor por capas verticales.** Empezar por Exploraciones (handlers) y dejar Battle para después. Menor riesgo de sesión, pero no resuelve el punto más grande (`Combatiente`) y deja duplicación Battle/Livewire.
3. **Refactor de serialización primero.** Introducir `SerializadorSesionBatalla` con lectura dual y después mover estado libremente. Máxima seguridad de sesión, pero retrasa las mejoras de testabilidad y añade una capa antes de saber qué estado se moverá.
4. **Reescritura del motor de batalla.** Alta impacto, alto riesgo; no recomendada sin golden-master y sin tests ejecutables.

---

## SUPOSICIONES

- La rama `task-011` y el HEAD `0317179` son la base real; el working tree solo tiene `.opencode/work/` sin rastrear.
- Los tests existentes son documentación de comportamiento y no deben modificarse ni ejecutarse.
- `Livewire` se mantiene; no se propone sustituirlo.
- La sesión de batalla debe seguir funcionando tras el refactor, incluidas batallas en curso.
- `SESSION_VERSION` puede desaparecer, pero no se ha confirmado si se acepta invalidar sesiones activas.
- Los efectos (`src/Battle/Domain/Effects/*`) no se refactorizan en esta tarea; solo se tienen en cuenta como consumidores.
- Los handlers de Exploraciones pueden seguir usando Eloquent en `App/` según la convención del proyecto, pero se propone aislarlo tras puertos.
- No se ha leído completo `app/Livewire/Combate.php` ni todos los tests; las conclusiones sobre ellos se basan en firmas y grep.

---

## PREGUNTAS BLOQUEANTES

1. **¿Se exige compatibilidad con sesiones de batalla ya abiertas (v9-v21) o se acepta invalidarlas al desplegar?** Sin esta respuesta no se puede decidir el orden del paso 12.
2. **¿Se permite cambiar la API pública de `Combatiente` (189 métodos) o debe quedar intacta durante todo el refactor?** Afecta a ~120 efectos y a Livewire.
3. **¿Los tests existentes son contrato intocable?** En particular `poolHabitat` por Reflection y los constructores directos.
4. **¿`Combatiente` debe seguir siendo la entidad raíz o se acepta que sea una fachada de componentes?** Es la decisión de diseño que corresponde al arquitecto.
5. **¿Se permite tocar el shape de `eventos` de exploración o es contrato con vistas y datos persistidos?**
6. **¿`PersistirRecompensas` puede pasar a interfaz o los tests lo mockean por clase concreta?**
7. **¿El RNG debe ser reproducible por semilla o basta con inyectar un callable?**
8. **¿Se acepta mover DTOs de `Presentation` a `Domain` para resolver deptrac, o se prefiere mapear en App?**
9. **¿La prioridad es testabilidad pura o reducir tamaño de archivo?** Determina si se extraen VOs pequeños o servicios completos.
10. **¿Hay presupuesto para una fase de golden-master antes de tocar el orden de daño?**

---

## RECOMENDACIÓN

**No empezar por `Combatiente`.** El orden de menor riesgo y mayor impacto es:

1. **Pasos 0-3:** puertos de reloj/RNG, calculadores puros de `ServicioEjecucionBatalla`, `AptitudMovimiento` y `RegistroDanio`. Son cambios locales, no alteran serialización ni firmas públicas y mejoran inmediatamente la testabilidad.
2. **Pasos 4-9:** condiciones, objetos, habilidades, invocaciones, clima/gas y daño diferido, siempre con delegación desde las clases actuales. Aquí se reduce el tamaño real de `Combatiente` y `AgregadoBatalla` sin tocar el payload.
3. **Pasos 10-11:** `MotorRondas` y unificación de `ServicioEjecucionBatalla` con Livewire. Es el punto donde se elimina la duplicación más peligrosa.
4. **Paso 12:** serialización con lectura dual, antes de mover estado interno. Sin este paso, el paso 13 es inseguro.
5. **Paso 13:** división final de `Combatiente`, solo con fachada y compatibilidad de constructores.
6. **Pasos 14-16 en paralelo:** Exploraciones. Menor riesgo de sesión y alto impacto en mantenibilidad; puede ejecutarse por otro especialista mientras Battle avanza.

**Primera fase concreta sugerida:** pasos 0-3 + 14 (calculadores de Exploraciones), con revisión independiente y sin tocar `BattleSessionService`, `Combatiente::__serialize` ni el shape de `eventos`.

---

## CIERRE DEL SUBAGENTE

### Resultado

Análisis parcial consolidado con inventario, grafo de dependencias, puntos de serialización, inventario por responsabilidad, acoplamientos, oportunidades de testabilidad, riesgos, orden de extracción y preguntas abiertas. **No se modificó ningún archivo.**

### Archivos leídos

- `.opencode/work/TASK-019/analysis.md`
- `.opencode/work/TASK-019/final-report.md`
- `src/Battle/Domain/Combatiente.php` (completo)
- `src/Battle/Domain/AgregadoBatalla.php` (completo)
- `src/Battle/Domain/ServicioEjecucionBatalla.php` (completo)
- `src/Exploraciones/App/FinalizarExploracionHandler.php` (completo)
- `src/Exploraciones/App/ProcesarExploracionHandler.php` (completo)
- `src/Battle/Domain/GestorTurnos.php`
- `src/Battle/Domain/EquipoBatalla.php`
- `src/Battle/Domain/EstadoManager.php`
- `src/Battle/Domain/CombatienteVista.php` (parcial, 120 líneas)
- `src/Exploraciones/App/CombateExploracion.php`
- `src/Exploraciones/App/PersistirRecompensas.php`
- `app/Support/BattleSessionService.php`
- `app/Support/CreadorBatallaSesion.php`
- `app/Bus/LaravelCommandBus.php`
- `app/Livewire/Combate.php` (solo firmas/propiedades, no completo)
- `src/Shared/Bus/*.php`
- `src/Exploraciones/App/ProcesarExploracionesPendientes.php` (parcial)

### Decisiones

- No se propone arquitectura final; solo candidatos y límites, según el encargo.
- Se prioriza la compatibilidad de sesión y de API pública sobre la pureza del diseño.
- Se separan las extracciones de Battle y Exploraciones para permitir trabajo paralelo.
- No se proponen tests nuevos ni se ejecutan los existentes.

### Verificación ejecutada

- `git status --short`, `git branch --show-current`, `git log -1 --oneline`: rama `task-011`, HEAD `0317179`, solo `.opencode/work/` sin rastrear.
- `wc -l` de los cinco archivos objetivo y contexto.
- `grep -c` de métodos públicos/privados y propiedades.
- `grep -rl`/`grep -ro` de referencias a `Combatiente`, `AgregadoBatalla`, `ServicioEjecucionBatalla`, `FinalizarExploracionHandler`, `ProcesarExploracionHandler`.
- `grep -rn` de `new Combatiente`, `new AgregadoBatalla`, `new ServicioEjecucionBatalla`, `new EstadoManager`, `new BarrerasVidaManager`, `new GestorTurnos`.
- `grep -rn` de `__serialize`, `__unserialize`, `__wakeup`, `SESSION_VERSION`, `session()`.
- `grep -rn` de `mt_rand`, `now()`, `app()` y llamadas estáticas en los archivos objetivo.
- **No se ejecutaron tests. No se ejecutaron migraciones ni comandos destructivos. No se escribieron archivos.**

### Problemas detectados

- `rg` no está instalado en el entorno; se usó `grep` como sustituto.
- El límite de pasos se alcanzó antes de leer completo `app/Livewire/Combate.php`, todos los tests y los helpers de `app/Support` (`CadenasEvolutivas`, `CargadorPokemonsDerrotados`, `AvistamientosPokedex`), por lo que algunas duplicaciones se marcan como “posible” y requieren verificación.
- No se pudo cuantificar el uso externo método por método de los 189 métodos de `Combatiente`; se cuantificó por clase y por archivos.
- No se pudo verificar el tamaño real de los payloads de sesión ni la presencia de sesiones activas.

### Riesgos pendientes

- La compatibilidad de sesión sigue siendo la decisión bloqueante principal.
- La extracción de `Combatiente` sin fachada rompería ~120 efectos y Livewire.
- La duplicación Livewire/Agregado puede persistir si solo se refactoriza el dominio.
- El shape de `eventos` y `poolHabitat` están acoplados a tests/vistas.
- No se ha validado con ejecución real el comportamiento de daño, RNG ni concurrencia.

### Recomendación inmediata

Solicitar al arquitecto que resuelva las preguntas 1-4 antes de aprobar cualquier plan. Mientras tanto, autorizar solo los pasos 0-3 y 14, que no tocan serialización, constructores ni contratos persistidos.
</subagent>