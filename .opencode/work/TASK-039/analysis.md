# TASK-039 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e40092840ffeQuU8wS4eCc5zTf`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# TASK-039 — Análisis: eliminar el botón Esperar y garantizar una acción

## 1. Resumen ejecutivo

Eliminar solo el bloque Blade **no cumple** la petición: en Livewire cualquier método público del componente es invocable desde el cliente (`$wire.pasarTurno()`), aunque no exista botón. La cadena pública es `Combate::pasarTurno()` → `OrquestadorBatalla::pasarTurno()` → `GestorSeleccionBatalla::pasarTurno()`, y el último consume la acción y registra `"{nombre} espera."`.

La decisión recomendada es **eliminar el botón, la clave `mostrarEsperar` y el método público de `Combate`/`Orquestador`**, y **sustituir la espera manual por un fallback automático interno** que solo se dispare cuando el actor autorizado no tenga ningún movimiento seleccionable ni objeto aplicable. Sin ese fallback aparece un **soft-lock real**: todos los botones de movimiento quedan `@disabled` y el jugador no tiene forma de cerrar el turno.

`Retrasar turno` y `Atacar con X` **no cancelan** la acción: solo reordenan la cola; deben mantenerse.

## 2. Causa y alcance del cambio

### Hechos verificados

- El botón está en `resources/views/livewire/partials/turn-bar.blade.php:33-41` y llama a `wire:click="pasarTurno"`.
- `mostrarEsperar` se calcula en `app/Livewire/Presenters/BatallaVistaPresenter.php:303` y **solo lo consume** `turn-bar.blade.php:33`. No hay otro uso en `app/`, `src/` ni `resources/`.
- `faseSeleccion` abarca `player_target`, `player_move` y `player_item_target`; por tanto Esperar también aparece durante la selección de objetivo de objeto.
- Cadena actual:
  - `app/Livewire/Combate.php:149-152` — método público.
  - `app/Livewire/Combate/Soporte/OrquestadorBatalla.php:113-116` — delegación.
  - `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:321-358` — lógica: guardas de fase/procesando/animación, `autoSaltar` de preparación, resolución del actor con `porRefId($componente->actingRefId)`, `consumeAction` y `nextActor`.
- No hay llamadas a `pasarTurno` fuera de esos tres archivos y del Blade. Tampoco en `tests/`.
- Livewire comprueba los métodos públicos del componente antes de ejecutarlos y lanza `MethodNotFoundException` si no existe.

### Contrato público de `Combate`

Actualmente hay 18 métodos públicos en `app/Livewire/Combate.php` (incluyendo `boot`, `mount`, `render`). Al eliminar `pasarTurno()` quedan **17**. El conjunto de acciones Livewire invocables se reduce en una.

## 3. Soft-lock y fallback sin movimientos

### Cómo se deshabilita un movimiento

- `moves-panel.blade.php:34-41` pinta cada movimiento y aplica `@disabled(! $move['disponible'])`.
- `MovesPreviewPresenter.php:223` calcula `disponible` con `movimientoDisponible`.
- Motivos en `MovimientosCombatiente.php:74-102`: `silencio` (solo estado), `cargando`, `sin_usos`, `objeto_eleccion`.

### Escenario de bloqueo

- Si **todos** los movimientos están no disponibles, el jugador no puede pulsar ninguno.
- En `player_target`, el panel lateral muestra objetos; si hay al menos un objeto aplicable, el jugador aún puede actuar.
- Si no hay objetos aplicables, **hoy solo Esperar permite cerrar el turno**. Sin él, el turno queda bloqueado.
- En `player_move` no se muestran objetos, solo movimientos y “Cambiar objetivo”. Si todos están deshabilitados, el jugador debe volver a `player_target` con `cancelTarget` para intentar usar un objeto.
- `cancelTarget` no consume acción ni termina el turno.

### ¿Hay fallback para el jugador?

**No.** El fallback existe únicamente para la IA:

- `SelectorAccionIA.php:98-100` usa `accionFallback` cuando no hay evaluación válida.
- `SelectorAccionIA.php:437-448`: si el Pokémon tiene movimientos, el fallback es un movimiento sintético `Espera`; si no tiene movimientos, usa `Placaje`.
- `GestorAccionBatalla.php:102-109` solo gestiona “sin movimientos disponibles” para la IA.

