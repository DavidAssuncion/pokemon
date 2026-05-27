# Módulo: Pokémon

## Descripción
Catálogo y gestión de pokémon. Cada pokémon tiene tipos, estadísticas, evoluciones posibles y movimientos configurables.

## Estado: PARCIALMENTE IMPLEMENTADO ⚠️

### Features actuales
- Seeder que importa pokémon desde CSVs
- Modelos Eloquent para Pokémon, Tipos, Estadísticas y Evoluciones
- Relaciones con hábitats (a través de familias evolutivas)

### Features pendientes
- [ ] Vista de Pokedex con listado de pokémon
- [ ] Detalle de pokémon (imagen grande, descripción, tipos, stats)
- [ ] Cálculo de experiencia requerida para evoluciones
- [ ] Sistema de movimientos configurables
- [ ] Asociación de pokémon a entrenador/nivel

### Estructura de datos
```php
Pokemon
  - id
  - name
  - species_id
  - capture_rate
  - base_experience
  - height
  - weight
  - hatch (turns to hatch)

PokemonType
  - pokemon_id
  - type (enum: FUEGO, AGUA, PLANTA, ...)
  - slot (1 o 2 para tipos duales)

PokemonStat
  - pokemon_id
  - stat (enum: HP, ATTACK, DEFENSE, SPEED, ...)
  - base_stat
  - effort

PokemonEvolution
  - evolved_species_id (FK Pokemon)
  - evolves_from_species_id
  - evolution_chain_id
  - minimum_level
```

### Evoluciones y Experiencia
**Regla de evolución**:
- Un pokémon evoluciona cuando alcanza el `minimum_level` requerido
- Para pokémon con múltiples tipos, se requiere cumplir condiciones para CADA tipo

**Ejemplo**:
- Charmander (Fuego) → Charmeleon (min_level: 16, tipo: Fuego)
- Charmeleon (Fuego) → Charizard (min_level: 36, tipos: Fuego + Volador)
  - Requiere experiencia de nivel 15→16 para Fuego + 35→36 para Volador

### Archivos clave
- `app/Models/Pokemon.php`
- `app/Models/PokemonType.php`
- `app/Models/PokemonStat.php`
- `app/Models/PokemonEvolution.php`
- `database/seeders/PokemonSeeder.php`

### CSVs de entrada
- `storage/data/pokemon.csv`
- `storage/data/pokemon_species.csv`
- `storage/data/pokemon_stats.csv`
- `storage/data/pokemon_types.csv`
- `storage/data/pokemon_evolution.csv`

---
Actualizado: 2026-05-27
