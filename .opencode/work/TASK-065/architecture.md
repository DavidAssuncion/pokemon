# TASK-065 — Arquitectura

- **ID:** TASK-065
- **Estado:** COMPLETADO
- **Arquitecto:** `architect`
- **Fecha:** 2026-11-19
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones. Incluye el delta
  de timing de la curación y las verificaciones cerradas.

## Resultado (resumen)

Ausente deja de ser un toggle evaluado en `onTurnStart` (tarde en Livewire) y pasa a
ser una **máquina de estados anunciada al inicio de ronda** mediante una condición
volátil nueva `AUSENTE`, consumida por `puedeActuar()` en el hueco del actor:

1. La decisión de ausencia ocurre en `onRoundStart`, **antes** de `puedeActuar()` y
   por tanto antes de elegir acción en los tres caminos (Livewire, prioridades y
   batalla automática/exploraciones). **No hace falta reordenar
   `app/Livewire/**`**; se elimina `EfectoTruant::onTurnStart`.
2. El enfado se dispara con el canal rico existente
   `ReaccionaAlDanioRecibido::alRecibirDanio()` (solo daño directo, por golpe,
   idempotente) y **cancela la condición**, con lo que `puedeActuar()` permite actuar
   ese turno.
3. La marca visual es la condición en `condicionesBadge` más dos campos derivados
   nuevos en `CombatienteVista`.
4. El boost es `CambioStat::desdeEtapas(ATAQUE, 1)->aplicadoTemporalEn(...)`
   (factor ×1.30, convención temporal del proyecto), retirado en `onTurnEnd` del
   mismo turno con un método nuevo mínimo `MultiplicadoresStats::retirarTemporal()`.
5. **La curación de 1/16 se ejecuta al perder el turno**, no en `onRoundEnd`, mediante
   la capacidad nueva `ReaccionaAlPerderTurno` despachada desde los tres puntos de
   denegación. Así un rival más lento que ataque después del descanso no golpea antes
   de la curación.
6. `SESSION_VERSION` no sube (21 se mantiene): no cambia el payload de `Combatiente`,
   los efectos se serializan nativamente y las propiedades nuevas tienen default; las
   condiciones ya se serializan.

## Hechos verificados (archivo:línea)

- `src/Battle/Domain/Effects/EfectoTruant.php:11-34` — `$descansar` privado, toggle
  en `onTurnStart`; aplica `aplicarInterrupcion()` y **siempre** invierte.
- `src/Battle/Domain/Effects/InterfazEfecto.php:24-48` — hooks: `onBattleStart`,
  `onRoundStart`, `onRoundEnd`, `onRoundEndFinal`, `onDamageDealt`,
  `onDamageReceived`, `onHealed`, `onFainted`, `onTurnStart`, `onTurnEnd`.
  **No hay hook de turno perdido.**
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:51-66` —
  `triggerRoundStartEffects`: `sincronizarGasReactivo()`, `team->onRoundStart()`,
  luego `effects()->triggerRoundStart()` **solo de vivos**, luego
  `procesarDebilitamientos`.
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:123-128` — `iniciarRonda`:
  `startNewRound()` → `triggerRoundStartEffects()` → log.
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:216-246` —
  `avanzarRonda`: `triggerRoundEndEffects()` → `startNewRound()` →
  `triggerRoundStartEffects()`.
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:95` — `puedeActuar()`
  antes de cualquier selección; `:114-125` consume acción y llama `nextActor()`;
  `:130` comprobación de postura **después**.
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:140-163` — batalla
  automática: `dispararInicioTurno` (`:147`) **antes** de `puedeActuar()` (`:148`);
  la rama de denegación (`:149-155`) **no** dispara `dispararFinTurno`.
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:62-64` —
  `dispararInicioTurno()` **después** de elegir acción (origen del defecto actual);
  `:130-132` `consumeAction` → `dispararFinTurno`.
- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:195-197` (movimiento
  aliado) y `:230-231` (objeto) también disparan `dispararFinTurno`.
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:86-134` — la IA elige en
  `prepararAnimacionIa`, invocada en `avanzar` **después** de `puedeActuar`.
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:144-153` — camino de
  prioridades: `puedeActuar()` propio antes de resolver cada prioridad.
- `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php:143-171` —
  `puedeActuar()`; `:173-208` `veredictoCondicionBloqueante` (`INTERRUPCION`
  `:178-184`, `ATURDIDO`, `RECARGA`, `FLINCH`).
- `src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:61-77` —
  `agregar/quitar`; `:92-104` `aplicarInterrupcion`.
- `src/Battle/Domain/Enums/ClaveCondicion.php:36,90,129` — `INTERRUPCION =
  'interrupcion'`, label y alias PS. `label()` es un `match` exhaustivo (`:77-110`);
  añadir `AUSENTE` obliga a añadir su rama.
- `src/Battle/Domain/Effects/ColeccionEfectos.php:414-425` —
  `dispararDanioRecibidoCompleto` (solo `instanceof ReaccionaAlDanioRecibido`);
  `:477-482` canal simple `onDamageReceived`; `:498-510` inicio/fin de turno.
- `src/Battle/Domain/Ejecucion/ResolutorImpacto.php:245-252` — reacción por golpe
  **solo si `huboDano()`**, con `$battle`.
- `src/Battle/Domain/Ejecucion/AplicadorEfectosPostImpacto.php:145-166` —
  `$accion->defender->dispararDanioRecibidoCompleto(...)`.
- `src/Battle/Domain/ServicioEjecucionBatalla.php:71-89,99-112` — la UI pasa
  `$battle`, por lo que el canal rico está activo en Livewire.
- `src/Battle/Domain/ValueObjects/ResultadoDanio.php:32-40` — `huboDano()` =
  `danoHp > 0 || danoEscudo > 0`.
- `src/Battle/Domain/Danio/AplicadorDanioObjetivo.php:71-87` — sustituto absorbe y
  devuelve `sinDano()` si absorbe todo ⇒ **no hay reacción**.
- `src/Battle/Domain/ValueObjects/CambioStat.php:28-31,60-79` — `desdeEtapas()`;
  `factorTemporal()` = ×1.30 por etapa positiva; `aplicadoTemporalEn()`.
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:25`
  `TURNOS_TEMPORALES = 5`; `:166-179` `aplicarTemporal` multiplica y **reinicia el
  contador compartido a 5**; `:208-223` `avanzarTemporales`; `:225-228`
  `reiniciarTemporales` (borra todo); `:264-270` final = base × temporal; `:306-313`
  serialización `{base, temporales, turnos}`.
- `src/Battle/Domain/Effects/EfectoIra.php:33-47` — modelo de reacción con
  `aplicarTemporal`.
- `src/Battle/Domain/Effects/EfectoCorazaIra.php:31-57` — modelo de reacción única
  con `CambioStat::desdeEtapas(...)->aplicadoTemporalEn(...)`.
- `src/Battle/Domain/Combatiente.php:717-726` `puedeActuar`; `:340-343`
  `aplicarInterrupcion`; `:310-333` API de condiciones (`tieneCondicion`,
  `agregarCondicion`, `quitarCondicion`); `:738-753` `recibirDaño`; `:790-797`
  `curarBarreras`; `:242-290` cargas de movimiento; `:564` `effects()` devuelve
  `ColeccionEfectos`.
