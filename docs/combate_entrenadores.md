# Documentación del Módulo `CombateEntrenadores`

## 1. Descripción general

Esta funcionalidad permite al jugador, desde la vista de detalle de un hábitat, conmutar al modo "Entrenadores" para enfrentarse a entrenadores generados proceduralmente. Cada hábitat tiene 3 niveles, y cada nivel expone 3 entrenadores (9 combates disponibles por día). El jugador selecciona un equipo de su propiedad, elige un rival, configura la formación (vanguardia/retaguardia) de sus 3 pokémon en un popup, e inicia un combate que reutiliza el motor de batalla existente (`src/Battle/Domain/`). Al ganar, otorga recompensas dobles (EXP, caramelos familia/EV/tipo) y dispara avistados en la Pokédex.

**Regla clave**: el límite diario es de 3 entrenadores por nivel × 3 niveles = 9 combates por hábitat al día. Si se pierde, se puede repetir al mismo entrenador (no se consume el slot). Si se gana, el entrenador queda bloqueado hasta el día siguiente.

---

## 2. Arquitectura (DDD)

El módulo sigue la convención DDD del proyecto (`docs/ddd.md`): capa `Domain/` pura (sin Eloquent, sin HTTP, sin facades) y capas `App/` + `Infra/` con Laravel libremente.

```
src/CombateEntrenadores/
├── Domain/
│   ├── GeneradorMovimientosTipo.php
│   ├── ClasificadorPosicion.php
│   ├── GeneradorFormacion.php
│   ├── Repositories/
│   │   └── EntrenadorLogRepositoryInterface.php
│   └── Exceptions/
│       └── EntrenadorDerrotadoHoy.php
├── App/
│   ├── MapeadorPokemonBatalla.php
│   ├── GeneradorEquipoEntrenador.php
│   ├── ConstruirEquipoJugador.php
│   ├── ObtenerEntrenadoresHabitat.php
│   ├── IniciarCombateEntrenador.php
│   ├── RegistrarResultadoEntrenador.php
│   └── OtorgarRecompensasEntrenador.php
└── Infra/
    ├── Controllers/
    │   └── EntrenadorController.php
    ├── EloquentEntrenadorLogRepository.php
    └── routes.php
```

### 2.1 Capa Domain

| Clase | Rol |
|---|---|
| `GeneradorMovimientosTipo` | Genera movimientos sintéticos a partir de los tipos del pokémon. Sin datos reales de ataques en BD, cada pokémon recibe movimientos según sus tipos: (a) tipos no-Normal → 1 físico (60) + 1 especial (80) por tipo; (b) Normal puro → 2 movimientos Normal de 80 y 100; (c) con tipos no-Normal → 2 movimientos Normal de 40 y 60. |
| `ClasificadorPosicion` | Clasifica un pokémon como defensivo (vanguardia) u ofensivo (retaguardia) según la comparación de stats: `def + spDef >= atk + spAtk` → defensivo (vanguardia); en caso contrario → ofensivo (retaguardia). |
| `GeneradorFormacion` | Genera una formación aleatoria 1/2 o 2/1 (vanguardia/retaguardia) para un equipo de 3. Respeta la clasificación: los pokémon defensivos tienen prioridad para ocupar las plazas de vanguardia; los ofensivos para retaguardia. La cantidad de vanguardia se elige aleatoriamente (`random_int(1, total-1)`). |
| `EntrenadorLogRepositoryInterface` | Contrato del repositorio: `haGanadoHoy(userId, habitatId, level, trainerIndex, fecha): bool` y `registrarResultado(userId, habitatId, level, trainerIndex, won, fecha): void`. |
| `EntrenadorDerrotadoHoy` | Excepción de dominio (`ViolacionReglaNegocio` con código 422) que se lanza cuando se intenta combatir contra un entrenador al que ya se le ha ganado hoy. |

### 2.2 Capa App

