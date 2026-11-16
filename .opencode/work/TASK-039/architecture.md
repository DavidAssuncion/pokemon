# TASK-039 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e4003f22fffeHDOJsLtcOOB295`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-039 — Diseño: eliminar «Esperar» y garantizar una acción

## 1. Decisiones de diseño

1. **Se adopta la Opción 3 del análisis**: eliminar botón + `mostrarEsperar` + cadena pública `pasarTurno`, y añadir un **fallback interno no invocable desde Livewire** en `GestorCicloTurnosBatalla::avanzar`.
2. **No se adopta la Opción 4** (puente con método guardado). El desarrollador decidió «eliminar por completo» el método público. Coste aceptado: una pestaña con HTML antiguo que pulse «Esperar» recibirá un error de método de Livewire **sin mutar estado** (la llamada no existe en servidor, por lo que no hay consumo ni corrupción) y el usuario deberá refrescar.
3. **El fallback se coloca en `avanzar` y no en un colaborador nuevo.** Ya existen tres rutas de auto-salto con el mismo patrón en ese método (estado bloqueante, postura defensiva y salto de IA). Crear una clase nueva no aporta y rompería la simetría.
4. **«Sin acción legal» = ningún movimiento con `movimientoDisponible()` y ningún objeto con `aplicable === true`.** La postura defensiva (`Guardia`/`Cubrir`) se evalúa **antes** del fallback, por lo que a la altura del fallback el actor está en `PosturaCombate::ATACAR`. `Retrasar turno` y `Atacar con X` no son acciones (no consumen), por lo que no cuentan como acción legal.
5. **Detección de objetos mediante la proyección ya sincronizada `$componente->objetosCombate`**, no inyectando `ObtenerOpcionesObjeto` + `Guard`:
   - `GestorVistaBatalla::sincronizar(..., $actor)` ya calcula las opciones del actor con `ObtenerOpcionesObjeto`.
   - `ObtenerOpcionesObjeto` marca `aplicable = cantidad > 0 && al menos un objetivo válido`, y ese flag es exactamente el que consume el panel.
   - Inyectar el caso de uso duplicaría la consulta de saldos en cada inicio de turno y crearía dos fuentes de verdad para «aplicable»; además añadiría dos dependencias al constructor.
   - Leer ese array en un **método privado** está amparado por la excepción de `.ai/rules/arrays-dto-collections.md:20` («arrays normalizados que solo consume una función privada»).
6. **El actor se toma de `$actor` (resultado de `getNextActor()`/actor forzado), nunca de `$componente->actingRefId`.** Es el mismo valor que devolvería `ResolutorActorBatalla::jugadorAutorizado()` una vez ejecutado `setActorActualId`, pero sin releer sesión ni reabrir la debilidad que tenía `pasarTurno`.
7. **Si hay preparación pendiente, el fallback se difiere.** Auto-saltar la preparación marcaría `rondaPreparada` y privaría a **todo el equipo** de declarar posturas/prioridades esa ronda, incluida la posibilidad de elegir `Guardia`/`Cubrir`. Diferir no produce soft-lock: el jugador tiene «Preparación» en la barra y «Listo»/«Saltar» en el modal; al confirmar se llama `nextActor()` y el fallback se reevalúa.
8. **Persistencia obligatoria antes de `nextActor()`**: `avanzar` recarga la batalla de sesión al entrar y `nextActor` reentra en `avanzar`. Si se consume la acción y no se guarda antes, la reentrada leería el estado antiguo y el fallback se repetiría indefinidamente. Orden exacto: consumir → log → `processing=false` → `session->guardar` → `nextActor` → `return`.

## 2. Eliminación UI/contrato

### 2.1. Blade

`resources/views/livewire/partials/turn-bar.blade.php` — eliminar **exactamente** el bloque `:33-41`:

```blade
@if($turnBar['mostrarEsperar'])
    <button type="button"
            class="btn btn-sm btn-outline-secondary"
            wire:click="pasarTurno"
            wire:loading.attr="disabled"
            title="Consume tu acción sin atacar">
        Esperar
    </button>
@endif
```

No tocar Preparación, Retrasar turno, Atacar con X ni Registro. El literal `Esperando...` de `:10` es el estado vacío de la cola y se mantiene.

### 2.2. Presentador

`app/Livewire/Presenters/BatallaVistaPresenter.php` — eliminar la clave `'mostrarEsperar' => $faseSeleccion && $sinAnimacion,` en `:303`, dentro de `turnBar()`.

