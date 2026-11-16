# TASK-044 — Arquitectura y diseño

- **Estado:** COMPLETADO
- **Arquitecto:** `architect`, sesión `ses_e2f758411ffeaHnPLd7Ub77J3S`.
- **Nota:** se reproduce el informe del arquitecto sin alterar sus conclusiones. No se modificó ningún archivo de aplicación.

---

# TASK-044 — Facciones y villanos: diseño de producto y arquitectura

**DECISIÓN:** las 8 facciones no son un sistema nuevo ni un quinto tipo de misión. Son **8 expedientes autorados de 3 actos** incrustados en los 8 capítulos de TASK-043, uno por capítulo y en secuencia, con **encargos de facción** dentro de la pestaña de campaña (no en el tablero diario). Una sola mitología —la Bruma como cicatriz de un «ancla» legendaria fuera de su sitio— explica gimnasios, hábitat 49 y facciones. El primer corte implementable es **Yell**, la única facción que no necesita legendario.

---

## 1. Resumen ejecutivo y recomendación

El juego ya tiene escalera de combate (ruta, entrenador, gimnasio, mazmorra) pero no tiene **quién** ni **por qué**. Las facciones son la respuesta más barata a ese hueco: no exigen NPCs con diálogo, ni retratos, ni mapa nuevo; exigen **una línea de texto por acto, un emblema y un cambio visible en el hábitat**.

Mi recomendación es una campaña de facciones **lineal y ascendente**, no ocho arcos abiertos:

1. **Un capítulo, una facción principal.** Yell en Mutxamel, Skull en Extremadura, Aqua en Galicia, Flare en Nova Alacant, Magma en Andalucía, Galaxia en Euskadi, Rocket en Barcelona y Plasma en el Vacío. Las demás facciones aparecen como «presencia» (una patrulla y una nota), nunca como arco.
2. **Dos facciones sin legendario.** Skull y Yell cierran con un jefe humano y un cambio de mundo, no con un dios. Eso baja el tono al principio, evita ocho apocalipsis y ahorra el sistema de anclas en el MVP.
3. **Equipos de 3.** Los 5 Pokémon de `mejoras` se parten en **3 de líder + 3 de lugarteniente** (dos canónicos + uno del pool). Ningún líder repite el trío del gimnasio de su provincia.
4. **Bases con entidad propia.** Un catálogo `BaseFaccion` en código y una tabla `faction_base_progress`; 3 pisos (3v1, 3v1, 3v3). No se reutiliza `habitats.mazmorra`, que hoy está vacío en todo el repositorio y cuyo `unique (user, habitat)` colisionaría.
5. **Un ancla por facción, elegida.** Mewtwo, Kyogre, Groudon, uno de Xerneas/Yveltal, uno de Dialga/Palkia/Giratina y uno de Reshiram/Zekrom/Kyurem; más Rayquaza como cruce Aqua+Magma. **Máximo 7 legendarios por cuenta en toda la vida**, no 20.
6. **Captura garantizada, rechazo acotado.** IV mínimo 10, shiny 1/512 **fijado una sola vez**, hasta 5 rechazos: cada uno sube +1 el suelo de IV y +10 % las stats del **jefe** (tope +50 %), nunca las del recluta. Es la única lectura de la regla de `mejoras` que no se convierte en granja infinita.
7. **Sin quinto tipo de misión y sin séptima tarjeta.** Los arcos son hitos de campaña; los encargos de facción viven dentro de la tarjeta de campaña. El tablero «Hoy» sigue con 3 encargos + 1 parte.
8. **Primero Yell, después Skull, después Aqua+Magma.** Yell valida el expediente sin tocar el sistema de anclas; Aqua+Magma justifican Rayquaza y el primer legendario.

Lo que **se aplaza**: retratos, escenas con diálogo, moneda nueva, segmentación del hábitat 49, poblar los 12 hábitats vacíos, combates de 5 y cualquier cambio en gimnasios o en el motor 3v3. Lo que **se descarta**: el «+10 % acumulable» sin tope, el reroll de shiny por rechazo y que el líder pelee con sus legendarios.

---

## 2. Premisa de mundo: una sola mitología

> **La Bruma no borra caminos: borra lo que no puede sostener.**

El mundo se mantiene en pie porque cada provincia tiene un **ancla**: un individuo legendario que ocupa su lugar y sostiene la memoria del territorio. Hace generaciones, una expedición intentó usar las anclas para rehacer el mundo a su manera —tierra, mar, tiempo, belleza, liberación—. No lo consiguió. Al arrancar las anclas de su sitio, dejó **la Bruma**: una niebla que no tapa el paisaje, sino la razón para recorrerlo. Los gimnasios son **faros** que mantienen despejado un tramo. Las facciones son los herederos de aquella expedición: no quieren destruir el mundo, quieren **arreglarlo a su manera**, y por eso son peligrosas.

Esa única regla resuelve las tres apariciones de un legendario sin tocar el código de gimnasios:

| Aparición | Nombre en el mundo | Qué es | Regla |
|---|---|---|---|
| Gimnasio | **Proyección** | Imagen que un faro usa como prueba | No se captura; es el rival de la etapa 4 |
| Hábitat 49 | **Eco** | Copia que la Bruma hace de lo que no puede retener | Captura normal (0-15 IV, 1/2048 shiny) |
| Facción | **Ancla** | El individuo real, fuera de su sitio | Encuentro único, captura garantizada, 1/512 |

Ejemplo: el Mewtwo del gimnasio Psíquico es una proyección del faro; el Mewtwo del laboratorio Rocket es el ancla; el Mewtwo del hábitat 49 es un eco. Mismo sprite, tres reglas, una explicación. Y el epílogo tiene sentido: cuando las anclas vuelven a su sitio, el hábitat 49 deja de ser una jaula y se convierte en el **Archivo Negro**, el archivo de los ecos.

**Regla de tono:** las facciones no compiten por el fin del mundo; compiten por **cómo ordenarlo**. Eso permite que Skull y Yell sean cómicas y locales sin romper la escala, y que Plasma sea la última porque su idea —«liberar» a los Pokémon de los entrenadores— es la única que discute el juego mismo.

