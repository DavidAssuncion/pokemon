# TASK-040 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e3ff13f3effea442MkYmkE9I6m`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# TASK-040 — Análisis: Aguante/Protección en rondas consecutivas

## 1. Resumen ejecutivo

Se confirman las cuatro hipótesis, con dos causas independientes que se potencian:

1. **La IA declara Aguante cada ronda** porque `GestorPrioridadesBatalla::declararIa()` elige el movimiento disponible con mayor `priority` sin comprobar la restricción de rondas consecutivas. Aguante tiene prioridad 4 y siempre gana a Ataque Rápido/Onda Vacío (prioridad 1).
2. **El ejecutor perpetúa el bloqueo** porque `EjecutorAguante::ejecutar()` actualiza `ultimaRondaProteccion` también en la rama bloqueada. Lo mismo hace `EjecutorProteccion`.
3. Como la prioridad consume la acción de la ronda (`consumeAction`) y el PP (`consumirMovimiento`), Riolu queda atrapado: intenta Aguante, se bloquea, avanza el marcador y vuelve a quedar bloqueado la ronda siguiente, sin usar sus otros movimientos.
4. **Protección/Detección comparten el mismo defecto y el mismo contador** (`ultimaRondaProteccion`), por lo que el alcance debe incluir ambos.

La corrección mínima recomendada es: predicado de dominio `puedeUsarProteccion(int $ronda)`, no actualizar el marcador en el intento bloqueado, y filtrar los movimientos de protección restringidos en `declararIa()` y en `SelectorAccionIA`. No hace falta tocar `SESSION_VERSION` ni la serialización.

## 2. Causa raíz por archivo/línea

### 2.1. Selección de la IA sin comprobar la restricción

`app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:28-51`

- `:33-36` recorre todos los movimientos y descarta los de `priority <= 0` y los no disponibles.
- `:38-42` se queda con el de mayor `priority`; en empate, mayor `potencia`.
- `:45-49` declara la prioridad y fuerza postura `ATACAR`.
- **No comprueba `ultimaRondaProteccion` en ningún punto.**

Riolu tiene Aguante con prioridad 4 y Ataque Rápido/Onda Vacío con prioridad 1. Por tanto, Aguante gana siempre y se declara todas las rondas.

Flujo que consume la acción:

- `GestorPreparacionBatalla::aplicar()` llama a `declararIa()` en `GestorPreparacionBatalla.php:229`.
- `confirmar()` resuelve prioridades en `:102`; `saltar()` en `:123`; `autoSaltar()` en `:143`.
- `GestorPrioridadesBatalla::resolver()` aplica el movimiento en `:132-145`. Aunque el ejecutor lo bloquee, el movimiento ya se aplicó como acción.
- `ResolutorAccionBatalla::aplicarMovimiento()` consume la acción y el PP en `ResolutorAccionBatalla.php:130-131`.
- `GestorTurnos::consumeAction()` marca `vecesActuado` en `GestorTurnos.php:159-170`; el combatiente ya no actúa normalmente esa ronda.

Consecuencia: **la IA no tiene fallback**. Si Aguante está restringido, no se elige Ataque Rápido/Onda Vacío ni la acción normal; se pierde la ronda.

### 2.2. El ejecutor actualiza el marcador en el intento bloqueado

`EjecutorAguante.php:21-37`

```php
$ronda = $battle->turnManager()->round();
$ultimaRonda = $atacante->ultimaRondaProteccion();

if ($ultimaRonda > 0 && $ultimaRonda === $ronda - 1) {
    $atacante->setUltimaRondaProteccion($ronda);   // línea 28: avanza el marcador
    $battle->agregarLog("... no puede volver a aguantar en rondas consecutivas");
    return;                                        // línea 31: no aplica la condición
}

$atacante->setUltimaRondaProteccion($ronda);       // línea 34: solo debería ejecutarse aquí
$atacante->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AGUANTE, 1));
```

Secuencia real:

- Ronda N: Aguante válido → `ultimaRondaProteccion = N`.
- Ronda N+1: `ultimaRonda = N = (N+1)-1` → bloqueado; **línea 28** pone `ultimaRondaProteccion = N+1`.
- Ronda N+2: `ultimaRonda = N+1 = (N+2)-1` → bloqueado otra vez; línea 28 pone `N+2`.
- Bucle infinito. Coincide exactamente con el log de rondas 8 y 9.

`EjecutorProteccion.php:21-37` reproduce el mismo patrón.

### 2.3. Contador compartido y serialización

- El campo único es `ultimaRondaProteccion` en `RegistroDanio.php:17`.
- Se deserializa en `:32` y se serializa en `:47`.
- Accesores en `RegistroDanio.php:72-80` y `Combatiente.php:501-509`.
- Se incluye en el payload de sesión en `SerializadorCombatiente.php:56`.

Aguante, Protección y Detección comparten el mismo contador. Es coherente con la mecánica canónica de Pokémon, pero implica que **el bug afecta a los tres**.

### 2.4. Por qué el log dice “riolu” en minúscula

Es cosmético: usa `$atacante->nombre()`. No forma parte del defecto.

## 3. Alcance y opciones de corrección

### 3.1. ¿Solo Aguante o también Protección/Detección?

**Debe incluirse Protección/Detección.** Es el mismo patrón en `EjecutorProteccion.php:27-32` y comparten `ultimaRondaProteccion`. Si solo se corrige Aguante, cualquier IA con Protección/Detección (prioridad 4) queda atrapada exactamente igual.

La restricción **no está centralizada**: está duplicada en los dos ejecutores y no existe predicado de dominio. `MovimientosCombatiente::motivoMovimientoNoDisponible()` solo valida silencio, cargas, usos y objeto de elección; no conoce la restricción de protección.

### 3.2. ¿Afecta a `SelectorAccionIA` o solo a `declararIa`?

Para el caso observado de Riolu, **solo `declararIa`**, porque Aguante (prioridad 4) siempre se declara antes de la acción normal y esta nunca llega.

Pero `SelectorAccionIA` también puede reproducir el problema por otra vía:

- `SelectorAccionIA.php:220-240` genera acciones candidatas para todos los movimientos disponibles.
- `:225` solo comprueba `movimientoDisponible()`.
- `:229-240` añade los autodirigidos (Aguante/Protección) como candidatos.
- `:394-413` ya tiene un desempate para evitar que Aguante gane sistemáticamente por empate, pero no comprueba la restricción.

Si un AI no tiene otro movimiento de prioridad, tras filtrar `declararIa` caería en la acción normal y `SelectorAccionIA` podría volver a elegir Aguante/Protección restringido, desperdiciando la acción (sin el bloqueo permanente, una vez corregido el ejecutor). Por robustez, conviene aplicar el mismo predicado en `generarAccionesCandidatas()`.

### 3.3. ¿Qué pasa si el movimiento elegido está restringido?

Comportamiento actual: se declara igualmente, se aplica, el ejecutor lo bloquea y se consume la acción. No hay fallback.

Comportamiento recomendado: **saltar el movimiento restringido dentro del bucle de `declararIa`**. El propio código ya tiene el fallback natural:

- Si queda otro movimiento de prioridad, se elige el siguiente (Ataque Rápido/Onda Vacío, prioridad 1).
- Si no queda ninguno, `$mejor` permanece `null`, no se declara prioridad, `resolver()` sale y el actor conserva su acción para el flujo normal de `SelectorAccionIA`.

No hace falta añadir lógica nueva de fallback: basta con `continue` al detectar la restricción.

**Matiz:** como Riolu conserva Ataque Rápido/Onda Vacío (prioridad 1), seguirá usando un movimiento de prioridad cada ronda y no usará Golpe Roca/Garra Metal mientras `declararIa` declare cualquier movimiento con `priority > 0`. Es deuda preexistente de diseño de prioridades, fuera del alcance de TASK-040. La corrección elimina el bloqueo, no cambia la política de “usar siempre una prioridad si existe”.

