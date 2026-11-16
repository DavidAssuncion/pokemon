# TASK-041 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e3fb16a9dffezoUsZ5Dzfy0iH9`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# TASK-041 — Análisis: IA contra Fantasma y tooltip de debug

## 1. Resumen ejecutivo

La causa raíz está confirmada: `GestorPrioridadesBatalla::declararIa()` **no usa `SelectorAccionIA`**. Recorre los movimientos del rival y declara el de **mayor `priority`**; solo compara potencia como desempate. Como Riolu tiene `Ataque Rápido` (Normal, prioridad 1) y `Garra Metal` (Acero, prioridad 0), `declararIa()` declara `Ataque Rápido` y `Garra Metal` nunca llega al flujo normal de la IA.

Matiz importante: `TypeChart` **no marca daño 0** contra Fantasma. `TypeChart::INMUNE` vale **0.25** (`src/Shared/Tipos/TypeChart.php:20-23`). Normal y Lucha contra Fantasma multiplican por 0.25 (`TypeChart.php:34-39` y `:41-56`). El daño de `Ataque Rápido` no es 0: es aproximadamente el 25 % y, por el mínimo de la cadena, al menos 1 (`CadenaDanio.php:48-68`). `ManejadorInmunidadTipo` solo anula daño por habilidades/inmunidades, no por la matriz de tipos (`ManejadorInmunidadTipo.php:20-31`). Aun así, `Garra Metal` es claramente superior contra Fantasma: 50 de potencia neutral frente a 10 efectivos de `Ataque Rápido` (40 × 0.25), sin STAB para ninguno de los dos porque Riolu es Lucha.

La corrección recomendada es pequeña: en `declararIa()`, obtener la **mejor acción con la misma evaluación que usa la IA normal** (`AgregadoBatalla::elegirAccionPara()` → `SelectorAccionIA`) y **declarar prioridad solo si esa mejor acción es un movimiento con `priority > 0`**. Si la mejor es `Garra Metal`, no se declara prioridad y el flujo normal la ejecuta.

Para el tooltip: el gate debe estar en servidor, al proyectar `team2`, no solo en Blade/CSS. `team2` es estado público de Livewire y viaja al navegador. Propuesta: `SincronizadorVistaBatalla` añade un array `debug` **solo al equipo rival** y **solo si `config('app.debug')` es true**. La vista pinta un tooltip hover-only con CSS, sin dependencias nuevas.

## 2. Causa raíz de la selección de movimiento

### 2.1. `declararIa()` selecciona por prioridad, no por valor

Evidencia:

- `App\Livewire\Combate\Soporte\GestorPrioridadesBatalla::declararIa()` (`:28-57`).
- `:37-39` — descarta todo movimiento con `priority <= 0` o no disponible.
- `:45-49` — entre los que quedan, elige mayor prioridad y, en empate, mayor potencia. **No consulta tipo, efectividad, daño, KO, supervivencia ni riesgo.**
- `:52-56` — declara la prioridad, fuerza postura `ATACAR` y limpia objetivo cubierto.

`SelectorAccionIA` no aparece en `declararIa()`. Solo se usa en el flujo normal:

- `GestorAccionBatalla::prepararAnimacionIa()` (`:80-112`) llama a `$battle->elegirAccionPara($actor)`.
- `AgregadoBatalla::elegirAccionPara()` (`:466-471`) delega en `SelectorAccionIA::elegirAccion()`.
- `SelectorAccionIA::elegirAccion()` (`:61-110`) hace la evaluación completa.
- `EvaluadorAccionIAImpl` (`:48-94`) puntúa por KO, daño efectivo, amenaza, supervivencia, riesgo y lookahead. **No hay término de prioridad.**

Por tanto, el flujo normal, si llegara, elegiría `Garra Metal` contra Fantasma; el problema es que no llega.

### 2.2. Qué elige ahora y por qué Garra Metal no llega

1. `GestorPreparacionBatalla::aplicar()` llama a `declararIa()` (`:229`), después de preparar al jugador.
2. `declararIa()` ve `Ataque Rápido` disponible y con prioridad 1. `Garra Metal` tiene prioridad 0 y se descarta.
3. Declara `Ataque Rápido`.
4. `GestorPrioridadesBatalla::resolver()` ejecuta las prioridades declaradas antes del flujo normal, aplica el movimiento y consume la acción.
5. Cuando el ciclo de turnos llega al rival, ya no tiene acción pendiente; el camino de IA normal no se ejecuta.

