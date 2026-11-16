# TASK-036 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e4094931bffeZ7ZNsbZXc71QQR`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-036 — Diseño de autoobjetivo de Bucle Arena y derivados

> Diseño de solo lectura. No se modificó ningún archivo. Evidencia citada sobre la rama `task-011`.

---

## 1. Decisiones de diseño (incluidas las de producto)

### 1.1 Alcance exacto

**Se confirma el alcance del analista:** solo `sand-tomb` (Bucle Arena), `fire-spin` (Giro Fuego) y `whirlpool` (Torbellino). Son los únicos tres movimientos de `CatalogoEfectosAliado` con `target: "normal"` en `storage/data/moves.json`; los otros 43 tienen `target: "self"` y ya funcionan por la ruta autodirigida.

No se abre el frente de los 21 movimientos aliados fuera del catálogo (`allySide`, `allies`, `adjacentAlly`, `allyTeam`, `adjacentAllyOrSelf`): siguen rechazados por `GestorSeleccionBatalla.php:151-155` y no tienen efecto declarado. Permitir su autoobjetivo sería otra tarea.

**Corrección al contexto recibido:** `protect` no está en `CatalogoEfectosAliado`; es autodirigido por `target: self`. No entra en este cambio.

### 1.2 Predicado de dominio

Se crea un único predicado `CatalogoEfectosAliado::permiteAutoobjetivo(string $slug): bool` con **lista explícita** de los tres slugs. Detalle y justificación en la sección 2.

### 1.3 Presentación: opción B.2.b (flag separado)

**Se elige B.2.b, no B.2.a.** `refsAliado()` mantiene su significado actual: “aliados vivos distintos del actor”. Se añade un flag de presentación `actorAutoobjetivoSeleccionable`, calculado desde `currentMoves`, y se usa solo para marcar la carta del actor como `selectable`.

Motivos con evidencia:

1. `refsAliado` se construye con `$actorAdmiteAliado` (`BatallaVistaPresenter.php:122-125`), que se calcula con `CatalogoEfectosAliado::admite()` (`PresentadorVistaCombate.php:53-62`). Ese predicado incluye **los 46** movimientos del catálogo, incluidos los 43 autodirigidos. B.2.a haría seleccionable la carta del actor con `recover`, `swords-dance`, etc., ampliando la UX más allá del alcance.
2. `aliadosSeleccionables` se deriva de `refsAliado !== []` (`BatallaVistaPresenter.php:104`) y alimenta el aviso de `items-panel.blade.php:37-41` (“Puedes seleccionar un aliado para movimientos de apoyo”). B.2.a mostraría ese aviso en combates sin aliados, lo que es una regresión visual.
3. `refsAliado` se devuelve en la vista (`BatallaVistaPresenter.php:103`) pero **no lo consume ningún Blade**. Por tanto B.2.a no aporta nada que B.2.b no resuelva, y sí cambia semántica.

Coste de B.2.b: un parámetro nuevo en `BatallaVistaPresenter::toView()` y en `marcarInteraccion()`, y el cálculo en `PresentadorVistaCombate`. Es acotado y no toca el contrato público de `Combate` (son arrays de vista, no propiedades públicas nuevas).

### 1.4 `DESAFIO`

**Se permite el autoobjetivo y no se redirige al rival forzado.** El `DESAFIO` es una condición de ataque; redirigir un apoyo sobre uno mismo lo convertiría silenciosamente en un ataque contra el rival. La exclusión debe aplicarse solo cuando el objetivo seleccionado **es el actor**, no cuando el movimiento es autoobjetivable pero se dirige a un rival.

**Corrección al analista:** su B.4 propone excluir `$autoobjetivoCatalogo` de la condición de `DESAFIO` (`GestorSeleccionBatalla.php:164-169`). Eso desactivaría la redirección también para `sand-tomb` lanzado contra un rival, porque `$autoobjetivoCatalogo` es una propiedad del movimiento, no del objetivo. Debe usarse `$esAutoobjetivo` (movimiento autoobjetivable **y** objetivo = actor).

### 1.5 Gasto de PP/turno cuando el beneficio es nulo

