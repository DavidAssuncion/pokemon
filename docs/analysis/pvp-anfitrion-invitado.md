# PvP Anfitrión–Invitado por Código — Análisis y Plan

## 1. Objetivo

Permitir que **dos jugadores** se enfrenten en tiempo real, donde:
- El **anfitrión** ejecuta y aloja el motor de batalla completo en su máquina.
- El **invitado** se une con un **código de batalla** corto y solo envía su intención (movimiento/objetivo), mientras **pinta** el estado que el anfitrión le difunde.

**Requisito duro:** todo el proceso (cálculo, estado, turnos, daño, estados) corre **exclusivamente en la máquina del anfitrión**. El invitado nunca ejecuta lógica del motor.

## 2. Contexto actual del código (verificado)

- El componente `App\Livewire\Combate` (`app/Livewire/Combate.php`) es el orquestador único: crea la batalla, avanza turnos y ejecuta el motor.
- El estado vive en la **sesión del cliente** vía `session($battleId)` (`getBattle()`/`saveBattle()`), con `SESSION_DRIVER=database`.
- `nextActor()` decide quién actúa con `$isPlayer = $actorView['team'] === 0`: asume que `team1` es siempre el humano local y `team2` la IA (`SelectorAccionIA`).
- Ruta: `Route::get('/combate', ...)` bajo middleware `auth` (`routes/web.php` línea 30).
- **No hay broadcasting configurado:** `.env` tiene `BROADCAST_CONNECTION=log`, no hay Reverb ni canales ni Echo en el bundle.

## 3. Arquitectura propuesta

### 3.1. Modelo de autoridad

```
[ANFITRIÓN — máquina que corre el motor]
   └─ Livewire Combate (orquestador + motor)
   └─ Estado: session($battleId)  ← única fuente de verdad
   └─ Registra la "sala" con código
   └─ Recibe intención del invitado vía Reverb
   └─ Resuelve el turno y difunde estado nuevo

[INVITADO — navegador remoto]
   └─ Entra con código de batalla
   └─ Envía su intención (DTOAccionBatalla) por WebSocket
   └─ Recibe el estado renderizado y lo pinta (cliente espejo)
```

### 3.2. Cambio clave en `nextActor()`

Hoy `nextActor()` asume `team===0` = humano local y `team===1` = IA. En PvP:
- Si el actor pertenece al **equipo del anfitrión** → el anfitrión elige (flujo actual `player_target`).
- Si el actor pertenece al **equipo del invitado** → el anfitrión **no** elige. Espera la intención del invitado vía Reverb antes de resolver.
- Si **no hay invitado conectado** → sigue el comportamiento actual con IA (modo vs IA intacto).

### 3.3. Estado central en la máquina anfitrión

- El estado **permanece** en `session($battleId)` del anfitrión (no se migra a BD compartida). Esto cumple "todo corre en la máquina anfitrión" y simplifica: no hay guardado distribuido.
- El invitado **no** persiste el `AgregadoBatalla`; solo guarda el `código` + un `sala_id` para suscribirse al canal.

### 3.4. Componentes nuevos

| Componente | Descripción | Archivo sugerido |
|---|---|---|
| **`SalaBatalla` / registro de sala** | Almacena en el anfitrión: código, `sala_id` (canal), estado de invitado (conectado/sin conectar), equipos confirmados. | `src/Battle/Infrastructure/SalaBatalla.php` o tabla `salas_batalla` |
| **Generador de código** | Código corto único por partida (ej. `ABC-123` o `7F3K`). | helper/random con colisión controlada |
| **Canal privado Reverb** | Canal `privado-batalla.{código}` por el que invitado envía intención y anfitrión difunde estado/log/anim. | `routes/channels.php` + `app/Broadcasting/` |
| **Clase `OrquestadorBatallaPvp`** | Encapsula la lógica de "qué intención esperar y cuándo resolver", reutilizando `AgregadoBatalla`/`GestorTurnos`/`ServicioEjecucionBatalla`. | `src/Battle/Application/OrquestadorBatallaPvp.php` |

### 3.5. Flujo completo

**Fase Lobby:**
1. Anfitrión pulsa **"Crear partida"** → genera `código` + `sala_id`, muestra el código.
2. Anfitrión (y luego invitado) **seleccionan su equipo** (3 Pokémon) y **confirman**.
3. Cuando ambos confirmaron, el anfitrión construye el `AgregadoBatalla` con ambos equipos (equipo anfitrión = `team1`, equipo invitado = `team2`) y arranca Ronda 1.

**Fase Turno (por actor, con `GestorTurnos`, que ya produce turnos por velocidad con posibles actores seguidos del mismo jugador):**
1. `nextActor()` decide el actor actual.
2. El anfitrión determina a qué jugador pertenece.
3. Si es del anfitrión → anfitrión elige en su UI → envía intención a sí mismo (local).
4. Si es del invitado → anfitrión **notifica al invitado "tu turno"** por Reverb y **espera**.
5. El invitado elige y envía `DTOAccionBatalla` por WebSocket.
6. El anfitrión recibe la intención, la aplica al motor, **resuelve** el turno completo (daño/estado/efectos) en un solo hilo.
7. El anfitrión difunde a ambos el nuevo estado + log + animación + quién sigue.

