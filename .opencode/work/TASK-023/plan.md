# TASK-023 — Plan v1 — Ola 3: `AgregadoBatalla` + limpieza segura

- **ID:** TASK-023
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `TASK-020/architecture.md` §3.2 y §8, `TASK-020/analysis.md` §1.2, inventario de Fase 6B en `analysis.md`
- **Alcance autorizado por este plan:** solo la **Ola 3**. La Ola 4 (Fase 5, serialización v22 / `SESSION_VERSION`) requiere plan y aprobación propios.

---

## 1. Objetivo

1. **Fase 4:** convertir `AgregadoBatalla` (890 líneas, 39 métodos públicos) en una fachada que delega en procesadores **sin estado**, conservando estado, serialización, clonado, secuencia de ronda, RNG y logs.
2. **Fase 6B (alcance mínimo seguro):** eliminar el código muerto con evidencia de cero consumidores, sin tocar rutas, tests, `Livewire/Combate.php` ni serialización.

Ambos frentes tienen archivos disjuntos y pueden ejecutarse en paralelo.

---

## 2. Frente A — Fase 4: `AgregadoBatalla` como fachada

### 2.1 Situación actual

- 890 líneas, 19 propiedades, 39 métodos públicos, 7 privados.
- Mezcla estado del agregado con motor de rondas, clima, gas, condiciones, invocaciones, daño diferido, IA y log.
- No tiene `__serialize()` propio: se serializa el objeto completo.
- `__wakeup()` rehidrata clima, Trick Room y gas.
- `__clone()` clona equipos, reconstruye `GestorTurnos`/`SujetoBatalla` y anula `selectorAccion`.

### 2.2 Componentes objetivo

Subdirectorio propuesto: `src/Battle/Domain/Componentes/Agregado/` con namespace `Src\Battle\Domain\Componentes\Agregado`.

| Componente | Responsabilidad | Estado |
|---|---|---|
| `GestorClimaTerreno` | Clima, terreno, Trick Room y duración | Sin estado |
| `GestorGasReactivo` | Gas reactivo y sincronización | Sin estado |
| `GestorInvocaciones` | Crear, limitar y retirar invocaciones | Sin estado |
| `ProcesadorDanioDiferido` | Daño diferido, muerte y log | Sin estado |
| `ProcesadorCondicionesRonda` | Condiciones volátiles y daño/curación por ronda | Sin estado |
| `MotorRondas` | Secuencia de ronda, efectos y observadores | Sin estado |
| `EjecutorAccion` | Acción, IA, servicio, log y observers | Sin estado |

### 2.3 Regla de estado (crítica)

- **No se añaden propiedades a `AgregadoBatalla`.** Los componentes se instancian por uso (`new`) o se reciben como parámetro; nunca se guardan como propiedades del agregado.
- Los componentes **no guardan** `AgregadoBatalla`, `Combatiente`, colecciones, closures, PDO, servicios de contenedor, relojes ni generadores como estado propio.
- Los componentes pueden recibir colaboradores inmutables por constructor y usarlos durante la llamada; no deben conservarlos entre llamadas.
- Si una extracción exige mover estado o añadir una propiedad serializable, **se detiene** y se consulta al `architect`. No se fuerza.
- `__serialize()` no se añade. El payload de sesión debe seguir siendo el mismo objeto con las mismas 19 propiedades.

### 2.4 Métodos que deben conservarse

Los **39 métodos públicos** actuales, con las mismas firmas y visibilidad, incluidos:

- `__construct`, `__clone`, `__wakeup`.
- Getters: `turnManager`, `damageChain`, `subject`, `log`, `pendingAction`, `actorActualId`, `weather`, `terreno`, `turnosTerreno`.
- Setters: `setPendingAction`, `setActorActualId`, `setWeather`, `setTerreno`, `setDificultad`, `setRondaPreparada`, `setTrickRoom`.
- Daño diferido: `agregarDanioDiferido`, `procesarDanioDiferido`.
- Gas: `activarGasReactivo`, `sincronizarGasReactivo`.
- Composición: `incorporarCombatiente`, `invocarCombatiente`, `equipoDe`, `equipoRivalDe`.
- Log: `agregarLog`, `limpiarLog`.
- Efectos: `triggerBattleStartEffects`, `triggerRoundStartEffects`, `triggerRoundEndEffects`.
- Debilitamientos: `procesarDebilitamientos`.
- Motor: `ejecutarBatalla`, `elegirObjetivoPara`, `elegirMejorMovimiento`.
- Consultas: `rondaPreparada`, `trickRoomActivo`, `trickRoomTurnos`.

