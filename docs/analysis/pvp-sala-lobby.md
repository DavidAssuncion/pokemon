# PvP — Sala, Código de Unión y Lobby

## 1. Objetivo

Diseñar el **front-office** del PvP anfitrión-invitado propuesto en `docs/analysis/pvp-anfitrion-invitado.md`, manteniendo la arquitectura de autoridad única: **todo el estado de la batalla corre en la máquina del anfitrión** (`session($battleId)`), y el invitado solo envía intención y pinta lo difundido.

Este documento cubre las tres piezas efímeras previas a la batalla:
- La **sala** (registro que vincula a dos jugadores y su código).
- El **código de unión** corto.
- El **lobby** de selección/confirmación de equipo (3 Pokémon por jugador).

## 2. Estado actual de selección de equipo (verificado)

Ya existe cadena completa para construir un `AgregadoBatalla` desde equipos reales de la BD. No hay que inventar el pipeline de batalla; hay que **reutilizarlo**:

- **Origen de los Pokémon disponibles** → `Team` + `TeamMember` + `Reclutado` en `app/Models/`, y el `Reclutado` apunta al catálogo `Pokemon`:
  - `app/Models/Team.php` (belongs-to-user, `members()` hasMany).
  - `app/Models/TeamMember.php` (`team_id`, `pokemon_id` → `reclutados.id`, `slot`, `behavior`). Nota: el FK de `team_members.pokemon_id` referencia a **`reclutados`**, no a `pokemon`.
  - `app/Models/Reclutado.php` (`pokemon_id` → `Pokemon`, nombre, `es_shiny`, `obj_equipados`, `movimientos`).
- **Mapeo BD → motor** → `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php` (`desdePokemon(): DatosPokemonBatalla`), que genera los movimientos a partir de los tipos (`GeneradorMovimientosTipo`).
- **Ensamblado del equipo del jugador** → `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` (`desdeEquipo(Team $equipo, array $formacion): list<DatosPokemonBatalla>`), aplica posición vanguardia/retaguardia por slot con `ClasificadorPosicion`.
- **Equipo al `AgregadoBatalla`** → `src/Battle/Domain/EquipoBatalla.php::fromData($datos, $name)` y luego `new AgregadoBatalla($team1, $team2)`, como hace `src/CombateEntrenadores/App/IniciarCombateEntrenador.php:49-60`.
- **UI actual de selector de equipo** → `resources/views/habitats/show.blade.php`: el jugador selecciona un `Team`, abre un modal de formación (toggle vanguardia/retaguardia por slot), y `confirmarCombate()` llama al backend, que devuelve `battle_id`, y se redirige a `/combate?battle_id=...` (líneas 858-881). El componente `App\Livewire\Combate` (`app/Livewire/Combate.php`) monta y carga la batalla desde `session($battleId)`.
- **No hay PvP ni sala ni código hoy.** Solo el modo contra IA/entrenador con `team1`=humano y `team2`=IA/mock (`FabricaBatallaMock`). `nextActor()` asume `$isPlayer = $actorView['team'] === 0` (Combate.php:236).

**Conclusión:** el lobby solo necesita **elegir 3 `Reclutado`** (a partir de los `Team` del jugador) y **posiciones**, para luego delegar en `ConstruirEquipoJugador`/`MapeadorPokemonBatalla`/`EquipoBatalla::fromData` existentes. No se replica lógica del motor en el lado de la sala.

## 3. Modelo de datos de la sala (recomendación)

**Recomendación: tabla persistida `salas_batalla` (nueva migración) para el REGISTRO / pacto de la sala; el `AgregadoBatalla` NO se persiste en la sala, sigue en `session` del anfitrión.**

Justificación:

1. **El invitado necesita descubrir la sala.** El invitado es otra máquina/otro `session` y otro `user_id`; no comparte sesión con el anfitrión. Una clase en memoria del anfitrión es invisible para el invitado y no sobrevive a la recarga del anfitrión.
2. **El `SESSION_DRIVER=database` ya existe** pero es por-sesión-cliente; no sirve para compartir estado entre anfitrión e invitado. La sala es el **único estado compartido EFÍMERO** que justifica tocar BD.
3. **Seal del estado de batalla intacto**: la sala guarda solo el **pacto** (quién, código, equipos confirmados, estado) — jamás el `AgregadoBatalla` ni el estado del motor. Eso preserva "todo corre en el anfitrión" (Requisito duro de la propuesta).

**Qué se persiste sí / no:**

