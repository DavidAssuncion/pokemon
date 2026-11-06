# TASK-026 — Arquitectura

- **Estado:** COMPLETADO
- **Autor:** `architect` (`ses_e4dd51963ffePximcIBRwvYq1l`)
- **Base:** `.opencode/work/TASK-024/architecture.md` §5, `.opencode/work/TASK-025/final-report.md`, commit `77ad147`.

## 1. Decisión

Extraer la aplicación de una acción ya resuelta a `ResolutorAccionBatalla`, que devuelve `ResultadoAccionBatalla` (`log` + `eventos`) y no toca sesión, `pendingAction`, animación ni `syncViewData`. `resolverAccion` y `resolverPrioridades` conservan su papel de orquestadores y llaman al mismo `aplicarMovimiento(...)`. `resolverAccionObjeto` delega en `resolverObjeto(...)`. Se añade `ResolutorPosicionCombatiente` para una única implementación `refId → (team, index)` y se elimina el campo muerto `AnimacionBatalla::tick`.

## 2. Contratos

### `ResolutorAccionBatalla`

```php
final class ResolutorAccionBatalla
{
    public function __construct(private readonly GrabadorEventosBatalla $grabador) {}

    public function aplicarMovimiento(
        AgregadoBatalla $battle,
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        ServicioEjecucionBatalla $servicio,
        string $prefijoMovimiento,
        string $prefijoBase,
        bool $grabando,
        int $tickEvento,
        int $secuenciaEvento,
    ): ResultadoAccionBatalla;

    public function resolverObjeto(
        AgregadoBatalla $battle,
        Combatiente $actor,
        Combatiente $objetivo,
        AccionObjeto $accion,
        UsarObjetoEnCombate $usarObjeto,
        int $usuarioId,
        bool $grabando,
        int $tickEvento,
        int $secuenciaEvento,
    ): ResultadoAccionBatalla;
}
```

- `grabando=false` (prioridades): sin snapshot previo, sin eventos, `eventos === []`.
- `registrarEvento` incrementa la secuencia solo al emitir un evento.
- `resolverObjeto` no consume acción ni dispara fin de turno en fracaso; en éxito emite `item` tras los debilitamientos.
- El colaborador no usa `app()` ni `Auth`; recibe `usuarioId` y `UsarObjetoEnCombate` por parámetro.

### `ResultadoAccionBatalla`

```php
final readonly class ResultadoAccionBatalla
{
    /** @param list<string> $log @param list<array<string, mixed>> $eventos */
    public function __construct(public array $log, public array $eventos) {}
}
```

### `ResolutorPosicionCombatiente`

```php
final class ResolutorPosicionCombatiente
{
    public function localizar(Combatiente $target, array $team1, array $team2): ?PosicionVistaBatalla;
}
```

- `PosicionVistaBatalla` (`team`, `index`, `aArray()`).
- `ColaTurnosBatalla` lo recibe por constructor.
- `Combate::findPokemonViewData` se elimina; su uso pasa al resolutor.

### `AnimacionBatalla`

- Se elimina `tick`; `EstadoAnimacionBatalla` pierde `$tickActual`; `Combate` mantiene `$this->animTick++`.

## 3. Orquestación

- **`resolverAccion`:** conserva lectura de `pendingAction`, despacho a objetos, resolución de actor/objetivo, `movimientoDisponible`, autodirigidos, `setPendingAction(null)`, `resetAnimState`, `syncViewData`, sesión y `nextActor`.
- **`resolverPrioridades`:** conserva guardas, ordenación, `limpiarPrioridad`, `bothTeamsAlive`, `estaVivo`, `movimientoDisponible`, `puedeActuar`, `objetivoParaPrioridad`, logs de guarda y `consumeAction` de salida. Solo el bloque común pasa al colaborador con `grabando=false`; `syncViewData` sigue una vez al final.
- **`resolverAccionObjeto`:** conserva resolución de actor/objetivo y `actorJugadorActual()`; delega la aplicación en el colaborador.

## 4. Orden de subolas

1. **4B — tick:** eliminar campo y parámetros.
2. **4A — posición:** resolutor compartido.
3. **3E.1 — movimiento:** aplicador común y refactor de `resolverAccion`/`resolverPrioridades`.
4. **3E.2 — objeto:** `resolverObjeto`.

`Combate.php` se modifica en las cuatro, por lo que son secuenciales. Cierre con un commit atómico de la ola (desviación documentada del commit por subola).

## 5. Criterios y riesgos

- Contrato público congelado (32 propiedades, 18 métodos).
- Prefijos de log idénticos: `PRIORIDAD: `, `RIVAL PRIORIDAD: `, `RIVAL: `, `''`.
- Orden de eventos `damage → status → effect`; ids `{tick}.{secuencia}`.
- Prioridades sin eventos ni snapshot previo.
- Objeto: fallo sin consumo de acción ni evento; éxito con `item` tras debilitamientos.
- `turnQueue` con la misma forma.
- Sin cambios en vistas, JS, rutas, `render()`, `SESSION_VERSION` ni `BattleSessionService`.
- Sin tests; verificación estática y manual.
