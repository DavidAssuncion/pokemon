# TASK-033 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e40ca6893ffef40m6zlM8JUNR6`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-033 — Diseño de corrección de targeting en preparación de ronda

> **Nota:** este documento es solo diseño. No se ha modificado ningún archivo, no se han ejecutado tests y no se han tocado dependencias, migraciones, `bugs.txt` ni `.opencode/work/TASK-032/`.

---

## 1. Decisiones de diseño

### 1.1. Resumen de decisiones

| Fallo | Decisión | Justificación breve |
|---|---|---|
| 1. Apoyo sobre aliado en retaguardia | **Opción A**: eliminar la restricción solo en el camino del jugador. IA intacta. | El dominio y `GestorAccionBatalla` no tienen restricción de fila para apoyo (`ResolutorAccionBatalla::aplicarMovimientoAliado()`, `GestorAccionBatalla.php:186-215`). El bloqueo es de presentación/selección. |
| 2. Objetivo de prioridad | **Opción B**: mapa `$prioridadesObjetivo` en el componente, opción “Automático”, validación en `aplicar()` y revalidación en `resolver()`. | No requiere tocar serialización de dominio ni `SESSION_VERSION`; la resolución ocurre en la misma petición. |
| 3. Objetivo de Cubrir | **Opción B**: mapa `$coberturas` en el componente, selector solo en preparación, sin persistencia en Equipo. | `normalizarCubridores()` ya conserva objetivos válidos y rellena fallback; no hace falta migración ni cambio de contrato de `teams.posturas`. |
| `DESAFIO` | **Prevalece el objetivo forzado** sobre la elección del jugador. Se refleja en la UI con aviso y sin selector. | Es coherente con el flujo normal de ataques (`GestorSeleccionBatalla.php:178-183`), donde el objetivo forzado sobrescribe al elegido. Ignorarlo permitiría saltarse la condición. **Requiere aprobación explícita.** |
| Reasignación de Cubrir al morir el objetivo | **Mantener comportamiento actual**: siguiente ronda. | La reasignación inmediata exigiría normalizar tras cada acción/debilitamiento y podría cambiar silenciosamente la cobertura elegida a mitad de ronda. Es un cambio de producto/balance, no trivial. |
| Prioridades de apoyo | **No se enrutan** a `aplicarMovimientoAliado()`. Se documenta la extensión. | Hoy ningún movimiento del catálogo aliado tiene `priority > 0` (`storage/data/moves.json`; análisis TASK-033 §2.2). |
| Métodos públicos nuevos | **Ninguno.** | La validación en `aplicar()`/`resolver()` es suficiente. Se evita ampliar el contrato de 18 métodos. |
| Propiedades públicas nuevas | **2**: `$prioridadesObjetivo` y `$coberturas`. | Es la opción menos frágil frente a codificar `índice|refId` o modificar `$posturas`. El contrato de `Combate` pasa de 32/18 a **34 propiedades/18 métodos**. **Requiere aprobación explícita.** |

### 1.2. Decisión de diseño adicional: regla única de objetivos rivales legales

**Propuesta:** añadir un método de lectura al agregado:

```php
/**
 * Rivales vivos a los que el actor puede dirigir un ataque según la regla
 * de fila: vanguardia → vanguardia rival mientras quede viva; retaguardia
 * o vanguardia rival caída → cualquier rival vivo.
 *
 * @return \Src\Battle\Domain\Collections\CombatientesCollection
 */
public function objetivosRivalesLegalesPara(Combatiente $actor): CombatientesCollection
```

Se usaría en:
- `GestorPreparacionBatalla::abrir()` para construir las opciones del selector.
- `GestorPreparacionBatalla::aplicar()` para validar el objetivo declarado.
- `GestorPrioridadesBatalla::objetivoPara()` para revalidar en resolución.

**Motivo:** la regla ya está duplicada en `SincronizadorVistaBatalla.php:45-52` y `SelectorAccionIA::filtrarEnemigosPorPosicion()` (`SelectorAccionIA.php:348-359`). Añadir una tercera copia en `aplicar()` y una cuarta en `resolver()` sería un riesgo de divergencia. El método es lectura pura, no altera IA, no se serializa y no toca `actorAdmiteSoporte()`.

**Alternativa si se prefiere no tocar dominio:** mover la regla a un helper privado de `GestorPrioridadesBatalla` y consumirlo desde `GestorPreparacionBatalla` (ya inyecta `GestorPrioridadesBatalla` en `GestorPreparacionBatalla.php:24`). No la recomiendo porque deja la regla en la capa Livewire.

### 1.3. Capas y dirección de dependencias

```text
Presentation
  - app/Livewire/Combate.php (props de formulario)
  - resources/views/livewire/partials/preparacion-panel.blade.php
  - app/Livewire/Presenters/BatallaVistaPresenter.php
        │
        ▼
Application / Livewire support
  - GestorPreparacionBatalla
  - GestorPrioridadesBatalla
  - GestorSeleccionBatalla
        │
        ▼
Domain
  - AgregadoBatalla (nuevo método de lectura de objetivos legales)
  - EquipoBatalla::normalizarCubridores() (sin cambios)
  - Combatiente / PosturaCombate / CatalogoEfectosAliado (sin cambios)
  - SelectorAccionIA / actorAdmiteSoporte (sin cambios)
```

- **Infraestructura:** sin cambios. La sesión sigue encapsulada en `BattleSessionService`.
- **Persistencia:** sin cambios. No hay tablas, migraciones ni transacciones nuevas.
- **Blade:** solo pinta; las listas legales se construyen en servidor.

### 1.4. Contexto y causa raíz (evidencia)

