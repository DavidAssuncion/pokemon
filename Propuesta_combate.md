# Propuesta de Combate — Consistencia Temporal de Eventos

## 1. Objetivo

Garantizar que **todos los daños residuales y estados** (recoil de Orbe Vida, quemadura, veneno, parálisis, daño por clima, daño por confusión) se apliquen **de forma síncrona y en el orden correcto** dentro del ciclo de turno, de modo que **el rival nunca actúe con estadísticas o velocidad que ya deberían estar afectadas** por un estado o daño residual pendiente.

## 2. El problema que queremos evitar (riesgo temporal)

La preocupación central: si el daño residual (p. ej. recoil de la Orbe Vida) o un estado (quemadura, parálisis) se **encolan** y se resuelven *después* de que se decida/ejecute el siguiente movimiento, la batalla se vuelve **inconsistente**:

- El rival podría **atacar antes de recibir el daño residual** que ya le correspondía.
- El rival podría **actuar con velocidad completa** cuando ya debería estar paralizado (×0.25).
- Un Pokémon podría **morir por recoil/estado y aun así atacar** en el mismo turno.

## 3. Estado actual verificado (código)

### 3.1. Flujo de una acción en Livewire (`app/Livewire/Combate.php`, `commitAction`)
El motor ya ejecuta todo **de forma síncrona, en un solo request**, en este orden:

1. `ServicioEjecucionBatalla::calcularYAplicarDano()` — aplica el daño del movimiento.
2. `ServicioEjecucionBatalla::aplicarEstado()` — aplica el estado secundario al objetivo.
3. `applyMoveStatChanges()` — aplica cambios de stats (self/target).
4. **Eventos de efectos:** `$objetivo->dispararDanioRecibido()` y `$actor->dispararDanioInfligido()` — aquí **corre el recoil de la Orbe Vida** (`EfectoOrbeVida::onDamageDealt`) que muta el HP del actor inmediatamente.
5. `consumeAction()` — marca que el actor ya actuó.
6. `nextActor()` — decide el siguiente actor (con la velocidad/HP ya actualizados).

**Conclusión sobre el recoil:** NO hay retraso. El recoil de la Orbe Vida se aplica de forma síncrona **dentro de la misma acción**, antes de `nextActor()`. Si el actor muere por recoil, `notifyFainted()` se dispara y `nextActor()` lo salta correctamente (verificado en `commitAction` líneas 385-388).

### 3.2. Efectos de fin de ronda (daño por estado y clima)
`AgregadoBatalla::triggerRoundEndEffects()` (y su equivalente `Combate::advanceRound()`) aplica al final de cada ronda:
- Daño por estado: `Combatiente::aplicarDañoStatus()` (quemadura/veneno 1/16 HP).
- Daño por clima: granizo / tormenta de arena.
Ambos se ejecutan **antes** de iniciar la siguiente ronda (`startNewRound()`), de forma síncrona.

### 3.3. Reducción de velocidad por parálisis — PUNTO A DECIDIR
La velocidad de turno se calcula en `GestorTurnos` **una vez por ronda**. Si un Pokémon se paraliza **durante** la ronda:
- Mientras dura la ronda, el orden de turnos ya está fijado (la parálisis no lo reordena a mitad de ronda).
- A partir de la **siguiente** ronda, la parálisis debería reducir su velocidad a ×0.25.

**Esto es coherente con la mecánica oficial** (los estados afectan la velocidad del siguiente turno), pero la reducción ×0.25 **todavía no está implementada** de forma verificada en el cálculo de velocidad: `procesarParalysis()` solo gestiona el 25% de no poder actuar, no reduce la velocidad. Es un gap pendiente.

## 4. Reglas de oro para evitar el retraso

