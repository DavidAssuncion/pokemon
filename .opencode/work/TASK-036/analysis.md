# TASK-036 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e409a650dffeL1JL6G1aFGiB5i`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# TASK-036 — Análisis de autoobjetivo de Bucle Arena y derivados

> Análisis de solo lectura. No se modificó ningún archivo. Rama verificada: `task-011`.

## 1. Resumen ejecutivo

**Causa raíz:** el autoobjetivo está bloqueado en tres capas distintas y, si se relajara solo la presentación, el servidor ejecutaría un **ataque normal contra el propio actor**:

1. La carta del actor no es seleccionable porque `BatallaVistaPresenter::refsAliado()` excluye explícitamente `$refId === $actingRefId`.
2. `MovesPreviewPresenter::previewDe()` tampoco considera aliado al propio actor, por lo que mostraría daño en lugar del efecto de apoyo.
3. `GestorSeleccionBatalla::selectMove()` rechaza el autoobjetivo salvo `esAutodirigido()`; y `GestorAccionBatalla::resolver()` solo enruta a `aplicarMovimientoAliado()` cuando el objetivo es **otro** combatiente del mismo equipo. Si `defenderId === actorId`, cae en `aplicarMovimiento()` y se autoinflige daño/estado.

**El ejecutor de efectos aliados sí funciona sobre el propio actor.** `ResolutorAccionBatalla::aplicarMovimientoAliado()` y `EjecutorEfectoAliado` no excluyen `defender === attacker`; los escudos y `AQUA_RING` se aplican correctamente. No hace falta cambiar el dominio de efectos.

**Alcance real de “Bucle Arena y derivados”:** solo `sand-tomb` (Bucle Arena), `fire-spin` (Giro Fuego) y `whirlpool` (Torbellino) son los únicos movimientos del catálogo aliado con `target: normal`. Los otros 43 del catálogo tienen `target: self`. Corrección al contexto recibido: `protect` **no** está en `CatalogoEfectosAliado`; es autodirigido por su `target: self`.

**Recomendación:** cambio mínimo en 5 archivos PHP, con un predicado en el catálogo y **validación en servidor en `selectMove` y `GestorAccionBatalla::resolver`**. Sin cambios de `SESSION_VERSION`, DTO, serialización ni IA.

---

## 2. Alcance de “Bucle Arena y derivados” (catálogo y `target`)

### 2.1 Movimientos del catálogo con `target: normal` (los únicos “derivados” reales)

| Slug | Nombre | `target` | Categoría | Potencia | Efecto en `CatalogoEfectosAliado` |
|---|---|---|---|---|---|
| `sand-tomb` | Bucle Arena | `normal` | físico | 35 | `ESCUDO_FISICO` |
| `fire-spin` | Giro Fuego | `normal` | especial | 35 | `ESCUDO_ESPECIAL` |
| `whirlpool` | Torbellino | `normal` | especial | 35 | `AQUA_RING` (5 turnos) |

Evidencia: `storage/data/moves.json` (entradas `sand-tomb`, `fire-spin`, `whirlpool`: `target: "normal"`, `base_power: 35`) y `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php:22-24`.

### 2.2 Resto del catálogo: todos con `target: self`

Agrupados por efecto declarado en `CatalogoEfectosAliado.php:21-69`; **todos** tienen `target: self` en `moves.json`:

- **`CURAR_PS`:** `recover`, `roost`, `morning-sun`, `synthesis`, `moonlight`, `soft-boiled`, `milk-drink`, `slack-off`, `heal-order`, `shore-up` — `self`.
- **`BUFF_STATS`:** `nasty-plot`, `paz_mental`, `swords-dance`, `dragon-dance`, `bulk-up`, `agility`, `rock-polish`, `autotomize`, `harden`, `withdraw`, `rizo_defensa`, `barrier`, `acid-armor`, `iron-defense`, `shelter`, `cotton-guard`, `amnesia`, `charge`, `tail-glow`, `cosmic-power`, `defend-order`, `magnetic-flux`, `sharpen`, `hone-claws`, `work-up`, `coil`, `quiver-dance`, `shift-gear`, `geomancy`, `victory-dance` — `self`.
- **`CURAR_ESTADO`:** `refresh` — `self`.
- **`CRITICO_MEJORADO`:** `focus-energy`, `laser-focus` — `self`.

Total: 3 con `normal` + 43 con `self` = 46 entradas en el catálogo.

### 2.3 Movimientos con objetivo aliado fuera del catálogo

Existen 21 movimientos en `moves.json` con `target` de aliado que **no** están en `CatalogoEfectosAliado` y, por tanto, quedan fuera de este análisis:

- `allySide` (6): `pantalla_de_luz`, `reflejo`, `tailwind`, `mist`, `safeguard`, `lucky-chant`.
- `allies` (7): `howl`, `gear-up`, `life-dew`, `coaching`, `jungle-healing`, `lunar-blessing`, `dragon-cheer`.
- `adjacentAlly` (4): `heal-pulse`, `aromatic-mist`, `floral-healing`, `purify`.
- `allyTeam` (3): `bendicion_revivir`, `vinculo_dano`, `revival-blessing`.
- `adjacentAllyOrSelf` (1): `acupressure`.