- Verificado: **solo** la consume `turn-bar.blade.php:33`.
- No hay docblock ni array shape que enumere la clave.
- `$faseSeleccion` y `$sinAnimacion` siguen usándose en `puedeRetrasar`/`puedeCambiarTurno`, por lo que no quedan variables muertas.
- **El borrado de esta clave y el del bloque Blade deben ir en el mismo commit/despliegue**; si se desplaza uno sin el otro, Blade lanzará «Undefined array key "mostrarEsperar"».

### 2.3. Cadena pública (de fuera hacia dentro)

1. `app/Livewire/Combate.php:149-152` — eliminar `pasarTurno()`. Contrato público: **18 → 17** métodos.
2. `app/Livewire/Combate/Soporte/OrquestadorBatalla.php:113-116` — eliminar la delegación.
3. `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:321-358` — eliminar el método. Actualizar el docblock de clase que dice «objetivo, movimiento, cambio de turno **y espera**» → «objetivo, movimiento y cambio de turno».
   - Comprobado que tras el borrado no quedan imports muertos.

4. No hay más referencias a `pasarTurno` ni `mostrarEsperar` en `app/`, `src/`, `resources/` ni `tests/`.

### 2.4. Compatibilidad con pestañas abiertas

- No se mantiene puente. Una pestaña con HTML anterior ejecutará `$wire.pasarTurno()`; Livewire lanza `MethodNotFoundException` en cliente. Como el método ya no existe, **no hay consumo de acción ni cambio de sesión**; el peor caso es un aviso de error y un refresco.
- Recomendaciones operativas: desplegar backend y Blade juntos; ejecutar `php artisan view:clear` si el despliegue no invalida vistas compiladas.
- No cambiar `SESSION_VERSION` ni la estructura serializada: eliminar un método público no afecta al snapshot de Livewire ni al agregado persistido.

## 3. Fallback automático y condición de «sin acción legal»

### 3.1. Ubicación exacta

En `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php`, dentro de `avanzar`, **después** de `$this->gestorVista->sincronizar($componente, $battle, $actor);` y de resolver `$posicion`/`$isPlayer`, al inicio de la rama `if ($isPlayer)`, **antes** de construir `currentMoves` y fijar `phase`.

Pseudocódigo:

```php
$isPlayer = $posicion->team === 0;

if ($isPlayer) {
    if (! $componente->preparacionPendiente
        && $this->sinAccionLegal($actor, $componente->objetosCombate)) {
        $componente->log[] = "{$actor->nombre()} no tiene ninguna acción disponible y pierde el turno.";
        $turnos->consumeAction($actor);
        $componente->selectedMoveIdx = null;
        $componente->processing = false;
        $this->session->guardar($componente->battleId, $battle);
        $componente->nextActor();

        return;
    }

    $componente->currentMoves = // sin cambios
    $componente->phase = FaseCombate::SELECCION_OBJETIVO->value;
    $componente->processing = false;
} else { /* sin cambios */ }
```

Helper privado (nuevo, en la misma clase; no crea abstracción):

```php
/**
 * ¿El actor del jugador carece de toda acción legal en esta ronda?
 * La postura defensiva ya se consumió antes de llegar aquí.
 *
 * @param list<array<string, mixed>> $objetosCombate proyección de vista ya sincronizada
 */
private function sinAccionLegal(Combatiente $actor, array $objetosCombate): bool
{
    foreach ($actor->pokemon()->moves() as $movimiento) {
        if ($movimiento instanceof MovimientoBatalla && $actor->movimientoDisponible($movimiento)) {
            return false;
        }
    }

    foreach ($objetosCombate as $objeto) {
        if (($objeto['aplicable'] ?? false) === true) {
            return false;
        }
    }

    return true;
}
```

Requiere `use Src\Battle\Domain\Combatiente;` en `GestorCicloTurnosBatalla`. No requiere dependencias nuevas en el constructor.

### 3.2. Cómo determina «sin acción legal»

- **Movimientos**: `Combatiente::movimientoDisponible()` delega en `MovimientosCombatiente::motivoMovimientoNoDisponible()`, cubriendo `silencio`/`mofa`, `cargando`, `sin_usos` y `objeto_eleccion`. Se usa el dominio, no `$componente->currentMoves` (que en la rama de jugador aún no incluye `disponible`).
- **Objetos**: `$componente->objetosCombate` ya proyectado para el actor actual. Se comprueba `'aplicable' === true`, el mismo flag que deshabilita los botones en `items-panel`.
- **Postura defensiva**: no se comprueba aquí porque se consumió antes (`postura() !== PosturaCombate::ATACAR`).
- **Retrasar/Atacar con X/Cambiar objetivo/Cancelar objeto**: no son acciones legales (no consumen) y por diseño no evitan el fallback.

### 3.3. Consumo, log, animación, sincronización, guardado y `nextActor`