### ¿Es alcanzable hoy el soft-lock?

- `usosMaximos` vale `0` por defecto y significa usos ilimitados; no se localizaron datos que definan usos limitados.
- Sí son alcanzables `silencio` (Pokémon con solo movimientos de estado), `objeto_eleccion` combinado con el movimiento bloqueado no disponible y `cargando`.
- Un Pokémon con **cero movimientos** también es posible si el mapeador devuelve una colección vacía.

Conclusión: el soft-lock es **posible al menos de forma temporal o defensiva**, y eliminar Esperar sin fallback es riesgoso.

## 4. Otras vías de saltar o cancelar el turno

| Vía | Evidencia | ¿Consume acción / cierra turno? | Decisión |
|---|---|---|---|
| `retrasarTurno` | `GestorSeleccionBatalla.php:250-289`; `EstadoTurno.php:103-113` | No consume; marca `turnoRetrasado` y solo una vez por ronda. El actor sigue pendiente. | Mantener |
| `swapTurno` | `GestorSeleccionBatalla.php:220-248` | No consume; fuerza que el siguiente aliado actúe antes. El actor actual sigue pendiente. | Mantener |
| `cancelTarget` | `GestorSeleccionBatalla.php:211-218` | No consume; solo vuelve a `player_target`. | Mantener |
| `cancelarObjeto` | `GestorObjetosBatalla.php:91-102` | No consume; vuelve a `player_target`. | Mantener |
| `autoSaltar` / preparación | `GestorPreparacionBatalla.php:129-146` | No consume; aplica valores por defecto y resuelve prioridades. | Mantener |
| Objetos | `GestorObjetosBatalla.php:162-189`; `GestorAccionBatalla.php:273-323` | Sí consume y ejecuta una acción. | Mantener |
| Postura `Guardia`/`Cubrir` | `GestorCicloTurnosBatalla.php:129-137`; `GestorSeleccionBatalla.php:99-108` | Consume automáticamente sin atacar; es una acción defensiva, no Esperar. | Decisión de producto |
| Estados que impiden actuar | `GestorCicloTurnosBatalla.php:113-124` | Consumo forzado por el motor. | Mantener |
| Prioridad no disponible/sin objetivo | `GestorPrioridadesBatalla.php:102-128` | Consumo forzado por el motor. | Mantener |
| `nextActor` público | `Combate.php:84-87`; `GestorCicloTurnosBatalla.php:33-178` | No consume por sí mismo; el mismo actor sigue pendiente. | Fuera de alcance |

Observación: **`Guardia`/`Cubrir` permite no atacar**. Si la petición significa “siempre debe atacar”, esta vía también debería revisarse. Si significa “siempre debe ejecutarse una acción”, la postura defensiva es una acción válida.

## 5. Opciones de diseño y recomendación

### Opción 1 — Solo UI

Eliminar el bloque Blade y `mostrarEsperar`, dejando la cadena pública intacta.

- **Ventaja:** mínimo cambio.
- **Inconveniente:** un cliente manipulado puede invocar `$wire.pasarTurno()`; no cumple “no se puede cancelar”.
- **Veredicto:** insuficiente.

### Opción 2 — Eliminar UI y cadena pública, sin fallback

Quitar el bloque Blade, `mostrarEsperar`, `Combate::pasarTurno`, `OrquestadorBatalla::pasarTurno` y `GestorSeleccionBatalla::pasarTurno`.

- **Ventaja:** elimina la superficie de cancelación y el código muerto.
- **Inconveniente:** deja el soft-lock descrito.
- **Veredicto:** no recomendable por sí sola.

### Opción 3 — Recomendada: eliminar UI y cadena pública + fallback automático interno

Además de la Opción 2, añadir un fallback **no invocable desde Livewire** en `GestorCicloTurnosBatalla::avanzar` (o en un colaborador interno) que, cuando el actor autorizado del jugador no tenga ningún movimiento seleccionable **ni objeto aplicable**, consuma la acción y avance, registrando un log claro.

