# TASK-047 — Aprobación

- **ID de tarea:** TASK-047
- **Estado:** APROBADO
- **Plan:** v1 (seeder de mazmorras)
- **Fecha:** 2026-11-16
- **Texto literal de la aprobación:** `Aprobar plan v1`

## Alcance autorizado

1. Crear `database/seeders/MazmorraSeeder.php`.
2. Registrar `MazmorraSeeder::class` en `database/seeders/CatalogoSeeder.php`
   después de `PokemonHabitatSeeder::class`.
3. Ejecutar el seeder sobre la BD local de desarrollo.
4. Verificar los datos generados con consultas de solo lectura.
5. No modificar ningún otro archivo, no crear ni ejecutar tests, no tocar el pivote
   `pokemon_habitat` ni el hábitat 49.
