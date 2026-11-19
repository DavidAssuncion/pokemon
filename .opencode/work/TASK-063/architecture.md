# TASK-063 — Arquitectura

- **ID:** TASK-063
- **Estado:** COMPLETADO
- **Arquitecto:** `architect`
- **Fecha:** 2026-11-19

## Resultado

La corrección es **eliminar las tres concesiones incondicionales** de turno extra y
**no tocar nada más**:

1. `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:138-141`
   (`aplicarMovimiento`).
2. `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:240-243`
   (`resolverObjeto`).
3. `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:126-129`
   (`procesarConsecuencias`).

En los tres casos se conserva la llamada `procesarDebilitamientos()` (necesaria
para notificar bajas, disparar `onFainted`/`ReaccionaAlDebilitamiento`,
`notifyFainted` y sincronizar Gas Reactivo). Solo desaparece el `if (...) {
$actor->otorgarAccionExtra(); }`.

Se conserva íntegra la capacidad: `EstadoTurno::accionesExtra`/`extraOtorgada`,
`otorgarAccionExtra()`, `getNextActor()` (pool de extras), `consumeAction()`
(consume extras primero) y su serialización. **No se introduce ninguna abstracción
nueva**: no hay consumidor hoy y la capacidad ya está expuesta en el agregado;
añadir un servicio/estrategia sería YAGNI y duplicaría el lugar de decisión.

El turno extra futuro no necesita código nuevo en Livewire: el dominio ya dispone
de un punto de despacho compartido por ambos caminos
(`AplicadorEfectosPostImpacto::dispararReacciones` vía `ResolutorImpacto`), con la
causalidad exacta "KO causado por el actor". La habilidad se implementará como
efecto registrado, sin tocar el motor.

## Hechos verificados adicionales (archivo:línea)

### Concesión actual

- Solo existen tres invocaciones de `otorgarAccionExtra()` en producción:
  `ResolutorAccionBatalla.php:140`, `:242`, `EjecutorAccion.php:128`
  (`grep -rn` sobre `src`, `app`).
- `aplicarMovimientoAliado()` ya llama `procesarDebilitamientos()` **sin** conceder
  extra (`ResolutorAccionBatalla.php:198-203`): la excepción ya existía en el
  código; la concesión era ad hoc, no una regla uniforme.
- `procesarDebilitamientos()` cuenta bajas globales de ambos equipos:
  `MotorRondas.php:92-107` recorre `allCombatants()`;
  `DelegacionEfectosCombatiente.php:52-64` devuelve `true` una sola vez por
  combatiente.

### Capacidad existente (a conservar)

- Idempotencia y tope de 1 extra por ronda: `EstadoTurno.php:68-76`
  (`extraOtorgada`) y `:63-66` (clamp 0..1); reinicio por ronda en
  `GestorTurnos.php:61` (`reiniciarAccionExtra`).
- El extra se consume antes de incrementar `vecesActuado`: `GestorTurnos.php:159-170`.
- El pool de extras es el último recurso de `getNextActor()`: `GestorTurnos.php:103-107`.
- Serialización intacta y orden de payload fijo: `TurnoCombatiente.php:30-31,
  49-64`; `SerializadorCombatiente.php:23-78` (claves `accionesExtra` y
  `extraOtorgada` en `:28-29`).
- `SESSION_VERSION = 21` y mínimo compatible 9 (`BattleSessionService.php:50, 64`):
  al no cambiar el esquema del payload **no hay que versionar ni migrar**.
- Nada en frontend consume `accionesExtra`: `grep` en `resources/` y `app/Support/`
  → 0 resultados; solo se expone en el contrato de vista (`CombatienteVista.php:153`).

### Punto de despacho futuro (ya existe y es compartido)

- Capacidad de reacción a KO:
  `src/Battle/Domain/Effects/Capacidades/ReaccionaAlDebilitarRival.php:10-13`;
  despacho en `ColeccionEfectos.php:373-380`.
- Se dispara exactamente al morir el objetivo por el golpe:
  `AplicadorEfectosPostImpacto.php:163-165`, invocado desde `ResolutorImpacto.php:251`
  (solo si `huboDano()`).
- Ese servicio es el mismo en ambos caminos: `ResolutorAccionBatalla.php:64` y
  `EjecutorAccion.php:43` llaman
  `ServicioEjecucionBatalla::calcularYAplicarDano`; este usa `ResolutorImpacto`
  (`ServicioEjecucionBatalla.php:36, 58`).
- Multiobjetivo por objetivo real: `ServicioEjecucionBatalla.php:114-135`,
  `accionContra()` crea una `AccionBatalla` por defensor, así que el hook ve el KO
  correcto en cada objetivo.
