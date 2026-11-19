# TASK-065 — Análisis

- **ID:** TASK-065
- **Estado:** PARCIAL (límite de pasos del analista) — bloqueado por decisiones de producto
- **Analista:** `analyst`
- **Fecha:** 2026-11-19

## Resultado (resumen ejecutivo)

1. **`Ausente` está implementado como `EfectoTruant`**, registrado en la fábrica con
   la clave `'ausente'` (`app/Providers/BattleEffectServiceProvider.php:408`). Su
   lógica actual es un toggle booleano por instancia de efecto: en el turno par
   aplica `aplicarInterrupcion()`, que crea la condición volátil `INTERRUPCION`, la
   cual hace que `puedeActuar()` deniegue la acción y la consuma.
2. **No existe un estado "ausente anunciado"** en el dominio: no hay condición, flag
   ni campo serializado que indique "el próximo turno perderá la acción". Solo existe
   el booleano privado `$descansar` dentro del efecto, que se serializa con el
   combatiente pero **no se expone en el contrato de vista** (`CombatienteVista::para()`
   no lo incluye). La vista solo vería `INTERRUPCION` en el turno en que ya se aplica
   (y solo la aplica en `puedeActuar`).
3. **Hallazgo crítico de comportamiento:** en el combate interactivo (Livewire)
   `onTurnStart()` se dispara **después** de que el jugador ya eligió el movimiento
   (`ResolutorAccionBatalla.php:63` y `:180`), mientras que `puedeActuar()` se evalúa
   **antes** (`GestorCicloTurnosBatalla.php:95`). En el combate automático el orden sí
   es correcto (`MotorRondas.php:147-148`). Consecuencia derivada: hoy la habilidad
   **no bloquea el turno de forma fiable en la UI**; la interrupción se aplica tarde y
   se consume en el turno siguiente, produciendo un desfase y un patrón de "pierde
   turno cada ~3 turnos" en lugar de alternancia limpia. Debe confirmarse con una
   prueba manual.
4. **"Escudos" = barreras** (`defensaHpActual` / `defensaEspHpActual`), no PS. Existe
   `Combatiente::curarBarreras(float $porcentaje)` (`Combatiente.php:790`) que delega
   en `BarrerasVidaManager::curarBarreras()`, donde el porcentaje se interpreta como
   **puntos porcentuales sobre el máximo** (`max * $porcentaje / 100`) y se aplica **a
   ambas barreras a la vez**, con tope en el máximo
   (`BarrerasVidaManager.php:59-76`). Para 1/16 hay que pasar `6.25` (o dividir
   `100/16`), no `0.0625`.
5. **Existe un canal rico de reacción al daño**: `ReaccionaAlDanioRecibido::alRecibirDanio()`
   con `AccionBatalla` + `ResultadoDanio`, despachado desde
   `ResolutorImpacto::resolverUnGolpe()` (`:250-252`) y desde
   `AplicadorEfectosPostImpacto::dispararReacciones()` (`:152`). Es el punto natural
   para "si recibe daño mientras iba a estar ausente". Las reacciones se disparan
   **por golpe**, solo si `huboDano()`; el daño indirecto (clima/estado) **no** pasa
   por este canal.
6. **No hay marca visual específica** de ausencia ni contrato para "estará ausente";
   habría que añadir un campo a `CombatienteVista` y pintarlo en Blade. El badge de
   condiciones actual se alimenta de `condicionesBadge` (`CombatienteVista.php:106-116`),
   consumido en `resources/views/livewire/_pokemon-card.blade.php:129`.

## Hechos verificados (archivo:línea)

- `src/Battle/Domain/Effects/EfectoTruant.php:11-35` — `class EfectoTruant implements
  InterfazEfecto`, con `private bool $descansar = false`; `onTurnStart()` aplica
  `$portador->aplicarInterrupcion()` y log "está ausente y pierde el turno", y luego
  invierte `$descansar` **siempre** (dentro del mismo método).
- `src/Battle/Domain/Effects/EfectoTruant.php:28-33` — el toggle ocurre incluso en
  turnos en los que el combatiente no llega a actuar (el efecto no distingue si la
  interrupción se consumió).
- `app/Providers/BattleEffectServiceProvider.php:408` —
  `$fabrica->registrarEfecto('ausente', EfectoTruant::class);`
- `active/catalogo_final_habilidades.csv:55` — `truant, Ausente, ... "Al ejecutar un
  movimiento, descansará en el turno siguiente."` (descripción de catálogo que
  **difiere** del comportamiento real).