| Dato | ¿Persistir en `salas_batalla`? | Por qué |
|---|---|---|
| `codigo` | ✅ | Clave de unión del invitado, índice único. |
| `canal` (`sala_id` Reverb) | ✅ | Identifica el canal privado; derivable del código, pero guardarlo explícitamente evita derivaciones ambiguas. |
| `anfitrion_user_id` | ✅ | Para autorizar (solo el dueño gestiona la sala) y para el canal privado. |
| `invitado_user_id` (nullable) | ✅ | Caso "sala llena" y ready-status. |
| `estado` (enum) | ✅ | State machine: `esperando_invitado → seleccionando → batalla_lista → en_batalla → finalizada/cerrada/vencida`. |
| `equipo_anfitrion_confirmado` (bool) | ✅ | Ready del anfitrión. |
| `equipo_invitado_confirmado` (bool) | ✅ | Ready del invitado. |
| Los 3 Pokémon/posiciones de cada uno | ⚠️ parcheado (ver abajo) | Necesario para el ready-status y para reconstruir los `DatosPokemonBatalla` en el anfitrión. Se guardan como **referencias** (`reclutado_id[]` + `posicion[slot]`), no como datos de motor. |
| `AgregadoBatalla` serializado | ❌ | Vive en `session` del anfitrión (`SESSION_VERSION|serialize`, como `IniciarCombateEntrenador`). La sala solo guarda el `battle_id` una vez arrancada (para re-vincular en reconexión). |
| `battle_id` (nullable) | ✅ | Una vez iniciada, vincula sala ↔ `session($battleId)` del anfitrión. |

**Nota sobre el contenido del equipo:** NO guardamos el `DatosPokemonBatalla` calculado ni el `Combatiente` (esos son del motor, en session). Guardamos referencias `reclutado_id` + posición; al construir la batalla el anfitrión vuelve a pasar por `ConstruirEquipoJugador`/`MapeadorPokemonBatalla`. Esto garantiza que el invitado nunca "inyecta" stats y que el motor siempre corre con datos derivados en la máquina anfitriona.

**Migración sugerida:**

```php
Schema::create('salas_batalla', function (Blueprint $table) {
    $table->id();
    $table->string('codigo', 10)->unique();
    $table->string('canal', 64)->unique();              // 'privado-batalla.{codigo}'
    $table->unsignedBigInteger('anfitrion_user_id');
    $table->unsignedBigInteger('invitado_user_id')->nullable();
    $table->string('estado')->default('esperando_invitado');
    $table->json('equipo_anfitrion')->nullable();       // [{reclutado_id, posicion}]
    $table->json('equipo_invitado')->nullable();        // [{reclutado_id, posicion}]
    $table->boolean('anfitrion_confirmado')->default(false);
    $table->boolean('invitado_confirmado')->default(false);
    $table->string('battle_id')->nullable();
    $table->timestamp('expira_en');
    $table->timestamps();

    $table->foreign('anfitrion_user_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('invitado_user_id')->references('id')->on('users')->onDelete('cascade');
});
```

Modelo Eloquent `App\Models\SalaBatalla` coherente con las convenciones del repo (relaciones `belongsTo(User, 'anfitrion_user_id')` / `belongsTo(User, 'invitado_user_id')`).

**Comodín de vida**: `expira_en` (columna `timestamp`) para el TTL de la sala; un comando/sweeper o validación al cargar compara contra `now()`.

## 4. Generador de código

- **Formato**: 5 caracteres del conjunto sin ambiguos `A-Z` y `0-9` **excluyendo** `O,0,I,1,L` → **31 símbolos** → `31^5 = 28.629.151` combinaciones. Suficiente para concurrencia baja de una app personal; si se quiere más margen, 6 caracteres (`= 887.503.681`) sin coste de UX relevante.
- **Unicidad/colisiones**: al generar, insertar con reintento `MAX(5)` sobre colisión de índice único `codigo`. Patrón: `do { $codigo = strtoupper(substr(str_shuffle($alfabeto), 0, 5)); } while (SalaBatalla::where('codigo',$codigo)->where('estado','!=','en_batalla')->exists());`. Mejor aún: índice único + captura de `QueryException` de constraint con reintento, para disparidad entre chequeo y escritura.
- **PCRNG**: usar `Str::random(5, $alfabeto)` o `random_bytes` (no `rand()`/`mt_rand()` para resistencia a guess).
- **Expiración**: la columna `expira_en = now()->addMinutes(10)` en el lobby. Al validar la sala (`unirse`) rechazar si `expira_en < now()`. Un `php artisan salas:limpiar` (o job programado) borra salas vencidas/finalizadas > X. **El código es solo para ENTRAR/UNIRSE**; una vez ambos confirmaron y arrancó la batalla, la seguridad del canal ya no depende del código (canal privado por `auth`), así que el código puede invalidarse al pasar a `en_batalla`.

## 5. Flujo de pantallas y componentes

