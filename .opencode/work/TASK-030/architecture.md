# TASK-030 — Diseño del modo aliado para 9 movimientos

> Resultado literal de `architect`. Estado: DISEÑO COMPLETO. Solo lectura: no se ha modificado código de aplicación. Rama `task-011`, HEAD `073c0d4`.

## 1. Resumen del diseño

**Decisión principal:** el modo aliado es una **capa de comportamiento por slug**, consultada solo cuando el `defender` resuelto pertenece al equipo del actor. No se cambia `MovimientoBatalla::esAutodirigido()`, no se cambia el `target` de la base de datos, no se toca `ResolutorImpacto`, no se añade fase nueva, no se migra nada y no se sube `SESSION_VERSION`.

Componentes:

1. **Catálogo separado** `CatalogoEfectosAliado` (dominio, estático como `CatalogoObjetos`) con el mapa `slug → EfectoAliado`. Es el predicado `admite()` que sustituye a cualquier cambio en `esAutodirigido()`.
2. **Ejecutor dedicado** `EjecutorEfectoAliado` que aplica el efecto al aliado sin pasar por daño, precisión, crítico, inmunidad de tipo, reacciones ni `partiallytrapped`.
3. **Rama de resolución dedicada** `ResolutorAccionBatalla::aplicarMovimientoAliado()`, invocada solo cuando `defender` es aliado y el catálogo admite el movimiento. Consume turno y PP igual que una acción normal.
4. **Identidad estable:** se añade `slug` a `MovimientoBatalla` y a `DTOMovimientoBatalla` (con valor por defecto `''` para sesiones antiguas). Es el único cambio estructural, y obliga a una corrección de una línea en `DepuradorMovimientos`.
5. **UI mínima:** los aliados se marcan seleccionables en `player_target`/`player_move` solo si el actor tiene al menos un movimiento del catálogo. Con un aliado seleccionado, el preview muestra el efecto aliado y deshabilita los movimientos no usables sobre aliados. Se añade un botón “Cambiar objetivo” reutilizando `cancelTarget()`.

**Opciones evaluadas y descartadas:**

| Opción | Motivo de descarte |
|---|---|
| Cambiar `ObjetivoMovimiento`/`target` en BD a `adjacentAlly` | Requiere migración y seed; además los 3 ataques (`sand-tomb`, `fire-spin`, `whirlpool`) necesitan **doble comportamiento** (rival: daño + atrapado; aliado: soporte). Un solo `target` no lo expresa. |
| Añadir `claveEfecto` a los 9 en el catálogo | `claveEfecto` dispara el pipeline de `ColeccionEjecutoresMovimiento` también en modo rival, y exige cambios de datos/migración. |
| Extender `ResolutorImpacto` con un flag `esAliado` | Obliga a saltar precisión, inmunidad, crítico, reacciones y post-impacto dentro del pipeline; alto riesgo de regresión. |
| Extender `GestorEfectosMovimiento`/`AplicadorEstadoMovimiento` para redirigir `selfStatChanges` | Afecta a todos los buffs propios y a `AplicadorEstadoMovimiento::aplicarEstadoYStatChanges`, usado también por `EjecutorAccion`/`SimuladorAccionIA`. |
| Mapa por `nombre` en vez de `slug` | Evita el cambio de serialización, pero acopla a textos localizados; `paz_mental` ya rompe la convención. Se descarta como opción principal, aunque queda como alternativa si se quiere diff cero en serialización. |

## 2. Modelo de dominio

### 2.1 Objetivo aliado: predicado y ubicación

No se toca `MovimientoBatalla::esAutodirigido()` (`src/Battle/Domain/MovimientoBatalla.php:194-215`). El predicado vive en un catálogo de dominio:

```php
// src/Battle/Domain/Catalog/CatalogoEfectosAliado.php
final class CatalogoEfectosAliado
{
    public static function para(string $slug): ?EfectoAliado;
    public static function admite(string $slug): bool; // para($slug) !== null
}
```

Se consulta:
- en presentación (`MovesPreviewPresenter`, `PresentadorVistaCombate`);
- en los gestores Livewire (`GestorSeleccionBatalla`, `GestorAccionBatalla`);
- en el ejecutor dedicado.

No se consulta en `MovimientoBatalla`, `SelectorAccionIA` ni `GestorPrioridadesBatalla`.

### 2.2 Mapa de efectos

| Slug | `TipoEfectoAliado` | Datos | Efecto sobre el aliado |
|---|---|---|---|
| `sand-tomb` | `ESCUDO_FISICO` | `fraccion: 0.5` | `defensaHpActual = min(max, actual + 0.5 * max)` |
| `fire-spin` | `ESCUDO_ESPECIAL` | `fraccion: 0.5` | `defensaEspHpActual = min(max, actual + 0.5 * max)` |
| `whirlpool` | `AQUA_RING` | `turnos: 5` | `AQUA_RING` con 1/16 PS/ronda |
| `nasty-plot` | `BUFF_STATS` | cambios del movimiento | `selfStatChanges` al aliado (capa temporal 5 rondas) |
| `paz_mental` | `BUFF_STATS` | cambios del movimiento | ídem |
| `swords-dance` | `BUFF_STATS` | cambios del movimiento | ídem |
| `dragon-dance` | `BUFF_STATS` | cambios del movimiento | ídem |
| `bulk-up` | `BUFF_STATS` | cambios del movimiento | ídem |
| `recover` | `CURAR_PS` | `curacionFraccion` del movimiento | `curarHpCantidad(0.5 * hpMax)` sin desbordamiento a escudos |

Los tres primeros conservan su comportamiento rival intacto porque el catálogo solo se consulta cuando `defender` es aliado.

### 2.3 Contratos nuevos

```php
// src/Battle/Domain/Enums/TipoEfectoAliado.php
enum TipoEfectoAliado: string
{
    case ESCUDO_FISICO = 'escudo_fisico';
    case ESCUDO_ESPECIAL = 'escudo_especial';
    case AQUA_RING = 'aqua_ring';
    case CURAR_PS = 'curar_ps';
    case BUFF_STATS = 'buff_stats';
}
```

```php
// src/Battle/Domain/ValueObjects/EfectoAliado.php
final readonly class EfectoAliado
{
    public function __construct(
        public TipoEfectoAliado $tipo,
        public float $fraccion = 0.0,
        public int $turnos = 0,
    ) {}
}
```