1. **Eventos SIEMPRE síncronos dentro de la acción.** Todos los listeners/efectos de batalla deben ejecutarse en el mismo request HTTP, **nunca** encolarse con `ShouldQueue`/jobs. Las colas son para trabajo ajeno a la batalla (emails, logs, notificaciones), jamás para mutar el estado de combate.
2. **Orden de aplicación fijo por acción:** daño del movimiento → estado → stat changes → efectos (recoil/items/habilidades) → debilitamiento → pasar turno.
3. **Efectos de fin de ronda antes de la siguiente:** el daño por estado y clima se resuelven siempre antes de `startNewRound()` y antes de `triggerRoundStartEffects()`.
4. **Si un evento muta HP/velocidad, se lee la nueva velocidad inmediatamente** (no se cachea el valor de velocidad de una fase previa).
5. **Regla de cancelación:** si un combatiente muere por daño residual (recoil/estado/clima) antes de actuar, ese turno se le **salta** (`consumeAction` + `nextActor`), nunca ataca.

## 5. Propuesta concreta de integración

### 5.1. `Shared/Constantes` centralizadas
Crear un módulo `Shared/Constantes` con las constantes únicas del motor:
- Tabla de boosts de stats (-6 a +6).
- Probabilidad de crítico (1/16, ×1.5), decidida como correcta.
- Multiplicadores de clima (versión **suave**: ×1.25/×0.75, no el ×1.5/×0.5 estándar — decisión de producto).
- Daño por estado (1/16 HP), cobro de burned físicos (×0.5).
- Multiplicador de parálisis a velocidad (×0.25).

### 5.2. Orden de aplicación garantizado (Pipeline fijo)
Formalizar el orden del punto 4 como una única secuencia de ejecución por acción, verificado por tests:
`calcularDaño → aplicarDaño → aplicarEstado → aplicarStatChanges → dispararEfectos (recoil/items/hab) → verificarDebilitados → avisarSiguienteTurno`

### 5.3. Sistema de estados con hooks temporales
- **Burn:** aplica daño 1/16 en fin de ronda **y** multiplicador ×0.5 a ataques físicos en el cálculo de daño.
- **Quemadura/Veneno:** daño pasivo en fin de ronda (ya implementado).
- **Parálisis:** ×0.25 a la velocidad en el cálculo de turno (PENDIENTE de implementar).

### 5.4. RNG centralizado
Reemplazar los `mt_rand()` dispersos (crítico, confusión, sueño, parálisis, orden de empate) por un `Prng` inyectable, para que las pruebas sean **deterministas** (semilla fija). El factor de daño aleatorio [0.85, 1.0] **no se implementa** (decisión: daño determinista; la variabilidad solo la aporta crítico y los estados).

### 5.5. Turn Order verificado
Confirmar (y cubrir con test) el orden de los combatientes en cada ronda: `priority del movimiento → velocidad (con parálisis ×0.25 y altreos) → tiebreak aleatorio`.

### 5.6. Multi-target
Preparar el paso `ManejadorSpread` (×0.75) en la Cadena de Daño para cuando existan ataques con múltiples objetivos. No activo hasta tener AOE.

## 6. Qué NO hacemos (decisiones de producto)

- **NO** factor de daño aleatorio [0.85, 1.0] → daño determinista.
- **NO** naturalezas → no aplican.
- **NO** cambiar el crítico → se mantiene 1/16 con ×1.5 (correcto).
- **NO** multiplicadores de clima estándar → se mantiene versión suave (×1.25/×0.75).
- **NO** encolar eventos de batalla en colas/jobs → siempre síncronos.

## 7. Próximos pasos (pendientes de implementar, priorizados)

1. Módulo `Shared/Constantes` centralizado.
2. Implementar **reducción de velocidad por parálisis ×0.25** en el cálculo de turno.
3. Implementar **quemadura como multiplicador ×0.5 físico** en la cadena de daño.
4. Sistema de **habilidades por composición de efectos simples** (multiplexado desde BD).
5. **RNG centralizado** inyectable (determinismo en tests).
6. **Persistencia de datos** (species/moves/abilities/items) desde BD + caché, sustituyendo el mock.
7. **Turn Order** y **multi-target** cubiertos por tests.
