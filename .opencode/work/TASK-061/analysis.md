# TASK-061 — Análisis

> Resultado de `analyst` (sesión `ses_e10b52cbeffe5G00TgnBGbl21l`). Se reproduce sin alterar
> sus conclusiones. El propio analista marca lo que quedó sin verificar por límite de pasos.

## Resumen

Proyecto Laravel 12.67 / PHP 8.4 en `/home/david/pokemon`, rama `master`, último commit
`fbe370b`. Estado git: solo `M .githooks/pre-commit` (preexistente, intacto) y
`?? .opencode/work/TASK-061/` (artefactos de coordinación). No se modificó nada.

Hallazgos principales:

- La Pokédex se sirve desde `PlayerController::pokedex()` con
  `DatagridService::list('pokemon', $params)`; el registro `pokemon` del datagrid está en
  `app/Providers/DatagridServiceProvider.php` y **ya soporta filtros por relación**
  (`types`, `effort`) mediante `RelationFilter`, que internamente usa `whereHas`. Por
  tanto, filtrar por habilidad y movimiento es viable sin tocar el servicio.
- **La causa de los `effect` vacíos está confirmada**: `AbilitySeeder` carga primero el CSV
  (59 filas, todas con texto), después recorre `abilities_lote1/2/3.json` (201
  definiciones) y hace `updateOrCreate` con
  `'effect' => (string)($definicion['effect'] ?? '')`; los 201 JSON tienen `"effect": ""`,
  así que machacan el texto del CSV. Los 27 restantes sin texto nunca estuvieron en el CSV.
- BD real: **287 habilidades**, 228 sin texto, 59 con texto. 278 asignadas a algún Pokémon;
  las 9 no asignadas son exactamente las 9 filas compuestas del CSV con nombres tipo
  `Enjambre/Mar Llamas/Espesura`. De las 228 vacías: 201 vienen de los JSON y 27 son
  placeholders generados desde `poke habilidades.csv`.
- Existe comportamiento real para **todas** las claves de las 228: 227 claves están
  registradas en `app/Providers/BattleEffectServiceProvider.php` y `ensañamiento` también
  (el regex inicial no capturó la `ñ`; verificado por grep en línea 441). 28 claves se
  registran como `EfectoMarcador` y su lógica se consulta desde el flujo de combate
  mediante `tieneEfecto(...)`.
- Punto importante de arquitectura: el registro real de efectos **no está en
  `FabricaEfectos`** sino en `BattleEffectServiceProvider::registrarHabilidades()`
  (líneas 139-487); `FabricaEfectos` solo es el contenedor.

## Requisitos

- Funcionales:
  - (A) Rellenar `abilities.effect` para las 228 habilidades sin texto, con descripciones
    fieles al comportamiento implementado (multiplicadores, condiciones, límites),
    incluidas las personalizadas.
  - (B) Añadir en `/pokedex` dos filtros de selección única e independientes (habilidad y
    movimiento), combinables entre sí y con vistos/no vistos/atrapados, tipo, esfuerzo y
    búsqueda.
- No funcionales:
  - Idempotencia del seeder; no romper el orden actual ni el estado local.
  - Mantener el patrón de `Datagrid` (whitelist, anti-inyección, `whereHas`).
  - UI accesible y coherente con los dropdowns existentes (`aria-haspopup`,
    `aria-expanded`, botón ✕ para limpiar).
  - Rendimiento aceptable con 287 habilidades / movimientos y SQL sobre PostgreSQL.
- Criterios de aceptación (propuestos):
  - El modal de detalle muestra texto para toda habilidad listada en cualquier Pokémon (no
    queda `effect` vacío en habilidades asignadas).
  - `GET /datagrid/pokemon?filter[ability]=X&filter[move]=Y` devuelve solo especies que
    cumplen ambos; combinado con `filter[visto]`, `filter[types]`, `filter[effort]` y
    `search`.
  - Los filtros no rompen `meta.counts` (los contadores globales no deben cambiar de
    semántica con los filtros nuevos).
  - Re-ejecutar `php artisan db:seed --class=AbilitySeeder` deja la BD idéntica
    (idempotente).
