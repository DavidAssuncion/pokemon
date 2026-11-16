# TASK-043 — Arquitectura y diseño

- **Estado:** COMPLETADO
- **Arquitecto:** `architect`, sesión `ses_e3a54706dffeePekePdlEh2r33`.
- **Nota:** se reproduce el informe del arquitecto sin alterar sus conclusiones. No se modificó ningún archivo de aplicación.

---

# TASK-043 — Diseño del sistema de misiones, narrativa y desbloqueo de hábitats

## Resumen ejecutivo

El juego tiene un motor de combate sólido, una economía generosa y muchos sistemas satélite, pero le falta **dirección**: los 56 hábitats están abiertos desde el minuto uno, la ruta no persigue nada y ninguna voz explica por qué hacer A y no B. El sistema de misiones no debe añadir una lista de tareas: debe **convertir el mapa en un objetivo**.

**Recomendación:** construir *El Cuaderno de la Expedición*, con tres capas subordinadas y un solo sistema:

1. **Campaña de la Expedición** (columna vertebral): 8 capítulos por provincia que reabren hábitats sellados por «la Bruma», con 3-5 hitos cada uno.
2. **Encargos de campo** (textura recurrente): 3 encargos diarios por jugador, emitidos por tres voces del campamento, aceptados de forma pasiva.
3. **Diario de campo** (voz y colección): notas de una línea por hábitat, sin diálogos ni árboles de decisión.

Decisiones estructurales que sostienen la propuesta:

- **Sin moneda nueva.** Se paga en caramelos de familia/EV/tipo, objetos de forja, capturas en cola y, muy ocasionalmente, un huevo o un reclutado directo.
- **Cuatro tipos de misión:** encargo (diario, pasivo), parte diaria, expedición semanal (elegir 1 de 3) e hito de campaña.
- **Generación determinista** por usuario + periodo + hábitat + slot, con **presupuesto de tiempo** (4-8 min por encargo), no con cantidades escaladas por nivel.
- **«Capturar» = el momento de la captura** (incremento de `reclutables` o creación directa de un reclutado), nunca el reclutamiento posterior.
- **Corte local**: medianoche `Europe/Madrid` para diarias, lunes 00:00 para semanales. Todo se resuelve on-read, sin depender del scheduler.
- **Idempotencia real**: ledger de eventos con clave única + contadores de progreso, recompensa automática al completar.
- **Desbloqueo blando**: campaña **o** nivel, lo que llegue antes; los hábitats bloqueados se ven y se desean. Los jugadores existentes conservan todo abierto.
- **MVP acotado**: 3 tablas, 6 arquetipos, parte diaria + 3 encargos, sin campaña ni desbloqueo, detrás de un flag.

El riesgo principal no es técnico: es que las misiones se conviertan en un segundo trabajo. El diseño lo combate con caducidad sin castigo, recompensa automática, máximo de 6 tarjetas visibles y elección real solo donde aporta (semanal e historia).

---

## 1. Diagnóstico de diseño

**Qué falta hoy**

- **Dirección.** El jugador elige hábitat por inercia, no por un objetivo. No hay razón para variar de zona salvo que el pool le interese.
- **Un porqué.** No hay emisor, conflicto ni promesa. Las provincias, los colores de bonguri y las medallas son bonitos pero mudos.
- **Dosificación.** Todo está abierto: no hay sensación de mapa que se abre. `min_lvl_1/2/3` existe pero nadie lo escribe, y solo afecta a exploración.
- **Registro.** Ruta, entrenador, gimnasio y mazmorra no dejan rastro de a quién derrotaste. Sin ledger no hay objetivos verificables ni idempotencia.
- **Razón para tocar sistemas laterales.** Granja, forja, huevos y evolución funcionan, pero nada te empuja a probarlos si no te apetece.

**Qué oportunidades hay**

- 56 hábitats con nombres evocadores y 7 provincias con identidad cromática (bonguri) ya sembradas.
- 18 gimnasios con medalla y nivel mínimo: faros naturales de la campaña.
- Una Pokédex que registra visto/atrapado y una colección que ya es el motor de casi todo.
- Multiplicadores de recompensa ya calibrados (×1 ruta, ×2 entrenador, ×10 gimnasio, ×70 mazmorra): permiten presupuestar misiones sin inventar economía.
- Una vista de misiones huérfana que, aunque no sirve tal cual, confirma que el hueco de UI ya estaba previsto.

**Principios que debe cumplir el sistema**

1. **No chores.** Nada de streaks, ni castigo por no entrar, ni recompensa diaria por conectar.
2. **Variedad con intención.** Cada periodo debe empujar a un sistema distinto, no repetir «derrota 10».
3. **Agencia donde importa.** Lo diario es pasivo; lo semanal y lo de historia se eligen.
4. **Narrativa mínima.** Una línea por misión, un emisor reconocible, cero diálogos ramificados.
5. **Respeto a la economía.** Sin moneda, con topes semanales y pagando en la moneda del sistema al que apunta la misión.
6. **Reparabilidad.** Una misión rota se invalida sin castigo; una recompensa nunca se paga dos veces.
7. **El juego base sigue siendo el motor.** La misión exige jugar; no lo sustituye.

---

## 2. Tres direcciones conceptuales

