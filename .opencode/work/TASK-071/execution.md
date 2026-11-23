# TASK-071 — Ejecución

- **Fecha:** 2026-11-23
- **Plan:** v3
- **Estado:** implementado
- **Aprobación:** `public\images\rivales aqui tienes las imagenes, los equipo pokemon luego los confirmo, de momento puedes ir implementando`

## 1. Retirada de Torres

Borrados:

```text
src/Torres/**
app/Http/Controllers/TorresViewController.php
app/Models/TorreEvento.php
app/Models/TorreProgreso.php
database/seeders/TorreEventoSeeder.php
database/migrations/2026_11_22_135525_create_torre_tables.php
database/migrations/2026_11_22_141324_add_recompensa_species_id_to_torre_eventos.php
routes/torres.php
resources/views/torres/**
```

Referencias eliminadas de:

- `routes/web.php` (import, rutas y `require`).
- `resources/views/layouts/app.blade.php` (entrada «Torres»).
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` (imports, rama `torre` y
  `procesarTorre()`).
- `database/seeders/CatalogoSeeder.php`.
- Comentarios de `app/Models/Habitat.php`, `config/habitats.php` y
  `resources/views/habitats/partials/columna-derecha.blade.php`.

No se ejecutó `migrate:rollback` ni se tocaron las tablas locales, según la decisión
del desarrollador.

## 2. Módulo `src/HabitatsTemporales`

Creado:

```text
Domain/
  FaccionRival.php, OleadaRival.php, CombateRival.php, PlantillaCombate.php
  CatalogoFaccionesRivales.php, ReglasHabitatTemporal.php
  Collections/ (FaccionRival, OleadaRival, CombateRival, FaccionResumen,
                OleadaVista, CombateVista)
  DataTransferObjects/ (ProgresoHabitatTemporal, ResultadoHabitatTemporal,
                        ProvinciaTemporal, FaccionResumen, FaccionDetalle,
                        OleadaVista, CombateVista)
  Enums/ (EstadoCombate, EstadoOleada)
  Exceptions/ (FaccionNoExiste, FaccionCompletada, OleadaNoDisponible,
               CombateNoDisponible)
  Repositories/ (ProgresoHabitatTemporalRepositoryInterface,
                 MedallasRepositoryInterface)
App/
  ObtenerProvinciaTemporal.php, ObtenerDetalleFaccion.php,
  IniciarCombateHabitatTemporal.php, RegistrarResultadoHabitatTemporal.php,
  EvaluadorEstadoFaccion.php, ResolutorAssetsFaccion.php
Infra/
  EloquentProgresoHabitatTemporalRepository.php,
  EloquentMedallasRepository.php,
  Controllers/HabitatTemporalController.php
```

Infraestructura:

```text
app/Models/HabitatTemporalProgreso.php
app/Http/Controllers/HabitatsTemporalesViewController.php
resources/views/habitats/temporales/show.blade.php
routes/habitats_temporales.php
database/migrations/2026_11_23_000001_create_habitat_temporal_progreso_table.php
```

## 3. Catálogo implementado

- 5 facciones: Rocket, Magma, Aqua, Galaxia y Skull.
- 3 oleadas por facción; 4 combates por oleada (3 reclutas + 1 jefe).
- Plantillas de recluta rotadas entre oleadas; 2 jefes secundarios + 1 capitán por
  facción.
- Colores: Rocket `#D32F2F`, Magma `#E65100`, Aqua `#1565C0`, Galaxia `#00838F`,
  Skull `#546E7A`.
- Imágenes reales: `public/images/rivales/Team_Rocket.jpg`,
  `Equipo_Magma_ROZA.png`, `Equipo_Aqua_ROZA.png`, `Equipo_Galaxia_Logo.png` y
  `Team_Skull.png`.
- Sprites de capitanes `magno`, `aquiles`, `ciro` y `guzman`: fallback temporal a
  `/images/misc/trainer.webp` hasta que se añadan los PNG.

## 4. Reglas aplicadas

- Desbloqueo de facción por medallas: 2, 3, 4, 5 y 6.
- Oleada 2 a las 9 medallas; oleada 3 a las 15.
- Nivel rival = `max(1, nivelJugador)`; sin bonus.
- IV/EV:
  - Oleada 1: reclutas `TopUno`; jefe `TopDos`.
  - Oleada 2: reclutas `TopDos`; jefe `Completo`.
  - Oleada 3: reclutas `Completo`; capitán `Completo`.