- Ambigüedades:
  - «Fiel al efecto real»: ¿describir literalmente la implementación (aunque difiera del
    oficial) o describir el oficial? El enunciado pide fiel a lo implementado; hay
    divergencias notables (p. ej. `demoledor`, `crueldad`, `escamas`, `antidoto`,
    `guardian_mares`).
  - Filtro por movimiento: ¿solo movimientos aprendidos por nivel (`source = level-up`, que
    es lo que pinta el modal) o todo el learnset (`pokemon_moves` completo)? El modal solo
    muestra level-up, pero la BD guarda más fuentes.
  - Filtro por habilidad: ¿incluir habilidades ocultas (`source`) o cualquier
    `ability_pokemon`?
  - ¿Se aceptan valores por nombre/slug o solo por id? Los filtros actuales aceptan
    etiqueta o id (`labelToId`).

## Alcance afectado

- Módulos:
  - Datagrid (`app/Datagrid`, `app/Providers/DatagridServiceProvider.php`).
  - Pokédex (controlador, vista, endpoint JSON `/datagrid/{model}`).
  - Catálogo de habilidades (`database/seeders/AbilitySeeder.php`, `storage/data/*`).
  - Dominio de combate (solo lectura como fuente de verdad para descripciones:
    `src/Battle/Domain/Effects/*`, `app/Providers/BattleEffectServiceProvider.php`).
- Archivos previsibles a modificar (propuesta, no ejecutada):
  - `app/Providers/DatagridServiceProvider.php` (añadir dos `RelationFilter`).
  - `app/Http/Controllers/PlayerController.php` (pasar catálogos de habilidades/movimientos
    a la vista; opcionalmente leer `filter[ability]`/`filter[move]` del request).
  - `resources/views/pokedex/index.blade.php` (estado Alpine, dos dropdowns, `buildParams`,
    limpiar, `emptyMessage`).
  - `database/seeders/AbilitySeeder.php` (no sobrescribir `effect` con vacío) y/o los 3
    JSON de `storage/data/`.
  - Posible nuevo endpoint de catálogo (si se opta por AJAX) o consulta previa en el
    controlador.
- Tablas/contratos:
  - `abilities` (`id`, `name`, `effect`, `data->effect_keys`), `ability_pokemon`
    (`ability_id`, `pokemon_id`, `slot`, `source`).
  - `moves` y pivote `pokemon_moves` (`slug`, `name`, `type_id`, `category`, `base_power`,
    `accuracy`, `level`, `source`).
  - Contrato JSON del datagrid: `data[]`, `meta{total,page,per_page,last_page,counts}`.
- Vistas/endpoints:
  - `GET /pokedex` (Blade + seed JSON embebido).
  - `GET /datagrid/pokemon` (paginado; `per_page` máximo 200 en el servicio; la vista pide
    120).
  - `GET /datagrid/pokemon/{id}/detalle` (modal).

## Evidencia

### Inventario de datos

- `database/seeders/AbilitySeeder.php:21-35` — lee `poke skills - habilidades.csv` y
  construye `nombres[normalizado] = ['name','effect']`.
- `database/seeders/AbilitySeeder.php:37-44` — placeholders `effect => ''` para habilidades
  de `poke habilidades.csv` ausentes del CSV de definiciones.
- `database/seeders/AbilitySeeder.php:46-59` — `Ability::updateOrCreate(['name'], ['effect'
  => $datos['effect'], 'data' => ['effect_keys' => $claves]])`.
- `database/seeders/AbilitySeeder.php:61-84` — **bucle de los 3 lotes JSON** y segundo
  `updateOrCreate` con `'effect' => (string)($definicion['effect'] ?? '')`. Aquí se produce
  el vaciado.
- `storage/data/abilities_lote1.json` — 14 definiciones, todas `"effect": ""`.
- `storage/data/abilities_lote2.json` — 99 definiciones, todas `"effect": ""`.
- `storage/data/abilities_lote3.json` — 88 definiciones, todas `"effect": ""`. Total 201.
- `storage/data/poke skills - habilidades.csv` — 59 filas (`habilidad,efecto`), **las 59 con
  texto**.
- `storage/data/poke habilidades.csv` — 288 filas (`id,name,habilidades,movimientos`).
- Consulta BD (solo lectura, `php artisan tinker`): `abilities` total 287 · `effect` vacío
  228 · con texto 59 · habilidades asignadas a algún Pokémon 278 · asignadas con texto 50 ·
  asignadas vacías 228.