---

## 3. Modelo de facción: ficha canónica y representación sin retratos

Cada facción es un **expediente** con estos campos, definidos en código (no en BD):

| Campo | Contenido | Ejemplo |
|---|---|---|
| `slug` | Identificador estable | `rocket` |
| `nombre` | Nombre en juego | «La Sombra» (eco canónico: Team Rocket) |
| `ideologia` | Una línea | «El mundo se arregla desde arriba.» |
| `territorio` | Provincia + base + hábitats de patrulla | Barcelona · Pantano Ponzoña · Las Ramblas, Cueva Granito |
| `lider` | Nombre, título y línea ≤140 caracteres | Don Ciro, «el que cobra» |
| `lugarteniente` | Nombre, rol y equipo | La Cuenta, «la que suma» |
| `equipoLider` | 3 especies | Persian, Rhyperior, Honchkrow |
| `equipoLugarteniente` | 3 especies | Nidoqueen, Nidoking, Muk |
| `insignia` | `species_id` del Pokémon emblema | Persian (53) |
| `base` | `base_slug`, hábitat y 3 pisos | `vertedero`, Pantano Ponzoña |
| `ancla` | Lista de especies o `null` | Mewtwo |
| `recompensaUnica` | Un solo tipo mecánico | Llave del vertedero |
| `tono` | Una palabra | Noir |

**Representación sin arte nuevo.** No hay retratos de NPCs, así que el emblema es la identidad:

- **Emblema CSS**: un marco geométrico de dos colores + el sprite del Pokémon insignia (`x-sprite`) + una inicial. Sin imágenes nuevas.
- **Color de facción**: una paleta de 8, no los 7 bonguris (que se agotan y además significan provincia, no facción).

| Facción | Color principal | Color de contraste | Insignia |
|---|---|---|---|
| Yell | `#E0B100` amarillo | `#2B2B2B` | Toxtricity (849) |
| Skull | `#6B7A3A` oliva | `#1F1F1F` | Golisopod (768) |
| Aqua | `#1F6F8B` azul profundo | `#E8F6F3` | Sharpedo (319) |
| Flare | `#C0504D` coral | `#F2D7D5` | Chandelure (609) |
| Magma | `#A63A1E` teja | `#F6DDCC` | Camerupt (323) |
| Galaxia | `#4B3F72` violeta | `#C9C2E8` | Bronzong (437) |
| Rocket | `#7A1F1F` granate | `#1A1A1A` | Persian (53) |
| Plasma | `#1E7A6B` verde frío | `#E8F6F3` | Hydreigon (635) |

**Estados**: `dormida` (sin presencia), `activa` (patrulla, bloqueo blando, emblema pintado), `derrotada` (patrulla retirada, base abierta, nota) y `cicatriz` (marca permanente en el hábitat; admite el flag `reconciliada` si el jugador eligió piedad). Tres estados visibles, uno interno.

---

## 4. Las 8 facciones

### 4.1 Ficha, territorio y elenco

| Cap. | Facción (slug) | Provincia | Base | Líder | Lugarteniente | Insignia | Ancla | Tono |
|---|---|---|---|---|---|---|---|---|
| 1 | **Yell** (`yell`) | Mutxamel | Calvari (33) | Púa, «la voz del barrio» | El Batería | Toxtricity | — | Cómico |
| 2 | **Skull** (`skull`) | Extremadura | Bosque Crecido (3) | El Nano, «el que cobija» | La Sombra | Golisopod | — | Trágicómico |
| 3 | **Aqua** (`aqua`) | Galicia | Corriente Marina (13) | Marea, «la que sube» | El Práctico | Sharpedo | Kyogre | Fanático |
| 4 | **Flare** (`flare`) | Nova Alacant | Pradera Orgullosa (37) | El Jardinero | La Modelo | Chandelure | Xerneas **o** Yveltal | Decadente |
| 5 | **Magma** (`magma`) | Andalucía | Cráter (14) | Bruno, «el que perfora» | La Capataz | Camerupt | Groudon | Industrial |
| 6 | **Galaxia** (`galaxia`) | Euskadi | Cámara Omega (45) | Cero, «el que mide» | El Cartógrafo | Bronzong | Dialga, Palkia **o** Giratina | Gélido |
| 7 | **Rocket** (`rocket`) | Barcelona | Pantano Ponzoña (34) | Don Ciro, «el que cobra» | La Cuenta | Persian | Mewtwo | Noir |
| 8 | **Plasma** (`plasma`) | Vacío | El Altavoz (sub-lugar de 49) | El Predicador | El Coro | Hydreigon | Reshiram, Zekrom **o** Kyurem | Demagogo |

*Los nombres de líder son propuesta original; entre paréntesis irían los canónicos si el desarrollador decide licenciarlos. Ver §12.*

### 4.2 Equipos 3v3 y resolución de solapes

| Facción | Equipo del líder (3) | Equipo del lugarteniente (3) | Solape resuelto |
|---|---|---|---|
| Yell | Perrserker (863), Toxtricity (849), Grimmsnarl (861) | Obstagoon (862), Scrafty (560), Skuntank (435) | El gimnasio Siniestro usa Obstagoon; el líder no. Slowbro-Galar se descarta: la forma 10165 existe, pero no aporta al trío. |
| Skull | Golisopod (768), Escavalier (589), Vikavolt (738) | Scizor (212), Kleavor (900), Heracross (214) | El gimnasio Bicho usa Scizor; pasa al lugarteniente. Salazzle no tiene hábitat: se sustituye por Vikavolt. |
| Aqua | Sharpedo (319), Walrein (365), Crawdaunt (342) | Gyarados (130), Drednaw (834), Cloyster (91) | El gimnasio Agua usa Swampert; pasa al lugarteniente. El líder no repite el trío. |
| Flare | Chandelure (609), Goodra (706), Altaria (334) | Granbull (210), Mawile (303), Slurpuff (685) | El gimnasio Hada usa Togekiss/Gardevoir; el líder no. Barbaracle no tiene hábitat: se sustituye por Altaria. |
| Magma | Camerupt (323), Houndoom (229), Flygon (330) | Steelix (208), Magcargo (219), Krookodile (553) | El gimnasio Fuego usa Blaziken; el líder no. El gimnasio Tierra usa Steelix; pasa al lugarteniente. |
| Galaxia | Bronzong (437), Weavile (461), Porygon-Z (474) | Magnezone (462), Alakazam (65), Cofagrigus (563) | El gimnasio Psíquico y el de Acero usan los legendarios y Bronzong; el líder no lleva legendarios. |
| Rocket | Persian (53), Rhyperior (464), Honchkrow (430) | Nidoqueen (31), Nidoking (34), Muk (89) | El gimnasio Veneno usa Nidoqueen/Nidoking/Roserade; pasan al lugarteniente, no al líder. |
| Plasma | Hydreigon (635), Froslass (478), Eelektross (604) | Cofagrigus (563), Bisharp (625), Vanilluxe (584) | El gimnasio Dragón usa Hydreigon en etapa 2, no en el líder. El trío tao queda como ancla, no como equipo. |

