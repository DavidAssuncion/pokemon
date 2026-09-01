# PvP — Red y Tailscale (Host WSL2 + Invitado Remoto)

## 1. Objetivo

Definir la **capa de red** que permite a dos navegadores remotos conectarse al Laravel + WebSocket (Reverb) del anfitrión para jugar PvP por código. El anfitrión corre Laravel en **WSL2** sobre un host Windows; el invitado es un amigo remoto que **solo abre el navegador** (SO indistinto) y nunca ejecuta el motor (autoridad única en el anfitrión, según `docs/analysis/pvp-anfitrion-invitado.md`).

**Requisito de producto:** usar **Tailscale** (recomendado) sobre ngrok/Hamachi. Este documento detalla el esquema de red, TLS/`wss`, la configuración de Laravel/Reverb/CORS y los casos límite, todo basado en la configuración real del repo.

---

## 2. Topología actual verificada (WSL2 / docker / puertos)

Estado real del repo (verificado leyendo archivos):

- **`.env`** (el que se usa hoy): `APP_URL=http://localhost`, `APP_ENV=local`, `APP_DEBUG=true`, `BROADCAST_CONNECTION=log`, `SESSION_DRIVER=database`, `SESSION_DOMAIN=null`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`.
- **`docker-compose.yml`**: expone la app Apache en `"${APP_PORT:-80}:80"` y la BD en `"${DB_PORT:-5432}:5432"`. **Pero hoy la app NO corre por docker**: se ejecuta directamente en WSL2 (`composer run dev` / `php artisan serve` + workers de cola). El contenedor `app` está cableado con `APP_URL=http://localhost:${APP_PORT}`.
- **`config/app.php`**: `'url' => env('APP_URL', 'http://localhost')`, `APP_DEBUG` por `.env`.
- **`config/session.php`**: `driver=database`, `domain=null` (cookie válida en el host actual), `same_site=lax`.
- **`vite.config.js`**: build estático de Vite (laravel-vite-plugin + tailwind) con inputs `resources/css/app.css`, `app.js`, `combate.css`, `combate.js`; sin server dedicado (HMR opcional).
- **No hay Reverb todavía**: `composer.json` **no incluye** `laravel/reverb`; no existe `config/reverb.php`, no existe `routes/channels.php`, no existe `bootstrap/providers.php` con Reverb, y `resources/js/app.js` solo importa `bootstrap` + Bootstrap JS (sin `laravel-echo`/`pusher-js`).
- **`bootstrap/app.php`**: middleware vacío; nada de CORS custom (si no hay publicados `config/cors.php`, Laravel aplica los valores por defecto `allowed_origins = ['*']` en desarrollo web).
- **Proceso actual**: `composer run dev` levanta `php artisan serve` (HTTP 8000 por defecto) + `queue:listen` + `pail` + `npm run dev` bajo `npx concurrently`. (Al salir del bundle de Vite, el invitado solo necesita el build estático.)

**Resumen de puertos en juego hoy:**
| Servicio | Puerto | Notas |
|---|---|---|
| App HTTP (WSL2 directa, `serve`) | 8000 (default `artisan serve`) | o 80 si se usara docker |
| DB PostgreSQL | 5432 | solo local del anfitrión |
| Reverb WS (a instalar) | 8080 (default) | nuevo |

---

## 3. Esquema de red propuesto (IP / puertos HTTP + WS)

### 3.1. Servir en `0.0.0.0`

- **HTTP (Laravel):** `php artisan serve --host=0.0.0.0 --port=8000` para que no quede atado a `127.0.0.1` (el default de `artisan serve`). En WSL2, `0.0.0.0` liga a la interfaz de la distro WSL, por lo que también es alcanzable desde el **host Windows** vía la IP WSL (lo que Tailscale del anfitrión hará enrutable).
- **Reverb (WS):** `php artisan reverb:start --host=0.0.0.0 --port=8080`. Reverb debe exponerse en la misma interfaz para que el invitado conecte por WebSocket.
- **DB y cola:** quedan internas al anfitrión (y a WSL); no se exponen (no las necesita ni el host Windows ni el invitado).

### 3.2. Tailscale: IP privada estable

- Al unir la **distro WSL2** al tailnet (instalar el binario `tailscale` **dentro de WSL2**, `tailscale up`), el host WSL2 recibe una **IPv4 privada estable tipo `100.x.y.z`** del rango CGNAT `100.64.0.0/10`.
- Esa IP es **privada y estable**: no cambia con la IP pública del hogar, funciona detrás de NAT/CGNAT y sobre cualquier red local (es el túnel WireGuard de Tailscale).
- Los invitados (que instalan Tailscale en su máquina — la app en sí la abren en su navegador) pertenecen al **mismo tailnet** y ven esa IP `100.x.y.z` como una interfaz local enrutada.