- Las 9 no asignadas coinciden con los nombres compuestos del CSV: `(Vacio)`, `Demoledor /
  Mandíbula Hidráulica`, `Depredador / Depredador Prehistorico / Malvado`,
  `Enjambre/Mar Llamas/Espesura`, `Espinas/piel seca`, `Flexibilidad/ Depredador Marino`,
  `Protector / Campo Magnetico`, `Regeneracion/Sintesis/Bendicion`, `Veleta /
  Actualizacion software`. Por eso 59 − 9 = 50 con texto asignadas.
- Desglose de las 228 vacías: 201 cubiertas por nombres de los JSON + 27 que solo vienen de
  placeholders de asignaciones (`Espesura, Mar Llamas, Torrente, Enjambre, Demoledor,
  Espinas, Protector, Sequia, Llovizna, Sintesis, Regeneracion, Granizo, Caparazon,
  Flexibilidad, Ira Gyarados, Depredador Prehistórico, Espsura, Mandíbula Hidráulica,
  Predicion, Tormenta Arena, Veleta, Malvado, Depredador Marino, Bendicion, Depredador,
  Campo Magnetico, Actualizacion software`).
- Todas las 228 tienen `data.effect_keys` con **una sola clave**; las 227 claves distintas
  están registradas en el provider (incluida `ensañamiento`, verificada en
  `app/Providers/BattleEffectServiceProvider.php:441`).

### Motor de efectos

- `src/Battle/Domain/Effects/FabricaEfectos.php:31-35` y `52-66` — contenedor de registro;
  una clave puede tener varios efectos (composición).
- `app/Providers/BattleEffectServiceProvider.php:100-131` — singleton de `FabricaEfectos` y
  registro real.
- `app/Providers/BattleEffectServiceProvider.php:139-487` — `registrarHabilidades()`: 280
  claves string + 8 `ClaveEfecto`, 316 llamadas a `registrarEfecto`.
- `src/Battle/Domain/Catalog/CatalogoHabilidades.php:17-100` — mapa nombre normalizado →
  clave; `clavesDe()` (líneas 124-136) expande grupos con `/`; `existe()` (142-145).
- `app/Models/Ability.php:10-14, 27-29` — `data` cast a array; `effect_keys` es el contrato
  hacia el motor.
- `src/Battle/Domain/Effects/EfectoMarcador.php:1-20` — marcador sin hooks; la lógica se
  consulta con `tieneEfecto()`.
- 28 claves se registran **solo** como `EfectoMarcador`: `allanamiento, cadena_toxica,
  coraza_reflejo, corrosion, dicha, dinamo, energia_eolica, ensañamiento, guardia_metalica,
  humedad, impasible, indefenso, letargo_perenne, libero, lodo_liquido, madrugar,
  metal_liviano, metal_pesado, mimetismo, mutatipo, ojo_mental, oportunista, polvo_escudo,
  remoto, reves, rompeaura, sincronia, titere_toxico`.
- Ejemplos de que el marcador sí tiene comportamiento:
  `src/Battle/Domain/Chain/ManejadorCritico.php:35-41` (`ensañamiento` ⇒ crítico
  garantizado contra envenenado, aplica `punto_ira`), `:31-33` (`francotirador` ⇒ bonus
  crítico 2.25), `src/Battle/Domain/Combatiente.php:554` (`metal_pesado` ×2 /
  `metal_liviano` ×0.5 al peso), `src/Battle/Domain/Ejecucion/ResolutorPrecision.php:37-39`
  (`indefenso`, `ojo_mental`).
- `src/Battle/Domain/Componentes/Combatiente/EfectosCombatiente.php:80` — `meditacion` ⇒ ×2
  multiplicador de crítico.

### Efectos personalizados (respecto al oficial)

Lectura de registros, con multiplicadores:

- `demoledor` — `BattleEffectServiceProvider.php:162` →
  `EfectoModificadorDanioEscudo(1.25)`: +25 % de daño a escudos. **No** ignora habilidades
  (Mold Breaker oficial). Personalizado.
- `vacio` — `:240` → `EfectoDobleStab`: aplica STAB una segunda vez. Personalizado
  (coincide con el texto del CSV).