**Regla de oro:** ningún líder repite el trío exacto del gimnasio de su provincia; los lugartenientes y los pisos de base pueden reutilizar especies, nunca el trío completo. Las especies sin hábitat (`Eelektross`, `Vanilluxe`, `Porygon-Z` sí tiene) se usan solo en equipos autorados, igual que el catálogo de gimnasios ya usa especies fuera del pool del hábitat.

### 4.3 Los 24 actos

Cada acto = **1 hito autorado + 1-2 encargos de facción**. El verbo cambia en cada acto: investigar → sabotear/rescatar → asaltar/duelar.

| Cap. | Facción | Acto | Título | Objetivo | Clímax |
|---|---|---|---|---|---|
| 1 | Yell | I | *El escenario del Calvari* | Batir 3 patrullas y encontrar el cartel del «concierto» | Primer combate de grunt |
| 1 | Yell | II | *La prueba de la bocina* | Sabotear el altavoz (piso 1) y rescatar al «batería» | Lugarteniente |
| 1 | Yell | III | *El último bis* | Asaltar la base y vencer a Púa | Líder 3v3 sin buff si sabotaje hecho |
| 2 | Skull | I | *El peaje del bosque* | Batir 3 patrullas y descubrir el peaje | Primer grunt |
| 2 | Skull | II | *La madriguera* | Cortar las trampas (piso 1) y liberar al «cachorro» | Lugarteniente |
| 2 | Skull | III | *El bosque que cobija* | Asaltar la base y decidir el futuro del bosque | Líder; elección de estado |
| 3 | Aqua | I | *El dique de la marisma* | Batir 3 patrullas y encontrar el dique | Primer grunt |
| 3 | Aqua | II | *La marea negra* | Sabotear las bombas y rescatar al vigía | Lugarteniente |
| 3 | Aqua | III | *La ballena y el ancla* | Asaltar el dique y vencer a Marea | Líder + duelo Kyogre |
| 4 | Flare | I | *El jardín cercado* | Batir 3 «jardineros» y encontrar la lista | Primer grunt |
| 4 | Flare | II | *La lista de los elegidos* | Robar la lista y rescatar al excluido | Lugarteniente |
| 4 | Flare | III | *La belleza que quema* | Asaltar el jardín y vencer al Jardinero | Líder + duelo Xerneas/Yveltal |
| 5 | Magma | I | *La perforadora* | Batir 3 patrullas y encontrar la máquina | Primer grunt |
| 5 | Magma | II | *El horno* | Sabotear la perforadora y rescatar al topógrafo | Lugarteniente |
| 5 | Magma | III | *El corazón de la tierra* | Asaltar el cráter y vencer a Bruno | Líder + duelo Groudon; epílogo Rayquaza |
| 6 | Galaxia | I | *El reloj de la Cámara Omega* | Batir 3 acólitos y encontrar el astrolabio | Primer grunt |
| 6 | Galaxia | II | *El mapa que no existe* | Sabotear el astrolabio y rescatar al cartógrafo | Lugarteniente |
| 6 | Galaxia | III | *El círculo se cierra* | Asaltar la cámara y vencer a Cero | Líder + duelo Dialga/Palkia/Giratina |
| 7 | Rocket | I | *El peaje del vertedero* | Batir 3 patrullas y encontrar la lista | Primer grunt |
| 7 | Rocket | II | *La lista de la mafia* | Sabotear el vertedero y rescatar al soplón | Lugarteniente |
| 7 | Rocket | III | *El gato y el laboratorio* | Asaltar el laboratorio y vencer a Don Ciro | Líder + duelo Mewtwo |
| 8 | Plasma | I | *La voz en la Bruma* | Batir 3 predicadores y encontrar el altavoz | Primer grunt |
| 8 | Plasma | II | *El amplificador* | Sabotear el altavoz y rescatar al disidente | Lugarteniente |
| 8 | Plasma | III | *La liberación* | Asaltar El Altavoz y vencer al Predicador | Líder + duelo Reshiram/Zekrom/Kyurem |

**«9 combates» de `mejoras`, descompuesto:** 3 grunts (acto I) + 1 lugarteniente (acto II) + 3 pisos de base (acto III) + 1 líder (acto III) + 1 ancla (epílogo) = **9**. Skull y Yell tienen 8 porque no tienen ancla. No hace falta inventar un formato de 5: la unidad es el expediente, no el número.

---

## 5. Estructura narrativa: 8 historias, una a la vez

### 5.1 Orden y pares

La campaña es **lineal por capítulo**. Solo hay un expediente abierto a la vez; el resto está `dormido`. Eso evita ocho arcos simultáneos y permite que cada facción tenga su propio clímax sin competir por atención.

Hay cuatro pares temáticos que se reflejan, no que se solapan:

| Par | Capítulos | Pregunta que comparten |
|---|---|---|
| **Yell / Flare** | 1 y 4 | ¿Quién merece un escenario: el que grita o el que es bello? |
| **Skull / Rocket** | 2 y 7 | ¿Una banda es una familia o un negocio? |
| **Aqua / Magma** | 3 y 5 | ¿Se arregla el mundo secándolo o quemándolo? |
| **Galaxia / Plasma** | 6 y 8 | ¿Se ordena el mundo con un reloj o con una voz? |