```php
// src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php
final class EjecutorEfectoAliado
{
    public function __construct(
        private readonly GestorEfectosMovimiento $gestorEfectos = new GestorEfectosMovimiento(),
    ) {}

    /** Aplica el efecto y devuelve el texto para el evento. */
    public function ejecutar(
        AccionBatalla $accion,
        EfectoAliado $efecto,
        AgregadoBatalla $battle,
    ): string;
}
```

Comportamiento interno:

- **Escudos:** usa `$aliado->pokemon()->battleStats()->defenseHp` / `spDefenseHp`, `$aliado->defensaHpActual()` / `defensaEspHpActual()` y `setDefensaHpActual()` / `setDefensaEspHpActual()` (`Combatiente.php:573-581`). No se toca `BarrerasVidaManager` ni `VitalidadCombatiente`.
- **AQUA_RING:** `$aliado->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AQUA_RING, 5))`; reaplicar refresca a 5.
- **Cura PS:** `$aliado->curarHpCantidad($hpMax * $fraccion)`. Se usa `curarHpCantidad` y no `curarConExcedente` para que el excedente no se convierta en escudo, igual que `AplicadorCuracion` en modo propio (`src/Battle/Domain/Ejecucion/AplicadorCuracion.php:24-25`).
- **Buffs:** reutiliza `GestorEfectosMovimiento::aplicarStatChangesAMuchos($aliado, [], $movimiento)` (`src/Battle/Domain/Danio/GestorEfectosMovimiento.php:144-152`). Así se aplican al aliado los `selfStatChanges`, con las mismas reglas de habilidades (`Simple`, `Inversión`, etc.) y la capa temporal de 5 rondas (`MultiplicadoresStats::TURNOS_TEMPORALES = 5`). No se duplica lógica de stats.

### 2.4 Identidad por slug

Se añade `public readonly string $slug = ''` al final del constructor de `MovimientoBatalla` (`src/Battle/Domain/MovimientoBatalla.php`), se incluye en `__serialize()` y se restaura en `__unserialize()` con `(string) ($data['slug'] ?? '')`. Se propaga en `conPotencia()` y `conTipo()`.

Se añade `public readonly string $slug = ''` al final de `DTOMovimientoBatalla`, con `desdeDominio()`, `toLivewire()`, `fromLivewire()` (`?? ''`) y `toDomain()`.

```php
// src/Battle/Domain/Movimiento.php:63-81
return new MovimientoBatalla(..., slug: $this->slug);
```

**Corrección obligatoria:** `DepuradorMovimientos::firma()` usa `$movimiento->__serialize()` y no descarta `slug`. Hay que añadir `$datos['slug']` al `unset(...)` de `src/Battle/Domain/Catalog/DepuradorMovimientos.php:82-90`. Sin esto, cada movimiento tendría firma única y **se desactivaría la deduplicación de loadouts de IA/entrenadores**.

## 3. Flujo Livewire

### 3.1 Selección (target-first)

El flujo actual es objetivo → movimiento. Se conserva:

1. `nextActor()` deja `phase = player_target`, `currentMoves` con los DTO crudos y `actorActualId` fijado en el agregado (`GestorCicloTurnosBatalla.php:87-90`, `:151-157`).
2. `BatallaVistaPresenter` marca rivales como hasta ahora y, además, aliados vivos alcanzables si `actorAdmiteAliado`.
3. El jugador pulsa una carta aliada → `previewTarget(0, idx)`.
4. `GestorSeleccionBatalla::previewTarget()` valida y recalcula `currentMoves` con `MovesPreviewPresenter::toView($actor, $battle, $aliado)`; `phase = player_move`.
5. El jugador pulsa un movimiento aliado → `selectMove($idx)`.
6. Se crea el `DTOAccionBatalla` con `defenderId = aliado`, se lanza la animación y `commitAction()` resuelve.

No se añade fase ni propiedad pública. `selectedTargetTeam/Idx/RefId` ya existen.

### 3.2 Autorización en servidor

`actingRefId` y `teamIdx` no se usan como fuente de verdad. En `GestorSeleccionBatalla`:

```php
$actor = $this->resolutorActor->jugadorAutorizado($battle); // actorActualId / getNextActor
if ($actor === null || ! $actor->estaVivo() || ! $actor->tieneAccionPendienteEstaRonda()) {
    $componente->nextActor();
    return;
}

$seleccionado = $this->resolutorActor->objetivoPorSeleccion(
    $battle,
    $componente->selectedTargetTeam,
    $componente->selectedTargetIdx,
);

$mismoEquipo = $seleccionado !== null
    && $battle->equipoDe($seleccionado) === $battle->equipoDe($actor);
$esAliado = $mismoEquipo && $seleccionado->id() !== $actor->id();
$efectoAliado = $esAliado ? CatalogoEfectosAliado::para($move->slug) : null;
```

`jugadorAutorizado()` ya existe en `ResolutorActorBatalla.php:28-47`. `objetivoPorSeleccion()` ya soporta `teamIdx 0` y `1` (`:49-58`). No hace falta cambiarlos.

### 3.3 `selectMove` — matriz de decisión

| Objetivo seleccionado | Movimiento | Resultado |
|---|---|---|
| Aliado vivo | En catálogo aliado | `defender = aliado`; modo aliado |
| Aliado vivo | No en catálogo | Log “no puede usarse sobre un aliado”; no crea acción |
| Aliado en retaguardia con vanguardia aliada viva | Cualquiera | Rechazo (no alcanzable) |
| Rival | Cualquiera | Comportamiento actual |
| Ninguno | `esAutodirigido()` | `defender = actor` (actual) |
| Ninguno | No autodirigido | `cancelTarget` (actual) |
| Actor (petición manipulada) | No autodirigido | Rechazo (evita autodaño) |

Pseudocódigo del núcleo:

