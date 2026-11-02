# TASK-005 — Análisis

- **Analista:** `analyst`
- **Fecha:** 2026-11-02
- **Alcance:** causa raíz de los 4 síntomas del diario de Exploraciones
- **Estado:** completado

## 1. Resumen ejecutivo

Los 4 síntomas tienen 3 causas raíz independientes, todas en presentación salvo una:

1. **Nombre del caramelo de familia**: `PresentadorExploraciones::nombresPokemon()` no escanea la bitácora de las **terminadas**, solo `resultado['capturados']`; el `pokemon_id=81` del hallazgo nunca se resuelve → `nombre=null` → `candy_nombre=null` → blade pinta «Pokémon».
2. **Caramelo de tipo**: `EventoExploracionVista::hallazgo()` **ignora `tipo_id`**; busca `slug`/`tipo_nombre`/`tipo_label`, que el generador no escribe (`SimuladorEncuentros` escribe `tipo_id`). Resultado: `candy_slug=""`, `candy_nombre="Desconocido"`, sin imagen.
3. **«Sin reclutado»**: `ExploradorVista::nombre()` devuelve el literal cuando `reclutado->nombre` es `null`, sin mirar `reclutado->pokemon->name`; ese valor se inyecta como `explorador_nombre` en **todos** los eventos del diario.
4. **Derrota sin PV**: `resolucionCombate()` persiste solo el estado **post-combate** (`hp_final=0`) y descarta `hpMax`; el HP de entrada y el HP del rival nunca se capturan. El evento raíz `derrota` solo lleva `reason`.

La imagen «como en emboscada» es viable porque `EventoExploracionVista` ya calcula `icono_pokemon` (`iconos_webp/{id}.webp`, el sprite de especie que usa emboscada); hoy el diario pinta `candy_pokemon/{id}.webp`, que es un caramelo genérico.

## 2. Causa raíz por síntoma (con evidencia)

### Síntoma 1 — «Caramelos de Pokémon ×1» + imagen

**Cadena verificada:**

- `src/Exploraciones/Presentation/PresentadorExploraciones.php:58-81` — `nombresPokemon()`:
  - `:61-67` recorre bitácoras de **activas**.
  - `:69-74` para **terminadas** solo lee `resultado['capturados']`, **no** `bitacora`.
- La exploración 24 está terminada y su hallazgo `{"tipo":"hallazgo","subtype":"caramelo_familia","pokemon_id":81,...}` no está en `capturados` → el mapa `$nombres` no contiene `81`.
- `src/Exploraciones/Presentation/EventoExploracionVista.php:302-315` — `nombre()` devuelve `null` si no hay `nombre` en el evento ni en `$nombres`.
- `EventoExploracionVista.php:241-252` — `carameloFamilia()` copia `$vista['nombre']` a `candy_nombre` (null) y fija `candy_src = /images/candy_pokemon/{id}.webp`.
- `resources/views/exploraciones/_evento.blade.php:102-105` — `Caramelos de <strong>{{ $evento['candy_nombre'] ?? 'Pokémon' }}</strong>` → **«Caramelos de Pokémon»**.

**Verificado en runtime** (`EventoExploracionVista::toView` sobre el evento real):

- sin nombres → `nombre:null`, `candy_src:"/images/candy_pokemon/81.webp"`, `candy_nombre:null`.
- con nombres → `nombre:"magnemite"`, `candy_nombre:"magnemite"`.

**Sobre la imagen:**

- `public/images/candy_pokemon/81.webp` **existe**, pero es una **bola de caramelo genérica** (verificado visualmente).
- `public/images/iconos_webp/81.webp` **existe** y es el sprite de magnemite (emboscada pinta esos: `_evento.blade.php:39-53` usa `iconos_pokemon`, generado en `EventoExploracionVista.php:64-68`).
- `public/images/candy_pokemon/24.webp` **no existe** → el `onerror` cae a `candy_pokemon/0.webp` (`_caramelo.blade.php:2` y `_evento.blade.php:2`). Es decir, el `candy_pokemon` no es fiable al 100 % (647 archivos para >1000 Pokémon con formas).

**¿Basta con escanear la bitácora de terminadas?** Sí para el diario: todo evento de hallazgo de familia se genera con `pokemonId` (`SimuladorEncuentros.php:277-283`) y también el hallazgo de emboscada evitada (`FinalizarExploracionHandler.php:344-351`). No hace falta `evolution_chain_id` **para el diario**. Ojo: si se amplía el escaneo a `resultado['caramelos_familia']`, ahí `pokemon_id` es opcional (`PresentadorExploraciones.php:237`, y el fixture `ExploracionesViewTest.php:34` lo omite), por lo que ese caso sí requeriría cadena o `nombre`.