El jugador no ve el par como dos arcos: ve una facción, y en el capítulo siguiente reconoce el eco de la anterior. Eso da sensación de tema sin coste de contenido.

### 5.2 Cómo se evita la fatiga

- **Un solo expediente abierto.** El cuaderno muestra 1 facción activa y, como mucho, 2 encargos de facción dentro de la tarjeta de campaña. Nada de 8 listas.
- **Verbo distinto por acto.** Investigar, sabotear/rescatar, asaltar/duelar. El jugador nunca hace tres veces «derrota 5».
- **Dos facciones sin legendario al principio.** Yell y Skull son locales y cómicas; el jugador aprende el sistema antes de que haya dioses.
- **Cierre por capítulo.** Al terminar el acto III, la facción pasa a `derrotada` y no vuelve a pedir nada. Su base queda como ruina opcional, no como chore.
- **Sin diarias de facción.** Los encargos de facción caducan al cerrar el acto, no cada día. No hay streaks ni penalización.

### 5.3 El Archivo Negro y «otros» villanos

El hábitat 49 se mantiene **sellado en duro** hasta el epílogo de TASK-043. Al cerrarlo, la etiqueta de la UI cambia a **«Archivo Negro»** sin tocar la fila de `habitats`: es el mismo hábitat, con otra función. Allí viven:

- Los **ecos** de los legendarios no elegidos (captura normal).
- Las **notas** de las facciones derrotadas (colección).
- Los **expedientes sin capítulo**: si el desarrollador quiere añadir Team Star, Cipher, Dim Sun o Macro Cosmos, entran aquí como **archivos de 1 acto sin ancla**, no como un noveno capítulo. Es el mecanismo de extensión, no una promesa de contenido.

---

## 6. Integración con misiones: hitos + encargos, sin quinto tipo

### 6.1 Dos mecanismos, un solo ledger

| Mecanismo | Dónde vive | Cuántos | Semántica |
|---|---|---|---|
| **Hito de facción** | `campaign_progress`, clave `fac:<slug>:<acto>` | 3 por facción + 1 epílogo | Autorado, secuencial, de un solo uso. Es el arco. |
| **Encargo de facción** | `mission_instances` con `period_type = 'faccion'`, `period_key = <slug>:<acto>`, `slot = 0..1`, `scope_key = 'fac:<slug>'` | 2 por acto | Repetible dentro del acto, caduca al cerrarlo. Es la textura. |

**El límite de 6 tarjetas se respeta:** «Hoy» = 1 parte + 3 encargos; «Semana» = 1; «Campaña» = 1 hito activo + hasta 2 encargos de facción **dentro de la misma tarjeta**. No hay quinta categoría ni séptima tarjeta. El expediente completo es una **ruta**, no una tarjeta.

### 6.2 Arquetipos y mapeo a eventos

| Arquetipo | Objetivo concreto | Evento | ¿Nuevo? |
|---|---|---|---|
| **Batir patrulla** | 3 combates contra grunts de la facción | `faccion_combate`, `rol = grunt` | Tipo nuevo, mismo ledger |
| **Vencer lugarteniente** | 1 combate 3v3 contra el lugarteniente | `faccion_combate`, `rol = lugarteniente` | Ídem |
| **Vencer líder** | 1 combate 3v3 contra el líder | `faccion_combate`, `rol = lider` | Ídem |
| **Asaltar base** | Completar 3 pisos | `faccion_piso`, `source_id = battleId` | Ídem |
| **Sabotaje** | Completar el piso 2 (modo `sabotaje`) | `faccion_piso`, `piso_kind = sabotaje` | Ídem |
| **Rescate** | Ganar un combate con ≥2 Pokémon vivos | `faccion_combate` + `supervivientes` | Campo nuevo en `EventoJuego` |
| **Control de territorio** | Derrotar 5 salvajes en el hábitat controlado | `ruta` existente + `faccion_presente` | Reutiliza ruta |
| **Ancla** | Ganar el duelo y aceptar/rechazar | `faccion_ancla` | Tipo nuevo, fuera de misiones |

Ningún arquetipo necesita un sistema de acciones nuevas: sabotaje y asalto son **pisos de base**; rescate es una **condición de supervivencia** sobre un combate normal; control de territorio es una **misión de ruta** con una etiqueta de facción.

### 6.3 Idempotencia

- Todo evento entra en `mission_events` con unique `(user_id, event_type, source_id)`.
- `source_id` = `battleId` para combates y pisos; para el rechazo del ancla, `slug:rechazo:N`.
- `mission_event_applications` unique `(mission_instance_id, mission_event_id)` evita contar dos veces el mismo evento en la misma misión.
- La recompensa se concede en la misma transacción que marca el hito/acto; `faction_progress.rewarded_at` + `lockForUpdate` impiden el doble pago.
- El cierre de batalla ya es idempotente por `battleId`; las facciones no añaden un segundo punto de escritura.

### 6.4 Ejemplos de encargos de facción

| Facción / acto | Título | Objetivo | Recompensa |
|---|---|---|---|
| Yell I | *Carteles rotos* | Derrota 5 Pokémon en Calvari | 6 familia, 8 EV, 1 tipo |
| Yell II | *Un altavoz menos* | Completa el piso 2 de la base | 8 familia, 1 material |
| Skull I | *Trampas en el bosque* | Derrota 5 Pokémon de tipo Bicho en Bosque Crecido | 6 familia, 1 bonguri verde |
| Skull II | *El cachorro* | Gana un combate con 2 supervivientes | 8 familia, 1 objeto |
| Aqua I | *El dique* | Derrota 5 Pokémon de tipo Agua en Corriente Marina | 7 familia, 9 EV |
| Magma II | *El topógrafo* | Completa una exploración de 2 h en el Cráter | 8 familia, 2 bayas |
| Rocket II | *El soplón* | Gana un combate con 2 supervivientes en el Pantano | 10 familia, 1 material |
| Plasma III | *La última voz* | Derrota 3 predicadores en el Vacío | 12 familia, 1 nota |

