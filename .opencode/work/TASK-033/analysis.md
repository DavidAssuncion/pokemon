# TASK-033 — Análisis

- **Estado:** COMPLETED
- **Analista:** `analyst` (sesión `ses_e40d0dff7ffeLV6LGrZR1ToDJL`)
- **Nota:** resultado del analista, sin alterar sus conclusiones.

---

# 1. Resumen ejecutivo

Los tres fallos son **carencias de producto/UX y de contrato de preparación**, no fallos del motor de ejecución:

1. **Danza Espada sobre aliado en retaguardia**: existe una restricción de posición copiada del targeting rival y aplicada al apoyo aliado. Está en tres puntos del camino del jugador (`BatallaVistaPresenter`, `previewTarget` y `selectMove`) y afecta a **todos** los movimientos del catálogo aliado, no solo a `swords-dance`.
2. **Objetivo de ataques de prioridad**: no existe estado ni UI para el objetivo. `$prioridades` solo guarda el índice del movimiento; `GestorPrioridadesBatalla::objetivoPara()` autoselecciona el rival mediante la IA. La acción de prioridad se resuelve en la confirmación, antes del flujo normal de selección de objetivo.
3. **Objetivo de Cubrir**: tampoco existe estado ni UI. `EquipoBatalla::normalizarCubridores()` asigna automáticamente el primer aliado vivo distinto del cubridor. La pantalla de Equipo solo persiste la postura, no el objetivo.

La corrección mínima recomendada es:
- eliminar la restricción de retaguardia **solo en el camino del jugador** para movimientos de apoyo;
- añadir un mapa de objetivo de prioridad por actor en el componente Livewire y consumirlo en la resolución;
- añadir un mapa de objetivo de Cubrir por cubridor y validarlo al aplicar la preparación;
- no tocar `SESSION_VERSION`, `BattleSessionService`, tests, dependencias ni migraciones.

---

# 2. Causa raíz y evidencia por cada fallo

## 2.1. Fallo 1 — Danza Espada sobre aliado en retaguardia

**Causa raíz:** la regla “solo vanguardia aliada si hay vanguardia viva” se diseñó en TASK-030 para el apoyo aliado y se implementó en la vista, en el gestor de selección y en la IA. Para movimientos de apoyo no existe adyacencia real en este juego; la restricción solo tiene sentido para ataques dirigidos a rivales.

Evidencia:

- `resources/views/livewire/partials/preparacion-panel.blade.php:7-11` — la postura se elige, pero no hay objetivo.
- `resources/views/livewire/_pokemon-card.blade.php:4` — la carta solo es clicable si `$p['selectable']`.
- `app/Livewire/Presenters/BatallaVistaPresenter.php:115-147` — `refsAliado()` construye los aliados seleccionables.
- `app/Livewire/Presenters/BatallaVistaPresenter.php:139-141` — **primer bloqueo**: excluye aliados en retaguardia si `$vanguardiaViva` es true.
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:59-68` — **segundo bloqueo**: `previewTarget()` rechaza al aliado en retaguardia si su equipo tiene vanguardia viva.
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:162-169` — **tercer bloqueo**: `selectMove()` rechaza el aliado en retaguardia con el log `"{$seleccionado->nombre()} no está en la vanguardia."`.
- `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php:28` — `swords-dance` está admitido como efecto aliado.
- `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php:21-68` — el catálogo completo: curas, escudos, buffs, Aqua Ring, curación de estado y crítico. **El fallo afecta a todos esos slugs**, no solo a Danza Espada.
- `.opencode/work/TASK-030/architecture.md:174` — decisión explícita: “Aliado en retaguardia con vanguardia aliada viva → Rechazo (no alcanzable)”.
- `.opencode/work/TASK-030/plan.md:63` — “Solo **vanguardia viva**; retaguardia no seleccionable”.
- `src/Battle/Domain/AI/SelectorAccionIA.php:314-326` — la IA aplica la misma restricción al generar acciones de soporte.
- `src/Battle/Domain/AgregadoBatalla.php:401-431` — `actorAdmiteSoporte()` también filtra retaguardia aliada si hay vanguardia viva.

**Conclusión:** el dominio y el resolutor aliado (`ResolutorAccionBatalla::aplicarMovimientoAliado()`, líneas 155-203) **no** tienen restricción de posición. El bloqueo es exclusivamente de presentación y selección. `swords-dance` es autodirigido (`MovimientoBatalla::esAutodirigido()`), pero la rama aliada de `selectMove()` ya lo trata correctamente cuando el objetivo es un aliado: usa `CatalogoEfectosAliado::para()` y crea la acción pendiente con `defender` aliado.

---

## 2.2. Fallo 2 — No se puede elegir contra quién van los ataques de prioridad

**Causa raíz:** no existe ningún campo de objetivo para prioridades. `$prioridades` es solo `refId => índice`; el panel solo pinta un `<select>` de movimiento; y la resolución fuerza la elección automática de la IA.

Evidencia:

- `app/Livewire/Combate.php:39-40` — `$prioridades` documentado como `refId => índice del movimiento prioritario`.
- `app/Livewire/Combate.php:45-46` — `$prioridadesDisponibles` documentado como `refId => list<{index, nombre, prioridad}>`; no hay objetivo.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:41-55` — solo se guardan `index`, `nombre` y `prioridad`.
- `resources/views/livewire/partials/preparacion-panel.blade.php:24-41` — un único `<select>` con `wire:model="prioridades.{{ $pokemon['refId'] }}"`; no hay selector de objetivo.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:174-192` — `aplicar()` valida el índice, la prioridad y la disponibilidad, pero **no lee ni valida ningún objetivo**. Si hay prioridad, fuerza `ATACAR`, limpia `objetivoCubiertoId` y declara la prioridad.
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:120` — `resolver()` llama a `objetivoPara()` sin objetivo declarado.
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:149-162` — `objetivoPara()`:
  - devuelve el actor si el movimiento es autodirigido o `ALL`;
  - en caso contrario, `$battle->elegirObjetivoPara($actor)`.
- `src/Battle/Domain/AgregadoBatalla.php:450-456` — `elegirObjetivoPara()` delega en `SelectorAccionIA::elegirAccion(..., incluirSoporte: false)`.
- `src/Battle/Domain/AI/SelectorAccionIA.php:112-125` — devuelve el `defender` de una evaluación completa de la IA, no del jugador.
- `src/Battle/Domain/ValueObjects/EstadoTurno.php:32` — la prioridad solo es `?MovimientoBatalla`.
- `src/Battle/Domain/ValueObjects/EstadoTurno.php:171-179` — `declararPrioridad()`/`prioridad()` no guardan objetivo.
- `src/Battle/Domain/Componentes/Combatiente/TurnoCombatiente.php:33-43` — la restauración solo lee `prioridadDeclarada`.
- `src/Battle/Domain/Componentes/Combatiente/TurnoCombatiente.php:48-62` — la serialización solo guarda `prioridadDeclarada`.
- `src/Battle/Domain/Componentes/Combatiente/SerializadorCombatiente.php:61-63` — el orden de claves no incluye objetivo de prioridad.

**Datos verificados de `storage/data/moves.json`:** hay **15 movimientos con `priority > 0`**: 12 con `target: "normal"` (quick-attack, mach-punch, velocidad_extrema, onda_vacio, ice-shard, shadow-sneak, aqua-jet, water-shuriken, baby-doll-eyes, accelerock, jet-punch, bullet-punch) y 3 autodirigidos (`protect`, `detect`, `endure`, `target: "self"`). Ningún movimiento del catálogo aliado tiene `priority > 0`, por lo que hoy no hay prioridades de apoyo.

**Conclusión:** el jugador no puede elegir porque no hay dato, ni UI, ni validación, ni resolución que lo admita. La prioridad se ejecuta en `confirmarPreparacion()`/`saltarPreparacion()`/`autoSaltar()` antes de que el jugador llegue a la fase normal `player_target`.

---

## 2.3. Fallo 3 — No se puede elegir a quién cubrir

**Causa raíz:** `Cubrir` solo persiste la postura; el objetivo lo decide `EquipoBatalla::normalizarCubridores()` eligiendo el primer aliado vivo distinto del cubridor. No hay UI ni propiedad de componente para el objetivo.

Evidencia:

- `src/Battle/Domain/Enums/PosturaCombate.php:12-18` — `CUBRIR` existe y redirige el 40 % del daño.
- `resources/views/livewire/partials/preparacion-panel.blade.php:7-11` — solo hay `<select>` de postura; no hay selector de aliado cubierto.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:131-156` — `cambiarPostura()` solo hace `setPostura()`, `normalizarCubridores()`, actualiza `$posturas`, limpia prioridad si no es `ATACAR`, sincroniza y guarda. No lee ni escribe objetivo.
- `src/Battle/Domain/EquipoBatalla.php:243-270` — `normalizarCubridores()`:
  - `:248-251` limpia el objetivo si la postura no es `CUBRIR`;
  - `:254-257` conserva el objetivo si existe, no es el propio combatiente y está vivo;
  - `:259-268` si no es válido, elige **el primer aliado vivo distinto** en el orden de la colección.
- `src/Battle/Domain/EquipoBatalla.php:233` — `fromData()` normaliza al crear el equipo; por eso al empezar la batalla ya hay un objetivo automático.
- `src/Battle/Domain/GestorTurnos.php:71-72` — cada `startNewRound()` normaliza ambos equipos.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:145-146` — cambiar postura normaliza inmediatamente.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:197` y `:203` — `aplicar()` normaliza el equipo jugador y el rival.
- `src/Battle/Domain/Danio/AplicadorPosturaDanio.php:47-58` — el daño se reparte solo entre aliados vivos con `postura() === CUBRIR` y `objetivoCubiertoId() === $defensor->id()`; máximo 2 cubridores.
- `src/Battle/Domain/Danio/AplicadorPosturaDanio.php:60-65` — reparto 40/60 con un cubridor y 40/40/20 con dos.
- `src/Battle/Domain/CombatienteVista.php:142` — se expone `objetivoCubierto`, pero ninguna vista lo consume (grep en `resources/views` sin resultados).
- `app/Http/Controllers/TeamController.php:190-207` — el contrato de Equipo solo valida `posturas.*` como `atacar|guardia|cubrir`.
- `src/Equipos/App/ActualizarPosturasEquipo.php:17-20` — solo persiste `$team->posturas`.
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php:81-85` — solo lee la postura persistida; no hay objetivo de Cubrir.
- `app/Models/Team.php:21` y `:31` — `posturas` es JSON; no existe `coberturas`.

