# Objetivo

Realizar un analisis arquitectonico profundo del codigo existente del proyecto Pokemon Battle Game, identificando problemas en seis areas clave (metodos tipados, primitive obsession, DTOs, encapsulamiento, CodeSniffer, Clean Architecture) y proponiendo mejoras concretas para cada una.

---

# Alcance

Se analiza la totalidad del codigo fuente en `src/` y `app/`, incluyendo:
- `src/Battle/Domain/`, `App/`, `Infrastructure/`, `Presentation/`
- `src/Pokemon/Domain/` (Stats, Movement)
- `src/Habitats/Domain/`, `App/`, `Infra/`
- `src/Equipos/Domain/`, `App/`
- `src/Reclutamiento/Domain/`, `App/`
- `src/Shared/Domain/`, `Tipos/`
- `app/Livewire/`, `app/Http/Controllers/`, `app/Models/`, `app/Providers/`, `app/Enums/`, `app/Crud/`
- `routes/`

Se excluyen del alcance: vistas Blade, assets frontend, migraciones, seeders, tests.

---

# Modulos afectados

| Modulo | Impacto |
|--------|---------|
| `src/Battle/Domain/` | Alto - multiples violaciones de encapsulamiento, primitive obsession, tipos faltantes |
| `src/Battle/App/` | Medio - DTOs faltantes en interfaz del caso de uso |
| `src/Battle/Infrastructure/` | Bajo - solo FabricaBatallaMock, aceptable |
| `src/Battle/Presentation/` | Bajo - buen uso de DTO con Wireable |
| `src/Pokemon/Domain/` | Alto - primitive obsession, tipos faltantes, clases vacias/duplicadas |
| `src/Habitats/` | Medio - retorno de arrays vs DTOs en casos de uso |
| `src/Equipos/` | Critico - violacion de Clean Architecture (src -> app) |
| `src/Reclutamiento/` | Critico - violacion de Clean Architecture (src -> app) |
| `src/Shared/Tipos/` | Bajo - bien modelado |
| `app/Livewire/Combate.php` | Alto - DI faltante, logica de dominio en presentacion, metodo largo (686 lines) |
| `app/Http/Controllers/` | Medio - tipos de retorno faltantes |
| `app/Providers/` | Bajo - providers vacios o incompletos |
| `routes/` | Medio - instanciacion directa de casos de uso sin DI container |

---

# Diseno tecnico

## Estrategia general

Las mejoras se agrupan en 5 oleadas para minimizar el riesgo de regresion:

1. **Oleada 1 (Inmediata)**: Value Objects y enumeraciones para reemplazar strings magicos y primitivos.
2. **Oleada 2 (Inmediata)**: Correccion de tipos de retorno y parametros faltantes.
3. **Oleada 3 (Corto plazo)**: Encapsulamiento de propiedades publicas y DTOs en fronteras.
4. **Oleada 4 (Medio plazo)**: Correccion de violaciones Clean Architecture (src -> app).
5. **Oleada 5 (Medio plazo)**: Configuracion de CodeSniffer/Pint y limpieza de codigo muerto.

## Principios rectores

