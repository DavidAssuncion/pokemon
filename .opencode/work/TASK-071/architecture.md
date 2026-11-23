# TASK-071 — Arquitectura

- **Plan:** v3
- **Estado:** propuesto (pendiente de aprobación)

## 1. Decisión principal

La provincia **Temporal** y sus hábitats son **virtuales**: no se insertan filas en
`provinces` ni en `habitats`. Se componen en tiempo de lectura desde:

1. Un catálogo en código de 5 facciones, 3 oleadas por facción y 4 combates por oleada.
2. El progreso por usuario en una única tabla nueva.
3. El contador de medallas de gimnasio completadas (`gym_progress.current_stage = 5`).

Ventajas:

- Un hábitat temporal no puede aparecer en rutas, exploraciones, spawns, gestiones ni
  detalles salvajes: no existe como hábitat de BD.
- No se ensucia `provinces`/`habitats` con filas especiales ni flags.
- El contenido (rosters, nombres, colores, imágenes, umbrales) es estático y vive en
  código, igual que el catálogo de gimnasios.
- La única persistencia necesaria es el avance del jugador.

## 2. Estructura del módulo

```text
src/HabitatsTemporales/
├── Domain/
│   ├── FaccionRival.php
│   ├── OleadaRival.php
│   ├── CombateRival.php
│   ├── CatalogoFaccionesRivales.php
│   ├── ReglasHabitatTemporal.php
│   ├── Collections/
│   │   ├── FaccionRivalCollection.php
│   │   ├── OleadaRivalCollection.php
│   │   └── CombateRivalCollection.php
│   ├── DataTransferObjects/
│   │   ├── ProgresoHabitatTemporal.php
│   │   ├── ProvinciaTemporal.php
│   │   ├── FaccionResumen.php
│   │   ├── FaccionDetalle.php
│   │   ├── OleadaVista.php
│   │   └── CombateVista.php
│   ├── Enums/EstadoCombate.php
│   ├── Exceptions/
│   │   ├── FaccionNoExiste.php
│   │   ├── OleadaNoDisponible.php
│   │   └── CombateNoDisponible.php
│   └── Repositories/
│       ├── ProgresoHabitatTemporalRepositoryInterface.php
│       └── MedallasRepositoryInterface.php
├── App/
│   ├── ObtenerProvinciaTemporal.php
│   ├── ObtenerDetalleFaccion.php
│   ├── IniciarCombateHabitatTemporal.php
│   └── RegistrarResultadoHabitatTemporal.php
├── Infra/
│   ├── Controllers/HabitatTemporalController.php
│   ├── EloquentProgresoHabitatTemporalRepository.php
│   └── EloquentMedallasRepository.php
└── Presentation/
    └── PresentadorFaccion.php
```

Infraestructura Laravel fuera del módulo:

```text
app/Models/HabitatTemporalProgreso.php
app/Http/Controllers/HabitatsTemporalesViewController.php
resources/views/habitats/temporales/show.blade.php
routes/habitats_temporales.php
database/migrations/2026_11_23_000001_create_habitat_temporal_progreso_table.php
```

## 3. Catálogo de facciones

### 3.1 Modelo

- `FaccionRival`: `slug`, `nombre`, `descripcion`, `color` (hex), `imagen` (slug de
  asset), `orden`, `medallasDesbloqueo`, `OleadaRivalCollection` (3).
- `OleadaRival`: `numero` (1-3), `nombre`, `CombateRivalCollection` (4).
- `CombateRival`: `numero` (1-4), `nombre`, `entrenador`, `descripcion`, `sprite`,
  `esJefe`, `EquipoEtapaGimnasio` (vanguardia/retaguardia con `species_id`).

Cada oleada tiene **4 combates**: los 3 primeros son reclutas y el cuarto es un jefe.
Los jefes de las oleadas 1 y 2 son secundarios; el de la oleada 3 es el capitán
principal.

### 3.2 Plantillas y reutilización

Para no inflar el catálogo con 60 rosters únicos:

- Cada facción define **3 plantillas de recluta** y **3 jefes** (2 secundarios + 1
  capitán).
- Las plantillas de recluta se reutilizan en las 3 oleadas rotando el orden:
  - Oleada 1: R1, R2, R3, jefe 1.
  - Oleada 2: R2, R3, R1, jefe 2.
  - Oleada 3: R3, R1, R2, capitán.
