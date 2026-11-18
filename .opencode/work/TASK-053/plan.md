# TASK-053 — Plan v1: sprites de entrenador en gimnasios, hábitats y rutas

- **ID:** TASK-053
- **Versión:** v1
- **Fecha:** 2026-11-17
- **Proyecto:** `/home/david/pokemon`
- **Estado:** EJECUTADO v1 + AJUSTE v2 (aprobado e implementado)

## 1. Objetivo

Aplicar la fase 1 de sprites de entrenador:

1. **Gimnasios:** líder canónico con nombre propio y sprite; etapas 1-3 con clases genéricas y sprites.
2. **Hábitats:** una clase fija por hábitat, coherente con sus Pokémon, con variantes fijas por índice.
3. **Rutas:** la clase fija del hábitat aparece en el panel de ruta y en el botón lateral.
4. **Assets:** subset curado de 74 PNG a 40 px, con atribución documentada.

## 2. Decisiones del desarrollador (2026-11-17)

| Decisión | Valor |
|---|---|
| Distribución | Proyecto privado; riesgo de licencia aceptado y documentado |
| Arte | Se acepta arte de fans/Showdown con atribución |
| Líderes | Nombre propio y canónico |
| Etapas 1-3 | Entrenadores genéricos, sin nombre propio |
| Hábitats | Clase fija por hábitat, con sentido según sus Pokémon |
| Tamaño | 40 px CSS (80×80 real en HiDPI) |

## 3. Alcance

### Incluido

- Dominio de gimnasios: nombre canónico del líder.
- Manifiesto de sprites y componente Blade reutilizable.
- Sprites en las 4 etapas de gimnasio.
- Sprites en las tarjetas de entrenador de hábitat, panel de ruta y botón lateral.
- Assets en `public/images/trainers/` (74 PNG).
- Atribución en `THIRD_PARTY_NOTICES.md` y metadatos del manifiesto.

### Fuera de alcance

- Facciones Magma/Aqua/Team Rocket y equipos rivales (dominio nuevo; fase posterior).
- Sprites de entrenador dentro de la pantalla de combate (`livewire/combate`).
- Cambios de balance, combate, recompensas, datos o migraciones.
- Nuevos endpoints o cambios de contrato JSON (`EtapaGimnasio` mantiene `{etapa, nombre}`).
- Tests.

## 4. Arquitectura y contratos

### 4.1 Dominio: nombre del líder

- Nuevo `src/Gimnasios/Domain/CatalogoLideres.php`:
  - `private const NOMBRES` con las 18 entradas `slug => nombre`.
  - `public const POR_DEFECTO = 'Líder'`.
  - `public static function nombreDe(string $slug): string` con fallback.
- `Gimnasio` añade `public readonly string $nombreLider` (parámetro obligatorio, entre `nivelMinimo` y `equipos`).
- `Gimnasio::nombreEtapa(4)` devuelve `$this->nombreLider`; las etapas 1-3 no cambian.
- `CatalogoGimnasios` pasa `nombreLider: CatalogoLideres::nombreDe('<slug>')` en las 18 construcciones.
- `EloquentGymCatalogoRepository::desdeModelo()` pasa `nombreLider: CatalogoLideres::nombreDe($model->slug)`. **La fuente en runtime es la BD `gyms`; sin este cambio el runtime rompe.**
- `EtapaGimnasio`, `DetalleGimnasio`, `GimnasioResumen` y las colecciones **no cambian de forma**.
- El contrato JSON `GET /api/gimnasios/{slug}` mantiene `{etapa, nombre}`; solo cambia el valor de `nombre` en la etapa 4.
- `docs/gimnasios.md`, `src/Gimnasios/context.md` y `src/Gimnasios/README.md` actualizan «4 = Líder» por el nombre canónico.

### 4.2 Presentación: manifiesto y resolución

- Nuevo `resources/data/entrenadores_sprites.json`:
  - `version`, `fuente` (repo, commit, upstream, directorio, tamaños, licencia),
  - `fallback` (`acetrainer`),
  - `clases` (`slug => nombre visible`),
  - `gimnasios` (`slug => { lider, etapas: {1,2,3,4} }`),
  - `habitats` (`id => { clase, variantes: [v1, v2] }`).
- Nuevo `app/Support/UI/SpritesEntrenador.php`:
  - carga el manifiesto una vez por request (static),
  - `manifiesto(): array`,
  - `url(string $slug): string` con comprobación `is_file` y fallback a `/images/misc/trainer.webp`.