### Síntoma 2 — «Caramelo de tipo Desconocido ×1»

**Cadena verificada:**

- `src/Exploraciones/Domain/SimuladorEncuentros.php:296-302` — genera `tipo_id` (no escribe `slug` ni `tipo_nombre`).
- `EventoExploracionVista.php:221-231` — `hallazgo()` para `caramelo_tipo` lee **solo** `$vista['slug']`, `$vista['tipo_nombre']`, `$vista['tipo_label']`; **nunca `tipo_id`**. Como `slug` está vacío: `candy_nombre = 'Desconocido'`, `candy_src = null`.
- Verificado en runtime con `tipo_id=9`: `candy_slug:""`, `candy_nombre:"Desconocido"`, `candy_src:null`.

**APIs exactas disponibles:**

- `app/Enums/TipoEnum.php:69-72` → `TipoEnum::fromId(int): ?self` (alias de `tryFrom`).
- `TipoEnum.php:31-53` → `->label()`; `9 → STEEL → 'Acero'`.
- `src/Shared/Domain/SlugTipo.php:37-48` → `SlugTipo::de('Acero') = 'acero'` (mapa de los 18 tipos como fallback de `iconv`).
- `public/images/candy_type/` contiene los 18 slugs, incluido `acero.webp` (verificado).

**Dónde omitir si no se resuelve:** recomiendo **marcador en `EventoExploracionVista`** (`'oculto' => true`) y **filtrado en `DiarioExploracion`** antes de `$transformados[]`, `total` y `grupos` (`DiarioExploracion.php:105-134`, `total` en `:130`). Motivo: la resolución (`tipo_id → label/slug`) es responsabilidad del presentador de evento, pero el descarte debe ocurrir donde se calcula el total y los grupos; filtrar solo en el presentador dejaría eventos fantasma en `total`/`grupos` y en la ruta `PresentadorExploraciones::activaToView` (`:317-320`). Si se quiere cubrir también esa ruta legacy, aplicar el mismo filtro en `activaToView`.

**`caramelo_ev` (stat 3) — bug adyacente confirmado:**

- `EventoExploracionVista.php:46-52` solo completa stats si `$tipo === 'caramelo_ev'` (evento **raíz**). Los hallazgos son `tipo:'hallazgo', subtype:'caramelo_ev'` → entra por `:96`/`:217-219` y **nunca** se completa: `stat_nombre:null`, `stat_slug:null`, `candy_nombre_js:"statName(3)"`. Verificado en runtime con `completarStats=true`.
- `_evento.blade.php:119-124` pinta `<strong x-text="statName(3)">`; hay un `statName()` JS en `index.blade.php:434-437` que lo resuelve en navegador, pero sin imagen y dependiente de JS.
- **Trampa adicional:** `EventoExploracionVista.php:17-24` define slugs `spAtk`, `spDef`, `speed`, pero los archivos reales son `atksp.webp`, `defsp.webp`, `spd.webp` (`StatEnum::slug()` en `app/Enums/StatEnum.php:49-59`). Si se activa `completarStats` para el hallazgo usando esa constante, los stats 4/5/6 generarían `candy_src` 404. Hay que reutilizar `StatEnum::slug()` (ya usado por `PresentadorExploraciones::stats()` `:46`) o corregir la constante.

### Síntoma 3 — «Sin reclutado repele el ataque»

**Cadena verificada (completa):**

1. `src/Exploraciones/Presentation/ExploradorVista.php:96-101` — `nombre()` devuelve el literal `'Sin reclutado'` si `nombre` es null/vacío. No consulta `pokemon`.
2. `PresentadorExploraciones.php:175-176` (activas) y `:267-268` (terminadas) pasan `$explorador['nombre']` a `DiarioExploracion::toView`.
3. `DiarioExploracion.php:62-70` inyecta `explorador_nombre` en **todos** los eventos (incluidos los raíz).
4. `EventoExploracionVista.php:187-192` (`sujeto()`) usa ese string tal cual → `emboscada()` `:127-150` produce «Sin reclutado repele el ataque».

**Datos reales:** exploración 24, reclutado 59 con `nombre = NULL`, `pokemon_id = 464`, `pokemon.name = "rhyperior"`.