**Se permite, por paridad con el comportamiento actual sobre aliados.** `EjecutorEfectoAliado::aplicarEscudo()` ya registra “ya tiene la DEF al máximo” y consume igualmente PP/turno (`EjecutorEfectoAliado.php:79-85`). `AQUA_RING` refresca 5 turnos mediante `reemplazarCondicion()` (`EjecutorEfectoAliado.php:119-124`), no acumula. No se añade ninguna comprobación de “beneficio nulo”: sería una decisión de producto distinta y afectaría también al uso sobre aliados.

### 1.6 Etiqueta de UI

**Se usa “SOBRE SÍ MISMO” para autoobjetivo y se mantiene “SOBRE ALIADO” para aliado.** Requiere tocar una línea de `moves-panel.blade.php:60`, sustituyendo el texto fijo por un campo de presentación `apoyoEtiqueta`. Es un cambio cosmético mínimo y evita que el jugador lea “SOBRE ALIADO” cuando se está aplicando a sí mismo.

### 1.7 Log

**Se recomienda cambiar el log a “sobre sí mismo”.** El texto actual es `"{$actor->nombre()} usa {$movimiento->nombre} sobre {$aliado->nombre()}"` (`ResolutorAccionBatalla.php:186`). Con autoobjetivo se leería “Pikachu usa Bucle Arena sobre Pikachu”, que parece un error. Cambio mínimo propuesto:

```php
$objetivoLog = $aliado->id() === $actor->id() ? 'sí mismo' : $aliado->nombre();
$log[] = "{$actor->nombre()} usa {$movimiento->nombre} sobre {$objetivoLog}";
```

Esto modifica `ResolutorAccionBatalla.php`, que el analista marcaba como “no tocar”. Es un cambio de una línea, sin efecto funcional, y se somete a aprobación explícita. Si se prefiere el mínimo absoluto, se puede dejar el log actual; no rompe nada.

### 1.8 Animación y eventos

**Sin cambios en `EstadoAnimacionBatalla`.** `crear()` considera autodirigido cuando `objetivo->id() === actor->id()` y deja vacíos `defenderId`/`defenderNombre` (`EstadoAnimacionBatalla.php:44-59`). Con autoobjetivo ocurre lo mismo: se ve el spinner del panel de movimientos y no el banner “atacante → movimiento → defensor”, igual que con Danza Espada. Es aceptable.

**Eventos:** el evento `effect` se acumula con `actorId` y `objetivoId` iguales (`ResolutorAccionBatalla.php:190-195`). `GrabadorEventosBatalla` no filtra por IDs, así que no hay que cambiar nada.

### 1.9 IA

**Intacta.** `AgregadoBatalla::actorAdmiteSoporte()` excluye al actor (`AgregadoBatalla.php:427-461`), `SelectorAccionIA::construirContexto()` también (`SelectorAccionIA.php:178-181`) y `agregarAccionesSoporte()` lo vuelve a excluir (`:319-322`). No se toca nada de `src/Battle/Domain/AI/` ni el dominio de soporte.

### 1.10 Sesión, DTO y contrato

**Sin cambios.** `DTOAccionBatalla` ya transporta `actorId` y `defenderId` (`DTOAccionBatalla.php:17-24`); no se añaden campos. No se añaden propiedades serializadas, por lo que `SESSION_VERSION` sigue igual (`BattleSessionService.php:50,64`). `Combate.php` no gana propiedades ni métodos públicos. `moves.json` no se toca.

---

## 2. Predicado de dominio

### 2.1 Firma y ubicación

En `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php`, junto a `para()` y `admite()`:

```php
/**
 * Movimientos del catálogo que pueden usarse sobre el propio actor.
 *
 * Son los del catálogo cuyo `target` declarado no es `self`. Sin esta lista,
 * `selectMove()` y `resolver()` los enrutarían como ataque normal contra el
 * propio actor. Si se añade al catálogo otro movimiento con `target` distinto
 * de `self`, debe incorporarse aquí de forma explícita.
 *
 * @var list<string>
 */
private const AUTOOBJETIVO = ['sand-tomb', 'fire-spin', 'whirlpool'];

public static function permiteAutoobjetivo(string $slug): bool
{
    return in_array($slug, self::AUTOOBJETIVO, true);
}
```

### 2.2 Lista explícita frente a “catálogo + no autodirigido”