| | **A. Tablón del guardabosques** | **B. Campaña de la Expedición** | **C. Diario de campo científico** |
|---|---|---|---|
| **Fantasía** | Un guardabosques clava encargos en cada hábitat; tú eres quien los resuelve. | Una expedición cartográfica reabre las sendas que la Bruma borró; tú eres el cartógrafo. | Un naturalista estudia la fauna de cada zona y completa un censo. |
| **Bucle** | Entrar al hábitat → coger 1-3 encargos → hacer la actividad → cobrar. | Capítulo → 3-5 hitos → desbloqueo de hábitats/gimnasio → siguiente provincia. | Observar/derrotar/capturar → rellenar tareas por especie y hábitat → rango de investigación → ventajas. |
| **Pros** | Barato, local, encaja en la pantalla de hábitat, procedural por naturaleza. | Da dirección, usa provincias/gimnasios/colores, justifica el desbloqueo, arco de primer mes claro. | Usa Pokédex y colección, es emergente y repetible, no necesita NPCs. |
| **Contras** | Sin arco ni desbloqueo; se parece demasiado a los entrenadores diarios; no saca al jugador de su hábitat. | Requiere autoría de texto y hitos; riesgo de pasillo; contenido de un solo uso. | Se confunde con la Pokédex; textura de checklist; no resuelve la falta de dirección. |
| **Encaje** | Alto con la UI actual, bajo con la progresión. | Alto con provincias, gimnasios y desbloqueo; medio con el sandbox. | Alto con Pokédex y colección; bajo con la economía y la narrativa. |

**Recomendación: B como columna vertebral, con A como textura y C como voz.**

No son tres sistemas: es **un** sistema con tres capas. La campaña da el *para qué* (reabrir el mapa), los encargos dan el *qué hago hoy* (3 tarjetas, no 20) y el diario da el *quién me lo cuenta* (tres voces y notas de una línea). A sola no arregla el vacío de progresión; C sola duplica la Pokédex; B sola sería un pasillo de un solo uso. Juntas se corrigen.

**Por qué B y no A:** el agujero más caro del juego es que el mapa no se abre. A produce contenido infinito pero deja el mapa plano. **Por qué B y no C:** la Pokédex ya existe y ya colecciona; C sería una segunda capa de deberes sobre la misma acción.

**Emisores (tres voces, cero retratos):**

- **El Consejo de la Expedición** — campaña. Su sello es un mapa doblado.
- **Nara, la guardabosques** — ruta, entrenadores, exploración. Sello: bonguri verde.
- **Tilo, el bongurero** — granja, plantaciones, forja. Sello: bonguri rojo.
- **Sel, la archivista** — Pokédex, huevos, evolución, reclutamiento. Sello: bonguri azul.

Cada tarjeta muestra un sello de color y una línea de voz. Nada más.

---

## 3. Taxonomía de misiones

| Tipo | Simultáneas | Aceptación | Duración | Caducidad | Recompensa |
|---|---|---|---|---|---|
| **Encargo de campo** | 3 activos | Pasiva (auto) | 4-8 min | Fin del día local | Pequeña, en la moneda del sistema |
| **Parte diaria** | 1 | Pasiva (auto) | 8-12 min | Fin del día local | Media + algo de EXP |
| **Expedición semanal** | 1 (de 3 opciones) | Manual: elegir 1 de 3 | 45-90 min repartidos | Domingo 23:59 local | Grande + 1 garantía |
| **Hito de campaña** | 1 activo | Automático y secuencial | 10-30 min | Permanente | Media + desbloqueo + nota |
| *(v2)* **Contrato de entrenador** | — | Al vencer 3 veces al mismo entrenador | 1 semana | 7 días | Objeto raro |

**Por qué estos y no más.** Cuatro tipos y un máximo de seis tarjetas visibles (3 + 1 + 1 + 1). Más tipos convierten el cuaderno en un panel de deberes. Los «contratos de entrenador» se dejan para v2 porque exigen identidad de NPC, que hoy no existe.

**Reglas transversales**

- Una misión caducada **no penaliza**: desaparece y se sustituye. No se pierde progreso de otras.
- Los encargos y la parte diaria se aceptan solos al abrir el cuaderno; no hay ritual de aceptación.
- La semanal es la única decisión real: tres opciones de sistemas distintos.
- Los hitos son secuenciales dentro del capítulo, pero el jugador elige el orden de los opcionales.
- Se puede **descartar** un encargo una vez al día sin coste (válvula de escape).

### Ejemplos concretos

**Encargos (3 por día, rotan por hábitat):**

1. *Ronda de vigilancia* — Derrota 8 Pokémon de tipo Bicho en Bosque Brumoso (nivel 1). → 5 caramelos de familia del pool, 8 de EV, 1 de tipo Bicho.
2. *El que susurraba* — Derrota 3 Pokémon de la familia de Gastly en Bosque Brumoso. → 6 caramelos de familia Gastly, 1 bonguri verde.
3. *Turno doble* — Vence a 2 entrenadores de nivel 2 en un hábitat desbloqueado. → 7 caramelos de familia, 10 de EV, 1 baya Oran.
4. *Pies ligeros* — Completa una exploración de al menos 4 horas. → 6 caramelos de familia, 8 de EV, 2 bayas.
5. *Primerizo* — Avista 4 especies que no tenías vistas. → 4 caramelos de familia, 1 bonguri del color de la provincia.
6. *Yunque y martillo* — Fabrica 2 objetos cualesquiera. → 1 material de recipiente, 4 caramelos de EV.

**Parte diaria (una):**

- *Parte de guardia* — Derrota 15 Pokémon y completa 1 exploración. → 12 caramelos de familia, 16 de EV, 3 de tipo, 2 objetos, EXP equivalente a media victoria de ruta.

**Semanales (tres opciones, se elige una):**

- *La ruta del bonguri* — Derrota 30 Pokémon en 3 hábitats distintos de una misma provincia y cosecha 5 bayas. → 40 de familia, 45 de EV, 5 bonguris del color provincial.
- *Censo de la costa* — Avista 8 especies de Mutxamel y captura 2. → 35 de familia, 1 captura asegurada en la cola, 2 bonguris azules.
- *El yunque de la semana* — Fabrica 5 objetos distintos y sube un nido al nivel 2. → 30 de familia, 3 mejoras, 1 baya rara.

**Hitos:** ver la tabla de la sección 6.

---

## 4. Catálogo de arquetipos de objetivo

