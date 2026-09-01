# PvP — Reverb y Livewire Broadcasting

## 1. Objetivo

Diseñar la capa de comunicación en tiempo real (Reverb + broadcast) para que un **invitado remoto** pueda unirse a una batalla PvP cuyo motor corre exclusivamente en la máquina del **anfitrión**. El invitado envía su intención (movimiento + objetivo) por WebSocket y recibe el estado resuelto para pintarlo como cliente espejo.

---

## 2. Estado actual verificado

### 2.1 Lo que SÍ existe

| Elemento | Ubicación | Detalle |
|---|---|---|
| Laravel 12.67.0 | `composer show` | Versión actual |
| Livewire 4.4.2 | `composer show` | Sin soporte de broadcast nativo (`#[On]` no existe en v4) |
| Componente `Combate` | `app/Livewire/Combate.php` | 709 líneas; orquesta batalla, lee/escribe `session($battleId)` vía `getBattle()`/`saveBattle()` |
| Persistencia en sesión | `.env` → `SESSION_DRIVER=database` | Estado del `AgregadoBatalla` serializado en la tabla `sessions` |
| Motor de turnos | `Combate::nextActor()` (línea 175) | Decide actor vía `$isPlayer = $actorView['team'] === 0` → team1=humano, team2=IA |
| Vite | `vite.config.js` | Entry points: `app.js`, `combate.js`, CSS |
| JS bundle | `resources/js/bootstrap.js` | Importa Livewire+Alpine, los arranca con `Livewire.start()` |
| Layout | `resources/views/layouts/app.blade.php` | Usa `@vite`, `@livewireStyles`, `@livewireScriptConfig` |

### 2.2 Lo que NO existe (confirmado por comandos)

| Elemento | Verificación |
|---|---|
| `laravel/reverb` | `composer show --direct \| grep reverb` → vacío |
| `config/broadcasting.php` | `glob` → no encontrado |
| `config/reverb.php` | `glob` → no encontrado |
| `routes/channels.php` | `glob` → no encontrado; `bootstrap/app.php` solo registra `web`, `commands`, `health` |
| `laravel-echo` / `pusher-js` | `package.json` → no están en `dependencies` ni `devDependencies` |
| `BROADCAST_CONNECTION` | `.env` línea 36 → `log` |
| Cualquier evento broadcast | Sin clases en `app/Events/` |

**Conclusión:** el broadcasting está en cero. Requiere instalación completa de infraestructura.

---

## 3. Arquitectura de canales y eventos

### 3.1 Canal privado

```
Privado: batalla.{codigo}
```

- `routes/channels.php` autoriza el canal verificando que el usuario esté autenticado (`Auth::check()`) y que el `codigo` coincida con una `SalaBatalla` activa en la que el usuario es anfitrión o invitado registrado.
- El código de sala actúa como secreto de corto alcance (ej. `ABC-123`); no se expone el `battleId` interno del anfitrión.

**Autorización en `routes/channels.php`:**

```php
use App\Models\SalaBatalla;

Broadcast::channel('batalla.{codigo}', function ($user, string $codigo) {
    $sala = SalaBatalla::where('codigo', $codigo)
        ->where('estado', 'activa')
        ->first();

    if ($sala === null) {
        return false;
    }

    // Anfitrión o invitado registrado
    if ($sala->user_anfitrion_id === $user->id) {
        return ['id' => $user->id, 'rol' => 'anfitrion', 'nombre' => $user->name];
    }

    if ($sala->user_invitado_id === $user->id) {
        return ['id' => $user->id, 'rol' => 'invitado', 'nombre' => $user->name];
    }

    return false;
});
```

### 3.2 Eventos broadcast

