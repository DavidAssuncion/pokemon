# Módulo: Sistema de Entrenador

## Descripción
Perfil del jugador (entrenador) y progreso general. Gestiona nivel global, pokémon capturados y equipo activo.

## Estado: PLANIFICADO 📋

### Features planificadas

#### Concepto de "Entrenador"
- Sin autenticación obligatoria (localStorage o sesión anónima)
- Perfil local del jugador
- Registro de pokémon capturados y niveles

### Datos del Entrenador
```php
Trainer
  - id
  - name (default: "Entrenador")
  - level (nivel global, afecta evoluciones)
  - experience (total)
  - pokemon_caught (conteo)
  - created_at

TrainerPokemon (relación n:m)
  - trainer_id (FK)
  - pokemon_id (FK)
  - level (1-100)
  - experience (para este nivel)
  - status (CAPTURADO, EVOLUCIÓN_PENDIENTE, ...)
  - moveset (JSON con movimientos configurados)
```

### Sistema de Experiencia y Niveles
**Para pokémon individuales**:
- Cada pokémon tiene su propio nivel (1-100)
- Gana experiencia en exploración/combate
- Evoluciona cuando alcanza el `minimum_level` requerido

**Requisito de evolución multi-tipo**:
Ejemplo: Charmander → Charmeleon → Charizard
- Charmeleon requiere: nivel 16 + tipo FUEGO
- Charizard requiere: nivel 36 + tipos FUEGO y VOLADOR
- El pokémon debe tener experiencia suficiente para AMBOS tipos

### Pendientes
- [ ] Pantalla de perfil del entrenador
- [ ] Historial de capturados
- [ ] Estadísticas de exploración

### Archivos a crear
- `app/Models/Trainer.php`
- `app/Models/TrainerPokemon.php`
- `src/Entrenador/App/ObtenerPerfilEntrenador.php`
- `src/Entrenador/Infra/TrainerRepository.php`

---
Actualizado: 2026-05-27
