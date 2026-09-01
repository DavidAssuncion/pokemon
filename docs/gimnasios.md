# Documentación del Módulo `Gimnasios`

## 1. Descripción general

El sistema de gimnasios permite al jugador desafiar a 5 gimnasios (bug, poison, normal, grass,
flying) en progresión secuencial. Cada gimnasio tiene 3 entrenadores + 1 líder, un nivel mínimo
de acceso y rivales que escalan su nivel según el del jugador. Al completar un gimnasio se
obtiene una medalla; el gimnasio queda cerrado para siempre. El combate reutiliza el motor de
batalla (`src/Battle/Domain/`) y las recompensas ×10 del sistema de entrenadores
(`OtorgarRecompensasEntrenador`).

**Regla clave**: progresión estrictamente secuencial sin retroceso ni repetición — solo se puede
combatir la etapa ACTUAL. Si pierdes, es repetible. Si ganas, `current_stage + 1`. Al llegar a 5
el gimnasio está completado.

---

## 2. Arquitectura (DDD)

El módulo sigue la convención DDD del proyecto (`docs/ddd.md`): capa `Domain/` pura (sin Eloquent,
sin HTTP, sin facades) y capas `App/` + `Infra/` con Laravel libremente.

```
src/Gimnasios/
├── Domain/
│   ├── CatalogoGimnasios.php
│   ├── Gimnasio.php
│   ├── Exceptions/
│   │   ├── GimnasioBloqueado.php
│   │   ├── GimnasioCompletado.php
│   │   ├── EtapaNoDisponible.php
│   │   └── GimnasioNoExiste.php
│   └── Repositories/
│       └── GymProgressRepositoryInterface.php
├── App/
│   ├── EscaladorNivelRival.php
│   ├── GeneradorPokemonGimnasio.php
│   ├── ObtenerGimnasios.php
│   ├── ObtenerGimnasioDetalle.php
│   ├── IniciarCombateGimnasio.php
│   └── RegistrarResultadoGimnasio.php
└── Infra/
    ├── Controllers/
    │   └── GimnasioController.php
    ├── EloquentGymProgressRepository.php
    └── routes.php
```

### 2.1 Capa Domain

| Clase | Rol |
|---|---|
| `Gimnasio` | Value object: slug, medalla, tipo (`TipoPokemon`), nivel mínimo y equipos (species_id) por etapa 1-4. `nombreEtapa()` devuelve el nombre de cada etapa. |
| `CatalogoGimnasios` | Registro en código de los 5 gimnasios. `todos()`, `porSlug()`, `porSlugOrFail()`, `existe()`. |
| `GimnasioBloqueado` | `ViolacionReglaNegocio` (422): nivel del jugador por debajo del mínimo. |
| `GimnasioCompletado` | `ViolacionReglaNegocio` (422): se intenta combatir un gimnasio ya completado. |
| `EtapaNoDisponible` | `ViolacionReglaNegocio` (422): etapa no disponible (de reserva para flujos que validen etapa explícita). |
| `GimnasioNoExiste` | `RecursoNoExiste` (404): slug desconocido. |
| `GymProgressRepositoryInterface` | Contrato del repositorio: `obtenerProgreso(userId, gymId): ?int`, `registrarVictoria(userId, gymId, etapaCompletada): void`, `esCompletado(userId, gymId): bool`. |

### 2.2 Capa App

| Clase | Rol |
|---|---|
| `EscaladorNivelRival` | `nivel_rival = nivel_min + floor((nivel_jugador - nivel_min) / 2)`, clampado a `nivel_min` por debajo. |
| `GeneradorPokemonGimnasio` | Dado `species_id[]` + `nivel_rival` → `DatosPokemonBatalla[]` con stats base y `nivel`. Reutiliza `MapeadorPokemonBatalla` (stats/tipos), `GeneradorMovimientosTipo` (movimientos sintéticos) y `ClasificadorPosicion` (vanguardia/retaguardia). El escalado de stats al nivel rival lo realiza `BattleStats` internamente. |
| `ObtenerGimnasios` | Lista los 5 gimnasios con progreso del usuario, estado (disponible/bloqueado/completado) y nivel mínimo. |
| `ObtenerGimnasioDetalle` | Detalle de un gimnasio: etapas (solo nombres, sin revelar equipo rival ni nivel rival), etapa actual y estado. |
| `IniciarCombateGimnasio` | Crea la batalla: valida nivel mínimo, gimnasio no completado, construye ambos equipos, `AgregadoBatalla`, guarda en sesión con metadatos. |
| `RegistrarResultadoGimnasio` | Persiste el progreso al ganar (mediando el repositorio), con anti-IDOR: solo si `won` y `userId === authUserId`. Devuelve `{avance, completado, medalla}`. |