Los usuarios están autenticados (ruta `/combate` ya corre bajo `auth`). Todo el bloque PvP va dentro del grupo `auth`, coexistiendo con `/combate` modo IA.

**Componentes Livewire propuestos** (nuevos en `app/Livewire/`; solo existe `Combate` hoy):

| Componente | Descripción | Ruta | Ruta Livewire |
|---|---|---|---|
| `PvpHome` | Punto de entrada: botones "Crear partida" y "Unirse con código". | `GET /pvp` | `/pvp` |
| `PvpCrear` | Genera sala + código (escribe `salas_batalla`), muestra código copiable y entra al lobby como anfitrión. | `GET /pvp/crear` | `/pvp/crear` |
| `PvpUnirse` | Form de código; valida existencia/estado; asigna `invitado_user_id` y redirige al lobby. | `GET /pvp/unirse` / `POST /pvp/unirse` | `/pvp/unirse` |
| `PvpLobby` | Selector de equipo (reutilizando lógica de `ConstruirEquipoJugador`), toggle de posición, botón confirmar, **ready-status de ambos**, y botón arranque para el anfitrión cuando ambos listos. | `GET /pvp/lobby/{sala}` | `/pvp/lobby/{sala}` |
| `Combate` (existente) | Reutilizado como pantalla de batalla; entra con `battle_id` del anfitrión. | `GET /combate?battle_id=...` | (existente) |

**Nombres de rutas** (segmento `pvp` para no chocar con `/combate`):

```
GET  /pvp                    → pvp.home        (PvpHome)
GET  /pvp/crear              → pvp.crear       (PvpCrear)
GET  /pvp/unirse             → pvp.unirse      (mostrar form)
POST /pvp/unirse             → pvp.unirse.post (validar código + asignar)
GET  /pvp/lobby/{sala}       → pvp.lobby       (PvpLobby)
```

Lobby → batalla: el anfitrión, tras ambos confirmados, construye la sala; **en PvP el anfitrión es quien monta `Combate`** con el `battle_id` (él redirige a `/combate?battle_id=...`). El invitado no monta `Combate` con motor; montará un componente espejo `PvpClienteEspejo` (Fase 3 de la propuesta) que solo pinta lo difundido — fuera del alcance de este doc.

**Sincronización del ready-status** entre anfitrión e invitado: al confirmar cada jugador, `POST` actualiza su `*_confirmado` en `salas_batalla` y hace `broadcast` a `privado-batalla.{codigo}`. Ambos re-renderizan el lobby (el anfitrión vía listener Reverb; el invitado también). Se evita el problema de que cada uno "confirme" contra su propia sesión y nunca se entere del otro.

## 6. Transición Lobby → Batalla (construcción del AgregadoBatalla)

Cuando `anfitrion_confirmado && invitado_confirmado && estado == 'seleccionando'`, el anfitrión (que tiene persistidos `equipo_anfitrion` e `equipo_invitado` de la sala, como referencias `reclutado_id`+`posicion`):

1. **Equipo anfitrión (team1)**: `ConstruirEquipoJugador::desdeEquipo($teamAnfitrion, $formacionAnfitrion)` → `list<DatosPokemonBatalla>`. Para ello monta un `Team` con los 3 `Reclutado` elegidos (o directamente construye desde una lista de `Reclutado`, extendiendo `ConstruirEquipoJugador` con un método `desdeReclutados(...)` si el lobby no se ata a un `Team` existente).
2. **Equipo invitado (team2)**: mismo proceso con las referencias del invitado, cargando los `Reclutado` por `id`.
3. `EquipoBatalla::fromData($datosAnfitrion, $nombreAnfitrion)` y `EquipoBatalla::fromData($datosInvitado, $nombreInvitado)` (mismo patrón que `IniciarCombateEntrenador:52-53`). **`team1 = anfitrión`, `team2 = invitado`** (convención actual: `team===0` humano local).
4. `$batalla = new AgregadoBatalla($team1, $team2); $batalla->triggerBattleStartEffects();`
5. `$battleId = 'batalla_pvp_'.uniqid();` → `session()->put($battleId, SESSION_VERSION.'|'.serialize($batalla));` (mismo `SESSION_VERSION` que `Combate`/`IniciarCombateEntrenador` — **debe quedar alineado**, ver Riesgos).
6. Actualiza `salas_batalla.battle_id = $battleId; estado = 'en_batalla'` y re-serializa al azar el código.
7. Redirige al anfitrión a `/combate?battle_id=$battleId`; broadcast al invitado para que entre a su cliente espejo.

Toda la lógica de la construcción vive en la **máquina anfitriona**; el invitado jamás ejecuta ni `ConstruirEquipoJugador` ni motores — solo manda referencias, como dicta la propuesta.

