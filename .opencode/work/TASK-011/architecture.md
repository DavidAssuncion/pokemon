
# TASK-011 — Diseño técnico: bonguris, bayas, forja y uso de objetos en combate

> **Estado:** diseño para aprobación (no implementado) · **Proyecto:** `/home/david/pokemon` · **Rama:** `master` · **PHP 8.4 / Laravel 12 / Livewire 4**
> **Convención:** HECHO (verificado con `archivo:línea`), INFERENCIA (deducido), PROPUESTA (decisión de diseño).

---

## 1) Resumen ejecutivo y decisiones

El sistema se construye sobre tres piezas existentes y una nueva:

1. **Inventario único** `player_inventory` con unique `(user_id, item_key)` y `item_key VARCHAR(64)` — HECHO: `database/migrations/2026_08_29_000007_create_player_inventory_table.php:15-23`. Las familias nuevas son aditivas y no requieren tocar la tabla.
2. **Catálogo en código**, no en BD — HECHO del patrón actual: `app/Support/ItemCatalogo.php:84-107`, `src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`. Se mantiene y se extrae el vocabulario compartido a `src/Shared/Domain/Items`.
3. **Motor de combate** con `MultiplicadoresStats` temporales de 5 turnos y clamp 0.25–4.0 — HECHO: `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:21-25`, `:166-179`; `Combatiente::revivir()` y `curarHpCantidad()` — HECHO: `src/Battle/Domain/Combatiente.php:574`, `:1730`.
4. **Módulo nuevo `src/Forja`** para recetas y fabricación, separado de `src/Granjas`.

**Decisiones estructurales (D1–D12):**

| ID | Decisión |
|----|----------|
| D1 | El catálogo de items vive en **`src/Shared/Domain/Items`** (shared kernel), no en un módulo. Motivo: lo consumen `Exploraciones`, `Forja`, `Granjas`, `Battle` y `Equipos`, y `docs/ddd.md:18-20` prohíbe que un `Domain` de módulo dependa de otro módulo, pero permite `Src\Shared`. |
| D2 | La **forja** vive en `src/Forja` (nuevo), no en `src/Granjas`. Motivo: caso de uso distinto (transformación de materiales vs. mejora de estructuras); `MejorarNido` consume un recurso y sube nivel, `Fabricar` consume N materiales heterogéneos y produce otro item — HECHO del contraste: `src/Granjas/App/MejorarNido.php:25-61`. |
| D3 | El **botín de exploración** vive en `src/Exploraciones` (Domain + App) porque pertenece al ciclo de vida de la expedición. |
| D4 | El **uso de objeto en combate** vive en `src/Battle` (Domain: acción, efectos, servicio puro) + un caso de uso de aplicación mínimo en `src/Battle/App` que coordina inventario. |
| D5 | La **plantación/cosecha** vive en `src/Granjas` (es infraestructura de granja). |
| D6 | **Botín calculado al finalizar** (no por evento) en MVP. Evita tocar `EventoExploracion` (sin `item_key` — HECHO: `src/Exploraciones/Domain/ValueObjects/EventoExploracion.php:27-59`) y minimiza riesgo. |
| D7 | El **modo** se modela como columna nullable + enum `ModoExploracion`; `null` = `MIXTO` = comportamiento actual exacto. |
| D8 | El **bonguri** se asigna por hábitat (`habitats.bonguri`), con backfill desde `province_id` — evidencia del color por provincia: `database/seeders/ProvinceSeeder.php:18-33`. |
| D9 | El **bonguri** cae con probabilidad por modo, no garantizado; el de otra zona no puede salir. |
| D10 | La **influencia de plantaciones** en bayas salvajes se limita a `(user_id, habitat_id)` y se aplica como multiplicador de peso renormalizado, tope ×2,0. |
| D11 | **Un solo objeto equipado** por Pokémon; se mantiene `reclutados.obj_equipados` como lista de una clave — HECHO: `src/Equipos/App/ActualizarObjetosEquipo.php:34-36`. |
| D12 | **Equipar descuenta y exige propiedad** (D-04 del desarrollador). El consumo del objeto equipado en batalla **no** vuelve a descontar: se descuenta al equipar. |

**Regla transversal añadida (para evitar el agujero actual G4/G5):** ninguna clave que llegue del cliente se acepta como `item_key` libre; siempre se valida contra `CatalogoItems` antes de tocar `player_inventory`.

---

## 2) Contextos delimitados y módulos

| Bounded context | Responsabilidad | Módulo | Capas nuevas |
|---|---|---|---|
| **Catálogo de objetos** (shared kernel) | Identidad, categoría, nombre visible, icono, equipable/usable, descripción. Sin BD. | `src/Shared/Domain/Items` | Domain |
| **Exploración** | Modos, perfil de recompensas, botín, pérdidas, bonus de hábitat. | `src/Exploraciones` | Domain/App/Presentation existentes |
| **Granja** | Nidos, plantaciones, crecimiento, cosecha, influencia de bayas. | `src/Granjas` | Domain/App/Infra existentes |
| **Forja** | Recetas, validación de materiales, fabricación transaccional. | `src/Forja` *(nuevo)* | Domain/App/Infra |
| **Combate** | Acción de objeto, efectos, objetivos aliados/rivales, consumo de inventario. | `src/Battle` + `app/Livewire` | Domain + App/Infra nuevos |
| **Equipamiento** | Propiedad y descuento al equipar. | `src/Equipos` + `app/Http/Controllers/TeamController.php` | App existente |

**Dirección de dependencias:**

```
Shared\Domain\Items  ◄── todos los Domain (permitido por docs/ddd.md:18-20)
Exploraciones.Domain ──► Shared
Granjas.Domain       ──► Shared
Forja.Domain         ──► Shared
Battle.Domain        ──► Shared
Exploraciones.App    ──► Granjas.App (precedente: ResolverBonosFamilia en FinalizarExploracionHandler)
Forja.Infra          ──► Forja.Domain + Shared (adapter del puerto de inventario)
Battle.App/Infra     ──► Battle.Domain + Shared
```

No se crean dependencias circulares: `Exploraciones` no importa `Granjas.Domain`; recibe datos ya calculados desde App (mismo patrón que `ResolverBonosFamilia` — HECHO: `src/Exploraciones/App/FinalizarExploracionHandler.php:67`, `:149`).

---

## 3) Modelo de dominio por fase

### 3.1 Catálogo compartido (`src/Shared/Domain/Items`)

**Enums (string, slug inglés para alinear con assets):**

```php
enum CategoriaItem: string {
    case BAYA = 'baya';
    case BONGURI = 'bonguri';
    case POCION = 'pocion';
    case MATERIAL = 'material';
    case MEJORA = 'mejora';
    case REDUCTOR = 'reductor';
    case RESISTENCIA = 'resistencia';
}

enum ClaveBaya: string {           // 36 casos
    case ORAN = 'oran';  case SITRUS = 'sitrus';  case CHERI = 'cheri';
    case PECHA = 'pecha'; case RAWST = 'rawst';   case ASPEAR = 'aspear';
    case KELPSY = 'kelpsy'; /* … 36 en total … */ case ROSELI = 'roseli';

    public function clave(): string  { return 'baya:'.$this->value; }
    public function nombre(): string { /* nombre español: Aranja, Zidra, Zreza… */ }
    public function archivoIcono(): string { return "/images/berry/{$this->value}-berry.webp"; }
}

enum ClaveBonguri: string {        // 7
    case BLANCO='blanco'; case AMARILLO='amarillo'; case NEGRO='negro';
    case VERDE='verde';   case ROSA='rosa';         case AZUL='azul'; case ROJO='rojo';

    public function clave(): string { return 'bonguri:'.$this->value; }
    public function archivoIcono(): string { return "/images/bonguri/80px-Bonguri_{$this->value}_EP.png"; }
    public static function porDefecto(): self { return self::BLANCO; } // fallback provincia "Vacío"
}

enum ClavePocion: string {         // 8
    case POCION='pocion'; case SUPER='super'; case HIPER='hiper'; case MAXIMA='maxima';
    case CURA_TOTAL='curatotal'; case RESTAURAR_TODO='restaurar_todo';
    case REVIVIR='revivir'; case MAX_REVIVIR='max_revivir';
    public function clave(): string { return 'pocion:'.$this->value; }
}

enum ClaveMejora: string {         // 7: incluye precisión (cubre precisión+evasión) y crítico
    case ATAQUE='ataque'; case DEFENSA='defensa';
    case ATAQUE_ESPECIAL='ataque_especial'; case DEFENSA_ESPECIAL='defensa_especial';
    case VELOCIDAD='velocidad'; case PRECISION='precision'; case CRITICO='critico';
    public function clave(): string { return 'mejora:'.$this->value; }
}

enum ClaveReductor: string {       // 5
    case ATAQUE='ataque'; case DEFENSA='defensa'; case ATAQUE_ESPECIAL='ataque_especial';
    case DEFENSA_ESPECIAL='defensa_especial'; case VELOCIDAD='velocidad';
    public function clave(): string { return 'reductor:'.$this->value; }
}

enum ClaveResistencia: string {    // 18 (un caso por TipoPokemon)
    case FUEGO='fuego'; /* … hasta HADA … */
    public function clave(): string { return 'resistencia:'.$this->value; }
    public static function desdeTipo(TipoPokemon $tipo): self; // usa SlugTipo existente
}
```

**`DefinicionItem` (readonly):**

