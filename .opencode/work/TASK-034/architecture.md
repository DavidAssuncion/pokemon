# TASK-034 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e40bce000ffefms7gVE5W3PGf3`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-034 — Diseño de presentación de EXP tras combate

> Diseño de solo lectura. No se ha modificado ningún archivo. Rama de referencia: `task-011`.

---

## 1. Decisiones de diseño

### 1.1 Decisiones de producto

| # | Decisión propuesta | Veredicto | Justificación / corrección |
|---|---|---|---|
| P1 | Fase 1 solo modal de combate (ruta, entrenador, gimnasio, mazmorra); exploración fuera de alcance | **VALIDADA** | La exploración se pinta como tarjeta estática tras recargar (`resources/views/exploraciones/index.blade.php:173-205`), no hay modal ni momento único de revelado. Mezclarla obligaría a decidir si la animación se repite en cada carga. |
| P2 | Snapshots de solo lectura alrededor de la concesión, sin tocar `PersistirRecompensas` ni DTOs | **VALIDADA con matiz** | Se captura “después” **inmediatamente** al retorno de la llamada que persiste, no después de `toArray()` ni de `conMedalla()`. Evidencia: `PersistirRecompensas.php:35-49,145-160,184-217`; `OtorgarRecompensasEntrenador.php:103-107`; `OtorgarRecompensasRuta.php:147`. No hay transacción envolvente en `GestorAccionBatalla::terminar()` (`:58-79`), por lo que la lectura posterior ve la escritura. |
| P3 | Builder nuevo `App\Support\ProgresoRecompensas` que compara y construye `progreso` | **VALIDADA con corrección** | Se mantiene el builder, pero **no devuelve arrays como contrato interno**: devuelve DTOs readonly y solo serializa con `aArray()` en la frontera de vista, conforme a `.ai/rules/arrays-dto-collections.md:5-32` y al patrón `SnapshotBatalla::toArray()` (`app/Livewire/Combate/Soporte/DTO/SnapshotBatalla.php:39-52`). |
| P4 | Mostrar todas las Sendas que ganan EXP, ordenadas por EXP ganada | **VALIDADA** | El orden se calcula en backend, no en Blade/Alpine (`frontend-minimal-logic.md:3-7`). Desempate determinista: EXP ganada ↓, EXP final ↓, `tipo_id` ↑. |
| P5 | Máximo 4 segmentos animados por barra y `niveles_omitidos` | **VALIDADA con corrección** | Algoritmo afinado en §2.3: primero + intermedios repartidos uniformemente + último. Caso especial de Senda en nivel 5: no se simula un sexto nivel ni un vaciado sin destino. |
| P6 | 1,5-2,5 s totales, barras en paralelo con escalonado 60-100 ms | **VALIDADA, concretada** | Escalonado `min(indice, 6) × 70 ms` (máx. 420 ms), duración por segmento 140-400 ms, pausa de 70 ms entre vaciado y reajuste. Peor caso ≈ 2,2-2,3 s. |
| P7 | `prefers-reduced-motion`: valores finales sin animación | **VALIDADA** | Se respeta en JS y en CSS, junto a la media query existente (`combate.css:722-758`). |
| P8 | Sin JS, el HTML muestra el resultado final correcto | **VALIDADA** | El servidor renderiza el ancho final y `aria-valuenow` final; Alpine solo retrocede al estado inicial para animar. Si el JS falla, no hay regresión visual. |
| P9 | El header global no se actualiza en tiempo real | **VALIDADA** | `nivelJugador`/`progresoNivel` se comparten por composer (`AppServiceProvider.php:77-90`) y se pintan en `layouts/app.blade.php:80-94`. Actualizarlos exigiría tocar el layout y el ciclo Livewire; fuera de alcance. Se actualiza al navegar. |

### 1.2 Correcciones al análisis que afectan al diseño

1. **`SlugTipo` no es necesario.** `TipoPokemon::slug()` ya devuelve `strtolower($enum->name)` (`src/Shared/Tipos/TipoPokemon.php:61-64`) y coincide con los slugs de assets (`electrico`, `dragon`). Se elimina esa dependencia del builder.
2. **El ejemplo de `resumen_accesible` del análisis suma EXP de cuenta y de Senda** (`analysis.md:238`: “1.600” = 1.000 + 600). Son acumuladores distintos (`users.experiencia` y `player_type_paths.exp`, `PersistirRecompensas.php:145-160`). El resumen accesible no debe presentar una suma global; debe describir cada acumulador por separado.
3. **El caso “Senda alcanza nivel 5” no puede usar el algoritmo genérico de segmentos.** El genérico haría `0→100` tras el vaciado, simulando un sexto nivel. Se trata como caso especial: se anima el tramo actual hasta 100 y se queda en “Tope alcanzado”.
4. **`player_type_paths.level` no se lee.** El nivel se deriva de `exp` con `SendaProgreso::resumen()` (`SendaProgreso.php:113-135`), igual que `CargarMapaSendaJugador.php:22-38`.

### 1.3 Decisiones arquitectónicas

- **Opción A (snapshots en el presentador) confirmada.** Se descartan:
  - **B** (modificar `persistir()`): afecta a ruta, entrenador y exploración, y a mocks de tests (`PersistirRecompensas.php` es `class` no final y se mockea; documentado en `:31`).
  - **C** (reconstruir la ganancia en cada otorgador): obliga a propagar datos por `DatosModalVictoria` y `ResultadoRuta`, con riesgo de perder `progreso` en `conMedalla()` (`DatosModalVictoria.php:30-38`).
  - **D** (evento/listener): sobrearquitectura; el modal necesita el dato en la misma respuesta.
- **Capa del builder:** `app/Support` (adaptador de presentación con lectura de persistencia), no `src/`. No contiene reglas de negocio: solo compara dos instantáneas y deriva datos de presentación con helpers existentes. Mismo patrón que `CargarMapaSendaJugador` y `SendaProgreso`, ya consumidos desde presentación.
- **Dirección de dependencias:** `App\Livewire\Presenters` → `App\Support\ProgresoRecompensas` → `App\Models\{User,PlayerTypePath}` + `Src\Shared\Domain\NivelHelper` + `App\Support\SendaProgreso` + `Src\Shared\Tipos\TipoPokemon`. Ninguna dependencia desde dominio hacia `app/`.
- **Contrato con Blade:** `progreso` viaja como array serializado dentro de `rewards`, que ya es un array de frontera de presentación (`Combate.php:31`, `GestorAccionBatalla.php:68-71`). Internamente se usan DTOs; el array solo existe en el límite de la vista.