Se elige **lista explícita** por tres razones:

1. **La firma requerida recibe solo `$slug`.** No puede comprobar `esAutodirigido()` sin recibir un `MovimientoBatalla` o duplicar el conocimiento de `target`.
2. **Congela el alcance aprobado.** `self::para($slug) !== null` devolvería `true` para los 43 movimientos autodirigidos y permitiría seleccionar la carta propia con `recover`/`swords-dance`, ampliando la UX sin aprobación.
3. **Evita divergencia presentación/servidor.** Una sola lista usada por las cinco capas elimina el riesgo de que un movimiento se muestre como apoyo y el servidor no lo enrute, o al revés.

**Contrapartida asumida:** si en el futuro se añade al catálogo un movimiento con `target normal`, hay que añadirlo a `AUTOOBJETIVO`. Se documenta en el docblock y se verifica en revisión.

**Invariante:** `AUTOOBJETIVO` debe ser subconjunto de las claves de `para()`. Los tres slugs existen (`CatalogoEfectosAliado.php:22-24`).

---

## 3. Flujo por capa y validaciones

### 3.1 Carta del actor seleccionable (presentación)

**`PresentadorVistaCombate::datos()`** calcula, igual que ya hace con `actorAdmiteAliado` (`PresentadorVistaCombate.php:21,53-62`):

```php
$actorAutoobjetivoSeleccionable = self::actorAdmiteAutoobjetivo($componente->currentMoves);
```

```php
private static function actorAdmiteAutoobjetivo(array $currentMoves): bool
{
    foreach ($currentMoves as $move) {
        if (CatalogoEfectosAliado::permiteAutoobjetivo((string) ($move['slug'] ?? ''))) {
            return true;
        }
    }

    return false;
}
```

Funciona tanto en `player_target` (los `currentMoves` son DTO con `slug`, `GestorCicloTurnosBatalla.php:151-156`) como en `player_move` (previews con `slug`, `MovesPreviewPresenter.php:274`). `currentMoves` es manipulable por el cliente, pero **solo afecta a la selección visual**; el servidor revalida en `previewTarget`, `selectMove` y `resolver`.

**`BatallaVistaPresenter::toView()`** recibe `bool $actorAutoobjetivoSeleccionable = false`, lo combina con la fase y lo pasa a `marcarInteraccion()`:

```php
$actorAutoobjetivoSeleccionable = $actorAutoobjetivoSeleccionable
    && in_array($phase, ['player_target', 'player_move'], true);
```

Así no se activa en `player_item_target` aunque `currentMoves` contenga slugs.

**`marcarInteraccion()`** añade el parámetro y, en la rama no-item, la condición:

```php
|| ($teamIdx === 0
    && $actorAutoobjetivoSeleccionable
    && (string) ($p['refId'] ?? '') === $actingRefId
    && (bool) $p['alive'])
```

La carta del actor ya emite `wire:click="previewTarget(...)"` cuando `selectable` es verdadero (`_pokemon-card.blade.php:4`). No se toca esa vista.

### 3.2 `GestorSeleccionBatalla::previewTarget()`

Guarda de servidor contra una llamada manipulada a `previewTarget(0, idxActor)`:

```php
if ($target->id() === $actor->id()) {
    if (! $this->actorAdmiteAutoobjetivo($actor)) {
        return;
    }
} elseif ($this->esAliado($battle, $actor, $target)) {
    if (! $this->actorAdmiteAliado($actor)) {
        return;
    }
}
```

`actorAdmiteAutoobjetivo()` recorre `$actor->pokemon()->moves()` y usa `CatalogoEfectosAliado::permiteAutoobjetivo($movimiento->slug)`. Solo abre la selección si el actor tiene al menos uno de los tres. El resto de la función no cambia: fija `selectedTarget*`, genera `currentMoves` con `MovesPreviewPresenter::toView($actor, $battle, $target)` y pasa a `player_move`.

### 3.3 `player_move` y previsualización

**`MovesPreviewPresenter::previewDe()`** debe distinguir tres casos y usar **el mismo predicado** que el servidor:

```php
$sobreSiMismo = $target->id() === $actor->id();
$esAliado = ! $sobreSiMismo
    && $battle->equipoDe($target) === $battle->equipoDe($actor);

if ($esAliado) {
    $efecto = CatalogoEfectosAliado::para($move->slug);

    return $efecto === null
        ? self::previewAliadoNoUsable($actor, $move)
        : self::previewAliado($actor, $target, $move, $efecto);
}

if ($sobreSiMismo && ! $move->esAutodirigido()) {
    if (! CatalogoEfectosAliado::permiteAutoobjetivo($move->slug)) {
        return self::previewAliadoNoUsable($actor, $move, 'No usable sobre sí mismo');
    }

    $efecto = CatalogoEfectosAliado::para($move->slug);

    return $efecto !== null
        ? self::previewAliado($actor, $target, $move, $efecto)
        : self::previewAliadoNoUsable($actor, $move, 'No usable sobre sí mismo');
}

// ruta normal actual
```

Cambios concretos:

- `previewAliadoBase()` añade `'apoyoEtiqueta' => ''` y mantiene `'modoAliado' => true` (`MovesPreviewPresenter.php:265-316`).
- `previewAliado()` añade:

  ```php
  $preview['apoyoEtiqueta'] = $target->id() === $actor->id()
      ? 'SOBRE SÍ MISMO'
      : 'SOBRE ALIADO';
  ```

- `previewAliadoNoUsable()` acepta un tercer parámetro `string $motivo = 'No usable sobre un aliado'` y lo usa en `motivoTexto`. Sigue poniendo `modoAliado = false` y `disponible = false`, de modo que Blade no pinta la fila de daño y sí pinta el badge de motivo (`moves-panel.blade.php:103-119`).
- La ruta normal añade `'apoyoEtiqueta' => ''` para que el contrato sea homogéneo.

Para movimientos autodirigidos (`recover`, `swords-dance`, etc.) **no se altera la ruta actual**: el autoobjetivo de esos no forma parte del alcance.

### 3.4 `GestorSeleccionBatalla::selectMove()`

Sustituir el rechazo binario (`:123-135`) por una decisión de tres vías y corregir el `DESAFIO`:

```php
$selfTargeting = $move->esAutodirigido();
$movimientoAutoobjetivable = ! $selfTargeting
    && CatalogoEfectosAliado::permiteAutoobjetivo($move->slug);

$seleccionado = $this->resolutorActor->objetivoPorSeleccion(
    $battle,
    $componente->selectedTargetTeam,
    $componente->selectedTargetIdx,
);

$esAutoobjetivo = $seleccionado !== null
    && $seleccionado->id() === $actor->id()
    && $movimientoAutoobjetivable;

if ($seleccionado !== null
    && $seleccionado->id() === $actor->id()
    && ! $selfTargeting
    && ! $esAutoobjetivo) {
    $componente->log[] = 'Objetivo no válido.';

    return;
}
```

Enrutado del `defender`:

```php
$esAliado = false;

if ($seleccionado !== null && $this->esAliado($battle, $actor, $seleccionado)) {
    // ... bloque actual de aliado, sin cambios ...
} elseif ($selfTargeting) {
    $defender = $actor;
} elseif ($esAutoobjetivo) {
    $defender = $actor;
} else {
    $defender = $seleccionado;
}
```

`DESAFIO` (corrección importante):

```php
if (! $esAliado && ! $selfTargeting && ! $esAutoobjetivo
    && $desafio !== null && $desafio->objetivoForzadoId !== '') {
    // redirección actual
}
```

Con esto:

- `sand-tomb` sobre el actor: `$esAutoobjetivo = true` → no se redirige.
- `sand-tomb` sobre un rival: `$esAutoobjetivo = false` → `DESAFIO` sigue redirigiendo como hoy.
- Movimiento normal sobre el actor: rechazado con “Objetivo no válido.”.
- Movimiento autodirigido sobre el actor: comportamiento actual.
- PP, silencio, carga y objeto de elección siguen bloqueados por `movimientoDisponible()` (`:111-121`), sin cambios.
- Postura (`:95-104`) se mantiene antes de todo.

### 3.5 `DTOAccionBatalla`

Sin cambios. Se sigue creando con `defenderId = $actor->id()` cuando el autoobjetivo es válido (`GestorSeleccionBatalla.php:179-185`). El DTO ya transporta ambos IDs (`DTOAccionBatalla.php:17-24`).