| Arquetipo | Datos que usa | Fuente actual | Hueco técnico |
|---|---|---|---|
| Derrotar N de tipo T en hábitat H | `team2` (especies) → `pokemon_types` | Cierre de batalla (ruta/entrenador/gimnasio/mazmorra) | No hay log; hay que resolver tipos en el cierre |
| Derrotar N de especie S | `pokemon.id` | `team2` | Ninguno |
| Derrotar N de familia F | `pokemon.evolution_chain_id` | `team2` + mapa de cadenas | Ninguno (mapa ya existe) |
| Derrotar N en hábitat H / nivel L | `meta['habitat_id']`, `meta['nivel']` | Meta de sesión | Persistir la meta en el evento |
| Vencer N entrenadores (nivel/índice) | `trainer_combat_log` | Log diario existente | Contar solo victorias del día |
| Vencer a un entrenador concreto | `habitat_id + nivel + trainer_index` | Mismo log | Ninguno |
| Completar gimnasio / etapa | `gym_progress`, meta | `RegistrarResultadoGimnasio` | Ninguno |
| Completar piso de mazmorra | `dungeon_progress`, meta | `RegistrarResultadoMazmorra` | Ninguno |
| Completar exploración ≥ X h / con resultado | `exploraciones_activas.eventos` | `FinalizarExploracionHandler` | Emitir un evento-resumen al cerrar |
| Capturar N (cola o directo) | `reclutables`, `reclutados` | `PersistirRecompensas`, `OtorgarRecompensasRuta` | Decidir semántica y emitir en el punto de captura |
| Avistar N especies nuevas | `pokedex.visto` | `ActualizarPokedexJob` | Saber cuáles eran nuevas en ese evento |
| Eclosionar N huevos | `huevos.eclosionado_en` | `EclosionarHuevo` | Ninguno |
| Fabricar N objetos / objeto concreto | `player_inventory` | `Fabricar` | No hay id de operación; generar clave |
| Plantar / cosechar N | `nest_plantings` | `PlantarBaya`, `CosecharTodo` | Clave estable por plantación |
| Subir nido a nivel N | `nests` | Caso de uso de mejora de nido | Ninguno |
| Evolucionar N | `reclutados.pokemon_id` | `ReclutadoController::evolucionar` | Ninguno |
| Reclutar N desde la cola | `reclutables` | `ReclutamientoController::recruit` | Ninguno |
| Ganar con equipo monocolor | `team1` (tipos) | Cierre de batalla | Resolver tipos del equipo |
| Ganar sin debilitar a nadie | `team1` (vivos) | Cierre de batalla | Ninguno |
| Ganar con un reclutado concreto | `team1` (ids) | Cierre de batalla | Ninguno |
| Visitar N hábitats distintos | Sin registro | — | **Hueco**: evento de visita (fácil, pero farmeable) |
| Racha de N victorias | Sin registro | — | **Hueco**: contador de racha (v2) |

**Decisión de semántica de captura.** «Capturar» cuenta **cuando la captura ocurre**: incremento de `reclutables` o creación directa de un `Reclutado` (shiny de ruta, huevo, reclutamiento). **No** cuenta al convertir `reclutables` en `reclutados`; eso es «reclutar», un arquetipo distinto. `pokedex.atrapado` no sirve como fuente porque solo se marca al crear el reclutado.

**Regla de oro de integración.** Todos los arquetipos se alimentan de un único `EventoJuego` con tipo, fuente, hábitat, nivel, especies derrotadas (id/tipo/familia), capturas y resultado. Ningún flujo llama a «misiones» con su propia lógica.

---

## 5. Generación procedural

**Objetivo:** que dos jugadores distintos vean cosas distintas, que el mismo jugador vea lo mismo al recargar, y que ninguna misión sea imposible ni trivial.

**Semilla.** `crc32(user_id | periodo | hábitat | slot | versión_catálogo)`. `crc32` es estable entre bases de datos y PHP, y la versión del catálogo invalida misiones si cambian los arquetipos. Nada de `rand()` en runtime: el generador es una función pura de la semilla.

**Algoritmo, en cinco pasos**

1. **Elegir arquetipos elegibles.** Se filtran por el pool real del hábitat (un tipo debe tener ≥3 especies; una familia, ≥2), por el estado del jugador (granja existente, huevos disponibles, nivel de mazmorra alcanzable) y por el nivel de cuenta. Se excluyen los arquetipos usados en los dos periodos anteriores y los objetivos idénticos vistos en 7 días.
2. **Elegir arquetipo y objetivo** con dos draws independientes de la semilla (LCG determinista documentado, no aleatoriedad del sistema).
3. **Calcular cantidad por presupuesto de tiempo.** Cada arquetipo tiene un coste unitario: derrota 25 s, entrenador 120 s, captura 60 s esperados, fabricar 30 s, cosechar 10 s, exploración 1 expedición. La cantidad sale del presupuesto (4-8 min encargo, 8-12 parte, 45-90 semanal). **El nivel no multiplica cantidades**; solo cambia la banda de recompensa. Esto evita pedir 200 derrotas a un nivel 100.
4. **Elegir recompensa** en la banda del arquetipo, pagando en el sistema al que apunta (sección 8).
5. **Persistir la instancia** con semilla, versión y objetivo ya resuelto (ids y nombres). No se regenera dentro del periodo.

**Anti-repetición.** Historial de `(arquetipo, objetivo)` por usuario; penalización de los usados recientemente. Si el pool elegible baja de 3, se permite repetir, pero nunca el mismo objetivo dos días seguidos. Además, el generador pondera los sistemas que el jugador **no ha tocado en 7 días** (última exploración, última forja, última cosecha), lo que produce variedad sin azar puro.

**Anti-imposible**

- No generar objetivos en hábitats bloqueados.
- No generar «eclosiona» si no hay huevo ni forma de ponerlo.
- No generar «sube un nido» si no hay granja en ese hábitat.
- No generar «completa el piso N» si `current_floor < N-1`.
- No generar capturas de especies con `capture_rate` 0.
- Validación server-side en el momento de conceder, no solo al generar.