```php
final readonly class DefinicionItem {
    public function __construct(
        public string $clave,
        public string $nombre,
        public CategoriaItem $categoria,
        public bool $equipable,
        public bool $usableEnCombate,
        public bool $consumible,
        public string $icono,
        public string $descripcion,
    ) {}
    public function esBaya(): bool;
    public function toArray(): array; // frontera
}
```

**`CatalogoItems` (Domain puro, mapa estático construido una vez):**

```php
final class CatalogoItems {
    public static function existe(string $clave): bool;
    public static function definicion(string $clave): DefinicionItem;   // lanza ItemDesconocido
    public static function definiciones(): DefinicionItemCollection;
    public static function porCategoria(CategoriaItem $c): DefinicionItemCollection;
    public static function clavesEquipables(): StringCollection;
    public static function clavesUsablesEnCombate(): StringCollection;
    public static function aPartirDeItemKey(string $itemKey): ?DefinicionItem; // parsea prefijo
}
```

**`ItemDesconocido extends ViolacionReglaNegocio`** (422) en `src/Shared/Domain/Exceptions/`.

**Justificación de no crear tabla de catálogo:** el catálogo es cerrado (36+7+8+1+7+5+18 = 82 claves), fijo, sin administración prevista y el proyecto ya resuelve objetos de combate por código — HECHO: `src/Battle/Domain/Catalog/CatalogoObjetos.php:20-29`. Una tabla exigiría migración, seeder, caché y sincronización con los enums; no aporta hasta que exista editor de contenido.

**Compatibilidad de claves de combate existentes:** `sitrus_berry`/`lum_berry` siguen siendo claves de objeto equipable en `CatalogoObjetos` — HECHO: `src/Battle/Domain/Enums/ClaveItem.php:12-13`. `baya:sitrus`/`baya:lum` son claves de inventario. Se añade un **mapping explícito** en `CatalogoItems::claveEquipableDeBaya()`; no se migra `obj_equipados` en esta fase (evita tocar datos legacy).

### 3.2 Exploraciones (fases 1 y 3)

```php
enum ModoExploracion: string {
    case EXPERIENCIA = 'experiencia';
    case RECLUTAMIENTO = 'reclutamiento';
    case BOTIN = 'botin';
    case MIXTO = 'mixto';                 // solo legacy/API antigua; no se ofrece en UI
    public static function desde(?string $valor): self; // null → MIXTO
}

final readonly class PerfilExploracion {
    public function __construct(
        public ModoExploracion $modo,
        public PesosEvento $pesos,          // VO: encuentra/hallazgo/especial/contratiempo/neutral
        public float $multiplicadorExp,
        public float $multiplicadorCaramelos,
        public float $factorCaptura,
        public float $multiplicadorBotin,
    ) {}
    public static function de(ModoExploracion $modo): self;  // tabla §6.1
}
```

- `PesosEvento` valida suma 100 y sustituye los literales de `SimuladorEncuentros` **solo cuando se pasa un perfil**; sin perfil usa las constantes actuales — HECHO: `src/Exploraciones/Domain/SimuladorEncuentros.php:33-37`.
- `SimuladorEncuentros::generarEventosDesdePool(..., ?PerfilExploracion $perfil = null)` — cambio aditivo, firma antigua conservada.

**Botín:**

```php
final readonly class RecompensaItem {
    public function __construct(
        public string $clave,       // validada contra CatalogoItems antes de salir del dominio
        public int $cantidad,
    ) {}
}
final class RecompensaItemCollection extends Collection { public string $type = RecompensaItem::class; }

final readonly class ContextoBotin {
    public function __construct(
        public ModoExploracion $modo,
        public ?ClaveBonguri $bonguriHabitat,
        public int $nivelHabitat,           // 1..3
        public int $duracionHoras,
        public string $categoria,           // exito_excepcional|exito|exito_parcial|fracaso|retirada
        public bool $delHabitat,            // especie nativa (MVP)
        public MapaFactores $factoresPlantacion, // baya => factor (vacío = sin plantaciones)
        public bool $huboDerrota,
    ) {}
}

final class CalculadorBotinExploracion {
    public function __construct(private readonly \Closure $aleatorio) {}
    public function calcular(ContextoBotin $contexto): RecompensaItemCollection;
}
```

- `MapaFactores` ya existe en `Src\Shared\Domain\ValueObjects` (usado por `MultiplicadoresStats`) — HECHO por su import en `MultiplicadoresStats.php:8`. Si no cubre el caso, se usa `MapaConteo` y un VO nuevo `MapaFactoresBayas`; **A VERIFICAR** la API exacta de `MapaFactores` (no leída).
- Topes duros por expedición: **máx. 3 bayas + 2 bonguris**, con rendimientos decrecientes por duración.
- El botín **solo incluye el bonguri de ese hábitat**; si `bonguriHabitat === null` no cae bonguri.

**Cambios en `ResultadoRecompensas`:**
- Nueva propiedad `public readonly RecompensaItemCollection|Collection $items` **con default `[]`** al final del constructor, sin reordenar parámetros existentes — compatible con los constructores actuales — HECHO: firma actual `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php:44-58`.
- Nuevo `sumarBotin(RecompensaItemCollection $botin): self` que combina por clave (mismo patrón que `sumarHallazgos`, `:69-84`).
- `restarPerdidas` extendido para restar `items` (mismo `intdiv(cantidad, 2)`).

**Bonus de hábitat (MVP):** servicio de aplicación `EsDelHabitat` que comprueba `pokemon_habitat (pokemon_id del reclutado, habitat_id)` — la tabla existe con unique `(pokemon_id, habitat_id)` — HECHO: `database/migrations/2026_02_24_201538_create_pokemon_habitat_table.php:15-21`. Se calcula una vez en `ProcesarExploracionHandler` y se persiste el flag en `eventos['bonos']['del_habitat']` para no repetir la consulta al finalizar.

### 3.3 Granja: plantaciones (fase 2)

```php
enum EstadoPlantacion: string { case CRECIENDO='creciendo'; case LISTA='lista'; case AGOTADA='agotada'; }

final readonly class Plantacion {
    public function __construct(
        public int $id,
        public int $slotIndex,
        public string $berryKey,            // validada contra ClaveBaya
        public \DateTimeImmutable $plantedAt,
        public int $harvestCount,
    ) {}
}

final class ReglasPlantacion {
    public const HUECOS_POR_NIVEL = 2;
    public const HORAS_CRECIMIENTO = 24;
    public const STOCK_MAXIMO = 3;
    public const COSTE_PLANTAR = 1;
    public const BONUS_PESO_POR_HUECO = 0.20;
    public const BONUS_PESO_MAXIMO = 2.0;

    public static function huecosDe(int $nivelNido): int;      // nivel*2
    public static function stockDe(Plantacion $p, \DateTimeImmutable $ahora): int;
    public static function puedeCosechar(Plantacion $p, \DateTimeImmutable $ahora): bool;
}
```

- El stock se **calcula**, no se almacena: `min(3, floor((ahora - planted_at)/24h) - harvest_count)`. Evita cron y carreras de maduración.
- Huecos vacíos **no** se persisten; se derivan de `huecosDe(level) - count(plantings)`.

### 3.4 Forja (fase 4)

```php
final readonly class RecetaFabricacion {
    public function __construct(
        public string $clave,
        public string $resultadoClave,
        public int $resultadoCantidad,
        public MapaConteo $ingredientes,     // clave => cantidad (contrato tipado, no array)
        public CategoriaItem $categoriaResultado,
    ) {}
}

final class CatalogoRecetas {
    public static function existe(string $clave): bool;
    public static function receta(string $clave): RecetaFabricacion;   // lanza RecetaNoExiste (404)
    public static function todas(): RecetaFabricacionCollection;
    public static function paraResultado(string $clave): RecetaFabricacionCollection;
}
```

- **No hay entidad persistente** mientras la fabricación sea instantánea. La receta es un VO inmutable.
- `MapaConteo` es el contrato de ingredientes (prohibido array asociativo — regla `arrays-dto-collections.md`).
- La forja **no depende de Battle**; solo del catálogo compartido. El efecto combativo se resuelve en Battle al usar el item.

### 3.5 Combate (fase 5)

**Objetivos y efecto:**

```php
enum ObjetivosItem: string {
    case ALIADO_VIVO = 'aliado_vivo';
    case ALIADO_DEBILITADO = 'aliado_debilitado';
    case RIVAL_VIVO = 'rival_vivo';
    case ALIADO_O_ACTOR = 'aliado_o_actor';   // incluye al actor (curarse a sí mismo)
}

interface EfectoItem {
    public function clave(): string;
    public function objetivos(): ObjetivosItem;
    public function validar(Combatiente $objetivo): ?string;   // null = aplicable
    public function aplicar(Combatiente $objetivo, ContextoUsoItem $ctx): ResultadoEfectoItem;
}
```

Implementaciones (una por familia, parametrizadas):

```php
EfectoCurarHp(float $porcentaje)              // curarHpCantidad(hpMax * pct)
EfectoCurarEstado()                           // setEstado(NONE) + quitarCondicion(CONFUSION)
EfectoRevivir(float $fraccion)                // Combatiente::revivir($fraccion)
EfectoRestaurarTodo()                         // HP 100% + estado + movimientos->reiniciar()
EfectoModificarStat(StatClave $stat, float $factor, bool $sobreRival)
EfectoMejorarCritico(int $niveles)            // CondicionVolatil(CRITICO_MEJORADO, turnos:5)
EfectoResistenciaTipo(TipoPokemon $tipo, float $factor)  // resistencia temporal
```

