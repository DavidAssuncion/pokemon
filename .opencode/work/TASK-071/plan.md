# TASK-071 — Plan

- **ID:** TASK-071
- **Versión:** v3
- **Estado:** APROBADO E IMPLEMENTADO
- **Fecha:** 2026-11-23
- **Base:** análisis y arquitectura de esta misma tarea.

## Cambios respecto a v2

- Cada oleada pasa de 1 combate a **4 combates**: 3 reclutas + 1 jefe.
- El jefe de las oleadas 1 y 2 es un **jefe secundario**; el de la oleada 3 es el
  **capitán principal**.
- Total: **12 combates por facción** (3 oleadas × 4), 60 en total.
- Todos los rivales tienen **exactamente el nivel del jugador**; se elimina el bonus
  `+2/+4`.
- IV/EV con el mismo sistema que gimnasio:
  - Oleada 1: reclutas `TopUno`; jefe `TopDos`.
  - Oleada 2: reclutas `TopDos`; jefe `Completo`.
  - Oleada 3: reclutas `Completo`; capitán `Completo`.
- El progreso necesita `combate_actual` además de `oleada_actual`.
- El detalle pasa a mostrar 3 oleadas con 4 combates cada una.

## Objetivo

Retirar por completo las torres temporales y sustituirlas por hábitats temporales de
equipos rivales, integrados como una provincia virtual «Temporal» dentro de
`/habitats`, con 5 facciones (Rocket, Magma, Aqua, Galaxia y Skull), 3 oleadas de 4
combates por facción, desbloqueo por medallas, borde e imagen por facción y
desaparición del hábitat al completarlo.

## Alcance

### Dentro

- Eliminar `src/Torres`, rutas, vistas, modelos, seeder y migraciones no trackeadas.
- Quitar todas las referencias de producción a torres.
- Crear el módulo `src/HabitatsTemporales` (Domain/App/Infra/Presentation).
- Catálogo en código de 5 facciones, 3 oleadas y 4 combates por oleada.
- Plantillas de reclutas reutilizadas entre oleadas con rotación; jefes secundarios en
  oleadas 1-2 y capitán principal en la 3.
- Nivel rival = nivel del jugador; IV/EV con perfiles de gimnasio.
- Progreso por usuario con `oleada_actual` y `combate_actual`.
- Provincia «Temporal» virtual en la portada de hábitats, con pestaña, tarjetas,
  imágenes, borde por facción y progreso.
- Detalle de facción tipo gimnasio con 3 oleadas × 4 combates, sprites, estados y
  combate.
- Combate 3v3 reutilizando el motor actual y el generador de gimnasios.
- Recompensa EXP ×3 + caramelo de tipo; sin reclutamiento.
- Integración en `PresentadorResultadoBatalla`.
- Verificación estática y criterios manuales.

### Fuera (v1)

- CRUD/admin de facciones u oleadas (catálogo en código).
- Selector de formación con popup como el de gimnasios (se usa selección simple;
  atajo marcado).
- Limpieza de las tablas `torre_eventos`/`torre_progreso` de la BD local.
- Reclutamiento de legendarios o especies de facción.
- Tests (política del proyecto: testing diferido y no se ejecuta).
- Cambios en el sistema de exploraciones, rutas o spawns salvajes.
- Nuevas provincias/hábitats persistidos en BD.
- 60 rosters únicos: se reutilizan 3 plantillas de recluta por facción y 3 jefes.

## Decisiones cerradas

| Tema | Valor |
|---|---|
| Facciones | Rocket, Magma, Aqua, Galaxia, Skull |
| Oleadas por facción | 3 (inicio, mitad, final) |
| Combates por oleada | 4: 3 reclutas + 1 jefe |
| Jefes | Oleadas 1-2: secundarios; oleada 3: capitán principal |
| Desbloqueo de facción | 2, 3, 4, 5 y 6 medallas completadas |
| Puerta oleada 2 | 9 medallas |
| Puerta oleada 3 | 15 medallas |
| Nivel rival | Exactamente el nivel del jugador |
| IV/EV oleada 1 | Reclutas TopUno; jefe TopDos |
| IV/EV oleada 2 | Reclutas TopDos; jefe Completo |
| IV/EV oleada 3 | Reclutas Completo; capitán Completo |
| Recompensa | EXP ×3 + caramelo de tipo |
| Derrota | Sin avance y sin cooldown |
| Provincia | Virtual; se oculta sin facciones pendientes |
| Torres | Código y migraciones fuera; BD local intacta |

## Fases y tareas