Ninguno tiene efecto declarado en el catálogo, así que hoy tampoco pueden usarse sobre un aliado: `selectMove()` los rechaza si el objetivo es aliado (`GestorSeleccionBatalla.php:151-155`). No son “derivados” de Bucle Arena.

**Conclusión del alcance:** el cambio solicitado debe cubrir exactamente los tres movimientos con `target: normal` del catálogo. Los movimientos con `self` ya son autodirigidos y funcionan por la ruta existente; el autoobjetivo sobre ellos es una mejora de UX opcional, no un requisito para “obtener el beneficio”.

---

## 3. Causa raíz por capa, con evidencia

### Capa 0 — Datos y contrato del movimiento

- `MovimientoBatalla::esAutodirigido()` solo devuelve `true` para `ObjetivoMovimiento::SELF`, `ALLY_SIDE`, `ALLIES`, `ALLY_TEAM`, o para movimientos de estado con `selfStatChanges` y sin `targetStatChanges`: `src/Battle/Domain/MovimientoBatalla.php:206-220`.
- `sand-tomb`, `fire-spin` y `whirlpool` son `target: normal`, categoría físico/especial y sin `selfStatChanges`; por tanto **no son autodirigidos**.
- El catálogo es el único predicado de apoyo aliado: `CatalogoEfectosAliado.php:10-16` y `:19-69`.

### Capa 1 — Tarjeta no seleccionable

- `BatallaVistaPresenter::refsAliado()` exige `actorAdmiteAliado`, fase `player_target`/`player_move` y descarta explícitamente al actor:

  ```php
  || $refId === $actingRefId) {
      continue;
  }
  ```

  `app/Livewire/Presenters/BatallaVistaPresenter.php:122-139`, exclusión en `:134`.

- En `marcarInteraccion()`, la carta del equipo del jugador solo es seleccionable si su `refId` está en `refsAliado`:

  ```php
  || ($teamIdx === 0
      && in_array((string) ($p['refId'] ?? ''), $refsAliado, true))
  ```

  `BatallaVistaPresenter.php:174-184`.

- La carta solo emite `wire:click` si `selectable` es verdadero:

  ```blade
  @if($p['selectable']) wire:click="previewTarget({{ $p['team'] }}, {{ $idx }})" ...
  ```

  `resources/views/livewire/_pokemon-card.blade.php:4`.

- `PresentadorVistaCombate` calcula `actorAdmiteAliado` a partir de `currentMoves`, que al inicio del turno del jugador se rellena con todos sus movimientos: `app/Livewire/Presenters/PresentadorVistaCombate.php:21,53-62` y `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:151-156`. Es decir, el dato existe, pero no se usa para incluir al actor.

### Capa 2 — Previsualización de daño

- `MovesPreviewPresenter::previewDe()` define `$esAliado` excluyendo al actor:

  ```php
  $esAliado = $target->id() !== $actor->id()
      && $battle->equipoDe($target) === $battle->equipoDe($actor);
  ```

  `app/Livewire/Presenters/MovesPreviewPresenter.php:127-128`.

- Si `target === actor`, no entra en `previewAliado()`; sigue la ruta normal y calcula daño con `$battle->damageChain()->calculate($action)` (`:130-152`). Para `sand-tomb` mostraría 35 de potencia contra uno mismo, no el escudo.
- `previewAliado()` y `previewAliadoBase()` sí saben construir el efecto sin daño (`:230-316`), incluido `textoEfectoAliado()` (`:318-360`). El problema es solo la condición de entrada.

### Capa 3 — Selección del movimiento: rechazo explícito del autoobjetivo

- `GestorSeleccionBatalla::selectMove()` calcula `$selfTargeting = $move->esAutodirigido()` y luego:

  ```php
  if ($seleccionado !== null && $seleccionado->id() === $actor->id() && ! $selfTargeting) {
      $componente->log[] = 'Objetivo no válido.';
      return;
  }
  ```

  `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:123-135`.

  Para `sand-tomb` `$selfTargeting` es `false`, por lo que el autoobjetivo se rechaza aquí.

- Aunque se eliminara ese bloque, `$esAliado` se calcula con un helper que **excluye al actor**:

  ```php
  private function esAliado(...): bool
  {
      return $objetivo->id() !== $actor->id()
          && $battle->equipoDe($objetivo) === $battle->equipoDe($actor);
  }
  ```

  `GestorSeleccionBatalla.php:273-277`.

  Por tanto, `$esAliado` sería `false`, no se usaría `CatalogoEfectosAliado::para()` y `$defender` acabaría siendo el actor (`:157-162`).

- Además, con `DESAFIO` activo, el autoobjetivo se sobrescribiría por el objetivo forzado porque la condición solo excluye `$esAliado` y `$selfTargeting`:

  ```php
  if (! $esAliado && ! $selfTargeting && $desafio !== null && ...) {
  ```

  `GestorSeleccionBatalla.php:164-169`.

- Finalmente se crea la acción pendiente con `defenderId = $actor->id()` (`:179-185`) y la animación (`:188`).

### Capa 4 — Ejecución: el autoobjetivo cae en ataque normal

- `GestorAccionBatalla::resolver()`:

  ```php
  $esAliado = $objetivo->id() !== $actor->id()
      && $battle->equipoDe($objetivo) === $battle->equipoDe($actor);
  $efectoAliado = $esAliado ? CatalogoEfectosAliado::para($movimiento->slug) : null;
  ```

  `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:186-188`.

  Con `defenderId === actorId`, `$esAliado` es `false` y `$efectoAliado` es `null`.

