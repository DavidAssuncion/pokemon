# TASK-040 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e3fec69bdffeba4xIUBDX0At0F`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-040 — Diseño: Aguante/Protección/Detección en rondas consecutivas

## 1. Decisiones de diseño

**D1. Predicado de dominio en `RegistroDanio` + delegación en `Combatiente`.**
`RegistroDanio` ya posee el estado (`ultimaRondaProteccion`, `:17`) y sus accesores (`:72-80`). La regla se coloca junto al estado y `Combatiente` la expone como API de agregado, igual que ya delega `ultimaRondaProteccion()`/`setUltimaRondaProteccion()` (`Combatiente.php:501-509`).

**D2. Clasificación en `MovimientoBatalla::esProteccion()`.**
Se basa en `perfil->claveEfecto` (`MovimientoBatalla.php:29`) y en `ClaveEfectoMovimiento::PROTECCION` / `AGUANTE` (`ClaveEfectoMovimiento.php:74-75`). Es la clasificación que ya usa el motor para enrutar los ejecutores (`ColeccionEjecutoresMovimiento.php:78-79`). Alternativas descartadas: comparar `slug` en cada punto (duplica el mapa y no cubre datos normalizados) y añadir la restricción a `MovimientosCombatiente::motivoMovimientoNoDisponible()` (cambia la semántica de disponibilidad, oculta el movimiento al jugador y elimina el log; opción E del análisis).

**D3. Los ejecutores son la última barrera; no avanzan el marcador en el intento bloqueado.**
`EjecutorAguante.php:27-32` y `EjecutorProteccion.php:27-32` actualizan `ultimaRondaProteccion` en la rama bloqueada y perpetúan el bucle. Se sustituye la comparación local por `puedeUsarProteccion()` y se elimina esa actualización. Se conserva el log y la creación de la condición solo en la rama válida.

**D4. La IA filtra en `declararIa()` con `continue`.**
No se añade lógica de fallback: el bucle ya elige el siguiente movimiento de prioridad o deja `$mejor = null`, con lo que el actor conserva su acción normal.

**D5. La IA filtra también en `SelectorAccionIA::generarAccionesCandidatas()`.**
Cubre la ruta normal y la acción extra. El filtro va después de `movimientoDisponible()` y antes de la rama de autodirigidos. No afecta al soporte porque Aguante/Protección/Detección no están en `CatalogoEfectosAliado::admite()`.

**D6. La preparación del jugador no se toca en el mínimo.**
`GestorPreparacionBatalla::abrir()` y `aplicar()` siguen ofreciendo/validando solo `priority > 0` y `movimientoDisponible()`. El jugador puede declarar el movimiento restringido, pero el ejecutor lo bloquea y registra el aviso. Filtrarlo es una mejora de UX opcional.

**D7. Sin cambios de sesión.**
No se toca `SESSION_VERSION` (21), ni `SerializadorCombatiente` (ya incluye `ultimaRondaProteccion`), ni migraciones. `perfil` ya se serializa y restaura en `MovimientoBatalla`.

### Alternativas evaluadas

| Opción | Veredicto |
|---|---|
| A. Solo ejecutores | Insuficiente: la IA sigue declarando Aguante y desperdiciando rondas. |
| B. Solo `declararIa` | Insuficiente: el jugador sigue atrapado. |
| C. Predicado + ejecutores + `declararIa` | Correcta, pero deja la ruta normal de la IA. |
| **D. C + `SelectorAccionIA` (elegida)** | Cubre todas las rutas de la IA sin rediseñarla. |
| E. Centralizar en `movimientoDisponible()` | Rechazada: cambia semántica, oculta el movimiento al jugador y elimina el log. |

## 2. Predicado y clasificación

### 2.1. `RegistroDanio`

Tras `ultimaRondaProteccion()` (`:72-75`), antes del setter:

```php
public function puedeUsarProteccion(int $ronda): bool
{
    return ! ($this->ultimaRondaProteccion > 0
        && $this->ultimaRondaProteccion >= $ronda - 1);
}
```