- `storage/data/abilities_descripciones.json:26` — descripción: "Alterna turnos:
  actúa en uno y descansa en el siguiente... el ciclo se repite... y no depende de su
  estado."
- `storage/data/abilities_lote3.json:655,1125,1128,1558` — `effect_keys: ["ausente"]`
  asociadas a habilidades.
- `database/seeders/AbilitySeeder.php` (modificado, sin commitear) — ahora fusiona
  `abilities_descripciones.json` + `abilities_lote*.json`; no contiene el literal
  `ausente` (la clave vive en el JSON).
- `src/Battle/Domain/Effects/InterfazEfecto.php:24-49` — hooks: `onBattleStart`,
  `onRoundStart`, `onRoundEnd`, `onRoundEndFinal`, `onDamageDealt`, `onDamageReceived`,
  `onHealed`, `onFainted`, `onTurnStart`, `onTurnEnd`.
- `src/Battle/Domain/Effects/ComportamientosPorDefecto.php:15-66` — implementaciones
  vacías por defecto.
- `src/Battle/Domain/Effects/Capacidades/ReaccionaAlDanioRecibido.php` — interfaz
  `alRecibirDanio(Combatiente, AccionBatalla, ResultadoDanio, AgregadoBatalla)`.
- `src/Battle/Domain/Effects/ColeccionEfectos.php:414-425` — despacho
  `dispararDanioRecibidoCompleto()` solo a efectos que implementan la capacidad.
- `src/Battle/Domain/Effects/ColeccionEfectos.php:477-482` — `dispararDanioRecibido()`
  → `onDamageReceived()` (canal simple, sin `AccionBatalla`).
- `src/Battle/Domain/Ejecucion/ResolutorImpacto.php:245-252` — por cada golpe: calcula
  daño, aplica postura y, si `$resultado->huboDano()`, llama `dispararReacciones()`.
- `src/Battle/Domain/Ejecucion/AplicadorEfectosPostImpacto.php:145-166` —
  `dispararReacciones()` llama `dispararDanioRecibidoCompleto()`, contacto, escudo
  roto y `alDebilitarRival`.
- `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:100-109` — canal legacy
  `notificarDano()` → `dispararDanioRecibido($daño)` (daño agregado, sin
  `AccionBatalla`). *(Archivo modificado localmente por TASK-063.)*
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:120-122` — canal legacy en
  la UI: `dispararDanioRecibido($daño)` con el daño total del movimiento. *(Archivo
  modificado localmente por TASK-063.)*
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:63` —
  `$actor->effects()->dispararInicioTurno($actor, $battle)` ocurre **ahora sí**, justo
  antes de `calcularYAplicarDano` (línea 64), después de que la acción fue autorizada
  y elegida.
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:95-125` — `puedeActuar()`
  se evalúa antes de que el actor elija; si deniega, consume acción y sale. No vuelve
  a comprobarse tras `onTurnStart`.
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:147-158` — en batalla
  automática: `dispararInicioTurno` **antes** de `puedeActuar()`; orden correcto.
- `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php:173-184` —
  `INTERRUPCION` se consume y devuelve `ResultadoAccion::denegada('fue interrumpido')`.
- `src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:92-104` —
  `aplicarInterrupcion()` limpia prioridad, cancela cargas, rompe combo y añade
  `INTERRUPCION` con `turnos: 1`.
- `src/Battle/Domain/Enums/ClaveCondicion.php:36,90,129` — `INTERRUPCION =
  'interrupcion'`, label `'interrupción'` y alias `'interrupt'`.
- `src/Battle/Domain/Combatiente.php:340-343` — `aplicarInterrupcion()` delega en
  `CondicionesCombatiente` con turno/movimientos/recursos.
