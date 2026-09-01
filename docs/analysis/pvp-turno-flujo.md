# PvP — Flujo de Turnos y partición de nextActor

## 1. Objetivo

Diseñar el refactor de `nextActor()` en `app/Livewire/Combate.php` para soportar tres modos de turno en una misma batalla PvP anfitrión–invitado, manteniendo la regla de oro de `Propuesta_combate.md`: **resolución síncrona en un solo hilo, en la máquina del anfitrión, sin colas ni jobs para mutar estado de combate**.

## 2. Contexto verificado

### 2.1. Orquestador actual (`Combate.php`)

| Método | Líneas | Responsabilidad |
|---|---|---|
| `nextActor()` | 175–254 | Obtiene actor de `GestorTurnos::getNextActor()`, verifica estado (sueño/hielo/etc.), y bifurca: `team===0` → fase `player_target` (humano local elige); `team===1` → `prepareAiAnimation()` (IA elige y fija `pendingAction`, la animación llama `commitAction()`). |
| `commitAction()` | 355–449 | Ejecuta la acción pendiente: daño → estado → stat changes → efectos (recoil) → debilitamiento → `consumeAction()` → `nextActor()`. Todo síncrono en un solo request. |
| `selectMove()` | 509–548 | El humano local confirma movimiento+objetivo → fija `pendingAction`. |
| `previewTarget()` | 454–507 | Previsualiza daño por movimiento contra un objetivo. |
| `prepareAiAnimation()` | 320–351 | IA: elige objetivo + mejor movimiento, fija `pendingAction`, configura animación. La animación Alpine dispara `commitAction()`. |

### 2.2. GestorTurnos (`GestorTurnos.php`)

- `getNextActor()` devuelve el combatiente vivo con **mayor velocidad acumulada** (prioridad del motor, no del movimiento por ahora).
- `consumeAction()` reduce la velocidad acumulada del actor por la menor velocidad entre vivos y registra que actuó.
- `hayAlgunoConAccionPendiente()` verifica si algún vivo tiene `velocidadAcumulada > 0`.
- **Clave**: produce múltiples participantes por ronda, incluso dos seguidos del mismo equipo si su velocidad acumulada es la más alta dos veces consecutivas.

### 2.3. Motor síncrono (`Propuesta_combate.md`)

Reglas de oro confirmadas:
1. Eventos siempre síncronos dentro del request — nunca `ShouldQueue`.
2. Orden fijo: daño → estado → stat changes → efectos (recoil) → debilitamiento → pasar turno.
3. Efectos de fin de ronda antes de la siguiente ronda.
4. Si un evento muta HP/velocidad, se lee la nueva velocidad inmediatamente.
5. **Regla de cancelación**: si un combatiente muere por daño residual antes de actuar, su turno se salta.

### 2.4. PvP propuesto (`pvp-anfitrion-invitado.md`)

- Autoridad única: motor en anfitrión, invitado solo envía intención y pinta.
- Estado vive en `session($battleId)` del anfitrión.
- Invitado envía `DTOAccionBatalla` por WebSocket (Reverb).
- Anfitrión difunde estado/log/animación a ambos.

### 2.5. Punto crítico en el código actual

```php
// Línea 236 de Combate.php
$isPlayer = $actorView['team'] === 0;
```

Esta línea **asume** que `team0` = humano local y `team1` = IA. En PvP, `team0` siempre será el anfitrión y `team1` siempre será el invitado (así lo establece la fase lobby del doc PvP). La partición debe extender esta dicotomía binaria a un **ternario**: anfitrión / invitado / IA.

## 3. Modelo de turnos (3 tipos)

### 3.1. Definición formal

| Tipo | Condición | Quién elige | Quién resuelve |
|---|---|---|---|
| **(a) Anfitrión** | `actor.team === 0` AND modo === `pvp` AND invitado conectado (o modo `ia`) | Anfitrión localmente en su UI | Anfitrión (commitAction) |
| **(b) Invitado** | `actor.team === 1` AND modo === `pvp` AND invitado conectado | Invitado vía WebSocket | Anfitrión (commitAction) |
| **(c) IA** | modo === `ia` OR (modo === `pvp` AND invitado NO conectado) | `SelectorAccionIA` | Anfitrión (commitAction) |