**Registro de comportamiento:** `CatalogoEfectosItem` (Battle Domain) con mapa `clave => EfectoItem`; no se reutiliza `FabricaEfectos` (es de habilidades/equipados — HECHO: `app/Providers/BattleEffectServiceProvider.php:117-128`) para no mezclar responsabilidades.

**Acción y resultado:**

```php
final readonly class AccionObjeto {
    public function __construct(
        public string $actorId,
        public string $objetivoId,
        public string $clave,
    ) {}
}

final readonly class ResultadoUsoObjeto {
    public function __construct(
        public bool $exito,
        public ?string $motivo,        // null si exito
        public string $clave,
        public string $objetivoNombre,
        public int $curado = 0,
        public bool $revivido = false,
        public string $log = '',
    ) {}
}

final class ServicioUsoObjetos {
    public function validar(AccionObjeto $accion, AgregadoBatalla $battle): ?string;
    public function aplicar(AccionObjeto $accion, AgregadoBatalla $battle): ResultadoUsoObjeto;
}
```

**Resistencia temporal (G15):** se añade `ResistenciasTemporales` (VO Battle Domain) en `Combatiente`, con la misma semántica que `MultiplicadoresStats` temporales: duración compartida de 5 rondas, **refresco sin stack** al reaplicar, y avance en `Combatiente::avanzarTemporales()` (ya invocado desde `AgregadoBatalla` — HECHO: `src/Battle/Domain/AgregadoBatalla.php:466-468`, `Combatiente.php:1349`). `Combatiente::multiplicadorDanioRecibido()` multiplica por el factor del tipo del movimiento atacante. Es la opción con menos superficie nueva y coherente con el diseño existente.

**Crítico (G14):** se reutiliza `ClaveCondicion::CRITICO_MEJORADO` — HECHO: `src/Battle/Domain/Enums/ClaveCondicion.php:65`, consumida por `ManejadorCritico.php:53` (×2,0) — con `CondicionVolatil(turnos: 5)`. `CondicionVolatil` ya tiene `turnos` y `AgregadoBatalla` los decrementa — HECHO: `src/Battle/Domain/ValueObjects/CondicionVolatil.php:17-20`, `AgregadoBatalla.php:572`. No hace falta `StatClave::CRITICO`.

**PP/usos (G13):** `EstadoMovimientos::reiniciar()` ya existe — HECHO: `src/Battle/Domain/ValueObjects/EstadoMovimientos.php:85-89`. `Restaurar Todo` y `Max Revivir` lo invocan mediante un método nuevo `Combatiente::restaurarUsos()`. No se modifica la estructura de `EstadoMovimientos`.

**Buff/debuff de stats:** `MultiplicadoresStats::aplicarTemporal()` — factor 1.30 / 0.70, 5 turnos — HECHO: `:166-179`. Decisión de balance: **no stackear**; si la stat ya tiene temporal activo, se refresca a 5 turnos sin multiplicar (evita ×1,69 y ×0,49). Esto exige pasar un flag a `aplicarTemporal` o un método `refrescarTemporal(StatClave)`; **A VERIFICAR** si conviene añadir el método (no existe hoy).

### 3.6 Equipamiento (fase 3.5 / 6)

- `ActualizarObjetosEquipo` (App) gana validación de propiedad y descuento dentro de `DB::transaction` con `lockForUpdate` sobre `player_inventory`.
- El descuento es **al equipar** (D12). Si el jugador cambia el objeto del slot, el anterior **no se devuelve** por defecto (decisión de producto; alternativa documentada: devolver al desequipar). Se implementa un DTO `ResultadoEquipamiento` con `descontado`, `devuelto`, `motivo`.
- La validación HTTP (`Rule::in(CatalogoObjetos::claves())`) sigue siendo necesaria pero no suficiente: ahora hay una regla de negocio en App.

---

## 4) Esquema de datos y migraciones

### 4.1 Fase 1 — modo + bonguri

```text
2026_11_xx_000001_add_modo_to_exploraciones_activas.php
  ALTER exploraciones_activas ADD modo VARCHAR(20) NULL
  -- sin índice: se lee con la fila
  -- null = MIXTO (comportamiento actual)

2026_11_xx_000002_add_bonguri_to_habitats.php
  ALTER habitats ADD bonguri VARCHAR(16) NULL
  -- backfill determinista desde province_id (ProvinceSeeder.php:18-33)
  -- provincia "Vacío" → NULL; fallback en dominio ClaveBonguri::porDefecto() = blanco
```

`down()`: `dropColumn` (aditivo, reversible).

### 4.2 Fase 2 — plantaciones

```text
2026_11_xx_000003_create_nest_plantings_table.php
nest_plantings
  id                bigserial PK
  nest_id           bigint FK → nests(id) ON DELETE CASCADE
  slot_index        unsignedSmallInteger
  berry_key         varchar(32)          -- 'baya:oran'
  planted_at        timestamp NOT NULL
  harvest_count     unsignedSmallInteger default 0
  created_at, updated_at

  UNIQUE (nest_id, slot_index)
  INDEX (nest_id)
  INDEX (berry_key)      -- agregación del bonus por granja
```

- `berry_key` ≤ 32: `baya:defensa_especial` no existe; el más largo es `baya:defensa_especial` (irreal) y `resistencia:defensa_especial` no aplica; los reales caben de sobra en 64 (inventario) y 32 (plantación).
- `slot_index` no se valida en BD contra `nests.level` (expresión mutua); se valida en el caso de uso. Se documenta.
- No hay FK a catálogo: igual que `player_inventory` — HECHO: la tabla no tiene FK de item.

### 4.3 Fase 4 — forja

**Sin migraciones.** Recetas en código. Si en el futuro se necesita cola/tiempos, se añadirá `craft_jobs`; se difiere.

### 4.4 Fase 5 — combate

**Sin migraciones.** El estado de batalla vive en sesión — HECHO: `app/Support/BattleSessionService.php:67-70`. Se requiere **bump de `SESSION_VERSION` 20 → 21** (por `ResistenciasTemporales` y campos nuevos en `DTOAccionObjeto`). Se mantiene `MINIMUM_COMPATIBLE_SESSION_VERSION` y se documenta que v20 se descarta limpiamente (`cargar()` ya descarta versiones incompatibles — HECHO `:96-100`).

### 4.5 Lo que se difiere explícitamente

- Tabla `item_catalog` / `recipes` / `item_transactions` (auditoría): no MVP.
- `reclutados.habitat_origen_id`: fase 2 opcional; el MVP usa especie nativa en `pokemon_habitat`.
- `habitat_berries` (bayas nativas por hábitat): el MVP usa tabla global ponderada + bonus de plantación.
- Auto-cosecha por `RECOLECTOR`: fase 2 de granja.
- Botín de combate: fase 6.

### 4.6 Índices y constraints justificados

| Índice/constraint | Motivo |
|---|---|
| `player_inventory UNIQUE (user_id, item_key)` | Ya existe; evita duplicados al incrementar — HECHO `2026_08_29_000007:22`. |
| `nest_plantings UNIQUE (nest_id, slot_index)` | Evita doble plantación en el mismo hueco (concurrencia). |
| `nest_plantings INDEX (berry_key)` | Agregación del bonus por granja. |
| `nests UNIQUE (farm_id, evolution_chain_id)` | Ya existe — HECHO `2026_10_26_000001:32`. |

Sin índices nuevos en `exploraciones_activas` ni `habitats` (columnas leídas con la fila).

---

## 5) Contratos, puertos y DTOs

### 5.1 Cambios en contratos existentes (aditivos y tolerantes)

**`ResultadoRecompensas`**
```php
public function __construct(
    ...,
    public readonly RecompensaItemCollection|Collection $items = [], // NUEVO, al final
)
public function sumarBotin(RecompensaItemCollection $botin): self;
```

**`TransformadorResultadoExploracion::desde()`**
- Nuevo campo de salida `items: list<array{clave: string, nombre: string, icono: string, categoria: string, cantidad: int}>`.
- El transformador resuelve nombre/icono con `CatalogoItems` (puro) para no duplicar lógica en Blade.
- Si `items` está vacío → `[]` (la vista tolera ausencia).

**`PresentadorExploraciones::toTerminada()`**
- Lee `resultado['items'] ?? []` y lo pasa a `ResultadoExploracionVista`.
- `objetosPerdidosVista()` añade ramas `baya` y `bonguri` (resuelven `ClaveBaya`/`ClaveBonguri` → nombre/icono). Sin consultas nuevas.
- El contrato persistido `eventos['resultado']` crece con `items`; los registros antiguos sin `items` caen a `[]`.

**`ResultadoExploracionVista::toView()`**
- Nuevas claves `items`, `tiene_items`, `tiene_obtenidas` incluye items.
- Sin cálculos de negocio.

**`GranjaDetalle`**
- Nuevo campo `public readonly ColeccionBonguri? $bonguri` o `string|null` + nombre/icono resueltos por App. Recomendación: DTO `BonguriGranja { clave, nombre, icono }` para que Blade solo pinte. Se añade al `toArray` como `bonguri`.
- `InventarioCaramelosGranja` se renombra conceptualmente a `InventarioGranja` **solo si** se añaden bayas; para no romper, se **amplía** con `public readonly CaramelosBayaCollection $bayas` (default vacío) y se mantiene el nombre actual. Alternativa: nuevo campo `bayas` en `GranjaDetalle` sin tocar el DTO de caramelos. **Recomendación:** campo `bayas` en `GranjaDetalle` (menos acoplamiento).

**`NidoGranja`**
- Nuevos campos: `public readonly int $huecosTotales`, `public readonly PlantacionesGranjaCollection $plantaciones`. `toArray()` añade `huecos_totales` y `plantaciones`.