Si `Ataque Rápido` y `Onda Vacío` empatan a prioridad y potencia, gana el primero del moveset. En cualquier caso, es un movimiento de prioridad, no `Garra Metal`.

### 2.3. Relación con TASK-040

TASK-040 corrigió el bloqueo perpetuo de Aguante/Protección/Detección, pero dejó explícita la deuda: Riolu seguiría usando Ataque Rápido/Onda Vacío y no Garra Metal mientras `declararIa` declare cualquier prioridad. Eso es lo que cambia TASK-041.

### 2.4. Efectividad y daño

- `TypeChart::INMUNE = 0.25` (`:20-23`), no 0.
- Normal → Fantasma = 0.25 (`:34-39`); Lucha → Fantasma = 0.25 (`:41-56`).
- `ManejadorEfectividadTipo` multiplica el daño (`:10-13`, `:49`).
- `CadenaDanio` aplica mínimo 1 si el movimiento no es de estado (`:48-68`).
- `ManejadorInmunidadTipo` solo anula por habilidad/condición, no por la matriz (`:20-31`).

| Movimiento | Tipo | Potencia | STAB Riolu (Lucha) | Efectividad vs Fantasma | Potencia efectiva |
|---|---|---:|---|---:|---:|
| Ataque Rápido | Normal | 40 | No | 0.25 | 10 |
| Garra Metal | Acero | 50 | No | 1.0 | 50 |
| Onda Vacío / Golpe Roca | Lucha | 40 | Sí (1.5) | 0.25 | 15 |

`CalculadoraDanioIA::estimar()` (`:32-70`) no usa precisión ni probabilidad de fallo; estima daño determinista. Garra Metal tiene 95 de precisión y Ataque Rápido 100, pero eso no entra en la puntuación actual.

## 3. Integración con SelectorAccionIA

### Opción A — recomendada: decidir con `elegirAccionPara()` y declarar solo si la mejor acción es prioridad

```php
public function declararIa(AgregadoBatalla $battle): void
{
    foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
        $rival->limpiarPrioridad();
        $rival->setPostura(PosturaCombate::ATACAR);
        $rival->setObjetivoCubiertoId('');

        if (! $this->tienePrioridadDisponible($rival)) {
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
```

Ventajas:

- Reutiliza la misma evaluación que la acción normal: tipo, daño, KO, amenaza, supervivencia, riesgo y lookahead.
- Respeta el filtro de protección de TASK-040, porque `SelectorAccionIA::generarAccionesCandidatas()` ya lo aplica.
- No duplica la lógica de puntuación.
- Si la mejor acción es Garra Metal (prioridad 0), no se declara prioridad y el flujo normal la ejecuta.

Costes/riesgos:

- **Doble evaluación** cuando la mejor acción no es prioridad: preparación y turno normal. Mitigación: pre-chequear `tienePrioridadDisponible()`.
- **`pendingAction`**: no se toca.
- **Postura**: fijar `ATACAR` y limpiar objetivo cubierto para todos los rivales vivos al inicio, no solo al declarar prioridad; si no, un rival con `CUBRIR` de una ronda anterior podría no atacar.
- **`DESAFIO`**: `SelectorAccionIA` respeta el objetivo forzado al generar acciones, pero `GestorPrioridadesBatalla::objetivoPara()` solo aplica forzado/declarado al equipo del jugador; para la IA usa `elegirObjetivoPara()`. Si un rival con `DESAFIO` declara prioridad, el objetivo puede no ser el forzado. Opciones: mover la comprobación de `objetivoForzadoDesafio()` antes del bloque `team1` o no declarar prioridad bajo `DESAFIO`.
- **Autodirigidos**: seguros; `objetivoPara()` devuelve el actor.
- **Soporte a aliado**: `resolver()` no soporta objetivo aliado en prioridades; no declarar apoyo a aliado; el flujo normal lo resuelve.
- **Acciones extra**: la prioridad consume una extra; coherente.
- **Aleatoriedad por dificultad**: `seleccionarSegunDificultad()` puede elegir entre las N mejores con `array_rand`; preparación y ejecución podrían diferir. Riesgo bajo; decidir si usar PERFECTA o aceptar.

### Opción B — método que devuelva `ResultadoDecision`

Ventaja: inspeccionar scores/logs. Desventaja: más API y doble evaluación si el flujo normal vuelve a decidir. Solo si se quiere logging de IA.

### Opción C — comparar prioridades con la mejor acción normal