### 3.2. Resolución

Los tres tipos comparten la **misma resolución** en `commitAction()`. La única diferencia es **cómo se obtiene la intención** (pendingAction):

- **(a)** El anfitrión interactúa con su UI: `previewTarget()` → `selectMove()` → fija `pendingAction` → `commitAction()`.
- **(b)** El anfitrión **bloquea** la espera de la intención del invitado por WebSocket. Al recibirla, la valida, fija `pendingAction` y ejecuta `commitAction()`.
- **(c)** `prepareAiAnimation()` fija `pendingAction` inmediatamente → `commitAction()`.

### 3.3. Modo de la batalla

Introducir una propiedad en `Combate.php`:

```
public string $battleMode = 'ia';  // 'ia' | 'pvp'
```

- Se establece en `mount()` al detectar que hay `sala_id` en sesión (PvP) o no (IA).
- En `pvp`, `team1` = anfitrión, `team2` = invitado.
- En `ia`, `team1` = humano local, `team2` = IA (comportamiento actual intacto).

## 4. Refactor de nextActor()

### 4.1. Estructura actual (antes)

```
nextActor():
  1. Obtener battle de sesión
  2. Verificar equipos vivos → endBattle si no
  3. Avanzar ronda si nadie con acción pendiente
  4. Obtener actor de GestorTurnos
  5. Verificar estado (sueño/hielo/etc.) → saltar si no puede actuar
  6. syncViewData + findPokemonViewData
  7. BIFURCACIÓN:
     - team === 0 → player_target (humano elige)
     - team !== 0 → prepareAiAnimation (IA elige)
  8. saveBattle
```

### 4.2. Estructura propuesta (después)

```
nextActor():
  1-5: (idéntico al actual — obtención de actor, verificación de estado)

  6. syncViewData + findPokemonViewData
  7. Determinar MODELO DE TURNO:
     $actorTeam = $actorView['team'];
     $turnType = $this->resolveTurnType($actorTeam);

  8. BIFURCACIÓN ternaria:
     CASE 'host':
       → lógica actual de player_target (UI del anfitrión elige)
       → (sin cambios significativos)

     CASE 'guest':
       → Notificar al invitado: "tu turno" (broadcast por Reverb)
       → Serializar y enviar estado mínimo del actor (moves disponibles)
       → PHASE = 'waiting_guest_intention'
       → Guardar battle
       → return (NO avanza; la reanudación llega desde el listener de Reverb)

     CASE 'ai':
       → prepareAiAnimation (comportamiento actual intacto)
       → return (la animación llama commitAction)

  9. saveBattle + buildTurnQueue (solo para host/ai, que no retornan antes)
```

### 4.3. Método `resolveTurnType()`

```php
private function resolveTurnType(int $actorTeam): string
{
    if ($this->battleMode === 'ia') {
        return $actorTeam === 0 ? 'host' : 'ai';
    }

    // Modo PvP
    if ($actorTeam === 0) {
        return 'host';
    }

    // actorTeam === 1 en modo PvP
    if ($this->isGuestConnected()) {
        return 'guest';
    }

    return 'ai';  // fallback: invitado desconectado → IA
}
```

### 4.4. Dónde se bloquea la espera del invitado

En el **case 'guest'** de `nextActor()`:

1. Se marca `phase = 'waiting_guest_intention'`.
2. Se broadcast por el canal privado (`privado-batalla.{codigo}`) un evento `TurnoInvitado` con:
   - `actorId` (para que el invitado sepa qué Pokémon actúa)
   - Movimientos disponibles (serializados como array de `DTOMovimientoBatalla::toLivewire()`)
   - Estado del combatiente (HP, estado, stats) para que el invitado pinte su UI
3. Se guarda la sesión y se **retorna** sin llamar a `commitAction()` ni a `nextActor()`.
4. El request HTTP del anfitrión termina. **No hay hilo bloqueado.** El anfitrión queda "a la espera" en el sentido de que su fase es `waiting_guest_intention` y no procesará nada más hasta que llegue la intención.

### 4.5. Reanudación del flujo