### 2.5 Reglas de comportamiento

- Conservar el orden exacto de ronda: inicio → inicio de ronda → acciones → fin de ronda → debilitamientos → invocaciones → Trick Room/terreno.
- No cambiar el número ni el orden de tiradas RNG.
- No cambiar textos ni orden de logs.
- `procesarDanioDiferido` conserva la fórmula, efectividad, STAB, redondeo y muerte.
- `procesarCondicionesVolatiles` conserva las 15+ ramas y sus efectos.
- `ejecutarAccion` conserva IA, servicio, estado, stats, consumo, memoria IA, log, observers y acción extra.
- `__wakeup()` y `__clone()` conservan su semántica exacta.

### 2.6 Criterios de aceptación

1. `AgregadoBatalla` ≤500 líneas; cada componente ≤300; métodos ≤40.
2. Los 39 métodos públicos siguen existiendo con las mismas firmas.
3. Sin propiedades nuevas en `AgregadoBatalla`; `serialize()` de una batalla equivalente produce el mismo payload (mismas claves y valores).
4. `__wakeup()` y `__clone()` sin cambios de comportamiento.
5. Secuencia de ronda, RNG, logs y efectos idénticos.
6. Sin cambios en `Combatiente`, `ServicioEjecucionBatalla`, `BattleSessionService`, `SESSION_VERSION`, tests, rutas ni migraciones.
7. Pint pass; PHPStan ≤34; deptrac ≤100.
8. Revisión independiente APPROVED.

---

## 3. Frente B — Fase 6B: limpieza segura

### 3.1 Archivos a eliminar

| Archivo | Evidencia |
|---|---|
| `app/Support/AvistamientosPokedex.php` | 0 referencias producción y tests; lógica inline en 3 flujos |
| `app/Support/CargadorPokemonsDerrotados.php` | 0 referencias; carga duplicada inline en 3 flujos |
| `src/Exploraciones/Presentation/DTOObjetoPerdido.php` | 0 referencias |
| `src/Exploraciones/Domain/ValueObjects/BitacoraExploracion.php` | 0 referencias en código |
| `resources/views/reclutados/index.blade.php` | 0 referencias; `/reclutados` redirige a `/equipos` |
| `resources/views/welcome.blade.php` | 0 referencias; `/` sirve `HabitatsController@index` |
| `config/crud.php` | 0 referencias tras la limpieza del CRUD |

### 3.2 Reglas

- Antes de eliminar cada archivo, repetir el `grep` de consumidores en `app`, `src`, `routes`, `config`, `database`, `bootstrap`, `resources` y `tests`.
- Si aparece cualquier referencia, **no se elimina** y se reporta.
- No tocar rutas, `route:list` debe seguir en 110 rutas.
- No tocar `tests/`, `misiones/index.blade.php`, `SelectorMovimientosMeta.php`, `EfectoMoody.php`, `EstadoPendiente.php`, `GeneradorAleatorioPhp.php`, `RelojSistema.php`, `config/ayuda.php`, `config/sendas.php`, `Livewire/Combate.php` ni frontend de combate.
- No cambiar bindings ni providers.

### 3.3 Criterios de aceptación

1. `grep` de los 7 nombres/clases devuelve 0 referencias tras la eliminación.
2. `php artisan route:list --json` conserva 110 rutas y no aparecen errores.
3. No se modifican tests, rutas, `Livewire/Combate.php` ni serialización.
4. Pint pass; PHPStan ≤34; deptrac ≤100.
5. Revisión independiente APPROVED.

---

## 4. Archivos y propiedad

### Frente A