**Anti-trivial**

- Mínimos por arquetipo: ≥3 derrotas, ≥1 entrenador, ≥1 exploración de ≥1 h, ≥1 objeto fabricado.
- Prohibido «derrota 1 Pokémon».
- La parte diaria exige dos cláusulas (una de combate y una de sistema lateral).

**Que el jugador no vea misiones rotas**

- La tarjeta muestra el objetivo resuelto con nombre propio y hábitat, no una plantilla.
- Si una misión deja de ser válida (hábitat bloqueado, objetivo eliminado del pool), pasa a `invalidada`, se sustituye en la siguiente lectura y no consume el slot.
- Botón «No puedo con esta» una vez al día: regenera el encargo con la misma semilla + 1.
- Las misiones nunca caducan a mitad de una acción: si el evento ocurre antes del corte, cuenta.

**Determinismo y concurrencia.** La generación se hace on-read con `insertOrIgnore` sobre la clave única `(user_id, period_type, period_key, scope_key, slot)`. Dos peticiones simultáneas generan lo mismo por construcción; la que pierde lee la fila ganadora.

---

## 6. Narrativa

**Marco.** Hace generaciones, las provincias estaban unidas por sendas que los cartógrafos recorrían a pie. Una marea de niebla —**la Bruma**— borró los caminos y, con ellos, la memoria de lo que había en cada uno. Los gimnasios siguen en pie porque son **faros**: su medalla mantiene despejado un tramo. El jugador es el último cartógrafo de la Expedición y reabre el mapa demostrando que conoce la fauna de cada zona.

**Por qué encaja sin reescribir nada**

- Las provincias ya existen y ya tienen color de bonguri: cada una es un capítulo y su color es su memoria.
- Los gimnasios ya tienen tipo, medalla y nivel mínimo: son los hitos naturales de cierre de capítulo.
- Las «Sendas» ya existen como progresión de tipo: la campaña usa la misma metáfora de camino, sin tocar la mecánica.
- La Bruma explica en una línea por qué un hábitat está cerrado, sin necesidad de NPCs ni diálogos.

**Mínimo texto.** Cada misión: título + objetivo + una línea de sabor (≤140 caracteres). Cada capítulo: un nombre y una frase de apertura. Las notas de campo son la única recompensa textual, y son coleccionables (3 por hábitat).

**Arco en 8 capítulos**

| Cap. | Provincia | Tema | Cierre | Desbloquea |
|---|---|---|---|---|
| 1 | Mutxamel | La costa que respira | Gimnasio Bicho | 4 hábitats |
| 2 | Extremadura | El bosque que escucha | Nido nivel 3 | 4 hábitats |
| 3 | Galicia | El hielo que recuerda | Gimnasio Eléctrico | 5 hábitats |
| 4 | Nova Alacant | La pradera sin sombra | Gimnasio Planta | 4 hábitats |
| 5 | Andalucía | El fuego antiguo | Mazmorra de Cráter | 5 hábitats |
| 6 | Euskadi | El lago que guarda nombres | Gimnasio Dragón (etapa 1) | 4 hábitats |
| 7 | Barcelona | Las ruinas que escupen | Gimnasio Veneno | 5 hábitats |
| 8 | Vacio | El corazón de la Bruma | Gimnasio Dragón (final) | «Legendarios» |

### 12 hitos de ejemplo

| # | Título | Objetivo | Recompensa |
|---|---|---|---|
| 1 | *Bautismo de sal* | Derrota 10 Pokémon en Bahía Crustáceo (nivel 1) | 10 familia, 1 bonguri azul; desbloquea La Marjal y Charca Renacuajo |
| 2 | *El pescador que no pescaba* | Completa 2 exploraciones de ≥2 h en La Marjal | 12 familia, 2 bayas; desbloquea Bonalba y el nivel 2 de la bahía |
| 3 | *La primera medalla* | Vence la etapa 1 del gimnasio Bicho | 20 familia, 2 tipo; desbloquea Pozas de Valencia y Calvari |
| 4 | *Sendero de niebla* | Derrota 15 Pokémon de tipo Planta o Bicho en Bosque Brumoso | 15 familia, 3 tipo; desbloquea Bosque Crecido y Bosque Nido |
| 5 | *Nido de bienvenida* | Sube un nido al nivel 2 | 10 familia, 1 baya rara; desbloquea Bosque Sanador |
| 6 | *El guardián de la madera* | Completa el piso 3 de la mazmorra de Bosque Nido | 25 familia, 1 mejora; desbloquea Bosque Elemental y Monte Verde |
| 7 | *Costas de sal* | Derrota 20 Pokémon de tipo Agua en Corriente Marina | 18 familia, 2 bonguris; desbloquea Mar Sereno y Playa Cubito |
| 8 | *La caverna que canta* | Completa una exploración de ≥8 h en Caverna Gélida | 22 familia, 1 huevo con IV 13-15; desbloquea Mina Magnética |
| 9 | *Chispa en la mina* | Vence la etapa 2 del gimnasio Eléctrico | 30 familia, 4 tipo; desbloquea Central Energía y Vieja Central |
| 10 | *Censo de la pradera* | Avista 12 especies de Nova Alacant y captura 3 | 28 familia, 1 captura asegurada; desbloquea Jungla |
| 11 | *La Senda del Dragón* | Completa 3 pisos de Cueva Dragón | 40 familia, 5 tipo; desbloquea Monte Partido y Cámara Omega |
| 12 | *El último sello* | Vence al líder del gimnasio Dragón (nivel 100) | 100 familia, 10 tipo, nota final; cierra la campaña |

**Notas de campo (ejemplos, una línea):** «Los bonguris de Mutxamel huelen a sal incluso a diez leguas del mar.» · «En Bosque Brumoso, los Pokémon no huyen: se apartan.» · «La Bruma no borra los caminos; borra las ganas de recorrerlos.»

---

## 7. Desbloqueo de hábitats

