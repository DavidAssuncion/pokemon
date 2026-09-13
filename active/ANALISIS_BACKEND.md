# ANALISIS_BACKEND — Combate de Ruta 5v5 y escalado global a 5v5

## Objetivo

Implementar combate de ruta 5v5 (pokemon salvajes), nuevo clasificador de posición
ofensiva/defensiva determinista, escalar entrenadores a 5 especies, divisor de recompensas
/3 → /5, escalar gimnasios a 5 por etapa, formación persistente del equipo y manejo de
roles deprecados.

## Archivos afectados

### Nuevos (módulo `src/CombateRuta/`)

- `src/CombateRuta/Domain/ClasificadorOfensivaDefensiva.php` — nuevo clasificador con
  `esOfensivo(DatosStats): bool` + `generarFormacion(list<DatosStats>): array` determinista
  (sin `random_int`). Fórmula: `ofensiva = atk + speed; defensiva = def + spDef + hp`.
  Edge case: si los 5 quedan en el mismo bando → mueve el de mayor valor de stat contraria.
- `src/CombateRuta/Domain/DataTransferObjects/ResultadoRuta.php` — DTO del resultado
  (victoria, capturas, recompensas).
- `src/CombateRuta/App/GeneradorEquipoRuta.php` — genera 5 pokemon del pool del hábitat con
  selección ponderada CON reemplazo (capture_rate/hatch), cada slot independiente.
- `src/CombateRuta/App/IniciarCombateRuta.php` — valida team 5 miembros, genera rival vía
  GeneradorEquipoRuta, crea AgregadoBatalla y guarda sesión con meta `['tipo' => 'ruta', ...]`.
  Sin límite diario, sin log.
- `src/CombateRuta/App/RegistrarResultadoRuta.php` — procesa resultado (sin log, sin bloqueo),
  devuelve datos modal.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php` — win: CalculadorRecompensas ×1.0
  (sin el ×2 del entrenador) + PersistirRecompensas (con capturas) + ActualizarPokedexJob
  AVISTADO por cada rival.
- `src/CombateRuta/Infra/Controllers/CombateRutaController.php` — GET `/api/habitats/{id}/ruta/rivales`
  y POST `/api/habitats/{id}/ruta/iniciar`. Patrón idéntico a EntrenadorController.
- `routes/ruta.php` — rutas del módulo.

### Modificados (producción)

- `src/CombateEntrenadores/Domain/ClasificadorPosicion.php` — delega a
  ClasificadorOfensivaDefensiva, marcado `@deprecated`.
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php` — escalar a 5 especies
  (Opción C: pool ≥5 → 5 únicas determinista; pool <5 → repetición ponderada hasta 5).
  Ahora usa ClasificadorOfensivaDefensiva + generación determinista de formación.
  Elimina dependencia de GeneradorFormacion.
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` — `desdeEquipo` prioriza:
  popup → formación persistente en Team → clasificación automática vía
  ClasificadorOfensivaDefensiva.
- `src/Exploraciones/Domain/CalculadorRecompensas.php` — divisor `/3` → `/5` con constante
  `MIEMBROS_EQUIPO = 5`. Afecta: `calcularExpPorMiembro` y `calcularExpTipoPorMiembro`.
- `src/Exploraciones/App/PersistirRecompensas.php` — docblock actualizado (era "entre 3").
  Lógica sin cambios (itera `$destinoExp->members`).
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` — añade rama `'ruta'` al match
  por `$meta['tipo']`.
- `app/Http/Controllers/PlayerController.php` — `equipos()`: condición
  `$members->count() === 3` → `>= 3` para calcular sinergia (la tabla solo tiene claves de
  2-3; con 5 devuelve null, aceptable).
- `app/Models/Team.php` — añadir `formacion` a fillable + cast `array`.
- `routes/web.php` — añadir `require __DIR__.'/ruta.php'`.
- `routes/player.php` — añadir `PATCH /teams/{team}/formacion`.

### Migraciones nuevas

- `database/migrations/20xx_add_formacion_to_teams.php` — columna `formacion`
  JSON nullable en tabla `teams`.

### Tests afectados

- `tests/Unit/Exploraciones/CalculadorRecompensasTest.php` — actualizar asserts de `/3` a `/5`.
- `tests/Unit/CombateEntrenadores/MapeadorPokemonBatallaTest.php` — verificar sin cambios
  (no usa ClasificadorPosicion directamente).
- Tests de gimnasios existentes: sin cambios (el catálogo DTO no cambia; el escalado es en
  GeneradorPokemonGimnasio).

## Tests

### Unit

1. `ClasificadorOfensivaDefensivaTest` — esOfensivo con ofensiva > defensiva, empate →
   vanguardia, formación mixta determinista, edge case todos-mismo-bando.
2. `CalculadorRecompensasTest` — actualizar asserts de /3 a /5 (nuevos valores:
   floor(51.2/5)=10, floor(49.6/5)=9 → 19; etc.).
3. `GeneradorEquipoEntrenadorTest` (nuevo o extender existente) — pool ≥5 → 5 especies;
   pool <5 → repetición; ids en formato `entrenador_*_0..4`.

### Feature

4. `IniciarCombateRutaTest` — crea batalla 5v5, meta tipo=ruta, sin límite diario,
   pool vacío → error claro.
5. `CombateRutaControllerTest` — POST con team_id y formación, validación, respuesta JSON
   con battle_id y redirect.