| Clase | Rol |
|---|---|
| `MapeadorPokemonBatalla` | Mapea un `App\Models\Pokemon` (con stats y types) a `DatosPokemonBatalla` (DTO del motor de batalla). Genera movimientos sintéticos vía `GeneradorMovimientosTipo`. Extrae stats usando `StatEnum` y tipos usando `TipoPokemon::from()`. |
| `GeneradorEquipoEntrenador` | Genera el equipo rival de un entrenador de forma determinista. Toma el pool del hábitat para un nivel dado, elige hasta 3 especies con semilla `crc32(habitat\|nivel\|entrenador\|fecha)`, las clasifica en defensivo/ofensivo y aplica una formación aleatoria. |
| `ConstruirEquipoJugador` | Construye el equipo de batalla del jugador desde un `Team` de la BD más un array de posiciones (formación elegida en el popup). Si un slot no tiene posición explícita, aplica la clasificación por stats. |
| `ObtenerEntrenadoresHabitat` | Devuelve el listado de entrenadores de un hábitat (3 por nivel, 3 niveles) con su estado de desbloqueo del día y vista previa de su equipo (idéntico al que se generará en el combate gracias a la semilla determinista). |
| `IniciarCombateEntrenador` | Crea una batalla contra un entrenador. Valida límite diario, construye ambos equipos, crea un `AgregadoBatalla`, lo serializa en sesión con prefijo `v{version}\|` y guarda metadatos (`habitat_id`, `nivel`, `trainer_index`, `user_id`, `team_id`, `fecha`) bajo `battleId._meta`. Retorna el `battleId`. El jugador es siempre team1; el rival es team2. |
| `RegistrarResultadoEntrenador` | Registra el resultado de un combate contra entrenador mediando el repositorio (upsert por día). Con `won=true` el entrenador queda bloqueado el resto del día. |
| `OtorgarRecompensasEntrenador` | Otorga recompensas dobles al ganar. Reutiliza `CalculadorRecompensas` × 2.0 de `Exploraciones`, persiste con `PersistirRecompensas`, y dispara `ActualizarPokedexJob` con estado `AVISTADO` por cada pokémon rival. Sin capturas: el combate contra entrenadores no recluta rivales. |

### 2.3 Capa Infra

| Clase | Rol |
|---|---|
| `EntrenadorController` | API REST: `index()` → GET listado de entrenadores; `combatir()` → POST para iniciar combate. Valida `team_id` (pertenece al usuario autenticado) y `formacion` (array con valores `vanguardia`/`retaguardia`). |
| `EloquentEntrenadorLogRepository` | Implementación Eloquent de `EntrenadorLogRepositoryInterface`. `haGanadoHoy()` consulta `TrainerCombatLog::where(...)->where('won', true)->exists()`. `registrarResultado()` hace `updateOrCreate()` con la unique key `(user_id, habitat_id, level, trainer_index, fought_at)`. |
| `routes.php` | Archivo legacy (las rutas reales se cargan desde `routes/entrenadores.php`). |

---

## 3. Flujo completo (end-to-end)

### 3.1 Vista del hábitat → modo Entrenadores

1. El usuario está en `/habitats/{id}` y pulsa el botón **"Entrenadores"** (icono `trainer.webp`).
2. Alpine.js (`habitatShow()`) conmuta `modo: 'pokemon'` → `modo: 'entrenadores'` e invoca `loadTrainers()`.
3. `loadTrainers()` hace fetch a `GET /api/habitats/{id}/entrenadores`.
4. El controlador `EntrenadorController::index()` llama a `ObtenerEntrenadoresHabitat::obtener()`, que genera los 3 entrenadores de cada nivel con su equipo (determinista por fecha) y su estado de desbloqueo (`haGanadoHoy`).
5. El frontend renderiza los niveles con sus 3 entrenadores. Cada entrenador muestra previsualización de sus 3 pokémon con icono y posición.

### 3.2 Selección de equipo y rival

1. El usuario selecciona un equipo (click en tarjeta de equipo → `selectedTeamId`).
2. Click en un entrenador desbloqueado → `selectTrainer(level, trainerIndex)`.
3. La función inicializa `formacion` con todos los slots en `'vanguardia'` por defecto y abre el popup de formación (`showFormacionPopup = true`).

### 3.3 Popup de formación

