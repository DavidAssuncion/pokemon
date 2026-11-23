# TASK-071 — Informe final

- **Fecha:** 2026-11-23
- **Estado:** COMPLETADO (equipos y sprites de capitanes pendientes de confirmación)
- **Plan:** v3
- **Aprobación:** «public\images\rivales aqui tienes las imagenes, los equipo pokemon luego los confirmo, de momento puedes ir implementando»

## Resultado

Las torres han desaparecido y los hábitats temporales de facciones rivales están
operativos dentro de la provincia virtual «Temporal» de `/habitats`.

- 5 facciones: Team Rocket, Team Magma, Team Aqua, Team Galaxia y Team Skull.
- 3 oleadas por facción; 4 combates por oleada: 3 reclutas + 1 jefe.
- Jefes secundarios en las oleadas 1 y 2; capitán principal en la oleada 3.
- Desbloqueo por medallas: 2, 3, 4, 5 y 6; oleadas 2 y 3 a los 9 y 15.
- Todos los rivales tienen exactamente el nivel del jugador.
- IV/EV: oleada 1 TopUno/jefe TopDos; oleada 2 TopDos/jefe Completo; oleada 3
  Completo.
- Recompensa EXP ×3 + caramelos de los tipos derrotados.
- Derrota sin avance y sin cooldown.
- La tarjeta usa la imagen real de `public/images/rivales` y el borde del color de la
  facción.
- El hábitat desaparece al completar la oleada 3/combate 4; la pestaña Temporal se
  oculta si no quedan facciones pendientes.

## Verificación

- `php -l` de todos los PHP nuevos/modificados: OK.
- `vendor/bin/pint` de los archivos tocados: pass.
- `vendor/bin/phpstan analyse --memory-limit=1G`: **0 errores**.
- `php artisan route:list --path=habitats-temporales`: 2 rutas.
- `php artisan route:list --path=torres`: sin rutas.
- `php artisan view:cache`: OK.
- `php artisan migrate --path=...create_habitat_temporal_progreso...`: OK.
- Comprobaciones manuales con el usuario 2 (2 medallas):
  - La provincia muestra solo Rocket, con imagen real y borde `#D32F2F`.
  - El detalle devuelve 3 oleadas × 4 combates; combate 1 disponible; oleada 2 a 9
    medallas y oleada 3 a 15.
  - `IniciarCombateHabitatTemporal` crea `battle_faccion_*` con nivel rival 10 (nivel
    del jugador).
  - El registro avanza de 1/1 a 1/2; al simular 3/4 marca completada y la provincia
    pasa a `null`.
  - `/habitats` responde 200; `/api/habitats-temporales/rocket` responde 200; una
    facción inexistente responde 404.
  - La vista de detalle renderiza con los equipos del usuario.

Sin tests: no se crean, actualizan ni ejecutan.

## Pendiente / notas

1. **Equipos Pokémon:** los rosters son provisionales hasta la confirmación del
   desarrollador; se ajustan en
   `src/HabitatsTemporales/Domain/CatalogoFaccionesRivales.php`.
2. **Sprites de capitanes:** `magno`, `aquiles`, `ciro` y `guzman` usan el fallback
   `/images/misc/trainer.webp` hasta que se añadan los PNG en
   `public/images/trainers/`.
3. **Tablas de torres:** la BD local conserva `torre_eventos`/`torre_progreso` como
   rastro inerte; las instalaciones nuevas no las crean.
4. **Deptrac:** 128 violaciones preexistentes del módulo Habitats; no hay violaciones
   nuevas de `HabitatsTemporales`.
5. **Revisión independiente:** no hay revisor separado en esta sesión; se documenta
   autorevisión en `review.md`.

## Archivos principales

- `src/HabitatsTemporales/**`
- `app/Models/HabitatTemporalProgreso.php`
- `app/Http/Controllers/HabitatsTemporalesViewController.php`
- `resources/views/habitats/temporales/show.blade.php`
- `resources/views/habitats/index.blade.php`
- `routes/habitats_temporales.php`
- `database/migrations/2026_11_23_000001_create_habitat_temporal_progreso_table.php`
- `app/Http/Controllers/HabitatsController.php`
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php`
- `app/Providers/AppServiceProvider.php`
- `routes/web.php`, `routes/habitats.php`, `resources/views/layouts/app.blade.php`

## Corrección posterior (2026-11-23)

- El detalle muestra **la oleada actual completa, con sus 4 entrenadores**, usando los
  mismos estilos que las etapas de gimnasio. Las oleadas futuras no se envían desde la
  API ni se renderizan.
- Si la oleada actual está bloqueada, las 4 tarjetas aparecen bloqueadas con el motivo;
  no hay botón de combate.
- El **equipo no se muestra en la página**: el selector vive en un modal que se abre
  al pulsar «Combatir». El equipo rival tampoco se muestra.
- La API del detalle devuelve `faccion`, `oleada`, `combate` y `combates` (los 4 de la
  oleada actual).
- El modal de victoria vuelve a `/habitats/temporales/{slug}` (o a `/habitats` si la
  facción se completa), no a gimnasios.
- La vista usa `max-w-6xl`, como la de gimnasios.
- El resolver de imágenes prueba `webp`, `png`, `jpg` y `jpeg`; el catálogo guarda
  nombres base. `Team_Rocket.png` se resuelve correctamente.
- Verificación repetida: PHPStan 0 errores, Pint pass, `view:cache` OK, `/habitats`
  y detalle 200, API 200; retorno de victoria comprobado.

## Formación compartida con gimnasios (2026-11-23)

- El modal «Configurar Formación» es el mismo que en gimnasios
  (`partials/formacion-combate` + `formacion-combate-script`): selección de equipo,
  formación vanguardia/retaguardia, comparación de PC y leyenda IV/EV.
- Se añadió `GET /api/habitats-temporales/{faccion}/pc?team_id=` reutilizando los
  helpers de PC de gimnasio; el rival se muestra solo como PC agregado, sin equipo,
  especies, tipos ni movimientos.
- El selector simple de v1 queda retirado.
- Verificación: PHPStan 0, Pint pass, `view:cache`, `/gimnasios/bug` y
  `/habitats/temporales/rocket` 200, endpoint PC 200.

## Corrección de imágenes en la formación (2026-11-23)

- El getter `selectedTeamMembers` del estado compartido se aplanaba con el spread de
  objetos; la lista de miembros quedaba vacía y no se veían los sprites.
- Se define el getter en `habitatTemporalShow` (el gimnasio ya tenía el suyo). El modal
  compartido usa el getter del componente.
- Verificación: `view:cache` OK, hábitat y gimnasio 200; iconos del equipo 11 existen.

## Sin texto de requisito de medallas (2026-11-23)

- La tarjeta y el detalle ya no muestran «Se desbloquea con N medallas (tienes M)».
- El estado bloqueado se comunica con el badge y los candados; el motivo no se
  renderiza en la UI.

## Hábitats temporales solo cuando están disponibles (2026-11-23)

- Las facciones con la oleada actual bloqueada por medallas no se listan; su detalle
  responde 404 y reaparecen cuando se alcanza el hito (p. ej. oleada 2 a 9 medallas).
- Si no queda ninguna facción visible, la provincia Temporal no se muestra.
- La UI ya no muestra las palabras «Oleada» ni «bloqueada» ni el texto del requisito de
  medallas. La tarjeta muestra «Combate X de 4» y el detalle «Entrenadores».
