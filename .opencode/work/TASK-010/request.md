# TASK-010 — Nombres capitalizados, sesión de 1 año y shiny consistente

- **Fecha de recepción:** 2026-11-03
- **Estado:** `RECEIVED`
- **Proyecto:** `/home/david/pokemon` (Laravel 12 / PHP 8.4, rama `master`)
- **Solicitado por:** desarrollador

## Petición literal

> Capitalizar nombre de pokemon, primera mayuscula, cambiarlo directamente en los seeders a la hora de insertarlos en bbdd
> No cerrar sesion tan rapido, ampliar token duracion a 1 año
> Los pokemon shiny deben verse siempre shiny, en seleccion de equipo, combate...

## Interpretación operativa inicial

La petición contiene tres frentes independientes:

1. **Nombres de Pokémon capitalizados en origen.**
   - Al insertar Pokémon desde los seeders, el nombre debe guardarse con la primera letra en mayúscula y el resto tal como esté definido (equivalente a `ucfirst`), sin cambiar nombres ya capitalizados ni alterar el interior de la cadena.
   - El cambio solicitado es en los seeders, en el momento de la inserción en base de datos.

2. **Sesión persistente durante 1 año.**
   - Evitar el cierre de sesión prematuro ampliando a un año la duración del mecanismo de autenticación persistente que use la aplicación.
   - El análisis debe determinar si el mecanismo real es `session.lifetime`, la cookie de sesión, un token de «recordarme» o cualquier otro; no se asume de antemano.

3. **Shiny consistente en toda la UI.**
   - Un Pokémon marcado como shiny debe mostrarse siempre con su sprite shiny en todos los contextos: selección de equipo, combate y cualquier otro listado o detalle.
   - El análisis debe localizar dónde se pierde o no se aplica el flag shiny.

## Contexto mínimo conocido

- Laravel 12, PHP 8.4, Blade + Alpine + Livewire.
- Estado de sesión: `config/session.php` define `lifetime` desde `SESSION_LIFETIME` (por defecto 120 minutos) y `expire_on_close` desde `SESSION_EXPIRE_ON_CLOSE` (por defecto `false`).
- `composer.json` no declara Sanctum, Passport, JWT ni Breeze/Fortify/Jetstream; la autenticación parece basarse en sesión Laravel.
- Seeders en `database/seeders/`; el más relevante para nombres es `PokemonSeeder.php`.
- El flag shiny aparece, entre otros, en `app/Models/Reclutado.php`, `app/Support/ReclutadoSerializer.php`, `app/Support/CrearReclutado.php`, `src/Battle/Domain/*`, `src/Shared/Domain/ProbabilidadesShiny.php` y varias vistas (`equipos/index`, `livewire/combate`, `livewire/_pokemon-card`, `exploraciones/_explorador`).

## Restricciones conocidas

- No revertir ni sobrescribir cambios locales preexistentes ajenos a esta tarea.
- No crear, modificar ni ejecutar tests; `tests/` queda fuera de alcance.
- No modificar dependencias (`composer.json`, `package.json`) sin autorización.
- No cambiar el modelo ni el proveedor.
- No incluir secretos ni valores de `.env` en artefactos.
- No implementar nada hasta que exista un plan aprobado explícitamente.
- Leer y respetar `.ai/rules/` antes de cualquier cambio de código.

## Preguntas que debe resolver el análisis

1. **Nombres:** ¿los seeders son la única vía de alta de Pokémon? ¿Hay datos ya insertados que quedarían sin capitalizar? ¿Los seeders son idempotentes o hacen `updateOrCreate`? ¿Qué pasa con nombres compuestos, guiones, espacios o formas regionales?
2. **Sesión:** ¿qué mecanismo concreto mantiene la autenticación? ¿`session.lifetime`, cookie `remember`, token propio? ¿Dónde se configura y qué efecto real tiene un año? ¿Hay riesgos de seguridad o de limpieza de sesiones?
3. **Shiny:** ¿dónde se guarda el flag, cómo viaja hasta la vista y en qué puntos concretos se ignora? ¿Falla solo en selección de equipo y combate o también en otros contextos?

## Criterios de aceptación preliminares

- Los Pokémon insertados por los seeders quedan con la primera letra del nombre en mayúscula.
- La autenticación persiste hasta un año en el mecanismo que corresponda, sin introducir regresiones de seguridad.
- Un Pokémon shiny se muestra shiny en selección de equipo, combate y demás contextos donde aparezca su sprite.
- Sin cambios en tests ni en dependencias.
- Análisis estático y revisión independiente completados antes del cierre.