- Lo que escala entre oleadas es el perfil de IV/EV (y el jefe), no el roster.
- El detalle muestra el nombre/plantilla de cada combate; la rotación evita que el
  primer recluta sea siempre el mismo.

### 3.3 Contenido por facción

| Facción | Color | Imagen | Medallas | Reclutas (R1 / R2 / R3) | Jefe oleada 1 | Jefe oleada 2 | Capitán oleada 3 |
|---|---|---:|---:|---|---|---|---|
| Team Rocket | `#D32F2F` | `rocket.webp` | 2 | R1 `roughneck`: Zubat 41, Rattata 19 / Ekans 23 · R2 `scientist`: Golbat 42, Raticate 20 / Koffing 109 · R3 `roughneck`: Arbok 24, Weezing 110 / Muk 89 | Proton (`scientist`): Golbat 42, Weezing 110 / Arbok 24 | Ariana (`beauty`): Arbok 24, Weezing 110 / Houndoom 229 | Giovanni (`giovanni`): Nidoqueen 31, Nidoking 34 / Rhydon 112 |
| Team Magma | `#E65100` | `magma.webp` | 3 | R1 `kindler`: Numel 322, Poochyena 261 / Baltoy 343 · R2 `hiker`: Camerupt 323, Mightyena 262 / Claydol 344 · R3 `kindler`: Torkoal 324, Houndoom 229 / Claydol 344 | Tabitha (`hiker`): Camerupt 323, Mightyena 262 / Claydol 344 | Courtney (`kindler`): Camerupt 323, Torkoal 324 / Houndoom 229 | Magno (`magno`): Camerupt 323, Torkoal 324 / Golem 76 |
| Team Aqua | `#1565C0` | `aqua.webp` | 4 | R1 `sailor`: Carvanha 318, Poochyena 261 / Spheal 363 · R2 `swimmerf`: Sharpedo 319, Mightyena 262 / Sealeo 364 · R3 `sailor`: Sharpedo 319, Sealeo 364 / Mightyena 262 | Matt (`sailor`): Sharpedo 319, Mightyena 262 / Sealeo 364 | Shelly (`swimmerf`): Sharpedo 319, Sealeo 364 / Walrein 365 | Aquiles (`aquiles`): Sharpedo 319, Walrein 365 / Wailord 321 |
| Team Galaxia | `#00838F` | `galaxia.webp` | 5 | R1 `scientist`: Stunky 434, Zubat 41 / Croagunk 453 · R2 `psychic`: Toxicroak 454, Golbat 42 / Bronzor 436 · R3 `scientist`: Skuntank 435, Crobat 169 / Bronzong 437 | Mars (`psychicf`): Toxicroak 454, Golbat 42 / Bronzor 436 | Saturn (`psychic`): Skuntank 435, Crobat 169 / Bronzong 437 | Ciro (`ciro`): Weavile 461, Honchkrow 430 / Bronzong 437 |
| Team Skull | `#546E7A` | `skull.webp` | 6 | R1 `punkgirl`: Rattata 19, Drowzee 96 / Ekans 23 · R2 `beauty`: Salandit 757, Golbat 42 / Mareanie 747 · R3 `roughneck`: Salazzle 758, Toxapex 748 / Ariados 168 | Recluta Skull de élite (`roughneck`): Salandit 757, Golbat 42 / Mareanie 747 | Plumeria (`beauty`): Salazzle 758, Golbat 42 / Toxapex 748 | Guzmán (`guzman`): Golisopod 768, Ariados 168 / Vikavolt 738 |

Notas:

- **Solo el cuarto combate de cada oleada es jefe.** En las oleadas 1 y 2 son jefes
  secundarios; en la oleada 3 es el capitán principal.
- La barra `/` separa vanguardia (2) y retaguardia (1). El generador normaliza a 3
  combatientes.
- Todos los `species_id` existen en la BD local.
- `magno`, `aquiles`, `ciro` y `guzman` se resuelven con `SpritesEntrenador::url()`; si
  el PNG no existe, fallback a `/images/misc/trainer.webp`.
- Los jefes secundarios usan sprites genéricos existentes; se pueden sustituir por
  assets canónicos cuando estén disponibles.

