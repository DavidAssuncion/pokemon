<subagent sessionID="ses_e593f4261ffe7o5C0Tvd5iV336" state="completed">
# TASK-012 — Diseño técnico F2–F5

## 1) Resumen ejecutivo y decisiones

### DECISIÓN

Se implementa F2–F5 sobre la base de F1 **sin migraciones destructivas**, con estas decisiones estructurales:

| ID | Decisión | Justificación breve |
|---|---|---|
| **D1** | Puerto compartido `InventarioJugador` en `src/Shared/Domain/Ports` + adaptador único `src/Shared/Infra/Inventario/InventarioJugadorEloquent` | F2, F4 y F5 necesitan la misma semántica atómica. Ampliar `InventarioGranja` obligaría a Forja y Battle a depender de `Granjas\App` y duplicaría `lockForUpdate`. |
| **D2** | F2: tabla `nest_plantings` con una fila por hueco ocupado, stock **calculado** al leer, unique `(nest_id, slot_index)` | Evita cron, doble plantación y doble cosecha. Replantar actualiza la fila existente (pierde stock, decisión de producto). |
| **D3** | F3: puerto `ConsultorPlantaciones` en `Exploraciones\Domain\Ports`, adaptador en `Granjas\Infra`; `EsDelHabitat` en `Exploraciones\App`; flag persistido en `eventos['bonos']['del_habitat']` | `Exploraciones\Domain` no importa `Granjas\Domain`; el cálculo del botín recibe un `MapaFactores` ya resuelto. |
| **D4** | F4: 39 claves nuevas en `src/Shared/Domain/Items`, recetas estáticas en `src/Forja`, sin migración | El catálogo es cerrado y ya vive en código (`ItemCatalogo.php:85` delega en `CatalogoItems`). |
| **D5** | F5: `DTOAccionObjeto` separado + unión en `AgregadoBatalla::$pendingAction`, fase `player_item_target`, `ResistenciasTemporales` con manejador propio de cadena, `refrescarTemporal()` y `restaurarUsos()` | No contamina `DTOAccionBatalla` con un movimiento ficticio y no depende del daño por habilidades (que se salta con `ignoraHabilidadRival`). |
| **D6** | `SESSION_VERSION` sube a **21**, pero `MINIMUM_COMPATIBLE_SESSION_VERSION` se mantiene en **9** | No hay necesidad técnica de invalidar v20: todo lo nuevo tiene default en `__unserialize`. Forzar el descarte destruiría batallas vivas en ruta/entrenador/gimnasio/mazmorra sin beneficio. |
| **D7** | F2 antes que F3; F4 en paralelo con F2/F3; F5 al final | F3 lee `nest_plantings`; F4 solo depende de `Shared\Domain\Items`; F5 consume el catálogo extendido. |

### CONTEXTO verificado

- F1 está sin commitear en `task-011`; `git status` confirma migraciones `modo`/`bonguri` y `src/Shared/Domain/Items/` nuevos.
- `GranjaDetalle` solo expone `id`, `habitat`, `familias`, `inventario` (`src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php:14-19`).
- `NidoGranja` solo expone `nivel`, `rangoIvs`, `factorTiempo`, `mejora` (`NidoGranja.php:14-18`); su `toArray()` no tiene huecos (`:28-39`).
- `ObtenerGranja` construye el detalle y usa nivel 0 si no hay fila (`ObtenerGranja.php:47-85`, `:94`).
- No existe `nest_plantings`; `nests` tiene unique `(farm_id, evolution_chain_id)` (`database/migrations/2026_10_26_000001_create_farm_tables.php:22-33`).
- `InventarioGranja` solo resume/consume `familia:*`/`ev:*` (`InventarioGranja.php:28-34`, `:59-87`); su `consumir()` privado ya usa `lockForUpdate` (`:99-116`).
- `CatalogoItems::construir()` solo recorre bayas y bonguris (`CatalogoItems.php:131-164`); `aPartirDeItemKey()` solo entiende `baya:`/`bonguri:` (`:99-118`).
- `ContextoBotin` no tiene factores de plantación (`ContextoBotin.php:23-32`); `FinalizarExploracionHandler` pasa `delHabitat: false` (`:658`) y `CalculadorBotinExploracion` ignora `delHabitat` (`:153`, `:211`).
- `pendingAction` está tipado solo como `?DTOAccionBatalla` (`AgregadoBatalla.php:29`, `:103-111`); `resolverAccion()` asume movimiento (`Combate.php:843-997`).
- `FaseCombate` no tiene fase de objeto (`FaseCombate.php:31-38`) y el presentador solo marca rivales (`BatallaVistaPresenter.php:102-121`).
- `SESSION_VERSION = 20` y mínimo 9; `cargar()` solo descarta `< 9` (`BattleSessionService.php:47`, `:60`, `:96-100`).

### OPCIONES EVALUADAS

1. **Ampliar `InventarioGranja`** con `baya:*` y reutilizarlo desde Forja/Battle. Descartada: acopla Forja y Battle a `Granjas\App`, duplica mensajes y deja el puerto genérico sin existir.
2. **Puerto `InventarioJugador` en Shared + adaptador único**. Elegida: contrato pequeño, una sola transacción/lock, reutilizable por F2/F4/F5.
3. **F3 con `Exploraciones\Domain` importando `Granjas\Domain`**. Descartada por `docs/ddd.md` (Domain sin dependencias de otros módulos).
4. **F3 con servicio `Granjas\App` inyectado** (precedente `ResolverBonosFamilia`). Viable, pero deja el cálculo del botín dependiente del contenedor; se prefiere puerto + VO.
5. **F5 añadiendo `itemKey` a `DTOAccionBatalla`**. Descartada: obliga a un `DTOMovimientoBatalla` ficticio y contamina el contrato de ataque.
6. **F5 con resistencia vía `effects()->add(...)`**. Descartada: `ManejadorHabilidadesDefensa` no se ejecuta si el atacante ignora habilidades (`ManejadorHabilidadesDefensa.php:13-22`). Se elige VO + manejador de cadena propio.
7. **Subir el mínimo de sesión a 21**. Descartada: `cargar()` no descarta v20 y no hay incompatibilidad real; se documenta que v20 sigue cargando con defaults.

---

## 2) Diseño por fase

### MAPA DE CAPAS

- **Dominio**: `Plantacion`, `ReglasPlantacion`, `EstadoPlantacion` (Granjas); `ContextoBotin`, `CalculadorBotinExploracion`, `ConsultorPlantaciones` (Exploraciones); `RecetaFabricacion`, `CatalogoRecetas` (Forja); `AccionObjeto`, `EfectoItem`, `CatalogoEfectosItem`, `ServicioUsoObjetos`, `ResistenciasTemporales` (Battle); enums de items en `Shared\Domain\Items`.
- **Aplicación**: `PlantarBaya`, `CosecharPlantacion` (Granjas); `EsDelHabitat`, `FinalizarExploracionHandler`, `ProcesarExploracionHandler` (Exploraciones); `ListarRecetas`, `Fabricar` (Forja); `UsarObjetoEnCombate`, `ObtenerOpcionesObjeto` (Battle).
- **Infraestructura**: `PlantacionNido`, `PlantacionNido` relations, `GranjaController`, `PlantarBayaRequest`; `ConsultorPlantacionesEloquent`; `ForjaController`, `FabricarRequest`; adaptador `InventarioJugadorEloquent`; `BattleSessionService`.
- **Presentación**: Blade de granja (`habitats/show.blade.php`), página de forja, partial de objetos de combate (`items-panel.blade.php`), `Combate.php`, `BatallaVistaPresenter`.

---

### F2 — Plantación y cosecha

#### Dominio (`src/Granjas/Domain`)

**Entidad `Entities/Plantacion.php`**

```php
final readonly class Plantacion
{
    public function __construct(
        public int $id,
        public int $slotIndex,
        public string $berryKey,          // validada contra ClaveBaya antes de construir
        public \DateTimeImmutable $plantedAt,
        public int $harvestCount,
    ) {}

    /** @return array{id:int, slot_index:int, berry_key:string, planted_at:string, harvest_count:int} */
    public function toArray(): array;
}
```

**Enum `EstadoPlantacion`**: solo `CRECIENDO` y `LISTA`. No se incluye `AGOTADA`: la planta persiste tras cosechar y el stock se recalcula; un estado “agotada” no existe en este modelo (decisión de producto: stock máximo 3 acumulable, planta persistente).

**`ReglasPlantacion`**

```php
final class ReglasPlantacion
{
    public const int HUECOS_POR_NIVEL = 2;
    public const int HORAS_CRECIMIENTO = 24;
    public const int STOCK_MAXIMO = 3;
    public const int COSTE_PLANTAR = 1;
    public const float BONUS_PESO_POR_HUECO = 0.20;
    public const float TOPE_FACTOR = 2.0;

    public static function huecosDe(int $nivelNido): int;              // max(0, nivel*2)
    public static function stockDe(Plantacion $p, \DateTimeImmutable $ahora): int;
    public static function progresoPct(Plantacion $p, \DateTimeImmutable $ahora): int;
    public static function estadoDe(Plantacion $p, \DateTimeImmutable $ahora): EstadoPlantacion;
    public static function puedeCosechar(Plantacion $p, \DateTimeImmutable $ahora): bool;
    public static function factorPlantacion(int $unidades): float;    // min(2.0, 1 + 0.20*unidades)
}
```

Fórmula exacta (sin cron):

```text
segundos    = max(0, ahora - planted_at)
transcurridas = intdiv(segundos, 86400)
stock       = max(0, min(3, transcurridas - harvest_count))
progreso    = stock > 0 ? 100 : (int) floor((segundos % 86400) / 864)
estado      = stock > 0 ? LISTA : CRECIENDO
```

`harvest_count` es acumulado histórico; por eso `transcurridas - harvest_count` sigue produciendo 1 cosecha/día indefinidamente tras cada recolección.

**DTOs**

```php
final readonly class PlantacionGranja
{
    public function __construct(
        public int $slot,
        public string $berryKey,
        public string $berryNombre,
        public string $berryIcono,
        public string $plantedAt,       // ISO-8601
        public int $stock,
        public int $stockMax,
        public bool $puedeCosechar,
        public int $progresoPct,
        public string $estado,          // EstadoPlantacion->value
    ) {}

    public function toArray(): array;   // snake_case para Blade/API
}
```

`PlantacionesGranjaCollection extends Collection` (`$type = PlantacionGranja::class`) con `porSlot(int $slot): ?PlantacionGranja`.

`BayaGranja` (`clave`, `nombre`, `icono`, `cantidad`), `BayasGranjaCollection`.
`BonguriGranja` (`clave`, `nombre`, `icono`) con `static desde(ClaveBonguri): self`.

**Modificación de `NidoGranja`** (aditiva y compatible):

```php
final readonly class NidoGranja
{
    public readonly PlantacionesGranjaCollection $plantaciones;

    public function __construct(
        public int $nivel,
        public RangoIvs $rangoIvs,
        public float $factorTiempo,
        public MejoraNido $mejora,
        public int $huecosTotales = 0,
        ?PlantacionesGranjaCollection $plantaciones = null,
    ) {
        $this->plantaciones = $plantaciones ?? new PlantacionesGranjaCollection();
    }

    public function huecosLibres(): int; // max(0, huecosTotales - count(plantaciones))
    public function toArray(): array;    // añade huecos_totales, huecos_libres, plantaciones
}
```

**Modificación de `GranjaDetalle`**:

```php
final readonly class GranjaDetalle
{
    public readonly BayasGranjaCollection $bayas;

    public function __construct(
        public int $id,
        public HabitatGranja $habitat,
        public FamiliasGranjaCollection $familias,
        public InventarioCaramelosGranja $inventario,
        ?BayasGranjaCollection $bayas = null,
        public ?BonguriGranja $bonguri = null,
    ) {
        $this->bayas = $bayas ?? new BayasGranjaCollection();
    }
}
```