---

## 2. Contrato `progreso` y API del builder

### 2.1 Contrato exacto (frontera de vista)

`rewards['progreso']` es aditivo y opcional. Si no hay ninguna ganancia, la clave no se añade.

```php
[
    'cuenta' => [                         // null si no hay ganancia de cuenta
        'exp_antes'          => int,      // >= 0, EXP real de users.experiencia antes
        'exp_despues'        => int,      // >= 0
        'exp_ganada'         => int,      // = exp_despues - exp_antes, > 0
        'nivel_antes'        => int,      // >= 1
        'nivel_despues'      => int,      // >= 1
        'niveles_ganados'    => int,      // = max(0, nivel_despues - nivel_antes)
        'porcentaje_antes'   => int,      // 0..100
        'porcentaje_despues' => int,      // 0..100
        'exp_inicio_nivel'   => int,      // NivelHelper::experienciaParaNivel(nivel_despues)
        'exp_siguiente'      => int,      // NivelHelper::experienciaParaNivel(nivel_despues + 1)
        'segmentos'          => list<array{nivel:int, desde:int, hasta:int}>,
        'niveles_omitidos'   => int,      // subidas de nivel sin segmento propio, >= 0
        'tope'               => false,    // la cuenta no tiene tope; se mantiene por uniformidad
    ],

    'sendas' => [                         // list<senda>; solo Sendas con exp_despues > exp_antes
        [
            'tipo_id'             => int,     // 1..18
            'label'               => string,  // TipoPokemon::label()
            'slug'                => string,  // TipoPokemon::slug()
            'color'               => string,  // #RRGGBB (TipoPokemon::color())
            'icono'               => string,  // '/images/type/{slug}.webp'
            'exp_antes'           => int,     // EXP real, sin clamp
            'exp_despues'         => int,     // EXP real, sin clamp
            'exp_ganada'          => int,     // exp_despues - exp_antes, > 0
            'exp_mostrada_antes'  => int,     // min(max(0, exp_antes), exp_maxima)
            'exp_mostrada_despues'=> int,     // min(max(0, exp_despues), exp_maxima)
            'exp_maxima'          => int,     // SendaProgreso::EXP_MAXIMA (configurable)
            'nivel_antes'         => int,     // 1..5
            'nivel_despues'       => int,     // 1..5
            'nivel_maximo'        => int,     // 5
            'niveles_ganados'     => int,
            'porcentaje_antes'    => int,     // 0..100
            'porcentaje_despues'  => int,     // 0..100 (100 si tope)
            'exp_inicio_nivel'    => int,     // SendaProgreso::umbral(nivel_despues)
            'exp_siguiente'       => int,     // SendaProgreso::umbral(nivel_despues + 1), satura en exp_maxima
            'tope'                => bool,    // nivel_despues >= nivel_maximo
            'segmentos'           => list<array{nivel:int, desde:int, hasta:int}>, // [] si tope ya alcanzado
            'niveles_omitidos'    => int,
        ],
    ],

    'resumen_accesible' => string,        // texto final generado en servidor
]
```

**Reglas de construcción**

1. `cuenta` solo se incluye si `exp_despues > exp_antes`.
2. Cada Senda se incluye solo si `exp_despues > exp_antes`.
3. Los tipos se resuelven con `TipoPokemon::tryFrom($tipoId)`; un `type_id` fuera del catálogo se ignora (no se muestra ni rompe).
4. No se usa `player_type_paths.level`; todo nivel y porcentaje se deriva de `exp` con `SendaProgreso::resumen()`.
5. Se capturan **todas** las filas de `player_type_paths` del usuario, no solo las que se espera que ganen. La diferencia real de BD es la fuente de verdad; así no hay que replicar `expTipoCuenta ?? expTipoPorMiembro` (`PersistirRecompensas.php:160`) ni el mapeo label → `TipoEnum` (`:190-193`).
6. Si `cuenta === null` y `sendas === []`, `comparar()` devuelve `null` y el presentador no añade la clave.

### 2.2 API del builder

```php
namespace App\Support;

use App\Models\PlayerTypePath;
use App\Models\User;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

final class ProgresoRecompensas
{
    /** Máximo de segmentos animados por barra. */
    public const MAX_SEGMENTOS = 4;

    /**
     * Instantánea inmutable del estado de EXP del usuario.
     * Dos SELECT de solo lectura; no escribe nada.
     */
    public function capturar(int $userId): SnapshotProgreso;

    /**
     * Compara dos instantáneas y construye el modelo de presentación.
     * Devuelve null cuando no hay ninguna ganancia.
     */
    public function comparar(
        SnapshotProgreso $antes,
        SnapshotProgreso $despues,
    ): ?ProgresoRecompensasVista;

    // Helpers privados:
    // - cuenta(int $expAntes, int $expDespues): ?ProgresoCuentaVista
    // - senda(int $tipoId, int $expAntes, int $expDespues): ?ProgresoSendaVista
    // - segmentos(int $nivelAntes, int $nivelDespues, int $pctAntes, int $pctDespues, bool $tope): list<ProgresoSegmentoVista>
    // - resumenAccesible(?ProgresoCuentaVista $cuenta, list<ProgresoSendaVista> $sendas): string
    // - ordenarSendas(list<ProgresoSendaVista> $sendas): list<ProgresoSendaVista>
    // - formatear(int $numero): string  // number_format($n, 0, ',', '.')
}
```

**Implementación de `capturar()` (pseudocódigo):**

```php
$expCuenta = (int) User::query()->whereKey($userId)->value('experiencia');

$filas = PlayerTypePath::query()
    ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME) // obligatorio: puede no ser el usuario autenticado
    ->where('user_id', $userId)
    ->pluck('exp', 'type_id')
    ->all();

$porTipo = [];
foreach ($filas as $tipoId => $exp) {
    $porTipo[(int) $tipoId] = (int) $exp;
}

return new SnapshotProgreso($expCuenta, $porTipo);
```

Se replica el acceso de `CargarMapaSendaJugador.php:24-28` (mismo `withoutGlobalScope` y justificación en `:17-19`).

**API de los DTOs**