Cuando el invitado envía su `DTOAccionBatalla` por WebSocket, el anfitrión recibe un evento Livewire/Reverb que ejecuta un método tipo:

```php
public function receiveGuestIntention(array $intention): void
{
    // 1. Validar que estamos en fase 'waiting_guest_intention'
    if ($this->phase !== 'waiting_guest_intention') {
        return;  // descarta intención fuera de turno
    }

    // 2. Validar que el actorId coincide con el actor actual
    $battle = $this->getBattle();
    // (o validar contra $this->actingRefId)

    // 3. Deserializar la intención en DTOAccionBatalla
    $dto = DTOAccionBatalla::fromLivewire($intention);

    // 4. Validar que actorId y defenderId corresponden a combatientes vivos
    //    y que el movimiento es válido para ese actor
    // 5. Fijar pendingAction
    $battle->setPendingAction($dto);
    $this->saveBattle($battle);

    // 6. Ejecutar resolución (el motor síncrono)
    $this->commitAction();
}
```

**Punto crítico**: el orquestador no tiene un "hilo bloqueado" esperando. El flujo es event-driven: `nextActor()` pone la fase en `waiting_guest_intention`, y `receiveGuestIntention()` retoma ejecutando `commitAction()`. Esto es equivalente al patrón actual donde la animación Alpine dispara `commitAction()` después de un delay visual.

### 4.6. Diferencia con el flujo IA

En el caso IA, la secuencia es: `nextActor()` → `prepareAiAnimation()` → fija `pendingAction` → la animación Alpine espera X ms → llama `commitAction()`. En PvP invitado, la secuencia es: `nextActor()` → notifica al invitado → espera → el invitado envía intención → `receiveGuestIntention()` → `commitAction()`. El motor de resolución (`commitAction`) es **idéntico** en ambos casos.

## 5. Serialización de intenciones

### 5.1. DTO entrante (del invitado al anfitrión)

Reutilizar `DTOAccionBatalla` existente, que ya implementa `Wireable` y tiene `fromLivewire()`:

```json
{
  "type": "attack",
  "actorId": "pokemon_abc123",
  "defenderId": "pokemon_def456",
  "attackerNombre": "Charizard",
  "move": {
    "nombre": "Lanzallamas",
    "potencia": 110,
    "tipo": "10",
    "categoria": "special",
    "statusEffect": "burn",
    "priority": 0,
    "selfStatChanges": [],
    "targetStatChanges": []
  }
}
```

### 5.2. Validación en el anfitrión

Antes de aceptar la intención, el anfitrión verifica:

1. **Fase**: `$this->phase === 'waiting_guest_intention'` (no hay intención fuera de turno).
2. **Actor**: `$dto->actorId === $this->actingRefId` (la intención corresponde al actor actual).
3. **Vivo**: el actor y el defensor están vivos en el `AgregadoBatalla`.
4. **Movimiento válido**: el movimiento indexado existe en los movimientos del actor (`$actor->pokemon()->moves()` lo contiene).
5. **Objetivo válido**: el defensor es un combatiente enemigo vivo.

Si cualquiera falla, se descarta la intención silenciosamente (no se rechaza al invitado con un error visible; se ignora y se espera de nuevo).

### 5.3. Serialización del "tu turno" (anfitrión → invitado)

Cuando el anfitrión notifica al invitado que es su turno:

```php
broadcast()->to("privado-batalla.{$codigo}", new TurnoInvitado(
    actorId: $actor->id(),
    actorNombre: $actor->nombre(),
    hpActual: $actor->hpActual(),
    hpMax: $actor->hpMax(),
    estado: $actor->estado()->value,
    movimientos: array_map(
        fn (MovimientoBatalla $m) => DTOMovimientoBatalla::desdeDominio($m)->toLivewire(),
        $actor->pokemon()->moves()->all()
    ),
    objetivosDisponibles: $this->buildTargetList($battle, $actor),
    round: $this->round,
));
```

El invitado recibe esto, pinta la UI de selección, y al elegir envía un `DTOAccionBatalla` de vuelta.

### 5.4. ¿Solo la intención del actor de turno?

