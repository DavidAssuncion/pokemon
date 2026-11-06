# TASK-025 — Plan v1 — Ola 2 del frontend: colaboradores de `Combate`

- **ID:** TASK-025
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `.opencode/work/TASK-024/architecture.md` §5, `commit 0e96b9d`
- **Alcance autorizado por este plan:** **3A-3D** de la Ola 2. **3E** (`ResolutorAccionBatalla`) queda fuera y requiere aprobación separada.

---

## 1. Objetivo

Extraer de `app/Livewire/Combate.php` cuatro responsabilidades de presentación en colaboradores sin estado, sin cambiar:

- propiedades públicas ni métodos `wire`;
- estado de Livewire ni sesión (`SESSION_VERSION` intacto);
- `render()` ni el layout de `/combate`;
- vistas de combate, JS o `resources/js`.

Subolas secuenciales porque comparten `Combate.php`:

| Subola | Colaborador | Riesgo |
|---|---|---|
| 3A | `GrabadorEventosBatalla` + snapshots | Alto |
| 3B | `SincronizadorVistaBatalla` + `VistaBatalla` | Alto |
| 3C | `ColaTurnosBatalla` | Medio |
| 3D | `EstadoAnimacionBatalla` + `AnimacionBatalla` | Medio |

---

## 2. Archivos

### Crear

- `app/Livewire/Combate/Soporte/DTO/SnapshotCombatiente.php`
- `app/Livewire/Combate/Soporte/DTO/SnapshotBatalla.php`
- `app/Livewire/Combate/Soporte/DTO/VistaBatalla.php`
- `app/Livewire/Combate/Soporte/DTO/AnimacionBatalla.php`
- `app/Livewire/Combate/Soporte/GrabadorEventosBatalla.php`
- `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php`
- `app/Livewire/Combate/Soporte/ColaTurnosBatalla.php`
- `app/Livewire/Combate/Soporte/EstadoAnimacionBatalla.php`

### Modificar

- `app/Livewire/Combate.php` (único archivo compartido entre subolas).

### No tocar

- `resources/views/**`, `resources/js/**`, `routes/**`, `app/Http/**`, `app/Support/BattleSessionService.php`, `src/**`, tests, migraciones, configuración.

---

## 3. 3A — `GrabadorEventosBatalla`

### Contratos

```php
final class GrabadorEventosBatalla
{
    public function capturar(AgregadoBatalla $battle): SnapshotBatalla;
    public function registrar(string $tipo, SnapshotBatalla $antes, SnapshotBatalla $despues, int $tick, int $secuencia, array $extra = []): ?array;
    public function eventoFin(SnapshotBatalla $estado, int $tick, int $secuencia): array;
    public function eventoBatallaTerminada(string $texto, bool $victoria, int $tick, int $secuencia): array;
    public function cambiosDeEstadoEnLog(AgregadoBatalla $battle, SnapshotBatalla $antes, string $prefijo = ''): ?string;
}
```

- `SnapshotCombatiente`: hp, defHp, spDefHp, alive, status, statusTurns.
- `SnapshotBatalla`: mapa `refId => SnapshotCombatiente`.
- `capturarEstado()` del componente devuelve `SnapshotBatalla`.
- `registrarEvento()` delega y conserva la secuencia `count($this->eventos) + 1` en el componente.
- `cerrarEventos()` y `endBattle()` usan `eventoFin()`/`eventoBatallaTerminada()`.
- `$eventos`, `$eventoTick`, `$grabandoEventos` permanecen en el componente.
- El payload de `eventos` conserva claves, orden, `id = tick.secuencia`, `cambios`, `muertos`, `estado`, `victoria`, `texto` y el `forzar` eliminado antes de serializar.

---

## 4. 3B — `SincronizadorVistaBatalla`

```php
final class SincronizadorVistaBatalla
{
    public function __construct(private ObtenerOpcionesObjeto $opcionesObjeto) {}
    public function proyectar(AgregadoBatalla $battle, ?Combatiente $actor, ?Combatiente $actorParaObjetos, int $usuarioId): VistaBatalla;
}

final readonly class VistaBatalla
{
    public function __construct(
        public string $weather,
        public array $logNuevo,
        public array $team1,
        public array $team2,
        public array $objetosCombate,
    ) {}
}
```

- `syncViewData()` calcula `$actorParaObjetos = $actor ?? $this->resolveActor($battle)` y aplica la proyección.
- El componente sigue siendo el único que hace `array_merge($this->log, $vista->logNuevo)`.
- `canTarget` solo se aplica si `$actor` fue explícito, igual que hoy.
- `refrescarObjetosCombate()` conserva el fallback a `actingRefId`.
- No se añaden sincronizaciones ni se cambia el orden.

---

## 5. 3C — `ColaTurnosBatalla`

```php
final class ColaTurnosBatalla
{
    public function construir(AgregadoBatalla $battle, string $actorActualRefId, array $team1, array $team2): array;
}
```

Conserva:
- actor actual primero;
- retrasados al final;
- velocidad efectiva descendente;
- Trick Room invierte;
- `usort` estable;
- fallback `['team' => 0, 'index' => 0]`.

`buildTurnQueue()` del componente delega y no cambia su firma pública.

---