- **Ventaja:** no hay cancelación manual, no hay soft-lock y `pasarTurno` desaparece de la superficie pública.
- **Inconveniente:** requiere detectar disponibilidad de objetos en servidor y decidir el texto del log.
- **Nota:** el fallback debe usar `jugadorAutorizado()` (`ResolutorActorBatalla.php:28-47`), no `actingRefId`, para no reintroducir la debilidad actual de `pasarTurno`.

### Opción 4 — Puente de compatibilidad: método público guardado

Mantener `Combate::pasarTurno` como acción pública, pero que **solo** actúe si el actor autorizado no tiene ninguna acción legal; en caso contrario, no-op con log. Quitar el botón igualmente.

- **Ventaja:** evita errores 500 en pestañas abiertas con HTML antiguo; no permite cancelar si hay acción legal.
- **Inconveniente:** conserva superficie pública y lógica condicional; no es tan limpio como la Opción 3.
- **Uso:** solo como transición de despliegue si importan las pestañas abiertas.

### Opción 5 — Movimiento base garantizado para el jugador

Cuando no haya movimientos, ofrecer un movimiento sintético tipo `Placaje`, replicando `SelectorAccionIA::movimientoBase()`.

- **Ventaja:** siempre hay un ataque.
- **Inconveniente:** cambio de diseño de juego; puede saltarse restricciones de recursos y complica la selección de objetivo.
- **Veredicto:** no adoptar sin decisión explícita de producto.

### Recomendación

**Opción 3**, con **Opción 4** solo si se exige compatibilidad con clientes abiertos durante el despliegue. Mantener `Retrasar turno`, `Atacar con X`, `Cambiar objetivo`, objetos y preparación. No tocar `SESSION_VERSION`.

## 6. Archivos afectados y orden

### Backend

1. `app/Livewire/Presenters/BatallaVistaPresenter.php`
   - Eliminar `'mostrarEsperar' => $faseSeleccion && $sinAnimacion`.
   - Ajustar el docblock/array shape si refleja la clave.
   - No tocar `puedeRetrasar` ni `puedeCambiarTurno`.

2. `app/Livewire/Combate.php`
   - Eliminar `pasarTurno()`.
   - Contrato público: 18 → 17 métodos.

3. `app/Livewire/Combate/Soporte/OrquestadorBatalla.php`
   - Eliminar `pasarTurno()`.

4. `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php`
   - Eliminar `pasarTurno()` o convertirlo en método interno de fallback.
   - Actualizar el docblock de clase que menciona “turno y espera”.

5. `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` (solo Opción 3)
   - Añadir el fallback tras resolver el actor y antes de fijar la fase de selección.
   - Debe comprobar movimientos disponibles y objetos aplicables, consumir acción y llamar a `nextActor`.
   - Posible dependencia: `src/Battle/App/ObtenerOpcionesObjeto.php`.

### Frontend

6. `resources/views/livewire/partials/turn-bar.blade.php`
   - Eliminar el bloque `@if($turnBar['mostrarEsperar']) ... @endif`.
   - Verificar que `Retrasar turno` y `Atacar con X` quedan intactos.

### Orden recomendado

- **Mismo despliegue:** backend (1-5) y frontend (6).
- **Despliegue por fases:** primero frontend (quitar botón), esperar a que las pestañas se refresquen; después backend (quitar método).
- **Si no hay despliegue por fases:** Opción 4 como puente durante una versión.

## 7. Criterios de aceptación y verificación

### Criterios de aceptación

1. En ninguna fase se renderiza el botón “Esperar”.
2. `turnBar` ya no expone `mostrarEsperar` y ningún Blade lo referencia.
3. `Combate` no expone `pasarTurno()` públicamente (o, en la Opción 4, rechaza la llamada si hay acción legal).
4. Una llamada manipulada a `$wire.pasarTurno()` no consume la acción del jugador.
5. `Retrasar turno`, `Atacar con X`, `Cambiar objetivo` y los objetos siguen funcionando.
6. No se registra `"{nombre} espera."` por una acción manual del jugador.
7. Si se implementa la Opción 3: con todos los movimientos no disponibles y sin objetos aplicables, el turno avanza automáticamente y queda un log comprensible; no hay bloqueo.
8. No se modifica `SESSION_VERSION` ni la estructura serializada.
9. No se crean, modifican ni ejecutan tests.