- Nuevo `resources/views/components/trainer-sprite.blade.php`:
  - props `slug`, `size` (40), `name` (''), `decorative` (false), `loading` ('lazy').
  - `<img>` con `width`/`height` = 40, `loading="lazy"`, `decoding="async"`, `style="image-rendering: pixelated"`, `object-contain`.
  - `alt` = nombre si no es decorativo; si es decorativo, `alt=""` + `aria-hidden="true"`.
  - Sin `x-html`; los nombres se escapan con `{{ }}`/`x-text`.
- Los controladores `GimnasiosViewController@show` y `HabitatsController@show` pasan `sprites` a la vista (`SpritesEntrenador::manifiesto()`).
- La vista de gimnasios (`Alpine`) añade `sprites: @json($sprites)` y resuelve `sprites.gimnasios[gym.slug].etapas[etapa.etapa]`.
- La vista de hábitats (`Alpine`) añade `sprites: @json($sprites)` y resuelve `sprites.habitats[habitatId]` (índice 1 = `clase`, índices 2-3 = `variantes`).
- El panel de ruta y el botón lateral usan la clase base del hábitat y el componente Blade.

### 4.3 Líderes canónicos propuestos

Se priorizan **líderes de gimnasio reales**: Kanto y Johto cubren 16 tipos; Piers (Galar) y Valerie (Kalos) cubren los dos restantes. El orden del juego es por tipo/dificultad, no por región; la mezcla regional es deliberada.

| Gimnasio | Tipo | Nv | Líder | Sprite |
|---|---|---:|---|---|
| `bug` | Bicho | 10 | Bugsy | `bugsy` |
| `poison` | Veneno | 15 | Koga | `koga` |
| `normal` | Normal | 20 | Whitney | `whitney` |
| `grass` | Planta | 25 | Erika | `erika` |
| `flying` | Volador | 31 | Falkner | `falkner` |
| `rock` | Roca | 36 | Brock | `brock` |
| `electric` | Eléctrico | 41 | Teniente Surge | `ltsurge` |
| `ice` | Hielo | 46 | Pryce | `pryce` |
| `fire` | Fuego | 52 | Blaine | `blaine` |
| `water` | Agua | 57 | Misty | `misty` |
| `ground` | Tierra | 62 | Giovanni | `giovanni` |
| `psychic` | Psíquico | 67 | Sabrina | `sabrina` |
| `dark` | Siniestro | 73 | Piers | `piers` |
| `ghost` | Fantasma | 78 | Morty | `morty` |
| `fighting` | Lucha | 83 | Chuck | `chuck` |
| `fairy` | Hada | 88 | Valerie | `valerie` |
| `steel` | Acero | 94 | Jasmine | `jasmine` |
| `dragon` | Dragón | 100 | Clair | `clair` |

Todas las variantes de nombre son decisiones de producto; si se prefiere otro líder por tipo (p. ej. Norman, Karen, Agatha, Bruno, Lance), se cambia en `CatalogoLideres` sin tocar el resto.

### 4.4 Etapas 1-3 por gimnasio

| Gimnasio | Etapa 1 | Etapa 2 | Etapa 3 |
|---|---|---|---|
| bug | `bugcatcher` | `bugmaniac` | `lass` |
| poison | `roughneck` | `biker` | `punkgirl` |
| normal | `youngster` | `lass` | `acetrainer` |
| grass | `aromalady` | `gardener` | `picnicker` |
| flying | `birdkeeper` | `camper` | `lass` |
| rock | `hiker` | `smasher` | `ruinmaniac` |
| electric | `guitarist` | `engineer` | `worker` |
| ice | `skier` | `boarder` | `beauty` |
| fire | `kindler` | `firebreather` | `firefighter` |
| water | `swimmer` | `swimmerf` | `sailor` |
| ground | `hiker` | `ruinmaniac` | `worker` |
| psychic | `psychic` | `medium` | `hexmaniac` |
| dark | `delinquent` | `punkgirl` | `roughneck` |
| ghost | `hexmaniac` | `channeler` | `medium` |
| fighting | `blackbelt` | `battlegirl` | `roughneck` |
| fairy | `fairytalegirl` | `aromalady` | `beauty` |
| steel | `worker` | `engineer` | `scientist` |
| dragon | `dragontamer` | `acetrainer` | `veteran` |

### 4.5 Clases por hábitat

Una clase base por hábitat y dos variantes fijas por índice (`indice` 1 = base, 2 = variante 1, 3 = variante 2). El mapeo completo, con tipos dominantes, especies y justificación, está en `.opencode/work/TASK-053/analysis.md` §2. Resumen:

| id | Hábitat | Clase base | Variantes |
|---:|---|---|---|
| 1 | Bahía Crustáceo | `swimmer` | `swimmerf`, `tuber` |
| 2 | Bosque Brumoso | `aromalady` | `gardener`, `lass` |
| 3 | Bosque Crecido | `bugcatcher` | `bugmaniac`, `lass` |
| 4 | Bosque Elemental | `pokemaniac` | `scientist`, `psychic` |
| 5 | Bosque Nido | `birdkeeper` | `camper`, `lass` |
| 6 | Bosque Sanador | `nurse` | `aromalady`, `pokemonbreeder` |
| 7 | Bosque Transformacion | `pokemonbreeder` | `scientist`, `veteran` |
| 8 | Campo Terroso | `rancher` | `cowgirl`, `gardener` |
| 9 | Caverna Gélida | `skier` | `boarder`, `hiker` |
| 10 | La Marjal | `fisherman` | `tuber`, `camper` |
| 11 | Charca Renacuajo | `tuber` | `swimmer`, `fisherman` |
| 12 | Bonalba | `swimmerf` | `swimmer`, `camper` |
| 13 | Corriente Marina | `scubadiver` | `swimmer`, `sailor` |
| 14 | Cráter | `kindler` | `firebreather`, `hiker` |
| 15 | Mar Sereno | `surfer` | `swimmer`, `beauty` |
| 16 | Lago Místico | `medium` | `channeler`, `fisherman` |
| 17 | Monte Disciplina | `blackbelt` | `battlegirl`, `hiker` |
| 18 | Mina Magnética | `worker` | `engineer`, `scientist` |
| 19 | Pradera Celeste | `picnicker` | `lass`, `youngster` |
| 20 | Pradera Salvaje | `youngster` | `lass`, `acetrainer` |
| 21 | Río Manso | `camper` | `fisherman`, `tuber` |
| 22 | Selva | `pokemonranger` | `sage`, `bugmaniac` |
| 23 | Vieja Central | `engineer` | `worker`, `scientist` |
| 24 | Sierra Quemada | `firebreather` | `kindler`, `hiker` |
| 25 | Central Energía | `scientist` | `engineer`, `worker` |
| 26 | Bosque Secreto | `bugmaniac` | `bugcatcher`, `hexmaniac` |
| 27 | Sabana | `cowgirl` | `rancher`, `hiker` |
| 28 | Prado Trueno | `guitarist` | `youngster`, `engineer` |
| 29 | Pozas De Valencia | `freediver` | `tuber`, `swimmer` |
| 30 | Bosque Seta | `gardener` | `aromalady`, `bugcatcher` |
| 31 | Desierto Horno | `backpacker` | `ruinmaniac`, `hiker` |
| 33 | Calvari | `punkgirl` | `delinquent`, `biker` |
| 34 | Pantano Ponzoña | `roughneck` | `punkgirl`, `biker` |
| 35 | Playa Cubito | `boarder` | `skier`, `sailor` |
| 36 | Las Ramblas | `delinquent` | `punkgirl`, `roughneck` |
| 37 | Pradera Orgullosa | `fairytalegirl` | `aromalady`, `beauty` |
| 38 | Monte Verde | `hiker` | `camper`, `birdkeeper` |
| 39 | Cueva Dragón | `dragontamer` | `hiker`, `veteran` |
| 40 | Cueva Granito | `smasher` | `hiker`, `worker` |
| 42 | Estrecho de Gibraltar | `sailor` | `fisherman`, `swimmer` |
| 43 | Playa del Postiguet | `beauty` | `swimmerf`, `tuber` |
| 44 | Monte Partido | `ruinmaniac` | `hiker`, `smasher` |
| 45 | Camara Omega | `psychic` | `psychicf`, `medium` |
| 46 | Camara Alfa | `hexmaniac` | `channeler`, `medium` |
| 47 | Vieja Reliquia | `sage` | `ruinmaniac`, `psychic` |
| 48 | Tabarca | `tourist` | `sailor`, `birdkeeper` |
| 49 | Legendarios | `veteran` | `veteranf`, `acetrainer` |
| 50 | Cañar | `bugcatcher` | `bugmaniac`, `camper` |
| 51 | Jungla | `backpacker` | `pokemonranger`, `sage` |
| 52 | Santa Pola | `fisherman` | `sailor`, `swimmer` |
| 53 | Monte Oscuro | `channeler` | `hexmaniac`, `delinquent` |
| 54 | Tierra Abrasada | `firefighter` | `kindler`, `firebreather` |
| 55 | Sierra Aitana | `skier` | `boarder`, `hiker` |
| 56 | Cueva del Eco | `hiker` | `channeler`, `dragontamer` |
| 57 | Fosa | `ruinmaniac` | `scubadiver`, `scientist` |
| 58 | Pradera T | `youngathlete` | `youngster`, `camper` |