1. El popup muestra cada miembro del equipo con su imagen, nombre, slot y un botón toggle.
2. Cada botón alterna entre `🛡️ Vanguardia` y `⚔️ Retaguardia` vía `toggleFormacionSlot(slot)`.
3. El usuario puede ajustar la posición de cada pokémon según su estrategia.
4. Al pulsar **"¡Combatir!"** → `confirmarCombate()`.

### 3.4 Inicio del combate

1. `confirmarCombate()` hace fetch a `POST /api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/combatir` con `{team_id, formacion}`.
2. `EntrenadorController::combatir()` valida los datos, llama a `IniciarCombateEntrenador::iniciar()`.
3. `IniciarCombateEntrenador`:
   - Verifica que el entrenador no esté derrotado hoy (`haGanadoHoy` → lanza `EntrenadorDerrotadoHoy`).
   - Construye equipo del jugador desde `Team` + `formacion`.
   - Genera equipo rival determinista desde el pool del hábitat.
   - Crea `EquipoBatalla` para ambos lados y los pasa al constructor de `AgregadoBatalla`.
   - Ejecuta `triggerBattleStartEffects()` (efectos de inicio de batalla como clima).
   - Serializa la batalla en sesión bajo `battleId` con prefijo `v{version}|`.
   - Guarda metadatos bajo `battleId._meta`.
   - Retorna `{battle_id, redirect}`.
4. El frontend redirige a `window.location.href = data.redirect` (ej: `/combate?battle_id=...`).

### 3.5 Ciclo de combate

1. `Combate::mount()` detecta `battle_id` en query params, carga la batalla desde sesión, la deserializa e inicia el ciclo de turnos.
2. El ciclo de turnos sigue el mismo flujo que la batalla manual estándar: `nextActor()` → selección de objetivo/movimiento → `commitAction()` → `ServicioEjecucionBatalla::calcularYAplicarDaño()` → consumir acción → siguiente actor.
3. La batalla continúa hasta que un equipo queda completamente debilitado.

### 3.6 Finalización y recompensas

1. `endBattle(AgregadoBatalla)` se invoca cuando `bothTeamsAlive()` es falso.
2. Dentro de `endBattle()` se llama a `registrarResultadoEntrenador(battle)`:
   - Recupera los metadatos de sesión (`battleId._meta`).
   - Determina si el jugador ganó (`!$battle->team1->todosDebilitados()`).
   - `RegistrarResultadoEntrenador::registrar()` → upsert en `trainer_combat_log`.
   - Si ganó: `OtorgarRecompensasEntrenador::otorgar()`:
     - Reutiliza `CalculadorRecompensas` (de `Exploraciones`) con multiplicador 2.0.
     - Reutiliza `PersistirRecompensas` para persistir EXP y caramelos.
     - Dispara `ActualizarPokedexJob::dispatch(userId, pokemonId, 'AVISTADO')` por cada rival.
     - Retorna datos para el modal de victoria.
   - Limpia los metadatos de sesión.
3. El modal de victoria se muestra con las recompensas (EXP total, EXP por miembro, caramelos).
4. Botón **"Volver al hábitat"** → enlace a `/habitats/{habitatId}`.

---

## 4. Reglas de negocio

### 4.1 Límite diario
- 3 entrenadores por nivel × 3 niveles = 9 combates por hábitat al día.
- La fecha base es `today()->toDateString()` (Y-m-d).
- La tabla `trainer_combat_log` usa un unique compuesto `(user_id, habitat_id, level, trainer_index, fought_at)`.

### 4.2 Upsert y bloqueo
- `trainer_combat_log` se upserta con `updateOrCreate`. Si `won=true`, el entrenador queda bloqueado (`haGanadoHoy()` retorna `true`).
- Si se pierde, `won=false` → no se bloquea → se puede repetir el mismo entrenador (no se consume el slot).

### 4.3 Desbloqueo
- Un entrenador está disponible si NO existe un registro con `won=true` para hoy en la misma combinación `(user_id, habitat_id, level, trainer_index)`.

### 4.4 Generación rival determinista
- Semilla: `crc32("{habitatId}|{nivel}|{entrenadorIndex}|{fecha}")`.
- Usa `Random\Engine\Mt19937` con la semilla para generar el mismo equipo durante todo el día.
- El pool del hábitat se obtiene de `Habitat::with('pokemon')` filtrado por nivel, con stats y types cargados.

