# Análisis Backend — Detalle reclutado + alimentación caramelos de tipo + evolución

## Contexto verificado

- `caramelos_tipo.tipo` guarda el **label español** del tipo (p. ej. 'Fuego', 'Volador', 'Eléctrico') — confirmado en `src/Exploraciones/App/ProcesarExploracionService.php:254` (`$tipo->tipo_nombre`).
- `reclutados.exp` es JSON `['total' => int]` (cast array). Se actualiza en `ProcesarExploracionService` / `CalcularRecompensasJob`.
- `NivelHelper` (curva ×10): `experienciaParaNivel(n)` = 10·n³; `nivelDesdeExperiencia(e)`.
- Umbrales reales (curva ×10, NO 5000): nivel 1→2 = 70; 15→16 = 7210; 35→36 = 37810.

## Resolución de `siguienteEvolucion` (pregunta crítica)

Inspeccionados datos reales (dev DB, solo lectura):

- `pokemon.evolution_chain_id` NO sirve como fuente única: la cadena 2 (charmander) contiene además mega/gmax (ids 10034/10035/10196) con el MISMO `species_id` (6); ordenar por `species_id` daría charizard-mega-x como "siguiente" de charizard. ✗
- `pokemon_evolution` SÍ sirve:
  - `evolves_from_species_id` = `species_id` del pokémon actual (charmander=4, charmeleon=5).
  - `evolved_species_id` = **FK directa a `pokemon.id`** de la siguiente etapa (4→5 charmeleon, 5→6 charizard). Verificado: `Pokemon::find(6)` = charizard base.
  - Las formas alternas (mega/gmax) son filas extra con `evolved_species_id` ≥ 10000 y `minimum_level` ≥ al canónico; **no** hay filas con `evolves_from_species_id` = 6 (charizard no evoluciona → null). ✓

**Regla implementada**: `PokemonEvolution::where('evolves_from_species_id', $pokemon->species_id)->where('evolved_species_id', '<', 10000)->orderBy('minimum_level')->orderBy('evolved_species_id')->first()` → `Pokemon::find($evolucion->evolved_species_id)`. Fallback a `evolution_chain_id` NO necesario (la tabla pokemon_evolution es la fuente completa y correcta).

## Qué voy a tocar

| Archivo | Acción | Propósito |
|---|---|---|
| `database/migrations/2026_08_28_000004_create_reclutados_exp_tipo_table.php` | Crear | Tabla exp por tipo por reclutado (UNIQUE reclutado_id+tipo, CASCADE). |
| `app/Models/ReclutadoExpTipo.php` | Crear | Modelo estándar (fillable, casts, belongsTo Reclutado). |
| `app/Models/Reclutado.php` | Modificar | Relación `expTipos(): HasMany`. |
| `src/Reclutamiento/App/ServicioEvolucion.php` | Crear | 5 métodos estáticos: `umbralParaNivel`, `siguienteEvolucion`, `tiposRequeridos`, `requisitos`, `puedeEvolucionar`. (App layer, mismo patrón que `ServicioCaptura` — importa App\Models permitido; Deptrac no matchea `src/*/App`.) |
| `app/Http/Controllers/ReclutadoController.php` | Crear | `show`, `darCaramelo`, `evolucionar` (artisan make:controller). |
| `routes/player.php` | Modificar | 3 rutas `/reclutado/...` con route-model binding. |
| `resources/views/reclutado/show.blade.php` | Crear | Vista mínima funcional (backend handoff; frontend la pulirá). |
| `tests/Feature/ReclutadoEvolucionTest.php` | Crear | Feature tests (abajo). |

## Tests a escribir

1. `siguienteEvolucion`: charmander→charmeleon, charmeleon→charizard, charizard→null.
2. `umbralParaNivel`: 1→2 = 70, 15→16 = 7210, 35→36 = 37810 (fórmula, no el ejemplo del ticket).
3. `tiposRequeridos`: charmeleon → ['Fuego']; charizard → ['Fuego', 'Volador'].
4. `darCaramelo`: descuenta pool + suma 100; 422 sin caramelos; 422 tipo no requerido.
5. `evolucionar`: 422 sin cumplir; consume exp (borra filas a 0); cambia pokemon_id; despacha ActualizarPokedexJob('RECLUTADO').
6. `GET /reclutado/{id}` → 200 con datos de vista (requisitos con necesario/actual/caramelosDisponibles/slug).

## Riesgos

- **Accents en tipos**: 'Eléctrico'/'Psíquico' — slug con `Str::ascii()` + `strtolower` → 'electrico'/'psiquico'. Keys de requisitos por tipo_nombre exacto (misma cadena que caramelos_tipo).
- **FK `evolved_species_id`** apunta a `pokemon.id` (no a species_id): usar `Pokemon::find`, no `where('species_id')`.
- **Formas alternas** (≥10000): excluidas con `where('evolved_species_id', '<', 10000)` para que mega/gmax nunca sean "siguiente evolución".
- **exp null** en reclutados recién capturados: `$reclutado->exp['total'] ?? 0` → nivel 1.
- **No tocar dev DB**: tests con RefreshDatabase sobre `laravel_test` (phpunit.xml).
- **Concurrencia** de dar-caramelo: operaciones atómicas con `decrement`/`increment`; updateOrCreate con unique key.