Los ids 32 y 41 no existen. El hábitat 49 sí tiene pool (119 filas) y se le asigna `veteran`; sigue fuera de la mazmorra por la decisión de TASK-047.

### 4.6 Assets

- Copiar 74 PNG desde `/tmp/opencode/trainer-sprites/repo/trainers/` a `public/images/trainers/`.
- Subset curado, no los 572: 18 líderes + 39 clases genéricas de gimnasio + 50 clases base de hábitat, con solapes.
- Peso total: ~57 KB.
- Validación: cada slug del manifiesto debe tener `public/images/trainers/{slug}.png`.
- No convertir a WebP: 57 KB es irrelevante y el pixel art PNG indexado es fiel.

## 5. Archivos y responsables

| Archivo | Acción | Responsable |
|---|---|---|
| `src/Gimnasios/Domain/CatalogoLideres.php` | Crear catálogo canónico | `backend` |
| `src/Gimnasios/Domain/Gimnasio.php` | Añadir `nombreLider`; `nombreEtapa(4)` | `backend` |
| `src/Gimnasios/Domain/CatalogoGimnasios.php` | Pasar `nombreLider` en 18 gimnasios | `backend` |
| `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php` | Pasar `nombreLider` desde `$model->slug` | `backend` |
| `resources/data/entrenadores_sprites.json` | Crear manifiesto | `frontend` |
| `app/Support/UI/SpritesEntrenador.php` | Crear resolutor | `frontend` |
| `resources/views/components/trainer-sprite.blade.php` | Crear componente 40 px | `frontend` |
| `public/images/trainers/*.png` | Copiar 74 PNG | `frontend` |
| `app/Http/Controllers/GimnasiosViewController.php` | Pasar `sprites` a la vista | `frontend` |
| `app/Http/Controllers/HabitatsController.php` | Pasar `sprites` a la vista | `frontend` |
| `resources/views/gimnasios/show.blade.php` | Sprite de etapa, nombre de líder, copy del title | `frontend` (solo tras cerrar TASK-050) |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Sprite en tarjetas de entrenador y panel de ruta | `frontend` |
| `resources/views/habitats/partials/columna-izquierda.blade.php` | Sprite en el botón «Entrenadores» | `frontend` |
| `THIRD_PARTY_NOTICES.md` | Sección de sprites y atribución | `documenter` |
| `docs/gimnasios.md`, `src/Gimnasios/context.md`, `src/Gimnasios/README.md` | «4 = Líder» → nombre canónico | `documenter` |

**No se toca:** `resources/css/*` (TASK-051), `tests/`, migraciones, `app/Models/Gym.php`, `app/Models/GymStage.php`, `database/seeders/GymSeeder.php`, `EtapaGimnasio`, `DetalleGimnasio`, `GimnasioResumen`, `AdminGymController`.

## 6. Orden de ejecución y dependencias

1. **Backend (sin conflicto):** `CatalogoLideres` → `Gimnasio` → `CatalogoGimnasios` y `EloquentGymCatalogoRepository` en el mismo paso.
2. **Assets + manifiesto + componente + resolutor:** copiar PNG, validar slugs, crear JSON, componente y `SpritesEntrenador`.
3. **Hábitats (sin conflicto):** controlador, `columna-derecha`, `columna-izquierda`.
4. **Gimnasios (bloqueado por TASK-050):** esperar a que TASK-050 esté `COMPLETED`; después modificar `gimnasios/show.blade.php` y `GimnasiosViewController`.
5. **Documentación y atribución:** `THIRD_PARTY_NOTICES.md` y docs de gimnasios.
6. **Revisión independiente** y cierre.

**Dependencia dura:** el paso 4 no puede empezar hasta que TASK-050 cierre (revisión y seguridad incluidas). Los pasos 1-3 y 5 pueden ejecutarse antes.

## 7. Criterios de aceptación