**Relación cargada:** `ExploracionActivaController::index` hace `ExploracionActiva::with('reclutado.pokemon.types', 'habitat')` (≈línea 57), por lo que `reclutado.pokemon` está disponible sin N+1 en el listado. Además, `CombateExploracion::combatir()` ya usa exactamente el fallback propuesto: `$reclutado->nombre ?? $reclutado->pokemon->name` (`CombateExploracion.php:59`) — precedente interno del proyecto.

**Sobre `PresentadorExploraciones` 185/275:** `'equipo' => $reclutado !== null ? $reclutado->nombre : 'Sin reclutado'` devuelve **null** (no el literal) cuando el reclutado existe con nombre null. `'equipo'` no se consume en ninguna vista (grep sin resultados) pero sí se asserta en `ExploracionesPageTest.php:157` y `:241`. No es necesario tocarlo para el diario. La tarjeta (`index.blade.php:42` y `:181`) usa `explorador['nombre'] ?? …`, y como `explorador['nombre']` es un string no nulo, el `??` nunca cae al fallback: **arreglar `ExploradorVista::nombre()` corrige tarjeta y diario a la vez**.

### Síntoma 4 — Explicar la derrota y mostrar PV

**Cadena verificada:**

- `src/Exploraciones/App/ProcesarExploracionHandler.php:489-502` — `resolucionCombate()` persiste `hp_final` (ya post-combate), `barrera_*_final`, `barrera_*_max`, `log`; **descarta `$resultado->hpMax`** y no tiene HP inicial.
- `ProcesarExploracionHandler.php:453-477` — `combatirEvento()` tiene `$estadoExplorador` **antes** del combate y llama a `estadoInicialCombate()` (`:529-539`). Ahí está el dato de entrada. Importante: el descanso previo ya se aplicó antes (`:296-303` y `:353-361`), así que el HP capturado en `combatirEvento` es el real «cuando enfrentó».
- `src/Exploraciones/App/CombateExploracion.php:89-131` — en `combatirDatos()`:
  - tras aplicar `estadoInicial` (`:103-107`), `$combatiente->hpActual()` es el HP inicial;
  - `$combatiente->pokemon()->battleStats()->hp` es el máximo (`:117`), ya expuesto como `hpMax`;
  - el rival (`$team2`) tiene `hpActual()` y `battleStats()->hp` accesibles antes/después, pero **no se capturan**.
- `src/Exploraciones/Domain/ValueObjects/ResultadoBatallaExploracion.php:18-49` — tiene `hpMax` pero no HP inicial ni datos del rival; `toArray()` emite `hp_max` pero nadie lo persiste en el evento.
- `ProcesarExploracionHandler.php:99-104` — evento raíz `derrota` = `{"reason":"explorador_debilitado","timestamp":...}`. `EventoExploracionVista.php:109-112` lo pinta «cae debilitado» y `_evento.blade.php:245-247` añade `(explorador_debilitado)`.

**¿Round-trip `EventoExploracion::desdeArray/aArray` conserva claves nuevas?** **No.** `EventoExploracion.php:109-218` es una **lista blanca**; cualquier clave que no esté en el constructor se pierde. Hoy no rompe porque:

- la bitácora se persiste con `array_merge($evento, $resolucion)` en crudo (`ProcesarExploracionHandler.php:305, 368, 386`);
- `FinalizarExploracionHandler` lee con `desdeArray` (`:85`, `:162`) pero **no reescribe** la bitácora con `aArrays()`.

Si en el futuro algo reescribe la bitácora vía `ColeccionEventosExploracion::aArrays()` o `BitacoraExploracion::toArray()` (`BitacoraExploracion.php:37`), las claves nuevas desaparecen. Por coherencia, si se añaden `hp_inicial`/`hp_rival_*` hay que añadirlas a `EventoExploracion` (constructor + `desdeArray` + `aArray`).

**«Por qué» con datos disponibles:** HP inicial/máximo, HP rival inicial/final/máximo, `resolucion`, `log` (contiene rondas `--- Ronda N ---`, quién atacó y daño). Con eso se puede narrar, p. ej.: «Cayó con 35/39 PS tras 4 rondas; a bronzor le quedaban 20/34 PS». Sin capturar HP inicial/rival, lo único honesto es el `log` crudo.

## 3. Contrato de datos: actual vs propuesto

### Evento de combate (`encuentro`/`emboscada`)