**Conclusión:** el objetivo de Cubrir existe en dominio y se serializa (`EstadoTurno::$objetivoCubiertoId`), pero nunca lo elige el jugador. La asignación automática es determinista por orden de colección, no por elección. La reasignación al morir solo ocurre cuando se vuelve a llamar a `normalizarCubridores()`; en mitad de ronda no se llama tras una muerte, por lo que el cubridor puede quedar apuntando a un aliado muerto hasta la siguiente ronda.

---

# 3. Flujo actual

## 3.1. Apertura y cierre de la preparación

- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:52-60` — al inicio de ronda o cuando todos actuaron, avanza ronda; si `rondaPreparada() !== round()` y no está pendiente, llama a `GestorPreparacionBatalla::abrir()`.
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php:180-210` — `avanzarRonda()` dispara efectos de fin/inicio de ronda y `startNewRound()` normaliza cubridores.
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:29-68` — `abrir()`:
  - activa `preparacionPendiente`;
  - limpia `prioridades`, `prioridadesDisponibles`, `posturas`;
  - para cada vivo del team1 inicializa `prioridades[id] = ''` y `posturas[id] = postura actual`;
  - construye `prioridadesDisponibles` solo con `priority > 0` y movimiento disponible;
  - sincroniza, reconstruye `turnQueue`, reinicia animación y guarda.
- `resources/views/livewire/partials/turn-bar.blade.php:24-31` — botón “Preparación” que abre el modal.
- `resources/views/livewire/partials/preparacion-modal.blade.php:13-19` — el panel se incluye dentro del modal; el campo de batalla queda detrás del backdrop, por lo que los objetivos deben elegirse con selects dentro del modal, no clicando cartas.

## 3.2. Prioridades: quién escribe, quién lee, cuándo se limpia

**Escritura del movimiento:**
- `resources/views/livewire/partials/preparacion-panel.blade.php:34-41` — `wire:model="prioridades.{refId}"`.
- No hay llamada al servidor al cambiar el select; el valor viaja en la siguiente petición (confirmar, cambiar postura, etc.).

**Aplicación al confirmar:**
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:158-207` — `aplicar()`:
  - lee `$posturas[id]`;
  - lee `$prioridades[id]`;
  - valida que el índice exista, que `priority > 0` y que `movimientoDisponible()`;
  - si es válido, fuerza `ATACAR`, limpia `objetivoCubiertoId` y `declararPrioridad($candidato)`;
  - si no, `limpiarPrioridad()`;
  - normaliza cubridores, limpia prioridades rivales, normaliza rivales, declara prioridades IA y marca `rondaPreparada`.

**Resolución:**
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:52-147` — `resolver()` recoge prioridades declaradas, ordena por prioridad y velocidad (con Trick Room invertido), y para cada actor:
  - limpia prioridad;
  - comprueba que sigan vivos ambos equipos, que el actor viva y que el movimiento siga disponible;
  - valida `puedeActuar()`;
  - obtiene objetivo con `objetivoPara()`;
  - ejecuta `ResolutorAccionBatalla::aplicarMovimiento(...)` con prefijo `PRIORIDAD:` o `RIVAL PRIORIDAD:`.
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:149-162` — objetivo: actor si autodirigido/`ALL`; si no, `elegirObjetivoPara()`.

**Limpieza:**
- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php:209-214` — `limpiar()` vacía `prioridades`, `prioridadesDisponibles` y `posturas` tras resolver.
- `src/Battle/Domain/GestorTurnos.php:59-64` — `startNewRound()` limpia prioridad de todos.
- `src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:97`, `:108-110` — interrupción/aturdimiento limpian prioridad y, en aturdimiento, también postura y objetivo cubierto.

**Conclusión del flujo:** el objetivo de prioridad no se escribe en ningún sitio y no se lee. La prioridad se resuelve antes de que el jugador seleccione objetivo en el flujo normal.

## 3.3. Cubrir: quién escribe, quién lee, cuándo se limpia

**Escritura de la postura:**
- Pantalla de Equipo: `resources/views/equipos/partials/panel-equipos.blade.php:157-162` — select de postura.
- `app/Http/Controllers/TeamController.php:194-207` — PATCH `/teams/{team}/posturas`, validación `in:atacar,guardia,cubrir`.
- `src/Equipos/App/ActualizarPosturasEquipo.php:17-20` — guarda `teams.posturas`.
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php:81-85` — al construir el equipo lee la postura por slot.
- En batalla: `resources/views/livewire/partials/preparacion-panel.blade.php:7-11` y `GestorPreparacionBatalla::cambiarPostura()`.

**Asignación del objetivo:**
- `src/Battle/Domain/EquipoBatalla.php:233` — al crear el equipo.
- `src/Battle/Domain/EquipoBatalla.php:243-270` — normalización.
- `src/Battle/Domain/GestorTurnos.php:71-72` — al comenzar ronda.
- `GestorPreparacionBatalla.php:145-146`, `:197`, `:203` — al cambiar postura y al aplicar preparación.
- `src/Battle/Domain/Componentes/Agregado/GestorInvocaciones.php:65`, `:86` — al añadir/quitar invocaciones.

