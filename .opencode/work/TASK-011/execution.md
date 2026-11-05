# TASK-011 — Ejecución

## Preflight inicial (2026-11-04)

- **Proyecto:** `/home/david/pokemon`.
- **Rama actual:** `master`.
- **HEAD:** `cd57b27 feat(assets): shiny de 59 formas regionales desde _f_s`.
- **Upstream:** `origin/master`; la rama local está 1 commit por delante. No se hace `push`.
- **Staged:** vacío.
- **Cambios locales preexistentes (ajenos a esta tarea):**
  - `MVP.txt` modificado: contiene la especificación de este módulo (la misma que originó la petición). No se toca ni se commitea.
  - `.githooks/pre-commit` modificado sin cambios de contenido en el diff stat (`0` líneas). No se toca.
- **Decisión:** no se revierte, no se hace `checkout`, `reset` ni `clean`; el WIP ajeno queda fuera de alcance y no se sobrescribirá.

## Estado del árbol relevante para el análisis

- Existen los módulos `src/Battle`, `src/Exploraciones`, `src/Granjas`, `src/Equipos` y `src/CombateEntrenadores`/`src/CombateRuta`.
- Existe inventario unificado `player_inventory` (`app/Models/PlayerInventory.php`).
- Existe catálogo de objetos de combate con solo dos bayas registradas: `sitrus_berry` y `lum_berry` (`src/Battle/Domain/Enum/ClaveItem.php`, `src/Battle/Domain/Catalog/CatalogoObjetos.php`).
- Existen assets:
  - `public/images/berry/`: 65 `.webp` con nomenclatura inglesa.
  - `public/images/bonguri/`: 7 PNG (amarillo, azul, blanco, negro, rojo, rosa, verde).
- Hay marcas explícitas de trabajo pendiente: `TODO(futuro): bayas` en `app/Models/ExploracionActiva.php:11` y comentarios «sin bayas» en `app/Http/Controllers/ExploracionActivaController.php:254` y `routes/exploraciones.php:15`.

## Observación de configuración

- El entorno de sesión reporta el modelo `opencode/deepseek-v4.1-flash-free`, mientras `.ai/rules/model-policy.md` fija `opencode-go/deepseek-v4.1-flash`. No se cambia el modelo ni la configuración; se registra para que el desarrollador lo valide. Los subagentes se lanzan sin forzar modelo.

## Delegación

- **Fase actual:** `ANALYZING`.
- **Especialista:** `analyst`.
- **Alcance delegado:** investigar el estado actual de inventario, objetos de combate, exploraciones y granja; identificar huecos; proponer modelo y flujo; inventariar assets; formular preguntas y fases.
- **Prohibido en esta fase:** modificar archivos, crear migraciones, tocar vistas, ejecutar tests.

## Análisis completado (2026-11-04)

- **Especialista:** `analyst` (sesión `ses_e5a5e140cffesWhkMKu2BRtzsG`).
- **Resultado:** `.opencode/work/TASK-011/analysis.md` (972 líneas), copiado sin alterar sus conclusiones.
- **Cobertura:** inventario, catálogo de objetos, combate (turnos, objetivos, acciones, equipamiento, consumo), exploraciones, granja/forja, UI, assets, riesgos, fases, preguntas y criterios de aceptación.

### Hallazgos principales

- **Reutilizable:** `player_inventory` admite claves nuevas sin migración; `CatalogoObjetos`/`ClaveItem` centralizan objetos; `MultiplicadoresStats::aplicarTemporal()` ya implementa 5 turnos y el clamp 0.25–4.0; `Combatiente::revivir()` y `curarHpCantidad()` cubren revivir y curación; las recompensas de exploración ya se persisten con `firstOrCreate + increment`.
- **No existe:** acción de «usar objeto» en combate, selección de aliados como objetivo, fases Livewire de objeto, forja/recetas, botín de bayas/bonguris en exploración, restauración de PP, resistencia de tipo temporal y crítico como stat.
- **Deuda relevante:** equipar un objeto no valida propiedad ni descuenta inventario (`TeamController`, `ActualizarObjetosEquipo`, `EquiposVistaPresenter`); el objeto equipado tampoco se descuenta al consumirse.
- **Assets:** 35 de las 36 bayas de la especificación tienen `.webp`; falta `roseli-berry.webp` (Baya Hibis/Hada). Los 7 bonguris están en PNG. Faltan 39 iconos de pociones, recipiente, mejoras, reductores y resistencias.
- **Mapeo verificado:** `Zreza = cheri`, `Lagro = micle`, `Hibis = roseli`. La comprobación externa confirma el mapeo del analista; la sospecha inicial del orchestrator sobre `leppa`/`starf` era incorrecta.