### 4.5 Formación rival
- Clasificación: `def+spDef >= atk+spAtk` → defensivo (vanguardia).
- Formación: `random_int(1, total-1)` define cuántos van a vanguardia. Los defensivos tienen prioridad para ocupar esas plazas.

### 4.6 Movimientos temporales
- No hay datos reales de ataques en BD. Los movimientos se generan sintéticamente según los tipos del pokémon:
  - Tipos no-Normal: 1 físico (60 potencia) + 1 especial (80 potencia) por tipo.
  - Normal puro: 2 movimientos Normal de 80 y 100.
  - Con tipos no-Normal: además 2 movimientos Normal de 40 y 60.

### 4.7 Recompensas
- **Doble** de la fórmula de exploración (`multiplicador = 2.0`).
- Sin capturas: el combate contra entrenadores no recluta pokémon rivales.
- Se otorga EXP a la cuenta y a cada miembro del equipo, caramelos de familia, caramelos EV y caramelos de tipo.
- Se dispara `ActualizarPokedexJob` con estado `AVISTADO` por cada especie rival derrotada.

---

## 5. Base de datos

### 5.1 Tabla `trainer_combat_log`

```sql
CREATE TABLE trainer_combat_log (
    id                BIGINT PRIMARY KEY,
    user_id           BIGINT NOT NULL,
    habitat_id        BIGINT NOT NULL,
    level             TINYINT UNSIGNED NOT NULL,   -- 1-3
    trainer_index     TINYINT UNSIGNED NOT NULL,   -- 1-3
    won               BOOLEAN NOT NULL DEFAULT false,
    fought_at         DATE NOT NULL,
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,

    CONSTRAINT trainer_log_encounter_unique UNIQUE (user_id, habitat_id, level, trainer_index, fought_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (habitat_id) REFERENCES habitats(id) ON DELETE CASCADE
);
```

### 5.2 Modelo `TrainerCombatLog` (`app/Models/TrainerCombatLog.php`)

- Uses `BelongsToUser` trait.
- `$fillable`: `user_id`, `habitat_id`, `level`, `trainer_index`, `won`, `fought_at`.
- `$casts`: `level` → integer, `trainer_index` → integer, `won` → boolean, `fought_at` → date.
- Relaciones: `user()`, `habitat()`.

---

## 6. API Endpoints

### 6.1 GET `/api/habitats/{habitat}/entrenadores`

**Descripción**: Lista los 3 niveles con sus 3 entrenadores, su disponibilidad (desbloqueado) y previsualización del equipo.

**Parámetros**: `{habitat}` (int) — ID del hábitat.

**Respuesta** (200):
```json
{
  "1": [
    {
      "indice": 1,
      "desbloqueado": true,
      "pokemon": [
        { "id": 25, "nombre": "Pikachu", "icon": "/images/iconos_webp/25.webp", "posicion": "vanguardia" },
        { "id": 6,  "nombre": "Charizard", "icon": "/images/iconos_webp/6.webp", "posicion": "retaguardia" },
        { "id": 94, "nombre": "Gengar", "icon": "/images/iconos_webp/94.webp", "posicion": "retaguardia" }
      ]
    }
  ]
}
```

**Controlador**: `EntrenadorController::index()` → `ObtenerEntrenadoresHabitat::obtener()`.

### 6.2 POST `/api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/combatir`

**Descripción**: Inicia un combate contra un entrenador. Valida límite diario, construye equipos y guarda la batalla en sesión. Retorna `battle_id` y `redirect` URL.

**Parámetros de ruta**: `{habitat}` (int), `{nivel}` (int, 1-3), `{trainer}` (int, 1-3).

**Body**:
```json
{
  "team_id": 1,
  "formacion": { "1": "vanguardia", "2": "retaguardia", "3": "vanguardia" }
}
```

**Validación**:
- `team_id`: required, integer, exists en `teams` con `user_id = Auth::id()`.
- `formacion`: sometimes, array, cada valor `in:vanguardia,retaguardia`.