- `src/Battle/Domain/Combatiente.php:790-797` — `curarBarreras(float $porcentaje)`
  delega a `VitalidadCombatiente::curarBarreras`.
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:245-260` —
  `curarBarreras($porcentaje, $maxFis, $maxEsp)`; ambos máximos.
- `src/Battle/Domain/BarrerasVidaManager.php:59-76` — `min($max, $actual + $max *
  $porcentaje / 100)` para física y especial.
- `src/Battle/Domain/Combatiente.php:738-753` — `recibirDaño(...)` →
  `VitalidadCombatiente::recibirDano`, con `multiplicadorEscudo`.
- `src/Battle/Domain/CombatienteVista.php:94-116` — `condiciones`,
  `condicionesDetalle`, `condicionesBadge`; **no** hay campo de "ausencia futura".
- `src/Battle/Domain/CombatienteVista.php:117-120` — `habilidades` expone
  `obtenerClave()` de efectos (contiene `'ausente'`).
- `src/Battle/Domain/CombatienteVista.php:65-74` — `defHp`, `maxDefHp`, `spDefHp`,
  `maxSpDefHp`, `defPct`, `spDefPct` (escudos visibles).
- `resources/views/livewire/_pokemon-card.blade.php:129` —
  `@foreach($p['condicionesBadge'] as $condicion)` (punto de pintado de badges).
- `src/Battle/Domain/Effects/EfectoIra.php:33-47` — modelo exacto de "cada golpe
  recibido sube 1/16 Ataque y Ataque Especial" vía `alRecibirDanio` +
  `MultiplicadoresStats::aplicarTemporal`.
- `src/Battle/Domain/Effects/EfectoReaccionDanioStats.php:41-65` — modelo de subida de
  etapas con `CambioStat::desdeEtapas($stat, $etapas)->aplicadoTemporalEn(...)`.
- `src/Battle/Domain/Effects/EfectoCorazaIra.php:31-57` — modelo de reacción única
  (`$usado`) con subida de `ATAQUE` en 1 etapa.
- `src/Battle/Domain/Componentes/Combatiente/TurnoCombatiente.php:49-64` —
  serialización del estado de turno (no incluye nada de ausencia).
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:48-55` —
  serializa HP y ambas barreras.
- `src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:36-39` —
  serializa condiciones vía `SerializadorCombatiente`.
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:216-246` — `avanzarRonda()`
  cierra ronda (`triggerRoundEndEffects`) y abre la siguiente
  (`triggerRoundStartEffects`), emitiendo un evento `round` único.
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:184-230` — daño indirecto
  (estado/clima) **no** invoca `dispararDanioRecibido`.
- `src/Battle/Domain/AgregadoBatalla.php:76-93,181-199` — `__clone`/`__wakeup` reseed de
  turnManager, clima, gas reactivo (punto de compatibilidad de serialización).

## Flujo actual de Ausente y del daño

### Ausente (hoy)

**Batalla automática** (`AgregadoBatalla::ejecutarBatalla` → `MotorRondas`):

1. `getNextActor()`.
2. `dispararInicioTurno($actor)` → `EfectoTruant::onTurnStart`:
   - si `$descansar === true` → `aplicarInterrupcion()` (crea `INTERRUPCION`) + log.
   - invierte `$descansar`.
3. `puedeActuar()` consume `INTERRUPCION` → `denegada('fue interrumpido')` → consume
   acción y log.

**Batalla interactiva** (Livewire):

1. `avanzar()` → `puedeActuar()` (sin `onTurnStart` previo).
2. Jugador/IA elige movimiento.
3. `commit()` → `ResolutorAccionBatalla::aplicarMovimiento()` →
   **`dispararInicioTurno()`** → `EfectoTruant::onTurnStart` (aplica `INTERRUPCION`,
   no se revalida) → el movimiento se resuelve **igual**.
4. La `INTERRUPCION` creada se consumirá en un `puedeActuar()` posterior (siguiente
   turno del mismo combatiente), desfasando el ciclo.

> Esto explica por qué un estado `INTERRUPCION` "aparece" en la vista sin que el turno
> se pierda en el momento: se aplicó tarde.

### Daño (canales existentes)

- **Canal rico por golpe:** `ResolutorImpacto::resolverUnGolpe` →
  `AplicadorEfectosPostImpacto::dispararReacciones` → `dispararDanioRecibidoCompleto` →
  `ReaccionaAlDanioRecibido::alRecibirDanio(portador, accionGolpe, ResultadoDanio,
  battle)`. Se ejecuta **solo si `huboDano()`**, por cada golpe (multi-golpe = varias
  veces) y por cada objetivo (multiobjetivo).
- **Canal simple/agregado:** `EjecutorAccion::notificarDano` y
  `ResolutorAccionBatalla` → `dispararDanioRecibido($daño)` → `onDamageReceived` (sin
  `AccionBatalla`, con daño total).
- **No hay reacción** para daño indirecto (estado/clima): `MotorRondas::aplicarDanioIndirecto`
  llama `recibirDaño` pero no `dispararDanioRecibido`.
- **No hay cancelación de turno en curso**: las reacciones solo modifican stats/HP/
  condiciones; no existe API para "cancelar la ausencia" ni "habilitar acción extra"
  tras daño.

## Inventario de escudos / estados / marcas visuales

**Escudos (barreras):**

- Almacenamiento: `VitalidadCombatiente::$defensaHpActual` (física) y
  `$defensaEspHpActual` (especial).