### Verificación del orchestrator

- `analysis.md` revisado en cabecera y cola; se eliminó únicamente la línea de metadatos de la sesión del subagente.
- Comprobadas las afirmaciones clave sobre `MultiplicadoresStats`, `StatClave`, `CatalogoObjetos`, `ItemCatalogo`, `BatallaVistaPresenter` y `EstadoMovimientos`.
- No se ha modificado código, migraciones, vistas, configuración ni tests.
- Los cambios locales preexistentes (`.githooks/pre-commit`, `MVP.txt`) siguen intactos y fuera de alcance.

## Estado

`ANALYZING` completado. `DESIGNING` no iniciado: depende de las decisiones del desarrollador recogidas en la sección 11 de `analysis.md`. No se ha implementado nada.

## Decisiones del desarrollador (2026-11-04)

- D-01: solo 1 objeto equipado por Pokémon.
- D-02: usar un objeto consume la acción del Pokémon aliado.
- D-03: los objetos usados contra el enemigo solo aplican debuff.
- D-04: equipar descuenta inventario y exige propiedad.
- D-05: orden de fases: refactor de exploración para botín → forja/creación → uso en combate.
- Nuevo alcance: un bonguri por hábitat; modos de exploración (experiencia, reclutamiento, botín); bonus por explorar el hábitat propio; plantación de bayas en nidos con 2 huecos por nivel, 1 día de crecimiento y 3 cosechas acumulables; mayor probabilidad de bayas salvajes del tipo plantado; botín de bayas/bonguris en combates como fase posterior.

## Ampliación del análisis (A2) — completada (2026-11-04)

- **Especialista:** `analyst` (sesión `ses_e5a358ac3fferQ0KZ34iO5YGqo`).
- **Resultado:** anexo A2 integrado en `.opencode/work/TASK-011/analysis.md` (1686 líneas totales; A2 empieza en la línea 978).
- **Cobertura A2:** modos de exploración, bonguri por hábitat, bonus por Pokémon del hábitat, botín de bayas/bonguris, plantación/cosecha en nidos, UI, riesgos y fases.
- **Hallazgos clave:**
  - No existe modo de exploración; `SimuladorEncuentros` tiene pesos fijos.
  - `ResultadoRecompensas` no transporta objetos; `EventoExploracion` no tiene clave de item.
  - No hay bonguri asignado a hábitat; `ProvinceSeeder` ya insinúa un color por provincia.
  - No hay origen de hábitat en reclutados; el MVP puede usar especie nativa en `pokemon_habitat`.
  - No existe plantación/cosecha; se propone `nest_plantings` con stock calculado por tiempo.
  - No hay endpoints de plantar/cosechar; `Nido` no usa `BelongsToUser`, la autorización debe pasar por la granja.
- **Propuesta A2:** modo nullable (`experiencia`/`reclutamiento`/`botin`), botín calculado al finalizar, `habitats.bonguri` con backfill provincial, `nest_plantings` (2 huecos/nivel, 24 h, stock máx. 3), cosecha manual en MVP, bonus por especie nativa.
- **Verificación:** anexo A2 guardado sin alterar conclusiones; se revisó la coherencia con el análisis A1.
- **Estado:** A2 completado. `DESIGNING` puede iniciarse con las decisiones D-01..D-05 y los defaults propuestos en A2.

## Diseño completado (2026-11-04)