```php
$selfTargeting = $move->esAutodirigido();

if ($esAliado) {
    if ($efectoAliado === null) { $componente->log[] = '...'; return; }
    if ($seleccionado->estaEnRetaguardia() && $battle->team1->tieneVanguardiaViva()) {
        $componente->log[] = '...'; return;
    }
    $defender = $seleccionado;
} elseif ($mismoEquipo && $seleccionado->id() === $actor->id() && ! $selfTargeting) {
    $componente->log[] = 'Objetivo no válido.'; return;
} elseif ($selfTargeting) {
    $defender = $actor;
} else {
    $defender = $seleccionado;
}

// Desafío solo aplica si no es modo aliado y no es autodirigido
if ($efectoAliado === null && ! $selfTargeting && $desafio?->objetivoForzadoId !== '') {
    // lógica actual
}
```

### 3.4 `GestorAccionBatalla::resolver` — resolución

Se sustituye el forzado incondicional de `:169-172`:

```php
$esAliado = $objetivo->id() !== $actor->id()
    && $battle->equipoDe($objetivo) === $battle->equipoDe($actor);
$efectoAliado = $esAliado ? CatalogoEfectosAliado::para($movimiento->slug) : null;

if ($efectoAliado !== null) {
    $resultadoAccion = $this->resolutorAccion->aplicarMovimientoAliado(
        $battle, $actor, $objetivo, $movimiento, $efectoAliado,
        $grabando, $componente->eventoTick, count($componente->eventos) + 1,
    );
} else {
    if ($esAliado) {
        // Defensa en profundidad: aliado con movimiento no aliado.
        // No se daña al aliado; se consume la acción y se registra el fallo.
        ...
        return;
    }
    if ($movimiento->esAutodirigido()) {
        $objetivo = $actor; // comportamiento actual
    }
    $resultadoAccion = $this->resolutorAccion->aplicarMovimiento(...);
}
```

`prepararAnimacionIa()` (`:98-104`) no cambia: la IA no genera objetivos aliados.

### 3.5 `ResolutorAccionBatalla::aplicarMovimientoAliado`

```php
public function aplicarMovimientoAliado(
    AgregadoBatalla $battle,
    Combatiente $actor,
    Combatiente $aliado,
    MovimientoBatalla $movimiento,
    EfectoAliado $efecto,
    bool $grabando,
    int $tickEvento,
    int $secuenciaEvento,
): ResultadoAccionBatalla
```

Secuencia:

1. `$previo = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();`
2. `$actor->effects()->dispararInicioTurno($actor, $battle);`
3. `$actor->romperCombo();` (decisión: una acción de apoyo corta combo; ver decisión abierta 6).
4. `$log[] = "{$actor->nombre()} usa {$movimiento->nombre} sobre {$aliado->nombre()}";`
5. `$texto = $this->ejecutorAliado->ejecutar($accion, $efecto, $battle);` (el ejecutor añade la línea concreta a `AgregadoBatalla::agregarLog`).
6. `$actual = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();`
7. Emitir **un único evento existente** `effect` con `forzar: true`, `actorId`, `objetivoId = aliado`, `texto`.
8. `$battle->turnManager()->consumeAction($actor);`
9. `$actor->consumirMovimiento($movimiento);`
10. `$actor->effects()->dispararFinTurno($actor, $battle);`
11. `$battle->procesarDebilitamientos();` (normalmente 0).
12. Devolver `ResultadoAccionBatalla($log, $eventos)`.

No se llama a `ResolutorImpacto`, `ServicioEjecucionBatalla::calcularYAplicarDano`, `dispararSerObjetivo`, `dispararDanioRecibido`, `contraatacar` ni `aplicarSecundarios`. Por tanto no hay daño, precisión, crítico, inmunidad de tipo, `partiallytrapped` ni reacciones de “al ser objetivo”.

### 3.6 `nextActor`

Sin cambios. Al terminar, `GestorCicloTurnosBatalla::avanzar()` cierra ronda, aplica `AQUA_RING`/buff temporales y proyecta la vista. El aliado verá el badge de `AQUA_RING` y las etapas de stats.

### 3.7 Impacto por gestor

| Archivo | Cambio |
|---|---|
| `GestorSeleccionBatalla` | Auth con `jugadorAutorizado`; rama aliado; validación de retaguardia; rechazo de movimientos no aliados sobre aliado. |
| `GestorAccionBatalla` | No forzar `actor` cuando hay `EfectoAliado`; rama a `aplicarMovimientoAliado`; defensa en profundidad. |
| `GestorPrioridadesBatalla` | **Sin cambios.** Las prioridades no seleccionan aliado en fase 1: `objetivoPara()` sigue forzando `actor` para autodirigidos y rival para el resto. |
| `ResolutorActorBatalla` | **Sin cambios.** Se reutilizan `jugadorAutorizado()` y `objetivoPorSeleccion()`. |

## 4. UI/JS

### 4.1 Tarjetas de aliado

`BatallaVistaPresenter::marcarInteraccion()` (`app/Livewire/Presenters/BatallaVistaPresenter.php:106-181`) recibe una lista de `refId` aliados seleccionables. En `toView()`:

```php
$refsAliado = [];
if ($faseSeleccion && $phase !== 'player_item_target' && $actorAdmiteAliado && $animAttackerId === '') {
    $vanguardiaViva = ...; // team1
    foreach ($team1 as $p) {
        if (! $p['alive'] || $p['refId'] === $actingRefId) continue;
        if ($p['posicion'] === 'retaguardia' && $vanguardiaViva) continue;
        $refsAliado[] = $p['refId'];
    }
}
```

En `marcarInteraccion`, para `teamIdx === 0`:

```php
$p['selectable'] = $faseItem
    ? $seleccionableItem && ! $ventanaActiva
    : $faseSeleccion
        && ! $ventanaActiva
        && (
            ($teamIdx === 1 && (bool) $p['alive'] && (bool) ($p['canTarget'] ?? true))
            || in_array((string) $p['refId'], $refsAliado, true)
        );
```

`blocked` sigue siendo solo para rivales. `acting` y `targeted` no cambian. `_pokemon-card.blade.php:4` ya hace `wire:click="previewTarget(team, idx)"` cuando `selectable`; **no requiere JS nuevo**.

### 4.2 Panel de movimientos

`MovesPreviewPresenter::previewDe()` (`app/Livewire/Presenters/MovesPreviewPresenter.php:112-200`) añade una rama al principio:

```php
$esAliado = $target->id() !== $actor->id()
    && $battle->equipoDe($target) === $battle->equipoDe($actor);

if ($esAliado) {
    $efecto = CatalogoEfectosAliado::para($move->slug);
    return $efecto === null
        ? self::previewNoUsableEnAliado($actor, $move)
        : self::previewAliado($actor, $move, $efecto);
}
```