**Lectura:**
- `src/Battle/Domain/Danio/AplicadorPosturaDanio.php:47-58` — solo en el momento del daño.
- `src/Battle/Domain/CombatienteVista.php:142` — se expone, pero no se pinta.

**Limpieza:**
- `EquipoBatalla::normalizarCubridores():248-251` — si la postura no es `CUBRIR`.
- `EstadoTurno::reiniciarParaRevivir():96-98` y `reiniciar():240-242` — limpian postura y objetivo.
- `CondicionesCombatiente::aturdir():108-110` — limpia postura, objetivo y prioridad.
- No hay limpieza específica al morir el objetivo cubierto en mitad de ronda; solo se recalcula en la siguiente normalización.

---

# 4. Opciones y recomendación

## 4.1. Fallo 1 — Apoyo sobre retaguardia

**Opción A — Quitar la restricción solo en el camino del jugador (recomendada).**
- Eliminar `$vanguardiaViva` y el filtro de `BatallaVistaPresenter::refsAliado()` (`:128`, `:139-141`). Si `tieneVanguardiaViva()` queda sin uso, eliminarlo.
- Eliminar el bloque de retaguardia en `GestorSeleccionBatalla::previewTarget()` (`:64-67`).
- Eliminar el bloque de retaguardia en `GestorSeleccionBatalla::selectMove()` (`:162-169`).
- Mantener `actorAdmiteAliado()` (sigue siendo necesario para saber si el actor tiene algún movimiento de apoyo).
- Mantener la IA como está: `actorAdmiteSoporte()` y `SelectorAccionIA::agregarAccionesSoporte()` siguen filtrando retaguardia. Ventaja: cambio mínimo, no altera balance de IA ni el modo rival. Desventaja: asimetría jugador/IA que ya existe y debe documentarse.

**Opción B — Quitar la restricción en todas las capas, incluida la IA.**
- Además de A, modificar `AgregadoBatalla::actorAdmiteSoporte()` (`:416-424`) y `SelectorAccionIA::agregarAccionesSoporte()` (`:314-326`).
- Ventaja: coherencia total. Desventaja: cambia el comportamiento de la IA y el balance; requiere autorización explícita y análisis aparte.

**Opción C — Mantener la restricción.**
- No satisface la petición del desarrollador.

**Recomendación:** Opción A en esta tarea. Documentar que la IA conserva la limitación y que alinearla es una decisión posterior.

---

## 4.2. Fallo 2 — Objetivo de prioridad

**Opción A — Codificar `índice|refId` dentro de `$prioridades`.**
- Ventaja: no añade propiedades públicas.
- Desventaja: rompe el tipo esperado (`string` numérico), obliga a parsear, es frágil y oculta el objetivo. No recomendada.

**Opción B — Nueva propiedad de componente `$prioridadesObjetivo` + metadatos de objetivos en `$prioridadesDisponibles` (recomendada).**
- `Combate`: `public array $prioridadesObjetivo = []; // refId actor => refId objetivo`.
- `GestorPreparacionBatalla::abrir()`: reinicializa el mapa; añade a cada item de `prioridadesDisponibles`:
  - `'autodirigido' => bool`;
  - `'objetivos' => list<array{refId:string, nombre:string, posicion:string}>` con los rivales legales para ese actor.
- `preparacion-panel.blade.php`: cuando el movimiento elegido no sea autodirigido, mostrar un `<select wire:model="prioridadesObjetivo.{refId}">` con esos objetivos; usar `wire:model.live` en el select de movimiento para que el panel se re-renderice al cambiar de movimiento.
- `GestorPreparacionBatalla::aplicar()`: validar el objetivo declarado por actor y limpiarlo si es inválido; no necesita guardarlo en dominio.
- `GestorPrioridadesBatalla::resolver()`: leer `$componente->prioridadesObjetivo[$actor->id()] ?? ''` y pasarlo a `objetivoPara()`; revalidar vivo/side/posición en el momento de resolver (otra prioridad anterior puede haber matado al objetivo).
- `limpiar()`: vaciar el mapa.
- Ventajas: aditivo, sin cambios de sesión, sin `SESSION_VERSION`, resolución inmediata en la misma petición. Desventaja: el objetivo no queda persistido en el agregado; si en el futuro la resolución se difiere, habría que moverlo a dominio.

**Opción C — Añadir objetivo al dominio (`EstadoTurno`, `TurnoCombatiente`, `SerializadorCombatiente`).**
- Ventaja: el agregado es la fuente de verdad y sobrevive a la petición.
- Desventaja: toca serialización, `restaurar()`, `limpiarPrioridad()` y el orden de `SerializadorCombatiente`; hay que garantizar defaults para sesiones v9-v21. No es necesario porque `resolver()` se ejecuta en la misma petición que `confirmar()`.

**Recomendación:** Opción B. Es la mínima que satisface al jugador y mantiene compatibilidad.