| Fallo | Evidencia |
|---|---|
| 1 | `BatallaVistaPresenter.php:128,139-141` filtra aliados en retaguardia; `GestorSeleccionBatalla.php:64-67` y `:162-169` repiten el rechazo. `GestorAccionBatalla.php:186-215` ya enruta apoyo aliado sin restricción de fila. `CatalogoEfectosAliado.php:28` incluye `swords-dance`; el catálogo completo está en `:21-68`. La IA conserva su filtro en `SelectorAccionIA.php:314-326` y `AgregadoBatalla.php:416-424`. |
| 2 | `$prioridades` solo guarda índice (`Combate.php:39-40`); `$prioridadesDisponibles` no tiene objetivo (`Combate.php:45-46`); el panel solo pinta el movimiento (`preparacion-panel.blade.php:24-41`); `aplicar()` no lee objetivo (`GestorPreparacionBatalla.php:174-192`); `resolver()` llama a `objetivoPara()` sin objetivo (`GestorPrioridadesBatalla.php:120`); `objetivoPara()` delega en la IA (`:149-162`). |
| 3 | `cambiarPostura()` solo persiste postura (`GestorPreparacionBatalla.php:131-156`); `aplicar()` solo normaliza (`:197`); `normalizarCubridores()` asigna el primer aliado vivo (`EquipoBatalla.php:243-270`); no hay propiedad de componente ni UI; `CombatienteVista.php:142` expone `objetivoCubierto` pero no se usa. |

---

## 2. Contrato Livewire y estructuras de datos

### 2.1. Propiedades nuevas en `App\Livewire\Combate`

```php
/** @var array<string, string> refId actor => refId del rival objetivo de su prioridad ('' = Automático) */
public array $prioridadesObjetivo = [];

/** @var array<string, string> refId cubridor => refId del aliado cubierto ('' = Automático) */
public array $coberturas = [];
```

- Valores por defecto `[]`: compatibles con snapshots antiguos y con la hidratación de Livewire.
- No se renombra ni se cambia el tipo de `$prioridades`, `$posturas`, `$prioridadesDisponibles`.
- **No se añaden métodos públicos.** El contrato de métodos sigue en 18.

### 2.2. `$prioridadesDisponibles` ampliado

Se mantienen las claves existentes y se añaden claves nuevas:

```php
/**
 * @var array<string, list<array{
 *     index: int,
 *     nombre: string,
 *     prioridad: int,
 *     autodirigido: bool,
 *     objetivoForzadoRefId: string,
 *     objetivoForzadoNombre: string,
 *     objetivos: list<array{refId: string, nombre: string, posicion: string}>
 * }>>
 */
public array $prioridadesDisponibles = [];
```

- `autodirigido`: `true` si `esAutodirigido()` o `objetivo === ObjetivoMovimiento::ALL` (mismo criterio que `objetivoPara()`, `GestorPrioridadesBatalla.php:154-158`).
- `objetivoForzadoRefId/Nombre`: rellenos solo si el actor tiene `DESAFIO` con objetivo forzado vivo; cadena vacía si no. La UI decide mostrarlo solo cuando `autodirigido === false`.
- `objetivos`: rivales vivos legales para ese actor según la regla de fila. Se calcula en servidor para no duplicar la regla en Blade.

### 2.3. Por qué `wire:model` y `wire:model.live`

| Control | Directiva | Motivo |
|---|---|---|
| Select de movimiento de prioridad | `wire:model.live="prioridades.{{ refId }}"` | El servidor necesita saber el movimiento elegido para decidir si el objetivo aplica (`autodirigido`) y para mostrar/ocultar el selector de objetivo y la cobertura. Sin `.live`, el bloque no aparecería hasta la siguiente petición. Coste: 1 roundtrip por cambio de movimiento. |
| Select de objetivo de prioridad | `wire:model="prioridadesObjetivo.{{ refId }}"` | El valor solo se necesita al confirmar/saltar. Livewire lo envía con la siguiente petición. Sin roundtrip adicional. |
| Select de cobertura | `wire:model="coberturas.{{ refId }}"` | Igual: solo se necesita al confirmar/saltar. Sin roundtrip adicional. |
| Select de postura | `wire:change="cambiarPostura(...)"` (existente) | Ya provoca roundtrip y es el que hace aparecer el selector de cobertura. |

**Alternativa descartada:** usar Alpine para ocultar/mostrar el objetivo según el movimiento. Evitaría el roundtrip, pero duplicaría en cliente la regla `esAutodirigido`/`ALL` y complicaría el Blade. Se prefiere autoridad del servidor.

**Fallback si `wire:model.live` diera problemas dentro del modal con `wire:ignore.self`:** sustituir por `x-on:change="$wire.set('prioridades.{{ refId }}', $event.target.value, true)"`. No se prevé necesario; verificación manual.

### 2.4. Inicialización y limpieza

En `GestorPreparacionBatalla::abrir()` (`GestorPreparacionBatalla.php:29-68`):

```php
$componente->prioridadesObjetivo = [];
$componente->coberturas = [];

foreach ($battle->team1->combatientesCollection()->vivos() as $combatiente) {
    $id = $combatiente->id();
    $componente->prioridadesObjetivo[$id] = '';
    $componente->coberturas[$id] = $combatiente->postura() === PosturaCombate::CUBRIR
        ? $combatiente->objetivoCubiertoId()
        : '';

    // ... construcción de prioridadesDisponibles con objetivos/autodirigido/forzado ...
}
```

En `GestorPreparacionBatalla::limpiar()` (`:209-214`):

```php
$componente->prioridades = [];
$componente->prioridadesDisponibles = [];
$componente->posturas = [];
$componente->prioridadesObjetivo = [];
$componente->coberturas = [];
```

`abrir()` se ejecuta una vez por ronda desde `GestorCicloTurnosBatalla.php:58-60`; `limpiar()` se ejecuta tras `resolver()` en `confirmar()`, `saltar()` y `autoSaltar()` (`:86`, `:107`, `:127`).

---

## 3. Flujo por fallo

### 3.1. Fallo 1 — Apoyo sobre aliado en retaguardia

#### Comprobaciones exactas que se eliminan

1. **`app/Livewire/Presenters/BatallaVistaPresenter.php`**
   - Eliminar `$vanguardiaViva = self::tieneVanguardiaViva($team1);` (`:128`).
   - Eliminar el bloque:
     ```php
     if (($p['posicion'] ?? '') === 'retaguardia' && $vanguardiaViva) {
         continue;
     }
     ```
     (`:139-141`).
   - Eliminar `tieneVanguardiaViva()` (`:152-161`) si queda sin uso. Grep actual: solo se usa en `:128`; se puede borrar.