Campos nuevos en **todos** los previews:

```php
'slug' => $move->slug,
'modoAliado' => false|true,
'efectoAliado' => null|['tipo' => ..., 'texto' => ...],
```

En modo aliado:

- `potencia = 0`, `esEstado = true`, `daño = 0`, `danoHp = 0`, `danoBarrera = 0`;
- `efectividad = 1.0`, `stab = false`, `directo = false`, `letal = false`;
- `statusEffect = ''`, `statusLabel = ''`;
- `selfStatChanges = []`, `targetStatChanges = []` (el efecto se pinta en `efectoAliado`);
- `disponible = $actor->movimientoDisponible($move)`;
- `motivoTexto` existente si no está disponible.

En modo “aliado seleccionado pero movimiento no aliado”:

- `disponible = false`;
- `motivoTexto = 'No usable sobre un aliado'`;
- `potencia = 0`, `esEstado = true`, `daño = 0`.

Textos de `efectoAliado`:

| Tipo | Texto |
|---|---|
| `ESCUDO_FISICO` | `Restaura 50% del escudo físico` |
| `ESCUDO_ESPECIAL` | `Restaura 50% del escudo especial` |
| `AQUA_RING` | `Acua Aro: 1/16 PS por ronda (5 rondas)` |
| `CURAR_PS` | `Cura 50% de los PS máximos` |
| `BUFF_STATS` | `+100% At.Esp`, `+50% Ataque`, etc., a partir de `selfStatChanges` |

`MovesPreviewPresenter::toView()` no cambia de firma; sigue ordenando por daño y conservando claves. Los previews aliados tienen `daño = 0`, por lo que quedan ordenados alfabéticamente y `esMejor = false`.

### 4.3 `moves-panel.blade.php`

- Añadir un badge `SOBRE ALIADO` y una línea con `{{ $move['efectoAliado']['texto'] }}` cuando `$move['modoAliado']`.
- La fila de daño existente (`@if($move['potencia'] > 0)`) no se pinta en modo aliado.
- Los movimientos no usables sobre aliado salen con `@disabled(true)` y `title` con `motivoTexto`.
- Añadir un botón “Cambiar objetivo” cuando `$selectedTargetTeam !== null`:

```blade
<button type="button" class="btn btn-sm btn-outline-secondary"
        wire:click="cancelTarget">
    Cambiar objetivo
</button>
```

`cancelTarget()` ya es un método público de `Combate` (contrato intacto: 32 propiedades / 18 métodos).

### 4.4 Preview / eventos / animación

- **Sin tipos de evento nuevos.** Se usa el tipo existente `effect`.
- Para curas/escudos, el evento lleva `cambios` con deltas de `hp`, `def` o `spdef`; el JS existente (`reproducirEvento` → `animarCura`) ya anima barras y píldoras (`combate-scripts.blade.php:452-469`).
- Para buffs y `AQUA_RING`, el evento no tiene cambios de vitales; se emite con `forzar: true` y `texto`. El JS muestra la píldora sobre `objetivoId` y el morph de Livewire actualiza `stagesDetalle`/`condicionesBadge`.
- No hay `damage` ni `status` para el aliado, así que no hay “golpe” ni animación de daño.
- La animación de la acción (`gestorAnimacion->movimiento($actor, $aliado, $move)`) se mantiene: muestra “X usa Y”; como no hay evento de daño, no hay impacto.
- **No se modifica `resources/js/combate.js` ni el JS clásico** de `combate-scripts.blade.php`.

### 4.5 Opcional de presentación

- `_pokemon-card.blade.php:52-69`: pintar `buffTurns` (`CombatienteVista.php:84` ya lo calcula) como badge `BUFF {n}`. Es aditivo, usa un dato existente y hace visible la caducidad de 5 rondas. Recomendado, pero separable.
- `items-panel.blade.php`: mostrar un aviso “Puedes seleccionar un aliado para movimientos de apoyo” si `$aliadosSeleccionables` (nuevo campo del array de vista, no de `Combate`).

## 5. Persistencia y compatibilidad

- **No hay migraciones.** No se toca `storage/data/moves.json`, `database/seeders/MoveSeeder.php`, la tabla `moves` ni `ObjetivoMovimiento`.
- **No se toca `SESSION_VERSION = 21`.** El cambio de forma de sesión es aditivo y tolerante:
  - `MovimientoBatalla::__serialize()` incluye `slug`; `__unserialize()` usa `?? ''`.
  - `DTOMovimientoBatalla::fromLivewire()` usa `?? ''`.
  - Sesiones antiguas: `slug = ''` → `CatalogoEfectosAliado::admite('') === false` → los aliados no son seleccionables; el modo propio y el modo rival siguen exactamente igual.
  - Acciones pendientes antiguas (`DTOAccionBatalla` en sesión) se hidratan con `slug = ''`; no rompen.
- **`AQUA_RING` antiguo:** las condiciones guardadas con `turnos = 0` seguirán siendo permanentes si el decremento se guarda con `if ($condicion->turnos > 0)`. Las nuevas aplicaciones usan `turnos = 5`. No se invalida ninguna partida.
- **Compatibilidad hacia atrás de código:** si se revierte el cambio, el antiguo `__unserialize()` ignora la clave `slug`; una sesión con `AQUA_RING turnos=5` volvería a comportarse como permanente (el procesador antiguo no la decrementa), pero no falla.
- **No se requiere `MINIMUM_COMPATIBLE_SESSION_VERSION`.** Solo se plantearía si el desarrollador quisiera forzar la expiración de `AQUA_RING` antiguo, lo que invalidaría partidas en curso.

## 6. Archivos a crear/modificar, responsabilidad y orden

### Crear

| Archivo | Responsabilidad | Depende de |
|---|---|---|
| `src/Battle/Domain/Enums/TipoEfectoAliado.php` | Enum de tipos de efecto aliado | — |
| `src/Battle/Domain/ValueObjects/EfectoAliado.php` | VO inmutable del efecto | `TipoEfectoAliado` |
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | Mapa `slug → EfectoAliado`; `para()`/`admite()` | Enum + VO |
| `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php` | Aplica escudo/AQUA_RING/cura/buffs al aliado | `GestorEfectosMovimiento`, `Combatiente`, `AgregadoBatalla` |

