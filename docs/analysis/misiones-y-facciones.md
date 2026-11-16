# Misiones y facciones: informe para el equipo

- **Estado:** documento de discusión. No es un plan de implementación y no autoriza cambios.
- **Fecha:** 2026-11-12.
- **Ámbito:** diseño de producto. Combina el diagnóstico del juego actual con dos propuestas ya trabajadas: el sistema de misiones y el arco de facciones.
- **Cómo leerlo:** cada apartado distingue **estado actual** (lo que hay hoy) de **propuesta** (lo que se discute y aún no existe). Las afirmaciones negativas se limitan a lo revisado; cuando hay duda se indica «no consta».

---

## 1. Portada y resumen ejecutivo

### 1.1 Qué es el juego

Juego de Pokémon por turnos con combates **3v3**, organizado en provincias y hábitats. El jugador colecciona y entrena reclutados, recorre hábitats, vence entrenadores y gimnasios, explora, cultiva, forja, incuba huevos y completa la Pokédex. La unidad de combate es siempre un equipo de exactamente tres.

### 1.2 Qué se propone

Dos piezas comparten mitología y registro de eventos: **«El Cuaderno de la Expedición»** (campaña de ocho capítulos que reabre hábitats sellados por la Bruma, con encargos diarios, parte diaria, semanal e hitos) y **ocho expedientes de facción** (un villano por capítulo, tres actos, base de tres pisos, líder 3v3 y, en seis casos, un legendario «ancla»; Yell y Skull cierran sin legendario). Ninguna está implementada; de la segunda solo existe una idea previa con equipos de cinco que no encaja con el motor.

### 1.3 Qué se pide al equipo

- **Validar el diagnóstico** de la sección 2: hay motor y economía, pero no dirección, objetivos ni voz.
- **Elegir el primer corte**: misiones diarias sin campaña y el expediente de Yell sin legendarios, tras un interruptor de configuración.
- **Cerrar las decisiones de la sección 8**: nombres, alcance de legendarios, formato de bases y reescritura de la pantalla de misiones.

La decisión no es «¿hacemos misiones?» sino «¿validamos el bucle diario y un solo expediente antes de autorizar ocho arcos?».

---

## 2. El juego hoy

### 2.1 Pitch

Entras en un mapa de provincias y hábitats y eliges dónde combatir. La ruta 3v3 contra salvajes es el bucle por defecto: da experiencia, caramelos y capturas. Con eso refuerzas el equipo y alimentas gimnasios, mazmorras, exploraciones, granjas, forja, huevos y Sendas. No hay moneda: se progresa con tiempo, objetos y colección.

### 2.2 El bucle

1. Elegir hábitat y nivel 1, 2 o 3.
2. Elegir equipo y formación de tres.
3. Combatir, explorar o entrar a una mazmorra.
4. Cobrar, reforzar equipo, granja, forja o Sendas y volver a elegir hábitat.

El paso 4 es el punto débil: no hay razón interna para cambiar de zona salvo que interese el pool.

### 2.3 Mapa de sistemas (estado actual)

| Sistema | Qué hace | Límite observado |
|---|---|---|
| Provincias y hábitats | Catálogo y entrada a todos los modos | 56 hábitats; 12 sin Pokémon asignado |
| Ruta 3v3 | Bucle principal de experiencia y capturas | Sin límite diario ni registro de derrotados |
| Entrenadores | Combate PvE con recompensa doble | 9 victorias al día por hábitat |
| Exploraciones | Expedición individual 1v1 con diario y botín | 30 min por encuentro; 1-72 h; sin tope de cantidad |
| Mazmorras | Pisos con jefe 3v1 | Mecanismo implementado; **0 de 56 hábitats configurados en el repositorio** |
| Gimnasios | Cuatro etapas por tipo y medalla | 18 gimnasios; nivel 10 a 100; sin cooldown diario |
| Granjas | Nidos, refugios, EV y plantaciones | Una cosecha cada 24 h; stock máximo 3 |
| Forja | Fabrica objetos de combate | 46 recetas y 39 resultados |
| Huevos | Reclutados con mejores IV | Tiempos reales; uno por familia en cada estado |
| Reclutamiento y evolución | Convierte capturas en reclutados; evoluciona | Cola de capturas; evolución por experiencia de tipo |
| Pokédex | Registro de visto y atrapado | Solo registra; no propone objetivos |
| Equipos | Unidad de combate de tres | Formación, posturas y objetos |
| Sendas de tipo | Progresión por tipo y movimientos | Umbrales de 0 a 72.000 |
| Inventario | Almacén único de objetos y caramelos | Transversal |

No consta PvP implementado en el código revisado; hay documentos de diseño, pero fuera de este alcance.