`toArray()` añade `bayas` y `bonguri`. El contrato actual (`id`, `habitat`, `familias`, `inventario`) no se altera.

#### Aplicación (`src/Granjas/App`)

**`PlantarBaya`**

```php
public function __construct(
    private readonly AsegurarGranja $asegurarGranja,
    private readonly FamiliaEnHabitat $familiaEnHabitat,
    private readonly InventarioJugador $inventario,
) {}

public function handle(
    int $userId,
    int $habitatId,
    int $evolutionChainId,
    int $slot,
    string $berryKey,
): void;
```

Flujo:

1. Validar `berryKey` contra `CategoriaItem::BAYA->prefijo()` + `ClaveBaya::tryFrom()`; si no, `ViolacionReglaNegocio` 422. Nunca se acepta `item_key` libre.
2. `FamiliaEnHabitat::asegurar($habitatId, $evolutionChainId)` (`FamiliaEnHabitat.php:15-25`).
3. Dentro de `DB::transaction`:
   - `AsegurarGranja::para($userId, $habitatId)` (patrón `MejorarNido.php:29-60`).
   - `Nido::where('farm_id', $granja->id)->where('evolution_chain_id', $chain)->lockForUpdate()->first()`.
   - Exigir `$nido !== null && $nido->level >= 1`; `huecos = ReglasPlantacion::huecosDe($nido->level)`; `1 <= $slot <= $huecos`.
   - `InventarioJugador::consumir($userId, $baya->clave(), 1, 'No tienes esa baya')`.
   - `PlantacionNido::where('nest_id', $nido->id)->where('slot_index', $slot)->lockForUpdate()->first()`.
   - Si existe: actualizar `berry_key`, `planted_at = now()`, `harvest_count = 0` (replantar; pierde stock). Si no: crear.
4. El lock sobre la fila del nido serializa todas las plantaciones de ese nido, incluidas las de huecos vacíos; el unique `(nest_id, slot_index)` es la red de seguridad.

**`CosecharPlantacion`**

```php
public function handle(
    int $userId,
    int $habitatId,
    int $evolutionChainId,
    int $slot,
): int; // unidades cosechadas
```

Flujo dentro de `DB::transaction`:

1. Resolver granja y nido por `(farm_id, chain)` con `lockForUpdate`.
2. Validar rango de slot con `ReglasPlantacion::huecosDe($nido->level)`.
3. Cargar `PlantacionNido` por `(nest_id, slot)` con `lockForUpdate`; si no existe → 422.
4. Construir `Plantacion` y `stock = ReglasPlantacion::stockDe($p, now()->toDateTimeImmutable())`; si `<= 0` → 422.
5. `InventarioJugador::incrementar($userId, $p->berryKey, $stock)`.
6. `$modelo->increment('harvest_count', $stock)`.
7. Devolver `$stock`.

El recálculo **después** del lock es lo que evita la doble cosecha: la segunda petición espera, lee `harvest_count` actualizado y obtiene stock 0.

**`ObtenerGranja`** (modificación):

- Cargar plantaciones en **una** consulta: `PlantacionNido::whereIn('nest_id', $nidos->pluck('id'))->get()->groupBy('nest_id')`.
- Para cada familia, construir `PlantacionesGranjaCollection` con `ReglasPlantacion` y `CatalogoItems::definicion($berryKey)` para nombre/icono.
- `$ahora = now()->toDateTimeImmutable()` una sola vez por petición.
- Construir `BonguriGranja` con `ClaveBonguri::tryFrom($habitat->bonguri ?? '') ?? ClaveBonguri::porDefecto()` (mantiene el fallback de F1).
- Construir `BayasGranjaCollection` con `InventarioGranja::resumenBayas($userId)`.

**`InventarioGranja`** (modificación):

- Inyectar `InventarioJugador`.
- `consumirFamilia`/`consumirEv` delegan en `InventarioJugador::consumir(..., $mensaje)` para eliminar el `lockForUpdate` duplicado. El resumen de caramelos (`resumen()`) no cambia.
- Nuevo `resumenBayas(int $userId): BayasGranjaCollection`: obtiene `saldos` de las 36 claves `baya:*` en una consulta y devuelve solo las de `cantidad > 0`.

#### Infraestructura

- **Modelo** `app/Models/PlantacionNido.php`: tabla `nest_plantings`, `$fillable` (`nest_id`, `slot_index`, `berry_key`, `planted_at`, `harvest_count`), cast `planted_at => datetime`, `belongsTo(Nido::class, 'nest_id')`.
- **`Nido`**: añadir `plantaciones(): HasMany` (`app/Models/Nido.php`).
- **Controlador** `GranjaController`:
  - `plantar(int $habitat, int $chain, PlantarBayaRequest $request): JsonResponse`
  - `cosechar(int $habitat, int $chain, int $slot): JsonResponse`
  - Ambos devuelven `$this->respuesta($habitat)` (mismo contrato actual, `GranjaController.php:55-60`).
- **FormRequest** `src/Granjas/Infra/Requests/PlantarBayaRequest.php`:
  - `slot`: `required|integer|min:1|max:10` (5 niveles × 2; el rango real se valida contra el nivel del nido en App).
  - `berry`: `required|string|max:32` (validación semántica contra `ClaveBaya` en App).
- **Rutas** (`routes/granjas.php`, dentro del grupo `auth` de `routes/web.php:37-48`):

```php
Route::post('/api/habitats/{habitat}/farm/nests/{chain}/plantings', [GranjaController::class, 'plantar'])
    ->whereNumber('habitat')->whereNumber('chain');
Route::post('/api/habitats/{habitat}/farm/nests/{chain}/plantings/{slot}/harvest', [GranjaController::class, 'cosechar'])
    ->whereNumber('habitat')->whereNumber('chain')->whereNumber('slot');
```

#### UI (`resources/views/habitats/show.blade.php`)

En la tarjeta de cada nido (`:1198-1215`), añadir bajo el botón de mejora:

- Cabecera “Plantaciones · X/Y huecos”.
- Grid de `familia.nido.huecos_totales` huecos. Para cada slot:
  - Buscar `familia.nido.plantaciones.find(p => p.slot === slot)` (iteración de presentación, no cálculo de negocio).
  - Vacío: `<select>` con `granja.bayas` y botón “Plantar”.
  - Ocupado: icono, nombre, barra `progreso_pct`, stock `stock/3`, botón “Cosechar” habilitado solo si `puede_cosechar`, y botón “Replantar”.
  - Replantar: `window.confirm('Este hueco ya tiene una planta. Se perderá el stock pendiente. ¿Continuar?')`.
- Estado Alpine nuevo: `plantandoSlot`, `bayaSeleccionada`.
- JS: `plantar(chainId, slot, berryKey)` y `cosechar(chainId, slot)` usando un `accionGranjaConBody(url, body)` (el `accionGranja` actual no envía body, `:1701-1726`).
- No se calcula stock, progreso ni estado en JS: llegan del backend (`PlantacionGranja`).

---

### F3 — Influencia de plantaciones y bonus por Pokémon nativo

#### Dominio (`src/Exploraciones/Domain`)

**`ContextoBotin`** (modificación aditiva):

```php
final readonly class ContextoBotin
{
    public readonly MapaFactores $factoresPlantacion;

    public function __construct(
        public ModoExploracion $modo,
        public ?ClaveBonguri $bonguriHabitat,
        public int $nivelHabitat,
        public int $duracionHoras,
        public bool $delHabitat,
        public bool $huboDerrota,
        public float $multiplicadorBotin = 1.0,
        public float $factorTiempo = 1.0,
        ?MapaFactores $factoresPlantacion = null,
    ) {
        $this->factoresPlantacion = $factoresPlantacion ?? MapaFactores::desdeArray([]);
    }
}
```

`MapaFactores` no admite `new` como default de parámetro; por eso se normaliza en el cuerpo (propiedad no promovida). `MapaFactores::obtener()` devuelve `1.0` por defecto (`MapaFactores.php:23-39`), así que un mapa vacío no altera el botín.

**`CalculadorBotinExploracion`** (modificación):

- `pesosBayas(MapaFactores $factores): array`: multiplica cada peso base por `$factores->obtener($baya->clave())`. El resto de la selección ponderada no cambia (`CalculadorBotinExploracion.php:153`).
- `tiradasDeBayas`: si `delHabitat`, `tiradas = min(4, (int) ceil($base * 1.25))`.
- `caeBonguri(..., bool $delHabitat)`: `probabilidad = min(85.0, ($base + ($delHabitat ? 10.0 : 0.0)) * $multiplicadorBotin * $factorTiempo)`.
- Los factores **solo** se aplican en `pesosBayas()`; `caeBonguri()` no los recibe (cumple “no afectan a bonguris”).

**Puerto `Ports/ConsultorPlantaciones.php`**

```php
namespace Src\Exploraciones\Domain\Ports;

use Src\Shared\Domain\ValueObjects\MapaFactores;

interface ConsultorPlantaciones
{
    /** @return MapaFactores clave `baya:{slug}` => factor (>1.0; 1.0 implícito) */
    public function factoresPara(int $userId, int $habitatId): MapaFactores;
}
```

Se elige puerto en el **consumidor** (`Exploraciones\Domain`) e implementación en `Granjas\Infra`: la dirección de dependencias es `Granjas.Infra -> Exploraciones.Domain` (permitida por `deptrac.yaml`, Infrastructure → Domain) y `Exploraciones.Domain` no importa `Granjas`.

#### Aplicación (`src/Exploraciones/App`)

**`EsDelHabitat`**

```php
final class EsDelHabitat
{
    public function handle(int $pokemonId, int $habitatId): bool;
}
```

Implementación: `DB::table('pokemon_habitat')->where('pokemon_id', $pokemonId)->where('habitat_id', $habitatId)->exists()`. La tabla tiene unique `(pokemon_id, habitat_id)` (`database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:15-21`). Se consulta por `$exploracion->reclutado->pokemon_id` (`app/Models/Reclutado.php:22-25`) y `$exploracion->habitat_id`.

**`ProcesarExploracionHandler`**:

- Nueva dependencia opcional `?EsDelHabitat $esDelHabitat = null` (mismo patrón que `ResolverBonosFamilia`, `ProcesarExploracionHandler.php:62`).
- Tras cargar `$eventos` (`:88`) y comprobar que hay reclutado/usuario (`:79`), persistir una sola vez:

```php
if (! $eventos->has('bonos')) {
    $eventos->put('bonos', [
        'del_habitat' => ($this->esDelHabitat ?? app(EsDelHabitat::class))
            ->handle((int) $exploracion->reclutado->pokemon_id, $exploracion->habitat_id),
    ]);
}
```

Así el flag se calcula una vez y viaja en el JSON de la exploración.

**`FinalizarExploracionHandler`**:

- Nuevas dependencias opcionales: `?ConsultorPlantaciones $consultorPlantaciones = null`, `?EsDelHabitat $esDelHabitat = null`.
- `botin()` (`:646-663`) pasa:
  - `factoresPlantacion: ($this->consultorPlantaciones ?? app(ConsultorPlantaciones::class))->factoresPara($exploracion->user_id, $exploracion->habitat_id)`.
  - `delHabitat: $this->delHabitat($exploracion, $eventos)`.
- `delHabitat()` privado:
  1. Si `eventos['bonos']['del_habitat']` existe, devolverlo (no repite consulta).
  2. Si no, calcular con `EsDelHabitat` (fallback para expediciones finalizadas sin tick previo).