Semántica (`ultimaRondaProteccion` es el último uso **válido**, nunca el intento bloqueado desde esta tarea):

| `ultimaRondaProteccion` | `$ronda` | Resultado | Motivo |
|---|---|---|---|
| `0` | cualquiera | `true` | Nunca usó protección |
| `ronda - 1` | N | `false` | Uso en la ronda inmediatamente anterior |
| `ronda` | N | `false` | Doble uso en la misma ronda |
| `<= ronda - 2` | N | `true` | Al menos una ronda completa de separación |
| `> ronda` | N | `false` | Valor imposible en flujo normal; se bloquea por defensa |

**Por qué `>=` y no `===`:**

- `=== $ronda - 1` solo bloquea la ronda anterior; permitiría volver a usar protección en la misma ronda con una acción extra por KO.
- `>= $ronda - 1` cubre ambos casos y tolera el contador monotónico.
- El guard `> 0` es obligatorio: con `ronda = 1` y `ultima = 0`, `0 >= 0` bloquearía el primer uso.

### 2.2. `Combatiente`

Junto a los accesores de protección (`:501-509`):

```php
public function puedeUsarProteccion(int $ronda): bool
{
    return $this->registro->puedeUsarProteccion($ronda);
}
```

### 2.3. `MovimientoBatalla::esProteccion()`

Junto a `esContacto()` (`:93-96`), con import de `ClaveEfectoMovimiento`:

```php
public function esProteccion(): bool
{
    return in_array($this->perfil->claveEfecto, [
        ClaveEfectoMovimiento::PROTECCION,
        ClaveEfectoMovimiento::AGUANTE,
    ], true);
}
```

Comprobaciones:

- `PROTECCION` y `AGUANTE` existen (`ClaveEfectoMovimiento.php:74-75`).
- `effect_key` es `proteccion` para Protección/Detección y `aguante` para Aguante.
- `MovimientoFactory::claveEfecto()` convierte el `effect_key` en enum.
- `Movimiento::aMovimientoBatalla()` conserva el `perfil`.
- `MovimientoBatalla::__unserialize()` restaura el perfil con `PerfilMovimiento::desdeArray()`, que restaura `claveEfecto`.

No se añaden propiedades ni se modifica la serialización.

## 3. Ejecutores y flujo del jugador

### 3.1. `EjecutorAguante`

```php
public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
{
    $atacante = $accion->attacker;
    $ronda = $battle->turnManager()->round();

    if (! $atacante->puedeUsarProteccion($ronda)) {
        $battle->agregarLog("{$atacante->nombre()} no puede volver a aguantar en rondas consecutivas");

        return;
    }

    $atacante->setUltimaRondaProteccion($ronda);
    $atacante->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AGUANTE, 1));
    $battle->agregarLog("{$atacante->nombre()} se prepara para aguantar");
}
```

- **Se elimina:** `$ultimaRonda = ...`, la condición `$ultimaRonda > 0 && $ultimaRonda === $ronda - 1` y el `setUltimaRondaProteccion($ronda)` de la rama bloqueada.
- **Se conserva:** el texto del log, la condición `AGUANTE`, la actualización del marcador y el log de la rama válida.
- **Orden:** en la rama válida se mantiene marcador → condición → log. En la bloqueada solo log, sin tocar el marcador.

### 3.2. `EjecutorProteccion`

Idéntico patrón (`:21-37`), con el log propio:

```php
if (! $atacante->puedeUsarProteccion($ronda)) {
    $battle->agregarLog("{$atacante->nombre()} no puede volver a protegerse en rondas consecutivas");

    return;
}
```

Se conserva `ClaveCondicion::PROTEGIDO` y el log `se protege (-60% daño)`. Cubre Protección y Detección porque ambos comparten `effect_key = proteccion` y el mismo ejecutor.

### 3.3. Flujo del jugador

- **Ronda N:** `ultima = 0` → válido → aplica y `ultima = N`.
- **Ronda N+1:** `ultima = N >= N` → bloqueado → log, `return`, sin actualizar marcador. `ResolutorAccionBatalla` ya consumió acción y PP antes de invocar el ejecutor. El intento fallido sigue costando turno y PP.
- **Ronda N+2:** `ultima = N >= N+1` es falso → válido. Ya no hay bloqueo permanente.
- Si el jugador no reintenta en N+1, en N+2 también es válido.