### 2.4 Progresión

Los ejes avanzan en paralelo:

- **Nivel de cuenta**: curva cúbica; el nivel 100 exige 10.000.000. Es el único eje que hoy abre puertas, y solo gimnasios y niveles de exploración.
- **Medallas, mazmorras y Pokédex**: 18 gimnasios sin exigir previos, un piso actual por hábitat y registro de visto/atrapado.
- **IV, EV, CP y Sendas**: eficacia en combate y progresión por tipo. Los hábitats no tienen desbloqueo observable.

Resultado: un juego **ancho pero plano**, con muchos ejes y ninguno que ordene el recorrido.

### 2.5 Economía sin moneda

No hay moneda, oro ni tienda en lo revisado. El capital son objetos, caramelos, reclutados y experiencia. Multiplicadores fijados: ruta ×1,0; entrenador ×2,0; gimnasio ×10,0; mazmorra ×70,0 (siete veces gimnasio); exploración individual ×0,5, con reparto 50/50 entre cuenta y reclutado.

Sumideros: nidos (25, 75, 225, 450 y 900 caramelos de familia), refugios (hasta 2.700), complementos de EV, plantaciones, forja, movimientos de Senda (2, 5, 10, 25 y 60 caramelos de tipo) y experiencia de Senda (100 por caramelo, tope 72.000).

Cifras que condicionan cualquier recompensa nueva: captura salvaje con tope de 25 sobre 255 por tirada (≈9,8 % máximo); shiny salvaje 1/2048, con captura garantizada e IV 15; shiny de huevo 1/512; IV en escala 0-15, no 0-31; un shiny salvaje capturado entra directo como reclutado.

### 2.6 Cadencia

| Actividad | Cadencia |
|---|---|
| Entrenadores | Bloqueo diario por entrenador vencido; perder es repetible |
| Ruta | Sin límite |
| Exploraciones | Sin tope de cantidad; 30 min por encuentro; procesado cada 5 min |
| Mazmorras | Una hora de espera tras derrota |
| Plantaciones | Una cosecha cada 24 h |
| Gimnasios | Sin límite diario |
| Huevos | Temporizadores reales calculados al leer |

El «día» no tiene un job de reinicio: se materializa por fecha. Zona horaria: `Europe/Madrid`.

### 2.7 Hábitats y desbloqueo

- Todos los hábitats se listan por provincia y se abren para cualquier usuario autenticado. Las columnas de nivel mínimo 1/2/3 existen, pero **no consta ningún escritor**; en la interfaz solo deshabilitan el modo Pokédex/exploración, y ruta y entrenadores las ignoran.
- La provincia «Vacío» no aparece en el listado normal y aloja el hábitat «Legendarios», con 100 especies. No consta un bloqueo explícito de ese hábitat en el código revisado.

Conclusión: **el mapa no se dosifica**; el jugador nuevo ve las 56 zonas desde el primer día.

### 2.8 Narrativa: el hueco

Lo que sí existe: nombres de provincias y hábitats con ambientación, color de bonguri por provincia, nombres de medallas, frases funcionales del diario de exploración y un peligro de 1 a 5 derivado del nombre.

Lo que no consta: NPCs con nombre, diálogos o trasfondo; entrenadores con identidad (se rotulan por índice y nivel); líderes de gimnasio con nombre propio; facciones, capítulos o misiones narradas. Hay una vista de misiones huérfana que consume endpoints inexistentes y tiene el enlace de menú comentado.

El juego dice **qué** hacer, nunca **quién** lo pide ni **por qué**.

---

## 3. La oportunidad

### 3.1 Qué falta

| Hueco | Síntoma |
|---|---|
| **Dirección y objetivos** | Se elige hábitat por inercia; la ruta es infinita y rentable, pero no persigue nada |
| **Dosificación** | Todo abierto desde el minuto uno; el mapa no se siente como progreso |
| **Voz narrativa** | Nombres bonitos y mudos; no hay promesa ni conflicto |
| **Sistemas laterales y registro** | Granja, forja y huevos funcionan solo si apetece, y no se guarda a quién derrotaste |

### 3.2 Principios que debe cumplir la solución

1. **No chores.** Sin rachas, recompensa por conectar ni castigo por ausencia.
2. **Variedad.** Cada periodo empuja a un sistema distinto; no todo es «derrota N».
3. **Agencia.** Lo diario es pasivo; lo semanal y lo narrativo se eligen.
4. **Narrativa mínima.** Una línea por misión, un emisor reconocible, cero diálogos ramificados.
5. **Economía.** Sin moneda nueva, con topes y pagando en la moneda del sistema implicado.
6. **Reparabilidad.** Una misión rota se invalida sin castigo; una recompensa no se paga dos veces.
7. **El juego base sigue siendo el motor.** La misión exige jugar; no lo sustituye.