---

## 7. Presencia en el mundo

### 7.1 Opcional vs bloqueante

| Capa | Qué es | ¿Bloquea? |
|---|---|---|
| **Presencia ambiental** | Emblema pintado, una línea, patrulla de 1-3 grunts opcionales | No |
| **Bloqueo blando** | El nivel 2 o 3 del hábitat queda tras la facción, con la válvula de nivel de TASK-043 | No (la válvula lo abre) |
| **Base** | Entrada al asalto de 3 pisos | Solo el acto III |
| **Ancla** | Duelo legendario | No; es opcional y única |

La regla es: **solo el líder del acto III es obligatorio para el hito**. Todo lo demás —sabotaje, rescate, patrullas, ancla— es opcional y cambia el mundo o la recompensa, no la puerta. Si el jugador no sabotea el altavoz, el líder empieza con `bonusInicialStats` +20 %; si lo hace, pelea limpio. Eso hace que lo opcional se note sin bloquear.

### 7.2 Tres estados por facción

| Facción | Activa | Derrotada | Cicatriz |
|---|---|---|---|
| **Yell** | Escenario con altavoces en Calvari; 3 grunts; el nivel 3 está «ocupado» | Escenario vacío; el nivel 3 se abre; nota | Pintada «PÚA» en la pared; el Batería saluda |
| **Skull** | Cuerdas y trampas en Bosque Crecido; peaje | Trampas retiradas; paso al nivel 3; nota | Nido reconstruido; placa «aquí se cobijó alguien» |
| **Aqua** | Dique en Corriente Marina; el nivel 3 inundado | Compuertas abiertas; Mar Sereno nivel 3; nota | Marca de marea en la roca; el Práctico barre |
| **Flare** | Valla y jardín privado en Pradera Orgullosa; solo «elegidos» | Valla caída; nivel 3 abierto; nota | Jardín abandonado; flores fuera del seto |
| **Magma** | Perforadora en el Cráter; calor y polvo | Perforadora parada; Sierra Quemada nivel 3; nota | Grietas enfriadas; la Capataz vende chatarra |
| **Galaxia** | Reloj y círculo en Cámara Omega; nivel 3 «congelado» | Reloj parado; nivel 3 abierto; nota | Astrolabio roto; el Cartógrafo dibuja el mapa |
| **Rocket** | Vertedero y peaje en Pantano Ponzoña; carteles | Vertedero cerrado; nivel 3 abierto; nota | El gato de Don Ciro duerme en la puerta |
| **Plasma** | Altavoz en el Vacío; la Bruma «habla» | Altavoz apagado; acceso al Archivo; nota | Una cinta roja atada a la entrada |

La «cicatriz» es el estado que hace que el mundo se sienta tocado: no da poder, da memoria. Es barata (un texto y un tinte) y es la recompensa emocional del arco.

---

## 8. Bases y mazmorras

### 8.1 Decisión: entidad propia, no `habitats.mazmorra`

| Opción | Veredicto | Motivo |
|---|---|---|
| (a) Reutilizar `habitats.mazmorra` | No | 0/56 configuradas, sin seeder ni CRUD; `dungeon_progress` tiene unique `(user, habitat)` y colisionaría con la mazmorra del hábitat; el piso final debe ser 3v3, no 3v1 |
| (b) `BaseFaccion` en catálogo + `faction_base_progress` | **Sí** | Aísla el contenido, no toca la mazmorra existente, permite 3 pisos con modos distintos |
| (c) Usar hábitats como territorio sin base | No | Pierde el clímax de asalto y el «9 combates» |

La base **reutiliza el combate** de mazmorra (`GeneradorJefeMazmorra`, 3v1) y su **cooldown de 1 h**, pero no su persistencia ni su UI. El piso 3 no es 3v1: es un **3v3** contra el líder, con el generador de equipos de gimnasio normalizado a 3.

### 8.2 Estructura de una base

| Piso | Modo | Enemigo | Combate | Recompensa |
|---|---|---|---|---|
| 1 | Guardia | Pokémon insignia / guardián | 3v1 | Nivel gimnasio (×10) + 1 material |
| 2 | Sabotaje | «Máquina» o ace de la facción | 3v1 | Nivel gimnasio (×10) + 1 material |
| 3 | Líder | Equipo 3v3 del líder | 3v3 | 2× gimnasio (×20) + emblema + nota + recompensa única |

- **Cooldown**: 1 h solo tras derrota, por piso. La victoria avanza y no se repite.
- **Derrota en un piso**: se conserva el progreso de los pisos anteriores; se reintenta el piso actual tras la hora.
- **Repetición**: tras completar la base, queda una «ruina» con el piso 1 repetible por un material menor (v2). Nunca caramelos en repetible.

| Facción | Base (hábitat) | Piso 1 | Piso 2 | Piso 3 (líder) |
|---|---|---|---|---|
| Yell | Calvari (33) | Perrserker | Skuntank | Perrserker / Toxtricity / Grimmsnarl |
| Skull | Bosque Crecido (3) | Kleavor | Heracross | Golisopod / Escavalier / Vikavolt |
| Aqua | Corriente Marina (13) | Drednaw | Cloyster | Sharpedo / Walrein / Crawdaunt |
| Flare | Pradera Orgullosa (37) | Slurpuff | Granbull | Chandelure / Goodra / Altaria |
| Magma | Cráter (14) | Magcargo | Steelix | Camerupt / Houndoom / Flygon |
| Galaxia | Cámara Omega (45) | Cofagrigus | Magnezone | Bronzong / Weavile / Porygon-Z |
| Rocket | Pantano Ponzoña (34) | Muk | Weezing | Persian / Rhyperior / Honchkrow |
| Plasma | El Altavoz (sub-lugar de 49) | Bisharp | Rotom | Hydreigon / Froslass / Eelektross |