### 3.3. Cómo resuelve el invitado el dominio/IP (MagicDNS vs IP fija)

Dos opciones, ambas compatibles:

- **MagicDNS (recomendado si el tailnet está configurado):** cada nodo residencial del tailnet recibe un nombre estable tipo `<nombre-host>` (o el `Name` del nodo). El invitado abre `http://<nombre-host-pokemon>/` y WebSocket apunta a `ws://<nombre-host-pokemon>:8080`. Ventaja: si el anfitrión reinicia WSL2 y cambia la IP `100.x`, el **nombre permanece igual** y el navegador del invitado no se rompe.
- **IP fija (fallback):** usar directamente la IP `100.x.y.z`: `http://100.x.y.z:8000` y `ws://100.x.y.z:8080`. Más simple de razonar, pero **si la IP cambia** hay que re-compartir la URL.

**Recomendación:** activar MagicDNS; compartir el hostname una sola vez y que sea estable a través de reinicios.

### 3.4. Puertos a abrir dentro del tailnet

| Dirección | Puerto | Quién abre |
|---|---|---|
| `0.0.0.0:8000` | HTTP app | servido por `artisan serve` en WSL2 |
| `0.0.0.0:8080` | WS Reverb | servido por `reverb:start` en WSL2 |
| `100.x` (Tailscale) | — | rango CGNAT, solo visible para nodos del tailnet |

> Los puertos **no** se abren al Internet público: Tailscale es una overlay privada. No hace falta tocar el router del host ni abrir puertos en el firewall de Windows salvo para permitir la salida de WireGuard.

---

## 4. TLS y `wss://` (opciones y recomendación)

Reverb, como todo WebSocket, exige cifrar por TLS si la página se sirve por `https://` (los navegadores **bloquean `ws://` mixto desde una página `https`**). Opciones:

### Opción A — `ws://` plano dentro del tailnet (sin TLS)
- Servir la app en `http://` y el WS en `ws://` aprovechando que **la red Tailscale ya es privada y está cifrada por WireGuard** (el túnel Tailscale cifra todo el tráfico, incluido HTTP/WS).
- **Requiere:** que el navegador del invitado cargue la página por `http://` (no `https`). Como la app es de desarrollo (`APP_DEBUG`, proyecto local), es viable.
- **Pros:** cero gestión de certificados (Tailscale/ACME/Caddy) → lo más simple y robusto.
- **Contras:** ninguna intrínseca si nunca publicamos la IP a Internet; el cifrado real lo aporta WireGuard. No apto para exponer con ngrok/dominios públicos.

### Opción B — `wss://` con TLS en el tailnet (Tailscale HTTPS + proxy)
- Pedir un certificado a **Tailscale ACME** (por nodo con `tailscale cert <host>`) y terminar TLS con un proxy inverso (Caddy o `tailscale serve`/`httproxy`).
- Caddy hace **reverse proxy** sobre `https://<host>:443` → app `http://localhost:8000`, y un segundo bloque para `wss://<host>:443` → Reverb `http://localhost:8080` (Caddy termina TLS y proxya a WS).
- **Pros:** navegador siempre seguro, `wss://` correcto desde cualquier origen, cookies `Secure` posible.
- **Contras:** más piezas (proxy + cert + conf), y el certificado Tailscale requiere MagicDNS + funcionalidad ACME habilitada.

### Recomendación
Para este caso (proyecto local, red privada Tailscale, invitado que solo abre el navegador) → **Opción A: `ws://` sin TLS sobre la red Tailscale**, manteniendo la página en `http://`.

- Es la **más simple**: dos comandos (`serve` + `reverb:start`) y sin proxy.
- Es **segura** dentro del tailnet: WireGuard cifra el transporte; nadie fuera del tailnet puede alcanzar `100.x.y.z`.
- Permite dejar TS/ACME/Caddy para más adelante si algún día se publica la app a Internet.

> Nota de fragmentación mixta: como la página se sirve en `http://`, el WS en `ws://` es permitido por el navegador (no hay protocolo mixto bloqueante). Si se decidiera servir HTTPS, habría que migrar a Opción B.

---

## 5. Config Laravel / Reverb / CORS para el invitado

