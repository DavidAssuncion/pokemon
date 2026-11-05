# ADR-0001: Inventario compartido y arquitectura de plantaciones, forja y objetos de combate (F2–F5)

- **Estado:** Aceptado
- **Fecha:** 2026-11-04
- **Ámbito:** `src/Shared`, `src/Granjas`, `src/Exploraciones`, `src/Forja`, `src/Battle`, `app/`
- **Tarea:** TASK-012 — plan v1 (F2–F5)

## Contexto

La Fase 1 dejó un catálogo compartido de 36 bayas y 7 bonguris, un inventario unificado en
`player_inventory`, el botín de exploración y los modos de expedición. Sobre esa base, F2–F5
introducen cuatro necesidades que no estaban resueltas:

1. **Una única semántica atómica de inventario.** Granjas (F2), Forja (F4) y Combate (F5)
   consumen e incrementan las mismas filas de `player_inventory`. Antes de F2 cada módulo
   resolvía sus propios locks y validaciones.
2. **Plantaciones sin cron.** El crecimiento de bayas debe calcularse al leer, sin jobs ni
   tablas de stock persistido, y sin permitir dobles plantaciones ni dobles cosechas.
3. **Bonus de exploración sin romper la dirección de dependencias.** `Exploraciones\Domain`
   no puede importar `Granjas\Domain`.
4. **Uso de objetos en combate sin contaminar el contrato de movimiento.** El flujo de turno
   asumía siempre un `DTOAccionBatalla` con un `DTOMovimientoBatalla`; un objeto consume la
   acción pero no el movimiento.

Restricciones de la tarea: sin tests, sin migraciones destructivas, sin F6/F7, sin auto-cosecha,
sin iconos definitivos y sin commit ni push.

## Decisión

| ID | Decisión | Motivo |
|---|---|---|
| **D1** | Puerto `InventarioJugador` en `src/Shared/Domain/Ports` + adaptador único `InventarioJugadorEloquent` en `src/Shared/Infra/Inventario` | F2/F4/F5 comparten la misma semántica; ampliar `InventarioGranja` acoplaría Forja y Battle a `Granjas\App` y duplicaría locks. |
| **D2** | Tabla `nest_plantings` con **una fila por hueco ocupado**, stock calculado al leer y unique `(nest_id, slot_index)` | Evita cron, dobles plantaciones y dobles cosechas; replantar actualiza la fila existente. |
| **D3** | Puerto `ConsultorPlantaciones` en `Exploraciones\Domain\Ports`, implementado en `Granjas\Infra`; `EsDelHabitat` en `Exploraciones\App`; flag persistido en `eventos['bonos']['del_habitat']` | `Exploraciones\Domain` recibe un `MapaFactores` ya resuelto y no importa `Granjas`; el flag se calcula una vez. |
| **D4** | 39 claves nuevas en `src/Shared/Domain/Items`, 46 recetas estáticas en `src/Forja`, sin migración | El catálogo ya vive en código y es cerrado; las recetas son configuración, no datos operativos. |
| **D5** | `AccionObjeto` + `DTOAccionObjeto` separado, unión en `AgregadoBatalla::$pendingAction`, fase `player_item_target`, `ResistenciasTemporales` con manejador propio de cadena, `refrescarTemporal()` y `restaurarUsos()` | No se inventa un movimiento ficticio, no se contamina `DTOAccionBatalla` y la resistencia no depende de habilidades defensivas. |
| **D6** | `SESSION_VERSION = 21`, `MINIMUM_COMPATIBLE_SESSION_VERSION = 9`; v20 sigue cargando con defaults | No hay incompatibilidad real y forzar el descarte destruiría batallas vivas sin beneficio. |

### D1 — Inventario compartido y carve-out de claves legacy

Contrato (`src/Shared/Domain/Ports/InventarioJugador.php`):