- Máximos: `battleStats()->defenseHp` y `battleStats()->spDefenseHp`.
- Restauración fraccionaria existente: `curarBarreras(6.25)` (ambas, con tope),
  `curarConExcedente` (reparte excedente), `curarHp(porcentaje)`.
- Efectos ya usan `curarBarreras` (pendiente de confirmar líneas exactas de
  `EfectoComealmas`, `Campo Magnético`, etc.).

**Estados/condiciones:**

- `ClaveCondicion::INTERRUPCION` y `ATURDIDO`, `CONFUSION`, `SILENCIO`, `SUSTITUTO`,
  etc.
- Badges en vista: `condicionesBadge` (filtra `confusion`).

**Marca visual de ausencia:** no existe. Candidato: nuevo campo en
`CombatienteVista::para()` leído desde el efecto (habría que exponer
`EfectoTruant::estaraAusente()` en la `ColeccionEfectos` o en `Combatiente`), y
pintarlo en `_pokemon-card.blade.php`.

## Impacto y archivos candidatos por capa

**Dominio (`src/Battle/`):**

- `Domain/Effects/EfectoTruant.php` — rediseño (estado explícito `estaraAusente`,
  restauración de barreras, reacción al daño, cancelación).
- `Domain/Effects/InterfazEfecto.php` + `ComportamientosPorDefecto.php` — si se
  requiere hook nuevo o exponer marca.
- `Domain/Combatiente.php` — acceso a barreras/estadísticas y quizá nuevo método de
  estado.
- `Domain/CombatienteVista.php` — añadir campo `estaraAusente` / `ausenciaTexto` /
  badge.
- `Domain/Componentes/Agregado/MotorRondas.php` — orden de `dispararInicioTurno` vs
  `puedeActuar` (clave para el rediseño).
- Posible `Domain/Effects/Capacidades/` — nueva capacidad si el enfado no encaja en
  `ReaccionaAlDanioRecibido`.

**Aplicación / Livewire (`app/Livewire/Combate/Soporte/`):**

- `ResolutorAccionBatalla.php` — orden actual de `dispararInicioTurno`.
- `GestorCicloTurnosBatalla.php` — `puedeActuar()` antes de elegir; punto donde
  debería anunciarse/forzarse la ausencia.
- `GestorAccionBatalla.php` — resolución de acción (no revalida `puedeActuar`).
- `GrabadorEventosBatalla.php` / `SincronizadorVistaBatalla.php` — si se emite
  evento/badge.

**Blade (`resources/views/livewire/`):**

- `_pokemon-card.blade.php` — badge de ausencia.
- `partials/battle-field.blade.php`, `combate.blade.php` — tooltip/icono.

**Datos:**

- `storage/data/abilities_descripciones.json:26` — nueva descripción.
- `database/seeders/AbilitySeeder.php` — sin cambio si la descripción vive en JSON.
- `active/catalogo_final_habilidades.csv:55` — actualizar texto.

## Preguntas bloqueantes

1. **Momento de la restauración de 1/16:** ¿al inicio del turno ausente (antes de
   saber si recibe daño), al final del turno ausente, o al confirmar que no actuó? Si
   recibe daño y se cancela la ausencia, ¿se restaura igualmente ese 1/16?
2. **Al cancelar la ausencia por daño, ¿puede actuar de inmediato** en ese mismo
   turno (recupera la acción) o solo evita la pérdida del siguiente? ¿El turno ya
   estaba anunciado como perdido en la UI?
3. **"Enfado":** ¿+1 nivel de Ataque permanente (etapas con `CambioStat`) o temporal
   (`aplicarTemporal` como Ira)? ¿Se dispara con **cualquier** daño (incluido
   indirecto de estado/clima, retroceso, viento) o solo con daño directo de
   movimiento? ¿Una vez por turno o por cada golpe (multi-golpe)?
4. **¿La restauración de escudos es a una barrera (física/especial) o a ambas?**
   `curarBarreras` restaura ambas por igual; ¿es lo deseado?
5. **Redondeo:** ¿1/16 sobre máximo con decimales (6.25%) o entero redondeado hacia
   arriba/abajo? El sistema actual trabaja en `float`.
6. **Interacción con habilidad anulada/Gas Reactivo:** si se anula `ausente` a mitad
   de ciclo, ¿se resetea el contador? ¿Y al copiar/intercambiar
   (Transformación/Intercambio)?
7. **Compatibilidad de sesión:** batallas ya guardadas con `EfectoTruant` antiguo
   (booleano `$descansar`) — ¿migración de payload o `try/catch` con estado por
   defecto? ¿Sube `SESSION_VERSION`?