### 3.3 Riesgos de diseño que hay que evitar desde el papel

- **Diarias como obligación**: el juego ya tiene un diario de facto (los entrenadores); duplicar esa textura cansa.
- **Inflación y farmeo**: sumar caramelos de misión rompe la curva de nidos y Sendas, y con ruta ilimitada cualquier «derrota N» es trivial sin límites por tiempo, hábitat o tipo.
- **Tres fuentes de bloqueo** (nivel, misión y medalla) sin regla clara producen atascos y confusión.

---

## 4. Propuesta de misiones — «El Cuaderno de la Expedición»

> **Propuesta.** Nada de esta sección está implementado.

El sistema no debe añadir una lista de tareas, sino **convertir el mapa en un objetivo**.

### 4.1 Las cinco piezas

| Pieza | Qué es | Simultáneas | Caducidad | Recompensa |
|---|---|---|---|---|
| **Campaña de la Bruma** | Ocho capítulos por provincia con 3-5 hitos | Un hito activo | Permanente | Media + desbloqueo + nota |
| **Encargo de campo** | Tarea corta de una de las tres voces del campamento | 3 | Fin del día local | Pequeña |
| **Parte diaria** | Combate más un sistema lateral | 1 | Fin del día local | Media + experiencia |
| **Expedición semanal** | Elección entre tres opciones de sistemas distintos | 1 de 3 | Domingo 23:59 | Grande + una garantía |
| **Hito de campaña** | Paso autorado y secuencial de un capítulo | 1 | Ninguna | Media + desbloqueo |

Reglas transversales:

- Una misión caducada **no penaliza**: desaparece, se sustituye y se puede descartar un encargo una vez al día.
- Encargos y parte diaria se aceptan solos al abrir el cuaderno; solo la semanal es una decisión real (tres opciones, una elección).
- **Máximo seis tarjetas visibles**: 3 encargos + 1 parte + 1 semanal + 1 hito.

### 4.2 Ejemplos concretos

**Encargos:**

| Título | Objetivo | Recompensa |
|---|---|---|
| *Ronda de vigilancia* | Derrota 8 Pokémon de tipo Bicho en Bosque Brumoso (nivel 1) | 5 caramelos de familia, 8 de EV, 1 de tipo Bicho |
| *Pies ligeros* | Completa una exploración de 4 horas o más | 6 de familia, 8 de EV, 2 bayas |
| *Yunque y martillo* | Fabrica 2 objetos cualesquiera | 1 material de recipiente, 4 de EV |

**Parte diaria:** *Parte de guardia* — derrota 15 Pokémon y completa una exploración. Paga 12 caramelos de familia, 16 de EV, 3 de tipo, 2 objetos y experiencia equivalente a media victoria de ruta.

**Semanales (se elige una):**

| Título | Objetivo | Recompensa |
|---|---|---|
| *La ruta del bonguri* | Derrota 30 Pokémon en 3 hábitats de una provincia y cosecha 5 bayas | 40 de familia, 45 de EV, 5 bonguris provinciales |
| *Censo de la costa* | Avista 8 especies de Mutxamel y captura 2 | 35 de familia, 1 captura asegurada, 2 bonguris azules |
| *El yunque de la semana* | Fabrica 5 objetos distintos y sube un nido al nivel 2 | 30 de familia, 3 mejoras, 1 baya rara |

**Hitos (muestra):** *Bautismo de sal* (derrota 10 Pokémon en Bahía Crustáceo; abre La Marjal y Charca Renacuajo) · *La primera medalla* (vence la etapa 1 del gimnasio Bicho; abre Pozas de Valencia y Calvari) · *El último sello* (vence al líder Dragón; cierra la campaña y abre «Legendarios»).

### 4.3 Generación procedural determinista

La misma persona ve lo mismo al recargar; dos personas distintas ven cosas distintas. El generador es una función pura de una semilla (usuario, periodo, ámbito, posición y versión del catálogo).

1. **Filtrar arquetipos** por pool real, estado y nivel, excluyendo lo usado en los dos periodos anteriores.
2. **Elegir arquetipo y objetivo** con dos extracciones independientes de la semilla.
3. **Calcular la cantidad por presupuesto de tiempo** (4-8 minutos por encargo, 8-12 por parte, 45-90 por semanal); el nivel solo cambia la banda de recompensa.
4. **Elegir recompensa** en la banda y en la moneda del sistema, y persistir la instancia con el objetivo resuelto, sin regenerar.