```php
interface InventarioJugador
{
    public function saldo(int $userId, string $clave): int;
    public function saldos(int $userId, StringCollection $claves): MapaConteo;
    public function consumir(int $userId, string $clave, int $cantidad, ?string $mensajeError = null): void;
    public function consumirVarios(int $userId, MapaConteo $claves, ?string $mensajeError = null): void;
    public function incrementar(int $userId, string $clave, int $cantidad): void;
}
```

El adaptador (`src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`):

- Usa `PlayerInventory::withoutUserScope()` y recibe `userId` explícito: no depende de `Auth`.
- `consumir`/`consumirVarios` bloquean las filas con `lockForUpdate` en orden de `item_key`,
  validan **todos** los saldos antes de decrementar y nunca dejan cantidades negativas.
- `incrementar` usa `insertOrIgnore` + `increment` para ser atómico ante concurrencia.
- Toda clave pasa por `CatalogoItems::existe()`; una clave desconocida lanza
  `ItemDesconocido` (HTTP 422).

**Carve-out:** el adaptador acepta además las claves legacy de caramelos
`familia:{id}` y `ev:{stat}` (con sufijo numérico). No se valida **solo** contra
`CatalogoItems` porque esas claves existen en `player_inventory` desde antes del catálogo,
las consume la granja (`InventarioGranja::consumirFamilia/consumirEv`) y las escribe
`PersistirRecompensas`; exigir catálogo únicamente rompería el flujo de caramelos o exigiría
una migración de datos fuera del alcance aprobado. El carve-out es estrecho: cualquier otra
clave no catalogada sigue siendo `ItemDesconocido`.

`InventarioGranja` queda como fachada de caramelos: `resumen()` mantiene su lectura directa,
`consumirFamilia`/`consumirEv` delegan en el puerto y se añade `resumenBayas()`.

### D2 — `nest_plantings`: una fila por hueco y stock calculado

Migración `database/migrations/2026_11_04_000003_create_nest_plantings_table.php`:

| Columna | Tipo | Nota |
|---|---|---|
| `nest_id` | FK → `nests.id` | `ON DELETE CASCADE` |
| `slot_index` | `unsignedSmallInteger` | Hueco 1..`nivel * 2`; validado en aplicación |
| `berry_key` | `varchar(32)` | Clave `baya:{slug}` validada contra `ClaveBaya` |
| `planted_at` | `timestamp` | Inicio del ciclo actual |
| `harvest_count` | `unsignedSmallInteger` | Acumulado histórico de cosechas |

Restricciones: unique `(nest_id, slot_index)` e índice por `berry_key`. Sin FK al catálogo,
igual que `player_inventory`.

Reglas (`src/Granjas/Domain/ReglasPlantacion.php`):

```text
huecos        = nivel * 2                       (nivel 0 no permite plantar)
transcurridas = intdiv(ahora - planted_at, 86400)
stock         = max(0, min(3, transcurridas - harvest_count))
progreso      = stock > 0 ? 100 : floor((segundos % 86400) / 864)
estado        = stock > 0 ? LISTA : CRECIENDO
```

`harvest_count` es acumulado, no un contador de stock: después de cosechar la planta sigue
produciendo una unidad al día. No se persiste el stock. `PlantarBaya` y `CosecharPlantacion`
resuelven la granja y el nido por `(user_id, habitat_id, evolution_chain_id)` —nunca por un
`nest_id` de URL—, bloquean nido y hueco y recalculan el stock **después** del lock.

### D3 — F3: puerto de plantaciones y flag de hábitat

- `ConsultorPlantaciones::factoresPara(int $userId, int $habitatId): MapaFactores` vive en
  `src/Exploraciones/Domain/Ports` y se implementa en
  `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`. La dirección
  `Granjas.Infra → Exploraciones.Domain` está permitida; `Exploraciones.Domain` no importa
  `Granjas`.
- El adaptador agrega `nest_plantings` por `berry_key`, filtrando por `(farms.user_id,
  farms.habitat_id)`, y aplica `min(2.0, 1 + 0.20 * plantas)`.