- `guardian_mares` — `:241` → `EfectoStabTipo(AGUA)`: STAB de Agua. Personalizado.
- `crueldad` — `:158` → `+30 %` de daño infligido si el objetivo tiene estado. Oficial:
  crítico garantizado contra envenenado. Personalizado.
- `escamas` — `:160` → daño **recibido ×0.875** solo a vida completa (oficial Multiscale =
  0.5). Personalizado.
- `sombra_traviesa` — `:161` → `EfectoPerforacionArmadura(0.15)`: ignora 15 % de escudo.
  Personalizado (oficial Infiltrator).
- `canones` — `:243-246` → +25 % a movimientos con flag puñetazo/pulso/bala y +25 % contra
  escudos. Oficial Mega Launcher: ×1.5 solo pulso. Personalizado.
- `prediccion` — `:212-213` → inmune a crítico + multiplicador de crítico ×2. Personalizado
  (oficial Super Luck: +1 etapa).
- `antidoto` — `:239` → `EfectoCuracionFinTurno(0.125)`: cura 12,5 % por turno; **no**
  inmunidad a veneno. Personalizado.
- `ira` / `ira_gyarados` — `:233-234` → `EfectoIra(0.0625)`; el CSV indica +1/16 a Ataque y
  Ataque Especial por golpe (1/8 si es múltiple).
- `espinas`, `piel_tosca`, `punta_acero` — `:235, 269, 429` → `EfectoEspinas(0.125)`: 12,5 %
  del daño físico de contacto devuelto.
- `veleta` / `actualizacion_software` — `:229-230` → `EfectoVeleta(1.25)`: +1 etapa (25 %)
  aleatoria al inicio de turno.
- `hipnotizador` — `:231-232` → `EfectoHipnotizador(0.125)`: 1/8 de dormir 1 turno al ser
  objetivo.
- `enredaderas` — `:205` → `EfectoEnredaderas(0.20, 0.0625)`: 20 % de bajar Velocidad 1/16.
- `doble_ataque` / `triple_ataque` / `cuadruple_ataque` — `:216-218` → 2×50 %, 3×33 %,
  4×25 %.
- `pantallista` — `:219` → `EfectoPantallista`: duplica pantallas (ver
  `src/Battle/Domain/Effects/Movimiento/EjecutorPantalla.php:44`).
- `refuerzos` — `:220` → `EfectoRefuerzos`: invoca clon al primer daño, +1 etapa
  Ata/AtEsp/Vel, pierde la habilidad.
- `dormilon` — `:221` → `EfectoDormilon`: duerme bajo 33 % de vida, cura 50 %, ronquido al
  recibir daño.
- `meditacion` — `:222` + `EfectosCombatiente.php:80` → duplica efectividad de estados y
  crítico ×2.
- `comealmas` — `:223` → `EfectoComealmas`: +15 % escudos al derrotar enemigo, +30 % si lo
  remata el propio Pokémon.
- `divide_dolor` — `:224` → `EfectoDivideDolor`: reparte daño/escudos entre físico y
  especial (`Combatiente.php:751, 786`).
- `flexibilidad` / `depredador_marino` — `:227-228` → `EfectoFlexibilidad(0.15)`: convierte
  15 % de Velocidad en Ataque y Ataque Especial.
- `superguarda` — `:359` → `EfectoSuperguarda`.
- `ultraimpulso` / `coranimas` / `impulso` — `:373-375` → mecanismos propios de subida de
  stats.
- `latido_oricalco` — `:467-468` → invoca Sequía **y** +33 % Ataque en sol.
- `motor_hadronico` — `:469-470` → terreno Eléctrico **y** +33 % Ataque Especial.
- `carga_cuark` — `:462` → +30 % Ataque; `paleosintesis` — `:461` → +30 % Ataque en sol
  (oficial: +30 % a la mejor stat).
- `*_debacle` — `:463-466` → `EfectoRuin(stat, -1)` (Caldero→AtEsp, Espada→Def, Tablilla→Ata,
  Abalorio→DefEsp).
- `unisono` — `:474` → `EfectoCostar`; `general_supremo` — `:473` →
  `EfectoSupremeOverlord`; `tumbos` — `:411` → `EfectoTangledFeet`.