- **Especialista:** `architect` (sesión `ses_e5a2ec4beffesfv4oIMx4L9BjI`).
- **Resultado:** `.opencode/work/TASK-011/architecture.md` (1054 líneas), guardado sin las líneas de proceso del subagente.
- **Decisiones estructurales:**
  - Catálogo de items en `src/Shared/Domain/Items` (shared kernel).
  - Forja en `src/Forja` (módulo nuevo); plantación en `src/Granjas`; botín en `src/Exploraciones`; uso de objeto en `src/Battle`.
  - Botín calculado al finalizar, aditivo a `ResultadoRecompensas` con `items` por defecto `[]`.
  - `modo` nullable (`null` = comportamiento actual) y `habitats.bonguri` con backfill provincial.
  - `nest_plantings` con una fila por hueco y stock calculado; `UNIQUE (nest_id, slot_index)`.
  - `DTOAccionObjeto` separado y `ServicioUsoObjetos` puro; bump de `SESSION_VERSION` 20 → 21 en F5.
  - Puerto único `InventarioJugador`; `ConsultorPlantaciones` para el botín.
- **Puntos marcados `A VERIFICAR`:** `MapaFactores`, `ProbabilidadCaptura::intentarConFactores`, expiración de `CondicionVolatil`/`CRITICO_MEJORADO` y `Combatiente::multiplicadorDanioRecibido`. No bloquean F1.
- **Verificación:** diseño coherente con A1/A2 y con las reglas `.ai/rules`; no se modificó código ni se ejecutaron tests.

## Plan v2 redactado (2026-11-04)

- **Alcance:** F1 — modos de exploración, bonguri por hábitat y botín de bayas/bonguris.
- **Cambio respecto a v1:** sin topes duros por expedición; el botín se regula con pesos/probabilidades centralizados. Se registra el mapping provincial confirmado (Galicia blanco, Euskadi amarillo, Barcelona negro, Extremadura verde, Nova Alacant rosa, Mutxamel azul, Andalucía rojo, Vacío con fallback).
- **Fuera de F1:** plantación/cosecha, bonus de hábitat, forja, uso en combate, equipables con coste, iconos y botín de combate.
- **Rama propuesta:** `task-011` desde `master`, sin `push`.
- **Estado:** `AWAITING_APPROVAL`; `approval.md` sigue `PENDIENTE`.
- **Siguiente paso:** aprobación explícita del plan v2. Sin ella no se delega implementación.

## Estado

`AWAITING_APPROVAL` — plan v2 pendiente de aprobación explícita. No se ha implementado nada.

## Aprobación y paso a implementación (2026-11-04)

- El desarrollador respondió **«Aprobar plan v2 (Recomendado)»** a la pregunta de aprobación.
- Registrado en `approval.md`: estado `APROBADO`, versión `v2`, alcance F1.
- Se pasa a `IMPLEMENTING`.
- Preflight previo a la implementación: se verifica rama, estado y diff; se crea la rama `task-011` desde `master` si no existe. Los cambios locales `.githooks/pre-commit` y `MVP.txt` siguen fuera de alcance.

## Estado

`IMPLEMENTING` — F1 autorizada. No se ha modificado código todavía.

## Respuesta del desarrollador al plan v1 (2026-11-04)

Texto literal:

> //Bonguri Blanco
> 'Galicia',
> //Bonguri Amarillo
> 'Euskadi',
> //Bonguri Negro
> 'Barcelona',
> //Bonguri Verde
> 'Extremadura',
> //Bonguri Rosa
> 'Nova Alacant',
> //Bonguri Azul
> 'Mutxamel',
> //Bonguri Rojo
> 'Andalucia',
> , No hay limite de expedicion, lo vamos regulando

- Confirma el mapping provincial de bonguris.
- Indica que **no debe haber tope duro por expedición**; el botín se regulará con pesos/probabilidades.
- **No es una aprobación explícita del plan v1**: no menciona «Aprobar plan v1» ni una versión. Se interpreta como cambio de alcance.
- Se genera **plan v2** y se vuelve a solicitar aprobación explícita. `approval.md` sigue `PENDIENTE`.

## Implementación F1 (2026-11-04)

- **Rama de trabajo:** `task-011`, creada desde `master` (`cd57b27`). Sin `push`.
- **F1.1 — Migraciones (`database`):** creadas y ejecutadas en local.
  - `2026_11_04_000001_add_modo_to_exploraciones_activas.php`: `modo varchar(20) NULL`, nullable, sin índice.
  - `2026_11_04_000002_add_bonguri_to_habitats.php`: `bonguri varchar(16) NULL` + backfill provincial.
  - `migrate:status`: ambas `Ran` en el lote [20].
  - Backfill verificado: blanco 8, amarillo 6, negro 4, verde 7, rosa 8, azul 13, rojo 9, NULL 1 (Vacio), total 56. Sin valores fuera del enum.