**Hábitats vacíos:** no se usan. Los 12 sin pool (Bonalba, Monte Disciplina, Bosque Seta, Cañar, Jungla, Santa Pola, Monte Oscuro, Tierra Abrasada, Sierra Aitana, Cueva del Eco, Fosa Abisal, Pradera Tranquila) quedan fuera del arco. Si en v2 se quieren como «ruinas», primero hay que poblarlos con 8-12 especies; eso es una tarea de contenido aparte, no de facciones.

**Plasma en el Vacío:** su base es un sub-lugar del hábitat 49 con progreso propio, accesible por el hito del capítulo 8 aunque el pool salvaje siga sellado. Es la excepción que confirma la regla: el jugador entra a «El Altavoz», no al hábitat.

---

## 9. Legendarios: encuentro, captura y duplicidad

### 9.1 Regla del Ancla

| Pieza | Regla | Por qué |
|---|---|---|
| **Encuentro** | 3v1 con `esJefe`, 3 fases, `bonusInicialStats` +20 %, multiplicadores propios (`ReglasAncla`: ×3 stats, ×4 HP), nivel del capítulo | Reutiliza el jefe de mazmorra sin el ×10, que sería absurdo contra un legendario |
| **Captura** | Garantizada al aceptar; sin tirada de captura | Es el premio de un hito único, no una misión repetible |
| **IV** | Suelo 10 por stat (escala 0-15), reparto aleatorio por encima | Coherente con un nido nivel 3; no rompe la escala |
| **Shiny** | 1/512, **fijado en la primera victoria** y persistido; rechazar no rerolea | Evita el gambling y el farmeo de shiny |
| **Rechazo** | Máximo 5; cada uno +1 al suelo de IV (tope 15) y +10 % a las stats del **jefe** (tope +50 %) | La regla de `mejoras` se cumple, pero el poder va al rival, no al recluta |
| **Aceptar** | Crea el reclutado con `CrearReclutado`, marca `legendary_ledger`, cierra el ancla | Un solo flujo, ya existente |
| **Perder** | Cooldown 1 h, sin pérdida de progreso ni de shiny | Consistente con mazmorra |
| **Unicidad** | Una ancla por facción; índice único parcial `(user_id, species_id) WHERE source = 'ancla'` | No impide capturar ecos normales de la misma especie |
| **Tope vital** | 7 anclas por cuenta (6 de facción + Rayquaza) | Un legendario se siente legendario si no hay 20 |

**Por qué no la regla literal de `mejoras`:**
- El «+10 % acumulable» sin tope convierte al legendario en un objeto de cultivo y rompe la curva de stats.
- El «1/512» con rechazo que repite el encuentro es un reroll: el jugador rechaza hasta que salga shiny. Al fijar el shiny en la primera victoria, el rechazo solo compra IV, que está acotado.
- «Derrotar asegura el reclutamiento» se mantiene, pero **solo en el ancla**, no en los ecos del hábitat 49.

### 9.2 Duplicidad con gimnasios y hábitat 49

| Facción | Ancla(s) | Condición | Ya aparece en gimnasio | Resolución |
|---|---|---|---|---|
| Rocket | Mewtwo | Acto III | Psíquico, etapa 4 | Proyección del faro; el ancla es el individuo del laboratorio |
| Aqua | Kyogre | Acto III | No | Ancla única |
| Magma | Groudon | Acto III | No | Ancla única |
| Flare | Xerneas **o** Yveltal | Acto III + elección | No | Elección; el otro queda como eco |
| Galaxia | Dialga, Palkia **o** Giratina | Acto III + elección | Dragón/Fantasma/Acero | Proyecciones; el elegido es el ancla |
| Plasma | Reshiram, Zekrom **o** Kyurem | Acto III + elección | No (solo paradojas) | Elección; los otros, ecos |
| — | Rayquaza | Aqua y Magma derrotadas | Dragón, etapa 4 | Ancla de cruce; proyección en el gimnasio |
| Skull | — | — | — | Sin ancla: clímax humano |
| Yell | — | — | — | Sin ancla: clímax humano |

**Hábitat 49:** no se segmenta en v1. Se mantiene como pool único de 100, sellado hasta el epílogo. Al abrirlo, es el **Archivo Negro**: ecos con captura normal, sin trato especial. Los no elegidos de Flare/Galaxia/Plasma se encuentran allí como ecos, no como anclas. Eso respeta el trabajo ya hecho y no exige tocar `pokemon_habitat.csv`.

**Skull y Yell sin legendario:** no es un hueco, es una decisión. Su clímax es **humano**: la banda que no quiere desaparecer. Su recompensa única es un reclutado firma (Yell: Toxtricity con IV 12) o un desbloqueo de mundo (Skull: paso al nivel 3 del bosque). Elevarlos a amenaza apocalíptica rompería su tono y les daría un dios que no les corresponde.

---

## 10. Recompensas y economía

### 10.1 Qué paga cada cosa

| Fuente | Multiplicador | Frecuencia | Qué da |
|---|---|---|---|
| Grunt | ×2 (entrenador) | 3 por acto I | Caramelos pequeños + 1 objeto |
| Lugarteniente | ×10 (gimnasio) | 1 por acto II | Caramelos medios + 2 objetos |
| Líder | ×20 (2× gimnasio) | 1 por facción | Caramelos + emblema + nota + recompensa única |
| Piso de base | ×10 (gimnasio) | 2 por facción | Material + EXP |
| Ancla | Sin caramelos | 1 por facción | Reclutado legendario |
| Encargo de facción | Nivel encargo | 2 por acto | Caramelos pequeños |

Nada de facción es repetible con caramelos. El líder se vence una vez; la base se completa una vez; el ancla se captura una vez. La única repetición (ruina, v2) paga material, no moneda.

### 10.2 Recompensas únicas, sin moneda nueva