**Respuesta** (200):
```json
{
  "battle_id": "battle_entrenador_abc123",
  "redirect": "/combate?battle_id=battle_entrenador_abc123"
}
```

**Errores**:
- 422: `EntrenadorDerrotadoHoy` ("Ya has derrotado a este entrenador hoy.").

**Controlador**: `EntrenadorController::combatir()` → `IniciarCombateEntrenador::iniciar()`.

---

## 7. Frontend

### 7.1 Vista de hábitat (`resources/views/habitats/show.blade.php`)

El componente Alpine `habitatShow()` gestiona el estado del modo "Entrenadores":

| Propiedad | Tipo | Descripción |
|---|---|---|
| `modo` | `'pokemon'` \| `'entrenadores'` | Modo actual de la vista |
| `trainers` | `array|null` | Datos de entrenadores cargados desde la API |
| `trainersLoading` | `boolean` | Indicador de carga |
| `selectedTrainer` | `object|null` | Entrenador seleccionado |
| `showFormacionPopup` | `boolean` | Controla visibilidad del popup de formación |
| `formacion` | `object` | Mapa `{slot: 'vanguardia'|'retaguardia'}` |

**Métodos Alpine clave**:

| Método | Descripción |
|---|---|
| `toggleEntrenadores()` | Conmuta modo pokemon/entrenadores. Al activar, carga los entrenadores vía fetch. |
| `loadTrainers()` | Fetch a `GET /api/habitats/{id}/entrenadores`. |
| `selectTrainer(level, trainerIndex)` | Selecciona entrenador, inicializa `formacion` con todos los slots en `'vanguardia'`, abre popup. |
| `openFormacionPopup()` | Abre popup preservando la formación actual (si ya existe). |
| `toggleFormacionSlot(slot)` | Alterna entre `'vanguardia'` y `'retaguardia'` para un slot. |
| `closeFormacionPopup()` | Cierra popup y resetea `selectedTrainer`. |
| `confirmarCombate()` | Fetch POST a la API de combate, redirige a `redirect` URL. |

**Selectores visuales**:
- Botón **"Entrenadores"** en la columna izquierda (con icono `trainer.webp`).
- Botón **"Iniciar Combate contra Entrenador"** (rojo) visible solo en modo `entrenadores`, deshabilitado si no hay equipo o entrenador seleccionado.
- Popup de formación: lista de miembros con toggle de posición, botón "Cancelar" y "¡Combatir!".

### 7.2 Modal de victoria (`resources/views/livewire/combate.blade.php`)

Se muestra cuando `$phase === 'battle_over'` y `$rewards` no está vacío:

```html
<ul class="list-group list-group-flush">
  <li>EXP para tu cuenta: +{rewards.exp_total}</li>
  <li>EXP para cada pokémon: +{rewards.exp_miembro}</li>
  @foreach(rewards.caramelos as caramelo)
    <li>{caramelo.nombre}: +{caramelo.cantidad}</li>
  @endforeach
</ul>
<a href="/habitats/{habitatId}">Volver al hábitat</a>
```

### 7.3 Lógica Alpine del popup de formación

```javascript
// Inicialización al seleccionar entrenador
selectTrainer(level, trainerIndex) {
    this.selectedLevel = level;
    this.selectedTrainer = trainer;
    this.formacion = {};
    const team = this.teams.find(t => t.id === this.selectedTeamId);
    if (team && team.members) {
        team.members.forEach(m => {
            this.formacion[m.slot] = 'vanguardia';  // por defecto
        });
    }
    this.showFormacionPopup = true;
},

// Toggle vanguardia/retaguardia
toggleFormacionSlot(slot) {
    this.formacion[slot] = this.formacion[slot] === 'vanguardia'
        ? 'retaguardia'
        : 'vanguardia';
},

// Confirmar e iniciar combate
async confirmarCombate() {
    const response = await fetch('/api/habitats/{id}/entrenadores/' + this.selectedLevel + '/' + this.selectedTrainer.indice + '/combatir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ team_id: this.selectedTeamId, formacion: this.formacion }),
    });
    const data = await response.json();
    window.location.href = data.redirect;
}
```

---

## 8. Dependencias entre módulos