2. **`app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` — `previewTarget()`**
   - Eliminar `$equipo = $battle->equipoDe($target);` y el bloque:
     ```php
     if ($target->estaEnRetaguardia() && $equipo !== null && $equipo->tieneVanguardiaViva()) {
         return;
     }
     ```
     (`:64-67`).
   - Conservar:
     - `if (! $this->actorAdmiteAliado($actor)) { return; }` (`:60-62`): evita seleccionar aliados si el actor no tiene ningún movimiento del catálogo aliado.
     - Comprobación de objetivo vivo (`:55`).
     - `esAliado()` (`:59`).

3. **`app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` — `selectMove()`**
   - Eliminar `$equipoAliado = $battle->equipoDe($seleccionado);` y el bloque:
     ```php
     if ($seleccionado->estaEnRetaguardia()
         && $equipoAliado !== null
         && $equipoAliado->tieneVanguardiaViva()) {
         $componente->log[] = "{$seleccionado->nombre()} no está en la vanguardia.";
         return;
     }
     ```
     (`:162-169`).

#### Comprobaciones que se conservan

- `selectMove()`:
  - Autoobjetivo solo para movimientos autodirigidos (`:135-140`).
  - Aliado debe estar vivo (`:148-154`).
  - El movimiento debe estar en `CatalogoEfectosAliado` (`:156-160`).
  - `defender === null || ! $defender->estaVivo()` → cancelar (`:185-191`).
  - `DESAFIO` solo para ataques no aliados/no autodirigidos (`:178-183`).
- `previewTarget()`:
  - `actorAdmiteAliado()`.
  - `jugadorAutorizado()`.
  - `objetivoPorSeleccion()`.
- Presentación:
  - `marcarInteraccion()` sigue exigiendo `$p['alive']` y, para rivales, `$p['canTarget']` (`BatallaVistaPresenter.php:193-207`).
  - `SincronizadorVistaBatalla.php:45-52` sigue calculando `canTarget` de team2 según la posición del actor y la vanguardia rival.

#### Evidencia de que no se relaja el targeting rival

- Los bloques eliminados están dentro de `if ($this->esAliado($battle, $actor, $target))` o dentro de `refsAliado()`, que **solo recorre `$team1`** (`BatallaVistaPresenter.php:131`).
- El targeting rival depende de `canTarget`, calculado en `SincronizadorVistaBatalla.php:45-52` y consumido en `BatallaVistaPresenter.php:198-203`. No se toca.
- `previewTarget()` no valida fila rival en servidor hoy; la restricción efectiva es la UI (`canTarget`). Este diseño **no añade ni elimina** esa validación. Si se quiere endurecer, es una mejora separada.
- El flujo rival de `selectMove()` (`else` de `:172-176`) no cambia.

#### Evidencia de que no cambia la IA

- No se modifica `AgregadoBatalla::actorAdmiteSoporte()` (`AgregadoBatalla.php:401-435`).
- No se modifica `SelectorAccionIA::agregarAccionesSoporte()` (`SelectorAccionIA.php:282-339`), que sigue excluyendo retaguardia si hay vanguardia viva (`:317-326`).
- El nuevo método `objetivosRivalesLegalesPara()` es lectura pura y no se usa desde la IA.

#### Flujo resultante

1. El actor tiene al menos un movimiento del catálogo aliado → `refsAliado()` incluye a **todos** los aliados vivos distintos del actor, sin filtro de posición.
2. El jugador clica la carta del aliado en retaguardia → `previewTarget()` acepta (solo comprueba `actorAdmiteAliado`) → `MovesPreviewPresenter::toView()` muestra el efecto aliado.
3. El jugador elige el movimiento → `selectMove()` acepta si el movimiento está en `CatalogoEfectosAliado`.
4. Se crea `DTOAccionBatalla(type: 'attack', defenderId: aliado)`.
5. `GestorAccionBatalla::resolver()` detecta `$esAliado` y `$efectoAliado !== null` y enruta a `ResolutorAccionBatalla::aplicarMovimientoAliado()` (`GestorAccionBatalla.php:186-215`), que no aplica daño ni precisión.

---

### 3.2. Fallo 2 — Objetivo de ataques de prioridad

#### Flujo de datos

```text
preparacion-panel.blade.php
  ├─ <select wire:model.live="prioridades.{refId}">        → $prioridades[refId]
  └─ <select wire:model="prioridadesObjetivo.{refId}">     → $prioridadesObjetivo[refId]
        │
        ▼
Combate::confirmarPreparacion()                (Combate.php:85-88)
  → OrquestadorBatalla::confirmarPreparacion() (OrquestadorBatalla.php:53-56)
  → GestorPreparacionBatalla::confirmar()      (GestorPreparacionBatalla.php:70-89)
  → aplicar(true)                              (:78)
        │
        ├─ valida índice/prioridad/disponibilidad (existente, :174-183)
        ├─ declara prioridad (:189)
        └─ valida objetivo declarado (nuevo)
        │
        ▼
GestorPrioridadesBatalla::resolver()           (:52-147)
  ├─ lee $componente->prioridadesObjetivo[$actor->id()] (nuevo)
  ├─ objetivoPara($battle, $actor, $movimiento, $objetivoDeclarado) (nuevo)
  └─ aplicarMovimiento() (:130-141)
```

#### Reglas de validez

Sea `$actor` el combatiente que declara la prioridad y `$movimiento` el movimiento prioritario:

1. **Autodirigido o `ALL`:** el objetivo se ignora y se resuelve al actor. Mismo criterio que `GestorPrioridadesBatalla.php:154-158`. En `aplicar()` se limpia `$prioridadesObjetivo[$actor->id()]`.
2. **Ataque normal:** el objetivo debe ser:
   - rival (`equipoRivalDe($actor)`),
   - vivo,
   - legal por fila:
     - actor en vanguardia y rival con vanguardia viva → solo vanguardia rival;
     - actor en retaguardia o rival sin vanguardia viva → cualquier rival vivo.