Garantías: **anti-repetición** (penaliza lo usado en 48 h y premia sistemas no tocados en 7 días), **anti-imposible** (nada de capturas imposibles, pisos inalcanzables o hábitats bloqueados), **anti-trivial** (mínimos por arquetipo y prohibición de «derrota 1») y **anti-rota** (sustitución sin consumir hueco y descarte diario).

Decisión semántica: **«capturar» cuenta en el momento de la captura** (entrada en la cola o creación del reclutado), nunca al convertir la cola en reclutado; eso es «reclutar».

### 4.4 Desbloqueo blando de hábitats

Un hábitat se abre con **la condición que llegue antes**: el hito de campaña que lo nombra o el nivel de válvula del capítulo (nivel esperado + 5).

El bloqueo es **blando** en todos salvo el final (se ve nombre, provincia, peligro, tres siluetas y requisito, pero no se entra), **duro** solo en «Legendarios» (aparece como incógnita hasta el epílogo) y **sin bloqueo** en todo lo ya abierto.

Reglas de seguridad: nada se vuelve a cerrar; ningún hito exige una acción dentro del hábitat que abre; hay válvula de nivel para cualquier atasco; los objetivos admiten alternativas («tipo Planta o Bicho»); los jugadores existentes conservan todo abierto. La campaña abre el hábitat; las columnas de nivel mínimo regularían el nivel 1, 2 o 3 dentro de él, y en el primer corte se dejan como están.

### 4.5 Ritmo

**Primer día.** El jugador aterriza en Bahía Crustáceo con tres encargos y una parte. En 12-15 minutos completa dos encargos y el primer hito, y **abre dos hábitats**. Esa es la promesa: el mapa se mueve el primer día.

**Primera semana.** Cierra el capítulo 1, abre 4-5 hábitats, toca por primera vez exploración, granja y forja porque los encargos se lo piden, vence el primer gimnasio y elige su primera semanal. El mapa muestra 8-10 hábitats.

**Primer mes.** Capítulos 1-3, 8-10 hábitats, 3-4 medallas, 2-3 nidos y 10 notas de campo; el mapa muestra 18 de 56. La pregunta pasa de «¿qué hago?» a «¿qué abro después?».

### 4.6 UX

- **Página `/misiones`** con cuatro pestañas: *Hoy*, *Semana*, *Campaña* y *Notas*.
- **Tarjeta en el hábitat** con hasta dos encargos locales y enlace al cuaderno; el nav activa el enlace existente con un punto cuando hay misiones completadas sin ver.
- **Accesibilidad**: barra con valor numérico y texto («3/8»), estado con icono y palabra, objetivos de 44 px, una columna en móvil y respeto a `prefers-reduced-motion`.
- **Sin ceremonia**: la recompensa se concede al completar. La vista huérfana se reescribe conservando tarjeta, barra y estados útiles; se descarta su contrato de misión única con botón de pelear.

---

## 5. Propuesta de equipos rivales

> **Propuesta.** Las ocho facciones no están implementadas. La idea previa usa equipos de cinco; aquí se adaptan a tres.

### 5.1 Premisa de mundo

Una sola mitología explica mapa, gimnasios y villanos:

> **La Bruma no borra caminos: borra lo que no puede sostener.**

Cada provincia tiene un **ancla**: un legendario que ocupa su lugar y sostiene la memoria del territorio. Hace generaciones, una expedición intentó rehacer el mundo a su manera —tierra, mar, tiempo, belleza, liberación— y arrancó las anclas. No lo consiguió; dejó la Bruma. Los gimnasios son faros que mantienen despejado un tramo; las facciones son los herederos de aquella expedición y quieren **arreglar el mundo a su manera**.

Eso permite que el mismo legendario aparezca tres veces con tres reglas:

| Aparición | Nombre | Qué es | Regla |
|---|---|---|---|
| Gimnasio | **Proyección** | Imagen que un faro usa como prueba | No se captura |
| Hábitat Legendarios | **Eco** | Copia que la Bruma hace de lo que no puede retener | Captura normal, IV 0-15, shiny 1/2048 |
| Facción | **Ancla** | El individuo real, fuera de su sitio | Encuentro único, captura garantizada, shiny 1/512 |

Ejemplo: el Mewtwo de un gimnasio es una proyección; el del laboratorio Rocket es el ancla; el del hábitat Legendarios es un eco. Mismo sprite, tres reglas. Cuando las anclas vuelven, el hábitat pasa a ser el **Archivo Negro**.

### 5.2 Las ocho facciones