### 3.4 Reglas

`ReglasHabitatTemporal`:

```php
public const TOTAL_OLEADAS = 3;
public const COMBATES_POR_OLEADA = 4;
public const MULTIPLICADOR_RECOMPENSA = 3.0;
```

Métodos:

- `medallasRequeridasOleada(int $oleada): int` →
  `1 => 0`, `2 => 9`, `3 => 15`.
- `indiceDificultad(string $slug): int` →
  `rocket => 3`, `magma => 6`, `aqua => 9`, `galaxia => 12`, `skull => 15`.
  Solo ajusta el perfil de movimientos del generador (`ConfiguracionCombate`).
- `nivelRival(int $nivelJugador): int` → `max(1, $nivelJugador)`.
  **Todos los rivales tienen exactamente el nivel del jugador; sin bonus.**
- `perfilIvsEvs(int $oleada, bool $esJefe): PerfilIvsEvs`:
  - Oleada 1: reclutas `TopUno`; jefe `TopDos`.
  - Oleada 2: reclutas `TopDos`; jefe `Completo`.
  - Oleada 3: reclutas `Completo`; capitán `Completo` (tope).
  - Es el mismo sistema de perfiles que gimnasio (`PerfilIvsEvs`), con el jefe
    subiendo un tier como el líder de gimnasio.

Sin requisito de nivel de jugador: el desbloqueo es solo por medallas.

## 4. Progresión y estados

### 4.1 Desbloqueo de facción

Una facción es visible si:

```text
medallasCompletadas >= faccion.medallasDesbloqueo
```

No se muestran facciones bloqueadas. La provincia Temporal aparece cuando hay al menos
una facción visible y no completada.

### 4.2 Progreso

Tabla `habitat_temporal_progreso`:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK users | `cascadeOnDelete` |
| `faccion_slug` | varchar(40) | validado contra catálogo |
| `oleada_actual` | tinyint unsigned | 1-3, default 1 |
| `combate_actual` | tinyint unsigned | 1-4, default 1 |
| `completed_at` | timestamp nullable | se rellena al ganar el combate 4 de la oleada 3 |
| `created_at` / `updated_at` | timestamps | |

`unique(user_id, faccion_slug)`.

Si no existe fila: `oleada_actual = 1`, `combate_actual = 1`, `completada = false`.

### 4.3 Estados

`EstadoCombate` (string-backed): `superado`, `disponible`, `bloqueado`.

Para el combate `c` de la oleada `o`:

- Si `o < oleada_actual`, o `o === oleada_actual && c < combate_actual` → `superado`.
- Si `o === oleada_actual && c === combate_actual`:
  - Si `medallas >= medallasRequeridasOleada(o)` → `disponible`.
  - Si no → `bloqueado` con motivo «Se desbloquea con N medallas (tienes M).».
- Si `o > oleada_actual`, o `o === oleada_actual && c > combate_actual` → `bloqueado`.
  Motivo: si la oleada `o` aún no alcanza su puerta de medallas, «Se desbloquea con N
  medallas (tienes M).»; si ya la alcanza, «Completa la oleada anterior.» o «Completa
  el combate anterior.».

La oleada 1 no tiene puerta de medallas; la 2 y la 3 sí (9 y 15). El estado de la
oleada (`superada`/`actual`/`bloqueada`) se deriva de su progreso y de su puerta.

### 4.4 Avance

`RegistrarResultadoHabitatTemporal`:

- Solo si `won === true` y `userId === authUserId`.
- Solo si `oleada === oleadaActual` y `combate === combateActual` (evita repetir).
- Si `combate < 4`: `combate_actual++`.
- Si `combate === 4` y `oleada < 3`: `oleada_actual++`, `combate_actual = 1`.
- Si `combate === 4` y `oleada === 3`: `completed_at = now()`; el hábitat desaparece.
- Derrota: no avanza, sin cooldown.

Si el jugador completa la oleada 1 antes de las 9 medallas, el progreso queda en
oleada 2/combate 1, pero la oleada 2 aparece bloqueada por medallas hasta llegar a 9.
Igual para la oleada 3 con 15.

### 4.5 Ocultación

- Facción completada → no se lista y su detalle responde 404.
- Facción con la oleada actual bloqueada por medallas → no se lista y su detalle
  responde 404; reaparece cuando se alcanza el hito.
