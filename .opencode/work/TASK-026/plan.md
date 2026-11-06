# TASK-026 — Plan v1 — 3E + clases reutilizables

- **ID:** TASK-026
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `.opencode/work/TASK-026/architecture.md`, `commit 77ad147`
- **Alcance autorizado por este plan:** 4B, 4A, 3E.1 y 3E.2.

---

## 1. Objetivo

1. **3E.1:** extraer `ResolutorAccionBatalla::aplicarMovimiento` y reutilizarlo en `resolverAccion` y `resolverPrioridades`.
2. **3E.2:** extraer `resolverObjeto` para el uso de objetos.
3. **4A:** unificar `refId → (team, index)` en `ResolutorPosicionCombatiente`.
4. **4B:** eliminar el campo muerto `AnimacionBatalla::tick`.

Sin cambios de contrato público, estado, sesión, vistas, JS ni `render()`.

---

## 2. Archivos

### Crear

- `app/Livewire/Combate/Soporte/DTO/ResultadoAccionBatalla.php`
- `app/Livewire/Combate/Soporte/DTO/PosicionVistaBatalla.php`
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php`
- `app/Livewire/Combate/Soporte/ResolutorPosicionCombatiente.php`

### Modificar

- `app/Livewire/Combate.php`
- `app/Livewire/Combate/Soporte/ColaTurnosBatalla.php`
- `app/Livewire/Combate/Soporte/DTO/AnimacionBatalla.php`
- `app/Livewire/Combate/Soporte/EstadoAnimacionBatalla.php`

### No tocar

- `resources/views/**`, `resources/js/**`, `routes/**`, `app/Http/**`, `app/Support/BattleSessionService.php`, `src/**`, tests, migraciones, config.

---

## 3. 4B — `AnimacionBatalla::tick`

- Eliminar `public int $tick` del DTO.
- `EstadoAnimacionBatalla::paraMovimiento`, `paraObjeto`, `reiniciada` y `crear` pierden `$tickActual`.
- `Combate::setAnimState`/`setAnimStateObjeto` mantienen `$this->animTick++`.
- `resetAnimState` no incrementa, igual que hoy.

**Criterios:** `grep -rn -- "->tick" app src resources` vacío; `animTick` se comporta igual.

---

## 4. 4A — `ResolutorPosicionCombatiente`

- Crear `PosicionVistaBatalla` (`team`, `index`, `aArray()`).
- Crear `ResolutorPosicionCombatiente::localizar(Combatiente, team1, team2): ?PosicionVistaBatalla`.
- `ColaTurnosBatalla` lo recibe por constructor y elimina su `posicion()`.
- `Combate::findPokemonViewData` se elimina; su uso en `nextActor` pasa al resolutor.
- `turnQueue` conserva la forma `list<array{team:int,index:int}>`.

**Criterios:** `findPokemonViewData` no existe; `ColaTurnosBatalla` no tiene `posicion()`; `turnQueue` idéntico.

---

## 5. 3E.1 — Movimiento

- `ResolutorAccionBatalla::aplicarMovimiento(...)` con los 10 parámetros del diseño.
- `resolverAccion` delega el bloque común y fusiona `log`/`eventos`.
- `resolverPrioridades` delega con `grabando=false`, prefijos `PRIORIDAD: `/`RIVAL PRIORIDAD: `, y mantiene `syncViewData` único al final.
- `ResultadoAccionBatalla` con `log` y `eventos`.

**Criterios:**
1. `grep -c "generarLogMovimiento"` en `Combate.php` = 0; en el colaborador = 1.
2. `notifyDamaged`, `dispararDanioRecibido`, `dispararDanioInfligido`, `procesarDebilitamientos` y `otorgarAccionExtra` no aparecen en `Combate.php`.
3. `resolverPrioridades` sin `capturarEstado` ni `registrarEvento`; `syncViewData($battle)` una vez al final.
4. Prefijos literales idénticos.
5. Eventos `damage`/`status`/`effect` con las mismas claves y orden.
6. Secuencia `{tick}.1`, `{tick}.2`, `{tick}.3` para 3 eventos.
7. Prioridades: `eventos === []` siempre.

---

## 6. 3E.2 — Objeto

- `ResolutorAccionBatalla::resolverObjeto(...)`.
- `resolverAccionObjeto` conserva resolución de actor/objetivo y `actorJugadorActual()`; delega la aplicación.
- `app(UsarObjetoEnCombate::class)` y `Auth::id()` permanecen en el componente.

**Criterios:**
1. Fallo: no consume acción, no dispara `finTurno`, no emite evento `item`.
2. Éxito: emite `item` después de `procesarDebilitamientos`.
3. Cierre del componente idéntico en éxito y fallo.

---

## 7. Criterios transversales

1. Contrato público congelado: 32 propiedades y 18 métodos públicos idénticos a `77ad147`.
2. `Combate.php` ≤1.500 líneas.
3. `render()`, vistas, JS, rutas y layout sin cambios.
4. `SESSION_VERSION` y `BattleSessionService` sin cambios.
5. Pint pass; PHPStan ≤34; deptrac ≤100; `php -l` OK; `view:cache` OK; `git diff --check` OK.
6. Revisión independiente APPROVED.

---

## 8. Verificación

- Contrato público por tokenizer/reflexión contra `77ad147`.
- `grep` de criterios 3E.1/3E.2/4A/4B.
- Scripts temporales en `/tmp/opencode/task026`:
  - `ResolutorPosicionCombatiente` con team1/team2/inexistente.
  - `EstadoAnimacionBatalla` sin tick.
  - `ResolutorAccionBatalla` con una batalla mock: movimiento con y sin eventos, y objeto éxito/fallo si es viable.
- `php artisan view:cache`/`view:clear`.
- Pint, PHPStan, deptrac, `php -l`, `git diff --check`.
- Sin tests.

---

## 9. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Deriva de secuencia de eventos | Alto | Secuencia inicial en el componente; incremento solo al emitir |
| Eventos en prioridades | Alto | `grabando=false` literal; criterio 7 |
| Snapshot previo en prioridades | Alto | `$previo` solo si `grabando` |
| Orden de logs de stats | Medio | Mismo orden en el pseudocódigo y revisión |
| Autorización de objetos movida | Medio | `actorJugadorActual()` permanece en el componente |
| `ServicioEjecucionBatalla` cacheado | Medio | Conservar `?? new` en los dos puntos actuales |
| `turnQueue` cambia de forma | Medio | `PosicionVistaBatalla::aArray()` |
| Contrato público | Alto | Diff tokenizado obligatorio |
| PHPStan/deptrac | Medio | No superar 34/100 |

---

## 10. Rollback

- Un commit local de la ola, sin `git push`.
- `git revert` del commit; sin operaciones destructivas.
- `SESSION_VERSION` intacto: las sesiones existentes siguen siendo válidas.
- No hay migraciones ni datos que revertir.

---

## 11. Fuera de alcance

- Restaurar docblocks eliminados en TASK-025.
- Ola 1C (scripts de Equipos/Hábitats).
- Ola 4 (componentes compartidos de modal).
- Cambios en vistas, JS, rutas, controladores, `render()`.
- Tests, migraciones, dependencias, `git push`.

---

## 12. Aprobación solicitada

Aprobar la ola **3E + 4A/4B** de TASK-026:

- 4B: limpieza de `AnimacionBatalla::tick`.
- 4A: `ResolutorPosicionCombatiente`.
- 3E.1: `ResolutorAccionBatalla::aplicarMovimiento`.
- 3E.2: `ResolutorAccionBatalla::resolverObjeto`.