3. **`DESAFIO`:** si el actor tiene `ClaveCondicion::DESAFIO` con `objetivoForzadoId` vivo, el objetivo forzado **prevalece** sobre el declarado. La fila no se aplica al forzado, igual que en el flujo normal (`GestorSeleccionBatalla.php:178-183`) y en la IA (`SelectorAccionIA.php:247-254`).
4. **Prioridad de apoyo (futuro):** no se admite. El selector solo lista rivales y `validarObjetivoPrioridad()` rechaza aliados. Se deja nota de extensión.

#### Validación en `aplicar()`

Pseudocódigo:

```php
private function validarObjetivoPrioridad(
    Combate $componente,
    AgregadoBatalla $battle,
    Combatiente $actor,
    MovimientoBatalla $movimiento,
): void {
    $id = $actor->id();

    if ($this->esAutodirigidoParaPrioridad($movimiento)) {
        $componente->prioridadesObjetivo[$id] = '';
        return;
    }

    $declarado = (string) ($componente->prioridadesObjetivo[$id] ?? '');
    if ($declarado === '') {
        return; // Automático
    }

    $objetivo = $battle->equipoRivalDe($actor)?->findCombatantById($declarado);
    $legal = $objetivo !== null
        && $objetivo->estaVivo()
        && $battle->objetivosRivalesLegalesPara($actor)
            ->contains(fn (Combatiente $c): bool => $c->id() === $objetivo->id());

    if (! $legal) {
        $componente->prioridadesObjetivo[$id] = '';
    }
}
```

- Si el índice de prioridad no es válido, el bloque existente (`:176-183`) deja `$prioridad = null`; se limpia `$prioridadesObjetivo[$id]` y se cae al comportamiento normal.
- Si el objetivo es inválido, se limpia y `resolver()` usará “Automático”.
- No se guarda el objetivo en el dominio. La resolución es inmediata en la misma petición.

#### Revalidación en `resolver()`

`resolver()` debe leer el objetivo declarado del componente y pasarlo a `objetivoPara()`:

```php
$objetivoDeclarado = (string) ($componente->prioridadesObjetivo[$actor->id()] ?? '');
$objetivo = $this->objetivoPara($battle, $actor, $movimiento, $objetivoDeclarado);

if ($objetivo === null || ! $objetivo->estaVivo()) {
    $actor->romperCombo();
    $componente->log[] = "{$actor->nombre()} no encuentra objetivo para {$movimiento->nombre}.";
    $turnos->consumeAction($actor);
    continue;
}
```

`objetivoPara()` (privado) resuelve en este orden:

```php
private function objetivoPara(
    AgregadoBatalla $battle,
    Combatiente $actor,
    MovimientoBatalla $movimiento,
    string $objetivoDeclaradoId = '',
): ?Combatiente {
    if ($this->esAutodirigidoParaPrioridad($movimiento)) {
        return $actor;
    }

    $forzado = $this->objetivoForzadoDesafio($battle, $actor);
    if ($forzado !== null) {
        return $forzado;
    }

    if ($objetivoDeclaradoId !== '') {
        $objetivo = $battle->equipoRivalDe($actor)?->findCombatantById($objetivoDeclaradoId);
        if ($objetivo !== null
            && $objetivo->estaVivo()
            && $battle->objetivosRivalesLegalesPara($actor)
                ->contains(fn (Combatiente $c): bool => $c->id() === $objetivo->id())) {
            return $objetivo;
        }
    }

    return $battle->elegirObjetivoPara($actor);
}
```

- `objetivoForzadoDesafio()` busca el id forzado solo en `equipoRivalDe($actor)` y exige que esté vivo. Si está muerto, devuelve `null` y se cae al declarado/automático.
- La IA no tiene entrada en `$prioridadesObjetivo`, por lo que su comportamiento no cambia: `$objetivoDeclaradoId = ''` → `elegirObjetivoPara()`.

#### Fallback y revalidación si el objetivo muere durante la resolución

- Si otra prioridad anterior mata al objetivo declarado, `objetivoPara()` detecta `! estaVivo()` y cae a `elegirObjetivoPara()`.
- Si `elegirObjetivoPara()` devuelve `null` o un objetivo muerto, se registra “no encuentra objetivo” y se consume la acción.
- Si el objetivo declarado era legal al declarar pero la vanguardia rival muere después, la fila solo se relaja, no se endurece; el objetivo sigue siendo legal. La revalidación es defensiva.

#### UI y `DESAFIO`

En `preparacion-panel.blade.php`, dentro de la tarjeta de prioridad:

- Si `autodirigido === true`: mostrar “Movimiento autodirigido: no requiere objetivo” y no pintar selector.
- Si `objetivoForzadoRefId !== ''`: mostrar aviso “Desafío: objetivo fijado en {nombre}” y **no pintar selector**. El valor de `$prioridadesObjetivo` se ignora en resolución.
- En caso contrario: pintar `<select wire:model="prioridadesObjetivo.{refId}">` con:
  - `<option value="">Automático</option>`,
  - `@foreach($prioridadElegida['objetivos'] as $objetivo)` con `nombre (posicion)`.

Así la UI refleja la decisión de que `DESAFIO` prevalece y el jugador no pierde la acción.

---

### 3.3. Fallo 3 — Objetivo de Cubrir

#### Flujo de datos

```text
preparacion-panel.blade.php
  ├─ <select wire:change="cambiarPostura(refId, postura)">  (existente)
  └─ <select wire:model="coberturas.{refId}">               (nuevo, solo si postura = cubrir)
        │
        ▼
GestorPreparacionBatalla::cambiarPostura()   (:131-156)
  ├─ setPostura()
  ├─ normalizarCubridores()
  ├─ sincroniza $coberturas[refId]
  └─ guarda
        │
        ▼
GestorPreparacionBatalla::confirmar()/saltar()/autoSaltar()
  → aplicar(...)                              (:158-207)
        ├─ setPostura()
        ├─ si prioridad → ATACAR + limpia objetivoCubiertoId + limpia cobertura
        ├─ si Cubrir → valida $coberturas[id] y setObjetivoCubiertoId()
        └─ normalizarCubridores()             (red de seguridad)
```

#### Inicialización

En `abrir()`:

```php
$componente->coberturas[$id] = $combatiente->postura() === PosturaCombate::CUBRIR
    ? $combatiente->objetivoCubiertoId()
    : '';
```

- Si el objetivo actual es válido, se muestra.
- Si está vacío o es inválido, el selector muestra “Automático”. `normalizarCubridores()` ya habrá asignado un fallback al inicio de ronda (`GestorTurnos.php:71-72`), por lo que en la práctica se muestra el objetivo normalizado.

#### Sincronización al cambiar postura

En `cambiarPostura()` (después de `normalizarCubridores()`):

```php
$componente->posturas[$refId] = $nuevaPostura->value;
$componente->coberturas[$refId] = $nuevaPostura === PosturaCombate::CUBRIR
    ? $combatiente->objetivoCubiertoId()
    : '';

if ($nuevaPostura !== PosturaCombate::ATACAR) {
    $componente->prioridades[$refId] = '';
    $componente->prioridadesObjetivo[$refId] = '';
}
```

- Si la postura deja de ser `CUBRIR`, se limpia el objetivo en dominio vía `normalizarCubridores()` (`EquipoBatalla.php:248-251`) y en el componente.
- Si pasa a `CUBRIR`, se muestra el objetivo normalizado (primer aliado vivo distinto) y el jugador puede cambiarlo.
- Si la postura no es `ATACAR`, también se limpia la prioridad y su objetivo; una prioridad no puede coexistir con Guardia/Cubrir (coherente con `:149-152`).

#### Validación y aplicación en `aplicar()`

Dentro del bucle de vivos del jugador, tras resolver la prioridad:

```php
private function aplicarCoberturaElegida(
    Combate $componente,
    AgregadoBatalla $battle,
    Combatiente $combatiente,
): void {
    $id = $combatiente->id();

    if ($combatiente->postura() !== PosturaCombate::CUBRIR) {
        $combatiente->setObjetivoCubiertoId('');
        return;
    }

    if (! array_key_exists($id, $componente->coberturas)) {
        return; // no gestionado: normalizarCubridores() conserva o reasigna
    }

    $ref = (string) $componente->coberturas[$id];
    if ($ref === '') {
        $combatiente->setObjetivoCubiertoId('');
        return;
    }

    $objetivo = $battle->team1->findCombatantById($ref);
    if ($objetivo === null || $objetivo->id() === $id || ! $objetivo->estaVivo()) {
        $combatiente->setObjetivoCubiertoId('');
        return;
    }

    $combatiente->setObjetivoCubiertoId($objetivo->id());
}
```

Validaciones:
- Mismo equipo: `findCombatantById()` sobre `team1`.
- Vivo: `estaVivo()`.
- Distinto de sí mismo: comparación de ids.

Si el valor es inválido o vacío, se limpia y `normalizarCubridores()` asigna el primer aliado vivo distinto (`EquipoBatalla.php:259-268`). Si el valor es válido, `normalizarCubridores()` lo conserva (`:254-257`).

#### Interacción con prioridad que fuerza `ATACAR`

En `aplicar()`, el bloque de prioridad ya existente (`:185-190`) hace:

```php
$combatiente->setPostura(PosturaCombate::ATACAR);
$combatiente->setObjetivoCubiertoId('');
$combatiente->declararPrioridad($prioridad);
```

Se añade:

```php
$componente->coberturas[$id] = '';
```

Así no queda un objetivo de cobertura obsoleto en el componente. La UI, al haber `wire:model.live` en el movimiento de prioridad, puede ocultar el selector de cobertura y mostrar “La prioridad fuerza Atacar y cancela Cubrir”.

#### Qué pasa al saltar u omitir la preparación

- `saltar()` llama a `aplicar(false)` (`:99`): no declara prioridades del jugador, pero **sí aplica posturas y coberturas**.
- `autoSaltar()` (al jugar sin confirmar) llama a `aplicar(false)` (`:121`): igual.
- Si el jugador nunca abrió la preparación, `$coberturas` se inicializó en `abrir()` desde el dominio, por lo que aplicar es un no-op.
- Si el jugador cambió una cobertura y luego pulsa “Saltar”, la cobertura se aplica, igual que las posturas ya se aplican al cambiar (`cambiarPostura()` guarda inmediatamente). Es consistente.
- Tras `resolver()`, `limpiar()` vacía `$coberturas`; no queda estado obsoleto.

#### Muerte del objetivo cubierto a mitad de ronda

Se mantiene el comportamiento actual:
- `AplicadorPosturaDanio.php:47-58` solo considera cubridores vivos y aliados vivos con `objetivoCubiertoId() === $defensor->id()`.
- Si el objetivo muere, el cubridor deja de redirigir; no se llama a `normalizarCubridores()` hasta `startNewRound()` (`GestorTurnos.php:71-72`), cambio de postura o siguiente preparación.
- Cambiarlo a reasignación inmediata requeriría llamar a `normalizarCubridores()` tras `procesarDebilitamientos()` en `ResolutorAccionBatalla.php:138` y `:200`, con impacto en balance y en la elección del jugador. **Fuera de alcance; requiere decisión y tarea separada.**

---

## 4. Compatibilidad y lo que NO se toca

### 4.1. Sesiones antiguas y serialización

- **No se añaden campos al dominio.** `EstadoTurno`, `TurnoCombatiente` y `SerializadorCombatiente` no cambian. `prioridadDeclarada` sigue siendo solo `?MovimientoBatalla` (`EstadoTurno.php:171-179`, `TurnoCombatiente.php:24-64`).
- **`SESSION_VERSION` no cambia**: sigue en 21 (`BattleSessionService.php:50`), mínimo compatible 9 (`:64`).
- **`BattleSessionService` no cambia.**
- Las sesiones v9-v21 siguen cargando. No hay migración ni re-serialización.
- Los mapas `$prioridadesObjetivo` y `$coberturas` viven solo en el componente Livewire, no en la sesión de batalla.

### 4.2. Snapshots antiguos del componente Livewire