### 5.1. Instalación (estado actual: no instalado)
```
composer require laravel/reverb
composer require pusher/pusher-php-server
npm install laravel-echo pusher-js
```
Luego:
```
php artisan vendor:publish --tag=reverb-config       # crea config/reverb.php
php artisan reverb:keys --force                      # genera REVERB_APP_ID/KEY/SECRET
php artisan broadcast:install                        # crea routes/channels.php y configura Echo
```
Añadir `App\Providers\BroadcastServiceProvider` a `bootstrap/providers.php`.

### 5.2. `.env` del anfitrión (horizontal de la URL)
```
APP_URL=http://<host-tailscale>:8000          # o http://100.x.y.z:8000 si no usas MagicDNS

# Broadcasting -> Reverb (driver pusher)
BROADCAST_CONNECTION=reverb
BROADCAST_DRIVER=reverb
PUSHER_APP_ID=<id de reverb:keys>
PUSHER_APP_KEY=<key de reverb:keys>
PUSHER_APP_SECRET=<secret de reverb:keys>
PUSHER_HOST=127.0.0.1
PUSHER_PORT=8080
PUSHER_SCHEME=http

# Reverb server (qué y dónde levanta el servidor WS)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_APP_ID=<id>
REVERB_APP_KEY=<key>
REVERB_APP_SECRET=<secret>
REVERB_APP_HOST=<host-tailscale>              # hostname o IP que verá el invitado
REVERB_APP_PORT=8080
REVERB_APP_SCHEME=http                          # ws://  (no https)
REVERB_ALLOWED_ORIGINS=*
```

**Mapeo de "host" que llega al invitado:**
- `REVERB_SERVER_HOST/PORT` = a qué interfaz/puerto **escucha** Reverb en WSL2 → `0.0.0.0:8080`.
- `REVERB_APP_HOST/PORT` (y `APP_URL`) = **qué dirección publica/construye el SDK del invitado** para conectar → el hostname `100.x`/`http(s)://`.
- Este es el punto que **horizontaliza el host**: el servidor escucha en `0.0.0.0`, el **invitado** es alcanzable y recibe `ws://<host>:8080`.

### 5.3. Cliente (Echo) en `resources/js/bootstrap.js`
```
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,     // <host-tailscale>
    wsPort: import.meta.env.VITE_REVERB_PORT,     // 8080
    enabledTransports: ['ws'],                    // no wss
});
```
Y en `.env` (del lado host, para que Vite lo inyecte en el build):
```
VITE_REVERB_APP_KEY=<key>
VITE_REVERB_HOST=<host-tailscale>
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### 5.4. CORS
- La app se sirve a sí misma y el invitado usa el mismo origen (el buscador del invitado pide la página **al mismo host** del que saca el WS). Si ambos comparten `<host>:8000`, **no hay cross-origin** real y los defaults de Laravel (`allowed_origins *` al publicar sin config custom) bastan.
- **Ajuste útil** si en algún momento la página y el WS vienen de orígenes distintos (p. ej. página `http://host:8000` y `REVERB_APP_HOST` distinto): publicar y editar `config/cors.php`:
```
'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://<host>:8000'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```
- **WebSocket no usa CORS de HTTP** pero sí el endpoint `broadcasting/auth` (POST con las credenciales del canal privado) y `sanctum/csrf-cookie`, que sí pasan por CORS HTTP; por eso se enumeran en `paths`.
- Recordar que el invitado en `http://` no envía cookies `Secure`; mantener `SESSION_SECURE_COOKIE` vacío en esta fase.

### 5.5. Proceso del anfitrión (arranque)
```
php artisan serve --host=0.0.0.0 --port=8000 &
php artisan reverb:start --host=0.0.0.0 --port=8080 &
php artisan queue:listen &
```

---

## 6. Pasos de puesta en marcha

1. **Tailscale dentro de WSL2** del host: instalar el binario en la distro WSL, `tailscale login`, `tailscale up`. Anotar `tailscale ip -4` (p. ej. `100.x.y.z`) y, si se usa MagicDNS, `tailscale status` para el `Name`.
2. **Invitado:** instalar Tailscale en su SO y `tailscale up` en el **mismo tailnet** (compartir la cuenta / `tailscale up --authkey=`).
3. **Verificar alcance** desde el invitado: `ping <host-tailscale>` y abrir en su navegador `http://<host>:8000`.
4. **Instalar Reverb + Echo** (pasos de §5.1) y configurar `.env` (§5.2) + `bootstrap.js` (§5.3).
5. **Arrancar** servidor de app + Reverb + cola (§5.5), ambos ligados a `0.0.0.0`.
6. **Build estático para el invitado:** como el invitado no corre Vite, ejecutar `npm run build` en el host y servir los activos desde `public/build` (Apache/`serve`). El invitado **no necesita** HMR ni Vite dev server.
7. **Crear la partida** desde el navegador del anfitrión, difundir el código y comprobar que el invitado se suscribe al canal `privado-batalla.{código}` y recibe el estado.