### 3.6 `GestorAccionBatalla::resolver()` y defensa en profundidad

```php
$esAliado = $objetivo->id() !== $actor->id()
    && $battle->equipoDe($objetivo) === $battle->equipoDe($actor);

$autoobjetivo = $objetivo->id() === $actor->id()
    && ! $movimiento->esAutodirigido()
    && CatalogoEfectosAliado::permiteAutoobjetivo($movimiento->slug);

$efectoAliado = ($esAliado || $autoobjetivo)
    ? CatalogoEfectosAliado::para($movimiento->slug)
    : null;
```

Mantener el bloque actual de aliado inválido (`:192-203`) y añadir, antes de la rama normal, el rechazo de autoataque:

```php
if ($objetivo->id() === $actor->id()
    && ! $movimiento->esAutodirigido()
    && $efectoAliado === null) {
    $actor->romperCombo();
    $componente->log[] = 'Objetivo no válido.';
    $battle->setPendingAction(null);
    $battle->turnManager()->consumeAction($actor);
    $this->gestorAnimacion->reiniciar($componente);
    $this->gestorVista->sincronizar($componente, $battle, $actor);
    $this->session->guardar($componente->battleId, $battle);
    $componente->nextActor();

    return;
}
```

Después, la rama `if ($efectoAliado !== null)` enruta tanto aliado como autoobjetivo a `aplicarMovimientoAliado()`. La rama normal solo recibe movimientos autodirigidos o ataques contra otro combatiente. Esto cubre una `pendingAction` manipulada o una sesión inconsistente: **nunca** se llama a `aplicarMovimiento()` con `defender === attacker` y un movimiento normal.

### 3.7 `aplicarMovimientoAliado()`, eventos, animación y log

`ResolutorAccionBatalla::aplicarMovimientoAliado()` ya funciona con `defender === attacker`:

- `defenderTeam()` resuelve el equipo por identidad (`:254-259`).
- `EjecutorEfectoAliado::ejecutar()` aplica sobre `$accion->defender` sin excluir al atacante (`EjecutorEfectoAliado.php:33-51`).
- `romperCombo()`, `consumeAction()`, `consumirMovimiento()` y `procesarDebilitamientos()` se ejecutan igual (`ResolutorAccionBatalla.php:184,197-200`).
- No se aplican `secondaries` (`partiallytrapped`), porque la ruta de apoyo no pasa por `aplicarMovimiento()`.

Cambio opcional aprobado: el log de `:186` con el condicional “sobre sí mismo” descrito en 1.7. Eventos y animación no requieren cambios.

### 3.8 Validaciones de servidor y estados imposibles

| Caso | Dónde se corta | Resultado |
|---|---|---|
| `defenderId === actorId` con movimiento normal no autodirigido | `selectMove()` y, como defensa en profundidad, `resolver()` | “Objetivo no válido.”; no se crea acción o se descarta la pendiente; sin daño ni estado |
| `previewTarget(0, idxActor)` sin movimiento autoobjetivable | `previewTarget()` | Retorno silencioso; no cambia fase ni objetivo |
| Objetivo muerto | `previewTarget()` (`estaVivo`), `selectMove()` (`:143-149`), `EjecutorEfectoAliado` (`:35-40`) | No se abre selección, se cancela o se registra “ya no puede recibir el efecto” |
| `defenderId` inexistente | `resolver()` (`:158-169`) | Limpia pendiente, reinicia animación y pasa al siguiente actor |
| Actor no autorizado | `jugadorAutorizado()` en `previewTarget()` y `selectMove()` | No hay acción; `selectMove` llama a `nextActor()` como hoy |
| Acción pendiente manipulada con IDs válidos | `resolver()` | La guarda de autoataque la rechaza y consume la acción para evitar bloqueo |
| `DESAFIO` activo + autoobjetivo | `selectMove()` | No se redirige; se mantiene el objetivo propio |
| `sand-tomb` contra rival | `selectMove()` y `resolver()` | Ruta normal de ataque, con daño y `partiallytrapped` como hoy |

Nota: `resolver()` no reautoriza al actor del jugador (comportamiento preexistente). El cambio no lo empeora; la guarda nueva reduce el impacto de una pendiente inconsistente. Se registra como riesgo residual.

