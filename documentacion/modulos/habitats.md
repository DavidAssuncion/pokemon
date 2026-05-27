# Módulo: Habitats

## Descripción
Gestión de provincias y hábitats. Los hábitats contienen familias de pokémon organizadas en **3 niveles de evolución**:
- Nivel 1: Forma inicial
- Nivel 2: Primera evolución (o secundaria)
- Nivel 3: Forma final

## Estado: IMPLEMENTADO ✓

### Features actuales
- Listado de 7 provincias en pestañas
- Visualización de hábitats por provincia
- Imagen representativa por hábitat
- Página de detalle de hábitat con pokémon agrupados por nivel evolutivo

### Rutas
- `GET /habitats` - Listado con pestañas de provincias
- `GET /habitats/{id}` - Detalle de hábitat con imagen y pokémon por niveles
- `GET /habitats-img/{id}.webp` - Servidor de imágenes de hábitat

### Estructura de datos
- **Province**: id, name
- **Habitat**: id, name, province_id, image_path
- **Pokemon**: id, name, species_id, tipos, stats (relacionados a hábitats)
- **PokemonEvolution**: evoluciones por familia, niveles mínimos

### Funcionalidad por nivel
| Nivel | Descripción |
|-------|------------|
| 1 | Pokémon en forma inicial |
| 2 | Pokémon en forma intermedia (si aplica) |
| 3 | Pokémon en forma final |

**Reglas de asignación (HabitatRepository::getEvolutionLevel)**:
- 1 especie → nivel 2
- 2 especies → niveles 2 y 3
- 3+ especies → niveles 1, 2, 3

### Archivos clave
- `src/Habitats/Domain/` - Entidades y colecciones
- `src/Habitats/App/ObtenerHabitatDetalle.php` - Use-case
- `src/Habitats/Infra/HabitatRepository.php` - Acceso a datos
- `resources/views/habitats/` - Vistas

### Pendientes
- [ ] Filtrado de pokémon por tipo dentro del hábitat
- [ ] Información adicional del hábitat (clima, terreno)

---
Actualizado: 2026-05-27