| Cap. | Facción | Provincia | Líder | Lugarteniente | Equipo 3v3 del líder | Ancla | Tono |
|---|---|---|---|---|---|---|---|
| 1 | **Yell** | Mutxamel | Púa, «la voz del barrio» | El Batería | Perrserker, Toxtricity, Grimmsnarl | — | Cómico |
| 2 | **Skull** | Extremadura | El Nano, «el que cobija» | La Sombra | Golisopod, Escavalier, Vikavolt | — | Trágicómico |
| 3 | **Aqua** | Galicia | Marea, «la que sube» | El Práctico | Sharpedo, Walrein, Crawdaunt | Kyogre | Fanático |
| 4 | **Flare** | Nova Alacant | El Jardinero | La Modelo | Chandelure, Goodra, Altaria | Xerneas **o** Yveltal | Decadente |
| 5 | **Magma** | Andalucía | Bruno, «el que perfora» | La Capataz | Camerupt, Houndoom, Flygon | Groudon | Industrial |
| 6 | **Galaxia** | Euskadi | Cero, «el que mide» | El Cartógrafo | Bronzong, Weavile, Porygon-Z | Dialga, Palkia **o** Giratina | Gélido |
| 7 | **Rocket** | Barcelona | Don Ciro, «el que cobra» | La Cuenta | Persian, Rhyperior, Honchkrow | Mewtwo | Noir |
| 8 | **Plasma** | Vacío | El Predicador | El Coro | Hydreigon, Froslass, Eelektross | Reshiram, Zekrom **o** Kyurem | Demagogo |

Notas de reparto:

- **Un capítulo, una facción principal**; las demás son presencia ambiental (patrulla y nota). Ningún líder repite el trío exacto del gimnasio de su provincia: donde había solape, la especie baja al lugarteniente o al piso de base.
- **Nombres originales en la interfaz** y recompensa única: reclutado firma (Yell, Plasma), desbloqueo de nivel (Skull, Aqua, Rocket), huevo IV 12 (Flare), receta de forja (Magma) o información del mapa (Galaxia). Nivel de expediente pendiente de reconciliar: 12, 22, 40, 50, 62, 75, 88 y 100.

### 5.3 Estructura de tres actos

Cada facción tiene tres actos con **verbos distintos**, para no repetir «derrota cinco» tres veces:

| Acto | Verbo | Qué se hace | Clímax |
|---|---|---|---|
| I | Investigar | Batir tres patrullas y encontrar la pista | Primer combate de grunt |
| II | Sabotear o rescatar | Inutilizar una máquina y liberar a alguien | Lugarteniente |
| III | Asaltar o duelar | Entrar a la base y vencer al líder | Líder 3v3; ancla si la hay |

**Ejemplo Yell (capítulo 1, Mutxamel):**

1. *El escenario del Calvari*: batir tres patrullas y encontrar el cartel del «concierto».
2. *La prueba de la bocina*: sabotear el altavoz y rescatar al batería; clímax contra El Batería.
3. *El último bis*: asaltar la base y vencer a Púa. Si el jugador saboteó antes, el líder pelea sin bonus; si no, empieza reforzado.

**Ejemplo Rocket (capítulo 7, Barcelona):**

1. *El peaje del vertedero*: batir tres patrullas y encontrar la lista.
2. *La lista de la mafia*: sabotear el vertedero y rescatar al soplón; clímax contra La Cuenta.
3. *El gato y el laboratorio*: asaltar el laboratorio, vencer a Don Ciro y enfrentar el duelo del ancla Mewtwo.

La cifra «9 combates» de la idea original se recompone así: 3 patrullas + 1 lugarteniente + 2 pisos de base + 1 líder (que es el tercer piso) + 1 ancla = 8 combates. Skull y Yell, sin ancla, quedan en 7. El «9» original era una estimación, no un contrato.

### 5.4 Bases de tres pisos

| Piso | Modo | Enemigo | Combate | Recompensa |
|---|---|---|---|---|
| 1 | Guardia | Pokémon insignia o guardián | 3v1 | Nivel gimnasio (×10) + 1 material |
| 2 | Sabotaje | Máquina o as de la facción | 3v1 | Nivel gimnasio (×10) + 1 material |
| 3 | Líder | Equipo 3v3 del líder | 3v3 | ×20 + emblema + nota + recompensa única |

- La derrota aplica una hora de espera **solo al piso actual**; los anteriores se conservan. La victoria avanza y no se repite; tras completar la base queda una ruina opcional que, en una fase posterior, paga material menor, nunca caramelos.
- La base **no reutiliza** la mazmorra del hábitat: tiene entidad y progreso propios. La mazmorra actual está vacía y su progreso por hábitat colisionaría.

### 5.5 Estados del mundo

