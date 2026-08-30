# SPEC — Evolución de Exploraciones a Expediciones (iteración 1)

> Documento de spec aprobado por el PO. Fuente de verdad para backend y frontend de la
> iteración 1 de "expediciones con riesgo". Consumido por el agente Frontend.

═══════════════════════════════════════════════
SPEC — Evolución de Exploraciones a Expediciones (iteración 1)
═══════════════════════════════════════════════

## Decisiones del PO
- D0 Peligro hábitat 1–5: columna `habitats.peligro` (unsignedSmallInteger, nullable, default 1). Seed orientativo 1–5 (bosque tranquilo = 1, cueva de dragones = 5). Estrellas UI = valor.
- D1 Afinidad derivada del pool de especies del hábitat (a ese nivel) + alineación de tipos vía TypeChart (Src\Shared\Tipos\TypeChart). Sin tabla nueva.
- D2 Pérdida de tiempo: adelantar `ultimo_procesado` hasta la próxima ejecución (incluso en el futuro). Sin bucle de presupuesto.
- D3 Caramelos de tipo desde EXP tipada: floor((exp_tipo × 0,2) / 100). Cuenta +100% T; cada integrante + floor((T × 0,8) / 3).
- D4 Español en campos (tipo, resolucion).
- D5 Seguir docs/ddd.md.
- D6 Capacidad NO usa Reclutado.exp; base = stats base de especie (pokemon_stats.base_stat).
- D7 Roles: enum team_members.behavior = VANGUARDIA, COMBATIENTE, RECOLECTOR, RASTREADOR (quitar SOPORTE, añadir RASTREADOR; migrar datos SOPORTE → RASTREADOR).
- D8 "Objetos" = caramelos: evento hallazgo entrega caramelos (familia/EV/tipo).
- D9 Sinergias incluidas + riesgo estimado en preview.
- D10 Huida condicionada a desventaja (no 15% plano).
- D11 `exploraciones_activas.eventos` → jsonb + cast 'collection' en ExploracionActiva.
- D12 Preview obligatorio antes de enviar el equipo.

## Roles (modificadores por behavior)
- Vanguardia: +30% encuentros (todos), detecta emboscadas (−50% o evita penalización), −50% tiempo terreno/bloqueo, +25% EXP.
- Combatiente: +resolución combate (contra grupos), −40% probabilidad retirada, −50% penalización clima, +25% EXP.
- Recolector: −30% encuentros, +50% caramelos hallazgos y +calidad, −20% EXP.
- Rastreador: +30% encuentros Pokémon (no peligros), −50% huidas, +captura, −tiempo perdido general, +eventos especiales.

## Sinergias (tabla config-driven, aplicadas como modificadores de capacidad/resolución)
- Pares: V+C=Asalto (grupos, −retirada); V+T=Patrulla (encuentros dirigidos, detección); C+T=Cacería (encuentros, −huidas, +captura); R+T=Prospección (+caramelos, raros); V+R=Avance seguro (−tiempo, bloqueos); C+R=Escolta (−retirada, conserva recursos).
- Tríos: V+C+C=Dominio del combate; V+C+T=Cacería; R+R+C=Recolección segura; V+R+T=Reconocimiento; V+R+C=Expedición equilibrada.
- Negativas (3 del mismo rol): VVV=Exploración agresiva (+riesgo, +tiempo); CCC=Fuerza bruta (−caramelos, exposición); RRR=Especialistas (respuesta baja vs grupos); TTT=Rastreo intensivo (+emboscadas).