| Facción | Emblema | Nota | Recompensa mecánica única | Se paga con |
|---|---|---|---|---|
| Yell | Sí | Sí | Reclutado firma Toxtricity (IV 12) | Sistema de reclutamiento |
| Skull | Sí | Sí | Paso al nivel 3 de Bosque Crecido | Desbloqueo de hábitat |
| Aqua | Sí | Sí | Compuerta: nivel 3 de Mar Sereno | Desbloqueo de hábitat |
| Flare | Sí | Sí | Huevo de Hada con IV 12 | Sistema de huevos |
| Magma | Sí | Sí | Receta de forja «núcleo de magma» | Forja existente |
| Galaxia | Sí | Sí | Astrolabio: revela anclas en el mapa | Información/UI |
| Rocket | Sí | Sí | Llave del vertedero: nivel 3 del Pantano | Desbloqueo de hábitat |
| Plasma | Sí | Sí | Reclutado firma Hydreigon (IV 12) + nota fundacional | Reclutamiento + colección |

**Anti-canibalización:**
- Gimnasio = medalla + nivel + caramelos. Sigue siendo la escalera de poder.
- Mazmorra = botín grande (×70). Sigue siendo el sumidero de farmeo.
- Facción = historia + emblema + nota + una recompensa única. No compite por caramelos.
- El líder paga 2× gimnasio **una vez**; un jugador no puede convertirlo en su granja.

**Anti-inflación:**
- Topes vitales: 8 emblemas, 7 anclas, 24 actos, 8 notas.
- Las recompensas de facción quedan **fuera del tope semanal** de TASK-043 porque son finitas, no repetibles. El tope semanal sigue aplicando a encargos y semanales.
- Ni shiny garantizado ni captura semanal extra: el ancla es un hito, no una misión.

---

## 11. Arquitectura técnica a alto nivel

### 11.1 Módulo y capas

**Bounded context:** `src/Facciones`. No es un submódulo de `Misiones`: tiene su propio estado (actos, bases, anclas) y su propia persistencia. La relación es **unidireccional**: `Facciones` emite eventos; `Misiones` los consume. `Facciones` no llama a casos de uso de `Misiones`, así que no hay ciclo.

| Capa | Contenido |
|---|---|
| **Dominio** | `Faccion`, `Expediente`, `ActoFaccion`, `BaseFaccion`, `AnclaLegendaria`, VOs (`FaccionSlug`, `EstadoFaccion`, `RolFaccion`, `EmblemaFaccion`, `RecompensaFaccion`, `ReglasAncla`), `CatalogoFacciones`, `CatalogoBases`, puertos. Sin Eloquent, HTTP ni facades. |
| **Aplicación** | `ObtenerExpedientes`, `ObtenerExpediente`, `IniciarCombateFaccion`, `RegistrarResultadoFaccion`, `IniciarAsaltoBase`, `RegistrarResultadoBase`, `IniciarDueloAncla`, `ResolverDueloAncla`, `ObtenerEstadoMundo`. |
| **Infraestructura** | Modelos Eloquent `FactionProgress`, `FactionBaseProgress`, `LegendaryLedger`; repositorios; controladores; rutas; migraciones. |
| **Presentación** | `resources/views/facciones/**`, componentes `x-emblema-faccion`, `x-estado-faccion`, `x-expediente-card`; integración en el modal de victoria. |

**Catálogo en código, no en BD.** 8 facciones y 8 bases son contenido autorado, versionado con el código, sin admin ni seeder. La BD solo guarda estado por usuario. Si en v2 se quiere editar sin despliegue, se migra detrás del mismo puerto `CatalogoFacciones` sin tocar dominio ni UI.

### 11.2 Tablas (mínimo necesario)

| Tabla | Unique / índices | Contenido |
|---|---|---|
| `faction_progress` | unique `(user_id, faction_slug)`; índice `(user_id, status)` | `act` 1-3, `status`, `anchor_state`, `refusals` 0-5, `choice` (jsonb), `rewarded_at` |
| `faction_base_progress` | unique `(user_id, base_slug)`; check `current_floor` 1-3 | `current_floor`, `completed_at` |
| `legendary_ledger` | unique parcial `(user_id, species_id) WHERE source = 'ancla'`; índice `(user_id, faction_slug)` | `species_id`, `faction_slug`, `source` (`ancla`/`eco`), `reclutado_id` |

No se crean tablas de hitos ni de notas: se reutilizan `campaign_progress` (`fac:<slug>:<acto>`) y `field_notes` (`fac:<slug>`) de TASK-043. Eso ahorra dos tablas y mantiene un solo cuaderno.

### 11.3 Combate y presentación

- **Tipos nuevos:** `meta['tipo'] = 'faccion'` (grunt, lugarteniente, líder 3v3) y `'faccion_base'` (piso 3v1 o líder 3v3). El `match` de `PresentadorResultadoBatalla::procesar()` gana dos ramas explícitas; no se deja caer en `entrenador` por defecto.
- **Equipo rival:** `GeneradorEquipoFaccion` reutiliza `MapeadorPokemonBatalla` y normaliza a 3, igual que el gimnasio. No se duplica la lógica de stats/movimientos.
- **Jefe de piso:** `GeneradorJefeMazmorra` con `ReglasAncla`/`ReglasBase` propias; el ×10 de mazmorra no se toca.
- **Modal de victoria:** **no se usa `log`**, que es el registro de batalla. Se añade a `rewards` una clave `expediente` (emblema, acto, línea, enlace) y otra `ancla` (sprite, IV, shiny, aceptar/rechazar). `BatallaVistaPresenter::rewardsVista()` las normaliza; `combate.blade.php` pinta dos parciales nuevos.
- **Identidad:** `nombreRival` = «Don Ciro · La Sombra», no «Entrenador 1». Es un tipo de combate nuevo con equipo fijo, no un alias del entrenador diario.

### 11.4 Rutas y UI

| Ruta | Pantalla |
|---|---|
| `GET /misiones/facciones/{slug}` | Expediente: ficha, 3 actos, base, ancla, nota |
| `GET /misiones/facciones/{slug}/base` | Asalto: 3 pisos, estado, cooldown |
| `POST /misiones/facciones/{slug}/base/{piso}/iniciar` | Crea la batalla del piso |
| `POST /misiones/facciones/{slug}/ancla/iniciar` | Crea el duelo |
| `POST /misiones/facciones/{slug}/ancla/aceptar` | Captura |
| `POST /misiones/facciones/{slug}/ancla/rechazar` | Rechazo acotado |

