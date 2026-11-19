# TASK-063 — Análisis

- **ID:** TASK-063
- **Estado:** PARCIAL (límite de pasos del analista) — bloqueado por decisión del desarrollador
- **Analista:** `analyst`
- **Fecha:** 2026-11-19

## Resultado (resumen ejecutivo)

El turno extra tras un KO **no depende de ninguna habilidad**: está codificado de
forma incondicional en los dos caminos de ejecución de acciones del motor de
batalla. La condición real es únicamente `hay al menos un debilitamiento nuevo
procesado && el actor sigue vivo`, sin comprobar la habilidad del actor, ni que
el KO lo haya causado él, ni que el muerto sea rival.

No existe en el repositorio ninguna habilidad, clave de efecto, seeder ni
documentación que defina "turno extra / actuar de nuevo / repetir turno". Por
tanto, el conjunto de habilidades que debería conservar el efecto **no está
definido** y hay que preguntar al desarrollador.

## Evidencia detallada

### 1. Puntos exactos donde se otorga el turno extra

- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:138-141` — camino real
  de la UI (Livewire/manual):

  ```php
  $nuevosDebilitados = $battle->procesarDebilitamientos();
  if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
      $actor->otorgarAccionExtra();
  }
  ```

- `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:239-242` — mismo patrón
  en `resolverObjeto()` (usar un objeto también concede turno extra si algo muere).
- `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:126-129` — mismo
  patrón en el camino de dominio/IA (`AgregadoBatalla::ejecutarBatalla()` →
  `MotorRondas` → `EjecutorAccion`), usado por
  `src/Exploraciones/App/CombateExploracion.php:165`.

Búsqueda exhaustiva: `otorgarAccionExtra()` solo se invoca en esas tres
ubicaciones (`grep -rn "otorgarAccionExtra" src app`).

### 2. Por qué ocurre para todos los Pokémon

- La condición no mira habilidades: no hay `if ($actor->tieneHabilidad('...'))` ni
  consulta a `FabricaEfectos`/`CatalogoHabilidades`.
- `procesarDebilitamientos()` devuelve el **total global de combatientes recién
  debilitados de ambos equipos**, no solo el objetivo golpeado por el actor:
  - `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:84-101` recorre
    `allCombatants()` y suma cualquier `notificarDebilitamiento()`.
  - `src/Battle/Domain/Componentes/Combatiente/DelegacionEfectosCombatiente.php:52-64`
    (`notificarDebilitamiento`) devuelve `true` una sola vez por combatiente nuevo.
- Consecuencia: cualquier KO producido durante la resolución de la acción
  (objetivo, segundo objetivo de un movimiento multiobjetivo, aliado por
  retroceso/Detonación, o incluso una muerte pendiente de otro efecto) dispara el
  turno extra para el actor, aunque no lo haya causado.
- No hay valor por defecto ni error de comparación: es una regla hardcodeada.
  `EstadoTurno` limita a una acción extra por ronda
  (`src/Battle/Domain/ValueObjects/EstadoTurno.php:63-77`), lo que hace que el
  síntoma sea "todos repiten una vez por ronda" en lugar de encadenar
  indefinidamente.

### 3. Flujo del turno (impacto)

1. `ResolutorAccionBatalla::aplicarMovimiento` aplica daño → estado → stats →
   `notifyDamaged` → `dispararDanioRecibido`/`dispararDanioInfligido` (aquí corren
   reacciones como `EfectoMoxie`/`EfectoUltraimpulso` vía
   `AplicadorEfectosPostImpacto::dispararReacciones`,
   `src/Battle/Domain/Ejecucion/AplicadorEfectosPostImpacto.php:149-165`).
2. `consumeAction($actor)` (`:133`) marca la acción normal.
3. `procesarDebilitamientos()` + `otorgarAccionExtra()` (`:138-141`) fija
   `accionesExtra = 1`.
4. `GestorAccionBatalla` guarda y llama `nextActor()` (`:270`).
5. `GestorCicloTurnosBatalla::avanzar` (`:34-...`) elige actor con
   `GestorTurnos::getNextActor()`.
6. `GestorTurnos::getNextActor` (`src/Battle/Domain/GestorTurnos.php:88-110`) solo
   recurre al pool de `accionesExtra() > 0` cuando ya no quedan combatientes con
   `vecesActuadoEstaRonda() === 0`; es decir, el actor repite **al final de la
   ronda**.
7. `consumeAction` (`GestorTurnos.php:159-170`) consume primero la acción extra y
   no incrementa `vecesActuado`.
8. La concesión se reinicia en `startNewRound()` (`GestorTurnos.php:51-74`, vía
   `reiniciarAccionExtra`).

En el camino de dominio la diferencia es que `consumeAction` se llama **después**
de la acción (`MotorRondas.php:120-127`), por lo que la acción extra se descuenta
en el mismo `consumeAction` y el actor vuelve a ser elegible por
`vecesActuado === 0`. El efecto neto (dos acciones en la ronda) es el mismo.

Efectos colaterales de la decisión:

- El log no anuncia el turno extra; el jugador solo ve que le vuelven a pedir
  acción.
- `EfectoMoxie`, `EfectoUltraimpulso`, `Relincho blanco/negro` (subida de stat al
  debilitar rival) se disparan aparte en
  `AplicadorEfectosPostImpacto.php:160-164`, no dependen de `otorgarAccionExtra`.
  Cambiar el turno extra no los rompe.
- `EfectoTruant`/`EfectoInicioLento` y las restricciones de `Aguante`/`Protección`
  ya contemplan la existencia de acciones extra (ver TASK-040): si se elimina el
  turno extra global, esas guardas siguen siendo válidas pero dejan de tener ese
  caso de uso.
- `resolverObjeto` (`:239-242`) también quedaría afectado: usar un objeto no
  debería dar turno extra salvo habilidad.

## Inventario de habilidades

Habilidades que reaccionan a debilitar a un rival (todas **solo suben stats**,
ninguna da turno extra):

- Autoestima — `storage/data/abilities_descripciones.json:27`; clase
  `src/Battle/Domain/Effects/EfectoMoxie.php`.
- Ultraimpulso — `abilities_descripciones.json:238`;
  `src/Battle/Domain/Effects/EfectoUltraimpulso.php`.
- Relincho Blanco / Relincho Negro — `abilities_descripciones.json:203-204`
  (implementadas vía `EfectoMoxie` con distinto `StatClave`).
- Detonación — reacciona a la propia muerte, no al KO ajeno
  (`EfectoAlDebilitar.php`).

Habilidades de multiataque que **no** son turno extra (reparten potencia en golpes
del mismo movimiento): Amor Filial, Doble Ataque, Triple Ataque, Cuádruple Ataque —
`abilities_descripciones.json:16,75,235,56`; `active/CATALOGO_FINAL_HABILIDADES.md:203`.

Habilidades que restan turnos: Ausente (`EfectoTruant.php`), Inicio Lento
(`EfectoInicioLento.php`).

**No existe** en `src/Battle/Domain/Enums/ClaveEfecto.php` (solo 7 casos, todos de
clima/armadura), en `FabricaEfectos`, en el seeder
(`database/seeders/AbilitySeeder.php`) ni en `storage/data/` ninguna clave o
descripción de "turno extra". Tampoco en `docs/` ni en
`active/CATALOGO_FINAL_HABILIDADES.md`. El único rastro documental del concepto es
la mecánica descrita en artefactos de tareas anteriores
(`.opencode/work/TASK-040/analysis.md:165`, `architecture.md:70`,
`TASK-026/review.md:23`), que la tratan como regla del motor, no como habilidad.

## Lagunas y preguntas para el desarrollador

1. ¿Qué habilidades deben conservar el turno extra tras KO? Candidatos homebrew a
   confirmar: ¿alguna de las de "debilitar" (Autoestima/Ultraimpulso/Relinchos) o
   una habilidad nueva tipo "Depredador"/"Cazador"? No hay definición en el repo.
2. ¿El turno extra debe darse solo si el actor causa el KO, o también si muere un
   aliado por retroceso/Detonación?
3. ¿Debe aplicarse también cuando el KO lo causa un objeto usado en combate
   (`resolverObjeto`)?
4. ¿Debe aplicarse en el camino de simulación/IA de `EjecutorAccion`
   (exploraciones/auto-batalla) o solo en el combate manual?
5. ¿El turno extra debe ser inmediato (misma posición en el orden) o al final de la
   ronda, como ocurre hoy?
6. ¿Debe mostrarse un mensaje en el log ("¡X puede actuar de nuevo!")?

## Archivos candidatos a modificar (sin modificarlos)

| Archivo | Capa | Cambio esperado |
|---|---|---|
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:138-141,239-242` | Presentación/Infra (Livewire) | Condicionar la concesión; extraer a un servicio de dominio para no duplicar |
| `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:126-129` | Dominio (Agregado) | Misma condición |
| `src/Battle/Domain/Componentes/Agregado/MotorRondas.php` (posible) | Dominio | Exponer si el debilitado fue causado por el actor |
| `src/Battle/Domain/Effects/FabricaEfectos.php` + nueva capacidad en `Effects/Capacidades/` | Dominio | Solo si se decide modelar por clave de efecto |
| `src/Battle/Domain/Enums/ClaveEfecto.php` | Dominio | Solo si se añade clave nueva |
| `database/seeders/AbilitySeeder.php` / `storage/data/abilities_descripciones.json` | Datos | Solo si el efecto se resuelve por datos |