Duplica evaluación y lógica; rechazada.

### Opción D — no declarar prioridades nunca para la IA

Simple, pero rompe la mecánica de prioridad; cambio de balance grande. Rechazada salvo decisión de producto.

### Recomendación

**Opción A**, con:

1. Limpiar prioridad y fijar `ATACAR`/objetivo vacío para todos los rivales vivos.
2. Pre-chequear prioridad disponible.
3. Usar `$battle->elegirAccionPara($rival)`.
4. Declarar solo si `priority > 0` y no es apoyo a aliado.
5. Corregir `DESAFIO` en `objetivoPara()` o no declarar prioridad bajo `DESAFIO`.
6. No tocar `EvaluadorAccionIAImpl`, `CalculadoraDanioIA` ni `TypeChart`.

## 4. Tooltip de debug

### 4.1. Contrato propuesto

```php
'debug' => [
    'tipos' => ['Lucha'],
    'habilidad' => ['base' => 'inner-focus', 'activas' => ['...']],
    'objeto' => ['clave' => '...', 'nombre' => '...', 'cargas' => 1, 'activo' => true, 'bloqueado' => false],
    'stats' => [
        'hp' => ['actual' => 120, 'max' => 150],
        'def' => ['actual' => 30, 'max' => 50],
        'spdef' => ['actual' => 30, 'max' => 50],
        'ataque' => 80,
        'defensa' => 60,
        'ataque_esp' => 40,
        'defensa_esp' => 60,
        'velocidad' => 90,
    ],
    'movimientos' => [
        [
            'nombre' => 'Garra Metal',
            'tipo' => 'Acero',
            'categoria' => 'fisico',
            'potencia' => 50,
            'precision' => 95,
            'usos' => 15,
            'prioridad' => 0,
        ],
    ],
]
```

Fuentes:

- **Movimientos:** `Combatiente::pokemon()->moves()->all()`; `MovimientoBatalla` expone `nombre`, `potencia`, `tipo`, `categoria`, `priority`, `perfil`.
- **PP/usos:** `CombatienteVista` ya expone `usosMovimiento` y `cargasMovimiento`; `movimientoDisponible()` marca disponibilidad.
- **Stats actuales:** `hpActual()`, `defensaHpActual()`, `defensaEspHpActual()`, `obtenerStatEfectivo(...)`.
- **Stats máximas:** `pokemon()->battleStats()`; confirmar campos de ataque/defensa especial máximos.
- **Tipos:** `Combatiente::tiposDefensivos()`.
- **Habilidad:** `pokemon()->habilidad()` y `CombatienteVista['habilidades']`; no se localizó catálogo de nombres, mostrar clave o presentador.
- **Objeto:** `item()`, `CatalogoObjetos::nombreDe()`, cargas/activo/bloqueado ya en `CombatienteVista`.

**Precisión y PP máximo:** `MovimientoBatalla` no expone `precision`; el JSON tiene `accuracy`, pero no se localizó el cargador. Añadirlo puede afectar serialización/`SESSION_VERSION`; no recomendado en TASK-041. Mostrar potencia, tipo, categoría, prioridad y usos restantes; omitir precisión/PP máximo salvo que ya estén disponibles.

### 4.2. Gate de debug

- `config('app.debug')` en servidor.
- Construir `debug` solo para `team2` en `SincronizadorVistaBatalla` (`:33-41`), no en Blade.
- `team1`/`team2` son propiedades públicas Livewire; un `@if` en Blade no impide la fuga. El array no debe construirse si `APP_DEBUG=false`.
- No añadir `debug` incondicional en `CombatienteVista::para()`.
- No usar `env('APP_DEBUG')` en runtime; usar `config('app.debug')`.

La vista ya envía datos del rival necesarios (HP, barreras, velocidad, tipos, objeto, habilidades activas); el gate aplica a los **datos nuevos de debug** (moveset, stats ofensivas, detalle de habilidad/objeto), no a los que ya usa la UI.

### 4.3. UI hover-only

Patrones existentes:

- `title` nativo en `_pokemon-card.blade.php`.
- Bootstrap modal, pero no tooltip/popover inicializado.
- Bootstrap JS solo en `combate.js`; Alpine global.
- `combate.css` y `combate.js` cargados en `combate_page.blade.php`.

Recomendación: **CSS puro**, sin JS ni dependencias, en `_pokemon-card.blade.php`:

```blade
@if(($p['team'] ?? 0) === 1 && !empty($p['debug']))
    <div class="debug-tooltip" role="tooltip">
        <span class="debug-tooltip-trigger">DEBUG</span>
        <div class="debug-tooltip-content">
            {{-- tipos, habilidad, objeto, stats, movimientos --}}
        </div>
    </div>
@endif
```

CSS:

```css
.debug-tooltip { position: relative; display: inline-block; }
.debug-tooltip-content {
    display: none;
    position: absolute;
    z-index: 50;
    top: 100%;
    left: 0;
    width: min(320px, 90vw);
    max-height: 60vh;
    overflow-y: auto;
    padding: .5rem .75rem;
    background: #1f2937;
    color: #f8fafc;
    border: 1px solid #475569;
    border-radius: .5rem;
    font-size: .75rem;
    line-height: 1.35;
    pointer-events: none;
}
.debug-tooltip:hover .debug-tooltip-content { display: block; }
@media (hover: hover) and (pointer: fine) {
    .debug-tooltip:hover .debug-tooltip-content { display: block; }
}
```

Puntos críticos:

- **Overflow recortado:** `.pokemon-card { overflow: hidden; }` (`combate.css:95-99`). Opciones: `.pokemon-card.debug-rival { overflow: visible; }` (simple) o Bootstrap Popover con `container: 'body'` (más complejo). Recomendada la primera.
- **Responsive:** `width: min(320px, 90vw)`, `max-height: 60vh`, scroll. En móvil no hay hover; aceptado.
- **Escapado:** todo con `{{ }}`; no `{!! !!}`.
- **Accesibilidad:** hover-only no accesible por teclado; aceptado. `role="tooltip"` y `aria-label` resumido opcional.
- **Interacción:** hover sobre toda la carta o badge DEBUG. El texto del usuario sugiere toda la carta; un badge evita tapar información y conflictos con `wire:click`.

## 5. Archivos y orden

### Backend

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Nueva política con `elegirAccionPara()`; limpiar prioridad/postura/objetivo; declarar solo prioridad; no apoyo a aliado; `DESAFIO` opcional. |
| B2 | `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php` | `debug` solo a `team2` y solo con `config('app.debug')`. |
| B3 | `app/Livewire/Presenters/DebugCombatientePresenter.php` (nuevo) | Payload de debug desde `Combatiente`/`PokemonEntity`/`CombatienteVista`. |
| B4 | `src/Battle/Domain/AI/SelectorAccionIA.php` | Sin cambios si se usa `elegirAccionPara()`. |
| B5 | `src/Battle/Domain/AgregadoBatalla.php` | Sin cambios si `elegirAccionPara()` basta. |
| B6 | `src/Battle/Domain/MovimientoBatalla.php` | Evitar; precisión/PP máximo afectan serialización. |
| B7 | `src/Battle/Domain/CombatienteVista.php` | Evitar para debug; el presentador de app es mejor. |

