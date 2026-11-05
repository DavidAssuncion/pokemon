# TASK-012 — Análisis

> Resultado del `analyst` para TASK-012. Verificado contra el código actual de `task-011` tras F1.

## 1) Resumen ejecutivo

F1 está implementada pero **sin commitear** en `task-011`: catálogo compartido de 36 bayas + 7 bonguris, modos de exploración, perfil de pesos, botín de items, persistencia en `player_inventory`, migraciones `modo`/`bonguri` y pérdidas por derrota. La base es sólida para F2–F5, pero hay cuatro brechas estructurales:

1. **No existe ningún puerto/servicio genérico de inventario.** `src/Shared/Domain/Ports` no existe; cada módulo consulta `PlayerInventory` por su cuenta. F2, F4 y F5 necesitan la misma semántica atómica y hoy no la tienen.
2. **El catálogo solo conoce bayas y bonguris.** `CategoriaItem` ya declara pociones/mejoras/reductores/resistencias, pero `CatalogoItems::construir()` solo genera 43 definiciones y `ItemCatalogo::resolve()` no resuelve esos prefijos.
3. **La granja no tiene plantaciones.** No hay tabla, modelo, DTO, endpoints ni UI; `GranjaDetalle`/`NidoGranja` solo exponen nidos/refugios/complementos y caramelos.
4. **El flujo de combate asume siempre un movimiento.** `pendingAction` es `?DTOAccionBatalla`, `resolverAccion()` hace `$pending->move->toDomain()` sin comprobar tipo, y la fase `player_target` solo permite seleccionar rivales vivos.

Además, el diseño de TASK-011 contiene una afirmación incorrecta: **subir `SESSION_VERSION` de 20 a 21 no descarta los payloads v20**, porque `cargar()` solo rechaza versiones inferiores a 9. Los v20 siguen cargando; si se añaden propiedades tipadas nuevas sin valores por defecto/restauración, el riesgo es de unserialize, no de versión.

## 2) Estado actual por fase

### F2 — Plantación y cosecha

**Lo que existe:**
- `GranjaDetalle` solo tiene `id`, `habitat`, `familias`, `inventario`: `src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php:14-19`.
- `NidoGranja` solo tiene `nivel`, `rangoIvs`, `factorTiempo`, `mejora`: `src/Granjas/Domain/DataTransferObjects/NidoGranja.php:14-18`; su `toArray()` no expone huecos ni plantaciones (`:28-39`).
- `ObtenerGranja` construye el detalle a partir de `$granja->nidos()->get()` y `ObtenerFamiliasDisponibles`: `src/Granjas/App/ObtenerGranja.php:47-85`; el nivel del nido es `0` si no hay fila (`:94`).
- La tabla `nests` existe con `level` por defecto 0 y unique `(farm_id, evolution_chain_id)`: `database/migrations/2026_10_26_000001_create_farm_tables.php`. **No existe `nest_plantings`.**
- `InventarioGranja` solo resume/consume `familia:*` y `ev:*`: `src/Granjas/App/InventarioGranja.php:28-34`, `:59-87`. Su `consumir()` privado sí usa `lockForUpdate` (`:99-116`), pero no hay método para `baya:*`.
- Endpoints actuales: GET de granja y cuatro POST de mejora: `routes/granjas.php:7-17`. **No hay endpoints de plantar/cosechar.**
- El controlador devuelve el `GranjaDetalle` completo tras cada acción: `src/Granjas/Infra/Controllers/GranjaController.php:55-60`.
- La UI tiene modal de granja, familias y botones de mejora: `resources/views/habitats/show.blade.php:1128-1290`; JS central `accionGranja()` en `:1701-1726`; mejoras en `:1745-1759`. **No hay selector de bayas, huecos ni cosecha.**