- Precedente de causalidad "último golpe del portador":
  `RegistroDanio::registrarDanoRecibido` (`AplicadorDanioObjetivo.php:97`) y su uso
  en `EfectoComealmas.php:67`.
- Una habilidad registrada como efecto hereda anulación/copia:
  `HabilidadCombatiente::anular()` retira de la colección todo efecto que no sea de
  objeto (`:68-92`), por lo que respeta Gas Reactivo/Anulación sin código extra.

### Impacto por camino

- Manual/objetos: los dos puntos de `ResolutorAccionBatalla`; tras el commit,
  `GestorCicloTurnosBatalla::avanzar` (`:53`) pasa al siguiente actor pendiente o
  abre ronda.
- Dominio/IA: `AgregadoBatalla::ejecutarBatalla()` (`:395-407`) → `MotorRondas` →
  `EjecutorAccion`. Único consumidor productivo: `CombateExploracion.php:165`. Es
  1v1, y `MotorRondas.php:32` termina al morir un bando, por lo que el extra era
  prácticamente inerte ahí.

### Guardas de TASK-040

- `RegistroDanio::puedeUsarProteccion` usa `>= $ronda - 1` (`:77-81`) y ya contempla
  el doble uso en la misma ronda. Sigue siendo válido y **no se toca**; queda como
  defensa en profundidad para una futura acción extra.
- `EfectoTruant` alterna por `onTurnStart` (`:26-34`); `EfectoInicioLento`
  descuenta por `onRoundStart` (`:28-33`); `SelectorAccionIA.php:221` filtra
  Protección/Aguante. Los tres quedan igual.

### Reglas y ADR

- `.ai/rules/index.md:9,13` cubren `src/**` y `app/**`: aplican `ddd-modules.md` y
  `arrays-dto-collections.md`. `arrays-dto-collections.md:69` exige explícitamente
  que Livewire no concentre reglas que pertenecen a `src/`, lo que respalda retirar
  la concesión de la presentación. No aplican `frontend-minimal-logic`,
  `infra-controllers` ni `domain-repositories` (no se tocan esas rutas).
  `grep -rin` sobre `.ai/rules` no añade reglas adversas.
- `docs/adr/` contiene 0001–0005 (inventario, bayas, cosecha, tema, pokedex):
  **ningún ADR cubre el motor de combate ni el turno extra**.

## Decisiones de diseño y alternativas descartadas

**D1. La regla de concesión sale de la presentación y del ejecutor; no se sustituye
por otra regla.** El motor no debe decidir nada mientras no exista una habilidad
que lo justifique (decisión del desarrollador 2026-11-19).

**D2. La capacidad se conserva tal cual.** `otorgarAccionExtra()` es la API de
agregado correcta (estado de turno en `EstadoTurno`, delegado por
`TurnoCombatiente` y `DelegacionTurnoCombatiente`). Eliminarla rompería el payload y
la posibilidad futura.

**D3. No se crea ningún colaborador nuevo.** Un `OtorgadorTurnoExtra` inyectado en
Livewire y en `EjecutorAccion` seguiría teniendo dos llamadores y duplicaría la
decisión; además obligaría a que `app/` dependiera de una pieza de dominio nueva sin
necesidad.

**D4. El futuro "quién concede" es una habilidad-efecto, no una condición en el
motor.** El motor ya tiene el gancho causal correcto; la comprobación de habilidad
es implícita (solo existe el efecto si la habilidad está registrada y no anulada).

### Alternativas evaluadas

| Opción | Veredicto |
|---|---|
| A. Retirar las tres concesiones y conservar la capacidad | **Elegida.** Mínima, coherente con `arrays-dto-collections.md:69`, sin cambios de esquema. |
| B. Añadir `if ($actor->tieneEfecto('...'))` en las tres ubicaciones | Descartada: duplica la regla en tres sitios (dos de ellos Livewire), exige una clave inexistente y mantiene la decisión en presentación. |
| C. Extraer ahora un servicio `OtorgadorTurnoExtra` compartido | Descartada: abstracción sin consumidor real; no aporta hasta que exista la habilidad. |
| D. Eliminar `otorgarAccionExtra()`/`accionesExtra` del modelo | Descartada: rompe serialización y el contrato explícito de esta tarea. |
| E. Cambiar `procesarDebilitamientos()` para devolver la colección de muertos y decidir dentro | Descartada *ahora*: es un contrato público con 5 llamadores (`MotorRondas:65,80,159`, `ResolutorAccionBatalla:138,201,240`, `EjecutorAccion:126`) y el agregado no conoce al actor; se reserva para la tarea de la habilidad si se requiere más precisión que la del gancho post-impacto. |

## Contrato futuro del turno extra

**Condición (la que fijó el desarrollador)**