### 8.1 `CombateEntrenadores` → `Battle`

- `DatosPokemonBatalla` (DTO de entrada del motor de batalla)
- `EquipoBatalla::fromData()` (construye equipo desde datos de batalla)
- `AgregadoBatalla` (agregado raíz del combate)
- `Posicion` (enum vanguardia/retaguardia)
- `MovimientoBatalla` (movimiento del pokémon en combate)
- `CategoriaMovimiento` (enum físico/especial)

### 8.2 `CombateEntrenadores` → `Exploraciones`

- `CalculadorRecompensas` (fórmula de recompensas, usada con multiplicador 2.0)
- `PersistirRecompensas` (persistencia de EXP y caramelos)
- `NormalizadorPokemonDerrotado` (normaliza pokémon Eloquent a `PokemonDerrotado`)
- `PokemonDerrotado` (DTO de pokémon derrotado)
- `ResultadoRecompensas` (DTO con el resultado de recompensas)

### 8.3 `CombateEntrenadores` → `Shared`

- `TipoPokemon` (enum de tipos)
- `DominioExcepcion` (excepción base de dominio)
- `ViolacionReglaNegocio` (excepción para reglas de negocio, HTTP 422)

### 8.4 `app/Livewire/Combate` → `CombateEntrenadores`

- `RegistrarResultadoEntrenador` (persiste resultado del combate)
- `OtorgarRecompensasEntrenador` (calcula y persiste recompensas al ganar)

### 8.5 `app/Models/TrainerCombatLog` → `CombateEntrenadores`

- Modelo Eloquent usado por `EloquentEntrenadorLogRepository`

---

## 9. Rutas

Las rutas se cargan desde `routes/entrenadores.php`, que es requerido desde `routes/web.php`:

```php
// routes/entrenadores.php
Route::get('/api/habitats/{habitat}/entrenadores', [EntrenadorController::class, 'index']);
Route::post('/api/habitats/{habitat}/entrenadores/{nivel}/{trainer}/combatir', [EntrenadorController::class, 'combatir']);
```

El archivo `src/CombateEntrenadores/Infra/routes.php` existe como placeholder para la migración futura (convención DDD).

---

## 10. Diagrama de secuencia (resumen)

```
Usuario                 Frontend (Alpine)          API (EntrenadorController)        Dominio/App
   │                         │                              │                          │
   │── click "Entrenadores" ──│                              │                          │
   │                         │── GET /api/habitats/{id}/entrenadores ──│              │
   │                         │                              │── ObtenerEntrenadoresHabitat ──│
   │                         │                              │                          │── GeneradorEquipoEntrenador (3×3)
   │                         │                              │                          │── haGanadoHoy (por entrenador)
   │                         │←────── JSON (3 niveles × 3 entrenadores) ──────────────│
   │── click entrenador ──────│                              │                          │
   │                         │── Abre popup formación ──────│                          │
   │── ajusta formación ──────│                              │                          │
   │── click "¡Combatir!" ────│                              │                          │
   │                         │── POST /combatir ────────────│                          │
   │                         │                              │── IniciarCombateEntrenador │
   │                         │                              │                          │── haGanadoHoy (validación)
   │                         │                              │                          │── ConstruirEquipoJugador
   │                         │                              │                          │── GeneradorEquipoEntrenador
   │                         │                              │                          │── new AgregadoBatalla
   │                         │                              │                          │── session()->put(battleId)
   │                         │←────── {battle_id, redirect} ──────────────────────────│
   │                         │── window.location = redirect ──│                          │
   │                         │                              │                          │
   │── /combate?battle_id=XX ──│                              │                          │
   │                         │── Combate::mount() ──────────│                          │
   │                         │   → session()->get(battleId) ──────────────────────────│
   │                         │   → nextActor() → ciclo de turnos                    │
   │                         │                              │                          │
   │                         │←── endBattle() ──────────────│                          │
   │                         │   → RegistrarResultadoEntrenador                      │
   │                         │   → OtorgarRecompensasEntrenador (si won)             │
   │                         │                              │                          │
   │                         │←── Modal de victoria ────────│                          │
   │── click "Volver al hábitat" ──│                          │                          │
```