- Mantener el fallback de bonguri blanco actual (`:654-655`); se documenta que “Vacío” sigue entregando blanco, comportamiento F1.
- `registrarResultado()` (`:588-614`) añade `bonos` al contrato persistido:

```php
'bonos' => ['del_habitat' => $this->delHabitat($exploracion, $eventos)],
```

#### Infraestructura (`src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`)

```php
public function factoresPara(int $userId, int $habitatId): MapaFactores
{
    $conteos = DB::table('nest_plantings')
        ->join('nests', 'nests.id', '=', 'nest_plantings.nest_id')
        ->join('farms', 'farms.id', '=', 'nests.farm_id')
        ->where('farms.user_id', $userId)
        ->where('farms.habitat_id', $habitatId)
        ->select('nest_plantings.berry_key', DB::raw('COUNT(*) as total'))
        ->groupBy('nest_plantings.berry_key')
        ->pluck('total', 'berry_key');

    $factores = [];
    foreach ($conteos as $clave => $unidades) {
        $factores[(string) $clave] = ReglasPlantacion::factorPlantacion((int) $unidades);
    }

    return MapaFactores::desdeArray($factores);
}
```

`ReglasPlantacion::factorPlantacion()` es `min(2.0, 1 + 0.20 * $unidades)`. Solo cuentan plantas existentes, no stock pendiente. Filtra por `(user_id, habitat_id)`: no afecta a otras granjas ni hábitats.

#### Presentación del bonus

- `TransformadorResultadoExploracion::desde()` añade al final `array $bonos = []` y devuelve `'bonos' => ['del_habitat' => (bool) ($bonos['del_habitat'] ?? false)]`.
- `PresentadorExploraciones::toTerminada()` propaga `'bonos' => is_array($resultado['bonos'] ?? null) ? $resultado['bonos'] : []` dentro de `'resultado'` (`:286-322`).
- `ResultadoExploracionVista::toView()` lee `$resultado['bonos']['del_habitat']`, añade `bonus_habitat` y `tiene_bonus_habitat`.
- `HitoExploracion::desde()` añade un hito `bonus_habitat` (“Conoce el terreno”) cuando el flag es true; la vista de exploraciones ya pinta hitos, así que no se duplica lógica en Blade.
- UI: badge “Bonus de hábitat” en el resumen de la expedición. No hay cálculos en JS.

#### Auto-cosecha

**Fuera de F3** por decisión de producto. No se añade llamada de cosecha en `FinalizarExploracionHandler` ni en el tick. El stock sigue siendo manual.

---

### F4 — Forja

#### Dominio compartido (`src/Shared/Domain/Items`)

Nuevos enums (todos con `clave(): string` y `nombre(): string`):

```php
enum ClavePocion: string {          // 8
    case POCION='pocion'; case SUPER='super'; case HIPER='hiper'; case MAXIMA='maxima';
    case CURA_TOTAL='curatotal'; case RESTAURAR_TODO='restaurar_todo';
    case REVIVIR='revivir'; case MAX_REVIVIR='max_revivir';
    public function clave(): string;   // 'pocion:'.$value
    public function nombre(): string;
    public function archivoIcono(): string; // '/images/misc/unknown.webp' (fallback F4)
}

enum ClaveMaterial: string {        // 1
    case RECIPIENTE='recipiente';
    public function clave(): string;   // 'material:recipiente'
    public function nombre(): string;  // 'Recipiente'
    public function archivoIcono(): string;
}

enum ClaveMejora: string {          // 7
    case ATAQUE='ataque'; case DEFENSA='defensa'; case ATAQUE_ESPECIAL='ataque_especial';
    case DEFENSA_ESPECIAL='defensa_especial'; case VELOCIDAD='velocidad';
    case PRECISION='precision'; case CRITICO='critico';
    public function clave(): string;   // 'mejora:'.$value
    public function archivoIcono(): string; // reutiliza icono de la baya de origen
}

enum ClaveReductor: string {        // 5
    case ATAQUE='ataque'; case DEFENSA='defensa'; case ATAQUE_ESPECIAL='ataque_especial';
    case DEFENSA_ESPECIAL='defensa_especial'; case VELOCIDAD='velocidad';
    public function clave(): string;   // 'reductor:'.$value
    public function archivoIcono(): string; // reutiliza icono de la baya de origen
}

enum ClaveResistencia: string {     // 18, valores = slug de tipo
    case NORMAL='normal'; case LUCHA='lucha'; case VOLADOR='volador'; case VENENO='veneno';
    case TIERRA='tierra'; case ROCA='roca'; case BICHO='bicho'; case FANTASMA='fantasma';
    case ACERO='acero'; case FUEGO='fuego'; case AGUA='agua'; case PLANTA='planta';
    case ELECTRICO='electrico'; case PSIQUICO='psiquico'; case HIELO='hielo';
    case DRAGON='dragon'; case SINIESTRO='siniestro'; case HADA='hada';
    public function clave(): string;   // 'resistencia:'.$value
    public function archivoIcono(): string; // '/images/type/'.$value.'.webp'
    public function tipo(): TipoPokemon;
    public static function desdeTipo(TipoPokemon $tipo): self;
}
```

`CatalogoItems` (modificación):

- `construir()` añade los 5 bloques anteriores con:
  - Pociones, mejoras, reductores, resistencias: `equipable: false`, `usableEnCombate: true`, `consumible: true`.
  - Material recipiente: todas las banderas `false`.
- `aPartirDeItemKey()` añade un mapa prefijo→enum para `pocion:`, `material:`, `mejora:`, `reductor:`, `resistencia:` y resuelve con `tryFrom` + `definicion()`. Mantiene `baya:`/`bonguri:` (`CatalogoItems.php:99-118`).
- Nuevo `objetosDeCombate(): DefinicionItemCollection`: filtra `usableEnCombate === true` y categoría distinta de `BAYA` (las bayas base siguen siendo equipables, no usables desde el panel de F5; decisión de alcance).
- `ItemCatalogo::resolve()` no necesita cambios: ya delega en `aPartirDeItemKey()` (`app/Support/ItemCatalogo.php:85-117`), por lo que las claves nuevas dejan de caer en “Desconocido”.

**Iconos** (no bloqueantes):
- Resistencias: `/images/type/{slug}.webp` (existen los 18; verificado en `public/images/type`).
- Mejoras/reductores: icono de la baya de origen (documentado como temporal).
- Pociones/recipiente: `/images/misc/unknown.webp` (existe).
- `roseli-berry.webp` no existe; el fallback `unknown` cubre la resistencia Hada hasta la fase de iconos.

#### Dominio de Forja (`src/Forja/Domain`)

```php
final readonly class RecetaFabricacion
{
    public function __construct(
        public string $clave,                 // = resultadoClave (una receta por resultado en MVP)
        public string $resultadoClave,
        public int $resultadoCantidad,        // 1 en MVP
        public MapaConteo $ingredientes,
        public CategoriaItem $categoriaResultado,
    ) {
        // Valida CatalogoItems::existe() para resultado e ingredientes.
        // Lanza ItemDesconocido si alguna clave no existe (evita "Desconocido").
        // Lanza InvalidArgumentException si resultadoCantidad !== 1.
    }
}

final class RecetaFabricacionCollection extends Collection { public string $type = RecetaFabricacion::class; }

final class CatalogoRecetas
{
    public static function existe(string $clave): bool;
    public static function receta(string $clave): RecetaFabricacion;       // RecetaNoExiste 404
    public static function todas(): RecetaFabricacionCollection;
    public static function paraResultado(string $clave): RecetaFabricacionCollection;
}
```

`RecetaNoExiste extends RecursoNoExiste` (`src/Forja/Domain/Exceptions`). El catálogo se construye una vez en estático; los arrays internos son configuración, no contrato.

**Recetas propuestas** (balance inicial, ajustable):

| Resultado | Ingredientes |
|---|---|
| `material:recipiente` | `bonguri:blanco` ×1 |
| `pocion:pocion` | `baya:oran` ×2 + `material:recipiente` ×1 |
| `pocion:super` | `baya:oran` ×3 + `baya:sitrus` ×1 + recipiente |
| `pocion:hiper` | `baya:sitrus` ×3 + `baya:oran` ×2 + recipiente |
| `pocion:maxima` | `baya:sitrus` ×5 + `baya:liechi` ×1 + recipiente |
| `pocion:curatotal` | `baya:cheri` + `baya:pecha` + `baya:rawst` + `baya:aspear` ×1 c/u + recipiente |
| `pocion:restaurar_todo` | `baya:sitrus` ×2 + las 4 bayas de estado ×1 + recipiente |
| `pocion:revivir` | `baya:oran` ×2 + `baya:liechi` ×1 + recipiente |
| `pocion:max_revivir` | `baya:sitrus` ×3 + `baya:liechi` ×2 + recipiente |
| `mejora:{ataque,defensa,ataque_especial,defensa_especial,velocidad,precision,critico}` | baya ×2 + recipiente (`liechi`, `ganlon`, `petaya`, `apicot`, `salac`, `micle`, `lansat`) |
| `reductor:{ataque,defensa,ataque_especial,defensa_especial,velocidad}` | baya ×2 + recipiente (`kelpsy`, `qualot`, `hondew`, `grepa`, `tamato`) |
| `resistencia:{tipo}` | baya de tipo ×1 + recipiente |

Mapa tipo→baya: fuego=occa, agua=passho, eléctrico=wacan, planta=rindo, hielo=yache, lucha=chople, veneno=kebia, tierra=shuca, volador=coba, psíquico=payapa, bicho=tanga, roca=charti, fantasma=kasib, dragón=haban, siniestro=colbur, acero=babiri, normal=chilan, hada=roseli.

#### Aplicación (`src/Forja/App`)

```php
final readonly class IngredienteDetalle {
    public function __construct(
        public string $clave, public string $nombre, public string $icono,
        public int $requerido, public int $disponible,
    ) {}
}
final class IngredientesDetalleCollection extends Collection { public string $type = IngredienteDetalle::class; }

final readonly class RecetaDetalle {
    public function __construct(
        public string $clave, public string $resultadoClave,
        public string $resultadoNombre, public string $resultadoIcono,
        public int $resultadoCantidad,
        public IngredientesDetalleCollection $ingredientes,
        public bool $puedeFabricar, public ?string $motivo,
    ) {}
}
final class RecetaDetalleCollection extends Collection { public string $type = RecetaDetalle::class; }

final readonly class ResultadoFabricacion {
    public function __construct(
        public string $clave, public int $cantidad, public MapaConteo $saldos,
    ) {}
}
```

**`ListarRecetas::handle(int $userId): RecetaDetalleCollection`**

- Reúne todas las claves de ingredientes y resultados de `CatalogoRecetas::todas()`.
- Una sola llamada a `InventarioJugador::saldos($userId, $claves)` (evita N+1).
- `puedeFabricar = every(ingrediente.disponible >= requerido)`; `motivo = 'faltan_materiales'` si no.
- Resuelve nombre/icono con `CatalogoItems::definicion()`.

**`Fabricar::handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion`**

1. `CatalogoRecetas::receta($recetaClave)` → 404 si no existe.
2. Exigir `$cantidad === 1` (MVP) → 422 si no.
3. `DB::transaction`:
   - `InventarioJugador::consumirVarios($userId, $receta->ingredientes, 'Faltan materiales para fabricar')`.
   - `InventarioJugador::incrementar($userId, $receta->resultadoClave, $receta->resultadoCantidad)`.
   - `saldos = $this->inventario->saldos($userId, claves de ingredientes + resultado)`.
4. Devolver `ResultadoFabricacion`.

`consumirVarios` valida **todos** los saldos antes de descontar y bloquea las filas ordenadas por `item_key` (evita deadlocks y saldos negativos). Si falla un ingrediente, la transacción revierte todo.