### Modificar

| Archivo | Responsabilidad | Orden |
|---|---|---|
| `src/Battle/Domain/MovimientoBatalla.php` | `slug` en constructor, `__serialize`, `__unserialize`, `conPotencia`, `conTipo` | 1 |
| `src/Battle/Domain/Movimiento.php` | Propagar `slug` en `aMovimientoBatalla()` | 2 |
| `src/Battle/Presentation/DTOMovimientoBatalla.php` | `slug` en DTO/Wireable/`toDomain` | 3 |
| `src/Battle/Domain/Catalog/DepuradorMovimientos.php` | `unset($datos['slug'])` para no romper deduplicación | 4 |
| `src/Battle/Domain/Effects/Movimiento/EjecutorAquaRing.php` | Aplicar a `defender` y `turnos: 5` | 5 (paralelo con 6) |
| `src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php` | Decrementar `AQUA_RING` si `turnos > 0` (después de curar) | 6 (paralelo con 5) |
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | `aplicarMovimientoAliado()` + evento `effect` | 7 |
| `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | Rama aliado; no forzar actor; defensa en profundidad | 8 |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Auth, objetivo aliado, validaciones, preview | 9 (paralelo con 7/8 tras 1-6) |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | Preview aliado/no usable; campos `slug`/`modoAliado`/`efectoAliado` | 10 (paralelo con 7-9) |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | `refsAliado`, `aliadosSeleccionables`, `actorAdmiteAliado` | 11 |
| `app/Livewire/Presenters/PresentadorVistaCombate.php` | Calcular `actorAdmiteAliado` desde `currentMoves` | 12 |
| `resources/views/livewire/partials/moves-panel.blade.php` | Badge de efecto aliado, deshabilitar no usables, “Cambiar objetivo” | 13 (tras 10) |
| `resources/views/livewire/_pokemon-card.blade.php` | **Opcional:** badge `buffTurns` | 14 (independiente) |
| `resources/views/livewire/partials/items-panel.blade.php` | **Opcional:** aviso de aliados seleccionables | 14 (independiente) |

### No tocar

- `app/Livewire/Combate.php` (32 propiedades / 18 métodos).
- `src/Battle/Domain/MovimientoBatalla.php::esAutodirigido()` (solo se añade `slug`).
- `src/Battle/Domain/Ejecucion/ResolutorImpacto.php`.
- `src/Battle/Domain/ServicioEjecucionBatalla.php`.
- `src/Battle/Domain/AI/SelectorAccionIA.php`.
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php`.
- `app/Livewire/Combate/Soporte/ResolutorActorBatalla.php`.
- `app/Support/BattleSessionService.php`.
- `database/`, `storage/data/moves.json`, `ObjetivoMovimiento`.
- `resources/js/combate.js`, `combate-scripts.blade.php`.
- `tests/` (no se crean ni se ejecutan).

## 7. Paralelización propuesta

Dependencias reales:

```
Fase 0 (núcleo, secuencial):
  TipoEfectoAliado → EfectoAliado → CatalogoEfectosAliado → EjecutorEfectoAliado
  MovimientoBatalla(slug) → Movimiento → DTOMovimientoBatalla → DepuradorMovimientos
  EjecutorAquaRing + ProcesadorCondicionesRonda  (paralelo con el bloque anterior)

Fase 1 (acción, tras Fase 0):
  ResolutorAccionBatalla → GestorAccionBatalla
  GestorSeleccionBatalla  (paralelo con los dos anteriores; solo depende del catálogo)

Fase 2 (presentación, tras Fase 0; puede solaparse con Fase 1):
  MovesPreviewPresenter
  BatallaVistaPresenter → PresentadorVistaCombate
  moves-panel.blade.php (tras MovesPreviewPresenter)
  _pokemon-card / items-panel (independientes)
```

Archivos disjuntos por fase:

- **Backend dominio:** 4 nuevos + `MovimientoBatalla`, `Movimiento`, `DTOMovimientoBatalla`, `DepuradorMovimientos`, `EjecutorAquaRing`, `ProcesadorCondicionesRonda`.
- **Backend Livewire:** `ResolutorAccionBatalla`, `GestorAccionBatalla`, `GestorSeleccionBatalla`.
- **Frontend:** `MovesPreviewPresenter`, `BatallaVistaPresenter`, `PresentadorVistaCombate`, `moves-panel`, `_pokemon-card`, `items-panel`.
- **Database:** no aplica.

No se puede paralelizar `MovimientoBatalla`/`Movimiento`/`DTO` (misma cadena de serialización) ni `BatallaVistaPresenter`/`PresentadorVistaCombate` (misma firma).

## 8. Criterios de aceptación

1. **Catálogo exacto:** `CatalogoEfectosAliado::para()` devuelve el tipo esperado para los 9 slugs y `null` para cualquier otro; `paz_mental` está escrito con guion bajo.
2. **Sin regresión de `esAutodirigido`:** `git diff src/Battle/Domain/MovimientoBatalla.php` no muestra cambios en `esAutodirigido()`; solo el campo `slug` y su propagación.
3. **Contrato de `Combate` intacto:** `git diff app/Livewire/Combate.php` vacío; siguen existiendo 32 propiedades y 18 métodos públicos.
4. **Selección:** con un actor sin movimientos del catálogo, las cartas aliadas no son seleccionables (comportamiento actual). Con un actor que tiene al menos uno, las cartas aliadas vivas y alcanzables son seleccionables; los rivales siguen siéndolo.
5. **Preview:** al seleccionar un aliado, los movimientos del catálogo muestran `SOBRE ALIADO` y su texto de efecto; los no usables aparecen deshabilitados con motivo; no se muestra daño contra el aliado.
6. **Resolución sin daño:** usar `sand-tomb`, `fire-spin` o `whirlpool` sobre un aliado no resta HP, no aplica `partiallytrapped`, no dispara precisión/crítico/inmunidad/reacciones, y consume turno y PP.
7. **Escudos:** `sand-tomb` restaura `min(max, actual + 0.5 * max)` del escudo físico; `fire-spin` hace lo mismo con el especial; nunca supera el máximo.
8. **Cura:** `recover` sobre aliado cura `min(faltante, 0.5 * hpMax)` y no desborda a escudos.
9. **AQUA_RING:** `whirlpool` sobre aliado aplica `AQUA_RING` con `turnos = 5`; cura 1/16 del HP máximo al final de 5 rondas y expira. `aqua-ring` en modo propio también dura 5 rondas.
10. **Buffs:** los 5 movimientos de stats aplican los `selfStatChanges` al aliado, no al actor; duran 5 rondas; el actor conserva sus stats. En modo propio, los mismos movimientos siguen buffeando al actor.
11. **Modo rival intacto:** `sand-tomb`, `fire-spin` y `whirlpool` contra rival siguen haciendo 35 de daño y aplicando `partiallytrapped`; los buffs usados tras seleccionar rival siguen buffeando al actor.
12. **Persistencia:** no hay migraciones nuevas; `SESSION_VERSION` sigue en 21; una sesión antigua carga sin error y mantiene el modo propio/rival.
13. **IA:** `SelectorAccionIA` no genera acciones contra aliados; no se rompe `prepararAnimacionIa()` ni el turno de la IA.
14. **Estático:** Pint, PHPStan, deptrac, `php -l`, `php artisan view:cache` y `git diff --check` sin errores.
15. **Smoke manual:** el flujo completo de la sección 9.3 se ejecuta sin excepciones y sin cambios visuales inesperados en el modo rival.