- `capa_toxica` — `:475` → `EfectoToxicDebris`; `hospitalidad` — `:478` →
  `EfectoHospitality`; `nectar_dulce` — `:477` → −1 Evasión a rivales al entrar.
- `salpicante` — `:482` → quema 100 % al contacto (oficial Flame Body es 30 %).
- `piel_seca` — `:483-485` → inmunidad Agua +25 % curación, +25 % daño recibido de Fuego,
  6,25 % daño/turno en sol.
- `peluche` — `:342-343` → ×0.5 contra contacto, ×2.0 contra Fuego.
- `pompa` — `:347-349` → ×2.0 daño Agua infligido, ×0.5 Fuego recibido, inmune a quemadura.
- `cromolente` — `:416` → ×2.0 contra movimientos poco eficaces.
- `experto` — `:385` → ×1.5 con movimientos de potencia baja; `audaz` — `:419` → ×1.2 con
  recoil.
- `flaqueza` — `:420-421` → reduce Ataque/AtEsp según vida.
- `escudo_recio`/`espada_indomita` — `:450, 449` → +1 Def/Ata por entrada; `pelusa` — `:451`
  → −1 Velocidad a rivales.

**Importante:** 23 de las anteriores (`demoledor`, `vacio`, `guardian_mares`, `crueldad`,
`escamas`, `sombra_traviesa`, `canones`, `prediccion`, `antidoto`, `ira`, `espinas`,
`veleta`, `hipnotizador`, `enredaderas`, `doble/triple/cuadruple_ataque`, `pantallista`,
`refuerzos`, `dormilon`, `meditacion`, `comealmas`, `divide_dolor`) se implementan con
clases propias del proyecto y **son las que más se desvían del oficial**; son las
prioritarias para descripción.

## Dependencias

- Laravel 12.67, PHP 8.4, PostgreSQL (`config('database.default') = pgsql`).
- El datagrid depende de `DatagridRegistry` como singleton construido en
  `DatagridServiceProvider::buildRegistry()`; cualquier filtro nuevo debe añadirse ahí (no
  en el servicio).
- `AbilitySeeder` depende de `CatalogoHabilidades` y `NormalizadorNombre` para
  `effect_keys`.
- Las descripciones deben derivarse de `BattleEffectServiceProvider` + clases en
  `src/Battle/Domain/Effects/**`; hay acoplamiento conceptual entre datos (`effect`) y código
  (multiplicadores). No existe hoy una fuente única de texto.
- La vista depende del contrato JSON del datagrid (`data`, `meta.counts`) y del seed
  embebido vía `@json($pokemons)`.

## Duplicidades y deuda técnica

- Doble origen del `effect`: CSV + JSON, con el JSON vacío ganando siempre. Deuda principal
  de (A).
- 9 filas compuestas del CSV (`Enjambre/Mar Llamas/Espesura`, etc.) nunca se asignan porque
  los nombres con `/` no coinciden con los de `poke habilidades.csv`; quedan como
  habilidades huérfanas con texto.
- Alias duplicados de habilidad-equivalente: `Depredador`/`Depredador Prehistórico`/`Malvado`
  (misma clase), `Regeneracion`/`Sintesis`/`Bendicion`, `Protector`/`Campo Magnetico`,
  `Veleta`/`Actualizacion software`, `Flexibilidad`/`Depredador Marino`,
  `Hipnotizador`/`Hypnotizador` (dos claves para la misma clase), `Espinas`/`Piel
  Tosca`/`Punta Acero`, `robustez`/`sturdy`/`undying`, `rompemoldes`/`mold_breaker`,
  `gas_reactivo`/`neutralizing_gas`.
- Errata de datos: `Espsura` (habilidad con typo) mapeada a `espesura`; `Predicion` mapeada
  a `prediccion`.
- Posible inconsistencia semántica: `Respondón` → clave `inversion`, registrada como
  `EfectoInmuneEstado([])` (placeholder), cuando oficialmente es Contrary.
- `PresentadorDetalleEspecie::abilities()` devuelve `effect` como string vacío sin
  fallback; la vista lo oculta con `x-if="hab.effect"` (línea 472), por eso no se ve el
  problema salvo por ausencia de texto.