#### Infraestructura

- `src/Forja/Infra/Controllers/ForjaController.php`:
  - `recetas(): JsonResponse` → `ListarRecetas`.
  - `fabricar(FabricarRequest $request): JsonResponse` → `Fabricar`; responde `{resultado, saldos}`.
- `src/Forja/Infra/Requests/FabricarRequest.php`:
  - `receta`: `required|string|max:64`.
  - `cantidad`: `sometimes|integer|in:1`.
- `routes/forja.php`:

```php
Route::get('/api/forja/recetas', [ForjaController::class, 'recetas']);
Route::post('/api/forja/fabricar', [ForjaController::class, 'fabricar']);
Route::get('/forja', fn () => view('forja.index'));
```

- `routes/web.php`: añadir `require __DIR__.'/forja.php';` dentro del grupo `auth` (`:37-48`).
- Sin migración. Las recetas son código.

#### UI (`resources/views/forja/index.blade.php`)

- Página `/forja` con Alpine.
- Carga `GET /api/forja/recetas` al montar.
- Agrupa por `categoriaResultado` (Pociones, Mejoras, Reductores, Resistencias, Materiales).
- Cada receta: icono/nombre del resultado, lista de ingredientes con `disponible/requerido`, botón “Fabricar” deshabilitado si `!puede_fabricar`, con `motivo`.
- `POST /api/forja/fabricar` con `{receta}`; al éxito, recarga la lista (evita cálculos locales).
- Enlace “Forja” en la navegación o junto al modal de granja (decisión de producto menor; no bloquea).

---

### F5 — Uso de objetos en combate

#### Dominio (`src/Battle/Domain`)

**`AccionObjeto`**

```php
final readonly class AccionObjeto
{
    public function __construct(
        public string $actorId,
        public string $objetivoId,
        public string $clave,
    ) {}
}
```

**`ObjetivosItem`**

```php
enum ObjetivosItem: string
{
    case ALIADO_VIVO = 'aliado_vivo';           // incluye al actor
    case ALIADO_DEBILITADO = 'aliado_debilitado';
    case RIVAL_VIVO = 'rival_vivo';
}
```

Se elimina `ALIADO_O_ACTOR`: “aliado vivo” ya incluye al actor, y un caso menos reduce superficie.

**`EfectoItem` y resultados**

```php
interface EfectoItem
{
    public function clave(): string;
    public function objetivos(): ObjetivosItem;
    public function validar(Combatiente $objetivo): ?string;   // null = aplicable
    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem;
}

final readonly class ResultadoEfectoItem
{
    public function __construct(
        public string $texto,
        public int $curado = 0,
        public bool $revivido = false,
    ) {}
}

final readonly class ResultadoUsoObjeto
{
    public function __construct(
        public bool $exito,
        public ?string $motivo,
        public string $clave,
        public string $actorNombre,
        public string $objetivoNombre,
        public string $log,
        public int $curado = 0,
        public bool $revivido = false,
    ) {}

    public static function fallo(string $clave, string $motivo): self;
    public function logLineas(): StringCollection;
}
```

**Implementaciones de efecto**

| Clase | Comportamiento | Objetivo |
|---|---|---|
| `EfectoCurarHp(float $pct)` | `curarHpCantidad(hpMax * $pct)` (`Combatiente.php:1730-1741`) | `ALIADO_VIVO` |
| `EfectoCurarEstado` | `setEstado(NONE)` + `quitarCondicion(CONFUSION)` + `setContadorVenenoGrave(0)` (`Combatiente.php:1304`, `:730`) | `ALIADO_VIVO` |
| `EfectoRevivir(float $fraccion, bool $restaurarUsos = false)` | `revivir($fraccion)` (`Combatiente.php:574-595`); si `$restaurarUsos`, `restaurarUsos()` | `ALIADO_DEBILITADO` |
| `EfectoRestaurarTodo` | HP 100 % + estado + `restaurarUsos()` + `setMultiplicadores(multiplicadores()->reiniciarTemporales())` | `ALIADO_VIVO` |
| `EfectoModificarStat(StatClave $stat, float $factor)` | `setMultiplicadores(multiplicadores()->refrescarTemporal($stat, $factor))` | `ALIADO_VIVO` o `RIVAL_VIVO` |
| `EfectoMejorarPrecision` | refresca `PRECISION` y `EVASION` a 1.30 | `ALIADO_VIVO` |
| `EfectoMejorarCritico(int $turnos = 5)` | `reemplazarCondicion(new CondicionVolatil(CRITICO_MEJORADO, turnos: 5, origenId: actor))` (`ClaveCondicion.php:65`; expiración `AgregadoBatalla.php:620-631`) | `ALIADO_VIVO` |
| `EfectoResistenciaTipo(TipoPokemon $tipo, float $factor)` | `aplicarResistenciaTemporal($tipo, $factor)` | `ALIADO_VIVO` |

**`CatalogoEfectosItem`**

```php
final class CatalogoEfectosItem
{
    public static function existe(string $clave): bool;
    public static function efecto(string $clave): EfectoItem;  // ItemDesconocido
    public static function todos(): EfectoItemCollection;
}
```

Mapa estático construido desde los enums de items (registro interno, no contrato). No se reutiliza `FabricaEfectos` (es de habilidades/equipados; `BattleEffectServiceProvider.php`).

**`ServicioUsoObjetos`**

```php
final class ServicioUsoObjetos
{
    public function validar(AccionObjeto $accion, AgregadoBatalla $battle): ?string;
    public function aplicar(AccionObjeto $accion, AgregadoBatalla $battle): ResultadoUsoObjeto;
}
```

`validar()`:
- Catálogo: `CatalogoEfectosItem::existe($clave)`.
- Actor y objetivo existen (`team1->findCombatantById` / `team2`, `EquipoBatalla.php:283`).
- Actor vivo.
- Pertenencia: mismo equipo para `ALIADO_*`, distinto para `RIVAL_VIVO`.
- Estado: `ALIADO_VIVO` exige `estaVivo()`; `ALIADO_DEBILITADO` exige `!estaVivo()`; `RIVAL_VIVO` exige `estaVivo()`.
- `efecto->validar($objetivo)` para precondiciones específicas (p. ej. HP lleno en poción).

`aplicar()` construye el log: `«Actor» usó «Item» en «Objetivo».» + texto del efecto`.

**`ResistenciasTemporales`** (`Domain/ValueObjects`)

```php
final class ResistenciasTemporales
{
    public const int TURNOS = 5;

    public static function vacia(): self;
    public static function desdeArray(array $datos): self; // ['factores'=>array<string,float>, 'turnos'=>int]
    public function aplicar(TipoPokemon $tipo, float $factor): self; // refresca, no stackea
    public function factorDe(TipoPokemon $tipo): float;              // 1.0 por defecto
    public function avanzar(): self;                                 // decrementa; limpia a 0
    public function reiniciar(): self;
    public function toArray(): array;
}
```

**`Combatiente`** (modificación):

- Nueva propiedad `private ResistenciasTemporales $resistencias;` inicializada en constructor.
- `revivir()` la reinicia (`Combatiente.php:574-595`).
- `aplicarResistenciaTemporal(TipoPokemon $tipo, float $factor): void`.
- `multiplicadorResistenciaTemporal(AccionBatalla $accion): float`.
- `restaurarUsos(): void { $this->movimientos->reiniciar(); }` (`EstadoMovimientos.php:85-89`).
- `avanzarMultiplicadoresTemporales()` avanza también resistencias (`Combatiente.php:1347`), ya invocado al final de ronda (`AgregadoBatalla.php:466-468`).
- `__serialize()` añade `'resistencias' => $this->resistencias->toArray()`; `__unserialize()` lo restaura con default `ResistenciasTemporales::desdeArray($data['resistencias'] ?? [])`.

**Cadena de daño**

Nuevo `src/Battle/Domain/Chain/ManejadorResistenciaTemporal.php`:

```php
class ManejadorResistenciaTemporal extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        return $daño * $action->defender->multiplicadorResistenciaTemporal($action);
    }
}
```

Se inserta en `CadenaDanio` después de `ManejadorHabilidadesDefensa` y antes de `ManejadorMarcado`. Así la resistencia se aplica aunque el atacante ignore habilidades, y no depende de `CondicionVolatil` (que solo aplica `> 1.0` en `ManejadorMarcado.php:24-28`).

**`MultiplicadoresStats`** (modificación):

```php
public function refrescarTemporal(StatClave $stat, float $factor): self
{
    $temporales = $this->temporales;
    $temporales[$stat->value] = $factor;   // reemplaza, no multiplica
    return new self($this->factores, $this->limiteInferior, $this->limiteSuperior, $temporales, self::TURNOS_TEMPORALES);
}
```

Se usa en `EfectoModificarStat` y `EfectoMejorarPrecision`; evita el stack ×1,3 ×1,3 de `aplicarTemporal()` (`MultiplicadoresStats.php:166-179`).

**`AgregadoBatalla`** (modificación):

```php
private DTOAccionBatalla|DTOAccionObjeto|null $pendingAction = null;

public function pendingAction(): DTOAccionBatalla|DTOAccionObjeto|null;
public function setPendingAction(DTOAccionBatalla|DTOAccionObjeto|null $pendingAction): void;
```

La unión es compatible con payloads v20: la propiedad tipada acepta el `DTOAccionBatalla` ya serializado; los payloads nuevos usan `DTOAccionObjeto`.

**`FaseCombate`**: añadir `case SELECCION_OBJETIVO_ITEM = 'player_item_target';` y actualizar el docblock (`FaseCombate.php:31-38`).

#### Aplicación (`src/Battle/App`)

**`UsarObjetoEnCombate`**

```php
final class UsarObjetoEnCombate
{
    public function __construct(
        private readonly ServicioUsoObjetos $servicio,
        private readonly InventarioJugador $inventario,
    ) {}

    public function handle(int $userId, AgregadoBatalla $battle, AccionObjeto $accion): ResultadoUsoObjeto;
}
```

Orden obligatorio:

1. `validar()`: si falla, devolver `ResultadoUsoObjeto::fallo` **sin consumir**.
2. `DB::transaction(fn () => $this->inventario->consumir($userId, $accion->clave, 1, 'No te queda ese objeto'))`; capturar `StockInsuficiente` y devolver fallo.
3. `aplicar()` en memoria.
4. El llamador guarda la sesión inmediatamente después.

Se documenta que si el guardado de sesión falla tras consumir, se pierde el objeto; el orden “validar → consumir → aplicar → guardar” evita regalar objetos y evita aplicar efectos no persistidos.

**`ObtenerOpcionesObjeto`**

```php
public function handle(int $userId, AgregadoBatalla $battle, Combatiente $actor): OpcionObjetoCollection;
```

- Itera `CatalogoItems::objetosDeCombate()`.
- `cantidad` con una sola llamada a `InventarioJugador::saldos($userId, claves)`.
- `aplicable`: `cantidad > 0` y existe al menos un objetivo válido para `ObjetivosItem`.
- `motivo`: `'sin_stock'` o `'sin_objetivo'`.
- `OpcionObjeto` y `OpcionObjetoCollection` en `src/Battle/Domain/DataTransferObjects` y `Domain/Collections` (para que App no dependa de Presentation).

#### Infraestructura / Presentación

**`DTOAccionObjeto`** (`src/Battle/Presentation`):

```php
final class DTOAccionObjeto implements Wireable
{
    public function __construct(
        public readonly string $actorId,
        public readonly string $defenderId,
        public readonly string $itemKey,
        public readonly string $attackerNombre = '',
        public readonly string $itemNombre = '',
    ) {}
    public function toLivewire(): array;
    public static function fromLivewire($value): static;
}
```