Reglas de validación propuestas:
- Movimiento autodirigido o `ALL`: el objetivo se ignora y se resuelve al actor.
- Movimiento de ataque: el objetivo debe ser rival vivo. Si el actor está en vanguardia y el rival tiene vanguardia viva, debe ser de vanguardia; si el actor está en retaguardia o no queda vanguardia rival, cualquiera.
- Movimiento de apoyo con prioridad (futuro): el objetivo debe ser aliado vivo distinto del actor y el slug debe estar en `CatalogoEfectosAliado`.
- Si el objetivo declarado no es válido: limpiar y caer al comportamiento actual (`elegirObjetivoPara()`), para no perder la acción.
- Si el objetivo muere antes de resolver: revalidar y caer al automático, o registrar “no encuentra objetivo” si no hay ninguno.

---

## 4.3. Fallo 3 — Objetivo de Cubrir

**Opción A — Mantener la asignación automática y solo mostrar el objetivo.**
- No satisface “tampoco a quién cubrir”; solo mitiga la falta de información.

**Opción B — Nueva propiedad `$coberturas` + selector en preparación + validación al aplicar (recomendada).**
- `Combate`: `public array $coberturas = []; // refId cubridor => refId aliado cubierto`.
- `GestorPreparacionBatalla::abrir()`: reinicializa el mapa; para cada vivo, inicializa con `$combatiente->objetivoCubiertoId()` si es válido, o vacío.
- `cambiarPostura()`: tras `normalizarCubridores()`, sincroniza `$coberturas[$refId]` con el objetivo resultante; si la postura deja de ser `CUBRIR`, lo vacía.
- `aplicar()`: para cada vivo:
  - fija la postura;
  - si es `CUBRIR`, valida el objetivo elegido: mismo equipo, vivo, distinto del cubridor; si es válido, `setObjetivoCubiertoId()`; si no, limpiar y dejar que `normalizarCubridores()` asigne el primero válido;
  - si hay prioridad, fuerza `ATACAR`, limpia objetivo y prioridad (la prioridad anula Cubrir);
  - al final, `normalizarCubridores()` conserva el objetivo válido o rellena el fallback.
- `limpiar()`: vaciar `$coberturas`.
- `preparacion-panel.blade.php`: si `$posturas[$refId] === 'cubrir'`, mostrar un `<select wire:model="coberturas.{{ $refId }}">` con los aliados vivos del team1 distintos de sí mismo. Como el cambio de postura usa `wire:change` y hay roundtrip, el bloque aparece en el render siguiente.
- Ventajas: sin migración, sin cambios de sesión, elección por ronda. Desventaja: no se configura desde Equipo; el objetivo se elige en cada batalla/ronda.

**Opción C — Persistir el objetivo de Cubrir en Equipo.**
- Requiere decidir el contrato: `teams.posturas` pasaría de `slot => string` a `slot => {postura, cubrir}` o añadir una columna/tabla `coberturas`.
- Archivos afectados: `TeamController`, `ActualizarPosturasEquipo`, `Team` (casts), `panel-equipos.blade.php`, `ConstruirEquipoJugador`, `DatosPokemonBatalla`, `EquipoBatalla::fromData`.
- Ventaja: configuración persistente. Desventaja: migración/contrato, fuera del alcance mínimo y con riesgo para datos existentes.

**Opción D — Asignación determinista por slot + visualización.**
- No permite elegir; no recomendada.

**Recomendación:** Opción B. Si el desarrollador quiere persistencia desde Equipo, abrir una tarea separada con decisión de esquema.

Casos de Cubrir:
- No se puede cubrir a sí mismo.
- Solo aliados vivos del team1.
- Si el objetivo muere en mitad de ronda: hoy el cubridor deja de redirigir hasta la siguiente ronda. Si se desea reasignación inmediata, llamar a `normalizarCubridores()` tras `procesarDebilitamientos()` o tras cada acción; es un cambio de comportamiento que debe aprobarse.
- Dos cubridores pueden apuntar al mismo aliado; `AplicadorPosturaDanio` limita a 2 y reparte 40/40/20.
- Un cubridor puede cubrir a otro cubridor; no hay cadena de redirección, solo un nivel.
- Si el cubridor muere, deja de redirigir (solo se recorren vivos).

---

# 5. Contratos y archivos afectados

## 5.1. Contrato Livewire propuesto

Propiedades nuevas en `App\Livewire\Combate`:
```php
/** @var array<string, string> refId actor => refId objetivo de prioridad */
public array $prioridadesObjetivo = [];

/** @var array<string, string> refId cubridor => refId aliado cubierto */
public array $coberturas = [];
```

Propiedades existentes que se amplían:
- `$prioridadesDisponibles`: cada item pasa de `{index, nombre, prioridad}` a `{index, nombre, prioridad, autodirigido, objetivos}`. Es aditivo; el Blade actual sigue funcionando.
- `$prioridades` no cambia de forma: sigue siendo `refId => índice`.
- `$posturas` no cambia.

Métodos públicos:
- **No es necesario añadir métodos** si se usa `wire:model` para los nuevos mapas y `wire:model.live` para el movimiento de prioridad.
- Si se quiere validación inmediata al cambiar objetivo, se pueden añadir métodos aditivos:
  - `public function seleccionarObjetivoPrioridad(string $refId, string $objetivoRefId): void`
  - `public function seleccionarCubierto(string $refId, string $objetivoRefId): void`
  - Ambos delegarían en `GestorPreparacionBatalla` y validarían en servidor. Trade-off: más contrato y más roundtrips; no imprescindible porque la validación final en `aplicar()`/`resolver()` ya protege.

