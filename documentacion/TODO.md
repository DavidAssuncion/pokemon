# TODO - Tareas Pendientes

Priorización de features por módulo y fase de desarrollo.

## P1: CRÍTICO (Fase actual)

### Habitats (✓ COMPLETO)
- [x] Listado de provincias en pestañas
- [x] Visualización de hábitats
- [x] Página de detalle con pokémon por niveles
- [x] Servidor de imágenes

### Pokemon (Base)
- [ ] Crear Pokedex (listado de pokémon con búsqueda)
- [ ] Página de detalle individual (imagen, stats, tipos, descripción)
- [ ] Integrar sistema de tipos (enum y colores)
- [ ] Mostrar información de evolución en detalle

### Sistema de Entrenador (Base)
- [ ] Crear modelo Trainer y TrainerPokemon
- [ ] Migración para tabla de entrenador
- [ ] Inicializar entrenador en sesión (localStorage o anónimo)

---

## P2: ALTA (Siguiente fase)

### Reclutados
- [ ] Crear módulo src/Reclutados
- [ ] Pantalla de 2 columnas (Equipos | Pokémon disponibles)
- [ ] Crear equipo con 3 pokémon
- [ ] Asignar comportamientos (Vanguardia, Combatiente, Recolector, Soporte)
- [ ] Editar/eliminar equipos

### Pokemon (Avanzado)
- [ ] Sistema de experiencia y niveles
- [ ] Lógica de evoluciones multi-tipo
- [ ] Configuración de movimientos

### BD & Migraciones
- [ ] Migración: Teams
- [ ] Migración: TeamMembers
- [ ] Migración: TrainerPokemon
- [ ] Relaciones Eloquent

---

## P3: MEDIA (Tercera fase)

### Exploración
- [ ] Crear módulo src/Exploracion
- [ ] Pantalla de selección de hábitat
- [ ] Motor de exploración (exp, objetos, encuentros)
- [ ] Visualización de resultados

### Combate
- [ ] Modelo base de combate
- [ ] Sistema de cálculo de daño
- [ ] Encuentros en exploración

---

## P4: BAJA (Futuro)

- [ ] Sistema de objetos/ítems
- [ ] Tienda
- [ ] Estadísticas avanzadas
- [ ] Multijugador (si aplica)

---

## Checklist por vista/ruta

### Vistas existentes
- [x] /habitats - Listado
- [x] /habitats/{id} - Detalle

### Vistas pendientes
- [ ] /pokedex - Catálogo de pokémon
- [ ] /pokemon/{id} - Detalle individual
- [ ] /reclutados - Gestión de equipos
- [ ] /exploracion - Selección y resultados
- [ ] /perfil - Perfil del entrenador

---
Actualizado: 2026-05-27