```php
// app/Support/SnapshotProgreso.php
final readonly class SnapshotProgreso
{
    /** @param array<int,int> $experienciaPorTipo */
    public function __construct(
        public int $experienciaCuenta,
        public array $experienciaPorTipo,
    ) {}

    public function experienciaDe(int $tipoId): int;
    /** @return list<int> */
    public function tiposConExperiencia(): array;
}

// app/Support/ProgresoSegmentoVista.php
final readonly class ProgresoSegmentoVista
{
    public function __construct(
        public int $nivel,
        public int $desde,   // 0..100
        public int $hasta,   // 0..100
    ) {}

    /** @return array{nivel:int, desde:int, hasta:int} */
    public function aArray(): array;
}

// app/Support/ProgresoCuentaVista.php y ProgresoSendaVista.php
// readonly con las propiedades del contrato §2.1 y aArray() snake_case.

// app/Support/ProgresoRecompensasVista.php
final readonly class ProgresoRecompensasVista
{
    /** @param list<ProgresoSendaVista> $sendas */
    public function __construct(
        public ?ProgresoCuentaVista $cuenta,
        public array $sendas,                 // ya ordenadas por el builder
        public string $resumenAccesible,
    ) {}

    public function estaVacio(): bool;        // cuenta === null && sendas === []

    /**
     * Frontera de vista.
     * @return array{cuenta: array<string,mixed>|null, sendas: list<array<string,mixed>>, resumen_accesible: string}
     */
    public function aArray(): array;
}
```

### 2.3 Algoritmo de segmentos

Entrada: nivel y porcentaje antes/después, y `tope`.

**Cuenta (sin tope):**

```text
nivelesGanados = nivelDespues - nivelAntes
totalSegmentos = nivelesGanados + 1

si nivelesGanados === 0:
    [ (nivelAntes, pctAntes, pctDespues) ]

si totalSegmentos <= MAX_SEGMENTOS (4):
    [ (nivelAntes, pctAntes, 100) ]
    + [ (nivelAntes+1 .. nivelDespues-1, 0, 100) ]
    + [ (nivelDespues, 0, pctDespues) ]

si totalSegmentos > MAX_SEGMENTOS:
    intermedios = MAX_SEGMENTOS - 2
    niveles intermedios elegidos uniformemente entre nivelAntes+1 y nivelDespues-1
    [ (nivelAntes, pctAntes, 100) ]
    + [ (nivelIntermedio_k, 0, 100) por k ]
    + [ (nivelDespues, 0, pctDespues) ]
    nivelesOmitidos = nivelesGanados - (MAX_SEGMENTOS - 1)
```

El último segmento puede quedar `(nivelDespues, 0, 0)` cuando la EXP cae exactamente en el umbral. Se mantiene para que la etiqueta de nivel cambie correctamente; su duración visual es 0.

**Senda con `tope = true` (nivel 5):**

```text
si nivelAntes >= nivelMaximo:
    segmentos = []                               // ya estaba en el tope
sino:
    segmentos = [ (nivelAntes, pctAntes, 100) ]  // un solo llenado; no se simula sexto nivel
nivelesOmitidos = 0
porcentajeDespues = 100
```

**Senda sin tope:** mismo algoritmo que la cuenta, con `SendaProgreso::NIVEL_MAXIMO` como cota superior.

**Cálculo de porcentajes (sin duplicar fórmulas):**

- Cuenta: `NivelHelper::progresoHaciaSiguienteNivel($exp)` (`NivelHelper.php:55-67`) y `NivelHelper::experienciaParaNivel($nivel)` (`:47-50`).
- Senda: `SendaProgreso::resumen($exp)` (`SendaProgreso.php:113-135`) para `nivel`, `porcentaje`, `exp` clampada y `exp_siguiente`.

**Resumen accesible (ejemplo):**

```text
Cuenta: nivel 10 a 11, más 1.000 de experiencia.
Senda de Planta: nivel 1 a 2, más 600 de experiencia.
Senda de Fuego: nivel 5, tope alcanzado, más 200 de experiencia.
```

Sin suma global. Si una barra no sube de nivel: “Cuenta: nivel 10, más 400 de experiencia.”

---

## 3. Flujo backend por tipo de combate

Helper común en `PresentadorResultadoBatalla`:

```php
/**
 * @template T
 * @param  callable(): T  $operacion   // la llamada que ya persiste recompensas
 * @return array{0: T, 1: ?ProgresoRecompensasVista}
 */
private static function medirProgreso(int $userId, callable $operacion): array
{
    $builder = app(ProgresoRecompensas::class);

    $antes = $builder->capturar($userId);
    $resultado = $operacion();
    $despues = $builder->capturar($userId);   // inmediatamente tras persistir

    return [$resultado, $builder->comparar($antes, $despues)];
}

/** Adjunta la clave solo si hay progreso y hay recompensas que mostrar. */
private static function adjuntarProgreso(array $rewards, ?ProgresoRecompensasVista $progreso): array
{
    if ($progreso === null || $rewards === []) {
        return $rewards;
    }

    $rewards['progreso'] = $progreso->aArray();

    return $rewards;
}
```

### 3.1 Entrenador — `PresentadorResultadoBatalla.php:89-104`

```php
if ($won) {
    $senda = app(CargarMapaSendaJugador::class)->para($userId);

    [$datosModal, $progreso] = self::medirProgreso($userId, fn () => self::otorgarRecompensas(
        userId: $userId,
        teamId: (int) ($meta['team_id'] ?? 0),
        battle: $battle,
        nivel: (int) ($meta['nivel'] ?? 0),
        dificultad: MultiplicadoresDificultad::para(
            PoliticaIvsEvs::perfilEntrenador((int) ($meta['nivel'] ?? 1))
        ),
        senda: $senda,
    ));

    if ($datosModal !== null) {
        $rewards = self::adjuntarProgreso($datosModal->toArray(), $progreso);
    }
}
```

- La escritura sigue siendo una sola: `OtorgarRecompensasEntrenador::otorgar()` → `persistir()` (`OtorgarRecompensasEntrenador.php:103`).
- `$senda` se sigue cargando antes (nivel de Sendas para el cálculo), sin cambios.

### 3.2 Gimnasio — `PresentadorResultadoBatalla.php:147-166`

```php
if ($won && $resultado->avance) {
    $senda = app(CargarMapaSendaJugador::class)->para($userId);

    [$datosModal, $progreso] = self::medirProgreso($userId, fn () => self::otorgarRecompensas(
        userId: $userId,
        teamId: $teamId,
        battle: $battle,
        nivel: $nivelRival,
        multiplicador: OtorgarRecompensasEntrenador::MULTIPLICADOR_GIMNASIO,
        dificultad: $dificultad,
        senda: $senda,
    ));

    if ($datosModal !== null) {
        if ($resultado->medalla !== null) {
            $datosModal = $datosModal->conMedalla($resultado->medalla);
        }

        // Se adjunta DESPUÉS de conMedalla(): el DTO no se toca y no puede perder progreso.
        $rewards = self::adjuntarProgreso($datosModal->toArray(), $progreso);
    }
}
```