- Las propiedades nuevas tienen default `[]`; Livewire las hidrata con ese valor si el snapshot no las contiene.
- Si un snapshot antiguo tiene `$prioridadesDisponibles` sin las claves nuevas, el Blade debe usar `??`:
  - `$prioridad['autodirigido'] ?? false`
  - `$prioridad['objetivos'] ?? []`
  - `$prioridad['objetivoForzadoRefId'] ?? ''`
  - `$prioridad['objetivoForzadoNombre'] ?? ''`
- Si no hay `objetivos`, el selector solo muestra “Automático” y `resolver()` cae al comportamiento actual. No hay error.

### 4.3. Lo que explícitamente NO se toca

- `bugs.txt` y `.opencode/work/TASK-032/`.
- `tests/` (no se crean, no se ejecutan, no se modifican).
- `composer.json`, `composer.lock`, dependencias.
- `database/migrations/`, modelos Eloquent, `Team`, `TeamController`, `ActualizarPosturasEquipo`, `ConstruirEquipoJugador`.
- `src/Battle/Domain/ValueObjects/EstadoTurno.php`.
- `src/Battle/Domain/Componentes/Combatiente/TurnoCombatiente.php`.
- `src/Battle/Domain/Componentes/Combatiente/SerializadorCombatiente.php`.
- `app/Support/BattleSessionService.php`.
- `src/Battle/Domain/EquipoBatalla.php` (`normalizarCubridores()` se mantiene tal cual).
- `src/Battle/Domain/AI/SelectorAccionIA.php`.
- `src/Battle/Domain/AgregadoBatalla.php::actorAdmiteSoporte()`.
- `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php`.
- `src/Battle/Domain/Danio/AplicadorPosturaDanio.php`.
- `src/Battle/Domain/CombatienteVista.php` (no se añade nombre del cubierto; el selector del panel es suficiente).
- `resources/views/livewire/partials/preparacion-modal.blade.php` (no requiere cambios).
- `resources/views/equipos/**` (no se persiste cobertura desde Equipo).
- Rutas, CSRF, policies, autenticación.

---

## 5. Archivos y orden de implementación por especialista

### 5.1. Backend

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate.php` | Añadir `$prioridadesObjetivo` y `$coberturas` con docblocks y defaults. Ampliar el docblock de `$prioridadesDisponibles`. |
| B2 | `src/Battle/Domain/AgregadoBatalla.php` | Añadir `objetivosRivalesLegalesPara(Combatiente $actor): CombatientesCollection` (lectura pura). Importar `CombatientesCollection`. |
| B3 | `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` | `abrir()`: inicializar mapas, ampliar `prioridadesDisponibles` (`autodirigido`, `objetivos`, `objetivoForzado*`). `cambiarPostura()`: sincronizar `$coberturas` y limpiar objetivo de prioridad. `aplicar()`: validar objetivo de prioridad, aplicar cobertura, limpiar mapas. `limpiar()`: vaciar mapas. Añadir helpers privados. |
| B4 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | `resolver()`: leer objetivo declarado y pasarlo a `objetivoPara()`; guard final de vivo. `objetivoPara()`: autodirigido → actor; `DESAFIO` → forzado; declarado legal → declarado; fallback IA. Importar `ClaveCondicion`. |
| B5 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminar los dos bloques de retaguardia aliada (`previewTarget()` y `selectMove()`). Conservar el resto. |
| B6 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminar el filtro de retaguardia en `refsAliado()` y `tieneVanguardiaViva()` si queda sin uso. |

**Dependencias backend:**
- B3 y B4 dependen de B1 y B2.
- B5 y B6 son independientes de B2-B4; pueden ejecutarse en paralelo.
- B2 no depende de B1.

### 5.2. Frontend

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/partials/preparacion-panel.blade.php` | Selector de objetivo de prioridad (condicional a `autodirigido`/`DESAFIO`), selector de aliado cubierto (condicional a postura `cubrir` y sin prioridad), avisos de ayuda, `??` defensivos para snapshots antiguos, `wire:key`. |

**Dependencia frontend:**
- F1 depende de B1 (propiedades) y B3 (forma de `prioridadesDisponibles` y semántica de `$coberturas`).
- No toca archivos backend.

### 5.3. Orden recomendado global

1. B1 → B2 → B3 → B4 (núcleo de prioridades).
2. B5 + B6 en paralelo con B3/B4 (fallo 1).
3. F1 cuando B1+B3 estén estables.
4. Verificación estática y manual.
5. Revisión independiente (`reviewer` + `security`) antes de cerrar.

**Conflicto de archivos:** ninguno entre backend y frontend. B3 y B4 son archivos distintos; B5/B6 también.

---

## 6. Criterios de aceptación y verificación

### 6.1. Criterios de aceptación

**Fallo 1**
- **CA1:** Con un actor que tiene `swords-dance` y un aliado vivo en retaguardia, la carta del aliado es `selectable` en `player_target` y `player_move`. Evidencia: `refsAliado()` ya no filtra retaguardia.
- **CA2:** `previewTarget()` acepta al aliado en retaguardia y `selectMove()` crea la acción pendiente con `defenderId` = aliado. Evidencia: no quedan los bloques `:64-67` ni `:162-169`.
- **CA3:** `GestorAccionBatalla::resolver()` enruta a `aplicarMovimientoAliado()` y el buff se aplica al aliado elegido (`GestorAccionBatalla.php:186-215`).
- **CA4:** Un actor en vanguardia sigue sin poder seleccionar la retaguardia rival mientras la vanguardia rival esté viva. Evidencia: `SincronizadorVistaBatalla.php:45-52` y `BatallaVistaPresenter.php:198-203` intactos.
- **CA5:** La IA sigue aplicando su restricción de retaguardia aliada. Evidencia: `AgregadoBatalla.php:416-424` y `SelectorAccionIA.php:317-326` intactos.