---

## 4. Impacto en presentación, objetos e IA

### 4.1 `refsAliado`

**Sin cambios.** Sigue excluyendo al actor (`BatallaVistaPresenter.php:134`) y significando “aliados distintos del actor”. Se puede ampliar el docblock para dejarlo explícito.

### 4.2 `aliadosSeleccionables`

**Sin cambios.** Sigue siendo `refsAliado !== []` (`:104`). En solitario con solo movimientos autoobjetivables seguirá siendo `false`, por lo que el aviso de `items-panel.blade.php:37-41` no aparece.

### 4.3 `items-panel`

**Sin cambios funcionales.** El panel de objetos no usa `refsAliado`; usa `aliadosSeleccionables` y la fase. Además, el flujo de objetos ya permite marcar al actor como objetivo con `ObjetivosItem::ALIADO_VIVO` (`BatallaVistaPresenter.php:167-172`) y `GestorObjetosBatalla::confirmarObjetivoItem()`; no se altera.

### 4.4 `marcarInteraccion`

Añade un parámetro `bool $actorAutoobjetivoSeleccionable = false` y una condición extra solo para `teamIdx === 0` y `refId === actingRefId`. No toca la rama de objetos (`$faseItem`), ni la de rivales (`teamIdx === 1`), ni `blocked`, ni `targeted`, ni `acting`.

### 4.5 `SincronizadorVistaBatalla` y `batallaVista`

**Sin cambios.** El sincronizador proyecta equipos, log y objetos (`SincronizadorVistaBatalla.php:23-61`); no decide selección. `batallaVista` no necesita una clave nueva: el flag es interno entre `PresentadorVistaCombate` y `BatallaVistaPresenter`.

### 4.6 IA

**Sin cambios.** No se toca `actorAdmiteSoporte()`, `SelectorAccionIA`, `EvaluadorSoporteIA` ni `GestorAccionBatalla::prepararAnimacionIa()`. La IA seguirá sin autoobjetivarse.

---

## 5. Archivos y orden por especialista

### Backend

| Orden | Archivo | Cambio |
|---|---|---|
| 1 | `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | `AUTOOBJETIVO` + `permiteAutoobjetivo(string): bool` |
| 2 | `app/Livewire/Presenters/MovesPreviewPresenter.php` | Rama de autoobjetivo en `previewDe()`, `apoyoEtiqueta`, motivo “No usable sobre sí mismo” |
| 3 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Parámetro `actorAutoobjetivoSeleccionable`, paso a `marcarInteraccion()` y condición de carta del actor |
| 4 | `app/Livewire/Presenters/PresentadorVistaCombate.php` | `actorAdmiteAutoobjetivo()` desde `currentMoves` y paso del flag |
| 5 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Guarda en `previewTarget()`, `actorAdmiteAutoobjetivo()`, `selectMove()` con `$esAutoobjetivo` y `DESAFIO` corregido |
| 6 | `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | `$autoobjetivo`, enrutado a `aplicarMovimientoAliado()` y rechazo de autoataque |
| 7 (opcional, requiere aprobación) | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | Log “sobre sí mismo” en `aplicarMovimientoAliado()` |

Dependencias: 1 es base de 2-6. 3 y 4 deben coordinarse (misma firma). 5 y 6 comparten el predicado y la semántica `$esAutoobjetivo`. 7 es independiente y opcional.

### Frontend

| Orden | Archivo | Cambio |
|---|---|---|
| 1 | `resources/views/livewire/partials/moves-panel.blade.php` | Sustituir el texto fijo “SOBRE ALIADO” por `{{ $move['apoyoEtiqueta'] ?? 'SOBRE ALIADO' }}` |

No requiere `npm run build`: Blade se compila en servidor. Si el entorno usa vistas compiladas en producción, bastará `php artisan view:clear`/`view:cache` según despliegue.

### Sin cambios

`_pokemon-card.blade.php`, `items-panel.blade.php` (solo verificación), `EstadoAnimacionBatalla.php`, `EjecutorEfectoAliado.php`, `EfectoAliado`, `TipoEfectoAliado`, `MovimientoBatalla.php`, `AgregadoBatalla.php`, `SelectorAccionIA.php`, `EvaluadorSoporteIA.php`, `DTOAccionBatalla.php`, `BattleSessionService.php`, `Combate.php`, `storage/data/moves.json`. No hay migraciones ni dependencias nuevas.