Esto elimina el riesgo detectado en `DatosModalVictoria::conMedalla()` (`:30-38`): la clave se añade al array final, no al DTO.

### 3.3 Mazmorra — `PresentadorResultadoBatalla.php:198-224`

```php
if ($won) {
    [$datosModal, $progreso] = self::medirProgreso($userId, fn () => app(OtorgarRecompensasMazmorra::class)->otorgar(
        userId: $userId,
        teamId: (int) ($meta['team_id'] ?? 0),
        battle: $battle,
        nivel: (int) ($meta['nivel_rival'] ?? 0),
    ));

    if ($datosModal !== null) {
        $caramelos = /* mismo bucle actual, líneas 207-216 */;

        $rewards = self::adjuntarProgreso([
            'exp_total'   => $datosModal->expTotal,
            'exp_miembro' => $datosModal->expMiembro,
            'caramelos'   => $caramelos,
        ], $progreso);
    }
}
```

La mazmorra construye `rewards` manualmente (`:218-222`); el helper añade `progreso` sin alterar las claves legacy.

### 3.4 Ruta — `PresentadorResultadoBatalla.php:258-272`

```php
$operacion = fn () => app(RegistrarResultadoRuta::class)->registrar(
    userId: $userId,
    teamId: $teamId,
    speciesIdsRival: $speciesRival,
    won: $won,
    shinySlots: $shinyRival,
    dificultad: DificultadHabitat::para((int) ($meta['nivel'] ?? 1)),
    nivelHabitat: (int) ($meta['nivel'] ?? 1),
);

if ($won) {
    [$resultado, $progreso] = self::medirProgreso($userId, $operacion);
} else {
    $resultado = $operacion();       // derrota: no persiste recompensas, sin snapshots
    $progreso = null;
}

$rewards = $resultado !== null ? $resultado->aArray() : [];
$rewards = self::adjuntarProgreso($rewards, $progreso);
```

- En ruta la persistencia ocurre dentro de `RegistrarResultadoRuta` → `OtorgarRecompensasRuta::otorgar()` → `PersistirRecompensas` (`OtorgarRecompensasRuta.php:147`); por eso el snapshot envuelve la llamada completa.
- La derrota evita 2 SELECT innecesarios y no puede producir progreso.

### 3.5 Reenvío en `BatallaVistaPresenter`

Cambio mínimo en `rewardsVista()` (`BatallaVistaPresenter.php:426-451`), al final del array de retorno:

```php
'progreso' => is_array($rewards['progreso'] ?? null) ? $rewards['progreso'] : null,
```

- Tolera recompensas antiguas o inyectadas sin `progreso` (p. ej. tests de Livewire que asignan `rewards` directamente).
- No se toca `refsAliado()` (`:115`), por lo que no colisiona con TASK-033.
- `rewardsVista()` sigue devolviendo `null` si `$rewards === []`, por lo que una derrota o una batalla sin meta no muestra modal.

### 3.6 Casos sin `progreso`

| Situación | Resultado |
|---|---|
| Derrota | No se llama a `otorgar`; `rewards = []`; no hay modal. |
| Recompensas ya concedidas / meta limpiada | `procesar()` devuelve `rewards = []` (`:50-52`); no hay modal. |
| `$datosModal === null` (sin rivales válidos) | `OtorgarRecompensasEntrenador.php:80-82` retorna antes de persistir; diff nulo; `progreso = null`. |
| Solo EXP de miembro, sin EXP de cuenta ni de tipo | `cuenta = null`, `sendas = []`; `comparar()` devuelve `null`; el modal legacy sigue igual. |
| Recompensa inyectada sin `progreso` | `rewardsVista['progreso'] = null`; el partial no se renderiza. |

---

## 4. Partial Blade + Alpine + CSS

### 4.1 Estructura de archivos de vista

**`resources/views/livewire/partials/reward-progreso.blade.php`** (contenedor y bucle)

```blade
@php($progreso = $progreso ?? null)

@if (is_array($progreso) && $progreso !== [])
    <div class="reward-progreso"
         wire:key="reward-progreso-{{ md5((string) json_encode($progreso)) }}"
         x-data="rewardProgreso(@js($progreso))"
         x-init="iniciar()"
         @reward-modal-visible.window="iniciar()">

        <div class="visually-hidden" aria-live="polite" aria-atomic="true">
            {{ $progreso['resumen_accesible'] }}
        </div>

        @if ($progreso['cuenta'] !== null)
            @include('livewire.partials._reward-progreso-barra', [
                'barra' => $progreso['cuenta'],
                'tipo'  => 'cuenta',
            ])
        @endif

        @foreach ($progreso['sendas'] as $indice => $senda)
            @include('livewire.partials._reward-progreso-barra', [
                'barra' => $senda,
                'tipo'  => 'senda',
            ])
        @endforeach
    </div>
@endif
```

`wire:key` usa un hash del payload: si el mismo modal se re-renderiza por estado ajeno, el nodo y el estado Alpine se conservan (no se repite la animación); si cambia el payload de una nueva batalla, el nodo se sustituye y vuelve a inicializarse.

**`resources/views/livewire/partials/_reward-progreso-barra.blade.php`** (una barra)