**Regla de campaña.** Un hábitat se abre cuando se cumple **una** de estas dos condiciones, la que llegue antes:

1. Se completa el hito de campaña que lo nombra.
2. Se alcanza el **nivel de válvula** del capítulo (nivel esperado + 5).

Esto convierte la campaña en una guía, no en un muro, y garantiza que ningún jugador quede atascado por no entender un hito.

**Relación con `min_lvl_1/2/3`.** Se separan dos puertas:

- **Campaña/nivel → acceso al hábitat.**
- **`min_lvl_N` → acceso al nivel N (1/2/3) de ese hábitat.**

Hoy `min_lvl` solo se aplica a exploración y nadie lo escribe. Propuesta: en v1, rellenarlo desde el capítulo (nivel 1 = 1; nivel 2 = nivel del capítulo − 5; nivel 3 = nivel del capítulo + 5) y aplicarlo de forma uniforme, con aviso previo en la UI y grandfather para jugadores existentes. En el MVP se deja NULL para no cambiar el comportamiento actual.

**Tipos de bloqueo**

- **Blando (todos salvo el final):** el hábitat se ve con nombre, provincia, estrellas de peligro y 3 siluetas del pool; muestra el requisito y un enlace al hito. No se puede entrar.
- **Duro (solo «Legendarios»):** aparece como «???» hasta el epílogo. Es el único secreto del mapa.
- **Sin bloqueo:** todo lo ya abierto.

**Qué ve el jugador.** Una tarjeta con una capa de Bruma, el nombre del hábitat, la frase del requisito y el progreso del hito («2/3 hitos del capítulo»). Al desbloquear: animación breve, la Bruma se retira, aparece el pool. Sin confeti.

**Jugador existente.** Regla de abuelo: **todo desbloqueado** para cualquier cuenta con `experiencia > 0` o con evidencia de uso (mazmorra, granja, favorito, exploración o reclutado de una especie del pool). Se marca `source = legacy`. Los veteranos no pierden acceso y la campaña les queda como historia y misiones. Los nuevos empiezan con el primer hábitat del capítulo 1.

**Cómo evitar callejones sin salida**

- Desbloqueo monótono: nada se vuelve a cerrar.
- Todo hito se puede cumplir con contenido ya desbloqueado.
- Válvula por nivel para cualquier hito atascado.
- Objetivos alternativos («derrota 15 de tipo Planta **o** Bicho»).
- Nunca se exige, para abrir un hábitat, una acción dentro de ese mismo hábitat.
- Si un hito depende de un gimnasio y el gimnasio está bloqueado por nivel, la válvula de nivel del hábitat se activa igualmente.

**Mapa de progresión (ejemplo).** Cap. 1 abre 4 de los 10 hábitats de Mutxamel; el resto llega en el capítulo 3 y en la válvula de nivel. El jugador ve 6/56 al final del primer día, 18/56 al final del primer mes y 56/56 tras el epílogo. La Bruma del mapa es, en sí misma, la barra de progreso más potente del juego.

---

## 8. Economía de recompensas

**Principio:** la misión paga en **la moneda del sistema al que apunta**. Una misión de ruta paga caramelos de familia/EV/tipo; una de granja, bayas; una de forja, materiales; una de huevos, caramelos de familia y, excepcionalmente, un huevo. Esto refuerza el bucle y evita que todas las misiones financien el mismo sumidero.

**Presupuesto orientativo** (unidad de referencia: una victoria de ruta ≈ 25-40 s y ≈ 5 caramelos de familia, 7 de EV, 0-2 de tipo)

| Tipo | Tiempo | Familia | EV | Tipo | Objetos | Extra |
|---|---|---|---|---|---|---|
| Encargo | 4-8 min | 3-6 | 5-9 | 1-2 | 0-1 | — |
| Parte diaria | 8-12 min | 8-14 | 12-18 | 2-3 | 1-2 | EXP ≈ ½ victoria |
| Semanal | 45-90 min | 25-40 | 40-60 | 5-8 | 3-5 | 1 captura asegurada |
| Hito | 10-30 min | 10-25 | 15-30 | 2-4 | 1-2 | Desbloqueo + nota |

**Tope semanal duro:** ≈220 caramelos de familia, 260 de EV, 30 de tipo, 12 objetos y **1 captura asegurada**. En la práctica, eso es como máximo un nivel de nido 3 por semana. Si el jugador completa todo, las misiones aportan ≤25 % del ingreso semanal de un jugador que ya farmea al máximo. Para un jugador casual, el aporte relativo es mayor: es catch-up deliberado, no inflación, porque el tope absoluto no crece con la actividad.

**Guardarraíles anti-inflación**

1. Sin moneda, sin tickets, sin puntos de misión.
2. Tope semanal absoluto, independiente de cuántas misiones se completen.
3. La EXP de misión nunca supera una victoria de ruta; la EXP fuerte sigue viniendo de combatir.
4. Una sola captura asegurada por semana; ningún shiny garantizado.
5. Las recompensas escalan con el **nivel de hábitat**, no con la repetición.
6. Los caramelos de familia se eligen del pool del hábitat, no de la familia que el jugador está subiendo: se reparte el interés.
7. Los hitos pagan una vez; no son repetibles.

**Catch-up**

- Recompensas por nivel de hábitat: un jugador nuevo en un hábitat nivel 1 no recibe limosna, recibe lo proporcional.
- Tres opciones semanales de sistemas distintos: si no tiene granja, elige la de ruta.
- La válvula de nivel evita atascos.
- Sin multiplicador de «bienvenido de vuelta»: sería explotable dejando de jugar.

**Evitar que las misiones hagan obsoletas las actividades base**

- Toda misión exige realizar la actividad; no hay progreso pasivo.
- El rendimiento por minuto de la actividad base siempre es mayor que el de la misión.
- La misión añade ≤30 % por acción, no la sustituye.
- No hay recompensa por conectar ni por esperar.

---