---

## 6. Criterios de aceptación y verificación

### Criterios de aceptación

- **CA1:** En `player_target`, la carta del actor vivo es seleccionable si y solo si tiene al menos uno de `sand-tomb`, `fire-spin`, `whirlpool`; no lo es si solo tiene `recover`, `swords-dance`, etc.
- **CA2:** Al seleccionar al actor se abre `player_move`; los tres movimientos muestran `modoAliado = true`, `apoyoEtiqueta = 'SOBRE SÍ MISMO'`, `daño = 0` y `efectoAliado` correcto. Los movimientos normales muestran `disponible = false`, `motivoTexto = 'No usable sobre sí mismo'` y no muestran fila de daño ni efectividad.
- **CA3:** Confirmar `sand-tomb` sobre el actor incrementa su DEF actual hasta el máximo, sin daño, sin `partiallytrapped`, consumiendo PP y turno, con evento `effect`.
- **CA4:** Confirmar `fire-spin` sobre el actor incrementa su DEF.ESP actual hasta el máximo, sin daño ni `partiallytrapped`, con el mismo consumo.
- **CA5:** Confirmar `whirlpool` sobre el actor aplica o refresca `AQUA_RING` 5 turnos, con curación por ronda del 5 % del HP máximo del lanzador; sin daño, consumiendo PP y turno.
- **CA6:** Un movimiento normal no autodirigido y no catalogado con `defenderId === actorId` se rechaza en `selectMove()` con “Objetivo no válido.” y no crea `pendingAction`.
- **CA7:** Una `pendingAction` manipulada o antigua con `defenderId === actorId` y movimiento normal se rechaza en `resolver()`, sin daño ni estado, consumiendo la acción y pasando al siguiente actor.
- **CA8:** Con `DESAFIO` activo, un autoobjetivo de catálogo no se redirige; el mismo movimiento dirigido a un rival sí se redirige como hoy.
- **CA9:** El soporte sobre otro aliado (incluida retaguardia, TASK-033) no cambia.
- **CA10:** `sand-tomb`, `fire-spin` y `whirlpool` contra rivales siguen aplicando daño y `partiallytrapped` como ataques normales.
- **CA11:** El panel de objetos no cambia: `aliadosSeleccionables` sigue siendo falso en solitario y el aviso solo aparece con aliados.
- **CA12:** La IA sigue sin generar autoobjetivos.
- **CA13:** No cambia `SESSION_VERSION`; una sesión anterior carga y una pendiente antigua inconsistente se rechaza sin daño.
- **CA14:** Posturas (`Cubrir`/`Guardia`), silencio, PP, carga y objeto de elección siguen bloqueando igual.
- **CA15:** La etiqueta es “SOBRE ALIADO” para aliado y “SOBRE SÍ MISMO” para autoobjetivo.

### Verificación estática

- `php -l` sobre los archivos PHP modificados.
- `composer analyse` (`vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M`).
- `composer analyse:hard` (`phpstan-hardener.neon`).
- `composer arch` (`vendor/bin/deptrac analyse`). El cambio no añade dependencias nuevas; `Presentation -> Domain` ya está permitido (`deptrac.yaml:42-44`).
- `vendor/bin/pint --dirty --format agent` sobre los archivos tocados.
- Revisión manual de que `src/Battle` no importa `App\Livewire`, ni Eloquent/Blade.

### Verificación manual (sin tests)