- Todo `string` que representa un conjunto cerrado de valores debe ser un enum o Value Object.
- Toda propiedad publica debe ser `private` o `readonly` con getter cuando sea necesario.
- `src/` NO puede importar nada de `app/` ni de `Illuminate\`.
- Los metodos deben tener tipos de retorno explicitos (`: void`, `: float`, `: array`, etc.).
- Las fronteras entre capas deben usar DTOs tipados, no arrays asociativos.

---

# Cambios backend

## BLOQUE 1: Value Objects y Enumeraciones (Primitive Obsession)

### B1.1 - Crear `TipoClima` enum

- **Modulo y archivo**: `src/Battle/Domain/Enums/TipoClima.php` (nuevo)
- **Problema actual**: El clima se representa como `string` en 13 lugares diferentes:
  ```php
  // AgregadoBatalla.php:16
  public string $weather = 'none';
  // ManejadorClima.php:22
  if ($weather === 'none' || $weather === '') { return $dano; }
  // EfectoInvocadorClima.php:30
  $battle->weather = $this->clima;
  // ServicioEjecucionBatalla.php:27
  string $weather,
  ```
- **Solucion propuesta**: Enum `TipoClima` con cases NONE, SEQUIA, DILUVIO, NIEBLA, GRANIZO, TORMENTA_ARENA, TURBULENCIAS.
  Reemplazar `string $weather` por `TipoClima $weather` en todas las firmas.
- **Prioridad**: Alta
- **Esfuerzo**: 1 dia (afecta ~10 archivos)

### B1.2 - Crear `EstadoPokemon` enum

- **Modulo y archivo**: `src/Battle/Domain/Enums/EstadoPokemon.php` (nuevo)
- **Problema actual**: Estados como string magico:
  ```php
  // Combatiente.php:27
  public string $estado = 'none';
  // Combatiente.php:13-21
  public const STATUS_LABELS = ['burn' => 'quemadura', ...];
  ```
- **Solucion propuesta**: Enum `EstadoPokemon` con label(), causaDanoPorRonda(). Eliminar STATUS_LABELS.
- **Prioridad**: Alta
- **Esfuerzo**: 0.5 dias

### B1.3 - Crear `CategoriaMovimiento` enum

- **Modulo y archivo**: `src/Battle/Domain/Enums/CategoriaMovimiento.php` (nuevo)
- **Problema actual**: `public readonly string $categoria` usada como 'fisico', 'especial', 'estado'.
- **Solucion propuesta**: Enum `CategoriaMovimiento: string`.
- **Prioridad**: Alta
- **Esfuerzo**: 0.5 dias

### B1.4 - Crear `NombreStat` enum

- **Modulo y archivo**: `src/Battle/Domain/Enums/NombreStat.php` (nuevo)
- **Problema actual**: Stats referenciados como strings: `['attack' => 0, 'defense' => 0, ...]`.
- **Solucion propuesta**: Enum `NombreStat: string`.
- **Prioridad**: Alta
- **Esfuerzo**: 1 dia

### B1.5 - Crear `EtapasStats` Value Object

- **Modulo y archivo**: `src/Battle/Domain/ValueObjects/EtapasStats.php` (nuevo)
- **Problema actual**: `array<string, int>` sin validacion de rango -6..+6.
- **Solucion propuesta**: Clase inmutable con validacion de rango, metodos `aplicarCambio()`, `obtener()`, `obtenerMultiplicador()`.
- **Prioridad**: Media
- **Esfuerzo**: 1 dia

### B1.6 - Crear `ColeccionMovimientos` tipada

- **Modulo y archivo**: `src/Battle/Domain/ValueObjects/ColeccionMovimientos.php` (nuevo)
- **Problema actual**: `public array $moves` en PokemonEntity (comentario: "pasar a collection y entity").
- **Solucion propuesta**: Extender Collection con type check para MovimientoBatalla.
- **Prioridad**: Media
- **Esfuerzo**: 0.5 dias

### B1.7 - Eliminar `MovementEffectiveness.php` (clase duplicada)

- **Modulo y archivo**: `src/Pokemon/Domain/Movement/MovementEffectiveness.php`
- **Problema actual**: Define exactamente la misma clase `MovementCollection` que `MovementCollection.php`.
- **Solucion propuesta**: Eliminar el archivo duplicado.
- **Prioridad**: Alta
- **Esfuerzo**: Inmediato

### B1.8 - Completar/eliminar `BattleSrv`

- **Modulo y archivo**: `src/Battle/Domain/BattleSrv.php`
- **Problema actual**: Servicio con metodos vacios (`stab() {}`, `calcularEfectividad() {}`, etc.). No utilizado.
- **Solucion propuesta**: Eliminar si no tiene referencias o marcar como @deprecated.
- **Prioridad**: Media
- **Esfuerzo**: 0.5 dias

### B1.9 - Completar/eliminar `BattleAggregate`

- **Modulo y archivo**: `src/Battle/Domain/BattleAggregate.php`
- **Problema actual**: Clase incompleta con metodos vacios y logica duplicada de `AgregadoBatalla`.
- **Solucion propuesta**: Revisar referencias. Si no se usa, eliminar. Si es WIP, documentar.
- **Prioridad**: Media
- **Esfuerzo**: 0.5 dias

---

## BLOQUE 2: Tipos de retorno y parametros

### B2.1 - Anadir tipos de retorno a metodos de `EquipoBatalla`

- **Modulo y archivo**: `src/Battle/Domain/EquipoBatalla.php`
- **Problema actual**: 9 metodos sin tipo de retorno explicito.
- **Solucion**: Anadir `: void`, `: array`, `: bool`, `: float`, `: ?Combatiente` segun corresponda.

### B2.2 - Anadir tipos de retorno a `Combate.php`

- **Modulo y archivo**: `app/Livewire/Combate.php`
- **Problema actual**: `render()`, `nuevaBatalla()`, `mount()`, `startBattle()` sin return types.

### B2.3 - Anadir tipos de retorno a Controladores

- **Archivos**: `app/Http/Controllers/*.php`
- **Problema actual**: Todos los metodos de controladores sin tipo de retorno.

### B2.4 - Anadir tipos de retorno a `BaseCrudController`

- **Archivo**: `app/Crud/Base/BaseCrudController.php`
- **Problema actual**: `index/store/update/destroy` sin return types.

**Prioridad general B2**: Alta | **Esfuerzo general B2**: 0.5 dias (cambio mecanico)

---

## BLOQUE 3: DTOs y comunicacion entre capas

### B3.1 - Crear `DTOAccionBatalla` para reemplazar `pendingAction` array

- **Modulo y archivo**: `src/Battle/Presentation/DTOAccionBatalla.php` (nuevo)
- **Problema actual**: `AgregadoBatalla::$pendingAction` es `?array` sin tipado. `Combate.php` construye arrays asociativos manualmente.
- **Solucion**: Clase DTO con propiedades tipadas que implemente `Wireable`.
- **Prioridad**: Alta | **Esfuerzo**: 0.5 dias

### B3.2 - Convertir `calcularYAplicarDano` a usar `AccionBatalla` como DTO

- **Modulo y archivo**: `src/Battle/Domain/ServicioEjecucionBatalla.php`
- **Problema actual**: 5 parametros sueltos (3 primitivos: string, bool, array de retorno).
  Ya existe `AccionBatalla` con toda la info necesaria.
- **Solucion**: Cambiar firma a `calcularYAplicarDano(AccionBatalla $action): DTOResultadoDanio`.
- **Prioridad**: Alta | **Esfuerzo**: 0.5 dias

### B3.3 - Crear `DTOResultadoDanio`

- **Modulo y archivo**: `src/Battle/Presentation/DTOResultadoDanio.php` (nuevo)
- **Problema actual**: Retorno de array asociativo `['dano' => float, 'directPct' => float]`.
- **Solucion**: Clase con propiedades `public readonly float $dano, $directPct`.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

### B3.4 - Crear `DTOHabitatDetalle`

- **Modulo y archivo**: `src/Habitats/Presentation/DTOHabitatDetalle.php` (nuevo)
- **Problema actual**: `HabitatRepository::getHabitatDetail()` retorna array asociativo.
- **Solucion**: DTO tipado con metodo `toArray()` si es necesario para Blade.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

### B3.5 - Refactor `IniciarBatalla::ejecutar()` para recibir DTOs

- **Modulo y archivo**: `src/Battle/App/IniciarBatalla.php`
- **Problema actual**: Recibe `array $team1Data, array $team2Data` sin contrato.
- **Solucion**: Crear `DTOEquipoBatalla` y usarlo como parametro.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

---

## BLOQUE 4: Encapsulamiento

### B4.1 - Encapsular propiedades de `Combatiente`

- **Modulo y archivo**: `src/Battle/Domain/Combatiente.php`
- **Problema actual**: TODAS las propiedades son publicas: `$hpActual`, `$defensaHpActual`, `$estado`, `$etapas`, `$id`, `$nombre`, `$item`, etc. Ademas usa `#[AllowDynamicProperties]`.
- **Solucion**: Propiedades privadas con getters. Implementar `__serialize()`/`__unserialize()` para compatibilidad de sesion.
  **Riesgo**: La serializacion de sesion se rompe sin manejo cuidadoso.
- **Prioridad**: Alta | **Esfuerzo**: 2 dias

### B4.2 - Encapsular propiedades de `AgregadoBatalla`

- **Modulo y archivo**: `src/Battle/Domain/AgregadoBatalla.php`
- **Problema actual**: `$turnManager`, `$damageChain`, `$subject`, `$log`, `$pendingAction`, `$weather` son publicos.
- **Solucion**: Privadas con getters. `pendingAction` migrar a `DTOAccionBatalla`. `weather` migrar a `TipoClima`.
- **Prioridad**: Alta | **Esfuerzo**: 1 dia

### B4.3 - Encapsular `EquipoBatalla::$combatants`

- **Modulo y archivo**: `src/Battle/Domain/EquipoBatalla.php`
- **Problema actual**: `public array $combatants = []`.
- **Solucion**: `private array $combatants` con getter y metodos de acceso (find, filter).
- **Prioridad**: Alta | **Esfuerzo**: 0.5 dias

### B4.4 - Encapsular `GestorTurnos::$round`

- **Archivo**: `src/Battle/Domain/GestorTurnos.php`
- **Problema**: `public int $round = 0`.
- **Solucion**: `private int $round` con getter `round(): int`.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

### B4.5 - Encapsular `PokemonEntity`

- **Archivo**: `src/Pokemon/Domain/PokemonEntity.php`
- **Problema**: Propiedades publicas, `$objetos` y `$habilidad` sin tipo, `$moves` como array.
- **Solucion**: Privadas con getters. Tipar objetos y habilidad. Migrar `$moves` a `ColeccionMovimientos`.
- **Prioridad**: Alta | **Esfuerzo**: 1 dia

---

## BLOQUE 5: Clean Architecture - Violaciones src -> app

### B5.1 - Extraer `TeamSrv` a infraestructura (CRITICO)

- **Archivo**: `src/Equipos/Domain/TeamSrv.php`
- **Problema actual**: Servicio de dominio importa `App\Models\Team` e `Illuminate\Database\Eloquent\Collection`:
  ```php
  use App\Models\Team;
  use Illuminate\Database\Eloquent\Collection;
  ```
- **Solucion**:
  1. Crear `TeamRepositoryInterface` en Domain
  2. Mover logica a `src/Equipos/Infra/EloquentTeamRepository.php`
  3. `ObtenerEquipos` caso de uso depende de la interfaz
  4. La interfaz retorna DTOs o `TeamAggregate`, nunca Eloquent Collection
- **Prioridad**: Critica | **Esfuerzo**: 2 dias

### B5.2 - Extraer `ReclutamientoSrv` a infraestructura (CRITICO)

- **Archivo**: `src/Reclutamiento/Domain/ReclutamientoSrv.php`
- **Problema actual**: Importa `App\Models\Reclutado` y `Illuminate\Database\Eloquent\Collection`.
- **Solucion**: Misma estrategia que B5.1: interfaz en Domain, implementacion en Infra.
- **Prioridad**: Critica | **Esfuerzo**: 1 dia

### B5.3 - Eliminar `aplicar` de `InterfazEfecto`

- **Archivo**: `src/Battle/Domain/Effects/InterfazEfecto.php`
- **Problema**: Metodo `aplicar()` es no-op no utilizado. El lifecycle real usa hooks especificos.
- **Solucion**: Eliminar de la interfaz y del trait `ComportamientosPorDefecto`.
- **Prioridad**: Baja | **Esfuerzo**: 0.5 dias

### B5.4 - Mover instanciacion de servicios de routes/ al contenedor DI

- **Archivos**: `routes/web.php`, `routes/habitats.php`
- **Problema**: Instanciacion directa con `new`: `new ObtenerHabitatsPorProvincia(new HabitatRepository())`.
- **Solucion**: Usar `app()->make()` o crear controladores con DI.
- **Prioridad**: Alta | **Esfuerzo**: 1 dia

### B5.5 - Inyectar `FabricaBatallaInterface` en `Combate`

- **Archivo**: `app/Livewire/Combate.php`
- **Problema**: Hard-coded `FabricaBatallaMock::createBattle()`.
- **Solucion**: Inyectar interfaz en constructor para poder cambiar a datos reales.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

### B5.6 - Inyectar `ServicioEjecucionBatalla` en `Combate`

- **Archivo**: `app/Livewire/Combate.php`
- **Problema**: Se instancia `new ServicioEjecucionBatalla(...)` cada accion.
- **Solucion**: Inyectar por constructor.
- **Prioridad**: Media | **Esfuerzo**: 0.5 dias

### B5.7 - Vincular `HabitatRepositoryInterface` con `HabitatRepository`

- **Archivo**: `app/Providers/AppServiceProvider.php`
- **Problema**: No hay binding. Se instancia directamente.
- **Solucion**: `$this->app->bind(HabitatRepositoryInterface::class, HabitatRepository::class);`
- **Prioridad**: Alta | **Esfuerzo**: 0.5 dias

---

## BLOQUE 6: CodeSniffer / Laravel Pint

### B6.1 - Crear `pint.json`

- **Archivo**: `pint.json` (raiz)
- **Problema**: Pint instalado pero sin configuracion personalizada.
- **Solucion propuesta**:
  ```json
  {
      "preset": "psr12",
      "rules": {
          "declare_strict_types": true,
          "strict_param": true,
          "no_unused_imports": true,
          "ordered_imports": { "sort_algorithm": "alpha" },
          "single_quote": true,
          "trailing_comma_in_multiline": true,
          "return_type_declaration": { "space_before": "none" },
          "no_empty_phpdoc": true,
          "phpdoc_no_access": true,
          "phpdoc_scalar": true,
          "phpdoc_summary": true,
          "phpdoc_trim": true
      }
  }
  ```
- **Prioridad**: Alta | **Esfuerzo**: 0.5 dias

### B6.2 - Reglas adicionales

1. `declare(strict_types=1)` obligatorio en todos los PHP.
2. Prohibir `#[AllowDynamicProperties]` (excepto migracion explicita).
3. Prohibir arrays asociativos como contratos publicos (preferir DTOs).
4. Evaluar PHPStan level 6+ para deteccion automatica de tipos faltantes.

---

## BLOQUE 7: Codigo muerto y limpieza

- B7.1: Eliminar `BattleSrv` (no referenciado)
- B7.2: Limpiar metodos vacios `PokemonEntity::habilidades()` y `objetos()`
- B7.3: Eliminar o implementar `CrudServiceProvider` (vacio)
- B7.4: Eliminar `TipoEntity` (clase vacia, no referenciada)
- B7.5: Verificar que `EfectoInvocadorTormentaArena.php` no duplique a `EfectoInvocadorClima` (clima='sandstorm' vs 'tormenta_arena')

---

# Cambios frontend

No se requieren cambios directos en el frontend. Los cambios en DTOs de presentacion (`aArrayVista`) requeriran verificacion de que las vistas Blade consuman las propiedades correctas, pero la estructura de los arrays de salida se mantendra compatible.

---

# Casos limite

1. **Serializacion de sesion**: Al encapsular propiedades, la serializacion PHP en sesion se rompe. Mitigacion: implementar `__serialize()`/`__unserialize()`, incrementar `SESSION_VERSION`.
2. **Rendimiento**: Value Objects inmutables tienen overhead minimo (< 100 instancias/batalla).
3. **Livewire Wireable**: Nuevos DTOs deben implementar `Wireable` si cruzan frontera Livewire.
4. **Migracion de datos**: Cambios de string a enum rompen comparaciones exactas; busqueda global necesaria.
5. **EfectoInvocadorTormentaArena**: Usa `sandstorm` como clima, mientras que el resto del sistema usa `tormenta_arena`. Inconsistencia a corregir.

---

# Riesgos

| Riesgo | Probabilidad | Impacto | Mitigacion |
|--------|-------------|---------|------------|
| Rotura serializacion sesion | Alta | Alto | Serializadores custom, versionado, pruebas manuales |
| Regresion batalla auto/manual | Media | Alto | Test suite comparativo antes/despues |
| String->enum rompe comparaciones | Alta | Medio | Busqueda exhaustiva de strings magicos |
| Refactor Equipos/Reclutamiento afecta controladores | Alta | Medio | Migracion gradual con interfaces |
| Tiempo subestimado | Media | Medio | Priorizar oleada 1 y 2 primero |

---

# Checklist de implementacion

## Oleada 1 - Value Objects y Enums
- [ ] B1.1: TipoClima enum + reemplazar en ~10 archivos
- [ ] B1.2: EstadoPokemon enum + eliminar STATUS_LABELS
- [ ] B1.3: CategoriaMovimiento enum
- [ ] B1.4: NombreStat enum
- [ ] B1.5: EtapasStats Value Object
- [ ] B1.6: ColeccionMovimientos tipada
- [ ] B1.7: Eliminar MovementEffectiveness.php duplicado
- [ ] B1.8: Revisar/eliminar BattleSrv
- [ ] B1.9: Revisar/eliminar BattleAggregate

## Oleada 2 - Tipos de retorno
- [ ] B2.1-B2.5: Anadir tipos de retorno a todos los metodos publicos sin tipo
- [ ] Revision global: grep de metodos sin return type declarado

## Oleada 3 - DTOs y encapsulamiento
- [ ] B3.1: DTOAccionBatalla + migrar pendingAction
- [ ] B3.2: Refactor calcularYAplicarDano con AccionBatalla
- [ ] B3.3: DTOResultadoDanio
- [ ] B3.4: DTOHabitatDetalle
- [ ] B3.5: DTOEquipoBatalla para IniciarBatalla
- [ ] B4.1: Encapsular Combatiente (con serializacion)
- [ ] B4.2: Encapsular AgregadoBatalla
- [ ] B4.3: Encapsular EquipoBatalla::$combatants
- [ ] B4.4: Encapsular GestorTurnos::$round
- [ ] B4.5: Encapsular PokemonEntity

## Oleada 4 - Clean Architecture
- [ ] B5.1: Extraer TeamSrv a infraestructura con interfaz
- [ ] B5.2: Extraer ReclutamientoSrv a infraestructura con interfaz
- [ ] B5.3: Eliminar aplicar de InterfazEfecto
- [ ] B5.4: Mover logica de rutas a controladores con DI
- [ ] B5.5: Inyectar FabricaBatallaInterface en Combate
- [ ] B5.6: Inyectar ServicioEjecucionBatalla en Combate
- [ ] B5.7: Vincular HabitatRepositoryInterface en AppServiceProvider

## Oleada 5 - CodeSniffer y limpieza
- [ ] B6.1: Crear pint.json
- [ ] B6.2: Ejecutar ./vendor/bin/pint y corregir
- [ ] B6.3: Evaluar PHPStan level 6
- [ ] B7.1-B7.5: Eliminar codigo muerto identificado

---

# Checklist de validacion

- [ ] `./vendor/bin/pint` pasa sin errores
- [ ] Todas las propiedades publicas en Domain son private o readonly
- [ ] Todos los metodos publicos tienen tipo de retorno explicito
- [ ] `src/` no importa nada de `App\` ni `Illuminate\` (excepto src/*/Infra/)
- [ ] No existen arrays asociativos como tipo de retorno en interfaces de Domain o App
- [ ] Los enums reemplazan todos los strings magicos identificados
- [ ] `Combate` recibe dependencias por constructor, no usa `new`
- [ ] `routes/` no instancian servicios directamente
- [ ] Serializacion de sesion funciona correctamente (probar batalla manual completa)
- [ ] Batalla automatica produce resultados identicos
- [ ] `php artisan test` pasa sin fallos
- [ ] PHPStan level 6 pasa sin errores criticos
- [ ] No hay clases duplicadas ni codigo muerto