**`PlantacionGranja` (nuevo DTO)**
```php
slot: int, berry_key: string, berry_nombre: string, berry_icono: string,
planted_at: string (ISO), stock: int, stock_max: int, puede_cosechar: bool,
progreso_pct: int, estado: string
```
Todos los derivados calculados en backend (`ReglasPlantacion`), nunca en JS.

**`CatalogoObjetos` (Battle)**
- `iconoDe()` deja de devolver `''` y delega en `CatalogoItems::definicion($clave)->icono` cuando la clave sea de inventario; mantiene su mapa de 8 objetos para nombres/cargas — HECHO del hueco: `src/Battle/Domain/Catalog/CatalogoObjetos.php:41-44`.
- No se añaden los 82 items a `ClaveItem` (enum de efectos de combate equipados); se mantienen separados.

**`DTOAccionBatalla` / nuevo `DTOAccionObjeto`**
- **Decisión:** crear `DTOAccionObjeto` (Wireable) separado y ampliar `AgregadoBatalla` con un `pendingAction` que acepte ambos. Alternativa descartada: añadir `itemKey` y `type='item'` a `DTOAccionBatalla` porque obliga a un `DTOMovimientoBatalla` ficticio y contamina el contrato de ataque.
- `DTOAccionObjeto`:
```php
final class DTOAccionObjeto implements Wireable {
    public function __construct(
        public readonly string $actorId,
        public readonly string $defenderId,
        public readonly string $itemKey,
        public readonly string $attackerNombre,
        public readonly string $itemNombre,
    ) {}
}
```
- `AgregadoBatalla::setPendingAction(DTOAccionBatalla|DTOAccionObjeto|null)`; `resolverAccion()` hace `instanceof` y delega al caso de uso de objeto.
- **Bump de `SESSION_VERSION` a 21** y compatibilidad: los payloads v20 no contienen el nuevo tipo; al no poder reconstruir, se descartan (comportamiento ya existente).

### 5.2 Puertos (interfaces)

**Inventario (consumidor: Forja, Equipos, Battle, Granjas)**

```php
// src/Shared/Domain/Ports/InventarioJugador.php
interface InventarioJugador {
    public function saldo(int $userId, string $clave): int;
    /** @throws StockInsuficiente */
    public function consumir(int $userId, string $clave, int $cantidad): void;
    public function incrementar(int $userId, string $clave, int $cantidad): void;
    /** @param MapaConteo $claves */
    public function consumirVarios(int $userId, MapaConteo $claves): void;
}
```

- Implementación única `App\Support\InventarioJugadorEloquent` (o `src/Shared/Infra/...`; el proyecto ya usa `app/Support` para infraestructura transversal — HECHO `ItemCatalogo`). Se registra en un provider.
- Motivo de un solo puerto compartido: Forja, Equipos y Battle necesitan la misma semántica atómica; duplicarla en tres módulos violaría DRY y crearía tres `lockForUpdate` distintos.
- `StockInsuficiente extends ViolacionReglaNegocio` (422) con la clave en el mensaje.

**Plantaciones para botín (consumidor: Exploraciones)**

```php
// src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php
interface ConsultorPlantaciones {
    /** @return MapaFactores  baya_key => factor (>1) */
    public function factoresPara(int $userId, int $habitatId): MapaFactores;
}
```

- Implementación `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent` (Infra→Domain permitido por deptrac — HECHO del ruleset `deptrac.yaml`: `Infrastructure: [Domain]`).
- Alternativa descartada: importar `Granjas.Domain` desde `Exploraciones.Domain` (prohibido por `docs/ddd.md:18-20`). Alternativa intermedia: servicio `Granjas\App` inyectado en el handler (precedente `ResolverBonosFamilia`); se elige el puerto porque el cálculo del botín es dominio puro y debe recibir el VO, no depender del contenedor.

**Reloj (testabilidad y coherencia):** `Reloj` ya existe en el proyecto (`Src\Shared\Domain\Reloj` o similar — **A VERIFICAR**). Si no existe, `Plantacion` recibe `DateTimeImmutable $ahora` por parámetro y no se añade abstracción.

### 5.3 DTOs de aplicación

**Forja**
```php
final readonly class RecetaDetalle {
    public function __construct(
        public string $clave, public string $resultadoClave, public string $resultadoNombre,
        public string $resultadoIcono, public int $resultadoCantidad,
        public IngredientesDetalleCollection $ingredientes,
        public bool $puedeFabricar, public ?string $motivo,
    ) {}
}
final readonly class IngredienteDetalle {
    public function __construct(
        public string $clave, public string $nombre, public string $icono,
        public int $requerido, public int $disponible,
    ) {}
}
final readonly class ResultadoFabricacion {
    public function __construct(
        public string $clave, public int $cantidad, public MapaConteo $saldosResultantes,
    ) {}
}
```

**Granja**
```php
final readonly class ResultadoPlantacion { public int $slot; public string $berryKey; }
final readonly class ResultadoCosecha   { public string $berryKey; public int $cantidad; }
```

**Combate**
```php
final readonly class OpcionObjeto {
    public function __construct(
        public string $clave, public string $nombre, public string $icono,
        public int $cantidad, public bool $aplicable, public ?string $motivo,
        public ObjetivosItem $objetivos,
    ) {}
}
final class OpcionObjetoCollection extends Collection { public string $type = OpcionObjeto::class; }
```

`OpcionObjetoCollection` la construye un presentador (`app/Support/ObjetosCombatePresenter` o `src/Battle/Presentation`) cruzando inventario + estado de la batalla; el Blade no decide aplicabilidad.

### 5.4 Comandos/casos de uso

| Módulo | Caso de uso | Entrada | Salida |
|---|---|---|---|
| Forja | `ListarRecetas` | `userId` | `RecetaDetalleCollection` |
| Forja | `Fabricar` | `FabricarCommand(userId, recetaClave, cantidad)` | `ResultadoFabricacion` |
| Granjas | `PlantarBaya` | `userId, habitatId, chainId, slot, berryKey` | `GranjaDetalle` |
| Granjas | `CosecharPlantacion` | `userId, habitatId, chainId, slot` | `GranjaDetalle` |
| Granjas | `ObtenerFactoresPlantacion` | `userId, habitatId` | `MapaFactores` |
| Exploraciones | `CalcularBotinExploracion` (dominio) | `ContextoBotin` | `RecompensaItemCollection` |
| Battle | `UsarObjetoEnCombate` | `userId, batalla, AccionObjeto` | `ResultadoUsoObjeto` |
| Equipos | `ActualizarObjetosEquipo` (existente, ampliado) | `team, items, userId` | `ObjetosEquipo` + resultado |

`Fabricar` va por `CommandBus`/`UnitOfWork` (ya existe el patrón: todo comando en transacción — HECHO `src/Shared/Bus/CommandBus.php:6-10`).

---

## 6) Flujos principales

### 6.1 Inicio de exploración con modo

1. La UI envía `modo` en `GET /exploraciones/preview` y `POST /api/exploraciones/store-individual` (aditivo).
2. `validarYNuevaExploracion()` valida `modo => nullable|in:experiencia,reclutamiento,botin` y lo persiste — punto de cambio HECHO: `app/Http/Controllers/ExploracionActivaController.php:295-343`.
3. `storeIndividual()` devuelve `modo` en el JSON (aditivo) — HECHO del contrato: `:277-283`.
4. `ProcesarExploracionHandler` construye `PerfilExploracion::de($exploracion->modo)` y lo pasa a `SimuladorEncuentros` — HECHO del punto de generación: `src/Exploraciones/App/ProcesarExploracionHandler.php:155-157`.
5. El bonus de hábitat se calcula con `EsDelHabitat` y se guarda en `eventos['bonos']`.

**Pesos por modo (defaults, ajustables):**

| Modo | Encuentro | Hallazgo | Especial | Contratiempo | Neutral |
|---|---:|---:|---:|---:|---:|
| `mixto` (actual) | 45 | 20 | 15 | 10 | 10 |
| `experiencia` | 65 | 5 | 15 | 10 | 5 |
| `reclutamiento` | 55 | 10 | 15 | 10 | 10 |
| `botin` | 20 | 45 | 10 | 15 | 10 |

**Multiplicadores:**

| Modo | EXP | Caramelos | Captura | Botín |
|---|---:|---:|---:|---:|
| `mixto` | ×1,00 | ×1,00 | ×1,00 | ×1,00 |
| `experiencia` | ×1,25 | ×0,75 | ×0,50 | ×0,50 |
| `reclutamiento` | ×0,75 | ×0,75 | ×2,00 | ×0,50 |
| `botin` | ×0,50 | ×0,50 | ×0,50 | ×1,50 |

Los multiplicadores de EXP/caramelos se aplican con las firmas existentes de `CalculadorRecompensas::calcular()` — HECHO: `src/Exploraciones/Domain/CalculadorRecompensas.php:51-60`. El factor de captura se aplica en `rollAleatorio` — punto HECHO: `FinalizarExploracionHandler.php:160`, `:491-525` (**A VERIFICAR** la firma exacta de `ProbabilidadCaptura::intentarConFactores` antes de implementar).

### 6.2 Generación de eventos y botín final