- **F1.2 — Catálogo (`backend`):** `src/Shared/Domain/Items/` con `CategoriaItem`, `ClaveBaya` (36), `ClaveBonguri` (7), `DefinicionItem`, `DefinicionItemCollection`, `CatalogoItems` y `ItemDesconocido`.
- **F1.3 — Dominio de exploración (`backend`):** `ModoExploracion`, `PesosEvento`, `PerfilExploracion`, `ContextoBotin`, `CalculadorBotinExploracion`, `RecompensaItem`, `RecompensaItemCollection`; `ResultadoRecompensas` ampliado con `items` y `sumarBotin`; `SimuladorEncuentros` parametrizado por perfil.
- **F1.4 — Aplicación (`backend`):**
  - `FinalizarExploracionHandler`: perfil por modo, multiplicadores de EXP/caramelos, `factorCaptura`, botín antes de pérdidas, pérdidas de `baya`/`bonguri` y preservación de `items`.
  - `PersistirRecompensas`: `guardarItems()` incrementa `player_inventory`.
  - `ProcesarExploracionHandler`: pasa el perfil a eventos normales y extra.
  - `ExploracionActivaController`: valida/persiste/expone `modo`; modelos con `modo`/`bonguri` en `$fillable`.
  - `ItemCatalogo`: resuelve `baya:*` y `bonguri:*`.
- **F1.5 — Presentación (`backend`):** `TransformadorResultadoExploracion` persiste `items`; `PresentadorExploraciones` resuelve nombre/icono/categoría y pérdidas; `ResultadoExploracionVista` normaliza `items`, `tiene_items` y `tiene_obtenidas`.
- **F1.7 — Vistas (`frontend`):**
  - `habitats/show.blade.php`: selector accesible de modo (Pelear/Buscar/Explorar), estado `modoExploracion: 'experiencia'`, envío en preview y store, badge de modo.
  - `exploraciones/index.blade.php`: sección “Objetos encontrados” con icono, nombre y cantidad.
  - `npm run build` regeneró CSS (`sm:flex-col`); `view:cache`/`view:clear` correctos.
- **F1.8 — Migraciones aplicadas** en base local de desarrollo (pgsql, `laravel`).

## Verificación estática (2026-11-04)

- `php -l`: sin errores en todos los PHP nuevos/modificados (incluidas las dos vistas Blade, que compilan como PHP).
- `php artisan view:cache` y `view:clear`: correctos.
- `php artisan route:list --path=exploraciones`: 7 rutas esperadas.
- `phpstan` (nivel 6, config del proyecto): 34 errores preexistentes; ninguno en archivos nuevos ni en las líneas modificadas. El único error en el módulo de exploración es `CombateExploracion.php:57` (archivo no tocado).
- `deptrac`: 113 violaciones preexistentes. Las nuevas clases (`Shared\Domain\Items`, `ModoExploracion`, `CalculadorBotinExploracion`, etc.) no aparecen en la salida. `FinalizarExploracionHandler` ya dependía de `Presentation` en `master` (violación preexistente).
- `git diff --check`: solo avisos de espacios finales en `MVP.txt` (cambio local ajeno, fuera de alcance).
- Smoke test con `php artisan tinker` (sin escrituras):
  - `ModoExploracion::desde(null)` → `mixto`.
  - Pesos de `botin` → 20/45/10/15/10.
  - Catálogo → 43 definiciones (36 bayas + 7 bonguris).
  - `baya:oran` → Aranja + `/images/berry/oran-berry.webp`; `bonguri:azul` → Azul + PNG.
  - `CalculadorBotinExploracion` determinista: con 0.0 devuelve 1 baya + 1 bonguri; con 0.99 devuelve 3 bayas y ningún bonguri.
- No se ejecutaron tests.

## Estado

`REVIEWING` — F1 implementada y verificada estáticamente. Pendiente revisión independiente y auditoría de seguridad.

## Correcciones post-revisión (2026-11-04)

### C1 — Backfill provincial en instalaciones nuevas (`database`)