1. Los 18 gimnasios muestran 4 sprites distintos a 40 px; la etapa 4 muestra el nombre canónico del líder y las etapas 1-3 siguen mostrando `Entrenador 1/2/3`.
2. El JSON de `GET /api/gimnasios/{slug}` mantiene el shape `{etapa, nombre}`; en etapa 4 `nombre` es el líder. No hay `Líder` literal en la respuesta.
3. Los 56 hábitats muestran su clase base y variantes fijas en las 3 tarjetas de entrenador por nivel; el panel de ruta y el botón lateral muestran la clase base.
4. Todos los sprites son 40×40 CSS, `width`/`height` explícitos, `loading="lazy"`, `decoding="async"`, `image-rendering: pixelated`.
5. Sprites decorativos con `alt=""` y `aria-hidden="true"`; el retrato del líder con `alt` de su nombre.
6. El manifiesto valida: todos los slugs tienen PNG; todos los gimnasios del manifiesto existen en `gyms`; todos los ids de hábitat existen en `habitats` (excepto 32/41); `clases` contiene todas las claves usadas.
7. `THIRD_PARTY_NOTICES.md` documenta repo, commit, Showdown, ausencia de LICENSE y uso privado no comercial.
8. No se modifican `resources/css/*`, tests, migraciones ni contratos ajenos. TASK-050 y TASK-051 quedan intactas.
9. `php -l`, Pint, `npm run build`, `php artisan view:cache` y `git diff --check` pasan.
10. Verificación manual: 18 gimnasios, 56 hábitats, ruta, fallback, teclado y 1×/2×.

## 8. Verificación requerida (sin tests)

- `php -l` de los 5 PHP modificados/creados.
- `vendor/bin/pint --dirty --format agent`.
- `npm run build`.
- `php artisan view:cache` y `php artisan view:clear`.
- Validación del manifiesto con PHP/JQ:
  - 18 gimnasios y 56 hábitats,
  - 74 slugs existentes en `public/images/trainers/`,
  - 0 claves huérfanas en `clases`.
- `git diff --check` y `git status` (solo archivos autorizados).
- Manual:
  - `/gimnasios/bug` y otro gimnasio: 4 sprites, nombre del líder, título correcto.
  - Un hábitat de agua, uno de bosque y el 49: 3 tarjetas con base/variantes.
  - Ruta y botón lateral.
  - Fallback renombrando temporalmente un PNG.
  - 40 px en 1×/2× y navegación por teclado.

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| **Conflicto con TASK-050** en `gimnasios/show.blade.php` | No tocar la vista hasta que TASK-050 cierre; el resto del plan avanza en paralelo |
| **Runtime roto** por no actualizar `EloquentGymCatalogoRepository` | Pasos de dominio en el mismo bloque; `php -l` y tinker de 18 líderes |
| **Licencia** de arte sin LICENSE | Proyecto privado; `THIRD_PARTY_NOTICES` y metadatos del manifiesto |
| **Coherencia regional** (Brock en Nv 36) | Aceptada: el juego ordena por tipo/dificultad; se documenta |
| **Repetición visual** en 3 tarjetas | Variantes fijas por índice + posible anillo de color |
| **Errata de slug** (`pokemanic`) | Validación mecánica del manifiesto contra PNG |
| **Escalado** a 64 px | Fijar 40 px y `image-rendering: pixelated` |
| **Conflicto con TASK-051** (tema) | No tocar `resources/css/*`; usar estilo inline para `pixelated` |
| **Hábitat 49** | Se le asigna `veteran`; sigue sellado en la mazmorra |

## 10. Rollback

- Revertir los archivos de código a su versión anterior (solo con autorización).
- Eliminar `public/images/trainers/`, `resources/data/entrenadores_sprites.json`, el componente y el resolutor.
- No hay cambios de datos ni de esquema.

## 11. Aprobación

No se implementa nada hasta que el desarrollador responda `Aprobar plan v1` o una fórmula equivalente que mencione la versión. La implementación del paso 4 esperará además a que TASK-050 esté cerrada.

## 12. Ajuste v2 — sprites a 80×80 (2026-11-17)

- **Origen:** instrucción literal del desarrollador:
  `Ponlos a 80x80, lo permiten tanto en gym como en rutas`.
- **Cambio:** subir de 40 a 80 px los sprites de las 4 etapas de gimnasio, las 3
  tarjetas de entrenador de hábitat y el encabezado del panel de ruta. El botón
  lateral «Entrenadores» se mantiene a 40 px para no romper la navegación.
- **Archivos:** `resources/views/gimnasios/show.blade.php`,
  `resources/views/habitats/partials/columna-derecha.blade.php`,
  `resources/data/entrenadores_sprites.json`,
  `resources/views/components/trainer-sprite.blade.php`.
- **Contratos:** sin cambios; el tamaño es presentación.
- **Verificación:** 80×80 en gimnasio/rutas, `image-rendering: pixelated`,
  `view:cache`, `npm run build`, `git diff --check`; sin tests.