## Decisiones/hipótesis separadas

**Hipótesis (no verificadas por completo):**

- El desfase en Livewire es real y observable; requiere prueba manual. (Alta
  probabilidad por el orden de llamadas.)
- "Escudos" significa barreras física+especial. (Soportado por el uso de "escudos" en
  las descripciones y por la existencia de `curarBarreras`.)
- El enfado debe modelarse con `CambioStat::desdeEtapas(StatClave::ATAQUE, 1)` (etapa
  real), no con `aplicarTemporal` (1/16 de Ira), porque el desarrollador dice
  "aumenta 1 nivel su ataque". (Interpretación razonable.)
- La restauración de 1/16 debe replicar el patrón de `EfectoCuracionFinTurno`/
  `EfectoComealmas`.

## Verificación realizada, problemas y riesgos

**Verificación:** lectura estática de `EfectoTruant`, `InterfazEfecto`,
`ComportamientosPorDefecto`, `ColeccionEfectos`, `ReaccionaAlDanioRecibido`,
`EfectoIra`, `EfectoReaccionDanioStats`, `EfectoCorazaIra`, `Combatiente`,
`VitalidadCombatiente`, `BarrerasVidaManager`, `AgregadoBatalla`, `MotorRondas`,
`EjecutorAccion`, `ResolutorImpacto`, `AplicadorEfectosPostImpacto`,
`ServicioEjecucionBatalla`, `GestorCicloTurnosBatalla`, `GestorAccionBatalla`,
`ResolutorAccionBatalla`, `CombatienteVista`, `CondicionesCombatiente`,
`TurnoCombatiente`, `EstadosCombatiente`, `ClaveCondicion`, seeder/JSON/catálogo y
búsquedas de `ausente`/`truant`/`escudo`/`barrera`. Sin tests ni ejecución.

**Problemas / riesgos:**

- **[alto] Orden `onTurnStart` vs `puedeActuar` en Livewire** — la habilidad no bloquea
  de forma fiable; impacto directo en el rediseño. Mitigación: mover la evaluación de
  ausencia a `avanzar()` (antes de elegir acción) o revalidar `puedeActuar()` tras
  `dispararInicioTurno`.
- **[alto] "Cancelar la ausencia" no tiene mecanismo** — no existe API para anular una
  interrupción anunciada ni devolver la acción. Requiere nuevo estado en dominio y
  coordinación con la UI (evitar que el jugador ya haya elegido).
- **[medio] Doble canal de daño** — la reacción puede dispararse dos veces si se usa
  `onDamageReceived` y `ReaccionaAlDanioRecibido` a la vez (los canales coexisten).
  Elegir uno.
- **[medio] Daño indirecto no dispara reacciones** — si el enfado debe activarse con
  clima/estado, hay que añadir el disparo en `MotorRondas::aplicarDanioIndirecto`.
- **[medio] Serialización/compatibilidad** — `EfectoTruant` actual guarda `$descansar`;
  un cambio de propiedades rompe batallas en curso. Revisar `SESSION_VERSION` y
  `__wakeup`.
- **[medio] Anulación/copia de habilidad** — `anularHabilidad`/`copiarHabilidadDe` no
  está verificado que preserven el ciclo de Truant; riesgo de doble ausencia o pérdida
  de estado.
- **[bajo] Descripción desincronizada** — `catalogo_final_habilidades.csv:55` dice "al
  ejecutar un movimiento" (modelo Pokémon real) pero la implementación alterna por
  turno; el JSON ya refleja lo implementado.
- **[bajo] Redondeo de 1/16** — el sistema usa floats; definir si se trunca/redondea
  para evitar escudos con decimales visibles.

**Trabajo no completado por el corte:**

- Confirmar líneas exactas de `EfectoComealmas`, `EfectoCuracionFinTurno`,
  `EfectoPantallista` y demás efectos que restauran barreras.
- Leer serialización de efectos y migración de payload.
- Revisar `GestorVistaBatalla`/`SincronizadorVistaBatalla` y `_pokemon-card.blade.php`
  completos para el contrato de badge.
- Revisar `EquipoBatalla::limpiarPantallas`/`Pantallista` y la interacción con
  Protección/Aguante.
- Revisar TASK-040/`architecture.md` y `.ai/rules/*` aplicables a `src/**` y
  `resources/**`.

**Recomendación:** antes de diseñar, resolver las preguntas 1–4 (son decisiones de
producto, no deducibles del repo) y encargar la verificación manual del desfase en
Livewire; en paralelo completar las lecturas pendientes para cerrar los riesgos de
serialización y de reutilización de `curarBarreras`.