**Fallo 2**
- **CA6:** En preparación, al elegir un movimiento de prioridad no autodirigido, aparece un selector de objetivo con “Automático” y los rivales vivos legales por fila.
- **CA7:** Elegir un rival y confirmar hace que `resolver()` ataque a ese rival. Evidencia: `$prioridadesObjetivo[actor]` se lee en `resolver()` y se pasa a `objetivoPara()`.
- **CA8:** Dejar “Automático” reproduce el comportamiento actual (`elegirObjetivoPara()`).
- **CA9:** `protect`/`detect`/`endure` no piden objetivo y resuelven sobre el actor.
- **CA10:** Un objetivo ilegal por fila, muerto, aliado o manipulado se limpia en `aplicar()` y no se usa en `resolver()`.
- **CA11:** Si el objetivo declarado muere por una prioridad anterior, la siguiente prioridad revalida y cae al objetivo automático o registra “no encuentra objetivo”; nunca golpea a un muerto.
- **CA12:** Con `DESAFIO` y objetivo forzado vivo, el selector se oculta, se muestra el aviso y el ataque va al forzado.

**Fallo 3**
- **CA13:** Al elegir postura `Cubrir`, aparece un selector de aliado cubierto con “Automático” y los aliados vivos distintos del cubridor.
- **CA14:** Elegir un aliado y confirmar hace que `AplicadorPosturaDanio` reparta el 40 % al cubridor (`AplicadorPosturaDanio.php:47-65`).
- **CA15:** Autoobjetivo, aliado muerto o manipulación se rechazan en `aplicar()`; `normalizarCubridores()` asigna fallback.
- **CA16:** Cambiar de `Cubrir` a `Guardia`/`Atacar` limpia el objetivo en dominio y componente.
- **CA17:** Elegir una prioridad y confirmar fuerza `ATACAR`, limpia `objetivoCubiertoId` y `$coberturas[id]`.
- **CA18:** “Saltar” y “autoSaltar” aplican posturas y coberturas, resuelven prioridades de la IA y limpian los mapas; no queda estado obsoleto.
- **CA19:** Dos cubridores sobre el mismo aliado siguen produciendo 40/40/20; un tercero no redirige (límite existente en `AplicadorPosturaDanio.php:58`).

**Compatibilidad**
- **CA20:** Una batalla iniciada antes del cambio carga sin errores; la preparación abre con mapas vacíos y `prioridadesDisponibles` sin claves nuevas se renderiza con defaults.
- **CA21:** `SESSION_VERSION` sigue en 21; `BattleSessionService` no cambia; no hay migraciones ni cambios de esquema.
- **CA22:** No se modifican tests, dependencias, `bugs.txt` ni `TASK-032`.

### 6.2. Verificación estática

Ejecutar desde `/home/david/pokemon`:

```bash
php -l app/Livewire/Combate.php
php -l src/Battle/Domain/AgregadoBatalla.php
php -l app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php

vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --dirty --format agent
php artisan view:cache
```

Grep de control:

```bash
grep -R "tieneVanguardiaViva" app/Livewire/Presenters/BatallaVistaPresenter.php
grep -R "no está en la vanguardia" app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
grep -R "prioridadesObjetivo" app/Livewire
grep -R "coberturas" app/Livewire
grep -n "SESSION_VERSION" app/Support/BattleSessionService.php
git status --short
git diff --stat
git diff -- tests composer.json composer.lock database/migrations
```

Resultados esperados:
- Sin coincidencias para `tieneVanguardiaViva` en el presenter y para el log de retaguardia.
- `prioridadesObjetivo` aparece en `Combate.php` (prop), `GestorPreparacionBatalla` (init/clear/apply) y `GestorPrioridadesBatalla` (read).
- `coberturas` aparece en `Combate.php` (prop), `GestorPreparacionBatalla` (init/sync/apply/clear).
- `SESSION_VERSION` sigue en 21.
- `git status` no muestra `bugs.txt` ni `TASK-032` modificados por esta tarea.
- Sin diffs en tests, composer o migraciones.

### 6.3. Verificación manual (sin tests)

1. **Fallo 1:** equipo con vanguardia viva y aliado en retaguardia; actor con `swords-dance`; clicar la carta del retaguardia; debe ser seleccionable, mostrar “SOBRE ALIADO” y aplicar +2 Ataque al aliado.
2. **Fallo 1, catálogo:** repetir con `recover`, `sand-tomb`, `nasty-plot`, etc.
3. **Fallo 1, rivales:** un actor en vanguardia no debe poder seleccionar la retaguardia rival mientras la vanguardia rival viva.
4. **Fallo 2, con objetivo:** `quick-attack`; elegir rival legal; confirmar; el ataque va al elegido.
5. **Fallo 2, automático:** dejar “Automático”; comportamiento actual.
6. **Fallo 2, autodirigido:** `protect`/`detect`/`endure`; sin selector y resolución sobre el actor.
7. **Fallo 2, objetivo muerto:** dos prioridades al mismo rival; la primera lo mata; la segunda revalida y no golpea a un muerto.
8. **Fallo 2, `DESAFIO`:** actor con `desafio`; el selector se oculta, aparece el aviso y el ataque va al forzado.
9. **Fallo 3, cobertura:** A en `CUBRIR`, elegir B; atacar a B; A recibe el 40 % y se ve el objetivo elegido.
10. **Fallo 3, inválidos:** autoobjetivo/aliado muerto/manipulación; el servidor rechaza o hace fallback.
11. **Fallo 3, muerte:** B muere; A deja de redirigir; al inicio de la siguiente ronda se reasigna.
12. **Fallo 3, prioridad + Cubrir:** A en `CUBRIR` con prioridad; al confirmar queda `ATACAR` y sin objetivo de Cubrir.
13. **Cambio de postura:** `CUBRIR` → `GUARDIA`/`ATACAR` limpia el objetivo; `GUARDIA`/`ATACAR` con prioridad fuerza `ATACAR`.
14. **Sesión antigua:** cargar una batalla iniciada antes del cambio; la preparación abre sin errores.
15. **IA:** una batalla normal contra IA no cambia su comportamiento; sigue sin apoyar a retaguardia.
16. **Dos cubridores:** mismo objetivo; reparto 40/40/20.
17. **Modal:** los selects funcionan dentro del modal; el backdrop no impide interactuar con ellos.
18. **Livewire:** verificar que `wire:model.live` del movimiento de prioridad actualiza el panel sin perder los valores de objetivo/cobertura.

No se crean ni ejecutan tests.

---