**`Combate`** (modificación):

- Propiedades públicas nuevas:
  - `public string $objetoSeleccionado = '';`
  - `public string $objetivosItem = '';`
  - `public array $objetosCombate = [];`
- `boot()`: `$this->usarObjeto ??= app(UsarObjetoEnCombate::class); $this->opcionesObjeto ??= app(ObtenerOpcionesObjeto::class); $this->inventario ??= app(InventarioJugador::class);`
- `syncViewData($battle, $actor)`: si `$actor` es del equipo 1, `$this->objetosCombate = ObjetosCombatePresenter::toView($this->opcionesObjeto->handle($this->userId(), $battle, $actor));`.
- `userId()` privado: `(int) auth()->id()`; si 0, no se ofrecen objetos.
- `seleccionarObjeto(string $clave): void` (nuevo):
  - Solo en `player_target`, sin `processing` ni animación (`:1001-1025` como referencia de guardas).
  - Valida `CatalogoEfectosItem::existe` + `usableEnCombate` + `saldo >= 1`.
  - Guarda `objetoSeleccionado`, `objetivosItem = efecto->objetivos()->value`, `phase = player_item_target`, `syncViewData`, guarda sesión.
- `cancelarObjeto(): void`: vuelve a `player_target` y limpia `objetoSeleccionado`/`objetivosItem`.
- `previewTarget()`: al inicio, si `phase === player_item_target`, delega en `confirmarObjetivoItem($battle, $teamIdx, $pokemonIdx)` y retorna. Si no, mantiene el flujo actual (validación de vivo y `player_move`).
- `confirmarObjetivoItem()`:
  - Resuelve `$target` (permite debilitado en esta fase).
  - `$accion = new AccionObjeto($actor->id(), $target->id(), $this->objetoSeleccionado)`.
  - `$motivo = $this->usarObjeto->validar(...)`; si no null, log y retorno sin consumir.
  - `$battle->setPendingAction(new DTOAccionObjeto(...))`.
  - `$this->setAnimStateObjeto($actor, $target, $itemNombre)` (mismo patrón que `setAnimState`, pero sin `MovimientoBatalla`; incrementa `animTick`).
  - `syncViewData`, guarda sesión. Alpine dispara `commitAction()` por el cambio de `animTick` (`combate.blade.php:95-100`).
- `resolverAccion()`: al inicio, `if ($pending instanceof DTOAccionObjeto) { $this->resolverAccionObjeto($battle, $pending); return; }`. El resto del método no cambia.
- `resolverAccionObjeto()`:
  - Resuelve actor; si no existe, limpia y `nextActor()`.
  - `$resultado = $this->usarObjeto->handle(...)`.
  - Añade `$resultado->logLineas()` a `$this->log`.
  - Si `exito`: `turnManager()->consumeAction($actor)`; `$actor->effects()->dispararFinTurno($actor, $battle)`; `$battle->procesarDebilitamientos()`.
  - Limpia `pendingAction`, animación, `objetoSeleccionado`, `objetivosItem`; `syncViewData`; guarda; `nextActor()`.
  - **No** llama a `consumirMovimiento()` ni a la cadena de daño.
- `pasarTurno()`/`retrasarTurno()`/`swapTurno()`: incluir `player_item_target` en las fases permitidas para no bloquear al jugador; `pasarTurno` consume acción y limpia el estado de objeto.

**`BatallaVistaPresenter`** (modificación):

- `toView(..., string $objetivosItem = '')`.
- `marcarInteraccion(..., string $objetivosItem = '')`:
  - Si `$objetivosItem === 'aliado_debilitado'`: seleccionable `teamIdx === 0 && !alive`.
  - Si `$objetivosItem === 'aliado_vivo'`: seleccionable `teamIdx === 0 && alive`.
  - Si `$objetivosItem === 'rival_vivo'` o vacío: comportamiento actual (`teamIdx === 1 && alive && canTarget`) (`BatallaVistaPresenter.php:102-121`).
- `turnBar` sigue considerando `player_item_target` como fase de selección.

**UI**:

- `resources/views/livewire/partials/items-panel.blade.php`:
  - En `player_target`: lista de `$objetosCombate`; cada opción con icono, nombre, cantidad, `wire:click="seleccionarObjeto('{{ $o['clave'] }}')"`, deshabilitada si `!aplicable` y `title` con `motivo`.
  - En `player_item_target`: “Usando {{ nombre }}” + botón “Cancelar” (`wire:click="cancelarObjeto"`).
- `moves-panel.blade.php`: incluir el partial en la rama `player_target` (hoy vacía, `:122-123`) y en `player_item_target`.
- `_pokemon-card.blade.php`: sin cambios; sigue usando `selectable` (`:1-4`).
- El log muestra “X usó Poción en Y” desde `ResultadoUsoObjeto->logLineas()`.

#### Sesión

- `BattleSessionService::SESSION_VERSION = 21` (`:47`).
- `MINIMUM_COMPATIBLE_SESSION_VERSION` se mantiene en 9 (`:60`).
- Documentar en el docblock: v20 sigue cargando porque `resistencias` tiene default vacío y `pendingAction` nunca contiene `DTOAccionObjeto` en payloads v20. No se destruyen batallas vivas.
- Si se hiciera rollback de código con payloads v21 que contengan `DTOAccionObjeto`, el `unserialize` fallaría porque la clase no existiría; se documenta que el despliegue debe ser hacia delante (o mantener la clase durante la ventana de rollback).

---

## 3) Contratos y firmas

### Puerto de inventario

```php
namespace Src\Shared\Domain\Ports;

use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\ValueObjects\MapaConteo;

interface InventarioJugador
{
    public function saldo(int $userId, string $clave): int;

    /** @throws \Src\Shared\Domain\Exceptions\ItemDesconocido */
    public function saldos(int $userId, StringCollection $claves): MapaConteo;

    /** @throws ItemDesconocido|StockInsuficiente */
    public function consumir(int $userId, string $clave, int $cantidad, ?string $mensajeError = null): void;

    /** @throws ItemDesconocido|StockInsuficiente */
    public function consumirVarios(int $userId, MapaConteo $claves, ?string $mensajeError = null): void;

    /** @throws ItemDesconocido */
    public function incrementar(int $userId, string $clave, int $cantidad): void;
}
```

**Respuesta a la pregunta 1**: puerto compartido, no ampliación de `InventarioGranja`.

- `InventarioGranja` queda como fachada de caramelos: `resumen()` sigue leyendo `familia:*`/`ev:*`; `consumirFamilia`/`consumirEv` delegan en el puerto.
- El adaptador único `InventarioJugadorEloquent`:
  - `saldo/saldos`: lectura con `PlayerInventory::withoutUserScope()->where('user_id', $userId)`.
  - `consumir/consumirVarios`: `lockForUpdate` y validación `cantidad >= requerida` antes de `decrement`.
  - `incrementar`: `firstOrCreate(...)->increment('cantidad', $n)`.
  - Toda clave pasa por `CatalogoItems::existe()`; si no, `ItemDesconocido` (422).
- `StockInsuficiente extends ViolacionReglaNegocio` (422), con factory `para(string $clave, int $requerido, int $disponible, ?string $mensaje = null)`.
- Binding en `AppServiceProvider::register()` (`app/Providers/AppServiceProvider.php:44-60`).

### F2

```php
PlantarBaya::handle(int $userId, int $habitatId, int $evolutionChainId, int $slot, string $berryKey): void
CosecharPlantacion::handle(int $userId, int $habitatId, int $evolutionChainId, int $slot): int
ObtenerGranja::handle(int $userId, int $habitatId): GranjaDetalle   // firma actual, contrato ampliado
```

### F3

```php
ConsultorPlantaciones::factoresPara(int $userId, int $habitatId): MapaFactores
EsDelHabitat::handle(int $pokemonId, int $habitatId): bool
CalculadorBotinExploracion::calcular(ContextoBotin $contexto): RecompensaItemCollection  // firma actual
```

### F4

```php
CatalogoRecetas::receta(string $clave): RecetaFabricacion
CatalogoRecetas::todas(): RecetaFabricacionCollection
ListarRecetas::handle(int $userId): RecetaDetalleCollection
Fabricar::handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion
```

### F5

```php
ServicioUsoObjetos::validar(AccionObjeto $accion, AgregadoBatalla $battle): ?string
ServicioUsoObjetos::aplicar(AccionObjeto $accion, AgregadoBatalla $battle): ResultadoUsoObjeto
UsarObjetoEnCombate::handle(int $userId, AgregadoBatalla $battle, AccionObjeto $accion): ResultadoUsoObjeto
ObtenerOpcionesObjeto::handle(int $userId, AgregadoBatalla $battle, Combatiente $actor): OpcionObjetoCollection
Combatiente::restaurarUsos(): void
Combatiente::aplicarResistenciaTemporal(TipoPokemon $tipo, float $factor): void
Combatiente::multiplicadorResistenciaTemporal(AccionBatalla $accion): float
MultiplicadoresStats::refrescarTemporal(StatClave $stat, float $factor): self
AgregadoBatalla::setPendingAction(DTOAccionBatalla|DTOAccionObjeto|null $pendingAction): void
```

---

## 4) Migración y datos

### Migración F2

```php
// database/migrations/2026_11_04_000003_create_nest_plantings_table.php
Schema::create('nest_plantings', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('nest_id')->constrained('nests')->cascadeOnDelete();
    $table->unsignedSmallInteger('slot_index');
    $table->string('berry_key', 32);
    $table->timestamp('planted_at');
    $table->unsignedSmallInteger('harvest_count')->default(0);
    $table->timestamps();

    $table->unique(['nest_id', 'slot_index']);
    $table->index('berry_key');
});
```

- `berry_key` 32 es suficiente: la clave real más larga es `baya:defensa_especial` (inexistente) y las reales ≤ 16; las claves de resistencia (F4) no se plantan.
- `slot_index` no se valida en BD contra `nests.level` (expresión mutua); se valida en App.
- Sin FK a catálogo, igual que `player_inventory` (`2026_08_29_000007:15-23`).
- `down()`: `dropIfExists('nest_plantings')`.

### Índices y constraints

| Índice/constraint | Motivo |
|---|---|
| `nest_plantings UNIQUE (nest_id, slot_index)` | Evita doble plantación concurrente; cubre además las búsquedas por `nest_id`. |
| `nest_plantings INDEX (berry_key)` | Agregación `GROUP BY berry_key` del bonus de plantación. |
| `nests UNIQUE (farm_id, evolution_chain_id)` | Ya existe (`create_farm_tables.php:32`); se usa para resolver el nido. |
| `player_inventory UNIQUE (user_id, item_key)` | Ya existe (`2026_08_29_000007:22`); soporta `increment` atómico y evita duplicados. |
| `pokemon_habitat UNIQUE (pokemon_id, habitat_id)` | Ya existe (`2026_02_24_201538:20`); base de `EsDelHabitat`. |

### Transacciones

- **F2**: `DB::transaction` + `lockForUpdate` sobre `nests` (serializa el nido) y sobre `nest_plantings` (serializa el hueco). Consumo de inventario y escritura en la misma transacción.
- **F3**: solo lecturas; la finalización ya corre dentro del `CommandBus`/`UnitOfWork` transaccional.
- **F4**: `DB::transaction` + `consumirVarios` con lock ordenado por `item_key` + `incrementar`.
- **F5**: transacción **solo** para el decremento de inventario; el estado de batalla vive en sesión y se guarda después de aplicar.

### Sin migración

- F4: recetas en código.
- F5: sin tablas nuevas; solo sesión (`SESSION_VERSION` 21) y nuevas propiedades serializadas con default.

---

## 5) Orden de implementación y paralelismo