- El bloque de defensa para aliados no se activa (`:192-203`), y se entra en la rama normal (`:216-238`). Si el movimiento no es autodirigido, no se reasigna `$objetivo`, y se llama a `aplicarMovimiento()` con `attacker === defender` (`:226-237`).

- `ResolutorImpacto` no bloquea el autoataque: solo evita disparar `dispararSerObjetivo()` cuando `defender === attacker`:

  ```php
  if ($battle !== null && $accion->defender !== $accion->attacker) {
      $accion->defender->dispararSerObjetivo($accion, $battle);
  }
  ```

  `src/Battle/Domain/Ejecucion/ResolutorImpacto.php:63`. El daño se sigue calculando y aplicando.

- `ResolutorObjetivosDanio::resolver()` devuelve `null` para movimientos `normal` que no son de estado (`src/Battle/Domain/Danio/ResolutorObjetivosDanio.php:20-89`), por lo que se ejecuta la ruta individual contra el propio actor.

- `ResolutorAccionBatalla::aplicarMovimiento()` aplica después los estados secundarios del movimiento (`:94-98`). `sand-tomb`, `fire-spin` y `whirlpool` tienen `secondaries: partiallytrapped`; el autoataque podría autoatrapar al actor, además del daño.

### Capa 5 — Efectos aliados: no excluyen al actor

- `ResolutorAccionBatalla::aplicarMovimientoAliado()` no comprueba `aliado !== actor`; recibe el `Combatiente $aliado` y ejecuta `EjecutorEfectoAliado` (`app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:155-203`).
- `defenderTeam()` busca el equipo por identidad, así que con `defender === actor` devuelve correctamente el equipo del jugador (`:254-259`).
- `EjecutorEfectoAliado::ejecutar()` aplica siempre sobre `$accion->defender` (`src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php:33-51`), sin exclusión del atacante.

### Capa 6 — IA: no se ve afectada

- `AgregadoBatalla::actorAdmiteSoporte()` exige aliados vivos **distintos del actor**:

  ```php
  $aliados = $equipo->combatientesCollection()->vivos()->filter(
      fn (Combatiente $combatiente) => $combatiente->id() !== $actor->id()
  );
  ```

  `src/Battle/Domain/AgregadoBatalla.php:427-461`, exclusión en `:434-436`.

- `SelectorAccionIA::construirContexto()` también excluye al actor de `aliados` (`src/Battle/Domain/AI/SelectorAccionIA.php:178-181`).
- `agregarAccionesSoporte()` vuelve a excluirlo explícitamente:

  ```php
  if ($aliado->id() === $actor->id() || ! $aliado->estaVivo()) {
      continue;
  }
  ```

  `SelectorAccionIA.php:319-322`.

Por tanto, si el cambio se limita a la ruta del jugador, la IA seguirá sin considerar autoobjetivo. No hay que tocar `SelectorAccionIA`, `EvaluadorSoporteIA` ni `actorAdmiteSoporte`.

### Capa 7 — Sesión y contrato

- `DTOAccionBatalla` ya transporta `actorId` y `defenderId`; no necesita un campo nuevo para este cambio: `src/Battle/Presentation/DTOAccionBatalla.php:16-27`.
- La acción pendiente vive en el agregado (`AgregadoBatalla::pendingAction()`/`setPendingAction()`: `src/Battle/Domain/AgregadoBatalla.php:117-125`) y se serializa con la sesión.
- `SESSION_VERSION = 21`, mínimo compatible `9` (`app/Support/BattleSessionService.php:50,64`). Si no se añaden propiedades serializadas, **no hace falta subir versión**.
- Las sesiones antiguas con `slug` desconocido o movimientos sin catálogo siguen devolviendo `null` en el catálogo y quedan intactas (`CatalogoEfectosAliado.php:10-16`).

---

## 4. ¿Funciona el efecto sobre uno mismo?

**Sí, el efecto de dominio funciona sin cambios.** Detalle por efecto:

- **`ESCUDO_FISICO` (`sand-tomb`)**: `EjecutorEfectoAliado::aplicarEscudo()` usa `$aliado = $accion->defender` y `$lanzador = $accion->attacker`; con ambos iguales calcula el máximo de DEF del propio Pokémon y el stat de DEF del lanzador, aplica `min(max, actual + incremento)` y registra el log (`EjecutorEfectoAliado.php:53-86`). Funciona igual que sobre un aliado.
- **`ESCUDO_ESPECIAL` (`fire-spin`)**: misma ruta con `especial = true`; usa `spDefenseHp` y `DEFENSA_ESPECIAL` (`:58-75`). Funciona.
- **`AQUA_RING` (`whirlpool`)**: calcula `aporte = lanzador->battleStats()->hp * 0.05` y llama a `$aliado->reemplazarCondicion(new CondicionVolatil(AQUA_RING, turnos: 5, origenId: $lanzador->id(), curacionFija: $aporte))` (`:109-136`). Con `aliado === lanzador`, el origen es el propio actor y la condición queda en él. `reemplazarCondicion()` quita la existente y añade la nueva (`src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:68-72`), por lo que **refresca** los 5 turnos, no acumula.
- **Procesamiento por ronda de `AQUA_RING`**: `ProcesadorCondicionesRonda` cura al portador con `curacionFija` si es mayor que 0 (`src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php:144-151`) y decrementa/quita la condición (`:187-193`). Funciona sobre el propio actor.