Resultado canónico: un fallo no regala el siguiente uso, pero tampoco lo bloquea para siempre.

## 4. IA: prioridades y acciones normales

### 4.1. `GestorPrioridadesBatalla::declararIa()`

```php
public function declararIa(AgregadoBatalla $battle): void
{
    $ronda = $battle->turnManager()->round();

    foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
        $mejor = null;
        $puedeProteger = $rival->puedeUsarProteccion($ronda);

        foreach ($rival->pokemon()->moves()->all() as $movimiento) {
            if ($movimiento->priority <= 0 || ! $rival->movimientoDisponible($movimiento)) {
                continue;
            }

            if ($movimiento->esProteccion() && ! $puedeProteger) {
                continue;
            }

            if ($mejor === null
                || $movimiento->priority > $mejor->priority
                || ($movimiento->priority === $mejor->priority && $movimiento->potencia > $mejor->potencia)) {
                $mejor = $movimiento;
            }
        }

        if ($mejor !== null) {
            $rival->setPostura(PosturaCombate::ATACAR);
            $rival->setObjetivoCubiertoId('');
            $rival->declararPrioridad($mejor);
        }
    }
}
```

- `$puedeProteger` se calcula una vez por rival.
- No se añaden imports.
- No se añade lógica de fallback: el `continue` deja que el bucle elija el siguiente movimiento de prioridad.
- Si no queda ninguno, `$mejor = null`, no se declara prioridad y el actor conserva su acción normal.

**Fallback observable en Riolu:** Aguante (prioridad 4) se filtra; el bucle elige Ataque Rápido/Onda Vacío (prioridad 1) y se declara con postura `ATACAR`. No hay log de bloqueo generado por la IA.

### 4.2. `SelectorAccionIA::generarAccionesCandidatas()`

Inserción entre `movimientoDisponible()` (`:225-227`) y la rama autodirigida (`:229-240`):

```php
$ronda = $battle->turnManager()->round();
$puedeProteger = $actor->puedeUsarProteccion($ronda);

foreach ($actor->pokemon()->moves() as $movimiento) {
    // ... instanceof y movimientoDisponible ...

    if ($movimiento->esProteccion() && ! $puedeProteger) {
        continue;
    }

    if ($movimiento->esAutodirigido()) {
        // ... sin cambios ...
    }
}
```

- Debe ir **antes** de `esAutodirigido()`: Protección/Detección/Aguante tienen `target: self` y hoy entrarían por esa rama.
- **Soporte:** no se toca `agregarAccionesSoporte()`; no incluye estos slugs.
- **Acción extra:** la IA vuelve a elegir por `SelectorAccionIA` en el turno normal/extra, por lo que el filtro cubre también el uso extra.
- `elegirObjetivoPara()` y `elegirMejorMovimiento()` de `AgregadoBatalla` pasan por `elegirAccion()`, así que quedan cubiertos.

**Corrección al análisis:** `SelectorAccionIA::elegirMejorMovimiento()` (`:130-152`) es un método legacy que recorre movimientos por su cuenta y solo comprueba `movimientoDisponible()`; no pasa por `generarAccionesCandidatas()`. No tiene llamadas de producción: `AgregadoBatalla::elegirMejorMovimiento()` usa `elegirAccion()`. No se modifica en el mínimo; riesgo residual.

## 5. Compatibilidad y casos límite

### 5.1. Compatibilidad