## 9. Ritmo y diversión

**Sesión diaria (10-20 min).** Al entrar, el cuaderno muestra 3 encargos + 1 parte. Dos encargos se completan en 4-6 combates de ruta; el tercero pide un sistema lateral (cosechar, fabricar, explorar). El jugador elige el orden. La parte diaria es la única que pide dos cosas y se completa sola mientras hace lo anterior. No hay que reclamar nada: al completar, salta el toast y la tarjeta cambia de estado.

**Ritmo semanal.** El lunes se eligen 1 de 3 expediciones. La elección es la decisión más importante de la semana: una apunta a provincia, otra a colección, otra a granja/forja. La expedición se puede hacer en dos o tres sesiones, no de una sentada. El domingo por la noche caduca; no pasa nada.

**Primer día.** El jugador aterriza en Bahía Crustáceo con 3 encargos y 1 parte. En 12-15 minutos completa dos encargos y el primer hito (*Bautismo de sal*), y **abre dos hábitats**. Esa es la promesa: el mapa se mueve el primer día. La Bruma retrocede y aparecen dos tarjetas nuevas.

**Primera semana.** Completa el capítulo 1 (3 hitos), abre 4-5 hábitats, toca por primera vez exploración, granja y forja (porque los encargos se lo piden), vence el primer gimnasio y elige su primera expedición semanal. Al final de la semana el mapa muestra 8-10 hábitats y el cuaderno tiene 3 notas.

**Primer mes.** Capítulos 1-3, 8-10 hábitats, 3-4 medallas, 2-3 nidos, su primer shiny hunt y 10 notas de campo. El mapa muestra 18/56 y la Bruma el resto. La pregunta ya no es «¿qué hago?», sino «¿qué abro después?».

**Variedad.** Tres encargos diarios de arquetipos distintos; el generador penaliza lo usado en 48 h y premia los sistemas no tocados en 7 días. La semanal obliga a cambiar de escala. Los hitos introducen una actividad nueva por capítulo.

**Sorpresa.** Uno de cada ocho encargos es un **hallazgo**: un objetivo raro (una familia concreta, una racha, un shiny avistado) con recompensa superior, marcado con un sello distinto. Es determinista por periodo, así que no se puede rerolear. La «zona del día» (v2) da +20 % solo a las misiones de un hábitat rotatorio, para romper la rutina sin tocar la economía base.

**Colección.** Notas de campo (3 por hábitat), censo por hábitat (especies avistadas) y el propio mapa. Nada de esto da poder: da identidad.

**Metas a medio plazo.** Completar una provincia, desbloquear el capítulo final, llenar el censo de un hábitat, completar las 18 medallas.

**Evitación de chores**

- Sin streaks, sin login reward, sin penalización por ausencia.
- Caducidad sin pérdida.
- Máximo 6 tarjetas; el resto se ignora.
- Recompensa automática: cero clics de ceremonia obligatorios.
- La decisión está donde aporta (semanal, orden de hitos), no en 20 aceptaciones.

---

## 10. UX/UI

**Página global vs panel vs híbrido.** Híbrido, con el cuaderno como casa:

- **`/misiones` — «Cuaderno de campo»**, con cuatro pestañas: *Hoy* (parte + 3 encargos), *Semana* (elección y progreso), *Campaña* (capítulo, hitos, mapa), *Notas* (colección).
- **Panel de hábitat**: tarjeta «Encargos de la zona» con hasta 2 misiones locales, su progreso y un enlace al cuaderno. No duplica la página; contextualiza.
- **Nav**: activar el enlace existente con un punto de aviso cuando hay misiones completadas sin ver o parte nueva.

**Qué hacer con el stub.** **Reescribir la vista, conservar la ruta y el hueco del nav.** Se salva la tarjeta, la barra de progreso accesible (`role="progressbar"`, `aria-valuenow`) y el patrón de estados. Se descarta: la misión activa única, el modal de aceptar con reclutado + hábitat, el botón «¡Pelea!» y el estado `listo_para_pelea`. Ese contrato contradice el diseño (varias misiones, progreso pasivo, sin combate de misión). Mientras no haya backend, el nav sigue comentado.

**Flujo**

1. **Ver**: el cuaderno carga estado; las misiones nuevas aparecen ya aceptadas.
2. **Progresar**: el evento de juego actualiza la barra en la siguiente visita o al cerrar la batalla (toast).
3. **Completar**: la tarjeta cambia a «Completada», muestra el resumen de recompensa y se queda hasta el corte.
4. **Elegir** (solo semanal): modal con 3 tarjetas comparables; confirmación explícita.
5. **Desbloquear** (hito): tarjeta de capítulo con la Bruma retirándose y el enlace al hábitat nuevo.

**Notificaciones y contadores.** Toast reutilizando el patrón del layout (`aria-live="polite"`), punto en el nav, y contador en el panel de hábitat. Sin push, sin email, sin campanas.

**Accesibilidad y móvil**

- Progreso con `role="progressbar"`, valor numérico y texto («3/8»), nunca solo color.
- Estado siempre con icono + palabra.
- Objetivos táctiles ≥44 px; sin hover-only.
- Pestañas con estado en URL (atrás funciona).
- Respeto a `prefers-reduced-motion` en la animación de la Bruma.
- Una columna en móvil; tarjetas apiladas; sin tablas horizontales.
- La elección semanal es un grupo de radios accesible, no tres divs clicables.

---

## 11. Arquitectura técnica a alto nivel

**Bounded context:** `Misiones`. Incluye campaña y desbloqueo en el mismo módulo (`Domain/Campania`) porque comparten periodo, eventos y recompensas; extraerlo a `src/Campania` solo si crece. Dependencias: `Shared` (colecciones, excepciones, `Reloj`, `InventarioJugador`), `Habitats` (pool), `Pokemon` (tipos/familias), `Exploraciones`/`Huevos`/`Forja`/`Granjas`/`Reclutamiento` (solo como emisores de eventos).