| Evento | Quién emite | Dirección | Payload |
|---|---|---|---|
| `IntencionEnviada` | Invitado (vía Livewire action → broadcast) | Invitado → Reverb → Anfitrión | `{ codigo, actorId, defenderId, moveNombre, attackerNombre }` |
| `EstadoBatallaActualizado` | Anfitrión (tras resolver turno) | Anfitrión → Reverb → ambos | `{ team1, team2, log, phase, round, weather, actingRefId, anim }` |
| `TurnoDelInvitado` | Anfitrión (cuando `nextActor()` detecta actor del equipo invitado) | Anfitrión → Reverb → Invitado | `{ actorId, moves[] }` — disponibles para ese actor |
| `BatallaFinalizada` | Anfitrión | Anfitrión → Reverb → ambos | `{ winner, log }` |
| `InvitadoConectado` | Anfitrión (post-suscripción) | Anfitrión → canal | `{ nombre }` — para que el anfitrión sepa que el invitado está online |

**Clases de eventos:** cada uno extiende `Illuminate\Broadcasting\Broadcastable` con `broadcastAs()` apuntando a un namespace privado, y `broadcastOn()` retorna `new PrivateChannel("batalla.{$codigo}")`.

---

## 4. Patrón de integración: invitado/anfitrión

### 4.1 Decisión: **Híbrido — JS listener + Livewire en ambos lados**

**Justificación:**

Livewire 4 no tiene directiva `#[On]` como v2/v3. La integración broadcast en v4 requiere escucha manual vía Echo en JS + dispatching a Livewire. Para un PvP es la opción correcta porque:

- El **anfitrión** ya tiene `Combate.php` (709 líneas) con toda la lógica de motor. No se duplica; solo se le añaden 2 métodos: uno para recibir `IntencionEnviada` del invitado y otro para difundir estado tras cada resolución.
- El **invitado** necesita un componente Livewire nuevo (`CombateInvitado`) que sea de solo lectura + envío de intención. La escucha del broadcast se hace en JS (`resources/js/pvp-listener.js`) y llama a `$wire.set(...)` o `$wire.call(...)` del componente vía `Livewire.find()`.
- No hay necesidad de un engine duplicado en el lado del invitado. El componente invitado es un espejo de props.
- La comunicación `invitado → anfitrión` (intención) pasa por un método Livewire del anfitrión invocado vía HTTP normal (o también por broadcast), no por WebSocket directo, para que el anfitrión valide y aplique al motor en un contexto HTTP seguro con sesión.

### 4.2 Flujo detallado

```
INVITADO                          REVERB                     ANFITRIÓN
   │                                │                           │
   │── suscribe.batalla.{cod} ─────>│                           │
   │                                │── autoriza ──────────────>│
   │                                │<── join ok ──────────────│
   │                                │                           │
   │<── TurnoDelInvitado ───────────│<── nextActor() detecta ──│
   │    (actorId, moves[])          │    team===invitado        │
   │                                │                           │
   │   [invitado elige movimiento]  │                           │
   │── $wire.enviarIntencion() ────>│── POST /pvp/intencion ──>│
   │   (Livewire method HTTP)       │   (anfitrión recibe)      │
   │                                │                           │
   │                                │<── resuelve turno ───────│
   │                                │    (motor completo)       │
   │                                │                           │
   │<── EstadoBatallaActualizado ───│<── broadcast(event) ─────│
   │    (JS listener → $wire.set)   │                           │
```

### 4.3 Por qué NO es 100% broadcast puro

La intención del invitado se envía vía **POST HTTP** a un endpoint Livewire del anfitrión (`/pvp/intencion`) en lugar de por broadcast:

1. **Seguridad:** el anfitrión necesita validar la sesión, el código de sala y que efectivamente sea el turno del invitado. Un evento broadcast entrante no tiene contexto HTTP de sesión Laravel.
2. **Serialización atómica:** el anfitrión recibe la intención, la aplica al motor y resuelve todo en un solo hilo de ejecución PHP. No hay carrera entre recibir y resolver.
3. **Compatibilidad:** funciona sin Reverb también (fallback HTTP), y facilita debugging.

El canal WebSocket se usa **exclusivamente para difusión anfitrión → ambos** (estado, turno, logs, fin).

### 4.4 Componente `CombateInvitado` (nuevo)

```
app/Livewire/CombateInvitado.php
```