### 2.3 Capa Infra

| Clase | Rol |
|---|---|
| `GimnasioController` | API REST: `index()` → lista; `show($gym)` → detalle; `combatir($gym)` → inicia combate (valida `team_id` y `formacion`). |
| `EloquentGymProgressRepository` | Implementación Eloquent de `GymProgressRepositoryInterface` sobre `App\Models\GymProgress`. |
| `routes.php` | Placeholder legacy (las rutas reales se cargan desde `routes/gimnasios.php`). |

---

## 3. Flujo completo (end-to-end)

1. El usuario abre la vista de gimnasios → `GET /api/gimnasios` devuelve los 5 gimnasios con su
   estado (disponible/bloqueado/completado) y `etapa_actual`.
2. El usuario abre un gimnasio → `GET /api/gimnasios/{gym}` devuelve el detalle con las 4 etapas
   (solo nombres del entrenador/líder), la etapa actual y el estado. No revela el equipo rival ni
   el nivel rival.
3. El usuario selecciona un equipo y configura la formación (vanguardia/retaguardia) →
   `POST /api/gimnasios/{gym}/combatir` con `{team_id, formacion}`.
4. `IniciarCombateGimnasio` valida nivel mínimo y no-completado, construye el equipo del jugador
   (`ConstruirEquipoJugador`, con stats escaladas al nivel del jugador) y el del rival
   (`GeneradorPokemonGimnasio` escalado al nivel rival), crea un
   `AgregadoBatalla` y lo serializa en sesión con metadatos (`tipo='gimnasio'`, `gym_id`, `stage`,
   `nivel_rival`, `user_id`, `team_id`). Retorna `{battle_id, redirect}`.
5. El frontend redirige a `/combate?battle_id=...`; `Combate::mount()` carga la batalla y se
   desarrolla el combate (mismo motor que la batalla manual).
6. Al finalizar, `endBattle()` → `registrarResultadoEntrenador()` detecta `meta.tipo === 'gimnasio'`
   y deriva a `registrarResultadoGimnasio()`:
   - Si ganó y `userId === Auth::id()` → `RegistrarResultadoGimnasio::registrar()` persiste
     `current_stage + 1` (y `completed_at` si llega a 5).
   - Si ganó → `OtorgarRecompensasEntrenador::otorgar()` (recompensas ×10.0 + `ActualizarPokedexJob`
     AVISTADO por cada rival).
   - Si ganó al líder (etapa 4) → añade `medalla` al array de recompensas para el modal.
   - Limpia los metadatos de sesión.
7. El modal de victoria muestra las recompensas (y la medalla si aplica).

---

## 4. Reglas de negocio

### 4.1 Progresión secuencial
- Etapas: `1`=Entrenador 1, `2`=Entrenador 2, `3`=Entrenador 3, `4`=Líder, `5`=Completado.
- Solo se puede combatir la etapa ACTUAL (`current_stage`).
- Si pierdes → la etapa no avanza → repetible.
- Si ganas → `current_stage + 1`.
- No hay marcha atrás ni repetición de etapas ya superadas.
- Gimnasio completado (`current_stage = 5` + `completed_at`) → cerrado para siempre.

### 4.2 Nivel mínimo
- Cada gimnasio tiene un `nivel_min` (bug=10, poison=15, normal=20, grass=25, flying=31).
- Si `nivel_jugador < nivel_min` → `GimnasioBloqueado` (422) y el gimnasio aparece bloqueado.

### 4.3 Escalado de nivel

- **Rivales**: `nivel_rival = nivel_min + floor((nivel_jugador - nivel_min) / 2)` (solo si `nivel_jugador >= nivel_min`).
- **Equipo del jugador**: los pokémon del jugador NO tienen nivel propio; su nivel ES el nivel del
  jugador. `MapeadorPokemonBatalla`/`GeneradorPokemonGimnasio` pasan las stats BASE y el `nivel`
  real a `DatosPokemonBatalla`; el escalado lo realiza `BattleStats` en `EquipoBatalla::fromData()`
  (NO se pre-escala):
  - HP = `floor((2*base*L/100) + L + 10)`
  - Resto = `floor((2*base*L/100) + 5)`
- El nivel se congela al INICIAR cada combate (se guarda en metadatos de sesión).

