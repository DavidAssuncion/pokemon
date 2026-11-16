# TASK-041 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e3fab6b0bffeprgrfMRnmdlvFp`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-041 — Diseño: IA contra Fantasma y tooltip de debug

## 1. Decisiones de diseño

### IA

1. **`declararIa()` deja de elegir por prioridad y pasa a elegir por valor**, reutilizando la misma evaluación que el turno normal: `AgregadoBatalla::elegirAccionPara()` → `SelectorAccionIA::elegirAccion()`.
2. **Solo se declara prioridad si la mejor acción global es un movimiento con `priority > 0`**. Si la mejor es Garra Metal (prioridad 0), no se declara nada y el flujo normal la ejecuta.
3. **Pre-chequeo de “¿tiene alguna prioridad disponible?”** para no pagar la evaluación completa en rivales que no pueden declarar prioridad.
4. **No se declaran prioridades de apoyo dirigidas a un aliado**, porque `resolver()` no tiene ruta de apoyo aliado; sí se permiten autodirigidas.
5. **No se toca `DESAFIO`**: `objetivoPara()` delega en `elegirObjetivoPara()`, y este vuelve a generar candidatas aplicando `DESAFIO` en `SelectorAccionIA`. La afirmación del análisis de que no se aplica a la IA es incompleta.
6. **Se usa la dificultad actual del agregado**, no `PERFECTA` forzada. Hoy el agregado nace en `PERFECTA` y `setDificultad()` no tiene llamadas en `app/` ni `src/`.
7. **No se modifica la serialización, `SESSION_VERSION`, `MovimientoBatalla`, `PerfilMovimiento` ni `CombatienteVista`.**

### Tooltip

1. **Gate en servidor**, en `SincronizadorVistaBatalla::proyectar()`, con `(bool) config('app.debug')`, y **solo para `team2`**. No se construye el array si el gate está apagado.
2. **Presentador nuevo** `App\Livewire\Presenters\DebugCombatientePresenter` (app → dominio, sin Livewire/Blade).
3. **UI hover-only con CSS puro**, sin JS, sin Bootstrap Popover, sin dependencias nuevas.
4. **Tooltip superpuesto dentro de la propia carta** (`position: absolute; inset: 0`) con `pointer-events: none`. Evita el recorte de `.pokemon-card { overflow: hidden; }`, los desbordes fuera del viewport y el conflicto con `wire:click` (los clics atraviesan el tooltip).
5. **Solo hover real**: `@media (hover: hover) and (pointer: fine)`. Sin `:focus`, `:focus-within`, `tabindex` ni listeners de touch.

## 2. Política de IA exacta

### 2.1. Situación actual

`GestorPrioridadesBatalla::declararIa()` recorre los movimientos y se queda con el de **mayor `priority`**, desempatando por potencia. No consulta tipo, efectividad, daño, KO, supervivencia ni riesgo (`:28-58`).

El flujo normal sí evalúa: `GestorAccionBatalla::prepararAnimacionIa()` llama a `elegirAccionPara()`; `AgregadoBatalla::elegirAccionPara()` delega en `SelectorAccionIA::elegirAccion()`; `EvaluadorAccionIAImpl` puntúa KO, daño efectivo, amenaza, supervivencia, riesgo y lookahead. No hay término de prioridad en la puntuación.

### 2.2. Nueva política (pseudocódigo)

```php
public function declararIa(AgregadoBatalla $battle): void
{
    if ($battle->team1->combatientesCollection()->vivos()->isEmpty()) {
        return;
    }

    $ronda = $battle->turnManager()->round();

    foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
        $rival->limpiarPrioridad();
        $rival->setPostura(PosturaCombate::ATACAR);
        $rival->setObjetivoCubiertoId('');

        if (! $this->tienePrioridadDisponible($rival, $ronda)) {
            continue;
        }

        $accion = $battle->elegirAccionPara($rival);

        if ($accion === null) {
            continue;
        }

        $movimiento = $accion->move;

        if ($movimiento->priority <= 0) {
            continue;
        }

        if (ClasificadorAccionIA::esApoyo($battle, $accion)
            && ! $movimiento->esAutodirigido()) {
            continue;
        }

        $rival->declararPrioridad($movimiento);
    }
}

private function tienePrioridadDisponible(Combatiente $rival, int $ronda): bool
{
    foreach ($rival->pokemon()->moves()->all() as $movimiento) {
        if (! $movimiento instanceof MovimientoBatalla) {
            continue;
        }

        if ($movimiento->priority <= 0 || ! $rival->movimientoDisponible($movimiento)) {
            continue;
        }

        if ($movimiento->esProteccion() && ! $rival->puedeUsarProteccion($ronda)) {
            continue;
        }

        return true;
    }

    return false;
}
```

Notas:

- El pre-chequeo replica los filtros de `SelectorAccionIA::generarAccionesCandidatas()`: disponibilidad y protección.
- `limpiarPrioridad()` ya se ejecuta antes en `GestorPreparacionBatalla::aplicar()`, pero repetirlo hace el método autocontenido. Fijar `ATACAR` es seguro: los rivales se construyen con `ATACAR` por defecto.
- No se toca `pendingAction`, la cola de turnos ni `resolver()`.

### 2.3. Respuestas a las preguntas

- **Dificultad:** `elegirAccionPara()` usa `$this->dificultad`; en producción es `PERFECTA`, por lo que el top N es 1 y la decisión es determinista. Usar la dificultad actual; no forzar PERFECTA.
- **Simulación/efectos:** `SimuladorAccionIA` clona la batalla y el combate; los efectos de `ManejadorDanioBase` (`mutatipo`/`libero`) se aplican al clon. **Riesgo real:** `CalculadoraDanioIA::estimar()` usa `mt_srand(42)` y `mt_srand($seed)`, alterando el estado global del RNG de PHP. La evaluación extra en preparación desplaza la secuencia de `mt_rand()` de críticos/estados. Se documenta como riesgo de balance.
- **`DESAFIO`:** `objetivoPara()` cae en `elegirObjetivoPara()` para la IA; `SelectorAccionIA` sustituye los objetivos por el forzado cuando sigue vivo. No se modifica.
- **Limpieza de postura/prioridad:** sí para todos los rivales vivos; la IA no usa `CUBRIR`/`GUARDIA`.
- **Apoyo:** `ClasificadorAccionIA::esApoyo()` + `!esAutodirigido()` es el filtro correcto; `resolver()` no tiene ruta de apoyo aliado.
- **Acciones extra:** la prioridad consume una extra; coherente.

## 3. Contrato `debug`, gate y presentador

### 3.1. Presentador

Nuevo `app/Livewire/Presenters/DebugCombatientePresenter.php`:

```php
final class DebugCombatientePresenter
{
    /**
     * @return array{
     *   tipos: list<string>,
     *   habilidad: array{base: ?string, activas: list<string>},
     *   objeto: array{clave: string, nombre: string, cargas: int, activo: bool, bloqueado: bool}|null,
     *   stats: list<array{clave: string, label: string, actual: int, max: int}>,
     *   movimientos: list<array{
     *     nombre: string, tipo: string, categoria: string,
     *     potencia: int, precision: int, prioridad: int,
     *     usos: ?int, usosMax: ?int, disponible: bool, motivo: ?string
     *   }>
     * }
     */
    public static function para(Combatiente $combatiente): array
}
```

Reglas:

- `tipos`: `tiposDefensivos()->nombres()`.
- `habilidad.base`: `pokemon()->habilidad()` (normalmente `null`).
- `habilidad.activas`: claves de `effects()->all()` excluyendo la del objeto, deduplicadas.
- `objeto`: `null` si `item() === ''`; si no, `clave`, `nombre`, `cargas`, `activo`, `bloqueado`.
- `stats`: orden fijo PS, Ataque, Defensa, At. Especial, Def. Especial, Velocidad; `actual` con `hpActual()`/`obtenerStatEfectivo()`; `max` con `battleStats()`.
- `movimientos`: todos, incluidos no disponibles.
  - `tipo` = `TipoPokemon::label()`.
  - `categoria` = `Físico`/`Especial`/`Estado`.
  - `precision` = `(int) round($m->perfil->precision * 100)`.
  - `prioridad` = `$m->priority`.
  - `usosMax` = `perfil->usosMaximos > 0 ? ... : null`.
  - `usos` = `null` si `usosMax === null`; si no, `max(0, usosRestantes())`.
  - `disponible`/`motivo` con `movimientoDisponible()`/`motivoMovimientoNoDisponible()`.

Precisión y PP máximo **sí están disponibles** en `PerfilMovimiento`; no hace falta tocar `MovimientoBatalla` ni serialización.

### 3.2. Gate en `SincronizadorVistaBatalla`

```php
$debug = (bool) config('app.debug');

$team2 = array_map(
    function (Combatiente $c) use ($debug): array {
        $vista = $c->aArrayVista(1);

        if ($debug) {
            $vista['debug'] = DebugCombatientePresenter::para($c);
        }

        return $vista;
    },
    $battle->team2->combatants()->all(),
);
```

- Gate server-side; no `@if` en Blade.
- No `env('APP_DEBUG')` en runtime.
- No añadir `debug` a `CombatienteVista`.
- En producción, `team2` no contiene `debug` ni moveset/stats ofensivas nuevas.

## 4. UI hover-only y CSS

En `_pokemon-card.blade.php`, añadir la clase y el bloque del tooltip en la raíz de la carta:

```blade
<div class="{{ $p['claseCarta'] }}{{ ! empty($p['debug']) ? ' pokemon-card--debug-rival' : '' }}"
     data-ref-id="{{ $p['refId'] }}"
     data-team="{{ $p['team'] }}"
     @if($p['selectable']) wire:click="previewTarget({{ $p['team'] }}, {{ $idx }})" role="button" tabindex="0" @endif>

    @if(($p['team'] ?? 0) === 1 && ! empty($p['debug']))
        @php($debug = $p['debug'])
        <div class="debug-tooltip" role="tooltip" aria-hidden="true">
            {{-- tipos, habilidad, objeto, stats y movimientos con {{ }} --}}
        </div>
    @endif

    <div class="card-body p-2 ...">
```

CSS en `combate.css`:

```css
.pokemon-card--debug-rival {
    position: relative;
}

.debug-tooltip {
    display: none;
    position: absolute;
    inset: 0;
    z-index: 60;
    overflow: hidden;
    padding: .45rem .55rem;
    border: 1px solid #475569;
    border-radius: inherit;
    background: rgba(15, 23, 42, .97);
    color: #e2e8f0;
    font-size: .68rem;
    line-height: 1.25;
    pointer-events: none;
}

@media (hover: hover) and (pointer: fine) {
    .pokemon-card--debug-rival:hover .debug-tooltip {
        display: block;
    }
}
```

Ventajas: no hace falta `overflow: visible`, no hay conflicto con `wire:click` (`pointer-events: none`), no se sale del viewport, solo hover real, sin JS ni dependencias.

## 5. Compatibilidad y casos límite

- `SESSION_VERSION` sigue en 21; sin cambios de serialización.
- Sesiones antiguas cargan igual; el tooltip se calcula en cada sincronización.
- `APP_DEBUG` en caliente: el array aparece/desaparece en el siguiente render.
- `team1` nunca recibe `debug` ni pinta tooltip.
- Rival sin prioridades: pre-chequeo evita la evaluación; flujo normal.
- Rival con solo prioridades: se evalúa y se declara la mejor si es prioridad.
- Prioridad de apoyo a aliado: no se declara; flujo normal.
- Prioridad autodirigida: se declara; objetivo actor.
- Protección/Aguante en cooldown: filtro de TASK-040 y pre-chequeo.
- `DESAFIO`: el selector ya aplica el forzado.
- Dormido/paralizado: `resolver()` comprueba `puedeActuar()`; no duplicar RNG.
- Sin movimientos: “Espera” prioridad 0; no se declara.
- `team1` derrotado: guard al inicio.
- Acciones extra: la prioridad consume una extra; coherente.
- Tooltip en carta K.O.: se muestra igual; no altera la vista.
- Accesibilidad: hover-only aceptado; `aria-hidden="true"`.

## 6. Archivos y orden

### Backend

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Nueva política con `elegirAccionPara()`; pre-chequeo; limpieza; filtro de apoyo. |
| B2 | `app/Livewire/Presenters/DebugCombatientePresenter.php` | **Nuevo**. Contrato `para(Combatiente): array`. |
| B3 | `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php` | Gate `config('app.debug')` y `debug` solo en `team2`. |

B3 depende de B2. B1 es independiente.