| Clave | Hoy | Propuesta |
|---|---|---|
| `resolucion`, `victoria`, `duration_loss` | sí | igual |
| `hp_final` | sí (post) | igual |
| `barrera_*_final`, `barrera_*_max` | sí | igual |
| `log` | sí | igual |
| `hp_inicial` | **no** | nuevo `float` (estado al empezar; si primer combate = `hp_max`) |
| `hp_max` | **no** (existe en el VO, no se persiste) | nuevo `float` |
| `rival_hp_inicial` | **no** | nuevo `float` |
| `rival_hp_final` | **no** | nuevo `float` |
| `rival_hp_max` | **no** | nuevo `float` |

Compatible: aditivo; eventos históricos sin claves → el presentador no pinta bloque de PV (`array_key_exists`, no `?? 0`, porque `hp_final=0` es un valor válido).

### Evento `caramelo_familia`

| Clave | Hoy | Propuesta |
|---|---|---|
| `candy_src` | `/images/candy_pokemon/{id}.webp` | mantener (contrato) |
| `candy_nombre` | null si no hay mapa | resuelto si `nombresPokemon` escanea bitácora de terminadas |
| `icono_pokemon` | ya existe (`/images/iconos_webp/{id}.webp`) | consumirlo en la vista del diario (sprite, como emboscada) |

### Evento `caramelo_tipo`

| Clave | Hoy | Propuesta |
|---|---|---|
| `candy_slug` | `""` | `SlugTipo::de(TipoEnum::fromId(tipo_id)->label())` |
| `candy_nombre` | `"Desconocido"` | `label()` (`"Acero"`) |
| `candy_src` | null | `/images/candy_type/{slug}.webp` |
| `oculto` | — | `true` si no hay `tipo_id` válido ni `slug`/`tipo_nombre` legacy |

### Evento `caramelo_ev` (hallazgo)

| Clave | Hoy | Propuesta |
|---|---|---|
| `stat_nombre`/`stat_slug` | null en hallazgos | completar también para `tipo='hallazgo' && subtype='caramelo_ev'` |
| `candy_src` | null | `/images/candy_ev/{StatEnum::slug()}.webp` (usar `atksp`/`defsp`/`spd`) |

## 4. Puntos de cambio mínimos por archivo

Sin código completo; solo firmas/keys.

### `src/Exploraciones/Presentation/PresentadorExploraciones.php`

- `nombresPokemon()` `:58-81`: añadir dentro del bucle de terminadas el recorrido de `eventosDe($exp)->get('bitacora', [])` con `idsDeEvento()` (mismo helper de `:98-109`). Opcionalmente escanear `resultado['caramelos_familia']` con fallback de nombre.
- **No** tocar `:185`/`:275` (`equipo`) en el cambio mínimo.

### `src/Exploraciones/Presentation/ExploradorVista.php`

- `nombre(?Reclutado): string` `:96-101`: fallback `$reclutado?->pokemon?->name` antes del literal; tratar `''`/espacios como ausente. Mantener `'Sin reclutado'` solo si tampoco hay Pokémon (compatibilidad con `ExploradorVistaTest:21`).

### `src/Exploraciones/Presentation/EventoExploracionVista.php`

- `hallazgo()` `:221-231`: resolver `tipo_id` con `TipoEnum::fromId()` + `SlugTipo::de()`; fallback a `slug`/`tipo_nombre`/`tipo_label` para eventos legacy; `oculto => true` si no se resuelve.
- `:46-52`: aplicar `completarStats` también a `subtype === 'caramelo_ev'` (o pasar stat info en `hallazgo()`), y sustituir `STATS` `:17-24` por `StatEnum::slug()`.
- `derrota` `:109-112`: mapear `reason` a texto humano (`explorador_debilitado` → «cayó sin fuerzas» u homólogo), conservando `reason` como key.
- `encuentro()` `:273-296`: nuevas keys de presentación para la explicación (`hp_inicial`, `hp_max`, `rival_hp_*`, `rondas`, `detalle_derrota`) cuando existan; no romper cuando falten.

### `src/Exploraciones/Presentation/DiarioExploracion.php`

- Bucle `:108-125`: `continue` si `($vista['oculto'] ?? false) === true` **antes** de añadir a `$transformados` y `$grupos` (así `total` `:130` cuadra).

### `resources/views/exploraciones/_evento.blade.php`