**Qué falta exactamente:**
- Migración `nest_plantings` con `nest_id` FK cascade, `slot_index`, `berry_key`, `planted_at`, `harvest_count`, unique `(nest_id, slot_index)` e índice por `berry_key`.
- Modelo `Plantacion` con relación a `Nido`/`Granja`; o consultas directas tipadas.
- Reglas `ReglasPlantacion`: huecos = `nivel * 2`, crecimiento 24 h, stock máximo 3, coste 1 baya.
- DTOs `PlantacionGranja`, `PlantacionesGranjaCollection`, campos `huecos_totales` y `plantaciones` en `NidoGranja`, y `bonguri`/`bayas` en `GranjaDetalle`.
- Ampliar `InventarioGranja` o crear el puerto `InventarioJugador` con `saldo/consumir/incrementar/consumirVarios`; el consumo de baya debe validar `ClaveBaya::tryFrom()` y ejecutarse dentro de `DB::transaction`.
- Casos de uso `PlantarBaya` y `CosecharPlantacion`; ambos deben resolver primero `AsegurarGranja::para($userId, $habitatId)` y luego buscar el nido por `farm_id` + `evolution_chain_id`, nunca confiar en un `nest_id` de URL.
- Endpoints `POST .../plantings` y `POST .../plantings/{slot}/harvest`, y UI de huecos.

**Nivel 0 y UNIQUE:**
- `ObtenerGranja` crea el DTO con nivel 0 para familias sin fila (`:94`); `MejorarNido` crea la fila con nivel 0 y la incrementa a 1 dentro de la misma transacción (`src/Granjas/App/MejorarNido.php:36-42`, `:58-59`). Por tanto, un nido persistido en nivel 0 no es un estado normal; aun así, plantar debe exigir `nido !== null && level >= 1` y `1 <= slot <= level*2`.
- El unique `(nest_id, slot_index)` **no existe hoy**; es nuevo de F2. Sin él, dos peticiones concurrentes pueden duplicar el hueco. `lockForUpdate` sobre el nido + unique + recálculo del stock cubren la carrera.
- `nests.level` es `unsignedTinyInteger`; con `MAX_NIDO = 5` (`src/Granjas/Domain/CalculadoraBonosGranja.php:21`) el máximo son 10 huecos, que cabe en `unsignedSmallInteger`.

**Consumo seguro de inventario:**
- El patrón ya existe en `MejorarNido`: `DB::transaction` + `Nido::whereKey(...)->lockForUpdate()` (`:29-44`) + `InventarioGranja::consumirFamilia` (`:58`).
- Para F2 hay que replicarlo, pero con `baya:{slug}` validado contra `ClaveBaya`; el `item_key` nunca debe llegar libre desde el cliente.

### F3 — Influencia de plantaciones y bonus de hábitat

**Cómo se aplica hoy `ResolverBonosFamilia`:**
- En exploración, `FinalizarExploracionHandler` inyecta/obtiene `ResolverBonosFamilia` y pide `refugiosDeHabitat($userId, $habitatId)`: `src/Exploraciones/App/FinalizarExploracionHandler.php:161-166`.
- Esos refugios alimentan la tirada de captura mediante `factorCaptura` en `rollAleatorio()`: `:520-555`, usando `ProbabilidadCaptura::intentarConFactores` (`:547-553`).
- `ProcesarExploracionHandler` también usa `ResolverBonosFamilia` para ponderar la aparición de Pokémon del hábitat: `:751-776`.

**Dónde se construye `ContextoBotin`:**
- En `FinalizarExploracionHandler::botin()`: `src/Exploraciones/App/FinalizarExploracionHandler.php:646-663`.
- Hoy pasa `delHabitat: false` siempre (`:658`) y usa el bonguri del hábitat con fallback a blanco (`:654-655`).
- `ContextoBotin` tiene `modo`, `bonguriHabitat`, `nivelHabitat`, `duracionHoras`, `delHabitat`, `huboDerrota`, `multiplicadorBotin`, `factorTiempo`: `src/Exploraciones/Domain/ContextoBotin.php:23-32`. **No tiene `factoresPlantacion` ni `categoria`.**
- `CalculadorBotinExploracion` ignora explícitamente `nivelHabitat`, `duracionHoras`, `delHabitat` y `huboDerrota` (`src/Exploraciones/Domain/CalculadorBotinExploracion.php:20-22`); solo usa modo, bonguri, `multiplicadorBotin` y `factorTiempo`.

