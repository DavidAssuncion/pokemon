# Módulo: Exploración

## Descripción
Envío de equipos de exploración a hábitats. Los equipos ganan experiencia y encuentran objetos según el hábitat y comportamientos.

## Estado: PLANIFICADO 📋

### Features planificadas

#### Flujo de exploración
1. Entrenador selecciona equipo desde **Reclutados**
2. Elige un hábitat para explorar
3. Equipo recibe: experiencia, objetos, encuentros
4. Resultado mostrado en pantalla con recompensas

### Comportamientos en exploración
| Comportamiento | Encuentros | Objetos | Exp |
|---|---|---|---|
| Vanguardia | +30% | Normal | +25% |
| Combatiente | Normal | Normal | Normal |
| Recolector | -30% | +50% | -20% |
| Soporte | Normal | Normal | +10% (curativo) |

### Recompensas por hábitat
- **Experiencia base**: por pokémon en el equipo
- **Objetos**: según rarity del hábitat
- **Encuentros**: pokémon salvajes (en futuro, combates)

### Archivos a crear
- `src/Exploracion/App/ExplorarHabitat.php`
- `src/Exploracion/Infra/ExploracionRepository.php`
- `resources/views/exploracion/resultado.blade.php`

---
Actualizado: 2026-05-27