## Decisiones/hipótesis separadas

Hechos verificados: ubicaciones y condición exactas (arriba); ausencia de
habilidad/clave que conceda turno extra; carácter global de
`procesarDebilitamientos()`.

Hipótesis (no verificadas): que el turno extra se introdujo como mecánica genérica
de "racha" en TASK-023/026 (git blame: `dd83ca98`, `fad7c65`) y no como soporte de
una habilidad concreta; que el desarrollador espera que exista una habilidad
homebrew todavía no modelada.

## Verificación realizada

Lectura y `grep` en `src/Battle`, `app/Livewire/Combate`, `database/seeders`,
`storage/data`, `docs`, `active`, `.ai/rules`, `.opencode/work`; `git status`,
`git log -S`, `git blame`. Sin ejecutar tests, migraciones ni seeders. No se leyó
`.env`.

## Problemas detectados

- Duplicación de la regla en tres sitios (Livewire, objeto y dominio).
- El contador de debilitamientos es global: puede conceder turno extra por muertes
  ajenas al actor.
- `resolverObjeto` concede turno extra por KO, presumiblemente no deseado.
- No hay log ni evento de "turno extra", lo que dificulta diagnosticar en UI.

## Riesgos pendientes

- **[alto]** Cambiar solo una de las tres ubicaciones deja comportamiento distinto
  entre combate manual, uso de objetos y simulación/IA.