- `:90-105` (hallazgo familia) y `:146-161` (familia raíz legacy): usar `$evento['icono_pokemon']` como imagen principal (sprite de especie) con fallback a `candy_src` y luego a `0.webp`; mantener alt/title con `candy_alt`.
- `:183-204` (encuentro): añadir bloque de PV inicial/máximo del explorador y del rival, rondas y causa — solo si las keys existen.
- `:239-248`: mostrar el texto mapeado de derrota en vez de `reason` crudo (o ambos, con `reason` como `title`).
- Ojo: hay ramas duplicadas (`hallazgo/caramelo_familia` vs raíz `caramelo_familia`); conviene unificar en un partial para no arreglar solo una.

### `src/Exploraciones/App/CombateExploracion.php`

- `combatirDatos()` `:89-131`: capturar tras aplicar `estadoInicial` (`:103-107`) `$hpInicial = $combatiente->hpActual()`; capturar del rival `$rival->hpActual()` antes/después y `$rival->pokemon()->battleStats()->hp`; pasarlos al VO.

### `src/Exploraciones/Domain/ValueObjects/ResultadoBatallaExploracion.php`

- Constructor `:18-28`: añadir `?float $hpInicial = null`, `?float $rivalHpInicial = null`, `?float $rivalHpFinal = null`, `?float $rivalHpMax = null` **con default null** para no romper el mock de `ProcesarExploracionCombateTest.php:272`.
- `toArray()` `:37-49`: emitir las nuevas claves solo si no son null.

### `src/Exploraciones/App/ProcesarExploracionHandler.php`

- `resolucionCombate()` `:489-502`: añadir `hp_inicial`, `hp_max`, `rival_hp_inicial`, `rival_hp_final`, `rival_hp_max` desde `$resultado`.
- `combatirEvento()` `:453-477`: si se opta por no tocar el VO, capturar `$hpAntes = $estadoExplorador->sinCombate() ? null : $estadoExplorador->hp` y pasarlo a `resolucionCombate` (opción B). El rival obliga a tocar `CombateExploracion` igualmente.

### `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php`

- `:27-55`, `:62-98`, `:109-218`: añadir `hpInicial`, `hpMax`, `rivalHpInicial`, `rivalHpFinal`, `rivalHpMax` para que el round-trip no las descarte.

### `src/Exploraciones/Domain/SimuladorEncuentros.php`

- **Sin cambios** (ya genera `tipo_id` y `pokemon_id`).

## 5. Tests existentes y casos nuevos

**Existentes relevantes:**

- `tests/Unit/Exploraciones/EventoExploracionVistaTest.php` — **no cubre ningún caramelo** (solo descanso/emboscada/derrota/etc.).
- `tests/Unit/Exploraciones/DiarioExploracionTest.php` — orden, eventos raíz, `explorador_nombre`; no cubre ocultación.
- `tests/Unit/Exploraciones/ExploradorVistaTest.php:21` — asserta `'Sin reclutado'` con `null`; no hay caso «reclutado con nombre null y pokemon cargado».
- `tests/Feature/ExploracionesPageTest.php:139-140` (bitácora activa con `caramelo_familia`/`caramelo_ev`) y `:236-266` (resumen de terminada); `:157` y `:241` assertan `equipo`.
- `tests/Feature/ExploracionesViewTest.php:24` — `caramelo_familia` con `nombre` ya presente (no reproduce el fallo).
- `tests/Feature/Exploraciones/ProcesarExploracionCombateTest.php:147-179` — aserta `resolucion`/`hp_final`/barreras; `:272` construye el VO con named args.
- `tests/Unit/Exploraciones/CombateExploracionTest.php:128-259` — propiedades del VO, sin HP inicial/rival.

**Casos nuevos necesarios:**

1. `EventoExploracionVistaTest`: `caramelo_familia` con `pokemon_id` + mapa → `candy_nombre`/`candy_alt`; sin mapa → null sin excepción.
2. `EventoExploracionVistaTest`: `caramelo_tipo` con `tipo_id=9` → `Acero`/`acero`/`/images/candy_type/acero.webp`; `tipo_id` inválido/ausente y sin legacy → `oculto=true`.
3. `EventoExploracionVistaTest`: `hallazgo/caramelo_ev` stat 4 con `completarStats` → `atksp` (no `spAtk`).
4. `DiarioExploracionTest`: evento oculto no suma en `total` ni aparece en `grupos`; terminada con bitácora de familia resuelta.
5. `ExploradorVistaTest`: reclutado `nombre=null` + `pokemon` cargado → nombre del Pokémon; sin relación → `'Sin reclutado'`.
6. `ExploracionesPageTest`: terminada cuya bitácora contiene `caramelo_familia` con Pokémon **no capturado** → nombre y sprite; `caramelo_tipo` `tipo_id=9` renderizado; explorador con `nombre=null` → nombre del Pokémon en tarjeta y diario.
7. `ProcesarExploracionCombateTest`: encuentro perdido persiste `hp_inicial>0`, `hp_max`, `rival_hp_*`; evento histórico sin esas claves sigue renderizando sin error.
8. `CombateExploracionTest`: `$resultado->hpInicial`, `rivalHpInicial/Final/Max` coherentes (incluido `estadoInicial` no nulo).
9. `ExploracionesViewTest`: HTML del diario con sprite de especie y sin «Sin reclutado»/«Desconocido».