1. Eventos por tick como hoy (sin cambios estructurales) — HECHO `ProcesarExploracionHandler.php:121-266`.
2. Al finalizar: `handle()` refresca, comprueba idempotencia por `regreso !== null` y no repite — HECHO `FinalizarExploracionHandler.php:78-83`.
3. Calcula recompensas + hallazgos como hoy.
4. **Nuevo:** `CalculadorBotinExploracion::calcular(ContextoBotin)` con `modo`, `bonguriHabitat`, `delHabitat`, `factoresPlantacion`, categoría y duración.
5. `recompensas = recompensas->sumarBotin($botin)`.
6. Pérdidas por derrota: `objetosPerdidos()` incluye items y `restarPerdidas()` los resta (se ejecuta **después** de sumar botín y **antes** de persistir) — puntos HECHO: `:187-197`, `:615-728`.
7. `PersistirRecompensas::persistir()` recorre `items` e incrementa con `firstOrCreate(...)->increment()` — punto HECHO: `src/Exploraciones/App/PersistirRecompensas.php:80-90`. `incrementarItem` ya tolera `usuario === null` (`:80-84`).
8. `registrarResultado()` persiste el contrato con `items`.
9. Todo dentro del `CommandBus` transaccional; la idempotencia por `regreso` protege de doble botín.

**Fórmulas de botín (defaults):**

- Bayas: `n` base por modo (`botin` 1–3, `experiencia` 0–1, `reclutamiento` 0–1, `mixto` 0–2), selección ponderada por rareza. Con bonus de hábitat: `ceil(n × 1,25)`.
- Bonguri: probabilidad base por modo (`botin` 60 %, `experiencia` 25 %, `reclutamiento` 25 %, `mixto` 30 %); con bonus `+10 pp`, tope 85 %. Cantidad 1; con tirada < 0,25 y bonus → 2. Tope 2.
- Topes duros: 3 bayas + 2 bonguris por expedición, aunque la duración sea 72 h.

### 6.3 Plantación / crecimiento / cosecha

1. `POST /api/habitats/{habitat}/farm/nests/{chain}/plantings` body `{slot, berry}`.
2. `PlantarBaya`:
   - `AsegurarGranja::para(userId, habitatId)` — HECHO `src/Granjas/App/AsegurarGranja.php:16-35`.
   - `FamiliaEnHabitat::asegurar(habitatId, chain)` — HECHO `src/Granjas/App/FamiliaEnHabitat.php:15-25`.
   - `Nido` por `(farm_id, chain)` con `lockForUpdate`; `level >= 1`.
   - Valida `1 <= slot <= level*2` y que no exista fila `(nest_id, slot)`.
   - Valida `berry` contra `ClaveBaya` (nunca string libre).
   - Consume `1 × baya:{slug}` vía `InventarioJugador::consumir` (lock).
   - Inserta `nest_plantings`.
   - Todo en `DB::transaction`.
3. `POST .../plantings/{slot}/harvest` → `CosecharPlantacion`:
   - Carga la fila con `lockForUpdate`, comprobando granja del usuario y hábitat.
   - `stock = ReglasPlantacion::stockDe(...)`; si `<= 0` → 422.
   - Incrementa `baya:{slug}` en `stock` y `harvest_count += stock`.
   - Devuelve `GranjaDetalle`.
4. Sin cron: el estado se calcula al leer (stock, progreso, estado). La UI recibe valores derivados.
5. Replantar un hueco ocupado: se elimina la fila y se pierde el stock (documentado en UI). Alternativa (bloquear hasta cosechar) se descarta para MVP.

**Influencia en bayas salvajes:**
- Solo `(userId, habitatId)` de la granja.
- Agrega `berry_key` de todas las plantaciones de la granja.
- `factor(tipo) = 1 + 0,20 × min(slots(tipo), 5)`, tope ×2,0; se renormaliza el peso total.
- No afecta a bonguri, ni a otros hábitats, ni a la cosecha.

### 6.4 Fabricación

1. `GET /api/forja/recetas` → `ListarRecetas` (recetas + ingredientes + saldo + `puedeFabricar`).
2. `POST /api/forja/fabricar` body `{receta, cantidad?}` (MVP: `cantidad=1`).
3. `Fabricar`:
   - Valida la receta contra `CatalogoRecetas` (404 si no existe).
   - `DB::transaction` + `InventarioJugador::consumirVarios` con `lockForUpdate` ordenado por clave para evitar deadlocks.
   - Valida **todos** los ingredientes antes de descontar (atomicidad); si falta uno → 422 y no descuenta nada.
   - Incrementa el resultado.
   - Devuelve `ResultadoFabricacion` con saldos.
4. Doble clic: el frontend deshabilita el botón; el backend no es idempotente por naturaleza. Se documenta que no hay `Idempotency-Key` en MVP.

### 6.5 Equipamiento

1. `PATCH /teams/{team}/items` (contrato actual) — HECHO `app/Http/Controllers/TeamController.php:221-244`.
2. `ActualizarObjetosEquipo`:
   - Valida que la clave sea equipable (`CatalogoItems::definicion()->equipable` y pertenezca a `CatalogoObjetos`).
   - Si el slot cambia a una clave no vacía: exige propiedad (`saldo >= 1`), descuenta 1 y escribe `obj_equipados = [clave]`.
   - Si el slot se vacía: escribe `[]` (decisión: no devuelve el objeto; ver §12).
   - Transacción + `lockForUpdate`.
3. La UI de equipos filtra el selector por inventario (el backend entrega `objetos` con `disponible` y `cantidad`); ya no se ofrece todo el catálogo — hueco actual HECHO: `app/Support/EquiposVistaPresenter.php:35-42`.

### 6.6 Uso de objeto en combate

1. En `phase = player_target` (turno del actor jugador), el panel ofrece «Objetos» **solo si el actor puede actuar** (ya se comprobó con `puedeActuar()` en `nextActor` — HECHO `app/Livewire/Combate.php:246-277`).
2. `seleccionarObjeto(clave)` → `phase = player_item_target`, marca objetivos válidos según `DefinicionItem`/`ObjetivosItem`.
3. `previewTarget(teamIdx, pokemonIdx)` ya acepta team 0/1 — HECHO `app/Livewire/Combate.php:1001-1025`; el presentador debe marcar aliados según el item, no solo rivales — hueco HECHO `app/Livewire/Presenters/BatallaVistaPresenter.php:112-121`.
4. `confirmarObjeto()` crea `DTOAccionObjeto`, lo guarda como `pendingAction` y deja que la animación llame a `commitAction()` (mismo patrón que ataque — HECHO `Combate.php:824-841`).
5. `resolverAccion()` detecta `DTOAccionObjeto` y llama a `UsarObjetoEnCombate`:
   - Valida clave y objetivo (`ServicioUsoObjetos::validar`); si no aplica, aborta sin consumir.
   - **Consume el inventario antes de aplicar** (`decrement` condicional `cantidad >= 1`); si 0 filas → aborta con «sin stock».
   - Aplica el efecto (`ServicioUsoObjetos::aplicar`), escribe log y evento de vista.
   - `turnManager->consumeAction($actor)`; **no** consume movimiento.
   - `setPendingAction(null)`, `syncViewData`, `guardar` sesión, `nextActor()`.
6. La IA **no usa objetos** en MVP (`AgregadoBatalla::ejecutarAccion` no tiene inventario — HECHO del flujo actual); se difiere.

**Tabla de objetivos:**

| Item | Objetivo |
|---|---|
| Poción/Súper/Hiper/Máxima/Cura Total/Restaurar Todo | Aliado vivo (incluido el actor) |
| Revivir/Max Revivir | Aliado debilitado |
| Mejora de stat | Aliado vivo |
| Reductor de stat | Rival vivo |
| Resistencia de tipo | Aliado vivo (incluido el actor) |

**Efectos (defaults):**

| Item | Efecto |
|---|---|
| Poción / Súper / Hiper / Máxima | +20 / +40 / +60 / +100 % HP máximo (`curarHpCantidad`) |
| Cura Total | Estado mayor + confusión |
| Restaurar Todo | HP 100 % + estado + `restaurarUsos()` (`EstadoMovimientos::reiniciar`) |
| Revivir | `revivir(0.5)` |
| Max Revivir | `revivir(1.0)` + estado limpio |
| Mejora stat | `aplicarTemporal(stat, 1.30)` 5 turnos, sin stack |
| Mejora crítico | `CondicionVolatil(CRITICO_MEJORADO, turnos:5)` |
| Mejora precisión | `aplicarTemporal(PRECISION, 1.30)` y `EVASION, 1.30` |
| Reductor stat | `aplicarTemporal(stat, 0.70)` 5 turnos al rival |
| Resistencia tipo | `ResistenciasTemporales::aplicar(tipo, 0.70)` 5 turnos |

### 6.7 Pérdida por derrota

- Se mantiene `intdiv(cantidad, 2)` para cada grupo — HECHO `FinalizarExploracionHandler.php:615-676`.
- Se añade para `items`: `tipo = 'baya'|'bonguri'`, `id = clave`, `label = nombre`; `DTOObjetoPerdido` ya acepta `label`/`cantidad_perdida` — HECHO de su uso en `:628-633`.
- La vista resuelve nombre/icono con `CatalogoItems` (sin consultas).

### 6.8 Botín de combate futuro (fase 6)

- Se extrae `OtorgarBotinLugar` (App, reutilizable) que recibe `userId`, `habitatId`, `contexto` y aplica el mismo `CalculadorBotinExploracion` con un `ContextoBotin` reducido.
- Punto de extensión: `OtorgarRecompensasRuta::otorgar()` — hoy recibe `nivelHabitat` pero **no** `habitat_id` — HECHO `src/CombateRuta/App/OtorgarRecompensasRuta.php:73-81`; `IniciarCombateRuta` sí guarda `habitat_id` — HECHO `:69`. Habrá que propagarlo.
- Sin UI nueva en la fase 6.