**Puntos que sí requieren atención, pero no impiden el efecto:**

1. **Log cosmético:** `ResolutorAccionBatalla::aplicarMovimientoAliado()` registra `"{$actor->nombre()} usa {$movimiento->nombre} sobre {$aliado->nombre()}"` (`:186`). Con autoobjetivo se leería “Pikachu usa Bucle Arena sobre Pikachu”. No rompe nada, pero conviene un texto “sobre sí mismo” o simplemente “usa Bucle Arena”.
2. **Animación:** `EstadoAnimacionBatalla::crear()` considera autodirigido cuando `$objetivo->id() === $actor->id()` y deja `defenderId` y `defenderNombre` vacíos (`app/Livewire/Combate/Soporte/EstadoAnimacionBatalla.php:43-56`). `BatallaVistaPresenter::battleField()` exige ambos nombres no vacíos para mostrar el banner (`BatallaVistaPresenter.php:346-351`). El resultado es el mismo que ya ocurre con Danza Espada: se muestra el spinner del panel de movimientos, pero no el banner “atacante → movimiento → defensor”. No es un fallo funcional.
3. **Eventos:** el evento `effect` se acumula con `actorId` y `objetivoId` iguales (`ResolutorAccionBatalla.php:190-195`). El grabador compara snapshots; no hay ninguna condición que exija IDs distintos. No se rompe.
4. **`defenderTeam`:** con `defender === actor`, `equipoDe()` devuelve el equipo correcto por identidad (`AgregadoBatalla.php:289-300`; `ResolutorAccionBatalla.php:254-259`). No se rompe.

**Conclusión:** no hay que modificar `ResolutorAccionBatalla::aplicarMovimientoAliado()`, `EjecutorEfectoAliado`, `EfectoAliado` ni `TipoEfectoAliado` para que el efecto funcione sobre el propio actor. El trabajo está en **enrutar** el autoobjetivo a esa ruta y en **impedir** que un movimiento normal llegue a `aplicarMovimiento()` con `defender === attacker`.

---

## 5. Opciones de diseño y recomendación

### Opción A — Solo presentación (no segura)

Incluir al actor en `refsAliado()` y eliminar el rechazo de `selectMove()`.

**Problema:** el autoobjetivo de `sand-tomb` seguiría cayendo en `aplicarMovimiento()` porque `$esAliado` excluye al actor (`GestorAccionBatalla.php:186-188`). Se autoinfligiría daño y se aplicaría `partiallytrapped` al propio actor. **Descartada.**

### Opción B — Predicado compartido en el catálogo + validación en aplicación (recomendada)

**B.1. Dominio: un único predicado**

Añadir en `CatalogoEfectosAliado` un método que distinga el autoobjetivo permitido, por ejemplo:

```php
public static function permiteAutoobjetivo(string $slug): bool
{
    return self::para($slug) !== null;
}
```

Si se quiere limitar estrictamente a los tres “derivados” con `target: normal`, usar una lista explícita:

```php
private const AUTOOBJETIVO = ['sand-tomb', 'fire-spin', 'whirlpool'];

public static function permiteAutoobjetivo(string $slug): bool
{
    return in_array($slug, self::AUTOOBJETIVO, true);
}
```

**Trade-off:** la lista explícita es más segura y expresa el alcance aprobado, pero exige mantenerla si se añaden futuros movimientos de apoyo con `target: normal`. La variante `self::para($slug) !== null` es más general, pero incluye los 43 movimientos `self` que ya funcionan por la ruta autodirigida. Para el requisito literal (“Bucle Arena y derivados”), recomiendo la lista explícita de los tres slugs o, como mucho, la condición “catálogo + no autodirigido”.

**B.2. Presentación: hacer seleccionable al actor**

Dos alternativas:

- **B.2.a Mínima:** eliminar la exclusión `$refId === $actingRefId` en `BatallaVistaPresenter::refsAliado()` (`:134`). El actor aparecería en `refsAliado` cuando `actorAdmiteAliado` sea verdadero (es decir, cuando tenga cualquier movimiento del catálogo, incluidos curas/buffs). `aliadosSeleccionables` pasaría a `true` también en solitario; hay que revisar el uso en `resources/views/livewire/partials/items-panel.blade.php:37` por si muestra un aviso de objeto sobre aliado sin aliados.
- **B.2.b Más limpia:** mantener `refsAliado` como “aliados distintos del actor” y añadir un flag `actorAutoobjetivoSeleccionable` calculado desde `currentMoves`, usado en `marcarInteraccion()` para marcar la carta del actor como `selectable`. Evita cambiar el significado de `aliadosSeleccionables` y el panel de objetos. Requiere un parámetro adicional en `BatallaVistaPresenter::toView()` y en `PresentadorVistaCombate`.

Recomiendo **B.2.b** si el especialista de frontend puede asumir el parámetro extra; en caso de mínimo absoluto, **B.2.a** es funcionalmente aceptable.

**B.3. `GestorSeleccionBatalla::previewTarget()`**