- `src/Battle/Domain/BarrerasVidaManager.php:59-76` — `min($max, $actual + $max *
  $porcentaje / 100)` para ambas barreras; **`$porcentaje` en puntos porcentuales**
  (1/16 = `6.25`), sin redondeo (float), tope al máximo.
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:245-260` —
  `curarBarreras($porcentaje, $maxFis, $maxEsp)`.
- `src/Battle/Domain/Effects/EfectoComealmas.php:44-75` — modelo de curación de
  escudos con log.
- `src/Battle/Domain/CombatienteVista.php:40-167` — contrato; `:106-116`
  `condicionesBadge` (filtra `'confusion'`); `:91-93` `stages`, `stagesDetalle`,
  `buffTurns`; **no hay campo de ausencia**.
- `resources/views/livewire/_pokemon-card.blade.php:129-133` — pintado de
  `condicionesBadge`; `:109-120` badges de estado/KO.
- `src/Battle/Domain/Componentes/Combatiente/HabilidadCombatiente.php:66-99` —
  `anular()` **saca** los efectos de habilidad de la colección y los guarda;
  `restaurar()` los devuelve; `:113-143` copiar/intercambiar con `clone`.
- `app/Support/BattleSessionService.php:50` `SESSION_VERSION = 21`; `:64` mínimo
  `9`; `:73` `serialize($battle)`; `:100-104` gate de versión; `:106-114`
  `try/catch`.
- `src/Battle/Domain/Combatiente.php` **no** implementa `__serialize` (búsqueda sin
  coincidencias) ⇒ los efectos se serializan nativamente como objetos PHP.
- `src/Battle/Domain/Effects/EfectoVeleta.php:40` — también usa `onTurnStart`; mismo
  riesgo de orden en Livewire (fuera de alcance).
- `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php:36-52,59-62` —
  proyecta `Combatiente::aArrayVista()` por combatiente sin filtrar claves; solo
  añade `canTarget` (team2) y `debug`. Basta con añadir campos en `CombatienteVista`.
- `src/Shared/Domain/ValueObjects/MapaConteo.php:71-74` — `isEmpty()` existe, pero
  **es el predicado incorrecto** para cargas pendientes: `EstadoMovimientos::
  consumir()` deja la entrada a `0` (`EstadoMovimientos.php:50-54`) y `cargas()`
  devuelve esas entradas (`:69-72`). Hay que comprobar “alguna carga > 0”.
- `src/Battle/Domain/Componentes/Combatiente/DelegacionEfectosCombatiente.php:52-64`
  — `onFainted` se dispara una única vez por muerte.
- `.ai/rules/index.md:7-16` — aplican `ddd-modules.md` (`src/**`: Domain puro,
  español), `arrays-dto-collections.md` (`src/**`, `app/**`, `resources/**`: sin
  arrays como contrato, constantes para valores de negocio) y
  `frontend-minimal-logic.md` (`resources/**`). Consecuencias: nombres en español
  (`alPerderTurno`), constante `6.25`, Blade sin lógica, interfaz de capacidad en vez
  de array.
- `docs/adr/` — existen 0001–0005; **no se crea ADR** porque el AGENTS.md del
  proyecto solo permite documentación si el desarrollador la pide.
- `.opencode/work/TASK-040/architecture.md:9-32` — guardas de Aguante/Protección.
  Corrección: Protección **no anula** el daño, lo reduce al 40 %
  (`ManejadorProteccion.php:17,21-22`), y Aguante deja 1 PS con daño > 0
  (`ManejadorAguante.php:25-31`). Ambas mantienen `huboDano()` verdadero y el
  enfado **sí** se dispara.

## Máquina de estados

### Estado persistido (en `EfectoTruant`, compatible con payload viejo)

| Propiedad | Tipo | Significado |
|---|---|---|
| `$descansar` | `bool` (se conserva el nombre) | “la **próxima** ronda será de descanso”. Default `false`. |
| `$ausenciaAnunciada` | `bool` | “esta ronda ha sido anunciada y aún no he cobrado el descanso”. Default `false`. |
| `$enfadado` | `bool` | “he aplicado el boost temporal y debo retirarlo al terminar mi turno”. Default `false`. |

Estado derivado visible: `AUSENTE ∈ condiciones` ⇔ ventana de anuncio abierta.

### Estados y transiciones

```
ACTIVO
  └─(onRoundStart: $descansar=true) → AUSENCIA_ANUNCIADA   [condición AUSENTE + flag]
AUSENCIA_ANUNCIADA
  ├─(daño directo de movimiento, antes de su hueco) → ENFADADO
  │     quita AUSENTE · +1 ATK temporal · log · flag=false · puede actuar
  └─(llega su hueco) → DESCANSO
        puedeActuar consume AUSENTE · pierde el turno · rompe combo · log
        alPerderTurno: cura 1/16 de ambas barreras · log
DESCANSO
  └─(onRoundEnd: limpieza) → ACTIVO
ENFADADO
  └─(onTurnEnd, tras actuar) → retira boost · programa $descansar=true → ACTIVO
```

| Transición | Punto del código |
|---|---|
| `ACTIVO → AUSENCIA_ANUNCIADA` | `EfectoTruant::onRoundStart()`, invocado por `MotorRondas::triggerRoundStartEffects:60-62`; disparado por `GestorCicloTurnosBatalla::avanzarRonda:227` (manual/IA) o `MotorRondas::iniciarRonda:126` (auto/exploraciones). |
| `AUSENCIA_ANUNCIADA → ENFADADO` | `EfectoTruant::alRecibirDanio()`, invocado por `ColeccionEfectos::dispararDanioRecibidoCompleto:414-425` ← `AplicadorEfectosPostImpacto::dispararReacciones:152` ← `ResolutorImpacto::resolverUnGolpe:250-252` **por golpe**, antes del hueco del portador. |
| `AUSENCIA_ANUNCIADA → DESCANSO` | `EstadosCombatiente::veredictoCondicionBloqueante()` (nueva rama `AUSENTE` al inicio), invocado desde `puedeActuar()` en `GestorCicloTurnosBatalla:95`, `MotorRondas:148` o `GestorPrioridadesBatalla:144`. |
| `DESCANSO → ACTIVO` | `EfectoTruant::onRoundEnd()` (solo limpieza); `alPerderTurno()` ya curó. |
| `ENFADADO → ACTIVO` | `EfectoTruant::onTurnEnd()` ← `ResolutorAccionBatalla:132/197/231` o `MotorRondas:158`. |

### Reglas derivadas

- **Ventana de enfado** = `$ausenciaAnunciada === true` **y**
  `$portador->tieneCondicion(AUSENTE)`. La condición desaparece exactamente en el
  hueco del portador (la rama `AUSENTE` va **primera** en
  `veredictoCondicionBloqueante`), de modo que **daño posterior al descanso no
  reactiva la acción**.
- **Orden de precedencia**: `AUSENTE` antes de `INTERRUPCION/ATURDIDO/RECARGA/FLINCH`.
- **Programación del descanso**: solo tras una acción efectivamente resuelta
  (`onTurnEnd`). Los caminos de denegación no programan descanso.
- **Cargas**: en `onTurnEnd`, si hay cargas pendientes (alguna > 0), **no** programar
  descanso; el turno de liberación de un movimiento de dos turnos no se come.
- **Postura**: `puedeActuar` corre antes que la rama de postura
  (`GestorCicloTurnosBatalla:95` vs `:130`); Ausente prevalece.
- **Primera ronda**: `$descansar` arranca `false` ⇒ ronda 1 actúa, ronda 2 descansa.
- **Tras el enfado**: actúa ese turno y `onTurnEnd` programa `$descansar=true`, por lo
  que descansa la ronda siguiente (ciclo normal de Ausente).

### Pseudocódigo de `EfectoTruant`

```php
final class EfectoTruant implements InterfazEfecto, ReaccionaAlDanioRecibido, ReaccionaAlPerderTurno
{
    use ComportamientosPorDefecto;

    private const float PORCENTAJE_ESCUDOS_DESCANSO = 6.25; // 1/16
    private const int ETAPAS_ENFADO = 1;                    // ×1.30 temporal

    private bool $descansar = false;
    private bool $ausenciaAnunciada = false;
    private bool $enfadado = false;

    public function __construct(private readonly string $clave) {}
    public function obtenerClave(): string;

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);
        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
        $this->ausenciaAnunciada = false;

        if (! $this->descansar || ! $portador->estaVivo()) { return; }

        $this->descansar = false;
        $this->ausenciaAnunciada = true;
        $portador->agregarCondicion(new CondicionVolatil(ClaveCondicion::AUSENTE, 1));
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if (! $this->ausenciaAnunciada
            || ! $portador->tieneCondicion(ClaveCondicion::AUSENTE)
            || ! $resultado->huboDano()
            || ! $portador->estaVivo()) {
            return;
        }

        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
        $this->ausenciaAnunciada = false;
        $this->enfadado = true;

        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, self::ETAPAS_ENFADO);
        $portador->setMultiplicadores(
            $cambio->aplicadoTemporalEn($portador->multiplicadores()),
        );

        $battle->agregarLog(
            "¡{$portador->nombre()} se enfada por el daño y cancela su ausencia! Su Ataque sube.",
        );
    }

    public function alPerderTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $this->ausenciaAnunciada || $portador->tieneCondicion(ClaveCondicion::AUSENTE)) {
            return;
        }

        $this->ausenciaAnunciada = false;

        if (! $portador->estaVivo()) { return; }

        $antes = $portador->defensaHpActual() + $portador->defensaEspHpActual();
        $portador->curarBarreras(self::PORCENTAJE_ESCUDOS_DESCANSO);
        if ($portador->defensaHpActual() + $portador->defensaEspHpActual() > $antes) {
            $battle->agregarLog(
                "{$portador->nombre()} recupera escudos mientras descansa.",
            );
        }
    }

    public function onTurnEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);

        if (! $this->tieneCargasPendientes($portador)) {
            $this->descansar = true;
        }
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);
        $this->ausenciaAnunciada = false;
        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
    }

    public function onFainted(Combatiente $portador): void
    {
        $this->ausenciaAnunciada = false;
        $this->descansar = false;
        $this->enfadado = false;
    }

    public function __clone(): void
    {
        $this->ausenciaAnunciada = false;
        $this->enfadado = false;
        $this->descansar = false;
    }

    private function tieneCargasPendientes(Combatiente $portador): bool
    {
        foreach ($portador->cargasMovimientoPorNombre()->toArray() as $carga) {
            if ($carga > 0) { return true; }
        }

        return false;
    }

    private function retirarEnfadoSiProcede(Combatiente $portador): void
    {
        if (! $this->enfadado) { return; }

        $this->enfadado = false;
        $portador->setMultiplicadores(
            $portador->multiplicadores()->retirarTemporal(
                StatClave::ATAQUE,
                CambioStat::desdeEtapas(StatClave::ATAQUE, self::ETAPAS_ENFADO)->factorTemporal(),
            ),
        );
    }
}
```

**Se elimina** `EfectoTruant::onTurnStart`. No se toca `ResolutorAccionBatalla:63`
porque `EfectoVeleta` sigue dependiendo de `onTurnStart`.

## Contratos

### 1. Condición `AUSENTE`

- `ClaveCondicion::AUSENTE = 'ausente'`, rama `label(): 'ausente'` en el `match`
  exhaustivo. Sin alias PS.
- Se añade con `CondicionVolatil(ClaveCondicion::AUSENTE, 1)`; `turnos` es metadato
  de presentación; la mecánica la gobierna `puedeActuar`.
- **No** se reutiliza `INTERRUPCION`: semántica distinta (INTERRUPCION cancela
  cargas/prioridad; Ausente no).

`EstadosCombatiente::veredictoCondicionBloqueante` (rama nueva, primera):

```php
if ($condiciones->tiene(ClaveCondicion::AUSENTE)) {
    $condiciones->quitar(ClaveCondicion::AUSENTE);
    $recursos->romperCombo();

    return ResultadoAccion::denegada('está ausente y pierde el turno');
}
```

### 2. Capacidad `ReaccionaAlPerderTurno`

```php
namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Efectos que reaccionan cuando su portador pierde la acción del turno.
 * La invocación es idempotente: puede repetirse sin efecto adicional.
 */