### 4.4 Equipos rivales (catálogo en código)
- 5 gimnasios definidos en `CatalogoGimnasios` con species_id por etapa.
- Los movimientos son sintéticos por tipo (reutiliza `GeneradorMovimientosTipo`).
- Los pokémon se clasifican por stats: defensivo → vanguardia, ofensivo → retaguardia.

### 4.5 Recompensas
- ×10 de la fórmula de exploración (`multiplicador = 10.0`), reutilizando
  `OtorgarRecompensasEntrenador` (los combates contra entrenadores de hábitat siguen en ×2.0).
- Se dispara `ActualizarPokedexJob` con estado `AVISTADO` por cada especie rival derrotada.
- Sin capturas: el combate de gimnasio no recluta rivales.

---

## 5. Base de datos

### 5.1 Tabla `gym_progress`

```sql
CREATE TABLE gym_progress (
    id             BIGINT PRIMARY KEY,
    user_id        BIGINT NOT NULL,
    gym_id         VARCHAR(30) NOT NULL,   -- slug: bug/poison/normal/grass/flying
    current_stage  TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1-5; 5 = completado
    completed_at   TIMESTAMP NULL,
    created_at     TIMESTAMP,
    updated_at     TIMESTAMP,

    UNIQUE (user_id, gym_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 5.2 Modelo `GymProgress` (`app/Models/GymProgress.php`)

- Uses `BelongsToUser` trait (multiplayer: scope por usuario autenticado).
- `$fillable`: `user_id`, `gym_id`, `current_stage`, `completed_at`.
- `$casts`: `current_stage` → integer, `completed_at` → datetime.
- Relación: `user()`.

---

## 6. API Endpoints

### 6.1 GET `/api/gimnasios`

**Descripción**: Lista los 5 gimnasios con su estado y progreso del usuario.

**Respuesta** (200):
```json
[
  {
    "slug": "bug",
    "medalla": "Medalla Bicho",
    "tipo": 7,
    "nivel_minimo": 10,
    "nivel_jugador": 20,
    "etapa_actual": 1,
    "estado": "disponible"
  }
]
```

`estado`: `disponible` | `bloqueado` | `completado`.

### 6.2 GET `/api/gimnasios/{gym}`

**Descripción**: Detalle de un gimnasio con información general, estado, etapa actual y el nombre
de cada etapa (entrenador/líder). **NO revela el equipo rival ni el nivel rival** (sin `pokemon[]`
ni `nivel_rival`) para no ofrecer pistas de los pokémon ni de la dificultad.

**Respuesta** (200):
```json
{
  "slug": "bug",
  "medalla": "Medalla Bicho",
  "tipo": 7,
  "nivel_minimo": 10,
  "nivel_jugador": 20,
  "etapa_actual": 1,
  "estado": "disponible",
  "etapas": [
    { "etapa": 1, "nombre": "Entrenador 1" },
    { "etapa": 2, "nombre": "Entrenador 2" },
    { "etapa": 3, "nombre": "Entrenador 3" },
    { "etapa": 4, "nombre": "Líder" }
  ]
}
```

**Errores**: 404 `GimnasioNoExiste` si el slug no está en el catálogo.

### 6.3 POST `/api/gimnasios/{gym}/combatir`

**Descripción**: Inicia un combate contra la etapa actual del gimnasio.

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
  "battle_id": "battle_gimnasio_abc123",
  "redirect": "/combate?battle_id=battle_gimnasio_abc123"
}
```

**Errores**:
- 422 `GimnasioBloqueado` — "Aún no tienes nivel suficiente. Necesitas nivel {n}."
- 422 `GimnasioCompletado` — "Ya has completado este gimnasio."
- 404 `GimnasioNoExiste` — "El gimnasio solicitado no existe."

**Controlador**: `GimnasioController::combatir()` → `IniciarCombateGimnasio::iniciar()`.

---

## 7. Frontend

> ⚠️ El frontend (vistas Blade/Alpine) NO forma parte de esta iteración: lo implementa otro agente.
> Contrato de API documentado arriba. La vista espera consumir `GET /api/gimnasios` y
> `GET /api/gimnasios/{gym}` para listado/detalle, y `POST /api/gimnasios/{gym}/combatir` para
> iniciar combate (con `{team_id, formacion}`).

### 7.1 Modal de victoria (`resources/views/livewire/combate.blade.php`)

El modal de victoria se muestra cuando `$phase === 'battle_over'` y `$rewards` no está vacío. Para
gimnasios, `$rewards` incluye las claves habituales (`exp_total`, `exp_miembro`, `caramelos`) y,
si se derrotó al líder, `medalla` (nombre de la medalla ganada).

---

## 8. Dependencias entre módulos

### 8.1 `Gimnasios` → `Battle`