- `EsDelHabitat` (`src/Exploraciones/App/EsDelHabitat.php`) consulta `pokemon_habitat` por
  `pokemon_id` + `habitat_id`.
- `ProcesarExploracionHandler` persiste el flag una sola vez en
  `eventos['bonos']['del_habitat']`; `FinalizarExploracionHandler` lo reutiliza y, si falta
  (finalización sin tick previo), lo calcula como fallback.
- Los factores de plantación solo multiplican los pesos de **bayas salvajes**
  (`CalculadorBotinExploracion::pesosBayas`); `caeBonguri()` no los recibe. El bonus de hábitat
  aplica +25 % de tiradas de baya (tope 4) y +10 pp de bonguri (tope 85 %). Un mapa vacío deja
  el botín idéntico a F1.

### D4 — F4: catálogo, recetas y forja

- 39 claves nuevas en `src/Shared/Domain/Items`: 8 pociones, 1 recipiente, 7 mejoras,
  5 reductores y 18 resistencias. `CatalogoItems::construir()` y `aPartirDeItemKey()` se
  amplían; `objetosDeCombate()` devuelve los 44 objetos usables.
- 46 recetas en `src/Forja/Domain/CatalogoRecetas.php`: 9 de pociones, 7 de recipiente
  (una por color de bonguri), 7 mejoras, 5 reductores y 18 resistencias; 39 resultados
  distintos.
- Varias recetas comparten `resultado_clave`: `pocion:pocion` se fabrica desde Aranja o Zidra
  y `material:recipiente` desde los 7 bonguris. El id de receta (`receta:*`) es único y
  `CatalogoRecetas::paraResultado()` devuelve las variantes; la UI las agrupa y muestra un
  selector con `etiqueta`.
- `Fabricar` exige `cantidad = 1`, consume todos los ingredientes en una transacción con lock
  ordenado (`consumirVarios`) e incrementa el resultado. Si falta un ingrediente devuelve 422
  y no descuenta nada. Sin `Idempotency-Key`: el doble clic se mitiga deshabilitando el botón.
- UI de `/forja` con **4 categorías colapsables**: Medicinas (pociones + recipiente),
  Mejoras, Reductores y Resistencias.

### D5 — F5: objetos en combate

- `AccionObjeto` (`src/Battle/Domain/AccionObjeto.php`) y
  `DTOAccionObjeto` (`src/Battle/Domain/DataTransferObjects/DTOAccionObjeto.php`). El DTO
  **no implementa `Wireable`**: vive en dominio y viaja dentro del agregado, no como estado
  público de Livewire.
- `AgregadoBatalla::$pendingAction` pasa a `DTOAccionBatalla|DTOAccionObjeto|null`;
  `resolverAccion()` bifurca con `instanceof`.
- Fase `player_item_target` (`FaseCombate::SELECCION_OBJETIVO_ITEM`).
- `AgregadoBatalla::$actorActualId` es la fuente de verdad server-side del actor para las
  acciones de objeto; `actorJugadorActual()` valida que sea del equipo del jugador, esté vivo
  y tenga acción pendiente. La propiedad pública `actingRefId` no autoriza el uso del objeto.
- `CatalogoEfectosItem` registra 44 efectos: 6 bayas básicas + 38 objetos fabricados.
- `ResistenciasTemporales` + `ManejadorResistenciaTemporal` en `CadenaDanio` aplican la
  resistencia de tipo a cualquier ataque de ese tipo, aunque el atacante ignore habilidades.
- `MultiplicadoresStats::refrescarTemporal()` reemplaza el factor temporal en lugar de
  multiplicarlo: reaplicar un buff/debuff refresca sin stackear.
- `Combatiente::restaurarUsos()` reinicia los usos de movimientos. Restaurar Todo y Max Revivir
  lo invocan.