**Sí, solo una.** El orquestador serializa y envía una intención por turno. El motor es secuencial: solo hay un `actingRefId` a la vez. El invitado nunca acumula intenciones; elige, envía, y espera al siguiente `TurnoInvitado`.

## 6. Consistencia síncrona

### 6.1. Garantía fundamental

El motor de batalla **nunca cambia de modelo**. Sea host, guest o IA, la resolución siempre pasa por `commitAction()` que:

1. Calcula y aplica daño (`ServicioEjecucionBatalla::calcularYAplicarDano`)
2. Aplica estado (`aplicarEstado`)
3. Aplica stat changes (`applyMoveStatChanges`)
4. Dispara efectos síncronos (recoil, items, habilidades)
5. Verifica debilitamientos
6. Consume acción (`consumeAction`)
7. Llama a `nextActor()`

Todo ocurre en **un solo request HTTP en la máquina del anfitrión**, exactamente como define `Propuesta_combate.md`.

### 6.2. Timeout del invitado (consistencia de disponibilidad)

Si el invitado no responde en un tiempo configurable (ej. 60 segundos):

1. Se dispara un timeout desde el anfitrión (un `setTimeout` en el cliente anfitrión o un timer en el componente).
2. Se ejecuta `commitAction()` con una **intención de "no acción"** o se fuerza la IA para ese turno:
   - Opción A: el anfitrión auto-genera una acción IA para el Pokémon del invitado (usando `SelectorAccionIA`).
   - Opción B: el Pokémon pierde su turno (como si estuviera dormido/paralizado) — `consumeAction()` sin ejecutar movimiento.
3. Se notifica al invitado que perdió su turno.

**Recomendación**: Opción A (auto-IA por timeout) para mantener el flujo de batalla sin interrupciones visuales. El invitado ve un log "Pokémon del rival actuó automáticamente" y la batalla continúa.

### 6.3. Concurrencia de requests

Como Livewire v3 ejecuta cada petición en un request separado, y la sesión se escribe con `session()->put()`, existe un riesgo de **race condition** si el invitado envía dos intenciones rápidamente (doble clic). Mitigaciones:

1. **Fase gate**: `receiveGuestIntention()` solo acepta intención si `phase === 'waiting_guest_intention'`. Al primer click exitoso, se cambia la fase a `resolving`, descartando cualquier segundo envío.
2. **Pending action gate**: si `$battle->pendingAction() !== null`, la intención se descarta.
3. **Lock de sesión**: si es necesario, usar un lock con `Cache::lock()` por `battleId` durante la resolución.

## 7. Casos límite y mitigaciones

### 7.1. Invitado se desconecta durante su turno

| Aspecto | Solución |
|---|---|
| **Detección** | El cliente del anfitrión detecta la pérdida de conexión del canal WebSocket (evento `pusher:disconnected` o heartbeat de Reverb). |
| **Acción inmediata** | Se activa un timeout (ej. 30s). Si el invitado reconecta en ese tiempo, re-suscribe al canal y se le reenvía el estado actual (`TurnoInvitado`). |
| **Si expira el timeout** | Se ejecuta la acción IA para el Pokémon del invitado (`SelectorAccionIA`). Se notifica a ambos "Turno automático por inactividad". La batalla continúa. |
| **Si se va definitivamente** | Opción de que el anfitrión declare victoria por abandono, o que la IA tome control del equipo invitado para el resto de la batalla. |

### 7.2. Intención duplicada o fuera de turno

| Escenario | Mitigación |
|---|---|
| Invitado envía intención dos veces rápido | Gate de fase: solo se acepta una intención por `waiting_guest_intention`. La segunda se descarta silenciosamente. |
| Invitado envía intención cuando no es su turno | Gate de fase: si `phase !== 'waiting_guest_intention'`, se descarta. |
| Invitado envía intención con `actorId` incorrecto | Validación de `actorId === $this->actingRefId`. Se descarta si no coincide. |
| Invitado envía movimiento que no tiene el actor | Validación de que el movimiento existe en `$actor->pokemon()->moves()`. Se descarta. |
| Invitado envía objetivo inválido (muerto, aliado) | Validación de que `$defenderId` corresponde a un combatiente enemigo vivo. Se descarta. |