- Sin facciones visibles pendientes → `ObtenerProvinciaTemporal` devuelve `null` y la
  pestaña «Temporal» no se renderiza.
- Si más adelante se desbloquea otra facción, la provincia vuelve a aparecer.

## 5. Contratos

### 5.1 Repositorios

```php
interface ProgresoHabitatTemporalRepositoryInterface
{
    public function obtener(int $userId, string $faccionSlug): ?ProgresoHabitatTemporal;

    public function registrarVictoria(
        int $userId,
        string $faccionSlug,
        int $oleadaCompletada,
        int $combateCompletado,
    ): void;
}

interface MedallasRepositoryInterface
{
    public function contarCompletadas(int $userId): int;
}
```

`ProgresoHabitatTemporal` es un DTO readonly con `oleadaActual`, `combateActual` y
`completada`.

### 5.2 API

```text
GET  /api/habitats-temporales/{faccion}
GET  /api/habitats-temporales/{faccion}/pc?team_id=
POST /api/habitats-temporales/{faccion}/oleadas/{oleada}/combates/{combate}/combatir
GET  /habitats/temporales/{faccion}          (vista)
```

Las imágenes se sirven directamente desde `public/images/rivales`; no hay ruta de
asset propia.

`GET` detalle (auth): **solo la oleada y el combate actuales**.

```json
{
  "faccion": {
    "slug": "rocket",
    "nombre": "Team Rocket",
    "descripcion": "…",
    "imagen_url": "…",
    "color": "#D32F2F",
    "oleada_actual": 1,
    "combate_actual": 1,
    "total_oleadas": 3,
    "combates_por_oleada": 4,
    "estado": "disponible",
    "motivo_bloqueo": null
  },
  "oleada": {
    "numero": 1,
    "estado": "actual",
    "motivo_bloqueo": null
  },
  "combate": {
    "numero": 1,
    "nombre": "Recluta Rocket",
    "entrenador": "Recluta Rocket",
    "descripcion": "…",
    "sprite_url": "…",
    "es_jefe": false,
    "estado": "disponible",
    "motivo_bloqueo": null
  },
  "combates": [
    {
      "numero": 1,
      "nombre": "Recluta Rocket",
      "entrenador": "Recluta Rocket",
      "descripcion": "…",
      "sprite_url": "…",
      "es_jefe": false,
      "estado": "disponible",
      "motivo_bloqueo": null
    }
  ]
}
```

La oleada actual incluye sus **4 combates** en `combates`; las oleadas futuras **no se
envían**. `combate` es el combate actual (para el modal). Los combates posteriores al
actual aparecen `bloqueado` por orden, sin texto de medallas. Si la oleada actual no
está disponible por medallas, el detalle responde 404 y la facción no se lista.

`GET /pc` devuelve el contrato de `ComparacionPc` + `perfil_ivs_evs` y
`contexto.tipo = 'habitat_temporal'`. Nunca incluye composición, especies, tipos ni
movimientos del rival.

`POST combatir`:

- Valida `team_id` del usuario y `formacion` opcional (mismos valores que gimnasio).
- Valida facción visible, no completada, oleada/combate actuales y puerta de medallas.
- Devuelve `{ "battle_id": "...", "redirect": "/combate?battle_id=..." }`.

Errores de dominio:

- `FaccionNoExiste` → 404.
- `FaccionCompletada` → 422.
- `OleadaNoDisponible` → 422.
- `CombateNoDisponible` → 422.

### 5.3 Datos para la portada de hábitats

`HabitatsController@index` recibe además:

```json
{
  "nombre": "Temporal",
  "descripcion": "Hábitats de equipos rivales. Desaparecen al completarlos.",
  "total": 1,
  "facciones": [
    {
      "slug": "rocket",
      "nombre": "Team Rocket",
      "descripcion": "…",
      "imagen_url": "…",
      "color": "#D32F2F",
      "oleada_actual": 1,
      "combate_actual": 1,
      "total_oleadas": 3,
      "combates_por_oleada": 4,
      "estado": "disponible",
      "motivo_bloqueo": null,
      "enlace": "/habitats/temporales/rocket"
    }
  ]
}
```