1. Pokémon del jugador con `sand-tomb`: su carta aparece con `cursor-pointer` y `wire:click` en `player_target`.
2. Pulsarla: el panel muestra Bucle Arena como apoyo (escudo), sin daño; los ataques normales aparecen deshabilitados con “No usable sobre sí mismo”.
3. Confirmar y comprobar log, DEF, PP y ausencia de daño/`partiallytrapped`.
4. Repetir con `fire-spin` (DEF.ESP) y `whirlpool` (Acua Aro, 5 turnos, curación por ronda, refresco al reutilizar).
5. Manipular `selectedTargetTeam=0` y `selectedTargetIdx` del actor y llamar `selectMove()` con un ataque normal: “Objetivo no válido.” sin daño.
6. Simular una `pendingAction` con `defenderId === actorId` y ataque normal: el resolver la rechaza.
7. Aplicar `DESAFIO` y comprobar que el autoobjetivo no se redirige; comprobar que `sand-tomb` contra el rival sí.
8. Usar `sand-tomb` sobre un compañero vivo y sobre retaguardia: sin cambios.
9. Turno de un rival con `sand-tomb`: sigue atacando o apoyando como antes, sin autoobjetivo.
10. Cargar una batalla anterior al cambio y continuar.
11. Con `Cubrir`/`Guardia`, comprobar que el movimiento no se puede seleccionar.
12. Sin PP y con objeto de elección: comprobar los bloqueos actuales.

---

## 7. Riesgos residuales y preguntas para el desarrollador

### Riesgos residuales

| Severidad | Riesgo | Mitigación |
|---|---|---|
| Medio | La lista `AUTOOBJETIVO` puede divergir del catálogo si se añade un movimiento con `target normal` | Docblock, revisión y prueba manual del catálogo; el alcance queda congelado a propósito |
| Medio | El cliente puede manipular `currentMoves` y hacer que la carta del actor parezca seleccionable | Solo afecta a presentación; `previewTarget`, `selectMove` y `resolver` revalidan con el estado del servidor |
| Bajo | Log “usa X sobre X” si no se aprueba el cambio de `ResolutorAccionBatalla` | Cosmético; se recomienda el condicional “sobre sí mismo” |
| Bajo | El banner de animación no se muestra en autoobjetivo | Igual que con movimientos autodirigidos; aceptado |
| Bajo | `AQUA_RING` refresca y no acumula; el escudo al máximo consume PP/turno | Paridad con aliados; documentado |
| Bajo | `resolver()` no reautoriza al actor del jugador (preexistente) | Fuera de alcance; la guarda nueva evita el autoataque |
| Bajo | `fire-spin`/`whirlpool`/`sand-tomb` tienen `secondaries: partiallytrapped` | La ruta de apoyo no aplica secundarios; el rechazo de autoataque cubre un enrutado erróneo |

### Decisiones que requieren aprobación explícita

1. **Alcance:** exactamente `sand-tomb`, `fire-spin`, `whirlpool`.
2. **`DESAFIO`:** permitir el autoobjetivo sin redirección.
3. **Etiqueta:** “SOBRE SÍ MISMO”, con el cambio de una línea en `moves-panel.blade.php`.
4. **PP/turno:** permitir el gasto aunque el escudo esté al máximo o `AQUA_RING` ya esté activo.
5. **Presentación:** opción B.2.b (flag `actorAutoobjetivoSeleccionable`), no B.2.a.
6. **Log:** cambiar a “sobre sí mismo” en `ResolutorAccionBatalla::aplicarMovimientoAliado()`.
7. **IA:** mantenerla intacta, sin autoobjetivo.

---

## Borrador de ADR

- **Título:** Autoobjetivo de los movimientos de apoyo del catálogo con `target normal`.
- **Estado:** Propuesto.
- **Contexto:** `sand-tomb`, `fire-spin` y `whirlpool` están en `CatalogoEfectosAliado` pero declaran `target: normal`. El dominio de efectos ya soporta `defender === attacker`; el bloqueo está en presentación (`refsAliado`), selección (`selectMove`) y resolución (`GestorAccionBatalla`). Relajar solo la presentación provocaría autoataque con daño y `partiallytrapped`.
- **Decisión:** añadir un predicado único en el catálogo; marcar la carta del actor con un flag de presentación separado; permitir el autoobjetivo solo con el predicado; excluir `DESAFIO` solo cuando el objetivo es el actor; enrutar a `aplicarMovimientoAliado()` y rechazar en servidor cualquier autoataque no autodirigido/no catalogado. Sin cambios en IA, DTO, `SESSION_VERSION`, migraciones ni `Combate`.
- **Consecuencias:** se obtiene el efecto defensivo/Aqua Ring sobre uno mismo con paridad respecto a aliados; el alcance queda explícito y congelado; el log y la etiqueta mejoran; se añade una lista que debe mantenerse si el catálogo crece.