### Frontend

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/_pokemon-card.blade.php` | Tooltip solo si `team === 1` y `debug` no vacío; renderizar datos con `{{ }}`. |
| F2 | `resources/css/combate.css` | Estilos, `overflow: visible` debug-only, hover-only, responsive. |
| F3 | `resources/js/combate.js` | Sin cambios si es CSS puro; solo si se usa Bootstrap Popover. |

Orden: B1 → B2+B3 → F1+F2 → verificación → revisión/seguridad.

## 6. Criterios de aceptación

### IA

- **CA1:** contra Fantasma, Riolu usa Garra Metal o al menos no declara Ataque Rápido si Garra Metal puntúa más.
- **CA2:** si la mejor acción es prioridad, se declara y resuelve antes.
- **CA3:** si la mejor acción es prioridad 0, no se declara y el flujo normal la ejecuta.
- **CA4:** el filtro de Aguante/Protección de TASK-040 sigue vigente.
- **CA5:** no se rompe `pendingAction`, orden de turnos, acciones extra ni soporte.
- **CA6:** `DESAFIO` se corrige o se evita declarar prioridad bajo `DESAFIO`.
- **CA7:** autodirigidos con prioridad se declaran y aplican al actor.
- **CA8:** `SESSION_VERSION` 21; sin serialización.

### Tooltip

- **CA9:** con `APP_DEBUG=false`, payload/HTML sin `debug` ni moveset/stats ofensivas del rival.
- **CA10:** con `APP_DEBUG=true`, hover sobre carta rival muestra movimientos (nombre, tipo, categoría, potencia, prioridad, usos; precisión si está disponible), stats actuales/máximas, tipos, habilidad y objeto.
- **CA11:** `team1` no muestra tooltip.
- **CA12:** solo hover; no tap ni teclado; sin errores JS.
- **CA13:** todo escapado; sin `{!! !!}`.
- **CA14:** no recortado por `overflow: hidden`.

### Verificación sin tests

- `php -l`, PHPStan, Pint, `view:cache`.
- Greps de `config('app.debug')`, `debug`, `priority`.
- Manual: `APP_DEBUG` false/true, Riolu vs Fantasma, 2v2, sesión antigua, hover/no recorte.

## 7. Riesgos, casos límite y preguntas

### Riesgos

- **[alto] Cambio de comportamiento de la IA:** menos prioridades inefectivas, más uso de movimientos correctos; altera balance. Aprobación explícita.
- **[alto] Doble evaluación de `SelectorAccionIA`:** preparación + turno normal. Mitigación: pre-chequeo de prioridad disponible; medir 2v2.
- **[medio] `DESAFIO` en prioridades:** corregir `objetivoPara()` o no declarar prioridad bajo `DESAFIO`.
- **[medio] Efectos secundarios durante la evaluación:** `ManejadorDanioBase` aplica `aplicarTipoOverride` para `mutatipo`/`libero`; verificar `SimuladorAccionIA` antes de implementar; si muta, clonar o evitar evaluación en preparación.
- **[medio] Fuga de debug en producción:** gate en `SincronizadorVistaBatalla`, no en Blade.
- **[medio] Tooltip recortado o inutilizable en móvil:** `overflow: visible` debug-only; aceptar no móvil.
- **[medio] Precisión/PP máximo no disponibles:** mostrar usos restantes y omitir precisión/máximo; no añadir campos.
- **[bajo] Decisión distinta preparación/ejecución por dificultad aleatoria:** usar PERFECTA o documentar.
- **[bajo] `elegirAccionPara()` sin enemigos:** guard si es barato.
- **[bajo] Nombres de habilidad:** mostrar clave o presentador; no bloqueante.
- **[bajo] XSS:** todo con `{{ }}`.
- **[bajo] `APP_DEBUG=true` en producción:** herramienta de desarrollo; documentar.

### Casos límite

- Rival con solo prioridades: se declara la mejor.
- Rival sin prioridades: no se declara; flujo normal; pre-chequeo evita evaluación.
- Prioridad de apoyo a aliado: no declarar; flujo normal.
- Prioridad autodirigida: declarar; objetivo actor.
- `DESAFIO`: corregir o evitar.
- Acciones extra: consume una extra; puede volver a actuar.
- Dormido/paralizado: `resolver()` comprueba `puedeActuar()`; no duplicar RNG.
- Sin movimientos: “Espera” prioridad 0; no declarar; flujo normal.
- `team1` derrotado: batalla termina antes; guard si es barato.
- Sesión antigua: sin cambios de serialización.
- `APP_DEBUG` en caliente: Livewire reconstruye; tooltip aparece/desaparece.

### Preguntas abiertas

1. ¿Tooltip sobre toda la carta o badge DEBUG? El usuario sugiere toda la carta; un badge evita tapar información.
2. ¿Dónde se mapea `accuracy` a `MovimientoBatalla`/`PerfilMovimiento`? No localizado.
3. ¿Existe máximo de PP/usos en `MovimientosCombatiente`? Solo se vieron usos restantes.
4. ¿Habilidad base, activas o ambas?
5. ¿Corregir `DESAFIO` en TASK-041 o evitar prioridad bajo `DESAFIO`?
6. ¿Forzar `PERFECTA` para determinismo preparación/ejecución?
7. ¿Aceptar doble cálculo o caché por ronda? Caché requeriría estado serializado.
8. ¿`APP_DEBUG` suficiente o además `app()->environment('local','testing')`?
9. ¿Stats efectivos actuales o base/máximos? Recomendado ambos.
10. ¿Mostrar efectividad de cada movimiento contra el rival? Útil para debug, no requerido.

---

**Conclusión:** la causa raíz está confirmada en `declararIa()`; la opción recomendada es decidir con `elegirAccionPara()` y declarar prioridad solo si la mejor acción es prioritaria, limpiando postura/prioridad y resolviendo `DESAFIO`. El tooltip debe construirse solo en servidor con `config('app.debug')`, solo para `team2`, con CSS hover-only y `overflow: visible` debug-only. No se requieren cambios de serialización, sesión, migraciones ni tests.