- Progreso: `oleada_actual` y `combate_actual`; 4 combates por oleada.
- Derrota: sin avance y sin cooldown.
- Recompensa: EXP ×3 + caramelos de los tipos derrotados.
- Provincia virtual: visible solo con facciones desbloqueadas y pendientes; se oculta
  al completar la última.

## 5. Integración de batalla

- `IniciarCombateHabitatTemporal` guarda meta:
  `tipo='habitat_temporal'`, `faccion_slug`, `oleada`, `combate`, `es_jefe`,
  `nivel_rival`, `user_id`, `team_id`.
- `PresentadorResultadoBatalla::procesarHabitatTemporal` registra el avance, otorga
  recompensas ×3 y escribe logs de recluta/jefe/completado/derrota.
- Anti-IDOR en `RegistrarResultadoHabitatTemporal` (`won` + `userId === authUserId`).

## 6. Desviaciones respecto al plan

1. Las imágenes se sirven directamente desde `public/images/rivales` en lugar de
   `resources/habitats_img/facciones`; no hace falta ruta de asset.
2. No se creó `Presentation/PresentadorFaccion`; se usa
   `App/ResolutorAssetsFaccion` para evitar una dependencia Application → Presentation.
3. El detalle de una facción completada responde 404 (`FaccionNoExiste`), no 422, para
   que el hábitat «desaparezca» también por URL.
4. Los rosters son provisionales hasta la confirmación del desarrollador; se cambian
   solo en `CatalogoFaccionesRivales`.

## 7. Verificación ejecutada

```text
php -l (todos los PHP nuevos/modificados)        → OK
vendor/bin/pint (archivos tocados)               → pass
vendor/bin/phpstan analyse --memory-limit=1G     → 0 errores
php artisan route:list --path=habitats-temporales→ 2 rutas
php artisan route:list --path=torres             → sin rutas
php artisan view:cache                           → OK
php artisan migrate --path=...progreso...        → OK
```

Comprobaciones manuales con el usuario 2 (2 medallas):

- `ObtenerProvinciaTemporal` devuelve solo Rocket, borde rojo e imagen real.
- `ObtenerDetalleFaccion` devuelve 3 oleadas × 4 combates; combate 1 disponible,
  combates 2-4 bloqueados por orden; oleada 2 bloqueada a 9 medallas y oleada 3 a 15.
- `IniciarCombateHabitatTemporal` crea `battle_faccion_*` con nivel rival 10 (nivel del
  jugador) y meta correcta.
- `RegistrarResultadoHabitatTemporal` avanza de 1/1 a 1/2; al simular 3/4 marca
  `completada` y `ObtenerProvinciaTemporal` devuelve `null`.
- La página `/habitats` responde 200 y renderiza la pestaña Temporal.
- La API `/api/habitats-temporales/rocket` responde 200; una facción inexistente
  responde 404.
- La vista de detalle renderiza con los equipos del usuario.

Sin tests: no se crean, actualizan ni ejecutan (política del proyecto).

## 8. Notas

- La BD local conserva `torre_eventos`/`torre_progreso` como rastro inerte; las
  instalaciones nuevas no las crean.
- Deptrac mantiene 128 violaciones preexistentes del módulo Habitats; no hay
  violaciones nuevas de `HabitatsTemporales`.
- Los equipos y sprites de capitanes quedan pendientes de confirmación/asset.

## 9. Corrección posterior (2026-11-23)

A petición del desarrollador, el detalle quedó así:

- `ObtenerDetalleFaccion` devuelve la **oleada actual** (`oleada`) con sus **4
  combates** (`combates`) y el combate actual (`combate`). Las oleadas futuras no se
  calculan ni se envían.
- La vista muestra los **4 entrenadores de la oleada actual** con el mismo estilo que
  las etapas de gimnasio (icono de estado, sprite 80×80, badge «Jefe», botón solo en
  el disponible). Si la oleada está bloqueada, las 4 tarjetas aparecen bloqueadas con
  el motivo.