## 9. Verificación requerida

### 9.1 Análisis estático

```bash
# Formato (no usar --test según AGENTS.md)
vendor/bin/pint --dirty --format agent

# PHPStan
composer analyse

# Deptrac (nuevas clases en Domain; App\Livewire → Domain permitido)
composer arch

# Sintaxis de cada archivo tocado
php -l src/Battle/Domain/Enums/TipoEfectoAliado.php
php -l src/Battle/Domain/ValueObjects/EfectoAliado.php
php -l src/Battle/Domain/Catalog/CatalogoEfectosAliado.php
php -l src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php
php -l src/Battle/Domain/MovimientoBatalla.php
php -l src/Battle/Domain/Movimiento.php
php -l src/Battle/Presentation/DTOMovimientoBatalla.php
php -l src/Battle/Domain/Catalog/DepuradorMovimientos.php
php -l src/Battle/Domain/Effects/Movimiento/EjecutorAquaRing.php
php -l src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php
php -l app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorAccionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Presenters/MovesPreviewPresenter.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php
php -l app/Livewire/Presenters/PresentadorVistaCombate.php

# Blade
php artisan view:cache
php artisan view:clear

# Integridad del diff
git diff --check
git diff --stat
```

No se ejecutan tests. No se crean archivos en `tests/`.

### 9.2 Comprobaciones de contrato

```bash
# El contrato público no cambia
git diff app/Livewire/Combate.php

# esAutodirigido no cambia
git diff -U0 src/Battle/Domain/MovimientoBatalla.php | grep -n "esAutodirigido" || true

# No hay migraciones nuevas
git status --short database/migrations
```

### 9.3 Smoke manual de navegador

Preparar un equipo del jugador con los 9 movimientos (loadout o seed temporal de desarrollo; no se commitea). Iniciar batalla.

1. **Selección normal:** en `player_target`, los rivales siguen siendo clicables; un actor sin movimientos del catálogo no hace clicables a sus aliados.
2. **Selección de aliado:** con un actor que tiene el catálogo, las cartas aliadas vivas de vanguardia son clicables. En retaguardia con vanguardia viva, no.
3. **Preview:** seleccionar un aliado; el panel muestra `SOBRE ALIADO`, el texto de efecto y los movimientos no aliados deshabilitados. “Cambiar objetivo” vuelve a `player_target`.
4. **Recuperación:** usar `recover` sobre un aliado dañado; la barra de PS sube hasta el 50% del máximo faltante o el tope; el log dice “usa … sobre …” y “recupera X PS”; el turno avanza y el PP baja.
5. **Bucle Arena:** sobre aliado con escudo físico dañado; la barra `DEF` sube un 50% del máximo; no hay daño ni badge de atrapado. Contra rival, sigue el daño y el atrapado.
6. **Giro Fuego:** ídem con la barra `DEF.ESP`.
7. **Torbellino:** sobre aliado aparece el badge `acua aro` con “5 turnos”; al final de cada ronda el aliado recupera 1/16 y el badge baja 5→4→…; a la quinta desaparece. En modo propio, `aqua-ring` también muestra 5 y caduca.
8. **Buffs:** usar `nasty-plot`, `paz_mental`, `swords-dance`, `dragon-dance` y `bulk-up` sobre un aliado; suben sus badges de etapas y no los del actor. Tras 5 rondas, los badges desaparecen. En modo propio, siguen buffeando al actor.
9. **Modo rival:** seleccionar un rival y usar los mismos buffs; el actor recibe el buff (sin regresión). Usar los 3 ataques contra rival; daño y atrapado normales.
10. **Sesión:** recargar el navegador a mitad de batalla; no hay errores de hidratación ni de `slug`.
11. **IA:** dejar pasar un turno de IA; no hay acciones contra aliados ni excepciones.
12. **Móvil/escritorio:** el panel de movimientos y las cartas mantienen el layout de Bootstrap; el botón “Cambiar objetivo” no rompe la altura del panel.

## 10. Riesgos, casos borde y rollback

