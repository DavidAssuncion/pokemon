# Módulo: Reclutados (Equipos de Exploración)

## Descripción
Pantalla de gestión de equipos de exploración. El usuario recluta pokémon y forma equipos de 3 miembros con comportamientos específicos.

## Estado: PLANIFICADO 📋

### Features planificadas

#### Pantalla principal (2 columnas)
```
[Equipos de Exploración] | [Pokémons Disponibles]
```

**Columna izquierda**: Listado de equipos creados
- Nombre del equipo
- 3 pokémon asignados
- Comportamientos de cada miembro
- Botones: Enviar a explorar, Editar, Eliminar

**Columna derecha**: Pokémon sin asignar
- Ícono y nombre
- Tipos
- Estado: Disponible / En equipo
- Botón: Asignar a equipo

### Comportamientos de pokémon en equipo
| Comportamiento | Efecto en batalla | Efecto en objetos | Descripción |
|---|---|---|---|
| **Vanguardia** | Busca peleas activamente | Normal | Aumenta encuentros. +25% daño |
| **Combatiente** | No busca, pero lucha | Normal | Neutro. Defensa normal |
| **Recolector** | -50% habilidades | +50% objetos | Enfocado en recolección |
| **Soporte** | Apoya, curativo | Normal | Cura post-combate, apoya en batalla |

### Restricciones
- **Un equipo requiere 3 pokémon**
- **Un pokémon solo puede estar en 1 equipo**
- Cada equipo puede ser enviado a explorar 1 hábitat por sesión

### Estructura de datos
```php
Team
  - id
  - name
  - created_at

TeamMember
  - team_id (FK)
  - pokemon_id (FK)
  - slot (1, 2, 3)
  - behavior (enum: VANGUARDIA, COMBATIENTE, RECOLECTOR, SOPORTE)
```

### Archivos a crear
- `src/Reclutados/Domain/TeamEntity.php`
- `src/Reclutados/Domain/TeamMemberEntity.php`
- `src/Reclutados/App/CrearEquipo.php`
- `src/Reclutados/App/AsignarPokemonAEquipo.php`
- `src/Reclutados/Infra/TeamRepository.php`
- `resources/views/reclutados/index.blade.php`
- `routes/reclutados.php`

---
Actualizado: 2026-05-27