---

## 7) UI

### 7.1 Exploraciones

- **Selector de modo** en el modal de exploración, antes de la duración: 3 tarjetas (Pelear / Buscar / Explorar) con descripción. Estado Alpine `modo`; se envía en `loadPreview()` y `confirmExploration()` — puntos HECHO: `resources/views/habitats/show.blade.php:1960-1997`, `:2018-2055`.
- **Preview:** muestra modo, recompensas esperadas y badge «Conoce el terreno» si `del_habitat`. El cálculo viene del backend (`preview()` — HECHO `ExploracionActivaController.php:78-138`); la vista no calcula.
- **Tarjeta activa:** badge del modo junto a hábitat/nivel.
- **Resumen de resultados:** nueva sección «Objetos encontrados» (icono + nombre + cantidad) en `resources/views/exploraciones/index.blade.php:305-362`; los items llegan ya resueltos desde `ResultadoExploracionVista`.
- **Objetos perdidos:** ramas `baya`/`bonguri` en `_perdidas` (vía el presentador, sin lógica en Blade).

### 7.2 Granja

- En el bloque de cada familia (`habitats/show.blade.php:1152-1170`), sección «Plantaciones»:
  - Grid de `nivel*2` huecos; los no desbloqueados muestran candado y «Mejora el nido».
  - Hueco vacío: botón «Plantar» → selector de bayas con cantidad del inventario (solo `baya:*`).
  - Hueco plantado: icono, barra de progreso (`progreso_pct` del backend), stock (`0..3`), botón «Cosechar» habilitado solo si `stock > 0`.
- Reutiliza `accionGranja()` — HECHO `:1660-1717`.
- El bonguri del hábitat se muestra en la cabecera de la granja (icono + nombre), como información de botín.
- **Nada de cálculos en JS**: el backend entrega `stock`, `progreso_pct`, `puede_cosechar`, `estado`.

### 7.3 Forja

- Página `/forja` o modal dentro de la granja. **Recomendación:** modal en `habitats/show.blade.php` para MVP (junto a Granja), con pestaña «Forja».
- Lista de recetas agrupadas por categoría; cada una con ingredientes (icono, requerido/disponible), resultado y botón «Fabricar» deshabilitado si `!puedeFabricar` con motivo.
- Sin cálculos: `ListarRecetas` entrega todo.

### 7.4 Combate

- Menú de objetos en el panel de acciones (nuevo partial `_items-panel.blade.php`), visible en `phase = player_target`:
  - lista de `OpcionObjeto` con icono, nombre, cantidad y `aplicable`.
- Fase `player_item_target`: reutiliza las cartas; `BatallaVistaPresenter` marca aliados y/o rivales según `ObjetivosItem` (cambio en `marcarInteraccion()` para aceptar equipos permitidos en lugar de fijar `$teamIdx === 1` — hueco HECHO `BatallaVistaPresenter.php:112-121`).
- Log: «X usó Poción en Y», «Y recuperó N PS».
- Iconos: `CatalogoObjetos::iconoDe()` deja de devolver vacío — HECHO del hueco `CatalogoObjetos.php:41-44`.
- **Iconos faltantes (39):** 8 pociones, recipiente, 7 mejoras, 5 reductores, 18 resistencias. Estrategia MVP:
  - Resistencias: reutilizar `/images/type/{slug}.webp` (semánticamente correcto).
  - Mejoras/reductores: reutilizar el icono de la baya de origen (documentado como temporal).
  - Pociones/recipiente: requieren icono propio; fase de iconos posterior. Fallback `/images/misc/unknown.webp`.
  - `roseli-berry.webp` falta (Baya Hibis) — HECHO del análisis; fallback `unknown`.
- Bonguris: PNG actuales con prefijo `80px-`; normalizar a WebP en la fase de iconos (no bloqueante).

---

## 8) Seguridad, concurrencia e idempotencia

| Riesgo | Control |
|---|---|
| **Claves arbitrarias** desde cliente | Toda clave se valida contra `CatalogoItems`/`CatalogoRecetas`/`ClaveBaya`; `item_key` nunca llega libre a `player_inventory` (regla nueva). |
| **IDOR en nidos/plantaciones** | `Nido`/`Refugio`/`Complemento` no usan `BelongsToUser` — HECHO `app/Models/Nido.php:14-36`. Todos los endpoints resuelven primero `AsegurarGranja::para(userId, habitatId)` y luego verifican `nest.farm_id`; nunca confían en `nest_id` de la URL. |
| **IDOR en canje/cosecha** | La fila se carga con `where('nest_id', $nido->id)` y el `nido` sale de la granja del usuario. |
| **Doble cosecha** | `lockForUpdate` + recálculo de stock dentro de la transacción + `harvest_count` actualizado; `UNIQUE (nest_id, slot_index)` evita doble plantación. |
| **Doble fabricación** | Transacción + `lockForUpdate` ordenado por clave + validación previa de todos los ingredientes. Sin idempotency key en MVP; el frontend deshabilita el botón. |
| **Doble uso de objeto** | `pendingAction` único + `processing`/`animAttackerId` bloquean doble commit (patrón existente — HECHO `Combate.php:1108-1114`, `:824-841`); el decremento es condicional (`where cantidad >= 1`), por lo que dos pestañas no pueden gastar el mismo stock. |
| **Saldos negativos** | `decrement` condicional; nunca se hace `cantidad = cantidad - n` sin comprobar. |
| **Doble botín de exploración** | Idempotencia por `regreso !== null` — HECHO `FinalizarExploracionHandler.php:78-83`; el botín va dentro de la misma transacción. |
| **Explotación de `BOTIN` con 72 h** | Topes duros por expedición (3 bayas + 2 bonguris) y rendimientos decrecientes por duración. |
| **Objetos gratis al equipar** | Validación de propiedad + descuento transaccional (G4). |
| **Consumo no devuelto** | Se descuenta al equipar (D12); si se cambia el objeto, el anterior no se devuelve (decisión §12). |
| **Autorización de equipos** | `abort_unless($reclutado?->user_id === $userId, 404)` ya existe — HECHO `src/Equipos/App/ActualizarObjetosEquipo.php:29`; se mantiene. |
| **Regresión de payloads de batalla** | `SESSION_VERSION` 21 y descarte limpio de versiones incompatibles — HECHO `app/Support/BattleSessionService.php:96-100`. |

**Transacciones:**
- Forja, plantación, cosecha y equipamiento usan `DB::transaction` explícito (patrón `MejorarNido` — HECHO `:29-60`).
- Exploración usa `UnitOfWork`/`CommandBus` (ya transaccional — HECHO `CommandBus.php:6-10`).
- Combate: el estado vive en sesión; la transacción cubre **solo** el decremento de inventario. Se documenta el orden «validar → consumir → aplicar» para no regalar objetos.

---

## 9) Compatibilidad y migración

| Caso | Estrategia |
|---|---|
| Exploraciones con `modo = null` | `ModoExploracion::desde(null) = MIXTO`; mismos pesos 45/20/15/10/10. |
| Resultados persistidos sin `items` | `resultado['items'] ?? []` en transformador y presentador; Blade con `@if(!empty(...))`. |
| Exploraciones en curso al desplegar | No se toca la fila; `modo` nullable y `bonguri` solo afecta al cálculo final. |
| Batallas en curso | `SESSION_VERSION` 21; v20 se descarta al cargar (comportamiento existente). Aviso al usuario: la batalla se reinicia. |
| `sitrus_berry`/`lum_berry` vs `baya:*` | Mapping explícito; no se migra `obj_equipados`; `baya:sitrus` es material de forja/plantación, `sitrus_berry` es objeto equipable. |
| `obj_equipados` legacy con claves no inventariadas | Tolerancia: se muestran y funcionan en batalla; al intentar reequipar se exige inventario. No se borran datos. |
| Cancelación de exploración | `cancelar` ya marca `regreso` sin repartir recompensas — HECHO `ExploracionActivaController.php:354-359`; el botín no se otorga. |
| API antigua | `modo` opcional; `storeIndividual` devuelve campos extra sin quitar los actuales. |
| `Contratos de eventos` (`EventoExploracion`) | **No se tocan** en MVP (D6). Fase 2 podría añadir `item_key` con migración de payloads. |

**Migración de datos:**
- `habitats.bonguri`: `UPDATE habitats SET bonguri = CASE province_id …` en la propia migración (determinista desde `ProvinceSeeder`); provincia «Vacío» → `NULL`.
- No hay backfill de inventario ni de `obj_equipados`.

---

## 10) Plan de fases y dependencias

Orden alineado con D-05 (exploración → forja → combate), intercalando granja antes de la forja porque comparte el inventario de bayas.