| Orden | Bloque | Archivos propios | Depende de |
|---|---|---|---|
| **0** | Base de inventario compartido | `src/Shared/Domain/Ports/InventarioJugador.php`, `src/Shared/Domain/Exceptions/StockInsuficiente.php`, `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`, `app/Providers/AppServiceProvider.php` (binding), `src/Granjas/App/InventarioGranja.php` | F1 |
| **1** | F2 Granjas | `src/Granjas/**`, `app/Models/PlantacionNido.php`, `app/Models/Nido.php` (relación), migración, `routes/granjas.php`, `GranjaController`, `PlantarBayaRequest`, `resources/views/habitats/show.blade.php` | Bloque 0 |
| **2** | F4 Forja (paralelo con F2) | `src/Shared/Domain/Items/*` (enums + `CatalogoItems`), `src/Forja/**`, `routes/forja.php`, `routes/web.php` (require), `resources/views/forja/index.blade.php` | Bloque 0 |
| **3** | F3 Exploraciones | `src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php`, `src/Exploraciones/Domain/ContextoBotin.php`, `CalculadorBotinExploracion.php`, `src/Exploraciones/App/EsDelHabitat.php`, `ProcesarExploracionHandler.php`, `FinalizarExploracionHandler.php`, `TransformadorResultadoExploracion.php`, `PresentadorExploraciones.php`, `ResultadoExploracionVista.php`, `HitoExploracion.php`, `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`, binding, `resources/views/exploraciones/index.blade.php` | F2 (tabla) + Bloque 0 |
| **4** | F5 Combate | `src/Battle/Domain/**` (AccionObjeto, enums, efectos, catálogo, resistencias, cadena, Combatiente, AgregadoBatalla, FaseCombate), `src/Battle/Presentation/DTOAccionObjeto.php`, `src/Battle/App/UsarObjetoEnCombate.php`, `ObtenerOpcionesObjeto.php`, `app/Livewire/Combate.php`, `BatallaVistaPresenter.php`, partials Blade, `app/Support/BattleSessionService.php` | F4 (catálogo/efectos) + Bloque 0 |

**Paralelismo permitido**:
- F2 y F4 sí, tras el bloque 0: F2 no edita `src/Shared/Domain/Items` (solo lee `ClaveBaya`), F4 no edita `src/Granjas`.
- F3 no puede empezar antes de que exista `nest_plantings` (F2) porque su adaptador consulta la tabla.
- F5 no puede empezar antes de que F4 haya añadido los enums y `CatalogoItems`, porque `CatalogoEfectosItem` referencia esas claves.

**Archivos que deben secuenciarse sí o sí**:
- `app/Providers/AppServiceProvider.php`: bloque 0 (inventario) y F3 (plantaciones). No editar en paralelo.
- `src/Shared/Domain/Items/CatalogoItems.php`: solo F4.
- `src/Exploraciones/App/FinalizarExploracionHandler.php` / `ProcesarExploracionHandler.php`: solo F3.
- `app/Livewire/Combate.php`, `AgregadoBatalla.php`, `Combatiente.php`, `BattleSessionService.php`: solo F5.
- `resources/views/habitats/show.blade.php`: solo F2.
- `routes/web.php`: solo F4 (require de forja).

---

## 6) Criterios de aceptación

### F2

- [ ] Un nido de nivel N ofrece exactamente `N*2` huecos; nivel 0 no permite plantar.
- [ ] Plantar fuera de rango o sin nivel ≥ 1 devuelve 422 sin cambios.
- [ ] Plantar consume exactamente 1 baya; sin saldo devuelve 422 y no crea/actualiza la plantación.
- [ ] La clave de baya se valida contra `ClaveBaya`; una clave desconocida nunca llega a `player_inventory`.
- [ ] Dos peticiones concurrentes al mismo `(nest_id, slot)` no crean dos filas (unique + lock).
- [ ] Cosechar en 24/48/72 h devuelve 1/2/3 unidades, con tope 3.
- [ ] Cosechar dos veces seguidas no duplica: la segunda devuelve 422 (stock 0).
- [ ] La planta persiste tras cosechar y vuelve a producir al día siguiente.
- [ ] Replantar un hueco ocupado sustituye la baya, reinicia `planted_at` y `harvest_count`, y la UI avisa.
- [ ] La granja devuelve `huecos_totales`, `huecos_libres`, `plantaciones`, `bayas` y `bonguri`; la UI no calcula stock/progreso/estado.
- [ ] Un usuario no puede plantar ni cosechar en nidos de otra granja (siempre se resuelve `AsegurarGranja` y `farm_id`).
- [ ] `GranjaDetalle` conserva `id`, `habitat`, `familias`, `inventario` para no romper el contrato actual.

### F3

- [ ] Un explorador cuya especie está en `pokemon_habitat` para el hábitat recibe el bonus; uno no nativo no.
- [ ] El flag `eventos['bonos']['del_habitat']` se calcula una sola vez y se reutiliza al finalizar.
- [ ] Si una expedición se finaliza sin tick previo, el fallback calcula el flag una vez.
- [ ] Los factores de plantación solo modifican pesos de bayas de `(user_id, habitat_id)`; no afectan a bonguris ni a otras granjas/hábitats.
- [ ] El factor por baya es `min(2.0, 1 + 0.20 * plantas)`; sin plantaciones, `MapaFactores` vacío y botín idéntico a F1.
- [ ] El resultado persistido incluye `bonos.del_habitat` y la UI muestra el hito/badge sin cálculos en JS.
- [ ] No se añade auto-cosecha.

### F4

- [ ] Cada receta consume exactamente sus ingredientes y produce exactamente 1 unidad.
- [ ] Si falta un ingrediente, devuelve 422 y **no** descuenta nada (transacción).
- [ ] Dos peticiones concurrentes no dejan saldos negativos (lock ordenado + validación previa).
- [ ] `ListarRecetas` devuelve `puede_fabricar` correcto y una sola consulta de saldos.
- [ ] Receta inexistente → 404 (`RecetaNoExiste`).
- [ ] `cantidad !== 1` → 422.
- [ ] Toda clave de ingrediente/resultado existe en `CatalogoItems`; `ItemCatalogo::resolve()` ya no devuelve “Desconocido” para `pocion:*`, `material:*`, `mejora:*`, `reductor:*`, `resistencia:*`.
- [ ] `src/Forja/Domain` no importa Eloquent/HTTP/Blade.
- [ ] Los iconos faltantes usan el fallback documentado; no bloquean la fabricación.

### F5

- [ ] Usar un objeto consume la acción y **no** consume movimiento.
- [ ] Usar un objeto descuenta 1 del inventario; sin stock no se ofrece y, si se fuerza, no se ejecuta ni consume acción.
- [ ] Poción/curar solo a aliados vivos (incluido el actor); revivir solo a aliados debilitados; reductor solo a rivales vivos.
- [ ] La UI permite seleccionar aliados y rivales según el objeto, incluidos los debilitados para revivir.
- [ ] Buff +30 % dura 5 rondas y se retira; debuff −30 % ídem; reaplicar refresca sin stackear.
- [ ] Resistencia de tipo −30 % durante 5 rondas y se aplica a cualquier ataque de ese tipo, aunque el atacante ignore habilidades.
- [ ] Crítico +1 nivel durante 5 rondas (`CRITICO_MEJORADO`), con expiración existente.
- [ ] Restaurar Todo y Max Revivir reinician usos (`restaurarUsos()`).
- [ ] El log registra “X usó Item en Y” y el efecto.
- [ ] No hay regresiones en ruta/entrenador/gimnasio/mazmorra/exploración: `resolverAccion()` mantiene la rama de movimiento y solo añade `instanceof DTOAccionObjeto`.
- [ ] Payloads v20 siguen cargando (mínimo 9) y `resistencias` se inicializa vacío; no se fuerza el descarte.

---

## 7) Riesgos y mitigaciones

| Nivel | Riesgo | Mitigación |
|---|---|---|
| **Alto** | F5 toca el núcleo del turno (`resolverAccion`, `pendingAction`, fases) | DTO separado + `instanceof` + rama aislada; no se modifica la cadena de daño salvo un manejador no-op; revisión manual de los 4 contextos. |
| **Alto** | Consumo de inventario en combate no transaccional con la sesión | Orden “validar → consumir → aplicar → guardar”; decremento condicional; se documenta la pérdida posible si falla el guardado. |
| **Alto** | F4/F5 introducen claves que `ItemCatalogo` no resuelve | `CatalogoItems` es la única fuente; `RecetaFabricacion` valida ingredientes/resultado; `ItemCatalogo` ya delega (`ItemCatalogo.php:85`). Criterio de conteo 82 claves. |
| **Alto** | Doble plantación/cosecha | Unique `(nest_id, slot_index)`, `lockForUpdate` del nido y del hueco, recálculo de stock tras el lock. |
| **Medio** | F3 aplica factores a bonguris u otras granjas | Factores solo en `pesosBayas()`; consulta filtrada por `(user_id, habitat_id)`; `caeBonguri()` no recibe factores. |
| **Medio** | `delHabitat` ambiguo | Se fija al explorador (`reclutado->pokemon_id`), se persiste una vez y se muestra como “Conoce el terreno”. |
| **Medio** | Buffs acumulables | `refrescarTemporal()` reemplaza el factor temporal; no usa `aplicarTemporal()`. |
| **Medio** | `SESSION_VERSION` no invalida v20 | Decisión explícita: se mantiene mínimo 9 y se documenta que v20 carga con defaults; no se destruyen batallas. |
| **Medio** | Replantar pierde stock | Decisión de producto; la UI muestra `confirm()` y el backend reinicia `harvest_count`. |
| **Medio** | Doble fabricación por doble clic | Locks evitan saldos negativos, no duplicados de intención; UI deshabilita el botón; se documenta ausencia de `Idempotency-Key`. |
| **Bajo** | Iconos nuevos ausentes | Fallback `unknown.webp` y reutilización de iconos de tipo/baya; fase de iconos posterior. |
| **Bajo** | Payload de granja grande | Ya ocurre hoy (`GranjaController.php:55-60`); se acepta en MVP; las plantaciones añaden como máximo 10 entradas por familia. |
| **Bajo** | Rollback con payloads v21 que contengan `DTOAccionObjeto` | Despliegue hacia delante o mantener la clase durante la ventana de rollback; documentado. |

---

## 8) Verificación estática

Sin ejecutar tests, migraciones ni seeders:

```bash
vendor/bin/phpstan analyse --memory-limit=1G          # nivel 6, src/ y app/ (phpstan.neon)
vendor/bin/pint --dirty --format agent
vendor/bin/deptrac analyse                            # Domain no depende de otros módulos
php -l <cada archivo PHP nuevo/modificado>
php artisan route:list --path=forja
php artisan route:list --path=farm
php artisan view:cache && php artisan view:clear
grep -rn "array<string" src/Granjas src/Forja src/Battle   # detectar arrays como contrato
grep -rn "CatalogoItems::existe" src/Shared src/Forja src/Granjas src/Battle
git diff --stat                                            # confirmar que tests/ no se toca
```

Comprobaciones de contrato:
- `CatalogoItems::definiciones()` debe devolver 82 claves (36 bayas + 7 bonguris + 8 pociones + 1 recipiente + 7 mejoras + 5 reductores + 18 resistencias). Verificación de solo lectura con `php -r` o `php artisan tinker --execute` (sin escritura).
- `grep -rn "delHabitat: false" src/Exploraciones` no debe quedar en el flujo final.
- `grep -rn "DTOAccionBatalla" app/Livewire/Combate.php src/Battle/Domain/AgregadoBatalla.php` debe mostrar la unión/`instanceof`.
- `grep -rn "SESSION_VERSION\|MINIMUM_COMPATIBLE" app/Support/BattleSessionService.php` debe mostrar 21/9.
- Revisión manual de los contratos `GranjaDetalle`, `NidoGranja`, `ContextoBotin`, `DTOAccionBatalla`/`DTOAccionObjeto`, `ResultadoRecompensas`.