| Estado | Qué se ve |
|---|---|
| **Dormida** | Sin presencia; el expediente no está abierto |
| **Activa** | Patrulla, emblema pintado, bloqueo blando de un nivel |
| **Derrotada** | Patrulla retirada, base abierta, nota y recompensa única |
| **Cicatriz** | Marca permanente: pintada, placa, marea, grieta o cinta |

La cicatriz es la recompensa emocional del arco: un texto y un tinte que hacen que el mundo parezca tocado.

### 5.6 Skull y Yell sin legendario

No es un hueco: es una decisión de tono.

- Son facciones locales, cómicas o trágicómicas: elevarlas a amenaza apocalíptica les daría un dios que no les corresponde. Su clímax es **humano**, la banda que no quiere desaparecer.
- Su recompensa única es un reclutado firma (Yell) o un desbloqueo de mundo (Skull). Dos finales sin legendario permiten que los otros seis se sientan especiales y evitan ocho apocalipsis seguidos.

---

## 6. Cómo encajan misiones y facciones

> **Propuesta.** Esta sección resuelve la integración; no hay nada construido.

### 6.1 Los arcos son hitos, no un quinto tipo de misión

Un expediente tiene tres actos **autorados y secuenciales**. Esa es la semántica de un hito de campaña: de un solo uso, con progreso persistido y recompensa única. Por tanto:

- Los actos viven en el progreso de campaña, con una clave por facción y acto; los encargos de facción viven en las instancias de misión con periodo propio («facción:acto») y caducan al cerrar el acto.
- **No hay quinto tipo de misión ni séptima tarjeta.** Añadir un tipo rompería el límite de seis tarjetas; el expediente completo es una **ruta** dentro de la pestaña de campaña.

### 6.2 Dónde se ven

La pestaña *Campaña* muestra un expediente abierto, sus tres actos y hasta dos encargos en la misma tarjeta. La tira de presencia del hábitat añade emblema, una línea y patrulla opcional; el modal de victoria, emblema, acto, línea y enlace. Cada facción derrotada deja una nota.

El tablero *Hoy* no cambia: una parte diaria y tres encargos. Las facciones no añaden diarias, rachas ni penalización.

### 6.3 Un solo registro de eventos

Cada batalla, exploración, huevo, fabricación o cosecha genera **un evento** con identificador de origen. Una clave única por usuario, tipo y origen impide contarlo dos veces; una tabla de aplicaciones impide que el mismo evento sume dos veces en la misma misión. La recompensa se concede en la transacción que marca la misión o el acto como completado, con bloqueo de fila.

Esto resuelve el problema real de hoy: ruta, entrenador, gimnasio y mazmorra no guardan a quién derrotaste.

### 6.4 Reparto de recompensas: cada escalera paga lo suyo

| Fuente | Qué aporta | Repetible |
|---|---|---|
| **Gimnasio** | Medalla, nivel y caramelos | Una vez por etapa |
| **Mazmorra** | Botín grande (×70) | Farmeo con espera |
| **Facción** | Historia, emblema, nota y recompensa única | Una vez por facción |
| **Misión diaria** | Caramelos pequeños y objetos | Cada día, con tope |
| **Misión semanal** | Caramelos medios y una garantía | Una por semana |

Reglas anti-canibalización:

- El líder de facción paga ×20 **una sola vez**; ninguna recompensa de facción es repetible con caramelos.
- Las recompensas de facción quedan **fuera del tope semanal** porque son finitas; el tope sigue aplicando a encargos y semanales. Ni shiny garantizado ni captura extra: el ancla es un hito, no una misión.

---

## 7. MVP y fases

> **Propuesta.** Sin fechas. Cada fase requiere su propia aprobación.

### 7.1 Primer corte de misiones

**Incluye:** un módulo con dominio aislado; tres tablas (instancias, eventos y aplicaciones); seis arquetipos (tipo, especie/familia, hábitat, entrenador, exploración y huevo); una parte diaria y tres encargos; aceptación pasiva y recompensa automática; página reescrita, tarjeta de hábitat e interruptor apagado por defecto.

**Deja fuera:** campaña, capítulos e hitos; semanal y elección 1 de 3; desbloqueo de hábitats; notas, hallazgos y zona del día; contratos de entrenador; notificaciones; cambios en combate, recompensas base o economía.

**Qué valida:** que el jugador completa al menos dos misiones en su primera sesión de juego sin que se le pida, vuelve al día siguiente sin racha y visita un hábitat o sistema que no habría tocado. Si falla lo primero o lo tercero, el problema es el generador; si falla lo segundo, la recompensa.

### 7.2 Secuencia de facciones