interface ReaccionaAlPerderTurno
{
    public function alPerderTurno(Combatiente $portador, AgregadoBatalla $battle): void;
}
```

```php
// src/Battle/Domain/Effects/ColeccionEfectos.php
public function dispararTurnoPerdido(Combatiente $portador, AgregadoBatalla $battle): void
{
    foreach ($this->effects as $e) {
        if ($e instanceof ReaccionaAlPerderTurno) {
            $e->alPerderTurno($portador, $battle);
        }
    }
}
```

`Combatiente::effects()` devuelve `ColeccionEfectos` (`Combatiente.php:564`), por lo
que el despacho vive ahí y no en `EfectosCombatiente`.

### 3. Puntos de despacho de turno perdido

| Camino | Punto | Detalle |
|---|---|---|
| Manual + IA (Livewire) | `GestorCicloTurnosBatalla::avanzar`, entre `:95` y `:96` | `$statusCheck = $actor->puedeActuar(); if (! $statusCheck->esPermitida()) { $actor->effects()->dispararTurnoPerdido($actor, $battle); }` **antes** del bloque `if ($grabando)` para que la curación entre en el snapshot `status_block`. |
| Prioridades | `GestorPrioridadesBatalla::resolver`, `:145-146` | Dentro de `if (! $puedeActuar->esPermitida())`, antes del log y de `consumeAction`. |
| Auto / exploraciones | `MotorRondas::ejecutarTurnosDeRonda`, `:149-150` | Dentro de `if (! $puedeActuar->esPermitida())`, antes del log y de `consumeAction`. |

Son los tres únicos consumidores de producción de `puedeActuar()`. La llamada es una
notificación genérica (“perdí el turno”); la regla de Ausente sigue en `EfectoTruant`.

### 4. Vista

Añadir dos claves derivadas en `CombatienteVista::para` (sin persistencia, sin lógica
en Blade):

```php
'ausente'        => $combatiente->tieneCondicion(ClaveCondicion::AUSENTE),
'ausenteTooltip' => 'Ausente: descansará este turno. Si recibe daño directo antes de su turno, se enfadará, cancelará la ausencia y atacará con +1 Ataque.',
```

Excluir `'ausente'` de `condicionesBadge` (mismo patrón que `'confusion'` en
`CombatienteVista.php:115`).

Blade, `_pokemon-card.blade.php` tras el badge de estado (≈`:117`):

```blade
@if($p['ausente'])
    <span class="badge bg-surface-sunken text-ink-muted" title="{{ $p['ausenteTooltip'] }}">AUSENTE</span>