**Nota de contrato:** TASK-030 congeló el contrato público de `Combate` en 32 propiedades/18 métodos. Añadir dos propiedades públicas es una ampliación, no una rotura de compatibilidad de Livewire, pero requiere aprobación explícita del desarrollador.

## 5.2. Backend

Archivos a modificar:

| Archivo | Cambio |
|---|---|
| `app/Livewire/Combate.php` | Añadir `$prioridadesObjetivo` y `$coberturas` con defaults. |
| `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` | Inicializar/limpiar mapas; ampliar `prioridadesDisponibles`; validar prioridad y Cubrir en `aplicar()`; sincronizar `coberturas` en `cambiarPostura()`. |
| `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Leer objetivo declarado en `resolver()`; validar vivo/side/posición en `objetivoPara()`; fallback automático. |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminar los dos bloqueos de retaguardia aliada (fallo 1). |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminar el filtro de retaguardia en `refsAliado()` y, si queda sin uso, `tieneVanguardiaViva()`. |
| `src/Battle/Domain/EquipoBatalla.php` | Sin cambios obligatorios: `normalizarCubridores()` ya conserva objetivos válidos y rellena fallback. Opcional: helper de validación de objetivo cubierto. |
| `src/Battle/Domain/CombatienteVista.php` | Opcional: añadir nombre del aliado cubierto para mostrarlo en la carta de batalla. |
| `src/Battle/Domain/AI/SelectorAccionIA.php` | Solo si se aprueba alinear la IA con el apoyo a retaguardia (opción B del fallo 1). |
| `src/Battle/Domain/AgregadoBatalla.php` | Solo si se aprueba alinear `actorAdmiteSoporte()` (opción B). |

No se necesitan cambios en:
- `BattleSessionService` ni `SESSION_VERSION`, si el objetivo de prioridad se queda en el componente.
- `EstadoTurno`, `TurnoCombatiente`, `SerializadorCombatiente`, si no se persiste el objetivo de prioridad.
- Migraciones ni modelos, si Cubrir se elige solo en preparación.

## 5.3. Frontend

| Archivo | Cambio |
|---|---|
| `resources/views/livewire/partials/preparacion-panel.blade.php` | Añadir selector de objetivo de prioridad (condicional a `autodirigido`) y selector de aliado cubierto (condicional a postura `cubrir`); actualizar textos de ayuda. |
| `resources/views/livewire/partials/preparacion-modal.blade.php` | Sin cambios obligatorios; valorar `modal-lg` ya está. |
| `resources/views/livewire/_pokemon-card.blade.php` | Opcional: badge “CUBRE A …” usando el nombre que exponga el presentador. |
| `resources/views/equipos/partials/panel-equipos.blade.php` | Solo si se persiste el objetivo desde Equipo (opción C). |
| `resources/views/livewire/combate.blade.php` | Sin cambios: ya incluye el modal de preparación. |

## 5.4. Contratos de dominio que no deben romperse

- `MovimientoBatalla::esAutodirigido()` sigue siendo la única fuente para saber si un movimiento apunta al actor.
- `CatalogoEfectosAliado::para($slug)` sigue siendo el único predicado de modo aliado.
- `AplicadorPosturaDanio` sigue leyendo `postura() === CUBRIR` y `objetivoCubiertoId()`.
- `EquipoBatalla::normalizarCubridores()` sigue siendo la red de seguridad.
- La IA normal no debe cambiar salvo aprobación; el modo propio/rival se mantiene.

---

# 6. Casos límite y riesgos

## 6.1. Casos límite

- **Aliado muerto:** el selector solo debe listar vivos; el servidor debe revalidar `estaVivo()` al aplicar y al resolver. Si muere después de seleccionar, fallback automático.
- **Autoobjetivo:** Cubrir no puede apuntar a sí mismo; la lista de prioridad no debe incluir al actor; `selectMove()` ya solo permite autoobjetivo a movimientos autodirigidos (`:128-140`).
- **Prioridad autodirigida:** `protect`, `detect`, `endure` no necesitan objetivo; el selector debe ocultarse o ignorarse; `objetivoPara()` ya devuelve el actor.
- **Cambio de postura:** una postura no `ATACAR` limpia prioridad (`GestorPreparacionBatalla.php:149-152`). Cambiar de `CUBRIR` a otra debe limpiar `$coberturas` y el objetivo en dominio.
- **Prioridad que anula Cubrir:** ya ocurre en `aplicar()` (`:185-190`): fuerza `ATACAR` y limpia `objetivoCubiertoId`. El nuevo mapa `$coberturas` debe limpiarse/ignorarse para no mostrar un objetivo obsoleto.
- **Reasignación al morir:** hoy solo en la siguiente normalización (inicio de ronda o cambio de postura). Decidir si se quiere inmediata; si sí, llamar a `normalizarCubridores()` tras `procesarDebilitamientos()` o tras cada acción.
- **Sesiones antiguas:** si no se añaden campos de dominio, no hay impacto. Si se añade objetivo de prioridad a `EstadoTurno`, hay que dar default `''` en `TurnoCombatiente::desdeArray()`, incluirlo en `serializar()`, `restaurar()`, `SerializadorCombatiente::ORDEN` y limpiarlo en `limpiarPrioridad()`. `SESSION_VERSION` podría mantenerse, pero hay que verificar payloads v9-v21.
- **Objetivo de prioridad muerto por una prioridad anterior:** revalidar en `resolver()` y caer al automático; no ejecutar sobre un muerto.
- **Objetivo de prioridad ilegal por posición:** si el actor es vanguardia y el rival tiene vanguardia viva, el objetivo debe ser de vanguardia; la lista y la validación deben reflejarlo.
- **`DESAFIO`:** el flujo normal de `selectMove()` sobrescribe el defensor con el objetivo forzado (`GestorSeleccionBatalla.php:178-183`), pero `GestorPrioridadesBatalla::resolver()` no aplica `DESAFIO`. Si se añade objetivo de prioridad, decidir si `DESAFIO` debe anularlo; hoy no lo hace.
- **Objetivo de apoyo con prioridad (futuro):** ningún movimiento del catálogo aliado tiene prioridad >0. Si se añade, `resolver()` debe distinguir aliado y llamar a `aplicarMovimientoAliado()`, no a `aplicarMovimiento()`.
- **Dos cubridores mismo objetivo:** permitido; máximo 2; el orden de `vivos()` decide. Si hay 3, el tercero no redirige.
- **Cubridor cubriendo a otro cubridor:** permitido; no hay cadena, solo un nivel.
- **Invocaciones:** `GestorInvocaciones` normaliza al añadir/quitar; el objetivo de una invocación puede perderse y reasignarse.
- **Aturdimiento/interrupción:** ya limpian postura, objetivo y prioridad en dominio (`CondicionesCombatiente.php:97-110`); el componente debe reflejarlo en el siguiente render.

## 6.2. Riesgos

| Riesgo | Nivel | Impacto | Mitigación |
|---|---|---|---|
| Eliminar la restricción de retaguardia en el jugador pero no en la IA | Medio | Asimetría y posible confusión; no rompe nada | Documentar; alinear IA en tarea separada con aprobación. |
| Eliminar la restricción también en la IA sin querer | Alto | Cambia balance y comportamiento rival | Limitar el cambio a `BatallaVistaPresenter` y `GestorSeleccionBatalla`; no tocar `actorAdmiteSoporte` ni `SelectorAccionIA`. |
| Añadir propiedades públicas a `Combate` | Alto | Amplía el contrato congelado 32/18 y el snapshot de Livewire | Aprobación explícita; defaults seguros; no eliminar propiedades existentes. |
| Objetivo de prioridad en componente y no en dominio | Medio | Si la resolución se difiere en el futuro, el objetivo se pierde | Documentar que la resolución es inmediata; si se difiere, mover a `EstadoTurno`. |
| Validación insuficiente del objetivo de prioridad | Alto | Acción sobre aliado/rival muerto o ilegal | Validar en `aplicar()` y revalidar en `resolver()`; fallback automático. |
| `normalizarCubridores()` sobrescribe la elección del jugador | Medio | El jugador elige un aliado y el sistema lo cambia | Validar antes; solo reasignar si el objetivo es inválido o muerto; mostrar el objetivo final. |
| Muerte del objetivo de Cubrir a mitad de ronda | Medio | El cubridor no redirige hasta la siguiente ronda | Decidir producto; si se quiere inmediato, normalizar tras debilitamientos. |
| `DESAFIO` ignorado en prioridades | Medio | El jugador elige un objetivo que no respeta el desafío | Decidir si `DESAFIO` anula la elección; hoy no se aplica en prioridades. |
| Selector de objetivo dentro del modal | Medio | El campo de batalla está detrás del backdrop; no se puede clicar | Usar `<select>` dentro del modal, no clics en cartas. |
| `wire:model.live` en el movimiento de prioridad | Bajo | Más peticiones Livewire | Usar solo en el select de prioridad; el resto puede ser diferido. |
| Cambio de forma de `$prioridadesDisponibles` | Bajo | Solo lo consume `preparacion-panel` | Mantener las claves existentes y añadir nuevas; no renombrar. |
| Reasignación automática de Cubrir a un aliado no deseado | Medio | El jugador no controla el 40 % del daño | Selector explícito + fallback visible; no asignar silenciosamente si hay elección. |
| Tests/cobertura | No aplica | El proyecto no ejecuta tests en este flujo | Verificación estática y manual. |

---

# 7. Verificación propuesta

## 7.1. Verificación estática

Para la implementación (no ejecutada aquí):
- `php -l` sobre cada archivo PHP modificado.
- `vendor/bin/phpstan analyse --no-progress` con la configuración del proyecto.
- `vendor/bin/pint --dirty --format agent` para estilo.
- `php artisan view:cache` para compilar Blade y detectar errores de sintaxis.
- `php artisan route:list` solo si se toca alguna ruta (no debería).
- Grep de control:
  - que no queden referencias a `tieneVanguardiaViva()` en `BatallaVistaPresenter` si se elimina;
  - que `$prioridadesObjetivo` y `$coberturas` se inicialicen en `abrir()` y se limpien en `limpiar()`;
  - que `GestorPrioridadesBatalla` no llame a `objetivoPara()` sin el objetivo declarado;
  - que `normalizarCubridores()` no se haya duplicado.
- Revisar que no se haya modificado `BattleSessionService`, `SESSION_VERSION`, `tests/`, `composer.json`, migraciones ni dependencias.

## 7.2. Verificación manual

1. **Danza Espada en retaguardia:** equipo con vanguardia viva y aliado en retaguardia; actor con `swords-dance`; clicar la carta del aliado en retaguardia; debe ser seleccionable, mostrar “SOBRE ALIADO +2 Ataque” y aplicar el buff al aliado.
2. **Resto del catálogo:** repetir con `recover`, `sand-tomb`, `fire-spin`, `whirlpool`, `nasty-plot`, `bulk-up`, etc., sobre retaguardia.
3. **Rivales:** verificar que un actor en vanguardia sigue sin poder seleccionar la retaguardia rival mientras la vanguardia rival esté viva.
4. **Prioridad con objetivo:** actor con `quick-attack`; en preparación, elegir movimiento y un rival legal; confirmar; el ataque debe ir al elegido.
5. **Prioridad sin objetivo:** dejar “Automático”; debe comportarse como hoy.
6. **Prioridad autodirigida:** `protect`/`detect`/`endure`; no debe pedir objetivo y debe resolver sobre el actor.
7. **Objetivo de prioridad muerto:** dos prioridades al mismo rival; la primera lo mata; la segunda debe revalidar y no golpear a un muerto.
8. **Cubrir con objetivo:** A en `CUBRIR`, elegir B; atacar a B; A debe recibir el 40 % y verse el objetivo elegido.
9. **Cubrir inválido:** no debe permitir autoobjetivo ni aliado muerto; el servidor debe rechazar/fallback.
10. **Muerte del objetivo cubierto:** B muere; comprobar si A deja de redirigir y si al inicio de la siguiente ronda se reasigna.
11. **Prioridad + Cubrir:** A en `CUBRIR` con prioridad; al confirmar debe quedar `ATACAR` y sin objetivo de Cubrir.
12. **Cambio de postura:** `CUBRIR` → `GUARDIA`/`ATACAR` limpia el objetivo; `GUARDIA`/`ATACAR` → prioridad fuerza `ATACAR`.
13. **Sesión antigua:** cargar una batalla iniciada antes del cambio; la preparación debe abrir sin errores y con mapas vacíos.
14. **IA:** una batalla normal contra IA no debe cambiar su comportamiento si se aplica la opción A del fallo 1; si se aplica la opción B, comprobar que la IA sigue actuando y que no hay errores.
15. **Saltar preparación:** no debe quedar ningún objetivo de prioridad/Cubrir obsoleto.
16. **Dos cubridores mismo objetivo:** verificar reparto 40/40/20.

No se deben crear ni ejecutar tests según las restricciones del proyecto.

---

# 8. Preguntas abiertas para el desarrollador

1. **Alcance del fallo 1:** ¿el apoyo a retaguardia debe permitirse solo al jugador o también a la IA? La opción recomendada cambia solo al jugador.
2. **Obligatoriedad del objetivo de prioridad:** ¿el jugador debe estar obligado a elegir objetivo, o se mantiene “Automático” como fallback?
3. **`DESAFIO`:** si un Pokémon tiene `DESAFIO` y el jugador elige otro objetivo de prioridad, ¿debe prevalecer el objetivo forzado?
4. **Cubrir desde Equipo:** ¿basta con elegir a quién cubre en la preparación de cada ronda, o se quiere persistir en la pantalla de Equipo? Persistirlo exige decidir esquema (`teams.posturas` ampliado o nueva columna/tabla).
5. **Reasignación al morir:** si el aliado cubierto muere a mitad de ronda, ¿el cubridor debe reasignarse inmediatamente o esperar a la siguiente ronda? Hoy espera.
6. **Prioridades de apoyo:** ningún movimiento aliado tiene `priority > 0`; ¿se prevé añadirlos? Si sí, `resolver()` debe enrutar a `aplicarMovimientoAliado()`.
7. **UI:** ¿el objetivo de prioridad y Cubrir se elige con desplegables dentro del modal de preparación (recomendado, porque el campo está detrás del backdrop) o se quiere otro patrón?
8. **Contrato público:** ¿se autoriza ampliar `Combate` con `$prioridadesObjetivo` y `$coberturas`? TASK-030 congeló 32 propiedades/18 métodos.

---

# Estado del trabajo y tareas restantes

- **Máximo de pasos alcanzado.** No se realizaron llamadas a herramientas después del aviso; no se modificó ningún archivo.
- **Completado:** lectura y análisis estático de los tres flujos, localización de la causa raíz con evidencia `archivo:línea`, identificación de archivos backend/frontend, contratos, casos límite, riesgos y verificación propuesta.
- **No realizado:** implementación, escritura de `analysis.md`, ejecución de tests, cambios de sesión, migraciones o dependencias.
- **Siguiente paso recomendado:** que el `architect` convierta este análisis en un plan con versión y que el desarrollador apruebe explícitamente el alcance, especialmente:
  1. opción A del fallo 1 (solo jugador);
  2. opción B del fallo 2 (`$prioridadesObjetivo` en componente);
  3. opción B del fallo 3 (`$coberturas` en componente y selector en preparación);
  4. decisión sobre `DESAFIO`, reasignación al morir y persistencia desde Equipo.