Sin ítem nuevo en el nav: el acceso está en la pestaña **Campaña** de `/misiones` y en la tira de **Presencia** del hábitat. Es coherente con el nav ya saturado (6 ítems) y con el principio de no añadir superficie.

### 11.5 Fases

| Fase | Alcance | Para qué |
|---|---|---|
| **MVP** | Yell completo: catálogo, `faction_progress`, `faction_base_progress`, 3 grunts + lugarteniente + 3 pisos + líder, expediente, modal, tira de presencia. Sin ancla. | Validar el bucle de expediente sin el sistema de legendarios |
| **v1** | Skull + Aqua + Magma; `legendary_ledger`; `ReglasAncla`; Rayquaza; hitos de campaña; encargos de facción | Validar el ancla y el cruce |
| **v2** | Flare, Galaxia, Rocket, Plasma; Archivo Negro; notas; variantes de estado; «expedientes sin capítulo» | Cerrar las 8 historias |

**Verificación:** `composer run analyse`, `analyse:hard`, `arch` con colector `Facciones` (dominio sin Eloquent/HTTP), revisión de controladores delgados y de que ninguna vista calcula reglas. Sin tests, según el flujo.

---

## 12. Riesgos y decisiones abiertas

| Severidad | Riesgo | Mitigación |
|---|---|---|
| **Alta** | Carga de autoría: 24 actos + 8 notas + ~40 encargos | Plantilla de 1 línea por acto, presupuesto de ~600-800 caracteres por facción; el resto es reutilización |
| **Alta** | IP: nombres, líderes y equipos oficiales | Nombres originales en UI, slugs canónicos internos, emblemas propios (CSS + sprite), sin logos ni diálogo copiado; mantener `THIRD_PARTY_NOTICES` |
| **Alta** | Canibalización de gimnasios y mazmorras | Gimnasio = medalla; mazmorra = botín; facción = historia + recompensa única; sin caramelos repetibles |
| **Alta** | Power creep del ancla | 1 por facción, 7 vitales, rechazo con tope 5, +10 % al jefe, shiny fijado |
| **Media** | Doble mitología Bruma/legendarios | Regla única Proyección/Eco/Ancla; las facciones explotan la Bruma, no la causan |
| **Media** | Duplicidad con gimnasios | Proyecciones; no se toca el catálogo de gimnasios |
| **Media** | Límite de 6 tarjetas | Encargos de facción dentro de la tarjeta de campaña; el tablero diario no cambia |
| **Media** | `habitats.mazmorra` vacío y sin CRUD | No se reutiliza; `BaseFaccion` en código + tabla propia |
| **Media** | 12 hábitats vacíos | Excluidos del arco; poblarlos es tarea aparte |
| **Media** | Nivel de capítulo vs nivel de gimnasio de TASK-043 | Definir un `nivelExpediente` por capítulo (12/22/40/50/62/75/88/100) y reconciliarlo con la tabla de TASK-043 |
| **Baja** | Assets: sin retratos ni emblemas | Emblema CSS + sprite insignia; paleta de 8, no bonguris |
| **Baja** | Rendimiento | Catálogo en código; consultas por unique; sin N+1 |
| **Baja** | Migración | Tablas aditivas; flag `facciones.enabled`; abuelo para cuentas existentes |
| **Baja** | `match` cae en `entrenador` para tipos desconocidos | Ramas explícitas y validación de `tipo` en el presentador |

### Decisiones que necesito del desarrollador (cerradas)

1. **¿Nombres originales en la UI** (recomendado: Don Ciro, Púa, El Jardinero) **o canónicos** (Giovanni, Guzma, Lysandre), manteniendo los slugs internos? **Originales / Canónicos**
2. **¿Una ancla por facción con elección** (recomendado: 7 legendarios vitales) **o todos los legendarios de `mejoras`** (hasta ~20)? **Una / Todos**
3. **¿La regla de rechazo con tope 5, +1 IV al recluta y +10 % al jefe** (recomendado) **o +10 % acumulable al recluta**? **Jefe / Recluta**
4. **¿Aceptas un `nivelExpediente` por capítulo** (12, 22, 40, 50, 62, 75, 88, 100) **independiente del nivel del gimnasio** de TASK-043? **Sí / No**
5. **¿Yell como vertical slice del MVP**, sin sistema de anclas? **Sí / No**
6. **¿Las facciones viven dentro de `/misiones`** (sin 7.º ítem de nav) **o quieres una ruta `/facciones` propia**? **Dentro / Propia**
7. **¿Skull y Yell se quedan sin legendario** (recomendado) **o les asignamos uno prestado** (Guzzlord/Tapu Bulu, Meltan/Melmetal)? **Sin / Con**
8. **¿Bases de 3 pisos con líder 3v3** (recomendado) **o 5 pisos 3v1** como una mazmorra más? **3 pisos / 5 pisos**

---

## Anexo — Borrador de ADR

**Título:** Facciones como expedientes de campaña con anclas legendarias acotadas.

**Estado:** Propuesto (pendiente de aprobación).

**Contexto:** el juego no tiene identidad narrativa; los 8 villanos de `mejoras` chocan con el motor 3v3, con los legendarios de gimnasio, con el hábitat 49 y con la economía sin moneda de TASK-043. No hay mazmorras configuradas ni retratos de NPCs.

**Decisión:** un módulo `src/Facciones` con catálogo en código, 3 tablas de estado, 8 expedientes de 3 actos insertados uno por capítulo, bases de 3 pisos con entidad propia, y una regla de ancla única por facción (captura garantizada, IV 10, shiny 1/512 fijado, rechazo máximo 5 con +1 IV y +10 % al jefe). Las facciones no son un quinto tipo de misión: son hitos de campaña y encargos dentro de la pestaña de campaña.

**Consecuencias:** se gana identidad y un clímax por capítulo sin tocar el motor ni la economía base; se acepta autoría de ~24 actos, 3 tablas nuevas y una capa de presentación en el modal de victoria. Se aplazan retratos, diálogos, moneda y segmentación del hábitat 49.