## 6. Riesgos y dudas abiertas

**Riesgos**

- [medio] `completarStats` para EV puede cambiar payloads esperados en tests de vista que hoy confían en el fallback JS. Impacto: asserts de `stat_nombre`/`statName`. Mitigación: caso explícito y revisar `ExploracionesViewTest`.
- [medio] Slugs erróneos de `STATS` (`spAtk`, `spDef`, `speed`) producirían 404 silenciosos al activar EV para hallazgos. Mitigación: migrar a `StatEnum::slug()`.
- [medio] `EventoExploracion::aArray()` es lista blanca: si se añaden claves y no se actualiza el VO, cualquier futura escritura vía `aArrays()` las borra. Mitigación: extender el VO en el mismo cambio.
- [bajo] Ampliar `nombresPokemon()` a bitácoras de terminadas incrementa IDs en la misma única query `Pokemon::whereIn` (`:78-80`); sin consultas extra.
- [bajo] Cambiar `candy_src` a `iconos_webp` rompería la semántica del nombre de clave. Preferible añadir/consumir `icono_pokemon` y dejar `candy_src` como está.
- [bajo] Ocultar eventos reduce `diario.total`; tests que cuentan eventos podrían fallar si sus fixtures incluyen tipos irresolubles (hoy no los hay).
- [bajo] Emboscada con varios sub-combates: un único `hp_inicial` puede inducir a error (¿primer sub-combate o el que perdió?). Aclarar semántica.
- [bajo] `hp_inicial` debe capturarse **después** del descanso previo (ya ocurre en `combatirEvento` `:296-303`); si se captura antes, el dato sería incorrecto.

**Dudas abiertas**

- ¿`resultado['caramelos_familia']` incluye siempre `pokemon_id`? En el contrato es opcional (`PresentadorExploraciones.php:237`; fixture sin él en `ExploracionesViewTest.php:34`). No verificado en datos reales.
- ¿Se desea el sprite de especie **solo** en el diario o también en el resumen de recompensas (`ResultadoExploracionVista::caramelosFamilia` `:168-183` y `_caramelo.blade.php`)? El síntoma menciona el diario; el resumen seguiría mostrando caramelo genérico.
- ¿El bloque resumen de derivados (`resultado.caramelos_familia`) muestra hoy «Caramelo de Pokémon» si falta `nombre`? No verificado en runtime con la exploración 24.
- ¿Se quiere enriquecer el evento raíz `derrota` con PV (requiere propagar el estado desde `procesarTick`) o basta con el evento de combate inmediatamente anterior? Propuesta: basta el combate + texto humano en la raíz.
- ¿`equipo` se consume en API/JSON externa? Grep no encuentra consumo en vistas; solo tests.

## 7. Tareas no completadas por el límite de pasos

- No se ejecutaron tests (además, la norma del proyecto difiere el testing al cierre del módulo).
- No se verificó en runtime el contenido de `resultado['caramelos_familia']`/`resultado['caramelos_tipo']` de la exploración 24 (solo las claves de `eventos` y la bitácora).
- No se revisó `TransformadorResultadoExploracion` para confirmar si el resumen de recompensas sufre el mismo problema de nombres.
- No se ejecutó `grep` sobre `.ai/rules` (el cambio es de solo lectura, no de implementación).

**Recomendación siguiente:** aprobar un plan que ataque en este orden: (1) `nombresPokemon()` + `ExploradorVista::nombre()`, (2) `tipo_id`/`oculto` + filtro en `DiarioExploracion`, (3) EV (`completarStats` + `StatEnum::slug()`), (4) VO `ResultadoBatallaExploracion` + `resolucionCombate` + presentación de PV. Antes de implementar, confirmar las dos dudas de contrato (sprite solo en diario; semántica de `hp_inicial` en emboscada).

**Confirmación:** no se modificó ningún archivo. Solo lecturas y `php artisan tinker` con `find`/`pluck`/`toView` en memoria (sin escrituras).