## 7. Riesgos residuales y preguntas para el desarrollador

### 7.1. Riesgos residuales

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Ampliación del contrato de `Combate` a 34 propiedades | Alto (visibilidad) | Aprobación explícita; defaults seguros; no se renombra ni elimina nada. |
| `wire:model.live` es un patrón nuevo en el proyecto | Medio | Verificación manual en el modal; fallback con `$wire.set(..., true)` si falla. |
| Snapshots antiguos sin claves nuevas | Bajo | `??` en Blade; defaults `[]`; fallback en `aplicar()`/`resolver()`. |
| Regla de fila duplicada en `SincronizadorVistaBatalla` y `SelectorAccionIA` | Medio | Nuevo método de dominio para el camino nuevo; se documenta la duplicación preexistente. Opcional: refactor posterior de `SincronizadorVistaBatalla` para consumirlo. |
| `DESAFIO` prevalece sobre la elección del jugador | Medio (producto) | Aprobación explícita; aviso en UI; coherente con el flujo normal. |
| Muerte del objetivo de Cubrir a mitad de ronda | Medio (producto) | Se mantiene el comportamiento actual; se documenta. Cambio inmediato requiere tarea separada. |
| Prioridades de apoyo futuras | Medio | No se enrutan; el selector solo lista rivales; nota de extensión. Si se añade `priority > 0` a un movimiento aliado, habrá que extender `objetivoPara()` y `resolver()` para llamar a `aplicarMovimientoAliado()`. |
| Más de 2 cubridores al mismo objetivo | Bajo | Límite existente en `AplicadorPosturaDanio.php:58`; se documenta. |
| `normalizarCubridores()` sobrescribe una elección inválida | Bajo | Se valida antes de aplicar; solo se reasigna si el objetivo es inválido/muerto. |
| Manipulación de propiedades Livewire | Medio | Validación en `aplicar()` y revalidación en `resolver()`; no se confía en el cliente. |
| Carreras entre pestañas/sesiones | Bajo | Fuera de alcance; la resolución revalida el estado del agregado. |
| `collect()` en Blade para localizar la prioridad elegida | Bajo | Es una búsqueda de presentación; si el equipo prefiere, se puede añadir una estructura derivada en servidor (tercera propiedad), no recomendado. |

### 7.2. Preguntas que requieren aprobación explícita

1. **Contrato Livewire:** ¿se autoriza añadir `$prioridadesObjetivo` y `$coberturas` (34 propiedades/18 métodos)?
2. **`DESAFIO`:** ¿se confirma que el objetivo forzado prevalece sobre la elección de prioridad del jugador, con aviso en UI?
3. **Fallo 1:** ¿se confirma la asimetría jugador/IA (solo el jugador puede apoyar a retaguardia) y se deja la IA para una tarea separada?
4. **Fallo 3:** ¿se confirma que la cobertura se elige solo en la preparación de ronda y no se persiste en Equipo?
5. **Reasignación al morir:** ¿se confirma mantener la reasignación en la siguiente ronda?
6. **Dominio:** ¿se autoriza añadir `AgregadoBatalla::objetivosRivalesLegalesPara()` como lectura pura, o se prefiere un helper en la capa Livewire?
7. **`wire:model.live`:** ¿se autoriza su uso en el select de movimiento de prioridad?
8. **Alcance:** ¿se confirma que no se tocarán `SESSION_VERSION`, `BattleSessionService`, serialización, migraciones, tests ni dependencias?

---

## Anexo — Borrador de ADR

**Título:** Objetivos de preparación de ronda en el componente Livewire

**Estado:** propuesto

**Contexto:**
La preparación de ronda permite posturas y movimientos de prioridad, pero no objetivo. Tres carencias: apoyo aliado bloqueado en retaguardia por una regla copiada del targeting rival; prioridades sin objetivo; Cubrir sin elección de aliado. El contrato de `Combate` está congelado en 32 propiedades/18 métodos (TASK-030/031), no se ejecutan tests y no se quieren cambios de sesión ni de esquema.

**Decisión:**
- Fallo 1: eliminar la restricción de retaguardia solo en el camino del jugador (`BatallaVistaPresenter`, `GestorSeleccionBatalla`); IA intacta.
- Fallo 2: añadir `$prioridadesObjetivo` al componente; selector con “Automático”; validar en `aplicar()` y revalidar en `resolver()`; `DESAFIO` prevalece; autodirigidos ignoran objetivo.
- Fallo 3: añadir `$coberturas` al componente; selector solo en preparación; validar y aplicar en `aplicar()`; `normalizarCubridores()` sigue siendo la red de seguridad.
- Añadir `AgregadoBatalla::objetivosRivalesLegalesPara()` como lectura pura para no duplicar la regla de fila.
- No tocar `SESSION_VERSION`, `BattleSessionService`, serialización de dominio, migraciones ni tests.

**Alternativas:**
- Codificar el objetivo dentro de `$prioridades` (`índice|refId`): más frágil y oculta el dato.
- Persistir el objetivo de prioridad en `EstadoTurno`/`TurnoCombatiente`: más invasivo y no necesario porque la resolución es inmediata.
- Persistir la cobertura en `teams.posturas`: requiere decisión de esquema y migración.
- Alinear la IA con el apoyo a retaguardia: cambia balance; tarea separada.

**Consecuencias:**
- Se amplía el contrato público de `Combate` en 2 propiedades.
- El objetivo de prioridad y la cobertura no sobreviven a la ronda; se eligen de nuevo en la siguiente.
- La IA conserva la restricción de retaguardia aliada (asimetría documentada).
- La cobertura no se persiste en Equipo.
- La reasignación al morir el objetivo cubierto sigue siendo en la siguiente ronda.
- Las prioridades de apoyo futuras requerirán enrutado a `aplicarMovimientoAliado()`.

**Referencias:**
- `app/Livewire/Combate.php`
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php`
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php`
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php`
- `app/Livewire/Presenters/BatallaVistaPresenter.php`
- `src/Battle/Domain/AgregadoBatalla.php`
- `src/Battle/Domain/EquipoBatalla.php`
- `resources/views/livewire/partials/preparacion-panel.blade.php`