Añadir una guarda de servidor: si `target->id() === actor->id()` y no existe ningún movimiento autoobjetivable en el actor, retornar. Así una llamada Livewire manipulada a `previewTarget(0, idxActor)` no abre una selección inválida. La comprobación puede reutilizar `CatalogoEfectosAliado::permiteAutoobjetivo()` recorriendo `$actor->pokemon()->moves()`.

**B.4. `GestorSeleccionBatalla::selectMove()`**

Sustituir el rechazo binario por una decisión de tres vías:

```php
$selfTargeting = $move->esAutodirigido();
$autoobjetivoCatalogo = ! $selfTargeting
    && CatalogoEfectosAliado::permiteAutoobjetivo($move->slug);

if ($seleccionado !== null && $seleccionado->id() === $actor->id()
    && ! $selfTargeting && ! $autoobjetivoCatalogo) {
    $componente->log[] = 'Objetivo no válido.';
    return;
}
```

Después:

- Si `$seleccionado` es el actor y `$autoobjetivoCatalogo`, fijar `$defender = $actor` y **no** entrar en la rama de aliado (para no confundir con `$esAliado`).
- Si `$seleccionado` es el actor y `$selfTargeting`, mantener el comportamiento actual (`$defender = $actor`).
- Excluir el autoobjetivo de la sobrescritura por `DESAFIO`:

  ```php
  if (! $esAliado && ! $selfTargeting && ! $autoobjetivoCatalogo && $desafio !== null ...)
  ```

  `GestorSeleccionBatalla.php:164-169`.

- Mantener la comprobación de `movimientoDisponible()` (`:111-121`) para PP, silencio, carga y objeto de elección. Los tres movimientos son físico/especial, por lo que `silencio` no los bloquea; el comportamiento actual se conserva.

**B.5. `GestorAccionBatalla::resolver()`: enrutado y defensa en profundidad**

Calcular:

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

Y añadir el rechazo de autoataque antes de la rama normal:

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

Así, un `pendingAction` manipulado o una sesión inconsistente con `defenderId === actorId` y un movimiento normal **nunca** llegan a `aplicarMovimiento()`. Si el movimiento es autodirigido (Danza Espada, Recuperación, etc.), se mantiene la ruta actual. Si es del catálogo (`sand-tomb`…), se enruta a `aplicarMovimientoAliado()`.

**B.6. `MovesPreviewPresenter::previewDe()`**

Mantener `$esAliado` como “otro combatiente del mismo equipo” y añadir el caso de autoobjetivo:

```php
$sobreSiMismo = $target->id() === $actor->id();
$esAliado = ! $sobreSiMismo
    && $battle->equipoDe($target) === $battle->equipoDe($actor);

if ($esAliado) { ... } // sin cambios

if ($sobreSiMismo && ! $move->esAutodirigido()
    && CatalogoEfectosAliado::para($move->slug) !== null) {
    return self::previewAliado($actor, $target, $move, $efecto);
}

if ($sobreSiMismo && ! $move->esAutodirigido()) {
    return self::previewAliadoNoUsable($actor, $move);
}

// ruta normal existente
```

Importante: para movimientos autodirigidos (`recover`, `swords-dance`, etc.) se conserva la previsualización normal actual, para no alterar la UX existente. Para un movimiento normal no catalogado seleccionado sobre uno mismo, se muestra “No usable sobre sí mismo” y **nunca** daño. El `previewAliado()` existente ya calcula el texto con el propio `target`, que en este caso es el actor (`MovesPreviewPresenter.php:230-360`).

**B.7. Etiqueta de la vista**

`moves-panel.blade.php:58-62` muestra el badge “SOBRE ALIADO” cuando `modoAliado` es verdadero. Para autoobjetivo podría mostrarse “SOBRE SÍ MISMO”. Es un cambio cosmético opcional; si no se quiere tocar Blade, “SOBRE ALIADO” sigue siendo comprensible. Recomiendo un campo `modoSobreSi` o reutilizar `modoAliado` con texto distinto, pero no es imprescindible para el requisito.

### Opción C — Marcar explícitamente el modo en `DTOAccionBatalla`

Añadir un campo `modo: 'ataque'|'apoyo_aliado'|'apoyo_propio'` a `DTOAccionBatalla` y serializarlo.

**Ventaja:** intención explícita; el resolver no infiere el modo de `defenderId === actorId`.

**Coste:** cambia el contrato Livewire/serialización, obliga a revisar `SESSION_VERSION`, compatibilidad con acciones pendientes antiguas y todos los constructores de `DTOAccionBatalla`. No es necesario para este caso. **Descartada** salvo que se quiera una política de acciones más formal a medio plazo.

### Recomendación

**Opción B**, con:

- lista explícita de los tres slugs en `CatalogoEfectosAliado::permiteAutoobjetivo()` o, si se prefiere generalizar, “catálogo + no autodirigido”;
- flag de presentación para el actor (B.2.b) o inclusión mínima en `refsAliado` (B.2.a);
- guardas de servidor en `previewTarget`, `selectMove` y `resolver`;
- exclusión de `DESAFIO` para autoobjetivo;
- sin cambios en `ResolutorAccionBatalla`, `EjecutorEfectoAliado`, `EfectoAliado`, `TipoEfectoAliado`, IA, DTO ni `SESSION_VERSION`;
- texto de log “sobre sí mismo” como mejora cosmética.

**Decisiones que requieren aprobación:** ver sección 8.