**Dirección de dependencias:** `Domain` puro (sin Eloquent, HTTP, facades ni `Request`); `App` orquesta; `Infra` implementa puertos. La presentación (Blade/Alpine/Livewire) solo pinta DTOs.

**Entidades y Value Objects**

- Entidad `Mision`: id, usuario, periodo, ámbito, slot, objetivo, progreso, estado, semilla, recompensa, timestamps.
- Entidad `HitoCampania`: capítulo, orden, objetivo, recompensa, desbloqueos.
- VO `ClavePeriodo` (tipo + clave), `ObjetivoMision` (arquetipo, cantidad, filtros), `ProgresoMision`, `RecompensaMision` (colección tipada), `SemillaMision`, `EstadoMision` (enum), `AmbitoMision` (global/hábitat).
- Colecciones propias: `MisionCollection`, `RecompensaMisionCollection`, `HitoCampaniaCollection`.
- DTOs readonly: `ContextoGeneracion`, `ResumenPoolHabitat`, `EventoJuego`, `MisionVista`, `CuadernoCampoVista`, `ResultadoProgreso`.

**Puertos**

- Entrada (casos de uso): `ObtenerCuaderno`, `ObtenerEncargosHabitat`, `RegistrarEventoJuego`, `ElegirExpedicionSemanal`, `DescartarEncargo`, `DesbloquearHabitat`.
- Salida: `MisionRepositorio`, `EventoJuegoRepositorio`, `CampaniaRepositorio`, `DesbloqueoHabitats`, `CatalogoHabitats`, `Reloj`, `InventarioJugador`, `OtorgadorExperiencia`.

**Servicios de dominio:** `GeneradorEncargos` (puro), `EvaluadorObjetivo` (puro), `CalculadorRecompensaMision`, `ResolutorPeriodo`.

**Tablas propuestas**

| Tabla | Claves y unique | Notas |
|---|---|---|
| `mission_instances` | unique `(user_id, period_type, period_key, scope_key, slot)` | Instancia generada, objetivo resuelto, progreso, estado, semilla, recompensa |
| `mission_events` | unique `(user_id, event_type, source_id)`; índice `(user_id, occurred_at)` | Ledger de entrada; `source_id` = batalla, exploración, huevo, operación |
| `mission_event_applications` | unique `(mission_instance_id, mission_event_id)` | Evita contar dos veces el mismo evento en la misma misión |
| `campaign_progress` | unique `(user_id, chapter, milestone)` | Hitos completados |
| `habitat_unlocks` | unique `(user_id, habitat_id)` | `source` = campaña / nivel / legacy |
| `field_notes` | unique `(user_id, note_key)` | Colección textual, sin efecto económico |

`scope_key` evita el problema de los NULL en uniques de PostgreSQL: valores como `global`, `hab:12`, `week`.

**Ledger vs contadores.** El ledger (`mission_events`) es la verdad de idempotencia y auditoría; los contadores (`mission_instances.progress`) son una proyección para lectura rápida. Se escribe el ledger primero, luego se aplica a las misiones activas en la misma transacción; si la proyección falla, el ledger queda y un job de reconciliación (v1) reintenta. Esto resuelve el doble cierre de batalla sin tocar la sesión.

**Idempotencia de recompensas.** La concesión se hace dentro de la transacción que marca `completed_at`; `lockForUpdate` sobre la instancia y unique en el ledger. Recompensa automática: no hay endpoint de reclamo en el MVP, así que no hay doble clic posible. En v1, si se añade reclamo manual, será una transacción con `lockForUpdate` y estado `reclamada`.

**Periodos.** `ResolutorPeriodo` deriva la clave de la fecha local (`Y-m-d`, ISO `o-W`) con el puerto `Reloj`. Sin cron: la generación, la caducidad y la sustitución se resuelven on-read. El scheduler solo se usaría en v2 para notificaciones.

**API/rutas** (dentro de `auth`, módulo en `src/Misiones/Infra/routes.php`):

- `GET /misiones` — cuaderno completo.
- `GET /misiones/habitat/{habitat}` — encargos locales (JSON para el panel).
- `POST /misiones/semanal/elegir` — opción 1-3.
- `POST /misiones/{mision}/descartar` — válvula de escape.
- `GET /misiones/contador` — badge.
- Sin `/pelear` y sin `/aceptar`.

**Integración en los flujos existentes**

- **`PresentadorResultadoBatalla::procesar()`**: tras las recompensas, un único `RegistrarEventoJuego` con `battleId`, tipo, resultado, hábitat, nivel, especies rivales (id/tipo/familia), composición del equipo y capturas. Es el único punto para ruta, entrenador, gimnasio y mazmorra.
- **Exploraciones**: al cerrar (`FinalizarExploracionHandler`), no en cada tick de 5 min. Un evento-resumen con derrotados y capturas.
- **Huevos**: `EclosionarHuevo`.
- **Forja**: `Fabricar`, con clave de operación generada en App.
- **Granjas**: plantación, cosecha y subida de nido.
- **Reclutamiento/evolución**: `recruit` y `evolucionar`.

**Fases**

- **MVP (1-2 semanas):** módulo, 3 tablas, 6 arquetipos (derrota por tipo, por especie, por hábitat, entrenador, exploración, huevo), parte diaria + 3 encargos, recompensa automática, `/misiones` reescrita, tarjeta de hábitat, nav con badge, flag de configuración. Sin campaña, sin semanal, sin desbloqueo.
- **v1 (2-3 semanas):** campaña + desbloqueo + semanal + arquetipos de granja/forja/evolución + notas + job de reconciliación.
- **v2:** hallazgos, zona del día, contratos de entrenador, historial consultable, analítica de completado.

**Verificación**

- `composer run analyse` (L6) y `analyse:hard` (L8) sin entradas nuevas en baseline.
- `composer run arch` con colector para `Misiones`; `Domain` sin imports de Eloquent/HTTP.
- Revisión de que ningún controlador, vista o modelo Eloquent contiene reglas de misión.
- Criterios de aceptación funcionales por fase (abajo).
- Sin tests, según las reglas del flujo.