- `UsarObjetoEnCombate` valida antes de consumir, y dentro de una transacción consume 1 unidad
  y aplica el efecto; si aplicar falla, el consumo se revierte. El flujo consume **acción**,
  no movimiento (`consumeAction`, sin `consumirMovimiento`).

### D6 — Compatibilidad de sesión

`app/Support/BattleSessionService.php` sube `SESSION_VERSION` a **21** y mantiene
`MINIMUM_COMPATIBLE_SESSION_VERSION` en **9**. Los payloads v20 siguen cargando porque:

- `Combatiente::__unserialize()` restaura `resistencias` con default vacío.
- Los payloads v20 nunca contienen `DTOAccionObjeto` en `pendingAction`.
- `AgregadoBatalla::$actorActualId` tiene default `null` y cae a `getNextActor()`.

No se destruyen batallas vivas de ruta, entrenador, gimnasio o mazmorra. Si se hiciera rollback
de código con payloads v21 que contengan `DTOAccionObjeto`, el `unserialize` fallaría porque la
clase no existiría: el despliegue debe ser hacia delante o mantener la clase durante la ventana
de rollback.

## Alternativas consideradas

| Alternativa | Resultado | Motivo |
|---|---|---|
| Ampliar `InventarioGranja` con `baya:*` y reutilizarlo desde Forja/Battle | Descartada | Acopla Forja y Battle a `Granjas\App`, duplica mensajes y deja el puerto genérico sin existir. |
| Validar **solo** contra `CatalogoItems` | Descartada | Rompe las claves legacy `familia:{id}` y `ev:{stat}` que ya existen y se consumen en granja. |
| `Exploraciones\Domain` importando `Granjas\Domain` | Descartada | Rompe la regla de dominio sin dependencias de otros módulos (`docs/ddd.md`). |
| Servicio `Granjas\App` inyectado en el handler (precedente `ResolverBonosFamilia`) | Descartada | Deja el cálculo del botín dependiente del contenedor; se prefiere puerto + VO. |
| Añadir `itemKey` a `DTOAccionBatalla` | Descartada | Obliga a un `DTOMovimientoBatalla` ficticio y contamina el contrato de ataque. |
| Resistencia de tipo vía `effects()->add(...)` | Descartada | `ManejadorHabilidadesDefensa` no se ejecuta si el atacante ignora habilidades; se elige VO + manejador propio. |
| Persistir el stock de cada plantación en columna | Descartada | Requiere cron o escrituras por tick; el cálculo on-read es idempotente y sin mantenimiento. |
| Recetas en base de datos | Descartada | El catálogo es cerrado y ya vive en código; una tabla añadiría migración y seed sin beneficio. |
| Subir también el mínimo de sesión a 21 | Descartada | Destruiría batallas vivas sin incompatibilidad real; se documenta que v20 carga con defaults. |
| Modelar “cualquier bonguri” con arrays alternativos en la receta | Descartada | `MapaConteo` no expresa “A o B”; 7 recetas con el mismo resultado son más simples y compatibles. |

## Consecuencias

### Positivas

- Una sola semántica de inventario: F2, F4 y F5 no duplican locks, validación de stock ni
  mensajes de error.
- Las plantaciones no necesitan cron y el unique + locks eliminan dobles plantaciones y dobles
  cosechas.
- `Exploraciones\Domain` conserva su independencia y el botín recibe factores ya resueltos.
- El combate no se contamina con movimientos ficticios: el objeto tiene su propio DTO, su fase
  y su rama de resolución.
- La resistencia temporal funciona con ataques que ignoran habilidades.
- Subir `SESSION_VERSION` sin subir el mínimo evita destruir batallas en curso.
- El catálogo y las recetas no requieren migración; los totales son verificables en código
  (82 definiciones, 44 objetos de combate, 46 recetas, 39 resultados).

### Negativas y deuda asumida

