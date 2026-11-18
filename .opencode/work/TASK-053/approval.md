# TASK-053 — Aprobación

- **ID de tarea:** TASK-053
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-17
- **Texto literal de la aprobación:** `Aprobar plan v1`

## Alcance autorizado

1. Dominio: `CatalogoLideres`, `Gimnasio`, `CatalogoGimnasios` y
   `EloquentGymCatalogoRepository`.
2. Assets: 74 PNG en `public/images/trainers/`.
3. Manifiesto `resources/data/entrenadores_sprites.json`, resolutor
   `app/Support/UI/SpritesEntrenador.php` y componente
   `resources/views/components/trainer-sprite.blade.php`.
4. Vistas de hábitats (`columna-derecha`, `columna-izquierda`), panel de ruta y
   botón lateral; controladores `GimnasiosViewController` y `HabitatsController`.
5. Vista `resources/views/gimnasios/show.blade.php` **solo después** de que
   TASK-050 esté cerrada.
6. `THIRD_PARTY_NOTICES.md` y documentación de gimnasios.
7. Verificación estática y revisión independiente.

## Exclusiones

- No tocar `resources/css/*` (TASK-051).
- No tocar tests, migraciones, datos, `app/Models/Gym.php`, `GymStage.php`,
  `GymSeeder.php`, `EtapaGimnasio`, `DetalleGimnasio`, `GimnasioResumen` ni
  `AdminGymController`.
- No crear endpoints ni cambiar el shape del JSON de gimnasios.
- No leer `.env` ni secretos.

### v2 — APROBADO

- **Fecha:** 2026-11-17.
- **Texto literal:** `Ponlos a 80x80, lo permiten tanto en gym como en rutas`.
- **Alcance:** subir a 80×80 los sprites de gimnasios y rutas; el botón lateral
  «Entrenadores» se mantiene a 40 px; actualizar manifiesto y componente.