---

## 6. Archivos afectados y orden por especialista

### Backend

| Orden | Archivo | Cambio |
|---|---|---|
| 1 | `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | Añadir `permiteAutoobjetivo(string $slug): bool` (lista explícita de `sand-tomb`, `fire-spin`, `whirlpool` o catálogo + no autodirigido). Único predicado de dominio. |
| 2 | `app/Livewire/Presenters/MovesPreviewPresenter.php` | Tratar `target === actor` con movimiento de catálogo no autodirigido como `previewAliado()`; bloquear daño para movimientos no usables sobre uno mismo. Mantener la ruta normal para autodirigidos. |
| 3 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Hacer seleccionable la carta del actor: incluir `$actingRefId` en `refsAliado` cuando corresponda, o añadir `actorAutoobjetivoSeleccionable` y usarlo en `marcarInteraccion()`. Actualizar docblock de `refsAliado` si cambia su significado. |
| 4 | `app/Livewire/Presenters/PresentadorVistaCombate.php` | Solo si se elige B.2.b: calcular el flag de autoobjetivo desde `currentMoves` y pasarlo a `BatallaVistaPresenter::toView()`. Si se elige B.2.a, no se toca. |
| 5 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | `previewTarget()`: guarda de autoobjetivo. `selectMove()`: permitir autoobjetivo solo con catálogo; no sobrescribir por `DESAFIO`; mantener PP/silencio/postura. |
| 6 | `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | `resolver()`: calcular `$autoobjetivo`, enrutar a `aplicarMovimientoAliado()` y rechazar autoataque normal con `defenderId === actorId`. |

### Frontend

| Orden | Archivo | Cambio |
|---|---|---|
| 1 | `resources/views/livewire/partials/moves-panel.blade.php` | Opcional: badge “SOBRE SÍ MISMO” si se añade un flag de preview. Si no, sin cambios. |
| 2 | `resources/views/livewire/_pokemon-card.blade.php` | Sin cambios: ya usa `selectable`; el cambio se hace en el presentador. |
| 3 | `resources/views/livewire/partials/items-panel.blade.php` | Solo verificación: `aliadosSeleccionables` en `:37` no debe ofrecer un objetivo de objeto inválido si se incluye al actor en `refsAliado`. |

### No se tocan