**Cómo aplicar factores sin afectar bonguris ni otros hábitats:**
- Añadir a `ContextoBotin` un `MapaFactores $factoresPlantacion = MapaFactores::desdeArray([])` al final del constructor (aditivo, con default).
- En `CalculadorBotinExploracion::pesosBayas()`/`elegirBaya()`, multiplicar el peso de cada baya por `factoresPlantacion->obtener($baya->clave())` y renormalizar. El bonguri se calcula en `caeBonguri()` (`:211-223`) y no debe recibir ese factor.
- Los factores deben calcularse solo para `(userId, habitatId)` de la granja y agregando `berry_key` de las plantaciones; tope ×2,0 y sin afectar a otros hábitats. Esto encaja en un servicio de `Granjas\App` o en un puerto `ConsultorPlantaciones` implementado en `Granjas\Infra`.
- Respetar la dirección de dependencias: `Exploraciones\Domain` no debe importar `Granjas\Domain`; ya existe el precedente de `Exploraciones\App -> Granjas\App` (`FinalizarExploracionHandler.php:38`). Lo más limpio es que `Exploraciones\App` reciba el `MapaFactores` ya calculado.

**¿Cómo comprobar si un Pokémon es nativo del hábitat?**
- La tabla `pokemon_habitat` tiene unique `(pokemon_id, habitat_id)`: `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:15-21`.
- El explorador es un único `Reclutado` con `pokemon_id`: `app/Models/Reclutado.php:22-25`; la exploración guarda `reclutado_id` y `habitat_id`: `app/Models/ExploracionActiva.php:17-30`.
- Basta una consulta `pokemon_habitat` por `pokemon_id = $exploracion->reclutado->pokemon_id` y `habitat_id = $exploracion->habitat_id`. Debe hacerse en App (servicio `EsDelHabitat`), no en Domain.
- El diseño propone persistir el flag en `eventos['bonos']['del_habitat']`; hoy **no existe esa clave** en el flujo (no aparece en `ProcesarExploracionHandler` ni en `FinalizarExploracionHandler`). Habría que añadirla en el tick o calcularla al finalizar; para no repetir consulta, persistirla en `ProcesarExploracionHandler` es razonable.

**Auto-cosecha sin cron:**
- Es viable porque el stock se calcula al leer, no por job. La auto-cosecha sería una llamada adicional al servicio de cosecha desde `FinalizarExploracionHandler` (o desde el tick) para las plantaciones de `(userId, habitatId)`.
- Implicaciones: hay que hacerla dentro de la misma transacción del `CommandBus`, antes de persistir recompensas, y actualizar `harvest_count`; la idempotencia por `regreso !== null` (`:90-93`) ya protege el doble botín. Requiere decidir si solo aplica a `RECOLECTOR` y/o modo `BOTIN`; el rol existe en `src/Exploraciones/Domain/RolExploracion.php:20`.

**Discrepancia importante:** el diseño dice que `bonguriHabitat === null` desactiva el bonguri, pero F1 usa `ClaveBonguri::porDefecto()` para hábitats sin color (`FinalizarExploracionHandler.php:654-655`), de modo que "Vacío" también entrega bonguri blanco. F3 debe decidir si corrige esto o lo documenta.

### F4 — Forja

**Qué permite hoy `CatalogoItems`:**
- `CategoriaItem` ya tiene `POCION`, `MATERIAL`, `MEJORA`, `REDUCTOR`, `RESISTENCIA`: `src/Shared/Domain/Items/CategoriaItem.php:11-19`.
- Pero `CatalogoItems::construir()` solo recorre `ClaveBaya` y `ClaveBonguri` (`src/Shared/Domain/Items/CatalogoItems.php:131-164`); no existen `ClavePocion`, `ClaveMejora`, `ClaveReductor` ni `ClaveResistencia`.
- `CatalogoItems::aPartirDeItemKey()` solo entiende `baya:` y `bonguri:` (`:99-118`).
- `DefinicionItem` solo guarda clave, nombre, categoría, flags, icono y descripción; **no expresa efecto combativo** (`src/Shared/Domain/Items/DefinicionItem.php:12-21`).
- `ItemCatalogo::resolve()` solo resuelve `familia:`, `ev:`, `tipo:` y las claves de `CatalogoItems` (`app/Support/ItemCatalogo.php:85-117`). Cualquier `pocion:*`, `mejora:*`, etc. cae a "Desconocido".