### Fase 0 — Retirada de torres

1. Borrar los archivos de `src/Torres`, controlador de vista, modelos, seeder,
   migraciones y vistas (listados en `architecture.md` §8.1).
2. Quitar referencias en `routes/web.php`, `layouts/app.blade.php`,
   `PresentadorResultadoBatalla.php`, `CatalogoSeeder.php`, `Habitat.php`,
   `config/habitats.php` y `columna-derecha.blade.php`.
3. `grep -rin 'torre' src app routes resources config database/seeders --include='*.php'
   --include='*.blade.php'` sin referencias de producción a la funcionalidad (se
   toleran palabras como «torrencial»).
4. No ejecutar `migrate:rollback` ni tocar la BD local.

### Fase 1 — Dominio

5. Crear `FaccionRival`, `OleadaRival`, `CombateRival`, `CatalogoFaccionesRivales`,
   `ReglasHabitatTemporal`, colecciones tipadas, `EstadoCombate`, DTOs y excepciones.
6. Crear las interfaces `ProgresoHabitatTemporalRepositoryInterface` y
   `MedallasRepositoryInterface`.
7. Rellenar el catálogo con las 5 facciones, 3 plantillas de recluta por facción, 2
   jefes secundarios y 1 capitán; rotar plantillas por oleada.
8. Implementar `perfilIvsEvs()` y `nivelRival()` en `ReglasHabitatTemporal`.

### Fase 2 — Persistencia

9. Migración `create_habitat_temporal_progreso_table` con `oleada_actual`,
   `combate_actual`, `completed_at` y unique `(user_id, faccion_slug)`.
10. Modelo `App\Models\HabitatTemporalProgreso` con casts.
11. `EloquentProgresoHabitatTemporalRepository` (`obtener`, `registrarVictoria`).
12. `EloquentMedallasRepository` (contar `gym_progress.current_stage = 5`).
13. Bindings en `AppServiceProvider`.

### Fase 3 — Casos de uso

14. `ObtenerProvinciaTemporal`: medallas + progreso + catálogo → provincia o `null`.
15. `ObtenerDetalleFaccion`: facción visible + estados de 3 oleadas × 4 combates.
16. `IniciarCombateHabitatTemporal`: validaciones, rival al nivel del jugador, perfil
    IV/EV por oleada/jefe, jugador, sesión y meta.
17. `RegistrarResultadoHabitatTemporal`: avance de combate/oleada, completado y
    anti-IDOR.

### Fase 4 — API, rutas y assets

18. `HabitatTemporalController` (`show`, `combatir`).
19. `routes/habitats_temporales.php` + carga en `routes/web.php`.
20. Ruta de imágenes `/habitats-img/facciones/{slug}.webp`.
21. Ruta de vista `/habitats/temporales/{faccion}` y
    `HabitatsTemporalesViewController`.
22. `whereNumber('id')` en `/habitats/{id}` para evitar colisiones.

### Fase 5 — Presentación

23. Inyectar `ObtenerProvinciaTemporal` en `HabitatsController@index` y pasar
    `provinciaTemporal`.
24. Añadir la pestaña «Temporal» y las tarjetas con borde, imagen, progreso y estado en
    `resources/views/habitats/index.blade.php`.
25. Crear `resources/views/habitats/temporales/show.blade.php` tipo gimnasio, con
    cabecera de facción, 3 oleadas, 4 combates por oleada, sprites, estados, badge de
    jefe y selector de equipo.
26. Retirar «Torres» de la navbar.

### Fase 6 — Batalla

27. Añadir la rama `habitat_temporal` en `PresentadorResultadoBatalla`; quitar la rama
    `torre`.
28. Otorgar recompensas ×3 con dificultad de facción y logs de combate/oleada/
    completado.
29. Verificar que el meta de sesión, el avance y el anti-IDOR funcionan.

### Fase 7 — Verificación

30. `php -l` sobre todos los PHP nuevos/modificados.
31. `vendor/bin/pint --dirty --format agent`.
32. `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` (nivel 6).
33. `php artisan route:list --path=habitats-temporales` y `--path=torres` (sin rutas de
    torres).
34. `php artisan migrate` (solo la migración nueva) en el entorno local.
35. Revisión manual de los criterios de aceptación.

## Criterios de aceptación

1. Con 2 medallas, `/habitats` muestra la pestaña «Temporal» con Team Rocket; no muestra
   Magma, Aqua, Galaxia ni Skull.
2. La tarjeta de Rocket lleva borde rojo `#D32F2F`, imagen (o fallback) 3:2 y
   «Oleada 1/3 · Combate 1/4».