```blade
@php
    $esCuenta    = $tipo === 'cuenta';
    $etiqueta    = $esCuenta ? 'Cuenta' : 'Senda de '.$barra['label'];
    $porcentaje  = $barra['porcentaje_despues'];
    $expMostrada = $esCuenta
        ? $barra['exp_despues']
        : $barra['exp_mostrada_despues'];
@endphp

<div class="reward-progreso__fila"
     data-reward-barra
     data-nivel-despues="{{ $barra['nivel_despues'] }}">

    <div class="reward-progreso__cabecera">
        @if (! $esCuenta)
            <img src="{{ $barra['icono'] }}"
                 alt=""
                 class="reward-progreso__icono"
                 loading="lazy" decoding="async">
        @endif

        <span class="reward-progreso__nombre">{{ $etiqueta }}</span>

        <span class="reward-progreso__nivel" data-reward-nivel>
            Nv {{ $barra['nivel_despues'] }}
        </span>

        @if (! $esCuenta)
            <span class="reward-progreso__delta">
                +{{ number_format($barra['exp_ganada'], 0, ',', '.') }} EXP
            </span>
        @endif
    </div>

    <div class="reward-progreso__barra"
         role="progressbar"
         aria-valuemin="0"
         aria-valuemax="100"
         aria-valuenow="{{ $porcentaje }}"
         aria-valuetext="{{ $etiqueta }}: nivel {{ $barra['nivel_despues'] }}, {{ $porcentaje }} por ciento"
         aria-label="{{ $etiqueta }}">

        <div class="reward-progreso__relleno"
             data-reward-fill
             data-segmentos='@json($barra['segmentos'])'
             style="width: {{ $porcentaje }}%;@if (! $esCuenta) background-color: {{ $barra['color'] }};@endif">
        </div>
    </div>

    <div class="reward-progreso__detalle">
        <span>
            {{ number_format($expMostrada, 0, ',', '.') }}
            / {{ number_format($barra['exp_siguiente'], 0, ',', '.') }} EXP
        </span>

        @if ($barra['niveles_omitidos'] > 0)
            <span class="reward-progreso__omitidos">
                +{{ $barra['niveles_omitidos'] }} niveles sin animar
            </span>
        @endif

        @if ($barra['tope'] === true)
            <span class="reward-progreso__tope">Tope alcanzado</span>
        @endif
    </div>
</div>
```

Blade no calcula niveles, porcentajes ni deltas: solo formatea números y decide qué bloque mostrar según flags ya resueltos.

### 4.2 Puntos de inclusión en `combate.blade.php`

- **Layout ruta:** después de la fila “EXP para cada pokémon” (`:143-146`), antes del bloque `@php` de capturas (`:148`):

  ```blade
  @include('livewire.partials.reward-progreso', ['progreso' => $rewardsVista['progreso'] ?? null])
  ```

- **Layout entrenador/gimnasio:** después de `</ul>` (`:242`) y antes del cierre de `.modal-body` (`:244`):

  ```blade
  @include('livewire.partials.reward-progreso', ['progreso' => $rewardsVista['progreso'] ?? null])
  ```

No se mueve la medalla ni las listas existentes. El modal conserva `role="dialog"`, `aria-modal` y `aria-labelledby` (`:128`).

### 4.3 Componente Alpine

Se añade al final de `resources/views/livewire/partials/combate-scripts.blade.php` una función global, siguiendo el patrón de `sendasApp`/`exploracionesPage` (funciones globales evaluadas por Alpine; `resources/js/bootstrap.js:2-13`).

```js
/**
 * Anima las barras de EXP del modal de recompensas.
 * No calcula niveles ni porcentajes: solo interpola entre valores del backend.
 */
function rewardProgreso(datos) {
    return {
        datos,
        iniciado: false,
        reducido: false,

        init() {
            this.reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const modal = this.$el.closest('.reward-modal');
            if (modal && modal.classList.contains('modal-pendiente')) {
                return; // espera al evento de visibilidad
            }

            this.$nextTick(() => this.iniciar());
        },

        iniciar() {
            if (this.iniciado) {
                return; // anti-repetición en morphs
            }

            const modal = this.$el.closest('.reward-modal');
            if (modal && modal.classList.contains('modal-pendiente')) {
                return; // aún oculto por la cola de eventos
            }

            this.iniciado = true;

            const barras = Array.from(this.$el.querySelectorAll('[data-reward-barra]'));

            barras.forEach((barra, indice) => {
                if (this.reducido) {
                    this.aplicarFinal(barra);
                    return;
                }

                this.animarBarra(barra, indice);
            });
        },

        async animarBarra(barra, indice) {
            const relleno = barra.querySelector('[data-reward-fill]');
            const etiquetaNivel = barra.querySelector('[data-reward-nivel]');
            const segmentos = JSON.parse(relleno.dataset.segmentos || '[]');
            const nivelFinal = Number(barra.dataset.nivelDespues);

            if (segmentos.length === 0) {
                return; // tope ya alcanzado: el HTML ya muestra 100 %
            }

            await this.esperar(Math.min(indice, 6) * 70); // escalonado acotado

            // Estado inicial sin transición.
            this.sinTransicion(relleno, () => {
                relleno.style.width = segmentos[0].desde + '%';
            });

            for (let i = 0; i < segmentos.length; i++) {
                const segmento = segmentos[i];
                const duracion = this.duracionSegmento(segmento);

                this.fijarNivel(etiquetaNivel, segmento.nivel, false);
                relleno.style.transitionDuration = duracion + 'ms';
                relleno.style.width = segmento.hasta + '%';

                await this.esperar(duracion + 60);

                if (i < segmentos.length - 1) {
                    this.fijarNivel(etiquetaNivel, segmento.nivel, true); // aviso de subida
                    await this.esperar(70);

                    // Vaciado instantáneo y reajuste al inicio del siguiente nivel.
                    this.sinTransicion(relleno, () => {
                        relleno.style.width = segmentos[i + 1].desde + '%';
                    });
                    void relleno.offsetWidth; // fuerza reflow
                }
            }

            this.fijarNivel(etiquetaNivel, nivelFinal, false);
        },

        aplicarFinal(barra) {
            const relleno = barra.querySelector('[data-reward-fill]');
            const etiquetaNivel = barra.querySelector('[data-reward-nivel]');

            relleno.style.transition = 'none';
            relleno.style.width = barra.dataset.porcentajeFinal + '%'; // o el ancho ya renderizado
            this.fijarNivel(etiquetaNivel, Number(barra.dataset.nivelDespues), false);
        },

        sinTransicion(relleno, aplicar) {
            relleno.style.transition = 'none';
            aplicar();
            void relleno.offsetWidth;
            relleno.style.transition = '';
        },

        fijarNivel(etiqueta, nivel, sube) {
            etiqueta.textContent = 'Nv ' + nivel;
            if (!sube) {
                return;
            }

            etiqueta.classList.remove('reward-progreso__nivel--sube');
            void etiqueta.offsetWidth;
            etiqueta.classList.add('reward-progreso__nivel--sube');
        },

        duracionSegmento(segmento) {
            // Presentación: interpola entre los valores dados, no aplica fórmulas de juego.
            return Math.min(400, Math.max(140, 140 + Math.abs(segmento.hasta - segmento.desde) * 3));
        },

        esperar(ms) {
            return new Promise((resolve) => window.setTimeout(resolve, ms));
        },
    };
}
```