- **Consumo**: `$turnos->consumeAction($actor)`; si el actor tiene `accionesExtra` (máximo 1) puede requerir una segunda pasada del fallback, que terminará igualmente.
- **Log**: una única entrada clara: `"{$actor->nombre()} no tiene ninguna acción disponible y pierde el turno."`.
- **Animación**: no se reinicia ni se añade `GestorAnimacionBatalla`. Al entrar en la rama de jugador, `animAttackerId` ya es `''`.
- **Sincronización**: no se repite `gestorVista->sincronizar`. La de antes ya volcó log, equipos y `objetosCombate`; el fallback solo añade una entrada al log del componente y consume una acción.
- **Guardado**: `session->guardar(...)` **antes** de `nextActor`, imprescindible por la recarga de `avanzar`.
- **`nextActor`**: llamada recursiva y `return` inmediato; no se ejecuta el resto de la rama de jugador.
- **`processing`**: ponerlo a `false` en el fallback (paridad con la rama normal).

### 3.4. Evitar bucles y dobles consumos

- Cada pasada consume exactamente una acción del actor resuelto y retorna. `getNextActor()` filtra por `vecesActuado === 0` y por `accionesExtra > 0`, y `consumeAction` incrementa `vecesActuado` o decrementa extras. El conjunto de actores pendientes es finito y decreciente.
- Cuando no queda ninguno, el inicio de `avanzar` avanza de ronda, lo que además avanza cargas. No hay recursión infinita.
- No se toca `pendingAction`; a esta altura siempre es `null`.

## 4. Casos límite e interacciones

| Caso | Comportamiento |
|---|---|
| **Sin movimientos** | `sinAccionLegal` devuelve `true`. Si no hay objeto aplicable → salto automático con log. Si hay objeto → fase normal de objetos. |
| **Todos sin PP** | Todos `'sin_usos'` → salto si no hay objetos. |
| **Silencio/Mofa con solo movimientos de estado** | Todos `'silencio'` → salto si no hay objetos. |
| **Objeto de elección** | El movimiento bloqueado sigue disponible; si además está `sin_usos`/`cargando`, el resto devuelve `'objeto_eleccion'` → salto si no hay objetos. |
| **Cargando** | `carga < cargaMaxima` → salto. No es permanente: `startNewRound` avanza cargas. |
| **Sin objetos aplicables / colección vacía** | Cuenta como no disponible, coherente con el panel. |
| **Sin objetivos legales (rivales)** | No se añade comprobación: con rivales vivos siempre hay al menos una carta objetivo; para objetos, `aplicable` ya exige objetivo válido. |
| **Objetivo aliado rechazado en `selectMove`** | Fricción recuperable con «Cambiar objetivo», no soft-lock. |
| **Preparación pendiente** | El fallback se difiere; el jugador confirma/omite y se reevalúa. Mantiene `Guardia`/`Cubrir` y prioridades. |
| **`Guardia`/`Cubrir`** | Acción legal ya consumida antes del fallback. |
| **Estados que bloquean** | Consumo forzado previo. |
| **`player_item_target`** | El fallback no puede dispararse durante la selección: solo se ejecuta al entrar en `avanzar`. |
| **Prioridades declaradas** | Se resuelven en preparación; si consumen la acción, el fallback no actúa. |
| **IA** | No se toca. La rama `team !== 0` mantiene `prepararAnimacionIa` y su salto propio. |
| **Sesión antigua sin `actorActualId`** | Sin impacto: `avanzar` resuelve con `getNextActor()` y fija `setActorActualId`. |
| **Pestaña abierta con HTML antiguo** | Llamada a método inexistente → error de Livewire sin mutación. Refrescar. |
| **`nextActor` público manipulado** | Reentra en `avanzar`; con acción legal solo re-renderiza selección, **no** consume. Con acción ilegal consume (deseado). Preexistente. |
| **Actor no localizable en la vista** | Se mantiene la salida defensiva; el fallback no se evalúa. |

## 5. Archivos, orden y especialistas

Sin migraciones, sin dependencias nuevas, sin tests, sin clases nuevas.

**`backend`**
1. `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` — `use Combatiente`, helper `sinAccionLegal()` y llamada en la rama de jugador. **Primero**.
2. `app/Livewire/Combate.php` — eliminar `pasarTurno()`.
3. `app/Livewire/Combate/Soporte/OrquestadorBatalla.php` — eliminar `pasarTurno()`.
4. `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` — eliminar `pasarTurno()` y ajustar docblock.
5. `app/Livewire/Presenters/BatallaVistaPresenter.php` — eliminar `'mostrarEsperar'`.