## 7. Casos límite y mitigaciones

| Caso | Comportamiento |
|---|---|
| **Código inexistente** | `POST /pvp/unirse` valida contra `salas_batalla`; si no existe → error de validación "Código no válido", sin crear filas. |
| **Sala ya tiene invitado** (`invitado_user_id` no nulo y ≠ usuario) | Rechazar unión: "Esta sala ya está completa". Si el `invitado_user_id` **es el mismo usuario** → permitir re-entrar (reconexión). |
| **Anfitrión abandona el lobby** | El invitado detecta (heartbeat/estado `anfitrión` muerto o cierre) y ve "El anfitrión abandonó la sala"; la sala pasa a `cerrada` y se limpia. El anfitrión se considera ausente tras timeout (ej. no responde en 5 min si nadie confirmó). |
| **Invitado abandona el lobby** | `invitado_user_id` vuelve a null y `invitado_confirmado` a false; el anfitrión ve "esperando invitado" de nuevo; el código vuelve a ser "unible". |
| **Reinicio de selección** | Desmarcar `*_confirmado` conlleva que el otro jugador vea "X no está listo"; no se puede arrancar hasta que ambos vuelvan a confirmar. Si alguien cambia de Pokémon, solo borra su propio `equipo_*` y su flag. |
| **Expiración de la sala** | `expira_en` (10 min de lobby). Al unirse o consultar, si `expira_en < now()` → se marca `vencida`, el código muere y entra en el sweep de limpieza. En `en_batalla` el código ya no es necesario. |
| **Invitado intenta unirse a sala `en_batalla`** | Rechazar; la batalla ya está cerrada (usar el `battle_id`/canal existente para reconexión, no un código nuevo). |
| **Anfitrión reconstruye tras recarga en lobby** | La sala está en BD; `PvpLobby` para el anfitrión la re-carga desde `salas_batalla` por su `anfitrion_user_id`, preservando equipos/flags. El `AgregadoBatalla` solo se pierde si recarga **durante la batalla** con `estado=en_batalla` y sin `battle_id` — esto es un riesgo real (ver §8). |
| **Anfitrión valida la sala al entrar** | `PvpLobby` valida que el usuario sea el anfitrión o el invitado registrado; 403 en otro caso (el canal privado Reverb hace lo mismo en `routes/channels.php`). |

## 8. Riesgos

1. **Pérdida de `AgregadoBatalla` en session del anfitrión** (recarga/cierre durante la batalla): el motor no está en BD, está en `session($battleId)` y `SESSION_DRIVER=database` la persiste en `sessions` de la sesión del anfitrión; si el anfitrión recarga sigue su `session`, pero si **expira su sesión** se pierde todo y el invitado queda huérfano. Mitigación a corto plazo: aceptar como límite (partidas de una sesión), documentar. Sería la **única** justificación futura para persistir el `AgregadoBatalla` — pero rompería el requisito de autoridad única/BD, así que se deja fuera del alcance.
2. **`SESSION_VERSION` desincronizado**: `saveBattle` luego `getBattle` exige `v{version}|`. Cualquier cambio de patrón/versión en `Combate` o `IniciarCombateEntrenador` debe replicarse en el constructor PvP. Recomiendo extraer una constante/voz central.
3. **Cast del equipo**: guardar `reclutado_id`+posición exige que el `Reclutado` no cambie entre confirmar y arrancar; si el jugador evoluciona/da caramelo tras confirmar, la batalla usará los datos recalculados (aceptable, sigue siendo el anfitrión quien los deriva).
4. **Sincronismo del ready-status**: si el broadcast falla (`BROADCAST_CONNECTION=log` ahora mismo), el lobby no es usable en tiempo real. La fase lobby depende de Reverb; mientras no se instale, el PvP real no funciona — solo la parte de crear/registrar sala. Esto es coherente con la Fase 3 de la propuesta.
5. **Canal privado por código**: el código es factor de autenticación del canal (`privado-batalla.{codigo}`); al ser de 5 caracteres, un tercero que lo conozca podría espiar. Mantener validación por `auth` + `user_id` en `routes/channels.php`, e invalidar el código al pasar a `en_batalla` (ver §4).
6. **Concurrencia de unión** (dos invitados a la vez): mitigar con transacción + update condicional `WHERE invitado_user_id IS NULL` para que solo uno gane (`updated rows = 0` → "sala llena").
7. **Código adivinable**: el conjunto sin ambiguos y PCRNG reducen la superficie, pero un código de 5 es débil contra fuerza bruta a escala; dado que es un proyecto personal sobre red privada (Tailscale, según la propuesta), riesgo asumido y mitigado por la invalidación temprana.