| Riesgo | Severidad | Detalle | Mitigación |
|---|---|---|---|
| `DepuradorMovimientos` con `slug` | **Alta** | `firma()` usa `__serialize()`; si incluye `slug`, cada movimiento tiene firma única y se desactiva la deduplicación de loadouts de IA/entrenadores. | Añadir `unset($datos['slug'])` en `DepuradorMovimientos.php:82-90`. Verificar en una batalla de entrenador que los loadouts siguen depurados. |
| Serialización de `MovimientoBatalla` | Media | Sesiones antiguas sin `slug`; acciones pendientes antiguas. | `?? ''` en `__unserialize` y `fromLivewire`; el modo aliado simplemente no está disponible en esas sesiones. |
| `AQUA_RING` legado `turnos=0` | Media | Si se decrementa sin condición, expira tras una cura; si se deja, sigue permanente. | Decrementar solo si `turnos > 0`; documentar que las sesiones antiguas conservan el comportamiento permanente hasta reaplicar. |
| Orden de `AQUA_RING` en la ronda | Media | Curar antes de decrementar da 5 curas; hacerlo al revés da 4. | Mantener `aplicarDuracionSimple` (cura) antes de `aplicarContadores` (decremento). |
| Cambio de auth a `jugadorAutorizado` | Media | `actorActualId` debe estar fijado por `nextActor`; en sesiones antiguas hay fallback a `getNextActor()`. | Probar `swapTurno`, `retrasarTurno` y `pasarTurno`; el fallback ya existe en `ResolutorActorBatalla:28-47`. |
| UX de selección de aliado | Media | Los aliados son clicables aunque el jugador quisiera atacar; puede confundir. | Badge/aviso, “Cambiar objetivo” y preview que deshabilita movimientos no usables. |
| Buffs con habilidades | Baja | `Simple`/`Inversión` pueden alterar el valor final; el log muestra el factor base. | El efecto real lo aplica `GestorEfectosMovimiento`; el log es informativo. Documentar. |
| `cuerpo_aureo` / Espejo Mágico | Baja | El camino aliado no llama a `AplicadorEstadoMovimiento::aplicarEstado`, por lo que no bloquea/refleja buffs beneficiosos. | Intencional: los efectos aliados son beneficiosos; si el desarrollador quiere que se bloqueen, añadir una comprobación explícita. |
| Cura a aliado con PS llenos | Baja | Consume turno/PP sin efecto. | Decisión de producto: mantener el consumo (coherente con movimientos de estado). |
| Aliado muere entre selección y commit | Muy baja | No hay interleaving en el flujo actual; podría ocurrir por efectos de inicio de turno. | `EjecutorEfectoAliado` comprueba `estaVivo()`; `aplicarMovimientoAliado` registra fallo y consume acción. |
| Buffs sin badge visible | Baja | `buffTurns` no se pinta hoy; la caducidad de 5 rondas puede pasar desapercibida. | Añadir badge opcional en `_pokemon-card` (sección 4.5). |
| Prioridades sin aliado | Baja | `GestorPrioridadesBatalla` no permite elegir aliado; una prioridad con buff sigue siendo propia. | Documentar como limitación de fase 1; si se quiere, ampliar `prioridades` con objetivo (fuera de alcance). |
| Regresión en modo rival | Media | Cualquier rama mal condicionada podría mandar un ataque normal al aliado. | La rama aliada exige `equipoDe(defender) === equipoDe(actor)` y `Catalogo::para(slug) !== null`; además hay defensa en profundidad en `resolver`. |

**Rollback:** revertir los archivos modificados. Las sesiones con `slug` son legibles por el código antiguo (clave desconocida ignorada). Las sesiones con `AQUA_RING turnos=5` volverían a tratarse como permanentes por el procesador antiguo, sin excepción. No hay migración que deshacer.

## 11. Decisiones abiertas que requieren aprobación del desarrollador

1. **Porcentaje de escudo:** 50% del máximo (recomendado, simétrico con `recover`), 25% o 100%. Afecta a `EfectoAliado::fraccion` de `sand-tomb`/`fire-spin`.
2. **Alcance del aliado:** solo vanguardia viva (recomendado, espejo de la regla rival) o cualquier aliado vivo, incluida retaguardia. Afecta a `refsAliado` y a la validación de `selectMove`.
3. **UX target-first vs move-first:** se propone target-first (sin fase nueva). Si se exige literalmente “elegir el movimiento primero y luego el aliado”, habría que añadir un modo pendiente con `selectedMoveIdx` o una fase nueva; es un cambio mayor.
4. **`AQUA_RING` legado:** conservar permanente en sesiones antiguas (recomendado) o forzar su expiración. Forzarla exigiría invalidar sesiones (`MINIMUM_COMPATIBLE_SESSION_VERSION`), no solo cambiar `SESSION_VERSION`.
5. **Buffs solo al aliado:** se asume que el actor **no** recibe también el buff (la tabla dice “sobre aliado”). Si se quiere doble efecto, hay que decidirlo antes de implementar.
6. **Combo:** se propone romper combo al usar una acción de apoyo. Si se prefiere conservarlo, quitar `$actor->romperCombo()` del camino aliado.
7. **Prioridades:** confirmar que en fase 1 las prioridades no permiten seleccionar aliado. Si deben permitirlo, hay que ampliar el contrato de `prioridades` y `GestorPrioridadesBatalla`.
8. **Badge `buffTurns`:** incluir el badge visible de rondas restantes de buffs (recomendado) o dejarlo para una fase posterior.
9. **Endurecimiento de autorización:** se propone usar `jugadorAutorizado()` en `previewTarget`, `selectMove`, `retrasarTurno` y `pasarTurno` (no solo en `selectMove`). Confirmar si se autoriza ese cambio de alcance en los tres últimos.
10. **Identidad por slug vs nombre:** se recomienda `slug` (requiere el `unset` en `DepuradorMovimientos`). Si se prefiere diff cero en serialización, se puede usar un mapa por `nombre` exacto, con el riesgo de acoplamiento a textos.

---

# Addendum v2 — Fórmulas de escudos y curación

> Delta del `architect` tras la precisión del desarrollador. Sustituye las fórmulas de 50%/50% del diseño base. Sin modificaciones al repositorio.

## A. Fórmulas exactas

### A.1 Escudo físico (`sand-tomb`)

```text
incremento = max(1, floor(redondear9(maxEscudoFisico / 32 + DefensaDelLanzador * 0.10)))
nuevoEscudo = min(maxEscudoFisico, escudoActual + incremento)
```

- `maxEscudoFisico` = `battleStats()->defenseHp` del receptor.
- `DefensaDelLanzador` = stat **efectiva** del lanzador.
- No se supera el máximo; no se crea escudo temporal.

### A.2 Escudo especial (`fire-spin`)

```text
incremento = max(1, floor(redondear9(maxEscudoEspecial / 32 + DefensaEspecialDelLanzador * 0.10)))
nuevoEscudo = min(maxEscudoEspecial, escudoActual + incremento)
```

### A.3 Curación (`recover` y `whirlpool`)