### 7.3. Actor debilitado por daño residual antes de actuar

Este caso ya está contemplado en `Propuesta_combate.md` (regla de cancelación) y en el código actual:

- En `nextActor()` línea 209, se verifica `$actor->puedeActuar()` (sueño, hielo, etc.), pero **no** se verifica si el actor sigue vivo después de daño residual previo.
- **Gap detectado**: si un actor muere por daño de clima o estado en `triggerRoundEndEffects()` (fin de ronda), el `nextActor()` de la siguiente ronda lo obtendría igualmente de `GestorTurnos::getNextActor()` si `combatientesVivos()` no lo filtra correctamente.

**Mitigación** (ya parcialmente implementada): `GestorTurnos::combatientesVivos()` filtra por `estaVivo()`, y `triggerRoundEndEffects()` aplica el daño **antes** de `startNewRound()`, que a su vez es llamado antes de `getNextActor()`. Sin embargo, en PvP se debe añadir una **verificación explícita** al inicio de `nextActor()`:

```php
$actor = $battle->turnManager()->getNextActor();
if ($actor !== null && !$actor->estaVivo()) {
    $battle->turnManager()->consumeAction($actor);
    $this->nextActor();  // recursión controlada
    return;
}
```

Esto cubre el caso edge donde un actor muere por efecto entre turnos (recoil que lo dejó en 1 HP + daño de estado en fin de ronda).

### 7.4. Anfitrión cierra el navegador

| Consecuencia | Mitigación |
|---|---|
| El invitado pierde la batalla | No hay motor en el invitado; la batalla simplemente se pierde. |
| **Prevención** | Evento `beforeunload` en el navegador del anfitrión que emite un broadcast de abandono al canal. El invitado recibe notificación "El anfitrión abandonó la partida". |
| **Persistencia** | La sesión del anfitrión se pierde (SESSION_DRIVER=database puede persistir, pero al reconectarse el anfitrión tendría que reconstruir el componente). Por simplicidad, si el anfitrión se va, la batalla se cancela. |

### 7.5. Reconexión del invitado

Si el invitado se desconecta y reconecta antes del timeout:

1. El invitado re-suscribe al canal privado.
2. El anfitrión verifica que la fase sigue en `waiting_guest_intention`.
3. Reenvía el evento `TurnoInvitado` con el estado actual del actor.
4. El invitado continúa eligiendo normalmente.

No se requiere reconstruir estado en el invitado porque este **nunca tiene estado de batalla** — solo tiene el código de sala y el canal.

### 7.6. Ronda con múltiples actores seguidos del mismo equipo

`GestorTurnos` puede producir dos actores seguidos del mismo equipo (ej. dos Pokémon del invitado con alta velocidad acumulada). En PvP:

- **Caso 1**: dos Pokémon del invitado seguidos → `nextActor()` se llama dos veces, ambas veces `resolveTurnType()` devuelve `'guest'`. Se notifica al invitado dos veces, y este responde dos veces (una por turno). **No hay problema** porque la resolución es secuencial: se espera la primera intención, se resuelve, se llama `nextActor()` de nuevo, se espera la segunda.
- **Caso 2**: un Pokémon del anfitrión y luego otro del anfitrión → ambos son `'host'`, el anfitrión elige dos veces en su UI. **Comportamiento actual sin cambios.**
- **Caso 3**: host → guest → host → guest → alternado → cada transición es independiente. **Sin problema.**

### 7.7. El invitado envía intención para un Pokémon que ya se debilitó

**Mitigación**: en `receiveGuestIntention()`, antes de fijar `pendingAction`, se verifica que el actor sigue vivo:

```php
$actor = $battle->team1->findCombatantById($dto->actorId)
    ?? $battle->team2->findCombatantById($dto->actorId);
if ($actor === null || !$actor->estaVivo()) {
    // El actor fue debilitado (¿por daño residual?). Saltar su turno.
    $battle->turnManager()->consumeAction($actor);
    $this->saveBattle($battle);
    $this->nextActor();
    return;
}
```

### 7.8. Race condition: sesión PHP y requests concurrentes