Notas:

- **Disparo:** si el modal ya está visible, `init()` llama a `iniciar()`. Si está oculto por `modal-pendiente` (`combate.blade.php:125-127`, `combate.css:714-716`), espera al evento `reward-modal-visible` que se emite desde `mostrarModalVictoria()`.
- **Antirrepetición:** `iniciado` evita reiniciar la animación en morphs; `wire:key` sustituye el nodo solo cuando cambia el payload real.
- **Reduced motion:** `reducido` evita toda la secuencia; además el HTML ya está en estado final.
- **Sin JS:** el HTML servido tiene `width` final, `aria-valuenow` final y texto final; no depende de Alpine.

### 4.4 Cambio en `mostrarModalVictoria()`

En `combate-scripts.blade.php:535-541`, tras retirar `modal-pendiente`:

```js
function mostrarModalVictoria() {
    document.querySelector('.fin-batalla.d-none')?.classList.remove('d-none');
    document.querySelector('.battle-over.d-none')?.classList.remove('d-none');

    document.querySelectorAll('.reward-modal.modal-pendiente').forEach((modal) => {
        modal.classList.remove('modal-pendiente');
    });

    if (document.querySelector('.reward-modal')) {
        window.dispatchEvent(new CustomEvent('reward-modal-visible'));
    }
}
```

Se emite siempre que exista el modal; el guard `iniciado` absorbe llamadas repetidas. Si por cualquier motivo no se emitiera, el modal se revela a los 8 s por el `x-init` existente y el usuario ve el estado final correcto (sin animación): degradación aceptable.

### 4.5 CSS

Ubicación: `resources/css/combate.css`, junto al bloque del modal (`:710-716`), y ampliación de la media query existente (`:722-758`). No se añaden dependencias; los tokens `--color-*` ya existen (`resources/css/tokens.css:11-65`) y Bootstrap aporta `.visually-hidden`.

```css
.reward-progreso {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    margin-bottom: 1rem;
}

.reward-progreso__fila {
    display: flex;
    flex-direction: column;
    gap: .35rem;
}

.reward-progreso__cabecera {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
}

.reward-progreso__icono {
    width: 24px;
    height: 24px;
    object-fit: contain;
    flex: 0 0 auto;
}

.reward-progreso__nombre {
    font-weight: 600;
    color: var(--color-ink);
}

.reward-progreso__nivel {
    margin-left: auto;
    font-weight: 700;
    color: var(--color-ink);
    display: inline-block;
}

.reward-progreso__delta {
    font-weight: 700;
    color: var(--color-success-ink);
    white-space: nowrap;
}

.reward-progreso__barra {
    height: 10px;
    overflow: hidden;
    border-radius: 9999px;
    background-color: var(--color-surface-sunken);
}

.reward-progreso__relleno {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background-color: var(--color-primary);
    transition: width 300ms ease-out;
    will-change: width;
}

.reward-progreso__detalle {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    font-size: .75rem;
    color: var(--color-ink-muted);
}

.reward-progreso__omitidos,
.reward-progreso__tope {
    padding: .1rem .4rem;
    border-radius: 9999px;
    font-weight: 700;
    font-size: .6875rem;
}

.reward-progreso__omitidos {
    background: var(--color-info-soft);
    color: var(--color-info-ink);
}

.reward-progreso__tope {
    background: var(--color-warning-soft);
    color: var(--color-warning-ink);
}

.reward-progreso__nivel--sube {
    animation: reward-nivel-pop 380ms ease-out 1;
}

@keyframes reward-nivel-pop {
    0%   { transform: scale(1); }
    45%  { transform: scale(1.22); color: var(--color-success-ink); }
    100% { transform: scale(1); }
}
```

Añadir dentro de `@media (prefers-reduced-motion: reduce)` (`combate.css:723-758`):

```css
.reward-progreso__relleno {
    transition: none;
}

.reward-progreso__nivel--sube {
    animation: none;
}
```

---

## 5. Compatibilidad y casos límite

| Caso | Comportamiento diseñado |
|---|---|
| Recompensas antiguas/inyectadas sin `progreso` | `rewardsVista()` devuelve `null`; el partial no se incluye; modal idéntico al actual. |
| Combate perdido | No se otorgan recompensas; `rewards = []`; no hay modal ni snapshots (en ruta se evitan las lecturas). |
| Recompensas ya concedidas | `procesar()` devuelve `[]` (`PresentadorResultadoBatalla.php:50-52`); sin modal. |
| Ganancia sin cruzar nivel | Un único segmento `(nivel, pctAntes, pctDespues)`. |
| Ganancia que cruza 1 nivel | Dos segmentos: llenado a 100, vaciado, reajuste a `pctDespues`. |
| Ganancia que cruza 2-3 niveles | Todos los segmentos (≤ 4). |
| Ganancia que cruza ≥ 4 niveles | 4 segmentos máximo, intermedios repartidos; `niveles_omitidos` visible. |
| Senda que no existía (`exp` antes 0) | `antes = 0`, nivel 1; aparece como ganancia normal. |
| Senda alcanza exactamente 9.000/18.000/36.000 | Segmento final con `hasta = 0`; la etiqueta cambia al nuevo nivel y la barra queda vacía. |
| Senda llega a nivel 5 | `tope = true`, se llena hasta 100 y no se vacía; chip “Tope alcanzado”. |
| Senda ya en nivel 5 con nueva EXP | `tope = true`, `segmentos = []`, barra al 100 %, delta visible con EXP real. |
| EXP de Senda > 72.000 | `exp_mostrada_*` clampada a 72.000; `exp_ganada` y `exp_despues` reales; sin sexto nivel. |
| Cuenta con EXP 0-9 | Nivel 1, porcentaje 0 (curva existente: `experienciaParaNivel(1) = 10`, `NivelHelper.php:47-50`). Es una peculiaridad preexistente, coherente con el header. |
| Muchas Sendas (hasta 18) | Todas se muestran ordenadas; `modal-dialog-scrollable` ya existe (`combate.blade.php:129`); animación en paralelo con escalonado acotado. |
| `prefers-reduced-motion` | JS no anima (`reducido`), CSS desactiva transiciones; el HTML ya está en estado final. |
| Sin JS | Ancho final, nivel final, delta y `aria-*` finales en el HTML. |
| Modal oculto por `modal-pendiente` | `iniciar()` no arranca hasta que `mostrarModalVictoria()` retira la clase y emite `reward-modal-visible`. |
| Morph de Livewire con el mismo payload | `wire:key` estable + `iniciado = true`: no se repite. |
| Morph con nuevo payload | `wire:key` cambia; Livewire sustituye el nodo y Alpine se reinicializa. |
| `expTipoCuenta` nulo / fallback legado | La diferencia de BD ya refleja lo escrito; no se necesita conocer la ruta económica. |
| Tipo fuera del catálogo (`TipoPokemon::tryFrom` null) | Se ignora la Senda; la cuenta sigue mostrándose. |
| Color de tipo no disponible | El payload incluye `color` hex de `TipoPokemon::color()` como fallback; no depende de `--type-*`. |
| Lectura concurrente de EXP | Ventana mínima entre captura y persistencia; ver riesgos. |