- **Hallazgo:** la migración `add_bonguri_to_habitats` no puede rellenar en una BD limpia porque `provinces`/`habitats` están vacías cuando se ejecuta; los seeders van después.
- **Corrección:** nuevo `database/seeders/BonguriHabitatSeeder.php`, idempotente, que resuelve provincias por nombre y actualiza `habitats.bonguri` por `province_id`. Registrado en `CatalogoSeeder` después de `HabitatSeeder`. “Vacio” queda NULL.
- **Verificación:** ejecutado dos veces sobre la BD local sin cambios (checksum idéntico; conteos 8/6/4/7/8/13/9/1). Esquema PostgreSQL temporal limpio (`task011_fresh`): migraciones + seeders, 56 hábitats, 55 con color, “Vacio” NULL; esquema eliminado después. No se usó `migrate:fresh` sobre la BD principal.

### C2 — `multiplicadorBotin` y farmeo instantáneo (`backend`)

- **Hallazgo 1:** `PerfilExploracion::$multiplicadorBotin` no se usaba.
- **Hallazgo 2:** `POST /exploraciones/{id}/recoger` con `forzarRegreso: true` permitía obtener botín sin tiempo transcurrido.
- **Corrección:**
  - `ContextoBotin` añade `multiplicadorBotin` y `factorTiempo` (default 1.0).
  - `CalculadorBotinExploracion` aplica `factorTiempo` a las tiradas de bayas y a la probabilidad de bonguri; `multiplicadorBotin` escala la probabilidad de bonguri con tope 100.
  - `FinalizarExploracionHandler::factorTiempo()` usa como base `inicioVuelta` (fin − duración/4), no la duración total; indefinidas 60 min.
  - Comentarios obsoletos de “sin bayas / TODO bayas” corregidos en controlador, ruta y modelo.
- **Verificación:** `php -l`, `pint`, `phpstan` sin errores nuevos; smoke tests: factor 1.0/mult 1.0/RNG 0.0 → 1 baya + 1 bonguri; factor 0.0 → 0; factor 0.5/mult 1.5/RNG 0.6 → 0; factor 1.0/mult 1.5/RNG 0.7 → 3 bayas + bonguri. Fórmula: 1 h/45 min → 1.0; 1 h/22-23 min → ~0.49-0.51; 72 h/54 h → 1.0; indefinida/30 min → 0.5; 1 h/1 min → ~0.022.

### C3 — Mínimo de `return_time` (`backend`)

- **Hallazgo de seguridad:** un `return_time` a ~1 min hacía que `factorTiempo` llegara a 1.0 tras ~60 s, permitiendo ciclos de farmeo.
- **Corrección:** `preview()` devuelve 422 y `validarYNuevaExploracion()` rechaza la creación si `return_time` está a menos de 60 minutos, calculado con `CalculadorFinExploracion::calcular(...)` (resuelve el cambio de día).
- **Verificación:** 1 min → 422; 2 h → OK; 40 min → 422; `php -l`, `pint` y `phpstan` correctos.

## Revisión y seguridad finales

- `reviewer`: **APPROVED**. Hallazgos altos resueltos; quedan limitaciones menores documentadas (preview sin multiplicadores de modo, doble escalado del bonguri, seeder que sobrescribe, setup sin seeders).
- `security`: **APPROVED**. Farmeo instantáneo y bypass por `return_time` cerrados; sin hallazgos críticos ni altos. Residuales: idempotencia dependiente del lock de caché, sin rate limit específico, avisos de dependencias preexistentes.

## Verificación final (2026-11-04)

- `php -l` de todos los PHP nuevos/modificados: correcto.
- `phpstan` nivel 6: 34 errores preexistentes; ninguno en archivos de F1.
- `deptrac`: 113 violaciones preexistentes; ninguna en clases nuevas.
- `php artisan view:cache` / `view:clear`: correctos.
- `php artisan route:list --path=exploraciones`: 7 rutas.
- `php artisan migrate:status`: ambas migraciones `Ran` [20].
- `git diff --check -- . ':!MVP.txt'`: limpio.
- `git status -- tests/`: vacío.
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`: sin cambios.
- Smoke tests de botín y de `factorTiempo`: correctos.
- No se ejecutaron tests.

## Estado

`COMPLETED` — F1 implementada, revisada, auditada y documentada. Las fases F2–F7 no están autorizadas y requieren plan y aprobación propias.