- `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php`
- `src/Battle/Domain/ValueObjects/EfectoAliado.php`
- `src/Battle/Domain/Enums/TipoEfectoAliado.php`
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php`
- `src/Battle/Domain/AgregadoBatalla.php`
- `src/Battle/Domain/AI/SelectorAccionIA.php` y `EvaluadorSoporteIA.php`
- `app/Support/BattleSessionService.php`
- `src/Battle/Presentation/DTOAccionBatalla.php`
- `app/Livewire/Combate.php`
- `storage/data/moves.json`

### Secuencia recomendada

1. Backend: catálogo.
2. Backend: presentadores (`MovesPreviewPresenter`, `BatallaVistaPresenter`, opcional `PresentadorVistaCombate`).
3. Backend: `GestorSeleccionBatalla`.
4. Backend: `GestorAccionBatalla`.
5. Frontend: badge opcional y verificación de `items-panel`.
6. Verificación estática y manual.

No hay dependencias externas ni migraciones. No se requiere `npm run build` salvo que se toque Blade; si se toca, el especialista debe indicar si hace falta recompilar.

---

## 7. Criterios de aceptación y verificación sin tests

### Criterios de aceptación

- **CA1:** En `player_target`, la carta del actor vivo es seleccionable cuando el actor tiene al menos un movimiento autoobjetivable del catálogo. No lo es si no tiene ninguno.
- **CA2:** Al seleccionar al actor y abrir `player_move`, `sand-tomb`, `fire-spin` y `whirlpool` muestran el efecto de apoyo (escudo físico, escudo especial, Acua Aro) y **no** muestran daño ni “×efectividad” contra uno mismo.
- **CA3:** Al confirmar `sand-tomb` sobre el actor, se incrementa su DEF actual hasta el máximo, sin daño, sin `partiallytrapped` y consumiendo PP/turno.
- **CA4:** Al confirmar `fire-spin` sobre el actor, se incrementa su DEF.ESP actual hasta el máximo, sin daño, sin `partiallytrapped` y consumiendo PP/turno.
- **CA5:** Al confirmar `whirlpool` sobre el actor, se aplica/refresca `AQUA_RING` con 5 turnos y curación por ronda del 5 % del HP máximo del lanzador; sin daño y consumiendo PP/turno.
- **CA6:** Un movimiento normal (no autodirigido y no catalogado) seleccionado con `defenderId === actorId` se rechaza en servidor con “Objetivo no válido.” y no aplica daño ni estado. Lo mismo si se manipula `pendingAction`/sesión.
- **CA7:** `DESAFIO` no redirige un autoobjetivo de catálogo al rival forzado.
- **CA8:** El soporte sobre otro aliado sigue funcionando igual que antes (TASK-033), incluyendo retaguardia, y no se altera el targeting de rivales.
- **CA9:** La IA no genera autoobjetivos de soporte; su comportamiento es el actual.
- **CA10:** No hay cambios de `SESSION_VERSION`; una sesión antigua sigue cargando y las acciones pendientes previas no se reinterpretan como autoobjetivo.
- **CA11:** No se rompen `Cubrir`/`Guardia`, prioridades, silencio, PP, objeto de elección ni el panel de objetos.

### Verificación estática

- `php -l` sobre los seis archivos PHP modificados.
- Análisis estático con la configuración existente: `phpstan.neon` y `phpstan-hardener.neon`; `deptrac.yaml` para la regla de dependencias (dominio sin Livewire).
- Formato con Pint sobre los archivos tocados.
- Revisión manual de que no se introducen dependencias de `App\Livewire` en `src/Battle` ni de Eloquent/Blade en dominio.

### Verificación manual (sin tests)

1. **Escenario base:** Pokémon del jugador con `sand-tomb`. En `player_target`, comprobar que su carta tiene `cursor-pointer` y `wire:click`. Pulsarla; comprobar que `currentMoves` se genera y que el panel muestra el efecto de escudo, no daño.
2. **Aplicación:** confirmar; comprobar log, incremento de DEF, PP restante y ausencia de daño/estado `partiallytrapped`.
3. **`fire-spin`:** repetir y comprobar DEF.ESP.
4. **`whirlpool`:** comprobar badge de `AQUA_RING`, 5 turnos, curación al final de ronda y refresco al reutilizar.
5. **Movimiento normal:** manipular desde el cliente/Livewire `selectedTargetTeam = 0`, `selectedTargetIdx = índice del actor` y llamar `selectMove()` con un movimiento normal; debe registrar “Objetivo no válido.” y no crear daño.
6. **`pendingAction`:** simular una acción pendiente con `defenderId === actorId` y movimiento normal; el resolver debe rechazarla sin daño.
7. **`DESAFIO`:** aplicar la condición y comprobar que el autoobjetivo no se redirige.
8. **Aliado:** usar `sand-tomb` sobre un compañero vivo; debe seguir funcionando.
9. **IA:** turno de un rival con `sand-tomb`; comprobar que no se autoobjetiva y que su comportamiento de ataque/soporte no cambia.
10. **Sesión antigua:** cargar una batalla anterior al cambio y continuar; no debe fallar por versión.
11. **Posturas:** con `Cubrir`/`Guardia`, comprobar que el movimiento no se puede seleccionar igual que antes.
12. **Sin PP / silencio:** comprobar que `movimientoDisponible()` sigue bloqueando y que el silencio no bloquea estos movimientos físico/especial (solo estado).

No se proponen tests ni se ejecuta la suite, conforme a las restricciones.

---

## 8. Riesgos, casos límite y preguntas abiertas

### Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | Relajar solo la presentación y no el resolver: `sand-tomb` se autoinflige y aplica `partiallytrapped`. | Daño/estado al propio actor; explotación evidente. | Guarda en `selectMove` y en `GestorAccionBatalla::resolver`; enrutar a `aplicarMovimientoAliado()` solo con catálogo. |
| **Alto** | Cliente manipula `selectedTargetTeam/Idx` (propiedades públicas Livewire) y llama `selectMove()` con un movimiento normal. | Autoataque/autoestado. | Validación de servidor en `selectMove`; `jugadorAutorizado()` ya lee el actor de la sesión, no de `actingRefId`. |
| **Alto** | `pendingAction` inconsistente o antigua con `defenderId === actorId`. | Autoataque si se ejecuta la rama normal. | Defensa en profundidad en `resolver`: rechazar antes de `aplicarMovimiento()` si no es autodirigido ni catálogo. |
| **Medio** | `DESAFIO` redirige el autoobjetivo al rival. | El jugador no obtiene el beneficio; ataca al rival. | Excluir `$autoobjetivoCatalogo` de la condición de `DESAFIO`. |
| **Medio** | `MovesPreviewPresenter` muestra daño contra uno mismo. | El jugador confirma pensando que ataca; UX incoherente. | Ruta de preview de autoobjetivo y bloqueo explícito de daño para no usables. |
| **Medio** | `refsAliado` incluye al actor y `aliadosSeleccionables` cambia de significado; `items-panel:37` podría mostrar un aviso/objeto sobre aliado sin aliados. | Regresión visual menor. | Preferir flag separado B.2.b; si se usa B.2.a, verificar `items-panel`. |
| **Bajo** | Log “usa X sobre X” y banner de animación oculto. | Cosmético. | Texto “sobre sí mismo”; aceptar el comportamiento de autodirigidos. |
| **Bajo** | `AQUA_RING` ya activo: `reemplazarCondicion()` refresca, no acumula. | Gasta PP/turno sin stack. | Mantener paridad con aliados; documentar. |
| **Bajo** | `sand-tomb`/`fire-spin`/`whirlpool` tienen `secondaries: partiallytrapped`; si alguna vez se enrutan mal, el actor se autotrampa. | Estado indebido. | La ruta de apoyo no aplica `secondaries`; el rechazo de autoataque lo impide. |
| **Bajo** | Cambiar `actorAdmiteSoporte()` o el contexto de IA para “incluir al actor”. | La IA empezaría a autoobjetivarse y cambiaría su comportamiento. | No tocar dominio de IA; mantener la exclusión del actor. |
| **Bajo** | Añadir un campo a `DTOAccionBatalla` sin subir `SESSION_VERSION`. | Sesiones antiguas incompatibles. | No añadir campos; el contrato actual basta. |

### Casos límite

- **Actor en vanguardia/retaguardia:** el autoobjetivo no debe someterse a la restricción de fila de los aliados; el actor siempre está a su propio alcance. No usar el filtro `estaEnRetaguardia()` de `actorAdmiteSoporte()` para el autoobjetivo.
- **Actor debilitado:** no es seleccionable; `refsAliado` exige `alive === true`. No hay autoobjetivo de un muerto.
- **Actor con escudo al máximo:** `aplicarEscudo()` registra “ya tiene la DEF al máximo” y igualmente consume PP/turno, igual que sobre un aliado. Es el comportamiento actual; si se quiere evitar, es una decisión de producto aparte.
- **`whirlpool` ya activo:** refresca 5 turnos; no acumula. `reemplazarCondicion()` quita y añade.
- **Movimiento sin PP:** `movimientoDisponible()` lo bloquea antes de crear la acción (`GestorSeleccionBatalla.php:111-121`); el preview lo marca no disponible.
- **`Cubrir`/`Guardia`:** `selectMove()` y `GestorCicloTurnosBatalla()` consumen la acción sin permitir movimiento (`GestorSeleccionBatalla.php:95-104`; `GestorCicloTurnosBatalla.php:129-137`). El autoobjetivo no cambia esta regla.
- **Prioridad declarada:** los tres movimientos tienen `priority: 0`; no aparecen en `prioridadesDisponibles` (`GestorPreparacionBatalla.php:55-56`). Si en el futuro se añadiera un autoobjetivo con prioridad, `validarObjetivoPrioridad()` solo contempla autodirigidos (`:246-250`) y habría que revisarlo.
- **`DESAFIO`:** decidir si el autoobjetivo debe permitirse o bloquearse. La recomendación técnica es permitirlo y **no redirigirlo**; la alternativa es rechazarlo mientras `DESAFIO` esté activo. Requiere aprobación de producto.
- **IA:** no se autoobjetiva; `actorAdmiteSoporte()` y `agregarAccionesSoporte()` excluyen al actor. No cambiar.
- **Sesión antigua:** sin cambios de serialización; `SESSION_VERSION` sigue en 21. Una acción pendiente antigua con `defenderId === actorId` no debería existir, pero el resolver la rechazará.
- **`items-panel`:** verificar que incluir al actor en `refsAliado` no habilita un flujo de objeto sobre uno mismo no deseado. `ObjetivosItem::ALIADO_VIVO` ya marca al equipo 0 vivo, incluido el actor, en `marcarInteraccion()` (`BatallaVistaPresenter.php:167-172`); el panel de objetos tiene su propia lógica y no debería verse afectado, pero conviene confirmarlo.
- **Movimientos `allySide`/`adjacentAlly` fuera del catálogo:** no forman parte de este cambio; siguen sin poder usarse sobre aliados porque `selectMove()` los rechaza (`:151-155`). No abrir ese frente sin una tarea aparte.
- **`fire-spin`/`whirlpool` contra rivales:** deben seguir funcionando como ataques normales con su daño y `partiallytrapped`; el enrutado a apoyo solo debe activarse cuando `defender === actor` y el movimiento esté en el catálogo.

### Preguntas abiertas / decisiones que requieren aprobación

1. **Alcance exacto:** ¿se confirma que “Bucle Arena y derivados” son solo `sand-tomb`, `fire-spin` y `whirlpool` (los tres con `target: normal` del catálogo), o se quiere permitir también pulsar la propia carta para `recover`, `swords-dance`, etc.? Los 43 restantes ya funcionan por la ruta autodirigida; permitir su selección desde la propia carta es solo UX.
2. **`DESAFIO`:** ¿el autoobjetivo debe permitirse bajo `DESAFIO` (recomendado, sin redirección) o rechazarse mientras la condición esté activa?
3. **Etiqueta:** ¿“SOBRE ALIADO” es aceptable para el autoobjetivo o se quiere “SOBRE SÍ MISMO” (requiere tocar `moves-panel.blade.php`)?
4. **Escudo al máximo / `AQUA_RING` activo:** ¿se permite gastar PP/turno igual que con aliados, o se bloquea el movimiento cuando el beneficio es nulo? La paridad actual sugiere permitirlo.
5. **Presentación:** ¿se prefiere el cambio mínimo B.2.a (incluir al actor en `refsAliado`, con posible efecto en `aliadosSeleccionables`) o el flag separado B.2.b (más limpio, más archivos)?
6. **IA:** confirmar que no se desea que la IA aprenda a autoobjetivarse. La recomendación es mantenerla intacta.
7. **Log/animación:** ¿se acepta el texto “usa X sobre X” y el banner de animación oculto, o se quiere un texto específico “sobre sí mismo” en `ResolutorAccionBatalla::aplicarMovimientoAliado()`?

---

**Conclusión final:** el beneficio de Bucle Arena, Giro Fuego y Torbellino sobre el propio Pokémon es viable con un cambio acotado en presentación, selección y resolución. El dominio de efectos ya lo soporta; el riesgo principal es enrutar mal el autoobjetivo y permitir autoataque, que debe bloquearse en servidor en `GestorSeleccionBatalla::selectMove()` y, como defensa en profundidad, en `GestorAccionBatalla::resolver()`. No se requiere cambiar la IA, el DTO ni la versión de sesión.