### 3.4. Jugador

- `GestorPreparacionBatalla::abrir()` lista todos los movimientos con `priority > 0` y `movimientoDisponible()`; no filtra por la restricción.
- `GestorPreparacionBatalla::aplicar()` valida índice, `priority > 0` y `movimientoDisponible()`; tampoco comprueba la restricción.
- Por tanto, el jugador **sí puede declarar Aguante manualmente en rondas consecutivas**. El ejecutor es la única barrera y, con el bug actual, también queda atrapado para siempre.

Recomendación: **mantener el ejecutor como barrera y conservar el log** para el jugador. Filtrar la preparación del jugador es una mejora de UX opcional, no necesaria para el bug reportado.

### 3.5. Opciones de corrección

| Opción | Cambios | Ventajas | Inconvenientes |
|---|---|---|---|
| **A. Solo ejecutores** | Quitar `setUltimaRondaProteccion($ronda)` de la rama bloqueada | Corrige el bloqueo permanente | La IA sigue declarando Aguante y desperdicia la mitad de las rondas |
| **B. Solo `declararIa`** | Filtrar Aguante/Protección restringidos | Corrige el síntoma de la IA | El jugador sigue permanentemente bloqueado |
| **C. Predicado + ejecutores + `declararIa`** | Predicado de dominio, ejecutores sin actualización en bloqueo, filtro en `declararIa` | Corrige IA y jugador; elimina duplicación | No cubre la ruta normal de `SelectorAccionIA` |
| **D. C + filtro en `SelectorAccionIA`** (recomendada) | Añade el filtro en `generarAccionesCandidatas()` | Cubre todas las rutas de la IA; consistente | Un archivo más; cambio acotado |
| **E. Centralizar en `movimientoDisponible()`** | Añadir la restricción a `motivoMovimientoNoDisponible()` | Un solo punto | Cambia semántica: oculta Aguante al jugador, elimina el log, afecta a `sinAccionLegal()` y a la lista normal. No recomendada |

## 4. Recomendación concreta

Implementar la **opción D**.

### 4.1. Predicado de dominio

En `RegistroDanio.php`:

```php
public function puedeUsarProteccion(int $ronda): bool
{
    return ! ($this->ultimaRondaProteccion > 0
        && $this->ultimaRondaProteccion >= $ronda - 1);
}
```

En `Combatiente.php`, delegar:

```php
public function puedeUsarProteccion(int $ronda): bool
{
    return $this->registro->puedeUsarProteccion($ronda);
}
```

**`>=` vs `=== $ronda - 1`:**

- `===` es la interpretación literal de “rondas consecutivas”.
- `>=` cubre además el doble uso en la misma ronda con acción extra por KO, que hoy no está bloqueado. Es más robusto y no rompe los tests existentes (Aguante usa rondas 1, 3, 4; Protección 1, 2). Recomendación: `>=`.

### 4.2. Clasificación del movimiento

En `MovimientoBatalla.php`, añadir:

```php
public function esProteccion(): bool
{
    return in_array($this->perfil->claveEfecto, [
        ClaveEfectoMovimiento::PROTECCION,
        ClaveEfectoMovimiento::AGUANTE,
    ], true);
}
```

Hay que importar `ClaveEfectoMovimiento`.

### 4.3. Ejecutores

En `EjecutorAguante.php:25-34` y `EjecutorProteccion.php:25-34`:

- Usar `$atacante->puedeUsarProteccion($ronda)`.
- **Eliminar `setUltimaRondaProteccion($ronda)` de la rama bloqueada.**
- Mantener `setUltimaRondaProteccion($ronda)` solo en la rama válida.
- Mantener el log actual en la rama bloqueada.

Resultado: un intento bloqueado no avanza el marcador; la ronda siguiente ya no es consecutiva respecto al último uso válido.

### 4.4. IA: `declararIa`

En `GestorPrioridadesBatalla.php:28-51`:

```php
$ronda = $battle->turnManager()->round();

foreach ($rival->pokemon()->moves()->all() as $movimiento) {
    if ($movimiento->priority <= 0 || ! $rival->movimientoDisponible($movimiento)) {
        continue;
    }

    if ($movimiento->esProteccion() && ! $rival->puedeUsarProteccion($ronda)) {
        continue;
    }

    // ... selección actual ...
}
```

Esto produce el fallback natural al siguiente movimiento de prioridad o, si no hay ninguno, a la acción normal.

### 4.5. IA: `SelectorAccionIA`

En `generarAccionesCandidatas()` (`:220-240`), después de `movimientoDisponible()` y antes de la rama de autodirigidos:

```php
if ($movimiento->esProteccion() && ! $actor->puedeUsarProteccion($battle->turnManager()->round())) {
    continue;
}
```

No afecta a `agregarAccionesSoporte()` porque Aguante/Protección no están en `CatalogoEfectosAliado::admite()`.

### 4.6. Jugador: preparación (opcional)

No tocar para el mínimo. Si se quisiera simetría, filtrar en `abrir()` y validar en `aplicar()` con el mismo predicado. Trade-off: el jugador deja de ver el intento fallido y el log; se recomienda no hacerlo en esta tarea.

### 4.7. Mensaje

**Mantener** los logs actuales. La IA evitará el intento; el jugador seguirá viendo el aviso si declara Aguante restringido. El ejecutor sigue siendo la última barrera.

### 4.8. Compatibilidad

- **No cambiar `SESSION_VERSION`**.
- No hay cambio de estructura: `ultimaRondaProteccion` ya se serializa.
- Sesiones antiguas con el marcador atascado pueden bloquear una ronda más y se desbloquean solas.
- Sin migraciones.

## 5. Archivos y orden

| Orden | Archivo | Cambio |
|---|---|---|
| 1 | `src/Battle/Domain/Componentes/Combatiente/RegistroDanio.php` | `puedeUsarProteccion(int $ronda): bool` |
| 2 | `src/Battle/Domain/Combatiente.php` | Delegación |
| 3 | `src/Battle/Domain/MovimientoBatalla.php` | `esProteccion(): bool` + import |
| 4 | `src/Battle/Domain/Effects/Movimiento/EjecutorAguante.php` | Predicado; sin actualización en bloqueo |
| 5 | `src/Battle/Domain/Effects/Movimiento/EjecutorProteccion.php` | Ídem |
| 6 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Filtro en `declararIa()` |
| 7 | `src/Battle/Domain/AI/SelectorAccionIA.php` | Filtro en `generarAccionesCandidatas()` |
| 8 (opcional) | `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` | Filtro/validación de preparación del jugador |

**No tocar:** `SESSION_VERSION`, `BattleSessionService`, `MovimientosCombatiente::motivoMovimientoNoDisponible()`, Blade, JS, tests, migraciones, `bugs.txt`, artefactos de TASK-032 a TASK-039 ni cambios de TASK-035.

## 6. Criterios de aceptación

1. **IA sin bloqueo:** si Riolu usa Aguante en N, en N+1 `declararIa()` no declara Aguante; elige otro movimiento de prioridad o deja que la IA actúe normalmente. No aparece el log por la IA.
2. **Ejecutor sin avance en bloqueo:** un intento de Aguante bloqueado no modifica `ultimaRondaProteccion`.
3. **Jugador:** si declara Aguante en N y lo reintenta en N+1, recibe el log y pierde la acción; en N+2 puede volver a usarlo. No queda bloqueado permanentemente.
4. **Protección/Detección:** mismo comportamiento, con su log propio.
5. **Rondas alternas:** N sí, N+1 no, N+2 sí.
6. **`SelectorAccionIA`:** no genera candidatos de protección restringidos.
7. **Sin regresiones de disponibilidad:** `movimientoDisponible()` conserva su semántica.
8. **Compatibilidad:** sesión antigua atascada se desbloquea como máximo una ronda después.
9. **Sin cambios de versión:** `SESSION_VERSION` en 21.
10. **Sin tests nuevos/modificados/ejecutados.**