---

## 7. Casos límite y mitigaciones

| Caso límite | Mitigación |
|---|---|
| **Cambio de IP Tailscale** (`100.x` cambia tras `tailscale up`/reinicio) | Usar **MagicDNS**: compartir el hostname, no la IP. Re-emitir URL solo si el nombre cambia. El flujo en `REVERB_APP_HOST`/`APP_URL` se hace cambiando `.env` una vez en el host (la IP de escucha `0.0.0.0` no cambia). |
| **Reinicio de WSL2** | `wsl --shutdown` reinicia la distro: `tailscaled` y todos los procesos PHP mueren. Debe reiniciarse Tailscale (`tailscale up`) y relanzar `serve`/`reverb:start`/`queue:listen`. La IP `100.x` suele reasignarse estable pero puede variar; MagicDNS amortigua. Considerar SystemD (WSL) o un script de arranque. |
| **Firewall del host Windows** | Tailscale crea su propia interfaz `tun`; permitir la salida UDP de WireGuard (41641/UDP) en el firewall de Windows. No hace falta abrir 8000/8080 al público (solo al tailnet). |
| **Puerto 8000/8080 ocupado** | `artisan serve` errorea "Address already in use"; cambiar puerto con `--port` (y reflejarlo en `APP_URL`, `REVERB_APP_PORT`, VITE). Elegir puertos altos no usados (ej. 8100/8081). |
| **Invitado sin Tailscale / en otra máquina** | Sin pertenecer al tailnet no hay reach; insistir en `tailscale up` del invitado en el mismo tailnet (es el "friend-to-friend" que pide Tailscale). |
| **`APP_DEBUG=true` expuesto** | En red tailnet privada el riesgo es bajo, pero por higiene considerar `APP_DEBUG=false` cuando se juegue con el invitado para no filtrar trazas. |
| **Cookie de sesión del invitado** | `SESSION_DOMAIN=null` y `http://` → cookie válida en el host de origen; no usar `same_site=none` ni `Secure` salvo migrar a HTTPS. |
| **Confusión `ws://` vs `wss://`** | Página en `http://` + Echo con `enabledTransports:['ws']` + `REVERB_APP_SCHEME=http`. Si se cambia a HTTPS hay que pasar a la Opción B (TLS) o el navegador bloquea el WS mixto. |
| **Reconección del invitado** | Reverb recompone la conexión; el anfitrión debe re-difundir estado al re-suscribirse (re-sync), según §5 de `pvp-anfitrion-invitado.md`. |

---

## 8. Riesgos

1. **Confundir IP de escucha con IP publicada** (`REVERB_SERVER_HOST` vs `REVERB_APP_HOST`): la causa nº1 de "el invitado no conecta"; el servidor escucha en `0.0.0.0`, el invitado recibe la dirección del tailnet.
2. **Fragmentación mixta `http`/`ws` vs `https`/`wss`:** decidir TLS desde el inicio; migrar después es costoso y rompe los transportes de Echo si se olvida.
3. **Dependencia de la instancia WSL2 viva:** la máquina anfitrión debe permanecer encendida y con Tailscale/WSL activos durante toda la partida; un `wsl --shutdown` o suspensión del portátil corta la partida.
4. **Requerimiento de instalar Tailscale en el invitado:** aunque el invitado "solo abre el navegador", **debe** tener Tailscale en su SO para formar parte del tailnet; sin Tailscale instalado en la máquina invitada, la Opción A es imposible (sería necesario ngrok, no recomendado).
5. **Reverb aún no instalado en el repo** (`composer.json` no lo incluye): toda la fase de WebSockets (§3 de `pvp-anfitrion-invitado.md`) es trabajo pendiente; este documento asume su instalación y configuración previas.
6. **`APP_DEBUG=true` y puertos expuestos:** aunque el tailnet es privado, cualquier fuga de la IP (share de URL) entregada a un tercero malintencionado del tailnet expondría trazas de debug; limitar en partidas "serias".
7. **Complejidad de MagicDNS/ACME (Opción B):** si se persigue `wss://`, añade piezas (Caddy/`tailscale cert`, dominio) y superficie de fallo; solo justificable si se publica fuera del tailnet.