**Qué falta:**
- Enums de claves: 8 pociones, 1 recipiente, 7 mejoras, 5 reductores, 18 resistencias (39 claves nuevas).
- Ampliar `CatalogoItems::construir()` y `aPartirDeItemKey()` para todos los prefijos.
- `CatalogoRecetas` + `RecetaFabricacion`; `MapaConteo` sí sirve como contrato de ingredientes (`src/Shared/Domain/ValueObjects/MapaConteo.php:33-87`).
- Módulo `src/Forja` (no existe; `find src/Forja` vacío, sin rutas ni vistas).
- Endpoints `GET /api/forja/recetas` y `POST /api/forja/fabricar`; UI modal/página.
- Servicio de inventario genérico; hoy no hay puerto compartido (`src/Shared/Domain/Ports` no existe) y `InventarioGranja` solo sirve para caramelos.
- Iconos: hay 65 `.webp` de bayas y 7 PNG de bonguris, pero **no hay directorio de pociones/mejoras/reductores/resistencias**. `CatalogoObjetos::iconoDe()` devuelve `''` (`src/Battle/Domain/Catalog/CatalogoObjetos.php:41-44`), por lo que el fallback de iconos es un gap real.

**Dónde ubicar Forja:** `src/Forja` es coherente con el diseño y con el autoload `Src\` → `src/` (`composer.json`). La forja no debe depender de `Battle`; solo del catálogo compartido y del puerto de inventario.

**Validación para no dejar saldos negativos:**
- `DB::transaction` + `lockForUpdate` de todas las filas de ingredientes ordenadas por clave; validar todos los saldos antes de descontar; usar `decrement` condicional o comprobar `cantidad >= requerido`; incrementar el resultado al final.
- El patrón transaccional ya está en `MejorarNido` (`src/Granjas/App/MejorarNido.php:29-60`) y el lock en `InventarioGranja::consumir` (`:99-116`).
- Sin `Idempotency-Key`, dos POST simultáneos pueden fabricar dos veces si ambos pasan la validación antes del lock; el lock ordenado evita saldos negativos, no duplicados de intención.

### F5 — Uso de objetos en combate

**Flujo actual de un turno:**
1. `nextActor()` (`app/Livewire/Combate.php:191-318`) carga la batalla, avanza ronda si procede, obtiene el actor y comprueba `puedeActuar()` (`:246-277`).
2. Si es jugador, expone movimientos y fija `phase = player_target` (`:299-307`); si es IA, prepara `pendingAction` y animación (`:783-820`).
3. `previewTarget()` (`:1001-1025`) valida que el objetivo esté vivo (`:1014`), guarda la selección, calcula `currentMoves` y pasa a `player_move`.
4. `selectMove()` (`:1027-1106`) valida el movimiento, crea `DTOAccionBatalla`, lo guarda como `pendingAction` y llama a `setAnimState()` (`:1094-1105`).
5. La animación Alpine llama a `commitAction()` (`:824-841`), que carga la batalla y ejecuta `resolverAccion()`.
6. `resolverAccion()` (`:843-997`) exige `pendingAction`, resuelve actor/objetivo, hace `$pending->move->toDomain()` (`:868`), calcula daño, consume acción y movimiento (`:973-974`), limpia el pendiente y llama a `nextActor()` (`:992-996`).

**Dónde insertar una acción de objeto:**
- Antes de `selectMove`, en fase `player_target`, con un método `seleccionarObjeto(string $clave)` que valide catálogo/inventario y pase a una fase nueva `player_item_target`.
- `previewTarget()` debe bifurcar: si la fase es de objeto, confirmar el objetivo del objeto en lugar de calcular movimientos. Hoy siempre fija `player_move` (`:1022-1023`).
- `resolverAccion()` debe comprobar `instanceof DTOAccionObjeto` antes de acceder a `->move`; si es objeto, llama al caso de uso, consume inventario y **solo** `turnManager()->consumeAction($actor)`; no debe llamar a `consumirMovimiento()`.
- El patrón de “consumir acción sin movimiento” ya existe en `pasarTurno()`: `$battle->turnManager()->consumeAction($actor)` (`:1228`).

**Representación y persistencia de la acción pendiente:**
- Hoy `AgregadoBatalla::$pendingAction` es `?DTOAccionBatalla` (`src/Battle/Domain/AgregadoBatalla.php:29`, getters/setters `:103-111`).
- `DTOAccionBatalla` exige `DTOMovimientoBatalla` (`src/Battle/Presentation/DTOAccionBatalla.php:15-24`), por lo que no sirve para un objeto sin movimiento ficticio.
- Opción recomendada: crear `DTOAccionObjeto` (Wireable) y ampliar la propiedad a `DTOAccionBatalla|DTOAccionObjeto|null`; `resolverAccion()` hace `instanceof`. Los payloads viejos con `DTOAccionBatalla` siguen siendo válidos para la unión.

**Métodos existentes reutilizables:**
- Curar: `Combatiente::curarHpCantidad()` (`src/Battle/Domain/Combatiente.php:1730-1741`).
- Revivir: `Combatiente::revivir()` (`:574-595`); resetea estado, condiciones, multiplicadores, recursos y turno.
- Estado: `setEstado()` (`:1304`), `quitarCondicion()` (`:730`), `agregarCondicion()`/`reemplazarCondicion()` (`:713-728`).
- Stats: `MultiplicadoresStats::aplicarTemporal()` (`src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:166-179`), duración 5 (`:25`) y `avanzarTemporales()` (`:189-204`).
- Usos: `EstadoMovimientos::reiniciar()` (`src/Battle/Domain/ValueObjects/EstadoMovimientos.php:85-89`), pero **no existe `Combatiente::restaurarUsos()`**; hay que añadirlo.
- Crítico: `ClaveCondicion::CRITICO_MEJORADO` (`src/Battle/Domain/Enums/ClaveCondicion.php:65`) y expiración en `AgregadoBatalla::procesarCondicionesVolatiles()` (`src/Battle/Domain/AgregadoBatalla.php:620-631`). Confirmado que se elimina al llegar a 0.
- Buff/debuff: `aplicarTemporal()` **multiplica** el factor existente (`:170`), por lo que reaplicar acumula ×1,3 ×1,3. El diseño pide “refrescar sin stack”; hay que añadir `refrescarTemporal()` o un flag, no existe hoy.

**Resistencia temporal de tipo con mínimo cambio:**
- `Combatiente::multiplicadorDanioRecibido()` delega en `effects()->multiplicadorDanioRecibido()` (`:1945-1948`), y `ManejadorHabilidadesDefensa` lo aplica al daño (`src/Battle/Domain/Chain/ManejadorHabilidadesDefensa.php:13-22`).
- `ManejadorMarcado` solo aplica condiciones `MARCADO`/`VULNERABLE` cuando el factor es `> 1.0` (`src/Battle/Domain/Chain/ManejadorMarcado.php:24-28`), así que **no sirve para una resistencia < 1**.
- Ya existe `EfectoEquipoResistenciaTipo` (`src/Battle/Domain/Effects/Equipo/EfectoEquipoResistenciaTipo.php:16-47`), pero es de equipo y dura por `onRoundEnd`. Una versión por combatiente que implemente `ModificaDanioRecibido` y se añada a `target->effects()->add(...)` aprovecha la cadena existente (`ColeccionEfectos::multiplicadorDanioRecibido()` `:164-174`), con la salvedad de que `ManejadorHabilidadesDefensa` se salta si el atacante ignora habilidades.
- Alternativa más limpia: nueva `ClaveCondicion::RESISTENCIA_TIPO` + campos `tipoResistido`/`factorResistencia` en `CondicionVolatil` y un manejador dedicado; así no depende de “ignora habilidad”. Si se añaden campos a `CondicionVolatil`, deben tener default para no romper sesiones antiguas (no tiene `__unserialize`).

**Bump de `SESSION_VERSION`:**
- `SESSION_VERSION = 20`, `MINIMUM_COMPATIBLE_SESSION_VERSION = 9` (`app/Support/BattleSessionService.php:47`, `:60`).
- `cargar()` solo descarta si `version < 9` (`:96-100`); por tanto, **v20 seguirá cargando tras subir a 21**. La afirmación del diseño de que v20 “se descarta limpiamente” es falsa.
- Si se añaden propiedades a `Combatiente`, hay que incluirlas en `__serialize()` (`:170-227`) y restaurarlas en `__unserialize()` con default (`:229-320+`). `AgregadoBatalla` no tiene serialización personalizada; una propiedad nueva con default `null` es segura para payloads viejos.
- Batallas afectadas: todas las que usan `BattleSessionService` y el componente `/combate` (ruta, entrenadores, gimnasios, mazmorras). La exploración no usa el componente Livewire; simula combates en servidor.

**Acceso al inventario desde el componente:**
- `/combate` está bajo `Route::middleware('auth')` (`routes/web.php:19-34`), así que `auth()->id()` está disponible en las acciones Livewire.
- `PlayerInventory` usa el global scope `BelongsToUser` (`app/Models/Concerns/BelongsToUser.php:23-30`), que filtra por `Auth::id()`.
- Aun así, el componente no tiene propiedad de usuario ni servicio de inventario; hay que inyectar un caso de uso `UsarObjetoEnCombate`/puerto y pasar el `userId` explícito.

## 3) Gaps y riesgos

### Gaps concretos
- **F2:** faltan migración, modelo, reglas, DTOs, endpoints, UI y consumo de `baya:*`.
- **F3:** `ContextoBotin` no tiene `factoresPlantacion`; `CalculadorBotinExploracion` no aplica factores; no existe `EsDelHabitat` ni persistencia de `eventos['bonos']`; `delHabitat` siempre es `false`; el bonguri de “Vacío” cae como blanco.
- **F4:** no hay enums de pociones/mejoras/reductores/resistencias, ni recetas, ni módulo `Forja`, ni endpoints, ni iconos; `ItemCatalogo` no resuelve las claves nuevas.
- **F5:** no hay DTO de objeto, fases de objeto, rama en `resolverAccion`, panel de items, selección de aliados ni `restaurarUsos()`; `previewTarget()` bloquea objetivos debilitados (revivir) y el presentador solo marca rivales.

### Riesgos
- **[alto] F5 toca el núcleo del turno.** `resolverAccion()` asume movimiento y es compartido por jugador/IA. Impacto: regresiones en ruta, entrenadores, gimnasios y mazmorras. Mitigación: DTO separado, `instanceof`, no tocar la cadena de daño, probar manualmente los cuatro contextos.
- **[alto] F4 puede introducir claves que el inventario acepta pero la UI/persistencia no resuelven.** Impacto: objetos “Desconocido” y pérdida de trazabilidad. Mitigación: ampliar `CatalogoItems` + `ItemCatalogo` + `DefinirItem` antes de habilitar la forja.
- **[alto] F2 sin unique ni lock puede duplicar plantaciones o cosechas.** Impacto: items infinitos. Mitigación: migración con unique `(nest_id, slot_index)`, `lockForUpdate` y recálculo de stock dentro de la transacción.
- **[alto] Consumo de inventario no atómico en F5.** El estado de batalla vive en sesión; si se descuenta y falla el guardado, se pierde el objeto. Mitigación: orden “validar → consumir → aplicar”, decremento condicional y single-flight de UI.
- **[medio] F3 con `delHabitat` mal definido.** “Nativo” puede referirse al explorador o al Pokémon capturado/derrotado; el diseño asume explorador. Impacto: bonus aplicado a quien no debe. Mitigación: fijar semántica y persistir el flag una vez.
- **[medio] F3 con factores de plantación.** Aplicarlos a bonguris o a otras granjas rompe el criterio. Mitigación: aplicar solo en `pesosBayas()` y filtrar por `(userId, habitatId)`.
- **[medio] `SESSION_VERSION` no invalida v20.** Si se espera invalidar, hay que subir también `MINIMUM_COMPATIBLE_SESSION_VERSION` o aceptar que las batallas viejas carguen con defaults. Mitigación: decisión explícita y pruebas de payload v20.
- **[medio] Buffs acumulables.** `aplicarTemporal()` multiplica; dos pociones de ataque dan ×1,69. Mitigación: `refrescarTemporal()` o guardar el factor base.
- **[medio] Sin puerto de inventario, F2/F4/F5 duplican `lockForUpdate` y validación.** Mitigación: crear `InventarioJugador` en `src/Shared/Domain/Ports` y una implementación única.
- **[bajo] Payload de granja grande tras cada acción.** Ya ocurre hoy (`GranjaController::respuesta()`); aceptado en MVP.

## 4) Puntos “A VERIFICAR” resueltos

1. **`MapaFactores`:** API real `desdeArray(array): self`, `obtener(string): float` (default 1.0), `toArray(): array`; **no tiene `con()`**. `src/Shared/Domain/ValueObjects/MapaFactores.php:23-39`.
2. **`ProbabilidadCaptura::intentarConFactores`:** firma `(int $captureRate, float $factorHabitat, float $factorRefugio, callable $aleatorio, float $factorSenda = 1.0): bool`. `src/Shared/Domain/ProbabilidadCaptura.php:91-104`. Ya se usa en `FinalizarExploracionHandler.php:547-553`.
3. **Expiración de `CRITICO_MEJORADO`:** está incluido en el bloque que decrementa y elimina al llegar a 0. `src/Battle/Domain/AgregadoBatalla.php:620-631`.
4. **`multiplicadorDanioRecibido`:** `Combatiente` delega en `effects()` (`:1945-1948`); la cadena lo aplica en `ManejadorHabilidadesDefensa` (`:13-22`). Las condiciones `MARCADO`/`VULNERABLE` se aplican aparte y solo si el factor es `> 1.0` (`ManejadorMarcado.php:24-28`). Por tanto, una resistencia `< 1` necesita un efecto/manejador nuevo; no basta con una condición existente.
5. **`SESSION_VERSION`:** 20 actual, mínimo compatible 9; v20 no se descarta al subir a 21. `app/Support/BattleSessionService.php:47`, `:60`, `:96-100`.
6. **`MapaConteo` para recetas:** dispone de `desdeArray`, `vacio`, `obtener`, `con`, `sin`, `claves`, `toArray`; sirve como contrato de ingredientes. `src/Shared/Domain/ValueObjects/MapaConteo.php:33-87`.
7. **Reloj:** no verificado; no puedo confirmar si existe `Src\Shared\Domain\Reloj`. Queda como pregunta abierta.
8. **Persistencia de items:** `PersistirRecompensas::guardarItems()` ya incrementa `player_inventory` con `firstOrCreate()->increment()` y tolera usuario nulo (`src/Exploraciones/App/PersistirRecompensas.php:86-103`).

## 5) Dependencias y orden recomendado

- **F1 → F2:** F2 necesita el catálogo `baya:*` y el inventario ya existente.
- **F2 → F3:** F3 necesita plantaciones para calcular factores. `EsDelHabitat` puede implementarse en paralelo, pero la aplicación de factores requiere F2.
- **F1 → F4:** F4 solo necesita el catálogo compartido; puede ir en paralelo con F2/F3 si se aíslan archivos (`src/Shared/Domain/Items`, `src/Forja`). Riesgo de solapamiento en `CatalogoItems` e `ItemCatalogo`.
- **F4 → F5:** F5 necesita objetos fabricados y el catálogo extendido.
- **Orden recomendado:** F2 → F3 → F4 → F5. Si se busca paralelismo: F4 en paralelo con F2, pero F5 siempre al final.
- **Archivos compartidos a secuenciar:** `src/Shared/Domain/Items/*` (F2/F4), `app/Support/ItemCatalogo.php` (F4), `src/Exploraciones/Domain/ContextoBotin.php` y `CalculadorBotinExploracion.php` (F3), `app/Livewire/Combate.php`, `AgregadoBatalla.php`, `DTOAccionBatalla.php`, `BatallaVistaPresenter.php` y partials (F5).
- **Dependencia de datos:** F2 requiere migración nueva; F3 no requiere migración si los factores se calculan desde `nest_plantings`; F4 no requiere migración; F5 no requiere migración, solo sesión y código.

## 6) Preguntas abiertas

1. **Bonus de hábitat:** ¿se aplica al explorador (`reclutado->pokemon_id`) o al Pokémon capturado/derrotado? El diseño asume explorador.
2. **Auto-cosecha:** ¿solo para `RECOLECTOR` y modo `BOTIN`, o para cualquier exploración? ¿Se ejecuta al finalizar o en cada tick?
3. **Stock 3:** ¿máximo acumulable (planta persistente) o 3 cosechas totales (la fila se borra)?
4. **Replantar:** ¿se permite perder el stock y sustituir la baya, o se bloquea hasta cosechar?
5. **Resistencia de tipo:** ¿reduce cualquier ataque de ese tipo o solo los supereficaces?
6. **Bonguri “Vacío”:** ¿debe entregar blanco (comportamiento F1) o ninguno?
7. **Iconos:** ¿se aportarán los 39 iconos nuevos o se reutilizan tipos/bayas con fallback?
8. **`SESSION_VERSION`:** ¿se fuerza la invalidación de v20 subiendo el mínimo, o se acepta que carguen con defaults?
9. **Reloj:** ¿existe `Src\Shared\Domain\Reloj`? No verificado.
10. **Recetas:** ¿la forja produce solo objetos de combate o también material intermedio (p. ej. recipiente)? ¿Cantidad de fabricación 1 en MVP?

## 7) Archivos inspeccionados

- Artefactos: `.opencode/work/TASK-011/architecture.md` (completo), `execution.md` (completo); `git status`, rama y log.
- Granjas: `src/Granjas/App/{ObtenerGranja,AccionesGranja,MejorarNido,InventarioGranja,AsegurarGranja,FamiliaEnHabitat,ResolverBonosFamilia}.php`; `src/Granjas/Domain/DataTransferObjects/{GranjaDetalle,NidoGranja,FamiliaGranja,InventarioCaramelosGranja}.php`; `src/Granjas/Infra/Controllers/GranjaController.php`; `src/Granjas/Domain/CalculadoraBonosGranja.php` (parcial); `app/Models/{Granja,Nido,Refugio,Complemento,Habitat,Reclutado,ExploracionActiva,PlayerInventory}.php`; `database/migrations/2026_10_26_000001_create_farm_tables.php`; `database/migrations/2026_08_29_000007_create_player_inventory_table.php`; `routes/granjas.php`.
- Catálogo e inventario: `src/Shared/Domain/Items/*` (todos); `app/Support/ItemCatalogo.php`; `app/Models/Concerns/BelongsToUser.php`; `src/Shared/Domain/{Collection.php,ValueObjects/MapaFactores.php,ValueObjects/MapaConteo.php,ProbabilidadCaptura.php}`.
- Exploraciones: `src/Exploraciones/Domain/{ContextoBotin,CalculadorBotinExploracion,PerfilExploracion,ModoExploracion,PesosEvento}.php`; `src/Exploraciones/Domain/Recompensas/{RecompensaItem,RecompensaItemCollection,ResultadoRecompensas}.php`; `src/Exploraciones/App/{FinalizarExploracionHandler,PersistirRecompensas,ProcesarExploracionHandler}.php` (parcial); `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php`.
- Combate: `src/Battle/Domain/{AgregadoBatalla,Combatiente,GestorTurnos,AccionBatalla}.php` (secciones); `src/Battle/Domain/ValueObjects/{MultiplicadoresStats,EstadoMovimientos,CondicionVolatil,EstadoObjeto}.php`; `src/Battle/Domain/Enums/{FaseCombate,ClaveCondicion,ClaveItem}.php`; `src/Battle/Domain/Catalog/CatalogoObjetos.php`; `src/Battle/Domain/Chain/{CadenaDanio,ManejadorMarcado,ManejadorHabilidadesDefensa,ManejadorEquipo,ManejadorObjetosEquipados}.php`; `src/Battle/Domain/Effects/Equipo/EfectoEquipoResistenciaTipo.php`; `src/Battle/Domain/Effects/{ColeccionEfectos,InterfazEfecto}.php` (parcial); `src/Battle/Presentation/DTOAccionBatalla.php`; `app/Support/BattleSessionService.php`; `app/Livewire/Combate.php` (secciones); `app/Livewire/Presenters/BatallaVistaPresenter.php` (parcial); `resources/views/livewire/combate.blade.php`, `partials/moves-panel.blade.php`; `resources/views/habitats/show.blade.php` (secciones de granja y JS).
- Configuración: `composer.json`, `deptrac.yaml`, `routes/web.php`.

**No leído / pendiente:** `.ai/rules/*`, `.opencode/work/TASK-011/{analysis.md,request.md,review.md,security.md,final-report.md}` completos, `TransformadorResultadoExploracion`, `PresentadorExploraciones`, `ObtenerFamiliasDisponibles`, `CalculadoraBonosGranja` completo, `BattleEffectServiceProvider` completo, `EstadoObjeto` completo y todos los contextos de creación de batalla. No se modificó ningún archivo.