**`frontend`**
6. `resources/views/livewire/partials/turn-bar.blade.php` — eliminar el bloque del botón.

**Dependencias y secuencia**
- 2 → 3 → 4 en ese orden (de fuera hacia dentro) para no dejar llamadas a métodos inexistentes en ningún paso intermedio.
- **5 y 6 son indivisibles** (mismo commit): clave y Blade deben desaparecer juntos.
- 1 es independiente y puede ir primero. Todo el conjunto debe desplegarse en una sola release.
- Tras el cambio: `php artisan view:clear` si el entorno no invalida vistas compiladas.

## 6. Criterios de aceptación y verificación

### Criterios de aceptación

1. En ninguna fase se renderiza el botón «Esperar».
2. `BatallaVistaPresenter::turnBar()` no devuelve `mostrarEsperar` y ningún Blade la referencia.
3. `App\Livewire\Combate` expone 17 métodos públicos; `pasarTurno` no existe en `Combate`, `OrquestadorBatalla` ni `GestorSeleccionBatalla`.
4. `$wire.pasarTurno()` produce error de método y **no** consume acción ni modifica la sesión.
5. `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos y preparación siguen funcionando.
6. Con todos los movimientos no disponibles y sin objetos aplicables, el turno avanza automáticamente, con log comprensible y sin bloqueo.
7. Con al menos un movimiento disponible **o** un objeto `aplicable`, el fallback **no** se dispara.
8. Con preparación pendiente, el fallback no se dispara hasta confirmar/omitir; `Guardia`/`Cubrir` y prioridades conservan su efecto.
9. Consumo único por actor y avance correcto de ronda; sin recursión infinita.
10. `SESSION_VERSION` y la serialización del agregado no cambian.

### Verificación estática (sin tests)

```bash
grep -rn "pasarTurno" app resources src          # 0 coincidencias
grep -rn "mostrarEsperar" app resources src      # 0 coincidencias
grep -c "public function" app/Livewire/Combate.php   # 17
php -l app/Livewire/Combate.php
php -l app/Livewire/Combate/Soporte/OrquestadorBatalla.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php
vendor/bin/pint --dirty --format agent
composer analyse
git diff --stat
```

### Verificación manual

- Combate normal: sin «Esperar»; seleccionar objetivo, atacar, usar objeto, «Cambiar objetivo», «Retrasar turno», «Atacar con X», preparación.
- Estado sin acción: provocar silencio/Mofa sobre un Pokémon con solo movimientos de estado, o un movimiento de carga no cargado como única opción; comprobar salto automático y log en «Registro».
- Confirmar que al abrir preparación el turno no se auto-salta hasta pulsar «Listo»/«Saltar».
- Pestaña abierta antes del despliegue: pulsar «Esperar» → error de método, sin cambios de estado; refrescar.

## 7. Riesgos residuales y preguntas para el desarrollador

### Riesgos residuales

| Severidad | Riesgo | Mitigación / estado |
|---|---|---|
| Medio | El aviso solo es visible en el modal «Registro»; en la vista principal el jugador puede no entender el salto | Aceptado por alcance mínimo; ver pregunta 1. |
| Medio | Pestaña abierta con HTML antiguo | Error de método no destructivo; refrescar. Despliegue conjunto + `view:clear`. |
| Bajo | El fallback consume acciones extra del mismo actor en pasadas consecutivas | `accionesExtra` está limitado a 1; finito. |
| Bajo | Acoplamiento al flag `aplicable` de la proyección | Contrato de vista documentado y único consumidor UI; lectura en método privado. |
| Bajo | `nextActor` sigue siendo público | Preexistente y fuera de alcance; con acción legal no consume. |
| Bajo | IA con movimiento disponible pero sin objetivo: `prepararAnimacionIa` retorna sin consumir | Preexistente; en la práctica hay siempre objetivo rival vivo. No se toca. |
| Bajo | Reproducir «todos sin PP» requiere datos con `usosMaximos > 0` | Validar por inspección y con silencio/carga. |

### Preguntas para el desarrollador

1. ¿Se desea un aviso visible fuera del modal «Registro» (banner/toast)? Implicaría una propiedad pública nueva o un evento de animación, y excede el cambio mínimo aprobado.
2. ¿Se acepta que, con preparación pendiente, el jugador deba pulsar «Listo»/«Saltar» antes del salto automático (recomendado para no perder posturas/prioridades del equipo), o se prefiere auto-saltar también la preparación?
3. ¿Se confirma el texto de log propuesto o se prefiere otro mensaje?
4. ¿Se confirma que no se quiere puente de compatibilidad para pestañas abiertas? El diseño asume que no, según la decisión de eliminar por completo el método público.