Livewire v3 ejecuta cada petición en un hilo PHP independiente. Si el anfitrión y el invitado envían algo simultáneamente (ej. el anfitrión avanza ronda mientras el invitado envía una intención residual), la sesión puede corromperse.

**Mitigación**: usar un lock por `battleId` en todas las operaciones que lean/escriban el `AgregadoBatalla`:

```php
$lock = Cache::lock("battle_lock:{$this->battleId}", 10);
if (!$lock->get()) {
    // retry o ignorar
    return;
}
try {
    $battle = $this->getBattle();
    // ... toda la lógica ...
    $this->saveBattle($battle);
} finally {
    $lock->release();
}
```

Alternativamente, si se usa `SESSION_DRIVER=database`, el propio driver de sesión de Laravel ya serializa writes por sesión, pero esto protege contra requests del **mismo** usuario, no contra el invitado (que tiene su propia sesión). Como el invitado **nunca** escribe el `AgregadoBatalla` (solo lo lee), el lock se necesita solo en el anfitrión, donde todas las escrituras pasan por el mismo componente Livewire.

## 8. Riesgos

### 8.1. Riesgos técnicos

| Riesgo | Severidad | Mitigación |
|---|---|---|
| **Reverb no está configurado** | Alta | Requiere `laravel/reverb`, `BROADCAST_CONNECTION=reverb`, `REVERB_*` en `.env`, `laravel-echo` + `pusher-js` en Vite. Fase 3 del plan de implementación. |
| **Sesión no persiste entre requests del invitado** | Media | El invitado no necesita sesión de batalla; solo suscribe al canal. Pero el componente Livewire del invitado necesita un `battleId` y `codigo` en la URL para suscribirse. Esto se resuelve en la fase lobby. |
| **Timeout incorrecto** | Media | Si el timeout es muy corto, se activa la IA innecesariamente. Si es muy largo, la batalla se estanca. Valor razonable: 45-60s con countdown visible para ambos jugadores. |
| **Complicación del componente Combate.php** | Alta | `Combate.php` ya tiene 709 líneas con responsabilidades mixtas. Se recomienda extraer la lógica PvP a un trait (`CombatePvp`) o un orquestador separado (`OrquestadorBatallaPvp`) como propone el doc PvP. |
| **Serialización de sesión pesada** | Media | El `AgregadoBatalla` serializado con `serialize()` puede ser grande. Con `SESSION_DRIVER=database`, cada escritura es un UPDATE a la tabla de sesiones. No es un problema inmediato, pero merece monitoreo. |

### 8.2. Riesgos de producto

| Riesgo | Severidad | Mitigación |
|---|---|---|
| **Invitado percibe lag** | Media | El flujo es: invitado elige → envía WebSocket → anfitrión recibe → resuelve → difunde estado → invitado pinta. Esto agrega ~100-300ms vs. una batalla local. Aceptable para PvP por turnos. |
| **Invitado no entiende que es su turno** | Baja | UI clara con indicador visual (resplandor, botón activo, countdown) y notificación push si es posible. |
| **Desincronización visual** | Media | Si el anfitrión difunde estado y el invitado lo pinta, pero el componente Livewire del invitado tiene un re-render inesperado, podría mostrar estado antiguo. Mitigación: el invitado siempre refleja el último estado recibido, nunca calcula nada localmente. |

### 8.3. Riesgos de arquitectura

| Riesgo | Severidad | Mitigación |
|---|---|---|
| **Acoplamiento Combate.php ↔ PvP** | Alta | El componente Combate está creciendo. Separar `CombateIa` (actual) de `CombatePvp` (nuevo) o usar un strategy pattern para el `resolveTurnType` mantendría el código limpio. |
| **Estado en sesión vs. base de datos** | Baja | La propuesta mantiene el estado en la sesión del anfitrión. Esto es correcto para "autoridad única", pero implica que el anfitrión no puede perder la sesión. Con `SESSION_DRIVER=database`, esto ya está mitigado. |
| **Testing del flujo PvP** | Alta | Los tests actuales solo cubren IA. Se necesitan tests de integración con un mock de WebSocket para validar el flujo completo: notificación → intención → resolución. |