> Solo un KO **causado por el propio actor con su acción ofensiva** puede conceder
> turno extra. Nunca muertes de aliados, retroceso, Detonación/autodestrucción,
> daño de estado/clima, efectos de terceros ni KO producidos por objetos.

**Dónde se decide (punto único, en dominio)**

- Nueva clase de efecto en `src/Battle/Domain/Effects/` que implemente la capacidad
  existente `ReaccionaAlDebilitarRival`, registrada en
  `app/Providers/BattleEffectServiceProvider.php` bajo la clave de la habilidad
  (mismo patrón que `EfectoMoxie`/`EfectoComealmas`).
- Pseudocontrato:

  ```php
  final class Efecto<TurnoExtra> implements InterfazEfecto, ReaccionaAlDebilitarRival
  {
      public function alDebilitarRival(Combatiente $portador, Combatiente $rival, AgregadoBatalla $battle): void
      {
          if (! $portador->estaVivo()) {
              return;
          }

          $portador->otorgarAccionExtra();
      }
  }
  ```

- **No** se modifica `AplicadorEfectosPostImpacto`: `dispararReacciones`
  (`:163-165`) ya se ejecuta solo cuando `$accion->defender` muere por el golpe del
  actor, en ambos caminos (Livewire y dominio/IA), porque ambos usan
  `ServicioEjecucionBatalla` → `ResolutorImpacto:251`.
- **No** se añade comprobación de habilidad en `GestorTurnos`, `MotorRondas`,
  `EjecutorAccion` ni en Livewire: la presencia/ausencia del efecto en
  `ColeccionEfectos` **es** la comprobación, y hereda automáticamente anulación,
  Gas Reactivo, copia e intercambio de habilidad.
- La idempotencia ya está resuelta: `EstadoTurno::otorgarAccionExtra` ignora una
  segunda concesión en la misma ronda (`:70-72`), así que multiobjetivo/multigolpe no
  apilan.

**Efecto sobre el orden de turno (decisión pendiente al implementar la habilidad,
no ahora)**

- Hoy el manual concede *después* de `consumeAction`
  (`ResolutorAccionBatalla.php:130` vs `:140`) → repetición al final de la ronda. El
  camino de dominio ya concede *antes* de `consumeAction` (`EjecutorAccion.php:128`
  vs `MotorRondas.php:160`) → repetición inmediata.
- Con el gancho post-impacto (dentro de `calcularYAplicarDano`) la concesión ocurre
  antes de `consumeAction` en **ambos** caminos: el extra se consume en el mismo
  `consumeAction` (`GestorTurnos.php:163-167`) y el actor vuelve a ser el más rápido
  pendiente → **repetición inmediata**, dos acciones netas por ronda,
  comportamiento homogéneo entre manual e IA.
- Recomendación: aceptar la repetición inmediata (simplifica y unifica caminos). Si
  el desarrollador quiere "al final de la ronda", habría que conceder en un cierre
  de acción posterior a `consumeAction` en los tres puntos, lo que reintroduce
  acoplamiento; conviene decidirlo en la tarea de la habilidad, no aquí.

**Truant/Inicio Lento con la futura habilidad**: un `onTurnStart` extra de Truant
aplicaría `aplicarInterrupcion` y bloquearía la acción extra; es una interacción a
decidir explícitamente cuando se defina la habilidad. Hoy no aplica.

## Archivos y orden de cambios

| # | Archivo | Líneas | Capa | Responsable | Cambio |
|---|---|---|---|---|---|
| 1 | `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php` | 126-129 | Dominio | `backend` | Eliminar el `if` y la variable `$nuevosDebilitados`; dejar `$batalla->procesarDebilitamientos();`. |
| 2 | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | 138-141 | Presentación (Livewire) | `backend` | Idem en `aplicarMovimiento`. |
| 3 | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | 240-243 | Presentación (Livewire) | `backend` | Idem en `resolverObjeto`. |

- **Orden**: 1 → 2 y 3 (independientes entre sí, mismo fichero) → verificación.
- **No** se modifican: `MotorRondas`, `GestorTurnos`, `EstadoTurno`,
  `TurnoCombatiente`, `SerializadorCombatiente`, `BattleSessionService`,
  `AplicadorEfectosPostImpacto`, `RegistroDanio`, guardas de TASK-040, `resources/**`,
  migraciones ni dependencias.
- Sin responsable `frontend` ni `database`: no hay cambios de UI ni de datos.
- **ADR 0006 descartado en esta tarea**: el AGENTS.md del proyecto indica que solo se
  crean documentos si el desarrollador lo pide explícitamente. La decisión queda
  registrada en los artefactos de TASK-063.

## Verificación requerida (comandos)