---

## 9) Archivos inspeccionados

- Artefactos: `.opencode/work/TASK-012/analysis.md`, `.opencode/work/TASK-011/architecture.md`, `.ai/rules/index.md`, `docs/ddd.md`, `deptrac.yaml`, `bootstrap/app.php`, `bootstrap/providers.php`, `routes/web.php`.
- Granjas: `src/Granjas/App/{AsegurarGranja,FamiliaEnHabitat,InventarioGranja,MejorarNido,ObtenerGranja,ResolverBonosFamilia}.php`; `src/Granjas/Domain/{CalculadoraBonosGranja.php,DataTransferObjects/{GranjaDetalle,NidoGranja,FamiliaGranja,HabitatGranja,InventarioCaramelosGranja}.php}`; `src/Granjas/Infra/Controllers/GranjaController.php`; `app/Models/{Granja,Nido,Habitat,PlayerInventory}.php`; `database/migrations/2026_10_26_000001_create_farm_tables.php`; `routes/granjas.php`.
- Catálogo/inventario: `src/Shared/Domain/Items/{CatalogoItems,CategoriaItem,ClaveBaya,ClaveBonguri,DefinicionItem}.php`; `app/Support/ItemCatalogo.php`; `src/Shared/Domain/{Collection.php,ValueObjects/MapaConteo.php,ValueObjects/MapaFactores.php,Exceptions/*}`; `src/Shared/Collections/StringCollection.php`; `database/migrations/2026_08_29_000007_create_player_inventory_table.php`.
- Exploraciones: `src/Exploraciones/Domain/{ContextoBotin,CalculadorBotinExploracion,PerfilExploracion,ModoExploracion}.php`; `src/Exploraciones/Domain/Recompensas/{RecompensaItem,RecompensaItemCollection,ResultadoRecompensas}.php`; `src/Exploraciones/App/{FinalizarExploracionHandler,ProcesarExploracionHandler,PersistirRecompensas}.php`; `src/Exploraciones/Presentation/{TransformadorResultadoExploracion,PresentadorExploraciones,ResultadoExploracionVista,HitoExploracion}.php`; `app/Models/{ExploracionActiva,Reclutado}.php`; `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php`.
- Combate: `src/Battle/Domain/{AgregadoBatalla,Combatiente,GestorTurnos,EquipoBatalla}.php`; `src/Battle/Domain/ValueObjects/{MultiplicadoresStats,CondicionVolatil,EstadoMovimientos}.php`; `src/Battle/Domain/Enums/{FaseCombate,ClaveCondicion,StatClave,EstadoPokemon}.php`; `src/Battle/Domain/Chain/{CadenaDanio,ManejadorHabilidadesDefensa}.php`; `src/Battle/Domain/Effects/{ColeccionEfectos,Equipo/EfectoEquipoResistenciaTipo}.php`; `src/Battle/Domain/Catalog/CatalogoObjetos.php`; `src/Battle/Presentation/DTOAccionBatalla.php`; `app/Support/BattleSessionService.php`; `app/Livewire/Combate.php`; `app/Livewire/Presenters/BatallaVistaPresenter.php`; `resources/views/livewire/{combate.blade.php,partials/moves-panel.blade.php,_pokemon-card.blade.php}`; `app/Providers/AppServiceProvider.php`.
- UI granja/exploraciones: `resources/views/habitats/show.blade.php` (modal de granja y JS `accionGranja`), `resources/views/exploraciones/index.blade.php`.
- Assets: `public/images/{misc,type,bonguri,berry}` (existencia de `unknown.webp`, 18 tipos, 7 bonguris; ausencia de `roseli-berry.webp`).
- Verificación de estado: `git status --short`, `git branch --show-current` (`task-011`).

**No se modificó ningún archivo, no se ejecutaron tests, migraciones ni seeders, y no se leyó ningún secreto.**

---

## Anexo A — Borrador de ADR

- **Título:** Inventario compartido y diseño de plantaciones, forja y objetos de combate (F2–F5).
- **Estado:** Propuesto.
- **Contexto:** F1 dejó catálogo de bayas/bonguris e inventario unificado, pero sin puerto genérico, sin plantaciones, sin recetas y con un flujo de combate que asume movimiento. F2–F5 necesitan la misma semántica atómica de inventario y una integración de combate de bajo riesgo.
- **Decisión:**
  1. Puerto `InventarioJugador` en `Src\Shared\Domain\Ports` con adaptador único `InventarioJugadorEloquent`.
  2. `nest_plantings` con stock calculado y unique `(nest_id, slot_index)`.
  3. `ConsultorPlantaciones` como puerto de `Exploraciones\Domain` implementado en `Granjas\Infra`; flag de hábitat persistido en `eventos['bonos']`.
  4. `src/Forja` con catálogo estático de recetas y 39 claves nuevas en `Shared\Domain\Items`.
  5. `DTOAccionObjeto` separado, fase `player_item_target`, `ResistenciasTemporales` con manejador propio, `refrescarTemporal()` y `restaurarUsos()`.
  6. `SESSION_VERSION` 21 con mínimo 9; v20 sigue cargando con defaults.
- **Consecuencias:**
  - Positivas: una sola semántica de inventario; F2/F4/F5 no duplican locks; el combate no se contamina con movimientos ficticios; F3 no rompe la dirección de dependencias.
  - Negativas: F5 sigue tocando el núcleo del turno (riesgo alto, mitigado con DTO separado y revisión manual); sin `Idempotency-Key` en forja; iconos nuevos quedan como deuda visual; la resistencia temporal no se refleja en la cadena de habilidades si se optara por el camino descartado (se evita con el manejador propio).

## Anexo B — Preguntas abiertas

1. **Cantidades exactas de las recetas**: la tabla propuesta es un balance inicial; producto debe confirmarla antes de F4.
2. **Magnitud del bonus de hábitat**: +25 % de tiradas de baya y +10 pp de bonguri (tope 85 %) es una propuesta; no está fijada en las decisiones de producto.
3. **Bayas base como objetos de combate**: se excluyen del panel de F5 (solo pociones/mejoras/reductores/resistencias). Confirmar si `baya:oran`/`baya:sitrus` deben poder usarse directamente.
4. **Receta del recipiente**: se propone `bonguri:blanco ×1`; confirmar si el recipiente debe venir de otro botín o de una tienda.
5. **`SESSION_VERSION`**: se propone subir a 21 manteniendo mínimo 9. Si producto prefiere invalidar todas las batallas, habría que subir también el mínimo, con el coste de destruir batallas en curso.
6. **Iconos definitivos**: no bloquean F4/F5; se usa el fallback documentado.
</subagent>
---

# Anexo C — Addendum de corrección F4/F5 (arquitecto)

## Decisiones v1 confirmadas

Se mantienen sin cambios: bonus de hábitat **+25 % tiradas de baya** y **+10 pp bonguri tope 85 %**; `SESSION_VERSION = 21` con mínimo compatible **9**; iconos con fallback documentado; resistencia de tipo a **cualquier ataque de ese tipo**; **1 unidad por fabricación**.

## (a) Catálogo de recetas corregido y contratos ajustados

### Verificación de nombres reales

`ClaveBaya` tiene exactamente **36 casos** y estos nombres (`src/Shared/Domain/Items/ClaveBaya.php:63-100`):

- `CHERI` → **Zreza** (`:66`)
- `PECHA` → **Meloc** (`:67`)
- `RAWST` → **Safre** (`:68`)
- `ASPEAR` → **Perasi** (`:69`)
- `ORAN` → **Aranja** (`:64`), `SITRUS` → **Zidra** (`:65`)

**No existen** en `ClaveBaya` los casos `CHESTO`, `PERSIM`, `LUM` ni `LEPPA`. `lum_berry` y `sitrus_berry` solo existen como claves de objeto equipable (`src/Battle/Domain/Enums/ClaveItem.php:12-13`), no como `baya:*`. No se inventan slugs.

### Árbol exacto de recetas

`RecetaFabricacion::$clave` pasa a ser el **id de receta** (`receta:...`) y **difiere** de `resultadoClave` siempre que haga falta (R1/R2 y las 7 variantes de recipiente).

| Id de receta | Ingredientes (`MapaConteo`) | Resultado |
|---|---|---|
| `receta:pocion_oran` | `baya:oran` ×3 | `pocion:pocion` ×1 |
| `receta:pocion_sitrus` | `baya:sitrus` ×1 | `pocion:pocion` ×1 |
| `receta:super` | `pocion:pocion` ×2 | `pocion:super` ×1 |
| `receta:hiper` | `pocion:super` ×3 | `pocion:hiper` ×1 |
| `receta:maxima` | `pocion:hiper` ×2 | `pocion:maxima` ×1 |
| `receta:restaurar_todo` | `pocion:maxima` ×1 + `pocion:curatotal` ×1 | `pocion:restaurar_todo` ×1 |
| `receta:curatotal` | `baya:cheri` + `baya:pecha` + `baya:rawst` + `baya:aspear` ×1 c/u | `pocion:curatotal` ×1 |
| `receta:revivir` | `pocion:curatotal` ×1 + `pocion:super` ×1 | `pocion:revivir` ×1 |
| `receta:max_revivir` | `pocion:revivir` ×1 + `pocion:restaurar_todo` ×1 | `pocion:max_revivir` ×1 |
| `receta:recipiente:{color}` | `bonguri:{color}` ×1 (7 recetas: blanco, amarillo, negro, verde, rosa, azul, rojo) | `material:recipiente` ×1 |
| `receta:mejora:{stat}` | baya de stat ×1 + `material:recipiente` ×1 | `mejora:{stat}` ×1 |
| `receta:reductor:{stat}` | baya reductora ×1 + `material:recipiente` ×1 | `reductor:{stat}` ×1 |
| `receta:resistencia:{tipo}` | baya de tipo ×1 + `material:recipiente` ×1 | `resistencia:{tipo}` ×1 |

**Mapas exactos** (usando los slugs reales de `ClaveBaya`):

- Mejoras (7): `ataque`=liechi, `defensa`=ganlon, `velocidad`=salac, `ataque_especial`=petaya, `defensa_especial`=apicot, `critico`=lansat, `precision`=micle.
- Reductores (5): `ataque`=kelpsy, `defensa`=qualot, `ataque_especial`=hondew, `defensa_especial`=grepa, `velocidad`=tamato.
- Resistencias (18): normal=chilan, lucha=chople, volador=coba, veneno=kebia, tierra=shuca, roca=charti, bicho=tanga, fantasma=kasib, acero=babiri, fuego=occa, agua=passho, planta=rindo, electrico=wacan, psiquico=payapa, hielo=yache, dragon=haban, siniestro=colbur, hada=roseli.

**Totales:** 46 recetas (9 de pociones + 7 recipiente + 7 mejoras + 5 reductores + 18 resistencias); 39 resultados distintos. `paraResultado('pocion:pocion')` devuelve **2**; `paraResultado('material:recipiente')` devuelve **7**; el resto devuelve 1.

### Cómo se resuelve “cualquier bonguri” sin arrays alternativos

Con `MapaConteo` no se puede expresar “A o B”. Se modelan **7 recetas distintas** con el mismo `resultadoClave`, que es la opción más simple y compatible con el contrato actual:

- `CatalogoRecetas::receta($clave)` busca por **id de receta**.
- `CatalogoRecetas::paraResultado($resultadoClave)` devuelve todas las variantes.
- `CatalogoRecetas::todas()` devuelve la lista plana de 46.
- `CatalogoRecetas::resultados(): StringCollection` devuelve las 39 claves de resultado únicas para que la UI agrupe.