- **Serialización:** sin cambios. `ultimaRondaProteccion` ya está en `RegistroDanio::serializar()`, en `SerializadorCombatiente::ORDEN` y en `desdeArray()`.
- **`SESSION_VERSION`:** sigue en 21. No hay migración ni cambio de payload.
- **Sesiones antiguas atascadas:** con el bug anterior el marcador pudo quedar en la ronda actual. Con el nuevo predicado pueden bloquear una ronda adicional y se recuperan solas.
- **Sesiones con `perfil` ausente/antiguo:** si un payload muy antiguo no trae `perfil`, `__unserialize()` usa `PerfilMovimiento::porDefecto()` y `claveEfecto` sería `null`. `esProteccion()` no filtraría, pero el ejecutor corregido ya no avanza el marcador: la IA alternaría uso válido/bloqueado en vez de quedar atrapada. Riesgo residual bajo.

### 5.2. Casos límite

| Caso | Comportamiento |
|---|---|
| Rondas alternas N sí, N+1 no, N+2 sí | Correcto por `>=` y por no avanzar en el bloqueo. |
| Protección/Detección | Comparten `effect_key`, ejecutor y contador; mismo comportamiento y log propio. |
| Aguante | Mismo patrón con `ClaveCondicion::AGUANTE`. |
| Acción extra por KO en la misma ronda | El segundo intento queda bloqueado por `>=` y por el filtro del selector. Si es jugador, el ejecutor lo bloquea sin avanzar el marcador. |
| Portador debilitado | No actúa; el marcador no se toca. |
| Portador revivido | `revivir()` no reinicia `ultimaRondaProteccion`; si revive en la misma ronda de un uso previo, el predicado lo bloquea; en la siguiente se libera. Marginal y aceptable. |
| Prioridad forzada por `DESAFIO` | Solo fuerza objetivo, no el movimiento. Protección/Aguante son autodirigidos; sin interacción. |
| Preparación manipulada por el jugador | `aplicar()` solo valida `instanceof`, `priority > 0` y disponibilidad. Puede declarar protección restringida; el ejecutor la bloquea, registra el aviso y consume acción/PP. No hay bypass del límite. |
| IA sin ninguna prioridad tras el filtro | `$mejor = null`; pasa al flujo normal y el selector elige otra acción. Si solo tiene protección, el selector no genera candidatos y cae en `accionFallback()` (“Espera”). No hay bucle; decisión de producto. |
| IA con dos protect (Aguante y Protección) | Ambos comparten contador; si está restringido, se saltan los dos y se elige otra prioridad o la acción normal. |

### 5.3. Tests existentes (solo lectura, no ejecutar)

- `tests/Unit/Battle/MovimientoEfectosTest.php:1436-1478`: Aguante en rondas 1, 3 y 4; Protección en 1 y 2. Con el nuevo predicado las secuencias siguen dando el mismo resultado. No verifica la ronda posterior al fallo.
- `tests/Unit/Battle/SelectorAccionIATest.php:226-263`: usa Aguante con `PerfilMovimiento::porDefecto()` (sin `claveEfecto`), por lo que `esProteccion()` devuelve `false` y el filtro no altera el desempate existente.
- No se crean, modifican ni ejecutan tests.

## 6. Archivos y orden para `backend`

| Orden | Archivo | Cambio | Depende de |
|---|---|---|---|
| 1 | `src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php` | `puedeUsarProteccion(int $ronda): bool` | — |
| 2 | `src/Battle/Domain/Combatiente.php` | Delegación `puedeUsarProteccion()` | 1 |
| 3 | `src/Battle/Domain/MovimientoBatalla.php` | `esProteccion(): bool` + import | — |
| 4 | `src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php` | Predicado; sin actualización en bloqueo | 1, 2 |
| 5 | `src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php` | Ídem | 1, 2 |
| 6 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Filtro en `declararIa()` | 2, 3 |
| 7 | `src/Battle/Domain/AI/SelectorAccionIA.php` | Filtro en `generarAccionesCandidatas()` | 2, 3 |

- **No crear clases nuevas.**
- **No modificar:** `SESSION_VERSION`, `BattleSessionService`, `SerializadorCombatiente`, `MovimientosCombatiente::motivoMovimientoNoDisponible()`, `GestorPreparacionBatalla`, Blade, JS, tests, migraciones, `bugs.txt`, artefactos de TASK-032 a TASK-039 ni cambios de TASK-035.
- El paso opcional de filtrar la preparación del jugador **no se incluye** en el mínimo.