| Fase | Entregable | Depende de | Criterios de aceptación |
|---|---|---|---|
| **F1 — Modo + bonguri + botín** | Migraciones `modo` y `bonguri`; `ModoExploracion`, `PerfilExploracion`, `ClaveBonguri`; `CatalogoItems` (bayas+bonguris); `CalculadorBotinExploracion`; `items` en `ResultadoRecompensas`; `PersistirRecompensas`; transformador/presentador/vista; pérdidas. | — | Ver §13.1 |
| **F2 — Plantación y cosecha** | Migración `nest_plantings`; `PlantarBaya`, `CosecharPlantacion`, `ReglasPlantacion`; `PlantacionGranja`; ampliación `NidoGranja`/`GranjaDetalle`; UI de huecos. | F1 (catálogo `baya:*`) | Ver §13.2 |
| **F3 — Influencia + bonus de hábitat** | `EsDelHabitat`; `ConsultorPlantaciones`; `factoresPlantacion` en `ContextoBotin`; auto-cosecha opcional (`RECOLECTOR` + `BOTIN`). | F1, F2 | Ver §13.3 |
| **F4 — Forja** | `src/Forja`; `CatalogoRecetas`; `ListarRecetas`, `Fabricar`; endpoints; modal. | F1 | Ver §13.4 |
| **F5 — Uso de objeto en combate** | `AccionObjeto`, `ServicioUsoObjetos`, `CatalogoEfectosItem`, `ResistenciasTemporales`, `restaurarUsos()`; `DTOAccionObjeto`; fases `player_item`/`player_item_target`; `UsarObjetoEnCombate`; UI. | F4 | Ver §13.5 |
| **F6 — Equipables + iconos** | Validación de propiedad y descuento al equipar; activación automática de bayas equipadas; iconos definitivos. | F1, F4 | Ver §13.6 |
| **F7 — Botín de combate (futuro)** | `OtorgarBotinLugar` reutilizable; hook en ruta/entrenador. | F1 | Ver §13.7 |

**Dependencias técnicas:**
- F1 es prerrequisito de todo (catálogo e inventario).
- F2 no depende de F3, pero F3 sí de F2.
- F4 solo necesita F1; puede ejecutarse en paralelo con F2/F3 si se aíslan archivos.
- F5 necesita F4 (necesita objetos fabricados) y el bump de sesión.

---

## 11) Riesgos

| Nivel | Riesgo | Mitigación |
|---|---|---|
| **Alto** | Regresión en el contrato de exploración (`ResultadoRecompensas`) | `items` con default `[]`; no reordenar; actualizar `sumarHallazgos`/`restarPerdidas`/transformador en el mismo cambio. |
| **Alto** | Botín duplicado o fuera de transacción | Cálculo y persistencia dentro del `CommandBus`; idempotencia por `regreso`. |
| **Alto** | Cambio del núcleo de combate | `DTOAccionObjeto` separado, servicio aislado, bump de versión, no tocar la cadena de daño. |
| **Alto** | Balance de pociones/revivir y stacks | Magnitudes por porcentaje; sin stack (refresco); topes de botín; telemetría. |
| **Alto** | Equipar sin coste (agujero previo) | Validación + descuento transaccional; criterio de aceptación explícito. |
| **Medio** | Concurrencia plantar/cosechar | `UNIQUE`, `lockForUpdate`, recálculo de stock. |
| **Medio** | `Nido`/`Refugio` sin `BelongsToUser` | Autorización siempre por granja; no confiar en IDs de URL. |
| **Medio** | Claves de baya del cliente | Validación contra enum; nunca `item_key` libre. |
| **Medio** | Duplicidad `baya:sitrus` vs `sitrus_berry` | Mapping explícito; no unificar en esta fase. |
| **Medio** | Bonus «del hábitat» ambiguo | MVP por especie nativa; documentado en UI; `habitat_origen_id` en fase posterior. |
| **Medio** | Sin tabla de bayas nativas | Tabla global ponderada + bonus de plantación; `habitat_berries` diferida. |
| **Medio** | Iconos incompletos (39 + roseli) | Fallback `unknown`; fase de iconos separada. |
| **Bajo** | Zonas horarias en crecimiento | Timestamps UTC y diferencias absolutas de 24 h. |
| **Bajo** | `player_inventory` sin FK | Catálogo estable; script de renombrado si cambia. |
| **Bajo** | Payload grande de granja tras cada acción | Ya ocurre hoy — HECHO `GranjaController.php:55-60`; aceptado en MVP. |

---

## 12) Decisiones abiertas (con recomendación)

1. **¿«3 cosechas acumulables» = stock máximo 3 o 3 cosechas totales por planta?**
   - **Recomendación:** stock máximo 3, planta persistente. Si es total, la fila se borra al llegar a 3 (cambio localizado en `ReglasPlantacion`).
2. **¿Plantar consume una baya?**
   - **Recomendación:** sí, 1 baya por hueco.
3. **¿Cosecha manual o automática?**
   - **Recomendación:** manual en MVP; auto-cosecha con `RECOLECTOR` + `BOTIN` en F3.
4. **¿Replantar sin cosechar?**
   - **Recomendación:** permitido, se pierde el stock; aviso en UI.
5. **¿Devolver el objeto al desequipar?**
   - **Recomendación:** no en MVP (simplicidad). Si se quiere, se añade devolución en `ActualizarObjetosEquipo` con el mismo puerto.
6. **¿Las pociones curan porcentaje o cantidad fija?**
   - **Recomendación:** porcentaje (20/40/60/100).
7. **¿«Restaurar Todo» y «Max Revivir» restauran PP?**
   - **Recomendación:** sí, `restaurarUsos()` (ya existe `reiniciar()`).
8. **¿Buff/debuff de la misma stat stackean o refrescan?**
   - **Recomendación:** refrescar duración sin multiplicar (evita ×1,69/×0,49).
9. **¿Resistencia de tipo solo a supereficaces o a cualquier ataque de ese tipo?**
   - **Recomendación:** cualquier ataque de ese tipo (el texto no restringe).
10. **¿Lagro sube precisión y evasión en un solo objeto?**
    - **Recomendación:** sí, `mejora:precision` aplica +30 % a ambas.
11. **¿Zonlan +1 o +2 niveles de crítico?**
    - **Recomendación:** +1 (según la especificación), modelado con `CRITICO_MEJORADO` (×2,0 de probabilidad).
12. **¿La forja es instantánea o con tiempos?**
    - **Recomendación:** instantánea en MVP; `craft_jobs` diferido.
13. **¿Cantidad de fabricación por petición?**
    - **Recomendación:** MVP 1; el DTO ya acepta `cantidad`.
14. **¿La IA usa objetos?**
    - **Recomendación:** no en MVP.
15. **¿Los objetos fabricados son equipables?**
    - **Recomendación:** no; solo bayas base y los 8 objetos actuales.
16. **¿Drop de bonguri garantizado o probabilístico?**
    - **Recomendación:** probabilístico por modo (tabla §6.2).

---

## 13) Criterios de aceptación técnicos

### 13.1 F1 — Modo + bonguri + botín
- [ ] `exploraciones_activas.modo` existe, nullable, y una fila `null` se comporta exactamente como hoy (45/20/15/10/10).
- [ ] `habitats.bonguri` tiene valor válido o `NULL` con fallback documentado; ningún hábitat queda sin decisión.
- [ ] Una expedición solo puede devolver el bonguri de su hábitat.
- [ ] Finalizar una exploración incrementa el inventario exactamente con las cantidades del botín.
- [ ] Repetir la finalización no duplica el botín.
- [ ] El resumen muestra bayas/bonguris con nombre e icono.
- [ ] En derrota se pierde `intdiv(cantidad, 2)` y aparece en `objetos_perdidos`.
- [ ] Una clave desconocida nunca llega a `player_inventory`.
- [ ] Topes: máx. 3 bayas + 2 bonguris por expedición.
- [ ] Exploraciones antiguas sin `items` no rompen la vista.

### 13.2 F2 — Plantación y cosecha
- [ ] Un nido de nivel N ofrece `N*2` huecos; nivel 0 → 0.
- [ ] No se puede plantar fuera de rango ni duplicar `(nest_id, slot_index)`.
- [ ] Plantar consume 1 baya; sin saldo → 422 y sin cambios.
- [ ] Se pueden repetir bayas en huecos distintos.
- [ ] El stock se calcula a 24/48/72 h y se topa en 3.
- [ ] Cosechar dos veces seguidas no duplica.
- [ ] La granja devuelve estado/progreso/stock; la UI no calcula.
- [ ] Un usuario no puede plantar/cosechar en nidos de otro.
- [ ] Baya fuera del catálogo → 422.

### 13.3 F3 — Influencia + bonus
- [ ] Un Pokémon de especie nativa recibe el bonus; uno no nativo no.
- [ ] El bonus se aplica una sola vez y se refleja en el resultado.
- [ ] Las plantaciones solo afectan a la misma granja/hábitat; tope ×2,0.
- [ ] No afectan a bonguri ni a otros hábitats.

### 13.4 F4 — Forja
- [ ] Cada receta consume exactamente sus ingredientes y produce exactamente su resultado.
- [ ] Si falta un ingrediente: 422 y **no** descuenta nada.
- [ ] Dos peticiones concurrentes no dejan saldos negativos.
- [ ] `ListarRecetas` devuelve `puedeFabricar` correcto.
- [ ] Receta inexistente → 404/422.
- [ ] El dominio de `Forja` no importa Eloquent/HTTP.

### 13.5 F5 — Combate
- [ ] Usar un objeto consume la acción y no consume movimiento.
- [ ] Usar un objeto descuenta 1 del inventario; sin stock no se ofrece/no se ejecuta.
- [ ] Poción solo a aliados vivos; revivir solo a debilitados; reductor solo a rivales vivos.
- [ ] Buff +30 % dura 5 rondas y se retira; debuff −30 % ídem.
- [ ] Resistencia de tipo −30 % durante 5 rondas.
- [ ] Crítico +1 nivel durante 5 rondas.
- [ ] Restaurar Todo y Max Revivir reinician usos.
- [ ] La UI permite seleccionar aliados y rivales según el item.
- [ ] El log registra uso y efecto.
- [ ] No hay regresiones en ruta/entrenador/gimnasio/mazmorra/exploración.
- [ ] Payloads v20 se descartan limpiamente.