Serialización en la frontera con `toArray()` de los DTO; la vista no calcula estados,
progreso, URLs ni colores.

## 6. Combate

`IniciarCombateHabitatTemporal`:

1. Carga la facción del catálogo (`FaccionNoExiste` si no existe).
2. Comprueba medallas de desbloqueo (404 si aún no visible).
3. Comprueba que no está completada.
4. Comprueba que `oleada/combate` coinciden con el progreso actual.
5. Comprueba la puerta de medallas de la oleada.
6. Construye el rival del combate:
   - `nivelRival = ReglasHabitatTemporal::nivelRival($nivelJugador)` (nivel exacto
     del jugador).
   - `perfil = ReglasHabitatTemporal::perfilIvsEvs($oleada, $combate === 4)`.
   - `GeneradorPokemonGimnasio::generar(equipo, $nivelRival, $perfil, $indice, $combate === 4, $movimientos)`.
7. Construye el equipo del jugador con `ConstruirEquipoJugador` y `FormacionEquipo`.
8. `CreadorBatallaSesion::crearYGuardar` con prefijo `faccion` y metadatos:

```php
[
    'tipo' => 'habitat_temporal',
    'faccion_slug' => $slug,
    'oleada' => $oleada,
    'combate' => $combate,
    'es_jefe' => $combate === 4,
    'nivel_rival' => $nivelRival,
    'user_id' => $userId,
    'team_id' => $teamId,
]
```

`PresentadorResultadoBatalla`:

- Nueva rama `'habitat_temporal' => self::procesarHabitatTemporal(...)`.
- Elimina la rama `'torre'`, `procesarTorre()` y sus imports.
- En victoria y avance:
  - `OtorgarRecompensasEntrenador` con `multiplicador = ReglasHabitatTemporal::MULTIPLICADOR_RECOMPENSA`
    y `dificultad` del perfil de la facción.
  - Log según combate/oleada:
    - Combate 1-3 superado → «¡Recluta derrotado! Siguiente combate disponible.».
    - Combate 4 de oleada 1-2 → «¡Jefe secundario derrotado! Oleada completada.».
    - Combate 4 de oleada 3 → «¡Has desmantelado a Team X! El hábitat ha desaparecido.».
- Sin reclutamiento de especie ni medalla.
- El resultado incluye `returnUrl`/`returnLabel` para el botón del modal de victoria:
  - Hábitat temporal → `/habitats/temporales/{slug}` (o `/habitats` si completa).
  - Gimnasio → `/gimnasios`.
  - Ruta/entrenador → `/habitats/{id}`.

## 7. Presentación

### 7.1 Portada de hábitats

`resources/views/habitats/index.blade.php`:

- Pestañas existentes + pestaña «Temporal» al final, solo si
  `$provinciaTemporal !== null`.
- La pestaña muestra el total de facciones activas.
- Panel con grid de tarjetas (2/3/4/6 columnas responsive).
- Tarjeta:
  - Imagen 3:2 (`imagen_url`) con fallback `onerror` a `/images/misc/raid.webp`.
  - Borde de 4 px con `style="border-color: {{ $faccion['color'] }}"`.
  - Nombre, descripción corta, «Oleada X/3 · Combate Y/4».
  - Solo se listan facciones con el combate actual disponible. Las completadas o con la
  oleada actual bloqueada no se muestran; no hay badge «bloqueada» ni texto de
  requisito de medallas.
  - Enlace a `enlace` (backend).
- Sin lógica de negocio en Blade: todo viene calculado.

### 7.2 Detalle de facción

`resources/views/habitats/temporales/show.blade.php`:

- Fetch a `/api/habitats-temporales/{slug}` al montar.
- Ancho `max-w-6xl`, como la vista de gimnasios.
- Cabecera tipo gimnasio: imagen de facción, borde superior del color, nombre,
  descripción y progreso «Oleada N · Combate Y de 4».
- **Solo se muestra la oleada actual, con sus 4 entrenadores.** Las oleadas futuras no
  llegan en la respuesta y no se renderizan.
  - Grid de 4 tarjetas con el mismo estilo que las etapas de gimnasio: icono de estado
    (superado/disponible/bloqueado), nombre, descripción, sprite 80×80, badge «Jefe»
    en el 4.º y botón «¡Combatir!» solo en el combate disponible.
  - Si la oleada actual no está disponible por medallas, la facción no se lista y su
    detalle responde 404; reaparece cuando se alcanza el hito. Nunca se muestra el texto
    del requisito ni la palabra «Oleada» en la UI.