---

# Resumen de hallazgos principales

## Por area de impacto

| Area | Problemas encontrados | Prioridad |
|------|----------------------|-----------|
| **1. Metodos tipados** | ~30 metodos sin tipo de retorno en Domain y App | Alta |
| **2. Primitive Obsession** | 4 strings magicos (clima, estado, categoria, stat), 3 arrays sin tipar | Alta |
| **3. DTOs** | 4 fronteras con arrays asociativos sin contrato | Alta |
| **4. Encapsulamiento** | 5+ clases con propiedades publicas masivas | Alta |
| **5. Clean Architecture** | 2 violaciones CRITICAS (src importa App\Models) | Critica |
| **6. Codigo muerto** | 5 archivos/clases sin uso o duplicados | Media |

## Top 5 archivos con mas problemas

| Archivo | Lineas | Problemas clave |
|---------|--------|-----------------|
| `app/Livewire/Combate.php` | 686 | Hardcoded DI, logica dominio en presentacion, sin DTOs, tipos faltantes |
| `src/Battle/Domain/Combatiente.php` | 452 | ~20 propiedades publicas, #[AllowDynamicProperties], strings magicos |
| `src/Battle/Domain/AgregadoBatalla.php` | 256 | Propiedades publicas, pendingAction array, weather string, logica clima mezclada |
| `src/Equipos/Domain/TeamSrv.php` | 16 | Violacion ARQUITECTONICA CRITICA (src importa App\Models) |
| `src/Reclutamiento/Domain/ReclutamientoSrv.php` | 16 | Violacion ARQUITECTONICA CRITICA (src importa App\Models) |

## Orden de implementacion recomendado

```
Fase 1 (Oleada 1+2): Value Objects + Tipos -> cambios seguros, alto impacto, bajo riesgo
Fase 2 (Oleada 3):   DTOs + Encapsulamiento -> cambios medios, requiere serializacion
Fase 3 (Oleada 4):   Clean Architecture -> cambios estructurales, refactor controladores
Fase 4 (Oleada 5):   CodeSniffer + Limpieza -> cambios cosmeticos, codigo muerto
```