## 6. 3D — `EstadoAnimacionBatalla`

```php
final class EstadoAnimacionBatalla
{
    public function paraMovimiento(int $tickActual, Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): AnimacionBatalla;
    public function paraObjeto(int $tickActual, Combatiente $actor, Combatiente $objetivo, string $itemNombre): AnimacionBatalla;
    public function reiniciada(int $tickActual): AnimacionBatalla;
}

final readonly class AnimacionBatalla
{
    public function __construct(
        public int $tick,
        public string $attackerId,
        public string $attackerNombre,
        public string $defenderId,
        public string $defenderNombre,
        public string $moveNombre,
    ) {}
}
```

- `setAnimState()` y `setAnimStateObjeto()` aplican los cinco campos.
- `resetAnimState()` sigue limpiando `selectedTarget*`, `objetivosItem` y `objetoSeleccionado` en el componente; el colaborador solo produce los campos de animación.

---

## 7. Reglas transversales

- Colaboradores `final`, sin estado mutable y sin propiedades públicas.
- Sin interfaces: no hay segunda implementación ni DIP real.
- Sin `app()` dentro de los colaboradores; inyección por constructor o parámetros.
- `GrabadorEventosBatalla`, `ColaTurnosBatalla` y `EstadoAnimacionBatalla` no necesitan constructor.
- `SincronizadorVistaBatalla` recibe `ObtenerOpcionesObjeto`.
- Resolución en `boot()` con `??=`.
- Sin cambios en `render()`, vistas, JS, rutas ni `SESSION_VERSION`.
- No hacer `private` métodos públicos ni cambiar firmas.
- DTOs efímeros: no se guardan en sesión.

---

## 8. Criterios de aceptación

1. **Contrato público congelado:** mismas propiedades públicas y mismos métodos públicos (nombres, parámetros, tipos y visibilidad) antes/después.
2. `Combate.php` ≤1.700 líneas tras 3A-3D.
3. `render()` sin cambios; vistas y JS sin cambios.
4. `SESSION_VERSION` y `BattleSessionService` sin cambios.
5. Payload de `eventos` estructuralmente idéntico: claves, orden, `id`, `cambios`, `muertos`, `estado`, `victoria`, `texto`.
6. Log con el mismo número y orden de líneas.
7. Cola de turnos idéntica en escenarios con Trick Room, retrasados y empates.
8. Campos de animación idénticos.
9. Pint pass; PHPStan ≤34; deptrac ≤100; `php -l` OK; `view:cache` OK.
10. Revisión independiente APPROVED.

---

## 9. Verificación

- **Contrato público:** comparación tokenizada/reflexión de propiedades y métodos públicos contra `0e96b9d`.
- **Extracción literal:** diff de los bloques movidos contra `git show 0e96b9d:app/Livewire/Combate.php`.
- **Colaboradores puros:** scripts temporales en `/tmp/opencode/task025` para `ColaTurnosBatalla` (Trick Room, retrasados, empates) y `EstadoAnimacionBatalla` (movimiento, objeto, reset).
- **Eventos y log:** comparación manual de una batalla real si el entorno lo permite; si no, comparación estructural y revisión del diff.
- **Estática:** Pint, PHPStan, deptrac, `php -l`, `php artisan view:cache`, `git diff --check`.
- **Sin tests:** no se crean, actualizan ni ejecutan.

---

## 10. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Deriva en la numeración de `eventos` | Alto | Secuencia calculada en el componente; colaborador recibe `tick` y `secuencia` |
| Log consumido dos veces | Alto | `syncViewData` único punto de consumo; mismas llamadas y orden |
| Cola de turnos distinta | Medio | Comparador copiado literal; script de escenarios |
| `resetAnimState` limpia de más/menos | Medio | El colaborador solo produce los cinco campos `anim*` |
| Colaboradores no inicializados en requests posteriores | Medio | `boot()` con `??=` |
| `app()` o `Auth` dentro de colaboradores | Medio | `usuarioId` por parámetro; inyección por constructor |
| Cambio accidental de contrato Livewire | Alto | Diff de contrato público obligatorio |
| PHPStan/deptrac | Medio | No superar 34/100 |

---

## 11. Rollback

- Un commit local por subola (3A, 3B, 3C, 3D), sin `git push`.
- `git revert` del commit correspondiente; sin operaciones destructivas.
- Al no cambiar `SESSION_VERSION`, las sesiones existentes siguen siendo válidas.
- No hay migraciones ni datos que revertir.

---

## 12. Fuera de alcance

- **3E:** `ResolutorAccionBatalla`.
- Cambios en `render()`, vistas, JS, rutas o controladores.
- Ola 1C (scripts de Equipos/Hábitats).
- Ola 4 (componentes compartidos).
- Tests, migraciones, dependencias, `git push`.

---

## 13. Aprobación solicitada

Aprobar la **Ola 2 (3A-3D)** de TASK-025:

- 3A: `GrabadorEventosBatalla` + snapshots.
- 3B: `SincronizadorVistaBatalla` + `VistaBatalla`.
- 3C: `ColaTurnosBatalla`.
- 3D: `EstadoAnimacionBatalla` + `AnimacionBatalla`.

**3E** se planificará y aprobará por separado.