6. `OtorgarRecompensasRutaTest` — victoria → recompensas ×1.0 con capturas,
   derrota → sin recompensas.
7. `RegistrarResultadoRutaTest` — victoria/derrota, sin log persistido, datos modal.
8. `TeamFormacionTest` (Feature) — PATCH persiste formación, ConstruirEquipoJugador la consume.
9. `GimnasioGeneradorEscaladoTest` (Feature) — GeneradorPokemonGimnasio produce 5 pokemon
   con formación mixta.

### Acceptance

No necesario para esta tarea (la cobertura Unit+Feature es suficiente).

## Diseño

### ClasificadorOfensivaDefensiva (Domain puro, puede vivir en `src/CombateRuta/Domain/`)

```php
final class ClasificadorOfensivaDefensiva {
    public function esOfensivo(DatosStats $stats): bool; // ofensiva > defensiva
    public function generarFormacion(array $statsPorSlot): array; // list<Posicion>
}
```

- `esOfensivo`: `($atk + $speed) > ($def + $spDef + $hp)` → RETAGUARDIA; empate → VANGUARDIA.
- `generarFormacion`: clasifica cada slot, si todos iguales → mueve el de mayor stat
  contraria al otro bando. Determinista, sin random.

### GeneradorEquipoRuta (App — usa Eloquent)

- Recibe `Habitat` → `PoolHabitat::ponderado()` → `ColeccionPoolPonderado`.
- 5 slots independientes con reemplazo vía `elegirConAleatorio(fn => mt_random_float(0,1))`.
- Sin pesos de hatch omitidos: `capture_rate ≤ 0` excluido; `hatch ≤ 0` → divisor 1
  (replicado de `PokemonDelPool::peso()`).
- Si pool vacío → retorna `[]`.
- Mapea cada id a `Pokemon` con stats+types → `MapeadorPokemonBatalla::desdePokemon()`.
- Aplica `ClasificadorOfensivaDefensiva::generarFormacion()`.

### IniciarCombateRuta

- Valida team con 5 miembros (si <5 → `ViolacionReglaNegocio`).
- Genera rival vía GeneradorEquipoRuta.
- Crea `AgregadoBatalla` y guarda sesión.
- Meta: `['tipo' => 'ruta', 'habitat_id' => ..., 'nivel' => ..., 'user_id' => ..., 'team_id' => ...]`.

### PresentadorResultadoBatalla — rama 'ruta'

- Sin log, sin bloqueo diario.
- Si gana → `OtorgarRecompensasRuta::otorgar()` con ×1.0.
- Capturas se deciden por la función de captura existente (se reutiliza el callback).

### Formación persistente (F)

- Columna `formacion` JSON nullable en `teams` (mapa slot → vanguardia|retaguardia).
- `ConstruirEquipoJugador::desdeEquipo` prioriza: formacion popup > formacion Team > auto.
- Endpoint `PATCH /teams/{team}/formacion` actualiza la columna.

### Gimnasios 5v5 (decisión: escalar en el generador)

**Decisión**: NO modificar el catálogo `CatalogoGimnasios` (18×4 entradas). Escalar en
`GeneradorPokemonGimnasio::porPosicion()` con repetición determinista de los members del DTO
hasta llegar a 5. Esto:
- Evita modificar 72 entradas de catálogo.
- Mantiene la curación manual de los 3-4 members originales.
- Garantiza formación mixta (vanguardia ≥ 1, retaguardia ≥ 1) porque el DTO siempre
  tiene al menos 1 de cada.
- Repite por ciclos del roster: [V1, V2, R1] → [V1, V2, R1, V1, V2] = 3V + 2R.

### Roles deprecados (G)

- `ClasificadorPosicion::esDefensivo` → delega a `ClasificadorOfensivaDefensiva`.
- `GeneradorFormacion` → `@deprecated` (ya no se usa en entrenadores; formación es
  determinista por stats).
- `PlayerController::equipos()` → `$members->count() === 3` → `>= 3`.
  La tabla `SinergiaEquipo` solo tiene claves de 2-3 chars; con 5 roles devuelve null
  (sin sinergia mostrada), que es aceptable.

## Riesgos

- **Gimnasios 5v5**: el escalado por repetición en GeneradorPokemonGimnasio puede generar
  el mismo pokemon 2-3 veces en un equipo. Aceptable: es un rival controlado por catálogo.
  Verificar que la BD tiene las especies en `pokemon.species_id` (ya está en el catálogo).
- **Recompensas /5**: test `CalculadorRecompensasTest` usa valores exactos calculados con /3.
  Actualizar TODOS los asserts afectados. Peligro: si otros tests en la suite asumen /3
  (ej. `MultiplicadorRecompensasTest` usa ratios relativos ×5, que se preservan).
- **Pool vacío en ruta**: si un hábitat no tiene pokemon en un nivel, `IniciarCombateRuta`
  debe fallar con mensaje claro (no battle_id con equipo rival vacío).
- **ClasificadorPosicion deprecated**: otros callers podrían existir. Grep mostrará todos.
  Si hay callers fuera de los identificados, actualizarlos.
- **SinergiaEquipo con 5 roles**: la clave tendria 5 chars, no encontrará match → null.
  Aceptable, pero notificar al frontend que la sinergia de 5 es null.
- **Migración formacion**: si el frontend aún no consume el endpoint, no afecta.