## 7. Criterios de aceptación

1. `RegistroDanio::puedeUsarProteccion(int $ronda): bool` existe y `Combatiente::puedeUsarProteccion()` delega.
2. `MovimientoBatalla::esProteccion()` devuelve `true` para `PROTECCION` y `AGUANTE`, y `false` para el resto.
3. En ambos ejecutores `setUltimaRondaProteccion()` aparece **solo** en la rama válida; la bloqueada solo registra el log.
4. Un intento bloqueado no modifica `ultimaRondaProteccion`.
5. `declararIa()` omite protección restringida con `continue` y elige la siguiente prioridad o deja `$mejor = null`.
6. `generarAccionesCandidatas()` omite protección restringida antes de `esAutodirigido()`.
7. El jugador sigue viendo el log y pierde acción/PP al reintentar en N+1; en N+2 vuelve a funcionar.
8. Protección y Detección se comportan igual que Aguante.
9. `movimientoDisponible()` conserva su semántica.
10. `SESSION_VERSION` sigue en 21 y no hay cambios de serialización, migraciones ni tests.

### Verificación estática

```bash
php -l src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php
php -l src/Battle/Domain/Combatiente.php
php -l src/Battle/Domain/MovimientoBatalla.php
php -l src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php
php -l src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l src/Battle/Domain/AI/SelectorAccionIA.php
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M
vendor/bin/pint --dirty --format agent
```

Greps:

- `grep -n "setUltimaRondaProteccion"` → 2 apariciones, ambas en la rama válida.
- `grep -rn "puedeUsarProteccion\|esProteccion"` → definiciones y usos en los 7 archivos.
- `SESSION_VERSION` → 21.
- `git diff --stat` → solo los 7 archivos.

### Verificación manual

1. Batalla con Riolu IA: forzar Aguante en N.
2. N+1: sin log de bloqueo de la IA; usa Ataque Rápido/Onda Vacío o su acción normal.
3. N+2: Aguante válido.
4. Repetir con Protección y Detección.
5. Jugador: Aguante en N, reintento en N+1 → log, acción y PP consumidos; N+2 válido.
6. Sesión antigua atascada: se recupera como máximo una ronda después.
7. Acción extra por KO: la IA no elige protección restringida; el jugador recibe el log si la declara.

## 8. Riesgos residuales y preguntas

- **[bajo] `perfil` ausente en sesiones muy antiguas:** `esProteccion()` no filtraría; el ejecutor corregido evita el bucle, pero la IA podría alternar uso válido/bloqueado. Mitigación opcional por slug; no recomendada en el mínimo.
- **[bajo] `SelectorAccionIA::elegirMejorMovimiento()` legacy** no pasa por el filtro; sin llamadas de producción.
- **[bajo] IA con solo movimientos de protección:** cae en `accionFallback()` (“Espera”). Preferible al bloqueo perpetuo; decisión de producto.
- **[bajo] Jugador:** sigue viendo la protección restringida en preparación; el intento falla con log.
- **[bajo] Coste del fallo:** el jugador pierde acción y PP al reintentar en N+1; comportamiento actual y canónico.
- **[bajo] Deuda preexistente:** Riolu seguirá usando cada ronda un movimiento de prioridad 1 y no Golpe Roca/Garra Metal mientras exista cualquier prioridad. Fuera de alcance.
- **[cosmético] Nombre en minúscula en el log:** fuera de alcance.

### Preguntas abiertas

1. ¿Se aprueba `>= $ronda - 1` (bloquea también el doble uso en la misma ronda con acción extra) frente a `=== $ronda - 1`?
2. ¿Se confirma no filtrar la preparación del jugador en esta tarea?
3. ¿Se acepta no añadir fallback por `slug` para sesiones antiguas sin `perfil`?
4. ¿Se acepta el fallback “Espera” para una IA que solo tenga protección restringida?
5. ¿Se deja sin tocar `SelectorAccionIA::elegirMejorMovimiento()` legacy?
6. ¿Se confirma que un intento fallido de protección debe seguir consumiendo acción y PP?