@endif
```

Sin cálculos, sin autorización, sin consultas.

### 5. Boost temporal de Ataque

- Aplicar: `CambioStat::desdeEtapas(StatClave::ATAQUE, 1)->aplicadoTemporalEn($portador->multiplicadores())`
  ⇒ `MultiplicadoresStats::aplicarTemporal(ATAQUE, 1.30)`.
- **Duración real**: el turno actual. Se retira en `EfectoTruant::onTurnEnd` del mismo
  turno (tras la acción), con limpieza defensiva en `onRoundStart`/`onRoundEnd`.
- API nueva mínima en `MultiplicadoresStats`:

```php
/**
 * Retira un stack temporal concreto dividiendo el factor aplicado; si no
 * quedan temporales, deja el contador a 0. Preserva otros stacks del mismo stat.
 */
public function retirarTemporal(StatClave $stat, float $factor): self
```

  Motivo: la API actual solo permite `avanzarTemporales()` (contador compartido de 5)
  o `reiniciarTemporales()` (borra todo). Sin esto, “1 turno” es inalcanzable sin
  arrastrar buffs ajenos. Si al retirar no quedan temporales, `turnosTemporales` debe
  quedar a 0 para no mostrar `buffTurns=5` residual.
- Efecto colateral documentado: `aplicarTemporal()` reinicia el contador compartido a
  5 para todos los temporales existentes (comportamiento preexistente).
- Visualización: `stagesDetalle` ya deriva de `MultiplicadoresStats::obtenerEtapas()`
  ⇒ mostrará “ATK +30%” y `buffTurns` mientras dure. No se añade badge extra.

### 6. Serialización / compatibilidad

- `EfectoTruant` se serializa como objeto PHP dentro de `Combatiente` (sin
  `__serialize` en `Combatiente`). Las propiedades nuevas (`ausenciaAnunciada`,
  `enfadado`) tienen default ⇒ payloads viejos v9–v21 cargan sin migración y sin
  tocar `SESSION_VERSION` (21) ni `MINIMUM_COMPATIBLE_SESSION_VERSION` (9).
- `$descansar` se mantiene con semántica equivalente (próxima ronda de descanso) para
  no perder el ciclo en batallas en curso.
- Sesiones antiguas pueden llevar un `INTERRUPCION` residual creado por el bug
  anterior; se autoconsume en el siguiente hueco del portador. No requiere bump.
- **Rollback**: revertir el código sin limpiar sesiones que contengan
  `ClaveCondicion::AUSENTE` rompería el `unserialize` del enum. Rollback = revertir
  archivos **y** limpiar claves `battle_*` de sesión (o mantener permanentemente la
  case del enum).

## Flujo de datos (Livewire, camino manual)

1. `avanzar()` detecta fin de ronda o ronda 0 ⇒ `avanzarRonda()`:
   `triggerRoundEndEffects()` → `startNewRound()` → `triggerRoundStartEffects()`.
2. `EfectoTruant::onRoundStart` anuncia `AUSENTE` si tocaba descanso.
   `GestorVistaBatalla::sincronizar` publica el badge.
3. Se abre la preparación (si toca) y el jugador declara posturas/prioridades. El
   badge ya es visible.
4. `getNextActor()` ordena por velocidad. Si el rival es más rápido, se resuelve su
   acción: `calcularYAplicarDano` → `ResolutorImpacto` → `dispararReacciones` →
   `EfectoTruant::alRecibirDanio` ⇒ enfado, se quita `AUSENTE`, boost +1 ATK temporal,
   log.
5. Cuando llega el hueco del portador: `puedeActuar()`.
   - Enfadado: `AUSENTE` ya no está ⇒ permitida ⇒ selección de acción ⇒ `onTurnEnd`
     retira el boost y programa descanso para la ronda siguiente.
   - Sin enfado: rama `AUSENTE` ⇒ `denegada('está ausente y pierde el turno')` ⇒
     `dispararTurnoPerdido` ⇒ cura 1/16 ⇒ `consumeAction` + `nextActor`.
6. `triggerRoundEndEffects` ⇒ `EfectoTruant::onRoundEnd`: limpieza de condición y flag.
7. `avanzarMultiplicadoresTemporales()` al final de ronda (boost ya retirado, sin
   arrastre).

## Flujo de datos (batalla automática / exploraciones)

Idéntico salvo que el bucle es `MotorRondas::ejecutarTurnosDeRonda:140-163`: ronda
(`iniciarRonda`) → por actor `dispararInicioTurno` → `puedeActuar`; el daño del
atacante anterior dispara el enfado antes del hueco del portador. El despacho de
`alPerderTurno` se añade en la rama de denegación.

## Archivos y orden de cambios

| # | Responsable | Archivo | Cambio |
|---|---|---|---|
| 1 | backend | `src/Battle/Domain/Enums/ClaveCondicion.php` | `case AUSENTE = 'ausente'` + rama en `label()` |
| 2 | backend | `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php` | Rama `AUSENTE` primera en `veredictoCondicionBloqueante` |
| 3 | backend | `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php` | `retirarTemporal(StatClave, float): self` |
| 4 | backend | `src/Battle/Domain/Effects/Capacidades/ReaccionaAlPerderTurno.php` | **Nuevo**; interfaz de capacidad |
| 5 | backend | `src/Battle/Domain/Effects/ColeccionEfectos.php` | `dispararTurnoPerdido()` |
| 6 | backend | `src/Battle/Domain/Effects/EfectoTruant.php` | Rediseño completo; implementa `ReaccionaAlDanioRecibido` y `ReaccionaAlPerderTurno`; elimina `onTurnStart`; `onFainted`, `__clone`, helper de cargas |
| 7 | backend | `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` | 1 llamada a `dispararTurnoPerdido` entre `:95` y `:96` |
| 8 | backend | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | 1 llamada en `:145-146` |
| 9 | backend | `src/Battle/Domain/Componentes/Agregado/MotorRondas.php` | 1 llamada en `:149-150` |
| 10 | backend | `src/Battle/Domain/CombatienteVista.php` | `ausente`, `ausenteTooltip`; filtrar `'ausente'` de `condicionesBadge` |
| 11 | frontend | `resources/views/livewire/_pokemon-card.blade.php` | Badge dedicado + tooltip (≈4 líneas) |
| 12 | backend | `storage/data/abilities_descripciones.json` | Nueva descripción de `ausente` (coordinar con TASK-061; solo esa clave) |

**Orden:** 1 → 2 → 3 → 4 → 5 → 6 → 7/8/9 → 10 → 11 → 12.
**Sin cambios:** `ResolutorAccionBatalla.php`, `ResolutorImpacto.php`,
`AplicadorEfectosPostImpacto.php`, `Combatiente.php`, `InterfazEfecto.php`,
`ComportamientosPorDefecto.php`, `database/**`, `app/Support/BattleSessionService.php`,
dependencias, ADR.
**Conflicto TASK-061:** `storage/data/abilities_descripciones.json:26` ya está
modificado sin commitear; el cambio debe aplicarse sobre el estado actual y **no**
revertir TASK-061. `AbilitySeeder` no se toca.

## Verificación requerida

### Análisis estático

- `php -l` sobre los archivos PHP modificados.
- `composer analyse` (o PHPStan dirigido si el global sigue con deuda preexistente de
  TASK-061); `composer arch`; `composer lint`.
- `grep -rn "AUSENTE\|ausente" src app resources` para confirmar que la regla no se
  duplica fuera del dominio.
- `grep -rn "dispararTurnoPerdido" src app` — tres llamadas.
- `grep -rn "onTurnStart" src/Battle/Domain/Effects` — solo `EfectoVeleta` y el
  trait, sin `EfectoTruant`.
- `grep -rn "otorgarAccionExtra"` no aplica a esta tarea.

### Criterios verificables para el reviewer

1. **Orden**: con Slaking anunciado y rival más rápido, el enfado se resuelve en el
   `commit` del rival y el jugador ve el diálogo de selección para Slaking; con rival
   más lento, Slaking pierde el turno sin diálogo. Ningún método de `app/Livewire/**`
   contiene la regla de Ausente.
2. **Badge**: `AUSENTE` visible desde el inicio de la ronda de descanso; desaparece
   tras el hueco del portador o al enfadarse; tooltip correcto.
3. **Curación**: ronda de descanso sin daño ⇒ ambas barreras suben exactamente
   `max/16` (con tope); a máximo ⇒ sin cambio ni log; la curación ocurre en el hueco
   del portador, antes de un atacante más lento.
4. **Curación no aplica** si hubo enfado.
5. **Boost**: `ATK` ×1.30 visible en `stagesDetalle` durante el turno enfadado;
   desaparece en el turno siguiente sin `buffTurns=5` residual.
6. **Daño posterior al descanso**: no devuelve el turno.
7. **Daño indirecto** (clima/estado/retroceso): no enfada, no cancela la ausencia;
   la curación se aplica al perder el turno.
8. **Multigolpe**: solo un enfado, un boost.
9. **Multiobjetivo**: cada Slaking objetivo gestiona su propia condición.
10. **Sustituto absorbiendo todo**: sin enfado.
11. **Protección/Aguante**: el daño reducido sigue contando (`huboDano()`), el enfado
    se dispara.
12. **Gas Reactivo**: anular antes de la ronda ⇒ sin anuncio; anular a mitad ⇒
    residual aceptado de un turno bloqueado sin curación.
13. **Cargas**: tras un movimiento de dos turnos, el descanso se programa tras la
    liberación, no tras la carga.
14. **Sesión**: batalla en curso con `EfectoTruant` antiguo carga sin error
    (`SESSION_VERSION` 21).

Sin tests: no se crean, no se modifican ni se ejecutan.

## Decisiones y alternativas descartadas

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| Anuncio en `onRoundStart` | Mantener `onTurnStart` y reordenar Livewire | Reordenar obliga a mover `dispararInicioTurno` antes de la selección en 3 sitios y afecta a `EfectoVeleta`; `onRoundStart` ya corre antes y da la ventana real “antes de su turno”. |
| Condición `AUSENTE` nueva | Reutilizar `INTERRUPCION` | Semántica distinta y etiqueta equivocada. |
| `AUSENTE` como estado + `puedeActuar` | Estado privado del efecto + hook nuevo `onTurnSkipped` | La capacidad `ReaccionaAlPerderTurno` permite curar en el momento exacto sin acoplar la regla a los callers. |
| Capacidad `ReaccionaAlPerderTurno` | Ampliar `InterfazEfecto` con `onTurnSkipped` | Habría que tocar `ComportamientosPorDefecto` y ~40 implementaciones; la capacidad es el patrón existente. |
| Curación en `alPerderTurno` | Curación en `onRoundEnd` | `onRoundEnd` es demasiado tarde: un rival más lento golpearía antes de la curación. |
| Boost con `aplicarTemporal` + `retirarTemporal` | Boost de 5 rondas sin API nueva | Incumple “1 turno”. |
| Boost con capa base +1/−1 | Temporal exacto | La etapa base no es reversible de forma segura con topes y contamina `stages`. |
| Sin bump de `SESSION_VERSION` | Subir a 22 | No cambia el payload de `Combatiente`; el default de propiedades cubre la compatibilidad. |
| Sin cambios de orden en `app/Livewire/**` | Mover `dispararInicioTurno` a `avanzar()` | Cambiaría el timing de `EfectoVeleta` y otros efectos; fuera de alcance. |

## Problemas detectados y riesgos

1. **[medio] Interpretación de la curación**: se cura al perder el turno, antes de un
   atacante más lento. Daño posterior al descanso no cancela la curación ya aplicada.
2. **[medio] Ciclo tras el enfado**: actúa ese turno y descansa la ronda siguiente
   (ciclo normal de Ausente). Es una decisión de producto asumida; el plan la declara.
3. **[medio] Contador temporal compartido**: `aplicarTemporal` reinicia a 5 el
   contador de todos los temporales. Afecta a buffs previos de Slaking. Preexistente;
   documentar.
4. **[medio] Anulación a mitad de ronda** (Gas Reactivo): la condición `AUSENTE`
   queda huérfana si el efecto se retira después del anuncio; se limpia en
   `onRoundStart`/`onRoundEnd` del efecto restaurado, pero si nunca se restaura,
   `puedeActuar` la consume una vez. Residual aceptado: un turno bloqueado sin
   curación.
5. **[medio] Rollback**: revertir el código sin limpiar sesiones con el enum `AUSENTE`
   fallará al deserializar. Rollback = revertir archivos **y** limpiar `battle_*`.
6. **[bajo] Copy/interchange**: el `clone` del efecto arrastra estado; se neutraliza
   con `__clone`.
7. **[bajo] `puedeActuar()` es mutante**: una doble llamada por ronda puede consumir
   `AUSENTE` y habilitar el turno. Riesgo preexistente con `INTERRUPCION`.
8. **[bajo] `EfectoVeleta`** sigue decidiendo en `onTurnStart`, tarde en Livewire.
   Deuda separada.
9. **[bajo] Acciones extra**: una acción extra tras el descanso permitiría actuar con
   `AUSENTE` ya consumido. Edge documentado.
10. **[bajo] Descripción**: `abilities_descripciones.json:26` debe actualizarse; el
    CSV de catálogo queda fuera de alcance.
11. **[bajo] Hook en cada denegación**: cualquier futuro `ReaccionaAlPerderTurno` debe
    ser idempotente; el docblock de la interfaz lo exige.
12. **[bajo] Cargas**: usar “alguna > 0”, no `MapaConteo::isEmpty()`, para no dejar de
    descansar tras liberar una carga.

## Preguntas abiertas

Ninguna bloqueante. Se acepta el residual de un turno bloqueado por `AUSENTE`
huérfano al anular la habilidad a mitad de ronda (recomendado) frente a acoplar
`HabilidadCombatiente::anular()` a esta habilidad.