- `FilterServiceProvider` usa closures en la definición; no hay un tipo explícito para
  "filtro por pivote many-to-many" más allá de `RelationFilter`.

## Riesgos

- **[alto] Sobrescritura de datos**: si se editan los JSON pero se re-ejecuta el seeder con
  `effect` vacío en cualquiera de los 3, se vuelve a perder el texto. Mitigación: cambiar el
  seeder a `effect` no vacío o no sobrescribir; completar los JSON; verificar tras re-seed.
- **[alto] Descripciones no fieles**: copiar el texto oficial en habilidades personalizadas
  (`demoledor`, `crueldad`, `escamas`, `antidoto`, `guardian_mares`, `canones`, `prediccion`,
  `sombra_traviesa`) induce a error de build. Mitigación: redactar desde el provider y las
  clases de efecto, revisión cruzada con `tieneEfecto()`.
- **[medio] Filtro por movimiento ambiguo**: si se filtra sobre `pokemon_moves` completo,
  aparecerán especies que en el modal no muestran ese movimiento (que solo pinta `level-up`).
  Mitigación: decidir fuente y aplicarla tanto al filtro como a la UI; documentar en la
  etiqueta.
- **[medio] Rendimiento**: `whereHas` sobre `ability_pokemon`/`pokemon_moves` con 287
  habilidades y ~288 especies es barato, pero dos `whereHas` combinados generan subconsultas
  `EXISTS`; con `page/per_page=120` y `ORDER BY id` es aceptable. Falta verificar índices
  reales en los pivotes (no consultado).
- **[medio] `counts` y filtros**: `meta.counts` se calcula con `pokemonCounts()` global sobre
  `pokemon`/`pokedex`, **ignorando los filtros**. Al añadir filtros, los contadores de la
  cabecera seguirán siendo globales (probablemente lo deseado); si se pretende que reflejen
  el filtro, hay que cambiar el contrato.
- **[bajo] Compatibilidad de UI**: los dropdowns actuales cierran con un
  `document.addEventListener('click')` que comprueba `closest('[x-data]')`; añadir dos más
  requiere incluirlos en el mismo handler o se quedarán abiertos.
- **[bajo] Catálogo grande en HTML**: 287 habilidades y cientos de movimientos embebidos en
  Blade aumentarían el HTML; puede requerir endpoint AJAX o `<datalist>`/búsqueda.
- **[bajo] Idempotencia**: `updateOrCreate` por `name` es idempotente, pero si se renombra
  una habilidad quedan filas huérfanas.

## Opciones

1. **Datos**: completar `effect` en los 3 JSON (201) + añadir un mapa para las 27 restantes
   en el seeder o en un CSV auxiliar, y **endurecer el seeder** para no sobrescribir con
   cadena vacía (`if ($effect !== '')`). Re-ejecutar
   `php artisan db:seed --class=AbilitySeeder`.
2. **Datos (alternativa mínima)**: no tocar JSON; cambiar el seeder para que el bloque de
   lotes solo actualice `data` y no `effect`, y añadir los textos en un nuevo fichero
   `abilities_descripciones.json` aplicado al final. Menos fricción, pero dos fuentes de
   verdad.
3. **Filtros**: añadir dos `RelationFilter` en `DatagridServiceProvider` (`ability` →
   `abilities` por `id`/slug, `move` → `moves` por `id`/slug) y en la vista pasar los
   catálogos desde `PlayerController` (consulta `Ability::orderBy('name')` y movimientos
   distintos) embebidos con `@json`.
4. **Filtros (alternativa)**: endpoint AJAX `/datagrid/ability` y `/datagrid/move`
   registrados en el registry, con búsqueda y límite; la vista los carga al abrir el
   dropdown. Más escalable, más piezas.

## Suposiciones

- El filtro de movimiento debe operar sobre todo el learnset registrado en el pivote
  (`pokemon_moves`), no solo `level-up`; no confirmado con el usuario.
- El filtro de habilidad debe incluir cualquier `ability_pokemon` (incluidas ocultas); no
  confirmado con el usuario.
- Los `counts` deben seguir siendo globales (no filtrados), porque la cabecera dice
  «vistos/atrapados/total» del jugador.
- Los textos se redactan en español, coherentes con el resto de la UI.
- `effect` es texto libre sin límite relevante; la vista lo recorta con `line-clamp-3` y
  `title`.