- `DatosPokemonBatalla` (DTO de entrada del motor de batalla)
- `EquipoBatalla::fromData()` (construye equipo desde datos de batalla)
- `AgregadoBatalla` (agregado raíz del combate)
- `Posicion` (enum vanguardia/retaguardia)

### 8.2 `Gimnasios` → `CombateEntrenadores`

- `MapeadorPokemonBatalla` (stats/tipos de pokémon Eloquent)
- `ConstruirEquipoJugador` (equipo del jugador desde Team + formación)
- `GeneradorMovimientosTipo` (movimientos sintéticos por tipo)
- `ClasificadorPosicion` (defensivo → vanguardia)
- `OtorgarRecompensasEntrenador` (recompensas ×10.0 + avistados Pokédex)

### 8.3 `Gimnasios` → `Shared`

- `TipoPokemon` (enum de tipos)
- `NivelHelper` (nivel desde experiencia)
- `ViolacionReglaNegocio` (422), `RecursoNoExiste` (404)

### 8.4 `app/Livewire/Combate` → `Gimnasios`

- `RegistrarResultadoGimnasio` (persiste progreso si ganó)
- `CatalogoGimnasios` (nombre de la medalla para el modal)

### 8.5 `app/Models/GymProgress` → `Gimnasios`

- Modelo Eloquent usado por `EloquentGymProgressRepository`.

---

## 9. Rutas

Las rutas se cargan desde `routes/gimnasios.php`, requerido desde `routes/web.php`:

```php
// routes/gimnasios.php
Route::get('/api/gimnasios', [GimnasioController::class, 'index']);
Route::get('/api/gimnasios/{gym}', [GimnasioController::class, 'show']);
Route::post('/api/gimnasios/{gym}/combatir', [GimnasioController::class, 'combatir']);
```

El archivo `src/Gimnasios/Infra/routes.php` existe como placeholder para la migración futura
(convención DDD).

---

## 10. Catálogo de gimnasios

| slug | Medalla | Tipo | Nv mín | E1 | E2 | E3 | Líder |
|---|---|---|---|---|---|---|---|
| `bug` | Medalla Bicho | Bicho | 10 | [268,266,900] | [11,15,269] | [14,12,267] | [213,212,127] |
| `poison` | Medalla Veneno | Veneno | 15 | [72,33,92] | [30,42,316] | [14,169,93] | [31,34,407] |
| `normal` | Medalla Normal | Normal | 20 | [288,113,18] | [108,40,398] | [241,53,22] | [143,242,115] |
| `grass` | Medalla Planta | Planta | 25 | [470,455,407] | [388,286,465] | [272,253,2] | [154,71,3] |
| `flying` | Medalla Volador | Volador | 31 | [426,398,123] | [472,22,468] | [227,142,169] | [630,279,130] |

Los pokémon se referencian por `species_id` (dex nacional) y se cargan de la BD al construir el
equipo; si una especie no existe en BD, se omite del equipo.

---

## 11. Diagrama de secuencia (resumen)

```
Usuario                 Frontend (Alpine)          API (GimnasioController)        Dominio/App
   │                         │                              │                          │
   │── abre gimnasios ────────│                              │                          │
   │                         │── GET /api/gimnasios ────────│                          │
   │                         │                              │── ObtenerGimnasios ──────│
   │                         │←────── JSON (5 gimnasios + estado) ─────────────────────│
   │── abre un gimnasio ──────│                              │                          │
   │                         │── GET /api/gimnasios/{gym} ──│                          │
   │                         │                              │── ObtenerGimnasioDetalle │
   │                         │                              │   (preview escalado)      │
   │                         │←────── JSON (detalle + etapas) ──────────────────────────│
   │── click "¡Combatir!" ────│                              │                          │
   │                         │── POST /combatir ────────────│                          │
   │                         │                              │── IniciarCombateGimnasio │
   │                         │                              │   (valida nivel/estado)   │
   │                         │                              │   (AgregadoBatalla+sesión)│
   │                         │←────── {battle_id, redirect} ───────────────────────────│
   │── /combate?battle_id=XX ─│                              │                          │
   │                         │── Combate::mount() ──────────│                          │
   │                         │   → nextActor() → ciclo turnos                          │
   │                         │                              │                          │
   │                         │←── endBattle() ──────────────│                          │
   │                         │   → RegistrarResultadoGimnasio (si won)                 │
   │                         │   → OtorgarRecompensasEntrenador (si won)               │
   │                         │   → medalla si líder (etapa 4)                          │
   │                         │←── Modal de victoria (rewards + medalla) ───────────────│
```