### 13.6 F6 — Equipables e iconos
- [ ] Solo se equipa un item poseído y equipable.
- [ ] Equipar descuenta 1; sin saldo → 422.
- [ ] `obj_equipados` sigue siendo lista de una clave.
- [ ] Item no equipable → 422.
- [ ] `CatalogoObjetos::iconoDe()` devuelve ruta para todas las claves conocidas.

### 13.7 F7 — Botín de combate
- [ ] `OtorgarBotinLugar` se invoca desde ruta/entrenador con `habitat_id` propagado.
- [ ] Respeta topes y no entrega bonguri de otro hábitat.
- [ ] Sin UI nueva.

### 13.8 Verificación estática (sin tests)
- `vendor/bin/phpstan analyse --memory-limit=1G` (nivel 6, `src/` y `app/` — HECHO `phpstan.neon:9-14`).
- `vendor/bin/pint --dirty --format agent` (regla del proyecto).
- `php -l` sobre cada archivo PHP nuevo/modificado.
- `php artisan view:cache` y `php artisan view:clear` (compila Blade).
- `php artisan route:list --path=forja` y `--path=farm` (rutas registradas).
- `vendor/bin/deptrac analyse` (dirección de capas; `deptrac.yaml` — HECHO).
- Revisión manual de contratos: `ResultadoRecompensas`, `Transformador`, `GranjaDetalle`, `NidoGranja`, `DTOAccionBatalla`/`DTOAccionObjeto`.
- `grep -rn "array<string" src/<módulo nuevo>` para detectar arrays como contrato.
- **No** se ejecutan tests ni se crean/actualizan archivos en `tests/`.

---

## Archivos inspeccionados

- `.opencode/work/TASK-011/request.md`, `.opencode/work/TASK-011/analysis.md` (A1 + A2, 1686 líneas).
- `.ai/rules/index.md`, `ddd-modules.md`, `arrays-dto-collections.md`, `frontend-minimal-logic.md`, `domain-repositories.md`, `infra-controllers.md`.
- `docs/ddd.md`, `AGENTS.md`, `phpstan.neon`, `deptrac.yaml`, `composer.json`.
- `src/Shared/Domain/Collection.php`, `ValueObjects/MapaConteo.php`, `Collections/ItemCarameloCollection.php`, `DataTransferObjects/ItemResuelto.php`, `Bus/CommandBus.php`.
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php`, `Domain/CalculadorRecompensas.php`, `Domain/SimuladorEncuentros.php`, `Domain/ReglasExploracion.php`, `Domain/EconomiaRecompensas.php`, `Domain/RolExploracion.php`, `App/PersistirRecompensas.php`, `App/FinalizarExploracionHandler.php`, `App/ProcesarExploracionHandler.php` (grep), `Presentation/TransformadorResultadoExploracion.php`, `Presentation/PresentadorExploraciones.php`, `Presentation/ResultadoExploracionVista.php`.
- `src/Granjas/**` (listado completo), `App/ObtenerGranja.php`, `App/InventarioGranja.php`, `App/MejorarNido.php`, `App/AsegurarGranja.php`, `App/FamiliaEnHabitat.php`, `Infra/Controllers/GranjaController.php`, `Domain/DataTransferObjects/GranjaDetalle.php`, `NidoGranja.php`, `FamiliaGranja.php`, `InventarioCaramelosGranja.php`, `Domain/Collections/FamiliasGranjaCollection.php`.
- `src/Battle/Domain/Catalog/CatalogoObjetos.php`, `Enums/ClaveItem.php`, `Enums/FaseCombate.php`, `Enums/ClaveCondicion.php`, `ValueObjects/EstadoObjeto.php`, `ValueObjects/EstadoMovimientos.php`, `ValueObjects/MultiplicadoresStats.php`, `ValueObjects/CambioStat.php`, `ValueObjects/CondicionVolatil.php`, `Effects/EfectoModificadorDanio.php`, `Chain/ManejadorCritico.php`, `EquipoBatalla.php`, `Combatiente.php` (grep), `AgregadoBatalla.php` (grep), `Presentation/DTOAccionBatalla.php`.
- `app/Support/ItemCatalogo.php`, `BattleSessionService.php`, `EquiposVistaPresenter.php`; `app/Providers/BattleEffectServiceProvider.php`; `app/Livewire/Combate.php` (secciones 185-314, 760-1179, 1530-1560); `app/Livewire/Presenters/BatallaVistaPresenter.php` (1-140); `app/Http/Controllers/ExploracionActivaController.php` (78-142, 240-359); `app/Http/Controllers/TeamController.php` (190-244); `app/Models/ExploracionActiva.php`, `Habitat.php`, `Nido.php`, `Reclutado.php`.
- `resources/views/habitats/show.blade.php` (grep + 1960-2019); `resources/views/exploraciones/index.blade.php` (grep); `resources/views/livewire/partials/moves-panel.blade.php`; `resources/views/livewire/_pokemon-card.blade.php` (grep).
- `database/migrations/2026_08_29_000007_create_player_inventory_table.php`, `2026_10_26_000001_create_farm_tables.php`, `2026_02_24_201538_create_pokemon_habitat_table.php` (vía análisis), listado de migraciones; `database/seeders/ProvinceSeeder.php`.
- `routes/granjas.php`, `routes/exploraciones.php`.
- `src/Equipos/App/ActualizarObjetosEquipo.php`; `src/CombateRuta/App/OtorgarRecompensasRuta.php` (60-119).
- Assets: `public/images/bonguri/` (7 PNG), `public/images/berry/` (listado parcial).

## Decisiones tomadas

1. Catálogo de items en `src/Shared/Domain/Items` (shared kernel) por la prohibición de dependencias entre `Domain` de módulos.
2. Forja en `src/Forja` (módulo nuevo) y plantación en `src/Granjas`.
3. Botín calculado al finalizar, aditivo a `ResultadoRecompensas` con default `[]`.
4. Modo nullable (`null` = `MIXTO`) y bonguri por hábitat con backfill desde provincia.
5. `nest_plantings` con una fila por hueco y stock calculado; `UNIQUE (nest_id, slot_index)`.
6. Uso de objeto con `DTOAccionObjeto` separado, `ServicioUsoObjetos` puro y descuento de inventario antes de aplicar.
7. Buffs de stats vía `MultiplicadoresStats::aplicarTemporal` (sin stack); crítico vía `CRITICO_MEJORADO`; resistencia vía `ResistenciasTemporales` nuevo.
8. Un único puerto `InventarioJugador` en `src/Shared/Domain/Ports`; `ConsultorPlantaciones` en `Exploraciones.Domain` implementado por `Granjas.Infra`.
9. Bump de `SESSION_VERSION` 20 → 21.

## Verificación ejecutada

- Lectura estática de los archivos listados; `grep`/`rg` sobre objetos, bayas, inventario, granja, exploración, combate y UI.
- Listado de `database/migrations` y de `public/images/bonguri`; comprobación de PHP 8.4.18.
- Lectura de `phpstan.neon`, `deptrac.yaml` y `composer.json` para fijar el plan de verificación.
- **No** se ejecutaron tests, migraciones, seeders ni comandos de escritura.
- **No** se modificó ningún archivo.

## Problemas encontrados

1. `Combatiente`/`DTOAccionBatalla` y la sesión están versionados; el cambio de DTO exige bump y descarte de payloads antiguos.
2. `EfectoModificadorDanio` es permanente; la resistencia temporal exige estructura nueva (resuelta con `ResistenciasTemporales`).
3. `CondicionVolatil` ya decrementa `turnos` en `AgregadoBatalla`, pero **A VERIFICAR** que `CRITICO_MEJORADO` se elimine al llegar a 0 en el bloque de condiciones (se infiere del patrón `turnos--`).
4. `MapaFactores` no fue leído; **A VERIFICAR** su API (`obtener`, `con`, `toArray`) antes de usarlo en `ContextoBotin`.
5. `ProbabilidadCaptura::intentarConFactores` no fue leída; **A VERIFICAR** su firma antes de aplicar `factorCaptura`.
6. La autorización de `Nido`/`Refugio`/`Complemento` no usa `BelongsToUser`; todos los endpoints nuevos deben resolver la granja primero.
7. `roseli-berry.webp` falta y 39 iconos nuevos no existen; la fase de iconos queda bloqueada hasta decidir estilo.
8. `OtorgarRecompensasRuta` no recibe `habitat_id`; el hook de botín de combate (F7) exige propagarlo.

## Riesgos pendientes

- **Balance:** los valores de botín, multiplicadores por modo y magnitudes de pociones son propuestas sin telemetría.
- **Semántica de «3 cosechas»:** stock vs total cambia el ciclo de vida de la fila.
- **Origen del Pokémon:** la comprobación por especie nativa es una aproximación; `habitat_origen_id` queda para fase posterior.
- **Concurrencia en combate:** el estado en sesión no es transaccional; se mitiga con orden «validar → consumir → aplicar» y single-flight de UI.
- **Sesión de batalla:** el bump invalida batallas en curso (aceptable, pero debe comunicarse).
- **Testing:** no se ejecutó nada por restricción; la verificación es estática + manual.

---

**Nota de proceso:** se alcanzó el máximo de pasos del agente durante la inspección. No se modificó ningún archivo, no se crearon migraciones y no se tocaron tests. Los puntos marcados `A VERIFICAR` requieren una lectura puntual antes de implementar: `MapaFactores`, `ProbabilidadCaptura::intentarConFactores`, el bloque de expiración de condiciones en `AgregadoBatalla` y la firma de `Combatiente::multiplicadorDanioRecibido`.
</subagent>