- **El equipo no se muestra en la página.** Al pulsar «¡Combatir!» se abre el mismo
  modal de formación que en gimnasios (`partials/formacion-combate`): selección de
  equipo, formación vanguardia/retaguardia, comparación de PC y leyenda IV/EV.
  El equipo rival no se muestra; solo el PC agregado del rival actual.
- `GET /api/habitats-temporales/{faccion}/pc?team_id=` calcula el PC del jugador y del
  rival del combate actual con los mismos helpers que gimnasio
  (`CalculadoraPcRival`, `ObtenerPcEquipoJugador`, `ComparadorPc`,
  `ConstruirLeyendaIvsEvs`).
- `confirmarCombate()` hace POST al endpoint con `team_id` y `formacion`, y redirige a
  `data.redirect`.
- La vista no revela la composición del equipo rival ni su nivel.

### 7.3 Assets

- Imágenes de facción: `public/images/rivales/{nombre}.{webp|png|jpg|jpeg}`.
  El catálogo guarda el nombre base (p. ej. `Team_Rocket`); el resolver prueba las
  extensiones en orden y usa `/images/misc/raid.webp` como fallback.
- Sprites de capitanes: `public/images/trainers/{slug}.png`.
  Se añaden `magno.png`, `aquiles.png`, `ciro.png` y `guzman.png` cuando estén;
  `SpritesEntrenador` ya hace fallback.
- Sprites de jefes secundarios: se usan clases genéricas existentes; se pueden añadir
  slugs canónicos después.

## 8. Retirada de Torres

### 8.1 Borrar

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

### 8.2 Modificar

- `routes/web.php`: quitar import, rutas `/torres` y `require routes/torres.php`.
- `resources/views/layouts/app.blade.php`: quitar la entrada «Torres».
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php`: quitar imports, rama
  `'torre'` y `procesarTorre()`.
- `database/seeders/CatalogoSeeder.php`: quitar `TorreEventoSeeder::class`.
- `app/Models/Habitat.php`: corregir el comentario que menciona torres.
- `config/habitats.php`: corregir el comentario que menciona torres.
- `resources/views/habitats/partials/columna-derecha.blade.php`: sustituir el texto
  «los legendarios solo aparecen en torres temporales» por una referencia genérica.
- `phpstan-baseline.php`: sin entradas de Torres (verificado); no se toca.

### 8.3 Base de datos

- No se añade migración de limpieza (decisión del desarrollador).
- Las tablas `torre_eventos` y `torre_progreso` de la BD local quedan como rastro
  inerte; las instalaciones nuevas no las crean al desaparecer las migraciones.
- No se ejecuta `migrate:rollback` ni ninguna operación destructiva.

## 9. Archivos nuevos/modificados (resumen)

**Nuevos**

- `src/HabitatsTemporales/**` (Domain/App/Infra/Presentation).
- `app/Models/HabitatTemporalProgreso.php`.
- `app/Http/Controllers/HabitatsTemporalesViewController.php`.
- `resources/views/habitats/temporales/show.blade.php`.
- `routes/habitats_temporales.php`.
- `database/migrations/2026_11_23_000001_create_habitat_temporal_progreso_table.php`.

**Modificados**

- `app/Http/Controllers/HabitatsController.php` (inyectar caso de uso y pasar la
  provincia temporal).
- `resources/views/habitats/index.blade.php` (pestaña y tarjetas temporales).
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` (rama nueva; retirada torre).
- `app/Providers/AppServiceProvider.php` (bindings de repositorios).
- `routes/web.php` (quitar torres; cargar rutas nuevas).
- `routes/habitats.php` (ruta de imágenes de facción; `whereNumber('id')` en la ruta
  salvaje).
- `resources/views/layouts/app.blade.php` (quitar Torres).
- `database/seeders/CatalogoSeeder.php` (quitar TorreEventoSeeder).
- `app/Models/Habitat.php`, `config/habitats.php`,
  `resources/views/habitats/partials/columna-derecha.blade.php` (comentarios/texto).