- Modificar: `src/Battle/Domain/AgregadoBatalla.php`.
- Crear: `src/Battle/Domain/Componentes/Agregado/*.php`.
- Solo lectura: `EquipoBatalla`, `GestorTurnos`, `CadenaDanio`, `SujetoBatalla`, `BattleLog`, `SelectorAccionIA`, `CalculadorDañoClima`, `ServicioEjecucionBatalla`, `Combatiente`, `BattleSessionService`.
- No tocar: `Combatiente`, `Componentes/Combatiente/*`, `Ejecucion/*`, `BattleSessionService`, `SESSION_VERSION`, tests, rutas, migraciones.

### Frente B

- Eliminar los 7 archivos listados.
- No tocar ningún otro archivo.

**Regla de concurrencia:** A y B son disjuntos. Si aparece un archivo compartido, se detiene el frente y se coordina.

---

## 5. Orden y dependencias

```text
Ola 3 (este plan)
  A: Fase 4 (AgregadoBatalla) ─┐
  B: Fase 6B (limpieza)       ─┼─ en paralelo
```

- A depende de la fachada de `Combatiente` (Ola 1) y del servicio dividido (Ola 2), ya commiteados.
- B es independiente.
- La Ola 4 (Fase 5: serialización v22 / `SESSION_VERSION`) depende de A y requiere plan y aprobación propios.
- La deuda de frontend (`Livewire/Combate.php`, JS inline, vistas gigantes) requiere plan aparte.

---

## 6. Verificación

- **Estática:** Pint, PHPStan L6, deptrac, `php -l`, `git diff --check`.
- **Búsquedas:** `grep` de `mt_rand`, `now()`, `app()`, `SESSION_VERSION`, imports prohibidos y consumidores de los 7 archivos eliminados.
- **Manual:**
  - Comparar `serialize()` de una batalla antes/después (mismas claves y valores).
  - Comparar la secuencia de `triggerRoundEndEffects`/`ejecutarAccion` contra `8d21625`.
  - Verificar `route:list` antes/después (110 rutas).
- **Sin tests:** no se crean, actualizan ni ejecutan.

---

## 7. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Mover estado sin serializador versionado | Alto | No añadir propiedades; componentes sin estado; `serialize()` comparado |
| Romper `__wakeup`/`__clone` | Alto | Conservar semántica; pruebas manuales de roundtrip |
| Reordenar rondas/RNG/logs | Alto | Extracción literal; comparación contra `8d21625` |
| Componentes que retienen estado | Alto | Instanciación por uso; sin propiedades; revisión |
| Eliminar código con consumidor dinámico | Medio | Repetir `grep` + `route:list`; si hay duda, no eliminar |
| Conflictos A/B | Bajo | Archivos disjuntos |
| PHPStan/deptrac ya rotos | Medio | No superar 34/100 |
| Sobreabstracción | Medio | Componentes solo donde hay lógica real; no crear interfaces sin DIP |

---

## 8. Rollback

- Un commit local por frente, sin `git push`, para revertir de forma selectiva.
- Si se prefiere, consolidar en un commit al cerrar la ola.
- No hay migraciones ni datos que revertir.
- Si un frente falla la revisión, se corrige dentro del mismo frente; no se avanza a la Ola 4.

---

## 9. Fuera de alcance

- Fase 5 (serialización v22 / `SESSION_VERSION`).
- Descomposición de `Livewire/Combate.php`.
- Extracción del JS inline de `combate_page`/`livewire/combate`.
- Eliminación de rutas `toggle-favorito` y `ruta/rivales`.
- `EfectoMoody`, `EstadoPendiente`, `GeneradorAleatorioPhp`, `RelojSistema`, `config/ayuda.php`, `config/sendas.php`.
- `misiones/index.blade.php` y `SelectorMovimientosMeta.php`.
- Vistas gigantes `equipos/index` y `habitats/show`.
- Tests, migraciones, dependencias, `git push`.

---

## 10. Aprobación solicitada

Aprobar la **Ola 3** de TASK-023:

- **Frente A:** Fase 4 — `AgregadoBatalla` como fachada + procesadores sin estado.
- **Frente B:** Fase 6B — eliminación de los 7 archivos muertos listados.

Las olas 4 y la deuda de frontend se planificarán y aprobarán por separado.