| Fase | Facción | Qué valida |
|---|---|---|
| **MVP** | Yell | Expediente completo: tres actos, base de tres pisos, líder 3v3, emblema y nota |
| **Fase 2** | Skull | Clímax humano sin ancla y desbloqueo de un nivel |
| **Fase 3** | Aqua + Magma | Primer ancla, elección de orden y cruce Rayquaza |
| **Fase 4** | Flare | Elección entre dos anclas y huevo como recompensa |
| **Fase 5** | Galaxia | Elección entre tres anclas y convivencia con proyecciones |
| **Fase 6** | Rocket | Tensión entre proyección, eco y ancla con Mewtwo |
| **Fase 7** | Plasma | Cierre, Archivo Negro y la facción que discute el juego |

La agrupación encaja con la propuesta original: Yell sola en el primer corte; Skull, Aqua y Magma después; Flare, Galaxia, Rocket y Plasma al final. La diferencia es que aquí se separa cada facción para saber **qué valida** antes de autorizar la siguiente.

### 7.3 Fuera de alcance en las fases iniciales

- Retratos, escenas con diálogo y arte nuevo de líderes o bases.
- Moneda, tienda, tickets o puntos de misión; combates de cinco o cambios en el motor 3v3.
- Segmentación del hábitat Legendarios y poblar los doce hábitats vacíos.
- Cambios en gimnasios o mazmorras existentes.
- Notificaciones push, ranking, logros y rachas.

---

## 8. Riesgos y decisiones para el equipo

### 8.1 Riesgos

| Severidad | Riesgo | Por qué importa | Mitigación |
|---|---|---|---|
| **Alta** | Autoría de contenido | 24 actos, 8 notas y unos 40 encargos; hoy no existe ni una línea de diálogo | Una línea por acto; 600-800 caracteres por facción; el resto es reutilización |
| **Alta** | Propiedad intelectual | Nombres, líderes y equipos son material oficial | Nombres originales, emblemas propios, sin logos ni diálogos copiados |
| **Alta** | Economía | Caramelos de misión sumados a los existentes rompen nidos y Sendas | Tope semanal absoluto y pago en la moneda del sistema |
| **Alta** | Alcance de ocho facciones | Ocho arcos simultáneos diluyen la historia y no se terminan | Un expediente abierto a la vez; uno por capítulo |
| **Alta** | 3v3 frente a equipos de cinco | Los equipos originales no caben en el motor | 3 de líder + 3 de lugarteniente |
| **Alta** | Legendarios | Power creep, farmeo por rechazo y duplicidad con gimnasios | Una ancla por facción, siete por cuenta, rechazo con tope, shiny fijado |
| **Media** | Assets | No hay retratos ni emblemas de facción | Marco de dos colores y sprite insignia; paleta propia de ocho colores |
| **Media** | Dos mitologías | La Bruma y los legendarios pueden competir | Regla única Proyección/Eco/Ancla; las facciones explotan la Bruma |
| **Media** | Doce hábitats vacíos | Limitan el reparto geográfico y las bases | Excluidos del arco; poblarlos es tarea aparte |
| **Medio** | Hábitat Legendarios | Hoy es un pool normal de 100 con UBs y paradojas | Sellado hasta el epílogo; después, Archivo Negro |

### 8.2 Decisiones que hay que tomar

| Decisión | Opciones | Recomendación |
|---|---|---|
| Pantalla de misiones | Reutilizar su contrato / reescribir | **Reescribir**, conservando tarjeta y barra |
| Aceptación y cobro | Manual / pasivo y automático | **Pasivo y automático** |
| Primer corte | Con campaña / solo diarias | **Solo diarias y encargos** |
| Nombres de villanos | Canónicos / originales | **Originales en la interfaz** |
| Legendarios | Todos / una ancla por facción | **Una, con elección cuando haya varias** |
| Skull y Yell | Con / sin legendario | **Sin legendario** |
| Bases | Cinco pisos 3v1 / tres con líder 3v3 | **Tres, tercero 3v3** |
| Rechazo del ancla | +10 % al recluta / +10 % al jefe con tope | **Al jefe, con tope 5** |
| Acceso a facciones | Séptimo menú / dentro de misiones | **Dentro de la pestaña de campaña** |

---

## 9. Preguntas para la reunión

1. ¿Aprobamos reescribir la pantalla de misiones conservando ruta, tarjeta y barra, en lugar de cumplir su contrato de misión única? **Sí / No.**
2. ¿Aceptamos aceptación pasiva y recompensa automática, y elección manual solo en semanal e hitos? **Sí / No.**
3. ¿El primer corte de misiones excluye campaña, semanal y desbloqueo? **Sí / No.**
4. ¿Yell es el primer expediente y se implementa sin legendarios? **Sí / No.**
5. ¿Usamos nombres originales para líderes y lugartenientes en la interfaz? **Originales / Canónicos.**
6. ¿Una ancla por facción, con elección cuando la ficha ofrece varias, y tope de siete por cuenta? **Una / Todos.**
7. ¿Skull y Yell cierran sin legendario? **Sin / Con.**
8. ¿Bases de tres pisos con líder 3v3, o cinco pisos 3v1 como una mazmorra más? **Tres / Cinco.**