```bash
# 1. Sintaxis de los ficheros tocados
php -l src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php
php -l app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php

# 2. Análisis estático del proyecto (phpstan sobre src/ y app/)
composer analyse
# opcional, configuración endurecida:
composer analyse:hard

# 3. Límites de capas (deptrac: Domain no depende de App/Presentation)
composer arch

# 4. Estilo (no modifica):
composer lint

# 5. Evidencia de que no queda ninguna concesión fuera del dominio
grep -rn "otorgarAccionExtra" src app
# esperado: solo definiciones (EstadoTurno:68, TurnoCombatiente:96,
# DelegacionTurnoCombatiente:32) — cero invocaciones desde app/ o Componentes/Agregado/
```

Sin tests: no se crean, no se modifican y no se ejecuta la suite.

## Criterios de aceptación verificables para el reviewer

1. `grep -rn "otorgarAccionExtra" src app` no devuelve ninguna llamada; solo las tres
   definiciones delegadas.
2. Tras un KO en combate manual, el actor **no** vuelve a recibir turno en la misma
   ronda; el turno pasa al siguiente pendiente o se abre ronda
   (`GestorCicloTurnosBatalla.php:53`).
3. Usar un objeto que debilite no concede turno extra (`resolverObjeto` sin
   `otorgarAccionExtra`).
4. `procesarDebilitamientos()` sigue invocándose en los tres puntos originales y en
   `aplicarMovimientoAliado`, y el log de debilitamiento se mantiene.
5. `accionesExtra` y `extraOtorgada` siguen presentes en `TurnoCombatiente::serializar()`
   y en `SerializadorCombatiente::ORDEN`; `SESSION_VERSION` sin cambios.
6. `composer analyse`, `composer arch` y `composer lint` en verde; `deptrac` sin
   nuevas violaciones.
7. Cero cambios en `resources/**`, `database/**` y `composer.json`.

## Problemas detectados

- **Duplicación de la regla en tres sitios con dos capas**: era el defecto central;
  la corrección la reduce a cero reglas en presentación.
- **`procesarDebilitamientos()` es global y sin causalidad**: no sirve para decidir
  "quién causó el KO". Documentar que la futura habilidad **no** debe usarlo como
  señal; la señal correcta es el gancho post-impacto (o `ultimoAtacanteId()` si se
  necesitara otra vía).
- **Concesión no registrada en log**: al eliminar la regla desaparece también la
  rareza de "el jugador actúa otra vez sin mensaje". No requiere cambio de UI.
- **Llamada redundante en `EjecutorAccion`**: `MotorRondas.php:159` ya procesa
  debilitamientos después; se conserva la llamada interna (idempotente) para no
  alterar el orden respecto a `dispararFinTurno` y por seguridad si `EjecutorAccion`
  se reutiliza. No es un defecto, pero el reviewer debe saber que es intencional.

## Riesgos pendientes

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Sesiones ya serializadas con `accionesExtra = 1` / `extraOtorgada = true` | Bajo | No se toca el esquema: se restauran (`TurnoCombatiente.php:30-31`) y el extra se consume una vez; `startNewRound` lo reinicia. No hay migración ni invalidación de sesión. Verificar carga de una batalla antigua. |
| `otorgarAccionExtra()` queda sin llamadores de producción hasta que exista la habilidad | Bajo | API pública intencional; phpstan no marca código muerto y no se añade baseline. Documentar la decisión. |
| Reintroducción accidental de la concesión en Livewire | Medio | Criterio de revisión 1; la capacidad solo debe invocarse desde un efecto de dominio. |
| Cambio de balance: menos acciones por ronda en batallas multijugador (manual y entrenadores) | Medio | Esperado por decisión de producto. En exploración (1v1) el impacto es nulo porque la batalla termina con el KO. Validar jugando una batalla 3v3 antes de cerrar. |
| Orden del futuro turno extra (inmediato vs fin de ronda) sin decidir | Medio | Fijado como pregunta abierta; el contrato propuesto produce repetición inmediata y homogénea en ambos caminos. Decidir en la tarea de la habilidad. |
| Interacción futura con `EfectoTruant` (la acción extra podría bloquearse por "Ausente") | Bajo | Aplazado a la tarea de la habilidad; no afecta a esta corrección. |
| Guardas de TASK-040 (`>= ronda - 1`) dejan de tener su caso de uso actual | Bajo | No se modifican: siguen siendo válidas y actúan como defensa en profundidad ante una futura acción extra. |

## Preguntas abiertas (no bloquean esta corrección)

1. ¿La futura habilidad repetirá de inmediato o al final de la ronda? (recomendado:
   inmediato, unifica caminos).
2. ¿Debe el turno extra anunciarse en el log/eventos de animación? (fuera del alcance
   actual, sin cambios de UI).
3. ¿Se creará un ADR de batalla cuando se implemente la habilidad? (recomendado sí;
   no existe documentación de esta regla).