---

## 12. Riesgos y decisiones abiertas

| Severidad | Riesgo | Mitigación |
|---|---|---|
| **Alta** | Chores: el cuaderno se convierte en una lista de deberes | Máx. 6 tarjetas, caducidad sin castigo, recompensa automática, sin streaks, elección solo en semanal/hito |
| **Alta** | Inflación de caramelos y ruptura de la curva de nidos/Sendas | Tope semanal absoluto, pago en la moneda del sistema, sin EXP fuerte, sin moneda |
| **Alta** | Idempotencia: doble cierre de batalla duplica recompensas | Ledger con unique + aplicación única + transacción + `lockForUpdate` |
| **Alta** | No hay log de derrotados ni `battle_id` persistido | Emitir evento en el cierre con el `battleId` de sesión; ledger como fuente |
| **Media** | Misiones imposibles o triviales | Generación por presupuesto de tiempo, elegibilidad previa, validación al conceder, invalidación sin castigo |
| **Media** | Concurrencia en el corte de medianoche | Clave de periodo resuelta una vez por request; generación idempotente por unique |
| **Media** | Scheduler no garantizado | Todo on-read; ningún estado depende de un job |
| **Media** | Migración de jugadores existentes | Abuelo: todo desbloqueado con `experiencia > 0` o evidencia de uso |
| **Media** | Stub engañoso | No activar nav hasta el MVP; reescribir la vista, no parchearla |
| **Media** | `min_lvl` inerte y semántica confusa | Separar campaña (hábitat) de `min_lvl` (nivel); no tocarlo en MVP |
| **Baja** | Árbol de trabajo sucio | Trabajar en worktree; no interpretar cambios locales como diseño |
| **Baja** | Crecimiento del ledger | Retención por periodo (v2); hoy el volumen es pequeño |

**Decisiones abiertas de producto**

- ¿La recompensa es automática o hay botón «Reclamar»? (Recomiendo automática.)
- ¿La parte diaria es una obligación o una sugerencia? (Recomiendo sugerencia sin castigo.)
- ¿El jugador puede elegir hábitat de encargo o los encargos llegan ya contextualizados? (Recomiendo contextualizados, con descarte.)
- ¿La campaña tiene 8 capítulos o se reduce a 5 para el lanzamiento? (Recomiendo 8 en el mapa, 5 authored en v1.)
- ¿Se muestran los pools de los hábitats bloqueados? (Recomiendo 3 siluetas, no el pool completo.)

**Siguiente paso recomendado.** Prototipar el **MVP** detrás de un flag: 3 tablas, 6 arquetipos, parte diaria + 3 encargos, `/misiones` reescrita y tarjeta de hábitat. Validar con 3-5 jugadores tres cosas: (1) completan al menos 2 misiones en la primera sesión sin que se les pida; (2) vuelven al día siguiente sin incentivo de streak; (3) los encargos les hacen visitar un hábitat o un sistema que no habrían tocado. Si falla (1) o (3), el problema es el generador; si falla (2), el problema es la recompensa. No construir la campaña hasta validar el bucle diario.

---

## Borrador de ADR

**Título:** Sistema de misiones con campaña de desbloqueo, encargos procedurales y ledger de eventos.

**Estado:** Propuesto (pendiente de aprobación).

**Contexto:** el juego no tiene objetivos, ni desbloqueo de hábitats, ni registro unificado de eventos. La economía no tiene moneda y está calibrada con multiplicadores fijos. Existe un stub de UI sin backend que asume un contrato incompatible (misión única, aceptación manual, combate de misión).

**Decisión:** un módulo `src/Misiones` con cuatro tipos (encargo, parte diaria, semanal, hito), generación determinista por presupuesto de tiempo, ledger de eventos idempotente, recompensas automáticas en las monedas existentes, campaña de 8 capítulos con desbloqueo blando (campaña o nivel) y reescritura del stub.

**Consecuencias:** se gana dirección, variedad y un mapa que se abre; se acepta autoría de texto acotada, una tabla de ledger que crece y la necesidad de instrumentar seis puntos de emisión de eventos. No se introduce moneda ni se modifican las reglas de combate o recompensa existentes.

---

## Decisiones que necesito del desarrollador

1. **¿Reescribimos el stub de `/misiones`** (conservando ruta, tarjeta y barra de progreso) en lugar de intentar cumplir su contrato actual de misión única con «pelear»? **Sí / No**
2. **¿Aceptación pasiva** para encargos y parte diaria, y **elección manual 1 de 3** solo para la semanal y los hitos? **Sí / No**
3. **¿Recompensa automática al completar** (sin botón «Reclamar», con tarjeta de resumen) o prefieres reclamo manual con ceremonia? **Automática / Manual**
4. **¿«Capturar» cuenta en el momento de la captura** (`reclutables` o reclutado directo) y **nunca** al reclutar? **Sí / No**
5. **¿Corte diario a medianoche `Europe/Madrid` y semanal el lunes 00:00**, con todo resuelto on-read y sin depender del scheduler? **Sí / No**
6. **¿Desbloqueo de hábitats por campaña *o* nivel (lo que llegue antes), blando, con abuelo total para cuentas existentes**, y `min_lvl` reservado para los niveles 1/2/3? **Sí / No**
7. **¿Apruebas que el MVP no incluya campaña, semanal ni desbloqueo** (solo diaria + 3 encargos + 6 arquetipos de combate/exploración/huevo) para validar el bucle antes de authoring narrativo? **Sí / No**
8. **¿Aceptas el tope semanal duro de recompensas** (≈220 familia, 260 EV, 30 tipo, 12 objetos, 1 captura asegurada) como guardarraíl anti-inflación, aunque limite la sensación de recompensa de un jugador de élite? **Sí / No**