3. Con 3/4/5/6 medallas aparecen Magma/Aqua/Galaxia/Skull, en ese orden.
4. El detalle de Rocket muestra 3 oleadas con 4 combates cada una. La oleada 1 tiene el
   combate 1 disponible; los combates 2-4 bloqueados por orden.
5. Todos los rivales tienen exactamente el nivel del jugador (sin +2/+4).
6. Oleada 1: reclutas con perfil `TopUno`; el jefe (combate 4, Proton) con `TopDos`.
7. Oleada 2 (9 medallas): reclutas `TopDos`; el jefe (Ariana) con `Completo`.
8. Oleada 3 (15 medallas): reclutas `Completo`; el capitán (Giovanni) con `Completo`.
9. Ganar el combate 1 avanza a 2/4; ganar el 4 avanza a la siguiente oleada (o completa
   la facción si era la oleada 3).
10. Al completar la oleada 3/combate 4, el hábitat desaparece y su detalle responde 404.
11. Si no quedan facciones pendientes, la pestaña «Temporal» no se renderiza.
12. La derrota no avanza ni bloquea; se puede reintentar.
13. Las recompensas de victoria muestran EXP ×3 y caramelos; no hay reclutamiento.
14. `/torres` no existe; la navbar no muestra «Torres»; no quedan clases/imports de
    `Src\Torres`.
15. Las provincias y hábitats salvajes siguen funcionando igual.
16. PHPStan nivel 6 sin errores nuevos; Pint limpio; `php -l` OK.

## Verificación

```bash
# Sintaxis
find src/HabitatsTemporales app/Http/Controllers app/Models \
  -name '*.php' -print0 | xargs -0 -n1 php -l

# Estilo
vendor/bin/pint --dirty --format agent

# Análisis estático
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

# Rutas
php artisan route:list --path=habitats-temporales
php artisan route:list --path=torres

# Migración local (solo la nueva)
php artisan migrate

# Referencias residuales
grep -rin 'Src\\Torres\|TorreEvento\|TorreProgreso\|/torres' src app routes resources config database/seeders
```

Sin tests: no se crean, actualizan ni ejecutan.

## Archivos autorizados

Los listados en `architecture.md` §9. Cualquier archivo fuera de esa lista requiere una
nueva versión del plan.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| 60 combates (12 × 5) | Plantillas de recluta reutilizadas con rotación; el contenido escala por IV/EV y jefes. |
| Detalle con 12 tarjetas | Secciones por oleada en acordeón/columnas; solo el combate actual tiene botón. |
| Archivos compartidos con cambios locales | Edición aditiva + `git diff` antes/después; no revertir. |
| Assets de facción/capitanes ausentes | Fallback de imagen y sprite; el contenido funciona igual. |
| Balance de 60 combates | Nivel exacto del jugador + perfiles IV/EV por oleada; ajuste en constantes. |
| Colisión de rutas `/habitats/{id}` | `whereNumber('id')` y ruta temporal con 2 segmentos. |
| Restos de torres | Búsqueda global y `route:list`; sin migración de limpieza por decisión del desarrollador. |
| PHPStan por deuda ajena | Comparar con el estado previo; solo se exige no introducir errores nuevos en el alcance. |

## Corrección posterior (v3.1)

- El detalle muestra la oleada actual completa (4 entrenadores) con los estilos de las
  etapas de gimnasio; las oleadas futuras no se envían.
- El modal de formación se comparte con gimnasios (`partials/formacion-combate`):
  equipo, formación, comparación de PC y leyenda IV/EV. El rival solo se muestra como
  PC agregado, sin composición.
- La victoria vuelve al hábitat (`/habitats/temporales/{slug}`) o a `/habitats` si se
  completa, no a gimnasios.
- Las facciones con la oleada actual bloqueada por medallas no se listan; su detalle
  responde 404 y reaparecen cuando se alcanza el hito.
- La UI no muestra «Oleada», «bloqueada» ni el texto del requisito de medallas.
- La vista usa `max-w-6xl`.
- Las imágenes de facción se resuelven por nombre base y extensión (`webp/png/jpg/jpeg`).

## Aprobación

- **Fecha:** 2026-11-23
- **Texto literal:** «public\images\rivales aqui tienes las imagenes, los equipo pokemon luego los confirmo, de momento puedes ir implementando».
- **Estado:** aprobado e implementado.
- **Pendiente:** confirmación de los equipos Pokémon y assets de capitanes; se ajustan
  sin cambiar la estructura.