## Preguntas bloqueantes (respuestas del desarrollador)

1. ¿El filtro de movimiento debe cubrir solo movimientos por nivel o todo el learnset?
   → **Todo el learnset**.
2. ¿El filtro de habilidad debe incluir habilidades ocultas? → **Todas**.
3. ¿Se acepta un cambio de comportamiento del seeder (no sobrescribir con vacío)? →
   **Sí; ficheros + seeder**.
4. Para las habilidades personalizadas: ¿descripción literal de la implementación o texto
   oficial con nota de divergencia? → **Fiel al efecto implementado** (texto literal del
   comportamiento real).

## Recomendación

- (A) Completar `effect` en los 201 JSON y cubrir las 27 restantes; endurecer
  `AbilitySeeder` para ignorar `effect` vacío. Las descripciones deben redactarse desde
  `BattleEffectServiceProvider.php:139-487` y las clases de `src/Battle/Domain/Effects/**`,
  priorizando las 23 personalizadas listadas arriba; el resto puede describirse con la
  semántica estándar ya reflejada en los CSV.
- (B) Implementar los filtros como dos `RelationFilter` nuevos en
  `DatagridServiceProvider.php` (`ability`, `move`), pasar los catálogos desde
  `PlayerController::pokedex()` y añadir dos dropdowns siguiendo el patrón exacto de
  tipo/esfuerzo (mismo `showX`, `selectX`, `clearX`, `buildParams`, `emptyMessage`, y cierre
  en el handler global). No tocar `DatagridService` ni `counts`.
- Antes de implementar: verificar índices de `ability_pokemon` y `pokemon_moves`
  (`php artisan db:table` o `\d`) — **no verificado en este análisis**.

## Archivos modificados

- Ninguno. Solo lecturas y consultas de solo lectura (`git status`, `git log`,
  `php artisan tinker` con SELECT, lectura de ficheros). Nota: se escribieron dos ficheros
  auxiliares fuera del proyecto (`/tmp/opencode/db_keys.txt`, `/tmp/registered_keys.txt`) para
  el cruce de claves; no afectan al repositorio.

## Verificación (solo lectura)

- `git status --short`: `M .githooks/pre-commit` y `?? .opencode/work/TASK-061/` (sin tocar).
- Recuentos BD vía `tinker` (SELECT): 287 / 228 / 59 / 278.
- Parseo de JSON y CSV con Python (solo lectura).
- Cruce claves provider ↔ `data.effect_keys` de las 228: 227 claves distintas, todas
  registradas (incluida `ensañamiento` con `ñ`).

## Problemas / no verificado

- **No verificado**: índices reales de `ability_pokemon` y `pokemon_moves`; migraciones de
  esas tablas (no leídas por límite de pasos).
- **No verificado**: estructura exacta de `app/Models/Move.php` y `Pokemon.php` (relaciones
  `moves`, `abilities`, `withPivot`), `MoveSeeder`/`PokemonMoveSeeder`; aunque el contrato se
  infiere de `PresentadorDetalleEspecie` (`slug`, `name`, `type_id`, `base_power`,
  `accuracy`, pivote `level`/`source`).
- **No verificado**: contenido completo de las vistas que consumen
  `PresentadorDetalleEspecie` (`para()` vs `paraPokedex()`); por el PHPDoc, `para()` se usa en
  el endpoint de hábitats y podría mostrar el learnset completo. No se localizaron otras
  vistas que lean `abilities.effect` fuera de `pokedex/index.blade.php`; queda pendiente
  `grep` en `resources/views/equipos/**` y `livewire/_pokemon-card.blade.php`.
- **No verificado**: el resto de la vista más allá de la línea 955 (código de `loadDetail`),
  irrelevante para el alcance.
- **No leído**: `.ai/rules/ddd-modules.md`, `frontend-minimal-logic.md`,
  `arrays-dto-collections.md` y `docs/ddd.md` (solo el índice).
- Las 59 descripciones existentes del CSV parecen coherentes con la implementación en los
  casos revisados (`Cañones`, `Crueldad`, `Escamas`, `Predicción`, `Sombra Traviesa`,
  `Veleta`, `Meditación`, `Demoledor`), pero no se auditó línea a línea las 59.