La UI de forja agrupa por `resultado_clave`. Si el grupo tiene más de una variante (poción desde Oran/Zidra; recipiente desde 7 bonguris), muestra un `<select>` con `etiqueta` (“Desde Baya Aranja”, “Desde Baya Zidra”, “Bonguri Blanco”…) y envía el `clave` de la variante elegida. `puede_fabricar` se evalúa **por variante**; el grupo se marca fabricable si al menos una lo hace. No se añaden arrays alternativos ni se cambia `MapaConteo`.

### Contratos ajustados

```php
final readonly class RecetaFabricacion
{
    public function __construct(
        public string $clave,              // 'receta:...' (id único)
        public string $resultadoClave,     // 'pocion:pocion', 'material:recipiente', ...
        public int $resultadoCantidad,     // 1
        public MapaConteo $ingredientes,
        public CategoriaItem $categoriaResultado,
        public string $etiqueta,           // distingue variantes en la UI
    ) {}
}

final class CatalogoRecetas
{
    public static function existe(string $clave): bool;
    public static function receta(string $clave): RecetaFabricacion;                 // RecetaNoExiste 404
    public static function todas(): RecetaFabricacionCollection;                     // 46
    public static function paraResultado(string $resultadoClave): RecetaFabricacionCollection;
    public static function resultados(): StringCollection;                           // 39 claves únicas
}

final class Fabricar
{
    public function handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion;
}

final readonly class ResultadoFabricacion
{
    public function __construct(
        public string $recetaClave,
        public string $resultadoClave,
        public int $cantidad,
        public MapaConteo $saldos,
    ) {}
}
```

`FabricarRequest` valida `receta` como `required|string|max:64` (los ids más largos, `receta:resistencia:defensa_especial` y `receta:recipiente:amarillo`, caben) y `cantidad` como `sometimes|integer|in:1`.

`RecetaDetalle` añade `etiqueta` y mantiene `resultadoClave`; `ListarRecetas` devuelve la colección plana y la UI agrupa por `resultado_clave`.

`RecetaFabricacion` sigue validando en constructor que `resultadoClave` y todas las claves de `ingredientes` existan en `CatalogoItems` (`ItemDesconocido` si no) y que `resultadoCantidad === 1`.

## (b) Bayas directas en F5

### Decisión

**Sí se incluyen las 6 bayas básicas que ya existen en `ClaveBaya`** (`BAYAS_BASE`, `CatalogoItems.php:21-33`): `oran`, `sitrus`, `cheri`, `pecha`, `rawst`, `aspear`.

**No se incluyen `chesto`, `persim`, `lum` ni `leppa` en v1** porque no existen como `ClaveBaya`; añadirlas exigiría ampliar el catálogo F1 (36→40), `nombre()`, iconos y, en el caso de `leppa`, un modelo de PP por movimiento que hoy no existe (`EstadoMovimientos::reiniciar()` reinicia todo, no una cantidad). `lum` además ya existe como objeto equipado (`lum_berry`) y no debe duplicarse. Se marca deuda explícita.

### Efectos y claves

| Clave de inventario | Efecto | Objetivo |
|---|---|---|
| `baya:oran` | `EfectoCurarHp(0.10)` | `ALIADO_VIVO` |
| `baya:sitrus` | `EfectoCurarHp(0.25)` | `ALIADO_VIVO` |
| `baya:cheri` | `EfectoCurarEstado(EstadoPokemon::PARALYSIS)` | `ALIADO_VIVO` |
| `baya:pecha` | `EfectoCurarEstado(EstadoPokemon::POISON, EstadoPokemon::BAD_POISON)` | `ALIADO_VIVO` |
| `baya:rawst` | `EfectoCurarEstado(EstadoPokemon::BURN)` | `ALIADO_VIVO` |
| `baya:aspear` | `EfectoCurarEstado(EstadoPokemon::FREEZE)` | `ALIADO_VIVO` |

`sitrus` usa el mismo 25 % que la baya equipada (`EfectoBayaSitrus.php:45`), pero por una vía distinta: la equipada es automática y consume carga del objeto; la directa es manual y consume 1 unidad de `player_inventory` con clave `baya:sitrus`.

`EfectoCurarEstado` se diseña con estados variables:

```php
final class EfectoCurarEstado implements EfectoItem
{
    public function __construct(private readonly EstadoPokemon ...$estados) {}
    public function validar(Combatiente $objetivo): ?string; // null si estado en $estados
    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem;
}
```

Al aplicar: `setEstado(NONE)`, `setContadorVenenoGrave(0)` y `quitarCondicion(CONFUSION)` solo si el estado curado es confusión (no aplica a estas 6).

`EfectoCurarHp::validar()` añade la comprobación de que el objetivo esté vivo y con HP < máximo; si no, devuelve motivo y no se consume.

### `CatalogoItems::objetosDeCombate()`

Pasa a ser **todos los items con `usableEnCombate === true`**, no solo los fabricados:

- Incluye las 6 `BAYAS_BASE` (ya tienen `usableEnCombate: true` en `CatalogoItems::construir()`, `:136`) y los 38 objetos usables de F4 (8 pociones + 7 mejoras + 5 reductores + 18 resistencias).
- Excluye `material:recipiente` y las bayas de stat/tipo (tienen `usableEnCombate: false`).
- Total del panel: **44 opciones**.
- `ObtenerOpcionesObjeto` añade `categoria` a `OpcionObjeto` para que la UI agrupe (Bayas / Pociones / Mejoras / Reductores / Resistencias) sin lógica de negocio.

### No duplicación con equipadas

- `sitrus_berry` y `lum_berry` siguen viviendo en `ClaveItem`/`CatalogoObjetos` y no aparecen en `objetosDeCombate()` porque `CatalogoItems::existe('sitrus_berry')` es `false`.
- `baya:sitrus` es inventario y manual; `sitrus_berry` es equipado y automático. Se documenta y se etiqueta en la UI (“Baya Zidra (inventario)” frente a “Baya Sitrus (equipada)”).
- No se añade `baya:lum` en v1.

### Deuda registrada

- `chesto` (sueño), `persim` (confusión), `lum` (todos los estados) y `leppa` (PP) quedan fuera de F5 v1. Los assets existen en `public/images/berry/` (`chesto-berry.webp`, `persim-berry.webp`, `lum-berry.webp`, `leppa-berry.webp`), por lo que la ampliación futura es de bajo coste, pero requiere nuevos casos de `ClaveBaya` y, para `leppa`, un modelo de PP por movimiento.

## (c) Impacto en archivos y criterios de aceptación

### Archivos afectados por la corrección

| Archivo | Cambio |
|---|---|
| `src/Forja/Domain/RecetaFabricacion.php` | `clave` = id de receta; nuevo `etiqueta`; validación de unicidad en catálogo |
| `src/Forja/Domain/CatalogoRecetas.php` | 46 recetas con ids `receta:*`; `paraResultado()` multi-variante; `resultados()` |
| `src/Forja/App/ListarRecetas.php` | Devuelve 46 detalles; agrupación es de UI |
| `src/Forja/App/Fabricar.php` | Recibe `recetaClave`, no `resultadoClave`; `ResultadoFabricacion` con ambos |
| `src/Forja/Infra/Requests/FabricarRequest.php` | `receta` max 64; `cantidad in:1` |
| `resources/views/forja/index.blade.php` | Agrupa por `resultado_clave`; selector de variantes si hay >1; `puede_fabricar` por variante |
| `src/Battle/Domain/UsoObjetos/CatalogoEfectosItem.php` | +6 entradas `baya:*`; `EfectoCurarEstado` multi-estado |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoCurarEstado.php` | Constructor variádico de `EstadoPokemon` |
| `src/Battle/Domain/UsoObjetos/Efectos/EfectoCurarHp.php` | `validar()` comprueba HP < máximo |
| `src/Shared/Domain/Items/CatalogoItems.php` | `objetosDeCombate()` = `usableEnCombate === true` (44) |
| `src/Battle/Domain/DataTransferObjects/OpcionObjeto.php` | Nuevo campo `categoria` |
| `src/Battle/App/ObtenerOpcionesObjeto.php` | Itera 44 opciones; una sola consulta de saldos |
| `resources/views/livewire/partials/items-panel.blade.php` | Secciones por categoría; etiquetas de bayas vs equipadas |

Sin cambios en migraciones: las recetas siguen en código y las bayas directas ya existen en `player_inventory`.

### Criterios de aceptación corregidos

**F4**

- [ ] Existen exactamente 46 recetas y 39 resultados; `todas()` devuelve 46.
- [ ] R1 (`receta:pocion_oran`) y R2 (`receta:pocion_sitrus`) producen `pocion:pocion`; `paraResultado('pocion:pocion')` devuelve 2.
- [ ] `paraResultado('material:recipiente')` devuelve 7 (una por color de bonguri).
- [ ] R3–R9 consumen exactamente los ingredientes de la tabla y producen 1 unidad.
- [ ] Cada receta de mejora/reductor/resistencia consume 1 baya + 1 recipiente.
- [ ] `Fabricar` acepta el id de receta (`receta:...`), no el resultado; una receta inexistente → 404.
- [ ] La UI agrupa variantes por `resultado_clave` y permite elegir la variante; `puede_fabricar` es por variante.
- [ ] Toda clave de ingrediente/resultado existe en `CatalogoItems`; no aparecen “Desconocido”.

**F5**

- [ ] El panel de objetos incluye 6 bayas directas (`baya:oran`, `baya:sitrus`, `baya:cheri`, `baya:pecha`, `baya:rawst`, `baya:aspear`) y 38 objetos fabricados (44 en total).
- [ ] Oran cura 10 % y Zidra 25 %; Cheri/Peque/Meloc/Safre/Perasi curan parálisis/veneno/quemadura/congelación respectivamente.
- [ ] Una baya directa consume 1 unidad de `player_inventory` y la acción; no consume movimiento.
- [ ] `baya:sitrus` no se confunde con `sitrus_berry`; ambos pueden coexistir y la UI los distingue.
- [ ] `baya:chesto`, `baya:persim`, `baya:lum` y `baya:leppa` no aparecen (deuda documentada).
- [ ] Las bayas de stat y de tipo siguen siendo solo materiales (`usableEnCombate: false`).

## (d) Riesgos nuevos

| Nivel | Riesgo | Mitigación |
|---|---|---|
| **Medio** | Varias recetas comparten resultado (2 pociones, 7 recipientes) y la UI puede duplicar tarjetas o confundir al jugador | Agrupar por `resultado_clave`; selector de variantes con `etiqueta`; `puede_fabricar` por variante. |
| **Medio** | R6–R9 encadenan objetos fabricados y pueden consumir mucho inventario en una sola acción | Es la especificación; la UI muestra ingredientes exactos y saldos; sin idempotencia (deuda ya asumida). |
| **Medio** | `baya:sitrus` (manual) vs `sitrus_berry` (equipada) | Claves y ciclos distintos; etiquetas explícitas; `objetosDeCombate()` solo incluye `baya:*`. |
| **Bajo** | Se esperaban 10 bayas directas y solo hay 6 en `ClaveBaya` | Deuda explícita; assets ya existen; ampliación futura localizada en `ClaveBaya` + `CatalogoEfectosItem`. |
| **Bajo** | `material:recipiente` con 7 variantes puede inflar la lista de recetas | Agrupación por resultado y selector; el jugador ve una sola tarjeta “Recipiente”. |
| **Bajo** | `Leppa` requeriría PP por movimiento, no cubierto por `EstadoMovimientos::reiniciar()` | Se excluye de v1; se abordará con un modelo de PP específico. |