**Fuera de alcance (deuda preexistente, no se corrige):**

- El layout se clasifica como `entrenador` cuando no hay caramelos ni capturas (`BatallaVistaPresenter.php:436-437`), por lo que una victoria de ruta con solo EXP muestra “Has derrotado al entrenador” (`combate.blade.php:213`). La barra funciona igual; corregir el texto requiere otra tarea o un flag de tipo de combate.
- El modal no gestiona foco al revelarse; es deuda previa.

---

## 6. Archivos y orden por especialista

### 6.1 Backend

Orden estricto (cada paso depende del anterior):

| Orden | Acción | Archivo | Responsabilidad |
|---|---|---|---|
| B1 | Crear | `app/Support/SnapshotProgreso.php` | DTO inmutable de instantánea (cuenta + mapa tipo→exp). |
| B2 | Crear | `app/Support/ProgresoSegmentoVista.php` | DTO de segmento animable. |
| B3 | Crear | `app/Support/ProgresoCuentaVista.php` | DTO de barra de cuenta + `aArray()`. |
| B4 | Crear | `app/Support/ProgresoSendaVista.php` | DTO de barra de Senda + `aArray()`. |
| B5 | Crear | `app/Support/ProgresoRecompensasVista.php` | DTO raíz + `aArray()` + `estaVacio()`. |
| B6 | Crear | `app/Support/ProgresoRecompensas.php` | `capturar()`, `comparar()`, segmentos, orden y resumen accesible. |
| B7 | Modificar | `app/Livewire/Presenters/PresentadorResultadoBatalla.php` | Envolver las 4 concesiones con `medirProgreso()` y adjuntar `progreso`. |
| B8 | Modificar | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Reenviar `progreso` en `rewardsVista()` (1 línea + docblock). |

Restricciones del especialista backend:

- No tocar `src/Exploraciones/App/PersistirRecompensas.php`.
- No tocar `DatosModalVictoria.php` ni `ResultadoRuta.php`.
- No tocar `app/Livewire/Combate.php`, `GestorAccionBatalla.php` ni gestores de preparación (TASK-033).
- No tocar `BatallaVistaPresenter::refsAliado()`.
- `declare(strict_types=1);` en todos los archivos nuevos.
- Ejecutar `vendor/bin/pint --dirty --format agent` al terminar.

### 6.2 Frontend

| Orden | Acción | Archivo | Responsabilidad |
|---|---|---|---|
| F1 | Crear | `resources/views/livewire/partials/_reward-progreso-barra.blade.php` | Markup de una barra (cuenta o Senda). |
| F2 | Crear | `resources/views/livewire/partials/reward-progreso.blade.php` | Contenedor, `x-data`, `aria-live`, bucle. |
| F3 | Modificar | `resources/views/livewire/combate.blade.php` | Dos `@include` (ruta y entrenador/gimnasio). |
| F4 | Modificar | `resources/views/livewire/partials/combate-scripts.blade.php` | `rewardProgreso()` + dispatch en `mostrarModalVictoria()`. |
| F5 | Modificar | `resources/css/combate.css` | Estilos, keyframe y reduced motion. |

Dependencias:

- F1/F2 dependen del contrato §2.1 congelado; pueden desarrollarse antes de que B6 exista si toleran `progreso = null`.
- F3 depende de F2.
- F4 depende de F2 (nombres de atributos `data-reward-*`).
- F5 depende de F1/F2 (clases).
- Orden recomendado: B1→B8, luego F1→F5. Alternativa: F1/F2/F4/F5 en paralelo tras congelar contrato, y F3 al final.

**Conflicto con TASK-033:** TASK-033 toca `app/Livewire/Combate.php`, gestores de preparación y `refsAliado()`. TASK-034 no toca ninguno. El solapamiento probable es `combate.blade.php` (modal de preparación) y `combate-scripts.blade.php`. Secuenciar TASK-034 después de TASK-033, o limitar los hunks a las líneas del modal de recompensas y al final del script. No tocar `bugs.txt`, `TASK-032/` ni `TASK-033/`.

---

## 7. Criterios de aceptación y verificación

### 7.1 Criterios de aceptación funcionales

1. Tras una victoria, el modal muestra una barra de **cuenta** con nivel final y porcentaje final.
2. Se muestra una barra por cada **Senda** que recibe EXP, con icono, nombre, nivel, barra y delta.
3. Las Sendas aparecen ordenadas por EXP ganada descendente (orden calculado en backend).
4. Al cruzar un nivel, la barra se llena, se vacía y se reajusta al porcentaje del nuevo nivel.
5. Con varios niveles, se animan como máximo 4 segmentos y se muestra `niveles_omitidos`.
6. Una Senda en nivel 5 muestra 100 %, “Tope alcanzado” y la EXP ganada; no simula un sexto nivel.
7. La animación no vuelve a conceder EXP ni recalcula fórmulas; solo usa los valores del backend.
8. El HTML final es correcto sin JS y con `prefers-reduced-motion: reduce`.
9. Recompensas sin `progreso` (antiguas/inyectadas) renderizan el modal exactamente como hoy.
10. La medalla de gimnasio sigue apareciendo y `conMedalla()` no pierde datos.
11. El modal sigue funcionando con el layout de ruta y el de entrenador/gimnasio.
12. No se modifica `PersistirRecompensas`, DTOs de recompensa, `Combate.php`, `SESSION_VERSION`, `BattleSessionService`, migraciones ni dependencias.
13. El modal hace scroll con muchas Sendas sin desbordar.
14. `aria-live` anuncia el resumen final una sola vez; cada barra expone `role="progressbar"` y valores finales.

### 7.2 Verificación estática

Ejecutar desde `/home/david/pokemon`:

```bash
php -l app/Support/SnapshotProgreso.php
php -l app/Support/ProgresoSegmentoVista.php
php -l app/Support/ProgresoCuentaVista.php
php -l app/Support/ProgresoSendaVista.php
php -l app/Support/ProgresoRecompensasVista.php
php -l app/Support/ProgresoRecompensas.php
php -l app/Livewire/Presenters/PresentadorResultadoBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php

composer analyse          # PHPStan nivel 6, app/ + src/
composer arch             # Deptrac; el presentador ya depende de App\Support (BattleSessionService, CargarMapaSendaJugador)
vendor/bin/pint --dirty --format agent
npm run build             # CSS/JS compilados; verifica que combate.css entra en el bundle
```

Comprobaciones específicas de PHPStan:

- PHPDoc de `list<ProgresoSendaVista>` y `list<ProgresoSegmentoVista>`.
- Tipos de retorno explícitos en todos los métodos nuevos.
- `array<string,mixed>` documentado en los helpers que reciben `rewards`.

No se ejecutan tests ni se crean archivos en `tests/` (regla del proyecto).

### 7.3 Verificación manual (sin tests)

1. **Ruta con un tipo:** barra de cuenta + una Senda; forzar EXP cerca de 9.000 para ver llenado, vaciado y reajuste.
2. **Ruta con doble tipo:** dos barras de Senda.
3. **Entrenador:** barra junto a `exp_total`/`exp_miembro`; sin medalla.
4. **Gimnasio:** medalla visible y barra presente; comprobar que `conMedalla()` no elimina `progreso`.
5. **Mazmorra:** barra presente con el layout actual (anotar el texto preexistente “Has derrotado al entrenador”).
6. **Cuenta cruza 1 y varios niveles:** sembrar EXP para forzar 1, 3 y 5 subidas; verificar límite de 4 segmentos y `niveles_omitidos`.
7. **Senda 71.999 → 72.100:** nivel 5, “Tope alcanzado”, sin vaciado.
8. **Senda ya en 72.000+:** barra al 100 %, delta visible, sin animación de subida.
9. **Recompensa inyectada sin `progreso`:** modal intacto.
10. **`prefers-reduced-motion: reduce`:** valores finales inmediatos.
11. **Lector de pantalla:** `aria-live` una sola vez; barras con `aria-valuetext`.
12. **Muchas Sendas:** scroll del modal sin desbordar y sin alargar la animación más allá de ~2,5 s.
13. **Sin JS (bloqueo del script):** el modal muestra anchos y niveles finales.

---

## 8. Riesgos residuales y preguntas para el desarrollador

### 8.1 Riesgos residuales

| Riesgo | Severidad | Mitigación / estado |
|---|---|---|
| Cambio concurrente de EXP entre las dos capturas | Baja | Ventana mínima; se captura “después” inmediatamente tras persistir. En un solo jugador es improbable. Si se quisiera eliminar, se podría hacer una única SELECT con `join` en `capturar()`, pero complica la consulta sin beneficio claro. |
| Flash del ancho final antes de que Alpine lo retroceda | Baja | El modal suele estar oculto por `modal-pendiente` hasta que arranca `mostrarModalVictoria()`; además `iniciar()` ajusta el ancho síncronamente antes de animar. Si se observa parpadeo, `wire:ignore` en el partial es la válvula de escape. |
| Animación repetida por morphs | Baja | `iniciado` + `wire:key` por hash de payload. |
| Modal revelado sin animación si el evento no se emite | Baja | El HTML final siempre es correcto; el evento se emite en el mismo `mostrarModalVictoria()` que ya revela el modal. |
| Muchas Sendas alargan el modal | Baja | Barras compactas, orden backend, scroll existente, escalonado acotado. |
| `player_type_paths` legados con `exp = 0` y `level > 1` | Media (preexistente) | El diseño deriva el nivel de `exp`, igual que `CargarMapaSendaJugador`; no introduce regresión. Un backfill sería otra tarea. |
| Texto “Has derrotado al entrenador” en ruta/mazmorra sin botín | Media (preexistente) | Fuera de alcance; nota separada. La barra no depende del layout. |
| Conflicto de hunks con TASK-033 en `combate.blade.php` / `combate-scripts.blade.php` | Media | Secuenciar después de TASK-033 o aplicar cambios limitados a las líneas del modal y al final del script. No tocar `Combate.php` ni `refsAliado()`. |
| Payload `progreso` grande en el snapshot de Livewire | Baja | Máx. 18 Sendas × ~250 bytes; irrelevante frente al estado de la batalla. |
| `@js($progreso)` con datos no serializables | Baja | El payload se construye con escalares y arrays; el DTO `aArray()` lo garantiza. |

### 8.2 Preguntas que requieren aprobación explícita

1. **Alcance fase 1:** ¿se confirma solo el modal de combate y se deja exploración para una fase 2?
2. **Número de Sendas:** ¿se muestran todas las que ganan EXP (recomendado) o solo las N principales?
3. **Segmentos:** ¿se aprueba `MAX_SEGMENTOS = 4` con el algoritmo primero + intermedios + último y `niveles_omitidos`?
4. **Duración:** ¿se aprueba el objetivo concreto de ~2,2 s en el peor caso (escalonado 70 ms, segmento 140-400 ms, pausa 70 ms)?
5. **Textos:** ¿se muestran totales exactos (`exp_mostrada_despues / exp_siguiente`) además del delta y del nivel, o solo nivel + delta?
6. **Estructura backend:** ¿se aprueba crear 6 archivos nuevos en `app/Support` (builder + 5 DTOs), en lugar de devolver arrays?
7. **Cabecera:** ¿se acepta que el header no se actualice hasta navegar?
8. **Deuda del layout:** ¿se confirma que el texto “Has derrotado al entrenador” para ruta/mazmorra sin botín se corrige en otra tarea?
9. **Secuenciación con TASK-033:** ¿se implementa TASK-034 después de que TASK-033 cierre, o se autoriza el trabajo en hunks disjuntos de `combate.blade.php` y `combate-scripts.blade.php`?

---

**Resumen de la decisión:** Opción A con builder `App\Support\ProgresoRecompensas` + DTOs readonly + serialización `aArray()` en la frontera + clave aditiva `progreso` en `rewards` + partial Blade + componente Alpine `rewardProgreso()` + estilos en `combate.css`. Sin cambios en `PersistirRecompensas`, DTOs de recompensa, `Combate.php`, migraciones, dependencias ni tests.