---

## 10. Anexos

### A. Ejemplos de misiones diarias y semanales

- **Diaria de combate:** *Parte de guardia* — derrota 15 Pokémon y completa una exploración. Paga 12 caramelos de familia, 16 de EV, 3 de tipo y 2 objetos.
- **Semanal de provincia:** *La ruta del bonguri* — derrota 30 Pokémon en 3 hábitats de una misma provincia y cosecha 5 bayas. Paga 40 de familia, 45 de EV y 5 bonguris provinciales.
- **Semanal de colección:** *Censo de la costa* — avista 8 especies de Mutxamel y captura 2. Paga 35 de familia, 1 captura asegurada y 2 bonguris azules.
- **Hito de apertura:** *Bautismo de sal* — derrota 10 Pokémon en Bahía Crustáceo. Paga 10 de familia, 1 bonguri azul y abre La Marjal y Charca Renacuajo.

### B. Fichas resumidas de las ocho facciones

| Facción | Ideología en una línea | Base | Lugarteniente (3) | Recompensa única |
|---|---|---|---|---|
| **Yell** | «El barrio merece un escenario.» | Calvari | Obstagoon, Scrafty, Skuntank | Reclutado firma Toxtricity IV 12 |
| **Skull** | «Si el sistema no cobija, cobijamos nosotros.» | Bosque Crecido | Scizor, Kleavor, Heracross | Nivel 3 del bosque |
| **Aqua** | «El mundo se arregla con más mar.» | Corriente Marina | Gyarados, Drednaw, Cloyster | Nivel 3 de Mar Sereno |
| **Flare** | «Solo lo bello merece quedarse.» | Pradera Orgullosa | Granbull, Mawile, Slurpuff | Huevo de Hada IV 12 |
| **Magma** | «El mundo se arregla con más tierra.» | Cráter | Steelix, Magcargo, Krookodile | Receta «núcleo de magma» |
| **Galaxia** | «El mundo se arregla con un reloj.» | Cámara Omega | Magnezone, Alakazam, Cofagrigus | Astrolabio: revela anclas |
| **Rocket** | «El mundo se arregla desde arriba.» | Pantano Ponzoña | Nidoqueen, Nidoking, Muk | Llave: nivel 3 del pantano |
| **Plasma** | «El mundo se arregla sin entrenadores.» | El Altavoz | Cofagrigus, Bisharp, Vanilluxe | Hydreigon IV 12 y nota fundacional |

### C. Glosario

- **Bruma**: niebla que no tapa el paisaje, sino la razón para recorrerlo. Explica el bloqueo de hábitats.
- **Ancla**: legendario que ocupa su lugar y sostiene la memoria de una provincia. Es el individuo real; se captura una vez.
- **Eco**: copia que la Bruma hace de un legendario que no puede retener. Habita el hábitat Legendarios y se captura con reglas normales.
- **Proyección**: imagen de un legendario que un gimnasio usa como prueba. No se captura.
- **Expediente**: ficha autorada de una facción con tres actos, base, equipo, ancla y nota.
- **Encargo**: misión corta y repetible dentro de un periodo; en facciones, caduca al cerrar el acto.
- **Hito**: paso autorado, secuencial y de un solo uso dentro de una campaña o un expediente.
- **Cicatriz**: estado permanente que deja una facción derrotada; no da poder, da memoria.
- **Archivo Negro**: función que adopta el hábitat Legendarios tras el epílogo: ecos, notas y expedientes sin capítulo.

### D. Referencias internas

- Análisis funcional del juego y del hueco de misiones: `.opencode/work/TASK-043/analysis.md`.
- Propuesta de misiones, campaña y desbloqueo: `.opencode/work/TASK-043/architecture.md`.
- Primer corte de misiones: `.opencode/work/TASK-043/plan.md`.
- Inventario de facciones, roster, hábitats y gimnasios: `.opencode/work/TASK-044/analysis.md`.
- Propuesta de expedientes, mundo y anclas: `.opencode/work/TASK-044/architecture.md`.
- Primer corte de facciones (Yell): `.opencode/work/TASK-044/plan.md`.
- Arquitectura y convenciones del proyecto: `docs/architecture.md`, `docs/conventions.md`.