```text
curacion = max(1, floor(redondear9(PSMaxReceptor / 32 + PSMaxLanzador * 0.05)))
curado = curarHpCantidad(curacion)   // tope en PS máximo del receptor
```

- `recover`: una sola aplicación.
- `whirlpool`: cada ronda durante 5 rondas.
- El 5% de los PS máximos del lanzador se **fija al aplicar** y se guarda en `CondicionVolatil::curacionFija`.
- El `1/32` del receptor se recalcula cada ronda con su máximo actual.

### A.4 Redondeo

- Suma en coma flotante, `round(..., 9)` y `floor`.
- Mínimo 1 por aplicación para evitar curas nulas por redondeo.
- El tope real lo pone el recurso (`min(max, ...)` o `curarHpCantidad`).

## B. Contratos actualizados

- `EfectoAliado` queda sin `float $fraccion`; solo `TipoEfectoAliado $tipo` e `int $turnos`.
- Nuevo `CalculadoraCuracionAliada` con constantes y métodos:
  - `incrementoEscudo(float $maxEscudo, float $statLanzador): int`
  - `curacionPs(float $psMaxReceptor, float $psMaxLanzador): int`
  - `curacionConAporteFijo(float $psMaxReceptor, float $aporteFijo): int`
- `CondicionVolatil` añade `float $curacionFija = 0.0`; `toArray()`/`desdeArray()` con default `0.0`.
- `EjecutorEfectoAliado` usa el calculador; escribe `curacionFija` en Aqua Ring aliado.
- `EjecutorAquaRing` en modo propio: `turnos: 5` y `curacionFija: 0.0` (mantiene 1/16).
- `ProcesadorCondicionesRonda`: si `curacionFija > 0`, cura `PSMax/32 + curacionFija`; si no, conserva 1/16 con desbordamiento. Decrementa `AQUA_RING` solo si `turnos > 0`, después de curar.

## C. Preview y log

- `MovesPreviewPresenter` muestra cantidades calculadas, no porcentajes:
  - `Restaura {n} de DEF (máx. {max})`
  - `Restaura {n} de DEF.ESP (máx. {max})`
  - `Cura {n} PS (máx. {max})`
  - `Acua Aro: {n} PS por ronda (5 rondas)`
- Log: “recupera {n} de DEF”, “recupera {n} de DEF.ESP”, “recupera {n} PS”, “queda rodeado por Acua Aro ({n} PS por ronda durante 5 rondas)”.
- Sin tipos de evento nuevos; se reutiliza `effect`.

## D. Archivos adicionales

- Crear `src/Battle/Domain/Ejecucion/CalculadoraCuracionAliada.php`.
- Modificar `src/Battle/Domain/ValueObjects/CondicionVolatil.php`.
- Mantener el resto de archivos del diseño base.

## E. Criterios de aceptación actualizados

7. `sand-tomb`: `max(1, floor(round(max/32 + Def*0.10, 9)))`, tope en el máximo. Ejemplo max 320, Def 120 → +22.
8. `fire-spin`: misma fórmula con `spDefenseHp` y `DEFENSA_ESPECIAL`. Ejemplo max 320, SpDef 100 → +20.
9. `recover`: `max(1, floor(round(PSMaxReceptor/32 + PSMaxLanzador*0.05, 9)))`, tope en PS máximo. Ejemplo 320/300 → 25 PS.
10. `whirlpool`: guarda `curacionFija = PSMaxLanzador*0.05`, `turnos=5`; cada ronda cura `PSMaxReceptor/32 + curacionFija`; ejemplo 320/300 → 25 PS/ronda, 5 rondas. `aqua-ring` propio dura 5 rondas con 1/16.
11. Redondeo `floor` + mínimo 1; recurso lleno → 0 y consume turno/PP.
12. Preview y log coinciden con la cantidad calculada.
13. `curacionFija` default `0.0`; sesiones antiguas cargan; sin migración ni `SESSION_VERSION`.
14. `recover` propio sigue 50%; `aqua-ring` propio 1/16 durante 5 rondas; buffs propios sin cambios.
15. Modo rival intacto.
16. Pint, PHPStan, deptrac, `php -l`, `view:cache`, `git diff --check`.

## F. Riesgos nuevos

- Divergencia preview/real: única fuente `CalculadoraCuracionAliada`.
- Defensa efectiva vs base: se adopta efectiva.
- Caster ausente: `curacionFija` evita depender de él.
- PS máximos cambiantes: 1/32 del receptor se recalcula; 5% del lanzador queda fijo.
- Escudo al máximo / PS llenos: 0 y consume turno/PP.
- Rollback: código antiguo leyendo `curacionFija` puede emitir `Deprecated: Creation of dynamic property` en PHP 8.2+, no fatal.
- `AQUA_RING` legado: `turnos=0`, `curacionFija=0` → 1/16 permanente.

## G. Decisiones adoptadas para el plan v2

1. Defensa del lanzador: **stat efectiva**.
2. Redondeo: **floor + mínimo 1**.
3. Aqua Ring propio: **1/16**, sin fórmula, durante 5 rondas.
4. Valor fijo: **5% del lanzador fijado**; 1/32 del receptor recalculado.
5. Aqua Ring aliado: **sin desbordamiento a escudos** (`curarHpCantidad`).
6. `curacionFija`: **float**.
7. `origenId`: se guarda para trazabilidad.

## H. Migración y `SESSION_VERSION`

- Sin migración.
- Sin cambio de `SESSION_VERSION`.
- `curacionFija` es aditivo con default `0.0`.
- No se requiere `MINIMUM_COMPATIBLE_SESSION_VERSION`.

---

# Addendum v3 — Recuperación al 50%

> Precisión del desarrollador: «para el caso de recuperacion es el 50% siempre por defecto».

- `recover` sobre aliado y en modo propio cura **50% del PS máximo del receptor**, con tope en el máximo.
- La fórmula `PSMaxReceptor/32 + PSMaxLanzador*0.05` queda **solo** para Aqua Ring (`whirlpool`).
- `CalculadoraCuracionAliada::curacionPs` se elimina; `recover` usa `0.5 * PSMaxReceptor` directamente.
- El resto del addendum v2 sigue vigente: escudos `max/32 + 10%`, Aqua Ring 5 rondas con 5% fijado, redondeo `floor` + mínimo 1, sin migración ni `SESSION_VERSION`.