## Requisitos
- RF-01 CalculadorPeligro (Domain puro): peligroZona = peligro(1–5) + nivel_exploración → escala ×5/×10 para dificultades.
- RF-02 CalculadorCapacidadEquipo (Domain puro): capacidad = base(stats especie normalizada 0–100) + afinidad + rol + sinergia. Sin Reclutado.exp.
- RF-03 Afinidad: por miembro, +bonus si especie en pool del nivel; +/− alineación de tipos vs tipos del pool (TypeChart); topes [−20, +30].
- RF-04 SimuladorEncuentros rework: 45% encuentro · 20% hallazgo · 15% encuentro especial · 10% contratiempo · 10% evento neutral. Subtipos encuentro: 80% normal · 10% grupo · 7% emboscada · 3% excepcional (sin huida plana). Hallazgo → caramelos. Constantes centralizadas. Mantener seam aleatorio y poolPonderado.
- RF-05 Tick D2: generar evento por slot → resolver → acumular duration_loss → al final `eventos['ultimo_procesado'] = max(hasta, hasta + tiempo_perdido_tick)`. `eventos['tiempo_perdido']` acumulado; `duration_real = nominal − tiempo_perdido` (mín 0). Retirada → despachar FinalizarExploracionCommand.
- RF-06 Resolución (EvaluadorExploracion): dificultad = base(subtipo) + peligro×5; capacidad >= dificultad → éxito; entre dificultad−15 y dificultad → éxito con coste (−5/10 min); < dificultad−15 → desventaja: 15% salvaje huye (sin recompensa, avistado sí) · resto derrota (−10 min) · si < dificultad−30 → retirada probable. Emboscada: Vanguardia detecta (evita o −50%); vence → −10 min; pierde → −15 min + retirada probable. Contratiempo (desorientacion −15, terreno −10, clima −10, bloqueo −15) mitigado por rol.
- RF-07 Derrotados = solo resolucion 'victoria', expandidos por miembro del grupo. Avistados = todo evento con pokemon_id(s) (incluye huidas) → ActualizarPokedexJob AVISTADO. Retrocompat: evento sin resolucion = victoria.
- RF-08 EvaluadorExploracion final: exito_excepcional | exito | exito_parcial | fracaso | retirada. Multiplicadores: 1.2 / 1.0 / 0.7 / 0.25 / retirada 1.0 (solo lo ya obtenido).
- RF-09 Retirada: marca eventos['retirada']={reason}; finaliza anticipado; conserva recompensas.
- RF-10 Contrato resultado aditivo: conservar capturados, caramelos_familia, caramelos_ev, caramelos_tipo, exp; añadir resultado, duration_real, tiempo_perdido, incidentes{encuentros,victorias,huidas,emboscadas,contratiempos}.
- RF-11 Preview: GET /exploraciones/preview?team_id=&habitat_id=&level= (anti-IDOR equipo del usuario) → JSON {peligro_estrellas, afinidad, advertencias[], roles[], riesgo(Bajo/Medio/Alto/Extremo), recompensa_esperada}. Advertencias: "Pokémon de tipo {X} en zona con Pokémon {Y}" → Fracaso asegurado; "Pokémon débiles para el nivel {N}" → Fracaso absoluto; buen equipo → "Equipo bien preparado para esta zona". Sin probabilidades numéricas.
- RF-12 Migración enum behavior (quitar SOPORTE, añadir RASTREADOR, UPDATE SOPORTE→RASTREADOR) + validación TeamController (in:VANGUARDIA,COMBATIENTE,RECOLECTOR,RASTREADOR) + ReclutadosSeeder + tests.
- RF-13 Sinergias como modificadores; Vanguardia detecta emboscadas (único cambio de resolución).
- RF-14 D3: EXP tipada por victoria (1 tipo → 100%, 2 tipos → 50/50); cuenta += T; cada integrante += intdiv(T*8,10)/3 → floor((T×0.8)/3); caramelos_tipo = floor((exp_tipo × 0.2)/100) → player_inventory item_key 'tipo:{slug}' (mecánica existente). Sustituye "1 caramelo tipo por derrotado" de CalculadorRecompensas::calcularCaramelosTipo. Caramelos familia (fase × derrotas), EV (effort) y capturas (ProbabilidadCaptura) sin cambio.

## Contratos
- Eventos jsonb + cast 'collection'; handlers/controller adaptados: $eventos = $exploracion->eventos ?? collect(); $eventos->get('bitacora', []); $eventos->put(...); $exploracion->eventos = $eventos.
- Nuevos shapes: {"tipo":"hallazgo","subtype":"caramelo_familia","pokemon_id":25,"cantidad":1,"timestamp":"…"}; {"tipo":"huida","pokemon_id":25,"resolucion":"sin_combate","timestamp":"…"}; {"tipo":"emboscada","pokemon_ids":[19,19,20],"dificultad":42,"resolucion":"superada_con_cost","duration_loss":10,"timestamp":"…"}; {"tipo":"retirada","reason":"grupo_enemigo","timestamp":"…"}; resultado aditivo (ver RF-10).
- Nota: la bitácora actual usa "tipo" (no "type").

## Migraciones
1. habitats: add peligro unsignedSmallInteger nullable default 1.
2. team_members.behavior enum: quitar SOPORTE, añadir RASTREADOR; UPDATE behavior='SOPORTE'→'RASTREADOR'. Verificar nombre de la constraint en Postgres y compatibilidad con SQLite (Laravel 12 rebuilds; si el schema grammar no soporta el cambio de check en SQLite, plantea un enfoque que funcione en ambos: p.ej. recrear la tabla o usar DB::statement condicional por driver).
3. exploraciones_activas.eventos: json → jsonb en pgsql (text/json en sqlite si hace falta). Verificar que el test con SQLite pasa; si jsonb no está soportado en SQLite, condicionar por driver.
4. Cambiar cast en ExploracionActiva: 'eventos' => 'collection'.

## Orden de trabajo sugerido (TDD)
1. Migraciones + modelo (cast collection, relación team/habitat ya existente).
2. Domain puro: CalculadorPeligro, CalculadorCapacidadEquipo, EvaluadorExploracion (resolución por evento + categoría final), SimuladorEncuentros rework, CalculadorRecompensas (D3 + multiplicadores) — con tests unitarios y seam aleatorio.
3. App: ProcesarExploracionHandler (D2), FinalizarExploracionHandler (Evaluador + multiplicadores + derrotados/avistados), PersistirRecompensas (reparto EXP 100/80/20), TransformadorResultadoExploracion (aditivo).
4. Controller: GET /exploraciones/preview + toActiva/toTerminada adaptados a Collection y nuevos campos.
5. Tests feature: pipeline completo, cada tipo de evento, retirada, tiempo perdido, retrocompatibilidad de bitácoras antiguas (sin 'resolucion' → victoria), idempotencia (reintento no duplica), rollback transaccional (UnitOfWork), Multiplayer. ACTUALIZA los tests existentes que cambian de semántica: ExploracionesTest::test_finalizacion_otorga_caramelos_de_tipo_por_derrotado (nueva fórmula D3) y EquiposControllerTest (SOPORTE → RASTREADOR). NO elimines tests; adáptalos.
6. Pint + suite: vendor/bin/pint --dirty; php artisan test --compact --filter=Exploraciones; luego toda la suite si procede.

## Verificación final
- Todos los tests del módulo Exploraciones verdes, incl. los preexistentes adaptados.
- vendor/bin/pint --dirty sin cambios pendientes.
- Responde con: resumen de archivos creados/modificados, tests añadidos/adaptados y resultado de la suite.