- **Segunda vía de escritura de inventario.** `PersistirRecompensas` sigue escribiendo
  `player_inventory` directamente (`insertOrIgnore` + `increment`) para caramelos y botín de
  F1, sin pasar por `InventarioJugador`. El puerto no es todavía el único escritor; unificarlo
  exige migrar ese flujo y decidir el tratamiento de `tipo:{slug}`.
- **`tipo:{slug}` fuera del carve-out.** Las claves de caramelo de tipo existen en
  `player_inventory` y las escribe `PersistirRecompensas`, pero el adaptador solo acepta
  catálogo, `familia:` y `ev:`. Hoy no se enrutan por el puerto; si en el futuro se hace, habrá
  que ampliar el carve-out o migrar la clave.
- **`resolveActor()` de movimientos sigue usando `actingRefId`.** Es una deuda preexistente:
  `actingRefId` es una propiedad pública manipulable por el cliente. Las acciones de objeto ya
  usan `AgregadoBatalla::$actorActualId`, pero el flujo de movimientos no se homogeneizó en
  esta tarea.
- **Iconos nuevos con fallback.** Pociones y recipiente usan `/images/misc/unknown.webp`;
  mejoras y reductores reutilizan el icono de su baya de origen; las resistencias usan el icono
  del tipo (`/images/type/{slug}.webp`). Es deuda visual, no bloquea la fabricación.
- **Sin `Idempotency-Key` en forja.** Dos peticiones simultáneas no dejan saldos negativos por
  los locks, pero podrían fabricar dos veces si ambas pasan la validación; la UI deshabilita el
  botón.
- **Consumo de inventario y sesión no son atómicos en F5.** La transacción cubre inventario y
  efecto; la sesión se guarda después. Si el guardado falla tras consumir, se pierde el objeto.
  El orden “validar → consumir → aplicar → guardar” evita regalar objetos y aplicar efectos no
  persistidos.
- **F5 toca el núcleo del turno.** Riesgo alto mitigado con DTO separado, `instanceof`, rama
  aislada y revisión manual de los cuatro contextos (ruta, entrenador, gimnasio, mazmorra).
- **Bayas directas limitadas a 6.** `chesto`, `persim`, `lum` y `leppa` no existen en
  `ClaveBaya`; se difieren. `leppa` requeriría un modelo de PP por movimiento.
- **Replantar pierde el stock pendiente.** Es una decisión de producto; la UI avisa con
  confirmación y el backend reinicia `planted_at` y `harvest_count`.

## Referencias

- Plan aprobado: [`TASK-012/plan.md`](../../.opencode/work/TASK-012/plan.md)
- Diseño técnico y Anexo C: [`TASK-012/architecture.md`](../../.opencode/work/TASK-012/architecture.md)
- Análisis previo: [`TASK-012/analysis.md`](../../.opencode/work/TASK-012/analysis.md)
- Ejecución y verificación estática: [`TASK-012/execution.md`](../../.opencode/work/TASK-012/execution.md)
- Arquitectura del proyecto: [`docs/architecture.md`](../architecture.md)
- Convención de módulos: [`docs/ddd.md`](../ddd.md)
- Implementación principal:
  - `src/Shared/Domain/Ports/InventarioJugador.php`
  - `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`
  - `src/Granjas/Domain/ReglasPlantacion.php`
  - `src/Granjas/App/PlantarBaya.php`
  - `src/Granjas/App/CosecharPlantacion.php`
  - `src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php`
  - `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`
  - `src/Forja/Domain/CatalogoRecetas.php`
  - `src/Forja/App/Fabricar.php`
  - `src/Battle/Domain/DataTransferObjects/DTOAccionObjeto.php`
  - `src/Battle/Domain/UsoObjetos/CatalogoEfectosItem.php`
  - `src/Battle/Domain/ValueObjects/ResistenciasTemporales.php`
  - `src/Battle/Domain/Chain/ManejadorResistenciaTemporal.php`
  - `src/Battle/App/UsarObjetoEnCombate.php`
  - `app/Support/BattleSessionService.php`