- **[alto]** Si se añade condición por habilidad sin definir antes el conjunto, se
  puede romper el diseño homebrew esperado.
- **[medio]** Persisten sesiones de batalla serializadas con `accionesExtra = 1`;
  `EstadoTurno::desdeArray` las restauraría (compatibilidad), conviene decidir si
  se limpian.
- **[medio]** Regresiones en restricciones de movimientos que asumían acción extra
  (TASK-040: Aguante/Protección con `>= ronda - 1`).
- **[bajo]** Balance: quitar el turno extra global reduce la letalidad de las rondas
  y puede afectar a la IA (`SelectorAccionIA`, `MemoriaCombateIA` registra KOs).

## Estado del informe

El informe **no está completo** según el formato exigido: faltan las secciones
`REQUISITOS`, `ALCANCE AFECTADO`, `DEPENDENCIAS`, `OPCIONES`, `SUPOSICIONES`,
`PREGUNTAS BLOQUEANTES` y `RECOMENDACIÓN` en su forma canónica, así como la
comprobación de `docs/adr` y reglas restantes (`frontend-minimal-logic.md`,
`infra-controllers.md`, `domain-repositories.md`) que no llegó a leer.

## Recomendación para el siguiente paso

Reanudar con un turno adicional para: (a) leer las reglas restantes y ADRs; (b)
cerrar el informe con el formato completo; (c) **bloquear el diseño** hasta que el
desarrollador responda qué habilidades deben conservar el turno extra, ya que el
conjunto no está definido en el repositorio.