- Props: `$codigo`, `$team` (1, ya que el invitado es team2), `$team1[]`, `$team2[]`, `$log[]`, `$phase`, `$round`, `$weather`, `$actingRefId`, `$anim*`.
- Método `enviarIntencion(string $actorId, string $defenderId, array $moveData)` → POST al anfitrión.
- **No tiene** `getBattle()`, `saveBattle()`, `commitAction()`, ni `nextActor()`. Solo recibe y pinta.
- Se inicializa escuchando el canal vía JS en `mount()`.

---

## 5. Instalación y configuración (pasos concretos)

### Paso 1: Reverb

```bash
composer require laravel/reverb
php artisan reverb:install --no-interaction
```

Esto genera `config/reverb.php`, las migraciones de Reverb y actualiza `.env`.

### Paso 2: Variables de entorno (`.env`)

```ini
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

En producción (Tailscale), `REVERB_HOST` apunta al hostname Tailscale del anfitrión y `REVERB_SCHEME=https`.

### Paso 3: `config/broadcasting.php`

Creado automáticamente por `reverb:install`. Verificar que el driver sea:

```php
'reverb' => [
    'driver' => 'reverb',
    'app_id' => env('REVERB_APP_ID'),
    'app_key' => env('REVERB_APP_KEY'),
    'app_secret' => env('REVERB_APP_SECRET'),
    'host' => env('REVERB_HOST', 'localhost'),
    'port' => env('REVERB_PORT', 8080),
    'scheme' => env('REVERB_SCHEME', 'http'),
],
```

### Paso 4: `bootstrap/app.php` — registrar canales

Añadir la línea `channels` al `withRouting`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    channels: __DIR__.'/../routes/channels.php',  // ← NUEVO
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### Paso 5: Crear `routes/channels.php`

```php
use Illuminate\Support\Facades\Broadcast;
use App\Models\SalaBatalla;

Broadcast::channel('batalla.{codigo}', function ($user, string $codigo) {
    $sala = SalaBatalla::where('codigo', $codigo)
        ->where('estado', 'activa')
        ->first();

    if ($sala === null) {
        return false;
    }

    if ($sala->user_anfitrion_id === $user->id) {
        return ['id' => $user->id, 'rol' => 'anfitrion', 'nombre' => $user->name];
    }

    if ($sala->user_invitado_id === $user->id) {
        return ['id' => $user->id, 'rol' => 'invitado', 'nombre' => $user->name];
    }

    return false;
});
```

### Paso 6: Frontend — Echo + Pusher en el bundle

```bash
npm install laravel-echo pusher-js --save
```

En `resources/js/bootstrap.js`, añadir al final:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME === 'https'),
    enabledTransports: ['ws', 'wss'],
});
```

### Paso 7: Variables Vite en `.env`

```ini
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Paso 8: JS listener para el invitado (`resources/js/pvp-listener.js`)

```js
export function escucharCanalBatalla(codigo, wireComponent) {
    const canal = window.Echo.private(`batalla.${codigo}`);

    canal.listen('.EstadoBatallaActualizado', (e) => {
        wireComponent.set('team1', e.team1);
        wireComponent.set('team2', e.team2);
        wireComponent.set('log', e.log);
        wireComponent.set('phase', e.phase);
        wireComponent.set('round', e.round);
        wireComponent.set('weather', e.weather);
        wireComponent.set('actingRefId', e.actingRefId);
        // anim props...
    });

    canal.listen('.TurnoDelInvitado', (e) => {
        wireComponent.set('currentMoves', e.moves);
        wireComponent.set('phase', 'player_target');
    });

    canal.listen('.BatallaFinalizada', (e) => {
        wireComponent.set('phase', 'battle_over');
        wireComponent.set('log', e.log);
    });

    return canal;
}
```

En el componente `CombateInvitado`, invocar esto desde el front vía `@script` o Alpine init.

### Paso 9: Crear eventos de broadcast

```bash
php artisan make:event EstadoBatallaActualizado
php artisan make:event TurnoDelInvitado
php artisan make:event IntencionEnviada
php artisan make:event BatallaFinalizada
```

Cada evento implementa `ShouldBroadcast`, su `broadcastOn()` retorna `new PrivateChannel("batalla.{$this->codigo}")`, y `broadcastAs()` retorna nombre limpio (ej. `.EstadoBatallaActualizado`).

### Paso 10: Añadir al bundle Vite

En `vite.config.js`, añadir el entry point:

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/combate.css',
    'resources/js/combate.js',
    'resources/js/pvp-listener.js',  // ← NUEVO
],
```