- La página usa `max-w-6xl`, como la vista de gimnasios.
- El selector de equipo sigue en un modal que se abre al pulsar «Combatir»; la página
  no muestra el equipo. El equipo rival nunca se muestra.
- El modal de victoria vuelve a `/habitats/temporales/{slug}` mediante
  `returnUrl`/`returnLabel`; si la facción se completa, vuelve a `/habitats`.
- Se eliminó `OleadaVista`/`OleadaVistaCollection`; `CombateVistaCollection` se
  reutiliza para los 4 combates de la oleada actual.
- `ResolutorAssetsFaccion` prueba `webp`, `png`, `jpg` y `jpeg`; el catálogo guarda
  nombres base (`Team_Rocket`, `Equipo_Magma_ROZA`, etc.).
- Verificación repetida: `php -l`, Pint, PHPStan (0 errores), `view:cache`, API 200
  con `Team_Rocket.png`, `/habitats` 200 y detalle 200; retorno de victoria comprobado
  a `/habitats/temporales/rocket` y a `/habitats` al completar.

## 10. Formación compartida con gimnasios (2026-11-23)

- Se extrajo el modal de formación a `resources/views/partials/formacion-combate.blade.php`
  y su estado Alpine a `resources/views/partials/formacion-combate-script.blade.php`.
  Gimnasios y hábitats temporales usan el mismo modal: equipo, formación
  vanguardia/retaguardia, comparación de PC y leyenda IV/EV.
- Nuevo `GET /api/habitats-temporales/{faccion}/pc?team_id=` con
  `CalcularComparacionPcHabitatTemporal`, reutilizando `CalculadoraPcRival`,
  `ObtenerPcEquipoJugador`, `ComparadorPc` y `ConstruirLeyendaIvsEvs`.
- El modal se invoca desde el combate disponible; `formacionPcUrl` y
  `formacionCombatUrl` se configuran en el componente. El equipo rival no se muestra:
  solo el PC agregado y la leyenda IV/EV.
- El atajo de v1 (selector simple) queda reemplazado por el modal compartido.
- Verificación: PHPStan 0, Pint pass, `view:cache`, `/gimnasios/bug` 200 y
  `/habitats/temporales/rocket` 200; endpoint PC 200 con `estado=ok` y
  `contexto.tipo=habitat_temporal`.

## 11. Corrección de imágenes en la formación (2026-11-23)

- El getter `selectedTeamMembers` del estado compartido se aplanaba al hacer spread en
  el componente de hábitat temporal (el spread copia el valor, no el getter), por lo
  que la lista de miembros quedaba vacía y no se veían los sprites.
- Se eliminó el getter del script compartido y se definió explícitamente en
  `habitatTemporalShow`; el gimnasio ya tenía el suyo. El modal compartido usa el
  getter del componente.
- Verificación: `view:cache`, `/habitats/temporales/rocket` 200 y `/gimnasios/bug` 200;
  las rutas de iconos de los miembros del equipo 11 (`472`, `464`, `383`) existen.

## 12. Sin texto de requisito de medallas en la UI (2026-11-23)

- Se retiró el texto «Se desbloquea con N medallas (tienes M)» de la tarjeta de la
  provincia y del detalle del hábitat.
- El estado bloqueado se mantiene con el badge y los iconos de candado; el motivo sigue
  en el contrato interno pero no se renderiza.

## 13. Hábitats temporales solo cuando están disponibles (2026-11-23)

- `ObtenerProvinciaTemporal` omite las facciones cuya oleada actual está bloqueada por
  medallas, además de las no desbloqueadas y las completadas. Si no queda ninguna, la
  provincia Temporal no se renderiza.
- `ObtenerDetalleFaccion` responde 404 si la oleada actual está bloqueada; la facción
  reaparece cuando se alcanza el hito (p. ej. oleada 2 a 9 medallas).
- Se retiraron de la UI las etiquetas «Oleada», «bloqueada» y el texto de requisito de
  medallas. La tarjeta muestra solo «Combate X de 4»; el detalle muestra «Entrenadores».
- El endpoint PC y el inicio de combate siguen validando la puerta de medallas.
- Verificación: `/habitats` y `/habitats/temporales/rocket` 200 sin «Oleada» ni
  «bloqueada»; Magma con oleada 2 gated responde 404 y no se lista; PHPStan 0.