**Fase Fin:** cuando un equipo queda sin vivos, `AgregadoBatalla` declara ganador y se difunde a ambos.

## 4. Decisiones de producto (confirmadas)

- **Cada jugador selecciona su equipo antes de entrar** → lobby con selector + confirmar.
- **Turno por turnos con varios participantes por ronda, incluso seguidos** → lo resuelve `GestorTurnos` actual (prioridad → velocidad → tiebreak). No se cambia el motor.
- **Autoridad única:** el motor corre solo en el anfitrión; el invitado es cliente espejo.

## 5. Paquetes / configuración nueva

- **Laravel Reverb** (broadcasting por WebSocket) + `pusher` como driver. Requiere `composer require laravel/reverb` y config de `BROADCAST_CONNECTION=reverb`, `REVERB_*` en `.env`.
- **`routes/channels.php`** con autorización del canal privado por código de sala.
- **Frontend:** `laravel-echo` + `pusher-js` en el bundle Vite.
- Confirmar el stack usa Vite (`vite.config.js`) y `@vite` en layout `layouts.app`.

## 6. Fin de cara a la consistencia temporal (crítico)

- El motor del anfitrión resuelve cada turno **de forma síncrona en un solo hilo**, tal como define `Propuesta_combate.md`.
- El invitado **nunca** muta estado: solo manda intención y pinta. No puede "adelantarse" ni actuar con velocidad/estado desactualizado, porque la resolución siempre la hace el anfitrión tras aplicar todo.
- Se mantienen las reglas de oro: eventos síncronos, orden de aplicación fijo, efectos de fin/inicio antes de cada ronda, cancelación de actores debilitados por daño residual.

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Invitado sin conexión / inactividad | Timeout por turno (ej. 60s); opción de reemplazo por IA si se desconecta. |
| Tercero se una con el código | Canal privado autorizado por código + token de sesión; no exponer el motor. |
| Intención duplicada o fuera de turno | El orquestador del anfitrión **descarta** intenciones que no correspondan al actor actual; serializa por actor. |
| `APP_DEBUG=true` expuesto | Al usar Reverb/Tailscale (red privada o túnel), o si se expone, limitar; despliegues de prueba con cuidado. |
| Estado del invitado desincronizado | Solo el anfitrión escribe estado; el invitado solo rebote de lo que difunde el anfitrión. |

## 8. Plan de implementación (fases)

### Fase 1 — Infraestructura de sala y código (sin PvP aún)
1. `SalaBatalla` (registro de sala, código, equipos confirmados).
2. Generador de código único.
3. Pantalla básica "crear partida" y "unirse con código" + lobby con selector de equipo (reutilizando datos de Pokemon disponibles).
4. Al confirmar ambos, construir el `AgregadoBatalla` con `team1`/`team2` y arrancar.

### Fase 2 — Orquestador PvP (núcleo)
1. `OrquestadorBatallaPvp` reutilizando `AgregadoBatalla`, `GestorTurnos`, `ServicioEjecucionBatalla`, `CadenaDanio`.
2. En `nextActor()`: distinguir turno de anfitrión vs invitado vs IA (cuando no hay invitado).
3. Serializar intenciones: solo aceptar la del actor de turno; resolver en un hilo.

### Fase 3 — WebSockets (Reverb + Echo)
1. Instalar/configurar Reverb; `routes/channels.php` con canal privado por código.
2. Invitado envía `DTOAccionBatalla` por WebSocket.
3. Anfitrión difunde estado/log/anim/quién sigue a ambos.
4. Suscripción del invitado al canal y actualización visual (Livewire Broadcast o re-render del componente invitado).

### Fase 4 — Red y prueba
1. Servir en `0.0.0.0` en el anfitrión.
2. Conectar ambos lados vía Tailscale (red privada, HTTPS para `wss://`); ngrok solo para prueba corta.
3. Ajustar Vite/CORS para que el invitado alcance Reverb del anfitrión.
4. Probar end-to-end: crear partida → unirse → pick → batalla → fin.

### Fase 5 — Robustez
1. Timeout por turno y manejo de desconexión (reemplazo por IA / victoria por abandono).
2. Reconexión del invitado (re-suscribir y re-sincronizar estado desde el anfitrión).
3. Bloqueo si se intenta más de un invitado.

## 9. Fuera de alcance (por ahora)

- Matchmaking / lobby público (solo código directo).
- Guardado de partidas PvP históricas / replay.
- Chat entre jugadores.
- Revertir la arquitectura a "guardado central en BD compartida" (aquí el estado vive en el anfitrión).