### Verificación estática y manual (sin tests)

- `grep -rn "pasarTurno" app resources` → sin coincidencias (Opción 3) o solo el fallback interno permitido.
- `grep -rn "mostrarEsperar" app resources` → sin coincidencias.
- `grep -rn "Esperar" resources/views/livewire` → sin coincidencia en `turn-bar`.
- `php -l` sobre los PHP modificados.
- `vendor/bin/phpstan analyse --no-progress`.
- `vendor/bin/pint --dirty --format agent`.
- Manual:
  - Combate normal, seleccionar objetivo y movimiento.
  - Comprobar que no aparece “Esperar”.
  - Pulsar “Retrasar turno” y verificar que no consume la acción.
  - Pulsar “Atacar con X” y verificar que el actor actual sigue pendiente.
  - Abrir panel de objetos y usar uno.
  - Provocar silencio con solo movimientos de estado.
  - Provocar objeto de elección con el movimiento bloqueado no disponible.
  - Comprobar una pestaña abierta antes del cambio.
  - Preparación con `Guardia`/`Cubrir`.

## 8. Riesgos, casos límite y preguntas abiertas

### Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| Alto | Soft-lock si se elimina Esperar sin fallback | El jugador queda atrapado sin poder actuar | Opción 3 o, como mínimo, Opción 4 |
| Medio | Pestaña abierta con HTML antiguo invoca `pasarTurno` eliminado | Error 500 / `MethodNotFoundException` | Despliegue por fases o método guardado temporal |
| Medio | Fallback automático silencioso | El jugador no entiende por qué no actuó | Log explícito y texto de estado |
| Medio | `Guardia`/`Cubrir` sigue permitiendo no atacar | Puede incumplir “siempre atacar” | Decisión de producto |
| Bajo | `mostrarEsperar` referenciado en otro sitio | Error de vista | Verificado: solo `turn-bar.blade.php:33` |
| Bajo | `GestorSeleccionBatalla::pasarTurno` usa `actingRefId` público | Manipulación del actor en la propia batalla | Eliminarlo o usar `jugadorAutorizado()` en el fallback |
| Bajo | Estado de objeto/objetivo no limpiado por `pasarTurno` | Datos obsoletos | Al eliminar el método desaparece la ruta |

### Casos límite

- Todos los movimientos `sin_usos`.
- Todos los movimientos de estado con `SILENCIO`/`MOFA`.
- Objeto de elección con el movimiento bloqueado sin usos o cargando.
- Movimientos de carga aún no cargados.
- Colección de movimientos vacía.
- Sin objetos aplicables y sin movimientos disponibles.
- Postura `Guardia`/`Cubrir`.
- Preparación pendiente al intentar actuar.
- Selección de objetivo de objeto.
- Sesión antigua sin `actorActualId`.
- Cliente con HTML antiguo tras el despliegue.

### Preguntas abiertas

1. ¿“Siempre se ejecutará una acción” significa **siempre atacar** o **cualquier acción válida** (objeto, postura defensiva, movimiento)? Determina si `Guardia`/`Cubrir` está en alcance.
2. Si no hay ninguna acción legal, ¿preferimos **saltar automáticamente** con log, **movimiento base garantizado** o **mantener un Esperar guardado** solo para ese caso?
3. ¿Se requiere compatibilidad con pestañas abiertas durante el despliegue? Si sí, Opción 4 como puente.
4. ¿Se autoriza tocar `GestorCicloTurnosBatalla` para el fallback, o se prefiere un colaborador nuevo?
5. ¿Se mantiene `Retrasar turno`? La recomendación es sí: no cancela, solo pospone y está limitado a una vez por ronda.

---

**Conclusión:** la eliminación segura requiere quitar el botón y la cadena pública, y añadir un fallback automático interno cuando no exista ninguna acción legal. Sin esa decisión, eliminar Esperar introduce un soft-lock.