---

## 6. WSS/HTTPS/CORS

### 6.1 HTTPS obligatorio para WSS

WebSocket seguro (`wss://`) requiere TLS. En la red local con Tailscale:

- Tailscale emite certificados internos automáticamente vía HTTPS. Configurar `REVERB_SCHEME=https` y `REVERB_HOST=tailscale-hostname.ts.net`.
- Reverb debe escuchar en `0.0.0.0:8080` y estar detrás del proxy TLS de Tailscale (o usar el puerto 443).
- `APP_URL` debe cambiar a `https://hostname.ts.net` para que las cookies de sesión (incluida la de auth) se envíen por HTTPS.

### 6.2 CORS

El invitado remoto carga la app del anfitrión (misma URL base) y se conecta a Reverb del anfitrión. Al ser misma URL, CORS no aplica. **Sin embargo**, si el invitado accede desde un dominio diferente (ej. ngrok):

- En `config/cors.php`, añadir el dominio del invitado a `allowed_origins`.
- O mejor: si ambos cargan la app desde el mismo host del anfitrión (vía Tailscale/HTTP), no hay problema de CORS. El JS del invitado corre en el contexto del dominio del anfitrión.

### 6.3 Servir en `0.0.0.0`

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Reverb ya escucha en `0.0.0.0:REVERB_SERVER_PORT`. Ambos accesibles desde Tailscale.

### 6.4 Cookies y sesión cross-domain

Si el invitado accede desde otra IP pero mismo dominio Tailscale, las cookies de `SESSION_DOMAIN=null` se envían correctamente. Verificar que `SESSION_DRIVER=database` permite que ambos usuarios (anfitrión e invitado) tengan sesión en la misma BD (ya es el caso).

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| **Livewire 4 sin `#[On]`** | Escucha JS manual vía Echo + `Livewire.find(componentId).set(...)` en `pvp-listener.js`. Funciona, es documentado por la comunidad. |
| **Invitado sin sesión del anfitrión** | El componente `CombateInvitado` no necesita la sesión del anfitrión. Solo necesita su propia sesión de auth para suscribirse al canal privado. El estado vive exclusivamente en `session($battleId)` del anfitrión. |
| **Carrera de turnos (invitado envía acción fuera de turno)** | El endpoint `POST /pvp/intencion` en el anfitrión valida: (1) que el `actorId` coincida con `actingRefId`, (2) que el componente esté en fase `waiting_for_guest`. Intenciones fuera de turno se descartan con 409. |
| **Desconexión del invitado** | Timeout de 60s en el anfitrión. Si no llega intención, el anfitrión sustituye por IA (flujo `SelectorAccionIA` existente) o declara victoria por abandono. |
| **Reverb sin Redis** | Reverb puede funcionar con su propio servidor WebSocket embebido. No requiere Redis si solo hay 2 jugadores. Para escalabilidad futura, considerar `REVERB_PRESENCE_REDIS`. |
| **Vite no incluye `pvp-listener.js`** | Verificar que `@vite(['resources/js/pvp-listener.js'])` esté en la vista del componente invitado, o importarlo desde `combate.js` condicionalmente. |
| **Sesión serializada incompatible** | El `SESSION_VERSION` en `Combate.php` (línea 107) ya maneja versionado. Si la estructura de `AgregadoBatalla` cambia, se incrementa la versión y se descarta la sesión antigua. |
| **`APP_DEBUG=true` en Tailscale** | No exponer a internet público. Tailscale es red privada; `APP_DEBUG=true` es aceptable en red local de desarrollo. Para producción, desactivar. |
| **Invitado manipula estado local** | Imposible: el componente invitado no tiene motor. Solo pinta lo que el anfitrión difunde. La autoridad es 100% el anfitrión. |