### Frontend

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/_pokemon-card.blade.php` | Clase `pokemon-card--debug-rival` y bloque `.debug-tooltip` solo para `team === 1` con `debug`. |
| F2 | `resources/css/combate.css` | Estilos hover-only, overlay interno, `pointer-events: none`. |

F1/F2 dependen del contrato de B3. No se toca `resources/js/combate.js`.

### Sin cambios

- `src/Battle/Domain/MovimientoBatalla.php`
- `src/Battle/Domain/ValueObjects/PerfilMovimiento.php`
- `src/Battle/Domain/CombatienteVista.php`
- `src/Battle/Domain/AI/SelectorAccionIA.php`
- `src/Battle/Domain/AgregadoBatalla.php`
- `app/Support/BattleSessionService.php`
- `resources/js/combate.js`
- `SESSION_VERSION`, migraciones, dependencias, tests.

## 7. Criterios de aceptación

### IA

- **CA-IA-1:** Riolu contra Fantasma no declara Ataque Rápido si Garra Metal es la mejor acción; el log no muestra `RIVAL PRIORIDAD: Ataque Rápido` y sí una acción normal con Garra Metal.
- **CA-IA-2:** si la mejor acción tiene prioridad > 0, se declara y resuelve antes.
- **CA-IA-3:** si la mejor acción tiene prioridad 0, no se declara y el flujo normal la ejecuta.
- **CA-IA-4:** el filtro de Protección/Aguante de TASK-040 sigue vigente.
- **CA-IA-5:** una prioridad de apoyo a aliado no se declara; el flujo normal la resuelve.
- **CA-IA-6:** una prioridad autodirigida se declara y se aplica al actor.
- **CA-IA-7:** un rival con `DESAFIO` que declara prioridad golpea al objetivo forzado.
- **CA-IA-8:** `SESSION_VERSION` sigue en 21; sesión antigua carga.
- **CA-IA-9:** un rival sin prioridad disponible no provoca `elegirAccionPara()`.
- **CA-IA-10:** no se declaran dos prioridades para el mismo rival en la misma ronda.

### Tooltip

- **CA-DBG-1:** con `APP_DEBUG=false`, `team2` no contiene `debug` y el DOM no contiene `.debug-tooltip`; no viajan moveset ni stats ofensivas nuevas.
- **CA-DBG-2:** con `APP_DEBUG=true`, cada rival contiene `debug` y el hover muestra tipos, habilidad/efectos, objeto, 6 stats actual/máx y todos los movimientos con tipo, categoría, potencia, precisión, prioridad y usos.
- **CA-DBG-3:** `team1` no contiene `debug` ni pinta tooltip.
- **CA-DBG-4:** solo hover; sin `tabindex`, `:focus`, `:focus-within` ni listeners táctiles.
- **CA-DBG-5:** el tooltip no se recorta ni cambia el layout fuera del hover.
- **CA-DBG-6:** con el tooltip visible, `wire:click="previewTarget(...)"` sigue seleccionando al rival.
- **CA-DBG-7:** todo con `{{ }}`; sin `{!! !!}`.
- **CA-DBG-8:** no se modifican `CombatienteVista`, `MovimientoBatalla`, `PerfilMovimiento` ni `SESSION_VERSION`.
- **CA-DBG-9:** sin dependencias; solo Blade y CSS.

### Verificación sin tests

- `php -l`, PHPStan, Pint, `view:cache`, `npm run build`.
- Greps: `config('app.debug')` solo en `SincronizadorVistaBatalla`; `debug` en presentador/sincronizador/carta; `SESSION_VERSION` 21; `elegirAccionPara`/`ClasificadorAccionIA` en `GestorPrioridadesBatalla`.
- Manual:
  - `APP_DEBUG=false`: batalla Riolu vs Fantasma; payload/DOM sin `debug`.
  - `APP_DEBUG=true`: hover sobre rival; contenido completo.
  - 2v2/3v3: prioridades, apoyo, autodirigidas y `DESAFIO`.
  - Sesión antigua.
  - Selección de objetivo con tooltip visible.

## 8. Riesgos residuales y preguntas

1. **[Alto] Cambio de balance de la IA.** Menos prioridades inefectivas y más uso del movimiento correcto. Requiere aprobación explícita.
2. **[Medio-alto] Perturbación del RNG global.** `CalculadoraDanioIA::estimar()` usa `mt_srand()`; la evaluación extra en preparación desplaza la secuencia de `mt_rand()` y puede cambiar críticos/estados. No es un fallo de estado, pero es observable.
3. **[Medio] Doble evaluación.** Para rivales con prioridad disponible cuya mejor acción no es prioridad, se evalúa en preparación y otra vez en el turno normal. Mitigación: pre-chequeo de prioridad; medir en 3v3.
4. **[Medio] Objetivo de la IA re-evaluado en `resolver()`.** `objetivoPara()` vuelve a llamar a `elegirObjetivoPara()` después de que otras prioridades cambien el estado; el defensor puede no coincidir con el declarado. Preexistente; corregirlo requeriría guardar el objetivo (serialización).
5. **[Medio] Fuga de debug.** Mitigado con gate server-side. `APP_DEBUG=true` en producción sigue siendo riesgo operativo.
6. **[Bajo] Habilidad base `null`.** `pokemon()->habilidad()` no se rellena en batalla; se muestran efectos activos.
7. **[Bajo] Tooltip superpuesto a la carta.** Cubre la información mientras se lee; debug-only.
8. **[Bajo] Precisión redondeada.** `PerfilMovimiento::$precision` es float 0-1; se muestra como porcentaje entero.

### Preguntas abiertas

1. ¿Se aprueba el cambio de comportamiento de la IA (riesgo de balance)?
2. ¿Se acepta la perturbación del RNG global o se autoriza un refactor separado para aislar el azar de `CalculadoraDanioIA`?
3. ¿Se acepta la doble evaluación o se autoriza un caché por ronda? El caché requeriría estado serializado y probablemente `SESSION_VERSION`.
4. ¿Se confirma el tooltip superpuesto dentro de la carta?
5. ¿Se confirma no tocar `DESAFIO`?
6. ¿Se confirma mostrar las habilidades como claves de efectos activos, sin nombre legible?
7. ¿Se incluye la efectividad de cada movimiento contra el objetivo actual en el tooltip? No es necesario, pero es útil para debug.