### Verificación estática y manual

- `php -l` sobre los archivos modificados.
- `vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M`.
- `vendor/bin/pint --dirty --format agent`.
- `grep -n "setUltimaRondaProteccion"` en los dos ejecutores: solo en la rama válida.
- `grep -n "puedeUsarProteccion\|esProteccion"` para confirmar el uso centralizado.
- Manual:
  1. Batalla con Riolu IA; forzar Aguante en N.
  2. N+1: sin bloqueo repetido; usa Ataque Rápido/Onda Vacío o acción normal.
  3. N+2: Aguante válido de nuevo.
  4. Repetir con Protección/Detección.
  5. Jugador: Aguante en N y N+1 → log; N+2 válido.
  6. Sesión antigua atascada: se recupera.
- Tests existentes de Aguante/Protección no se modifican ni ejecutan; por su secuencia (rondas 1, 3, 4 y 1, 2), el cambio no los invalida.

## 7. Riesgos, casos límite y preguntas abiertas

### Riesgos

- **[alto] Corregir solo `declararIa`:** el jugador sigue bloqueado permanentemente. Mitigación: corregir ambos ejecutores.
- **[alto] Corregir solo los ejecutores:** la IA sigue declarando Aguante cada ronda y desperdicia acciones. Mitigación: filtrar en `declararIa`.
- **[medio] Ruta normal de `SelectorAccionIA`:** sin filtro, un AI sin otra prioridad puede seguir eligiendo Aguante restringido como acción normal. Mitigación: filtro en `generarAccionesCandidatas()`.
- **[medio] Doble uso en la misma ronda:** con `===` una acción extra podría repetirlo. Mitigación: `>=`.
- **[bajo] Balance:** la IA usará Aguante en rondas alternas y otra prioridad en las intermedias; es el comportamiento correcto.
- **[bajo] Tests existentes:** no cubren la ronda posterior al fallo; no se ejecutan ni modifican.
- **[bajo] Sesiones antiguas:** bloqueo de una ronda adicional y recuperación automática.
- **[bajo] Contador compartido Aguante/Protección/Detección:** intencional y canónico.

### Casos límite

- Rondas alternas N sí, N+1 no, N+2 sí.
- Protección/Detección comparten campo y ejecutor.
- Portador debilitado: no actúa; al revivir conserva el marcador (marginal).
- Cambio de ronda: `startNewRound()` no limpia el marcador, pero la comparación lo maneja.
- IA vs jugador: IA filtrada; jugador protegido por el ejecutor.
- Prioridad forzada por `DESAFIO`: no aplica a Aguante autodirigido.
- Preparación: el jugador puede declarar Aguante restringido; el ejecutor lo bloquea y registra.
- Acción extra por KO: el predicado con `>=` bloquea el segundo intento en la misma ronda.

### Preguntas abiertas

1. ¿La IA debe saltar al siguiente movimiento de prioridad o dejar que actúe normalmente? Recomendación: fallback natural del código existente.
2. ¿Filtrar la preparación del jugador? Recomendación: no en el mínimo.
3. ¿Bloquear el doble uso en la misma ronda? Recomendación: sí, con `>=`.
4. ¿Se acepta que Riolu siga usando Ataque Rápido/Onda Vacío cada ronda y no Golpe Roca/Garra Metal? Es deuda preexistente de `declararIa`; fuera de alcance.
5. ¿Corregir el nombre en minúscula? Cosmético, fuera de alcance.

---

**Conclusión:** la causa raíz está confirmada en `GestorPrioridadesBatalla::declararIa()` (sin filtro), `EjecutorAguante` y `EjecutorProteccion` (actualización del marcador en el intento bloqueado). La corrección mínima recomendada es el predicado de dominio + ejecutores sin avance en bloqueo + filtro en `declararIa` y `SelectorAccionIA`. No requiere cambios de sesión, serialización, migraciones ni tests.
