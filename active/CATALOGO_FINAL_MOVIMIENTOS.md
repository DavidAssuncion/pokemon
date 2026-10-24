# Catálogo final de movimientos

**Estado:** catálogo implementado. Incluye los 160 movimientos del cierre.

## Resumen

| Concepto | Cantidad |
|---|---:|
| Movimientos en PokeAPI | 937 |
| Movimientos canónicos implementados | 665 |
| Movimientos internos | 7 |
| Duplicado interno | 1 |
| Movimientos excluidos del catálogo | 272 |
| **Catálogo final canónico** | **665** |
| **Catálogo final total** | **673** |
| Cobertura PokeAPI | 71.0 % |
| Cobertura principales (901) | 73.8 % |

> De los 665 canónicos, 505 ya estaban y 160 entraron en el cierre. Incluyen 3 movimientos LGPE ya presentes (`splishy-splash`, `buzzy-buzz`, `sappy-seed`), que se conservan como legado. No se añaden más movimientos LGPE.

## Criterio

- **Dentro:** ya está en el catálogo actual. No se vuelve a revisar su inclusión.
- **Entra:** movimiento oficial de PokeAPI que reutiliza una mecánica ya presente o una extensión directa de ella (daño, multigolpe, prioridad fija, estados soportados, estadísticas, curación, clima, campo, pantallas, trampas, OHKO, carga/recarga, recoil, drenaje).
- **Fuera:** familia no estándar, mecánica descartada, efecto único que exigiría un sistema nuevo, o contenido que depende de objetos, cambio de Pokémon, copia/transformación, formas, Tera, Dynamax o Z.
- Los movimientos con efecto de **protección básica** (Protección, Detección, Aguante) sí entran; las variantes de protección con efectos extra se dejan fuera.
- Los cambios de tipo de las habilidades se resolvieron en la tanda anterior conservando el tipo original cuando hace más daño.

## Familias no estándar excluidas

| Familia | Cantidad | Tratamiento |
|---|---:|---|
| Z-Moves de PokeAPI | 53 | Fuera: requieren Cristal Z y la mecánica Z. |
| Max Moves de PokeAPI | 19 | Fuera: requieren Dynamax. |
| G-Max Moves de Showdown | 33 | Fuera: requieren Gigantamax; no están en PokeAPI. |
| Shadow Moves de PokeAPI | 18 | Fuera: usan el tipo Sombra y el sistema de XD/Colosseum. |
| Movimientos LGPE | 10 pendientes + 3 ya presentes | No se añaden; los 3 ya presentes se conservan como legado. |
| Hidden Power y variantes | 1 base + 16 variantes | Fuera: dependen de IV y de 16 tipos; no encajan sin sistema de IV. |
| CAP de Showdown | 3 | Fuera: contenido no oficial. |
| Futuro de Showdown | 1 | Fuera: Nihil Light, sin datos de mainline. |
| Duplicado de Showdown | 1 | `vise-grip` es alias de `vice-grip`; fuera. |

## Movimientos internos ya presentes

Estos 7 movimientos no son canónicos de PokeAPI y se conservan porque ya forman parte del juego:

`bendicion_revivir` (Bendición Revivir), `desafio` (Desafio), `foco_resplandor` (Foco resplandor), `invocar_esbirro` (Invocar Esbirro), `marca_del_cazador` (Marca del Cazador), `marioneta_inerte` (Marioneta Inerte), `vinculo_dano` (Vínculo de Daño)
.

Además, `envite_igneo` (Envite Ígneo) es un duplicado interno de `flare-blitz`; no aporta cobertura nueva pero se conserva para no romper datos existentes.

## Dentro ya: composición del catálogo actual

| Categoría | Movimientos |
|---|---:|
| Físico | 289 |
| Estado | 176 |
| Especial | 208 |
| **Total** | **673** |

La lista completa ya vive en `storage/data/moves.json`; aquí solo se documentan las decisiones nuevas.

## Entran en el cierre

Se añaden **160** movimientos, agrupados por función.

### Daño directo (58)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Anillo Ígneo | Fuego | Especial | 150 | Explosión de fuego. El atacante debe descansar el siguiente turno. |
| Arrumaco | Psíquico | Físico | 60 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Asalto Estelar | Lucha | Físico | 150 | El usuario agita violentamente su grueso puerro para atacar, pero el mareo que le provocan las sacudidas le impide moverse en el turno siguiente. |
| Ataque Aéreo | Volador | Físico | 140 | Ataca durante dos turnos y suele asestar un golpe crítico. También puede amedrentar al objetivo. |
| Ataque Furia | Normal | Físico | 15 | Cornea al objetivo de dos a cinco veces. |
| Azote Torrencial | Agua | Físico | 25 | El usuario, dominador absoluto del líquido elemento, golpea hasta tres veces con movimientos fluidos. Siempre asesta un golpe crítico. |
| Bola de Polen | Bicho | Especial | 90 | Ataca al oponente con una bola explosiva. Si esta alcanza a un aliado, le hará recuperar PS. |
| Bombardeo | Normal | Físico | 15 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Cabeza Sorpresa | Fuego | Especial | 150 | El usuario hace explotar su cabeza para atacar a todos los Pokémon que se hallan a su alrededor, aunque también se hiere a sí mismo. |
| Cabezazo | Normal | Físico | 130 | El usuario se prepara y sube su Defensa en el primer turno y en el segundo arremete con un cabezazo. |
| Cañón Dinamax | Dragón | Especial | 100 | El usuario ataca emitiendo un rayo desde su núcleo. El daño infligido se duplica si el objetivo ha alcanzado el estado Dinamax. |
| Clavo Cañón | Normal | Físico | 20 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Cuchilla Solar | Planta | Físico | 125 | El usuario dedica un turno a absorber energía lumínica y concentrarla en forma de cuchilla con la que ataca al rival en el siguiente turno. |
| Danza Despertar | Normal | Especial | 90 | Ataque que consiste en un baile muy enérgico. El tipo de este ataque se corresponde con el del Pokémon que lo ejecuta. |
| Daño Secreto | Normal | Físico | 70 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Desahogo | Siniestro | Físico | 75 | Ataca al rival presa de la rabia. Si el usuario ha sufrido una reducción de características en ese turno, la potencia del movimiento se duplica. |
| Destructor | Normal | Físico | 40 | Golpea con las patas o la cola. |
| Doble Bofetón | Normal | Físico | 15 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Doble Golpe | Normal | Físico | 35 | Golpea al objetivo dos veces seguidas con la cola u otras partes de su cuerpo. |
| Día de Pago | Normal | Físico | 40 | Arroja monedas al objetivo y las recupera al final del combate. |
| Embate Supremo | Acero | Físico | 100 | El usuario se convierte en un escudo gigante para golpear al objetivo. Si este ha alcanzado el estado Dinamax, se duplica el daño infligido. |
| Espabila | Lucha | Físico | 70 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Espada Santa | Lucha | Físico | 90 | El usuario ataca con una espada, ignorando cualquier cambio en las características del objetivo. |
| Estímulo | Normal | Físico | 70 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Ferropuño Doble | Acero | Físico | 60 | Usando la tuerca del pecho como eje, gira sobre sí mismo y golpea con los brazos dos veces seguidas. Puede amedrentar al rival. |
| Gigaimpacto | Normal | Físico | 150 | El usuario carga contra el objetivo con toda la fuerza que tiene y descansa durante el siguiente turno. |
| Giro Vil | Siniestro | Físico | 60 | Hace pivotar su cuerpo para causar daño a su alrededor. |
| Golpes Furia | Normal | Físico | 18 | Araña rápidamente de dos a cinco veces. |
| Guardia Baja | Normal | Físico | 70 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Géiser Fotónico | Psíquico | Especial | 100 | El usuario ataca con una gran columna de luz. Compara sus valores de Ataque y Ataque Especial para infligir daño con el más alto de los dos. |
| Hidrocañón | Agua | Especial | 150 | Disparo de agua. El atacante debe descansar el siguiente turno. |
| Hiperrayo | Normal | Especial | 150 | Es eficaz, pero el atacante deberá descansar en el siguiente turno. |
| Lariat Oscuro | Siniestro | Físico | 85 | Gira sobre sí mismo y golpea al oponente con ambos brazos. Ignora los cambios en las características del objetivo. |
| Láser Prisma | Psíquico | Especial | 160 | El usuario utiliza un prisma para emitir un rayo de gran potencia, pero no puede moverse en el turno siguiente. |
| Magnitud | Tierra | Físico | — | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Martillo Dragón | Dragón | Físico | 90 | Usa el cuerpo como un martillo para abalanzarse sobre su rival y causarle daño. |
| Megapatada | Normal | Físico | 120 | Patada de extrema fuerza. |
| Meteoimpacto | Acero | Físico | 100 | Ataca al objetivo con la potencia de un meteoro, ignorando su habilidad. |
| Núcleo Castigo | Dragón | Especial | 100 | Inflige daño al rival, y si este ya ha hecho uso de algún movimiento, pierde su habilidad. |
| Pataleta | Tierra | Físico | 75 | Usa la frustración como revulsivo para atacar. La potencia de Pataleta se duplica si el usuario ha fallado el último movimiento usado. |
| Pico Cañón | Volador | Físico | 100 | Primero aumenta la temperatura de su pico y luego ejecuta un ataque. Quema al rival si este le propina un ataque físico mientras está calentando el pico. |
| Planta Feroz | Planta | Especial | 150 | Golpea con una enorme planta. Quien lo usa no puede moverse en el siguiente turno. |
| Plumerazo | Normal | Físico | 25 | Golpea con la cola de dos a cinco veces seguidas. |
| Presente | Normal | Físico | — | Quien lo usa ataca al objetivo dándole un regalo con una bomba trampa. Sin embargo, a veces restaura sus PS. |
| Punzada Rama | Planta | Físico | 40 | Ataca pinchando al objetivo con una rama afilada. |
| Puño Cometa | Normal | Físico | 18 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Rayo Infinito | Dragón | Especial | 160 | Este es el mayor ataque de Eternatus una vez adquirida su forma original. No puede moverse en el turno siguiente. |
| Rayo Meteórico | Roca | Especial | 120 | El usuario dedica el primer turno a aumentar su Ataque Especial acumulando energía cósmica y lanza su ofensiva contra el objetivo en el segundo. |
| Rayo Solar | Planta | Especial | 120 | El usuario absorbe luz en el primer turno y en el segundo lanza un potente rayo de energía. |
| Rayo Umbrío | Fantasma | Especial | 100 | Ataca con un rayo misterioso que ignora la habilidad del objetivo. |
| Represalia | Normal | Físico | 70 | Venga a los amigos caídos. Si en el turno anterior han derrotado a alguno, la potencia del ataque aumentará. |
| Rueda Doble | Acero | Físico | 50 | Rota dos engranajes de hierro sobre el objetivo. Golpea dos veces. |
| Sincrorruido | Psíquico | Especial | 120 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Tajo Supremo | Acero | Físico | 100 | El usuario se convierte en una espada gigante para rebanar al objetivo. Si este ha alcanzado el estado Dinamax, se duplica el daño infligido. |
| Viento Cortante | Normal | Especial | 80 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Voto Agua | Agua | Especial | 80 | Ataca con columnas de agua. Combinado con Voto Fuego, crea un arcoíris y aumenta su potencia. |
| Voto Fuego | Fuego | Especial | 80 | Ataca con columnas de fuego. Combinado con Voto Planta, crea un mar de llamas y aumenta su potencia. |
| Voto Planta | Planta | Especial | 80 | Ataca con columnas de hojas. Combinado con Voto Agua, crea un pantano y aumenta su potencia. |

### Daño + estado (10)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Ataque Fulgor | Eléctrico | Físico | 130 | Ataca envolviéndose de una gran carga eléctrica y embistiendo al objetivo con ella. Puede paralizar. |
| Canto Arcaico | Normal | Especial | 75 | Ataca conmoviendo a los rivales de alrededor con un antiguo canto. Puede dormirlos. |
| Electrocañón | Eléctrico | Especial | 120 | Dispara una descarga eléctrica que causa daño y parálisis. |
| Llama Gélida | Hielo | Especial | 140 | Ataca al objetivo en el segundo turno rodeándolo de un aire gélido. Puede causar quemaduras. |
| Moluscañón | Veneno | Especial | 90 | El usuario lanza un ataque físico o especial en función de cuál inflija más daño. Puede envenenar al objetivo. |
| Onda Tóxica | Veneno | Especial | 95 | Una onda tóxica que daña a los Pokémon de alrededor. Puede envenenar. |
| Onda Ígnea | Fuego | Especial | 95 | Provoca un viento abrasador que puede quemar al objetivo. |
| Rayo Gélido | Hielo | Físico | 140 | El usuario carga un bloque de hielo con electricidad en el primer turno y ataca con él en el segundo. Puede paralizar. |
| Rayo Señal | Bicho | Especial | 75 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Triataque | Normal | Especial | 80 | Ataque triple que puede paralizar, quemar o congelar al objetivo. |

### Daño + baja estadística (7)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Batería Asalto | Planta | Físico | 80 | El usuario controla un tocón mediante la percusión y al atacar reduce la Velocidad del objetivo. |
| Concha Filo | Agua | Físico | 75 | Una afilada vieira ataca al objetivo. También puede hacer disminuir su Defensa. |
| Golpe Rastrero | Bicho | Físico | 70 | Ataca al objetivo por la espalda de forma subrepticia y además reduce su Ataque Especial. |
| Mundo Gélido | Hielo | Especial | 65 | Ataque con aire helado que baja la Velocidad del objetivo. |
| Patada Tropical | Planta | Físico | 70 | Lanza una patada con la fuerza del trópico que golpea al rival y reduce su Ataque. |
| Plancha | Bicho | Físico | 80 | Ataca al oponente abalanzándose sobre él con todas sus fuerzas y reduce su Ataque. |
| Restricción | Normal | Físico | 10 | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |

### Daño + sube estadística (2)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Danza Llama | Fuego | Especial | 80 | Envuelve en llamas y daña al objetivo. Puede aumentar el Ataque Especial de quien lo usa. |
| Rueda Aural | Eléctrico | Físico | 110 | La energía que acumula en las mejillas le sirve para atacar y aumentar su Velocidad. Este movimiento cambia de tipo según la forma que adopte Morpeko. |

### Cambios de estadísticas (21)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Aligerar | Acero | Estado | — | El usuario se desprende de partes prescindibles de su cuerpo para hacerse más ligero y aumentar mucho su Velocidad. |
| Atiborramiento | Normal | Estado | — | El usuario ingiere la baya que lleva equipada para aumentar mucho su Defensa. |
| Aullido | Normal | Estado | — | Aullido que sube el ánimo y aumenta el Ataque del equipo. |
| Bastión Final | Lucha | Estado | — | El usuario aumenta todas sus características, pero ya no puede huir ni ser cambiado por otro. |
| Camaradería | Normal | Estado | — | Se hace amigo de su oponente y consigue que a este se le quiten las ganas de combatir. Además, reduce su Ataque. |
| Cambio de Marcha | Acero | Estado | — | Al hacer girar los engranajes, el usuario mejora su Ataque y aumenta mucho su Velocidad. |
| Cosquillas | Normal | Estado | — | Hace reír al objetivo para bajar su Ataque y Defensa. |
| Destello | Normal | Estado | — | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Disparo Demora | Bicho | Estado | — | Lanza seda a los rivales y reduce mucho su Velocidad. |
| Dulce Aroma | Normal | Estado | — | Un dulce aroma engatusa al objetivo, por lo que se reduce mucho su Evasión. |
| Esporagodón | Planta | Estado | — | Adhiere esporas a los rivales para reducir mucho su Velocidad. |
| Estruendo Escama | Dragón | Estado | — | Utiliza parte de los PS propios para subir sus características. |
| Geocontrol | Hada | Estado | — | Concentra energía durante el primer turno, de forma que su Velocidad, Ataque Especial y Defensa Especial aumenten mucho en el segundo. |
| Kinético | Psíquico | Estado | — | Dobla una cuchara para distraer al objetivo y bajar su nivel de Precisión. |
| Llanto Falso | Siniestro | Estado | — | Lágrimas de cocodrilo que bajan mucho la Defensa Especial del objetivo. |
| Motivación | Lucha | Estado | — | El usuario imparte indicaciones precisas a sus aliados, que ven aumentados su Ataque y su Defensa. |
| Niebla Aromática | Hada | Estado | — | Consigue aumentar la Defensa Especial de un Pokémon de su equipo con una fragancia misteriosa. |
| Ojos Llorosos | Normal | Estado | — | Mira al objetivo con ojos llorosos para hacerle perder su espíritu combativo y reduce su Ataque y Ataque Especial. |
| Piñón Auxiliar | Acero | Estado | — | Cambia de marcha y logra aumentar el Ataque y el Ataque Especial de los Pokémon aliados que cuenten con las habilidades Más y Menos. |
| Rizo Algodón | Planta | Estado | — | Cubre al Pokémon con una madeja protectora. Aumenta muchísimo la Defensa. |
| Rugido de Guerra | Normal | Estado | — | Intimida a su oponente con un rugido de guerra, lo que hace que disminuyan tanto su Ataque como su Ataque Especial. |

### Curación (6)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Batido | Normal | Estado | — | Restaura la mitad de los PS máximos del usuario. |
| Cura Floral | Hada | Estado | — | Restaura la mitad de los PS máximos del objetivo. Es más efectivo cuando se usa en conjunción con Campo de Hierba. |
| Gota Vital | Agua | Estado | — | Vierte un agua misteriosa y balsámica que restaura tanto sus propios PS como los de aquellos aliados presentes en el terreno de combate. |
| Ovocuración | Normal | Estado | — | Restaura la mitad de los PS máximos del usuario. |
| Pulso Cura | Psíquico | Estado | — | Una onda curativa restaura la mitad de los PS máximos del objetivo. |
| Relajo | Normal | Estado | — | El usuario se relaja y restaura la mitad de sus PS máximos. |

### Clima/campo (5)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Danza Lluvia | Agua | Estado | — | Genera una fuerte lluvia que refuerza los movimientos de tipo Agua durante cinco turnos y debilita los de tipo Fuego. |
| Día Soleado | Fuego | Estado | — | Hace que se intensifique el efecto del sol durante cinco turnos, lo que potencia los movimientos de tipo Fuego y debilita los de tipo Agua. |
| Granizo | Hielo | Estado | — | Tormenta de granizo que dura cinco turnos. Hiere a todos los Pokémon excepto a los de tipo Hielo. |
| Hidrochorro | Agua | Estado | — | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Tormenta de Arena | Roca | Estado | — | Tormenta de arena que dura cinco turnos y hiere a todos, excepto a los de tipo Roca, Tierra y Acero, y aumenta la Defensa Especial de los de tipo Roca. |

### Protección de equipo (3)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Conjuro | Normal | Estado | — | Movimiento obsoleto o sin descripción oficial; se conserva por compatibilidad. |
| Neblina | Hielo | Estado | — | Rodea de fina niebla al usuario y protege las características de su equipo durante cinco turnos. |
| Velo Sagrado | Normal | Estado | — | Un escudo que protege de problemas de estado, como el sueño o la parálisis, durante cinco turnos. |

### Utilidad (15)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Absorbefuerza | Planta | Estado | — | Restaura una cantidad de PS equivalente al valor de Ataque del rival, que además verá reducida esta característica. |
| Acua Aro | Agua | Estado | — | Un manto de agua cubre al Pokémon que lo usa. Recupera algunos PS en cada turno. |
| Acupresión | Normal | Estado | — | Aplica presión en puntos clave del cuerpo para potenciar mucho una de sus características. |
| Aguante | Normal | Estado | — | Resiste cualquier ataque y deja al menos 1 PS. Puede fallar si se usa repetidamente. |
| Bomba Caramelo | Planta | Especial | 60 | Sin descripción oficial en PokeAPI. |
| Cura Selvática | Planta | Estado | — | Al entrar en plena armonía con la selva, el usuario cura problemas de estado y restaura PS no solo de sí mismo, sino también de los aliados presentes en el terreno. |
| Danza Lunar | Psíquico | Estado | — | El usuario se debilita, pero el Pokémon que lo sustituye recupera su estado, los PS y los PP. |
| Deseo Cura | Psíquico | Estado | — | El Pokémon cae debilitado, pero su sustituto recupera su estado y los PS. |
| Detección | Lucha | Estado | — | Frena todos los ataques, pero puede fallar si se usa repetidamente. |
| Levitón | Eléctrico | Estado | — | Levita gracias a un campo magnético generado por electricidad durante cinco turnos. |
| Protección | Normal | Estado | — | Frena todos los ataques, pero puede fallar si se usa repetidamente. |
| Purificación | Veneno | Estado | — | Cura los problemas de estado del Pokémon rival y a cambio recupera PS propios. |
| Reversión | Siniestro | Estado | — | Invierte por completo los cambios en las características del objetivo. |
| Salpicadura | Normal | Estado | — | No tiene ningún efecto. Solo salpica. |
| Tambor | Normal | Estado | — | Reduce la mitad de los PS máximos para mejorar al máximo el Ataque. |

### OHKO (3)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Fisura | Tierra | Físico | — | Abre una grieta en el suelo y mete al objetivo en ella. Fulmina en un golpe. |
| Guillotina | Normal | Físico | — | Ataque cortante que debilita al oponente de un golpe si acierta. |
| Perforador | Normal | Físico | — | Ataque con un cuerno giratorio que fulmina de un solo golpe al objetivo si lo alcanza. |

### Cambio + confusión (1)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Alquitranazo | Roca | Estado | — | Cubre al objetivo de un alquitrán pegajoso que reduce su Velocidad y lo vuelve débil contra el fuego. |

### Nuevas (Gen 9) (29)

| Movimiento | Tipo | Cat. | Poder | Efecto |
|---|---|---|---:|---|
| Arremetida | Tierra | Físico | 120 | Sin descripción oficial en PokeAPI. |
| Bramido Dragón | Dragón | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Brinco | Bicho | Físico | 50 | Sin descripción oficial en PokeAPI. |
| Bálsamo Osado | Psíquico | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Cañón Armadura | Fuego | Especial | 120 | Sin descripción oficial en PokeAPI. |
| Clorofiláser | Planta | Especial | 150 | Sin descripción oficial en PokeAPI. |
| Cólera Ardiente | Fuego | Físico | 75 | Sin descripción oficial en PokeAPI. |
| Danza Triunfal | Lucha | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Deslome | Normal | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Electormenta | Eléctrico | Especial | 100 | Sin descripción oficial en PokeAPI. |
| Electrorrayo | Eléctrico | Especial | 130 | Sin descripción oficial en PokeAPI. |
| Extracto Picante | Planta | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Fiebre Dorada | Acero | Especial | 120 | Sin descripción oficial en PokeAPI. |
| Garra Nociva | Veneno | Físico | 80 | Sin descripción oficial en PokeAPI. |
| Luna Roja | Normal | Especial | 140 | Sin descripción oficial en PokeAPI. |
| Láser Doble | Psíquico | Especial | 40 | Sin descripción oficial en PokeAPI. |
| Ominochoque | Siniestro | Físico | 80 | Sin descripción oficial en PokeAPI. |
| Oído Cocina | Dragón | Físico | 80 | Sin descripción oficial en PokeAPI. |
| Paisaje Nevado | Hielo | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Pirochoque | Fuego | Físico | 80 | Sin descripción oficial en PokeAPI. |
| Plancha Voltaica | Eléctrico | Físico | 100 | Sin descripción oficial en PokeAPI. |
| Plegaria Lunar | Psíquico | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Plegaria Vital | Normal | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Prensa Metálica | Acero | Físico | 0 | Sin descripción oficial en PokeAPI. |
| Pugnachoque | Lucha | Físico | 100 | Sin descripción oficial en PokeAPI. |
| Retracción | Acero | Estado | 0 | Sin descripción oficial en PokeAPI. |
| Tajo Acuático | Agua | Físico | 70 | Sin descripción oficial en PokeAPI. |
| Triple Flecha | Lucha | Físico | 90 | Sin descripción oficial en PokeAPI. |
| Truco Floral | Planta | Físico | 70 | Sin descripción oficial en PokeAPI. |

## Se excluyen

Quedan fuera **272** movimientos. Se agrupan por motivo; no se repiten los ya incluidos.

### No estándar / variante (101)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Agujero Negro Aniquilador | Siniestro | Físico | No estándar / variante |
| Agujero Negro Aniquilador | Siniestro | Especial | No estándar / variante |
| Aluvión de Flechas Sombrías | Fantasma | Físico | No estándar / variante |
| Aplastamiento Gigalítico | Roca | Físico | No estándar / variante |
| Aplastamiento Gigalítico | Roca | Especial | No estándar / variante |
| Arrojo Intempestivo | Normal | Físico | No estándar / variante |
| Arrumaco Sideral | Hada | Físico | No estándar / variante |
| Arrumaco Sideral | Hada | Especial | No estándar / variante |
| Barrena Telúrica | Tierra | Físico | No estándar / variante |
| Barrena Telúrica | Tierra | Especial | No estándar / variante |
| Bruma Oscura | Sombra | Estado | No estándar / variante |
| Brío Oscuro | Sombra | Físico | No estándar / variante |
| Carga Oscura | Sombra | Físico | No estándar / variante |
| Carrera Arrolladora | Normal | Físico | No estándar / variante |
| Carrera Arrolladora | Normal | Especial | No estándar / variante |
| Cielo Oscuro | Sombra | Estado | No estándar / variante |
| Constelación Robaalmas | Fantasma | Físico | No estándar / variante |
| Crioaliento Despiadado | Hielo | Físico | No estándar / variante |
| Crioaliento Despiadado | Hielo | Especial | No estándar / variante |
| Cólera del Guardián | Hada | Especial | No estándar / variante |
| Deflagración Lunar | Fantasma | Especial | No estándar / variante |
| Diluvio Corrosivo | Veneno | Físico | No estándar / variante |
| Diluvio Corrosivo | Veneno | Especial | No estándar / variante |
| Disruptor Psíquico | Psíquico | Físico | No estándar / variante |
| Disruptor Psíquico | Psíquico | Especial | No estándar / variante |
| Dracoaliento Devastador | Dragón | Físico | No estándar / variante |
| Dracoaliento Devastador | Dragón | Especial | No estándar / variante |
| Eevimpacto | Normal | Físico | No estándar / variante |
| Embestida Solar | Acero | Físico | No estándar / variante |
| Espeaura | Psíquico | Especial | No estándar / variante |
| Estruendo Implacable | Dragón | Especial | No estándar / variante |
| Fin Oscuro | Sombra | Físico | No estándar / variante |
| Flarembestida | Fuego | Físico | No estándar / variante |
| Fotodestrucción Apocalíptica | Psíquico | Especial | No estándar / variante |
| Fuego Oscuro | Sombra | Especial | No estándar / variante |
| Gigarrayo Fulminante | Eléctrico | Especial | No estándar / variante |
| Gigavoltio Destructor | Eléctrico | Físico | No estándar / variante |
| Gigavoltio Destructor | Eléctrico | Especial | No estándar / variante |
| Glaceoprisma | Hielo | Especial | No estándar / variante |
| Guadaña Sedosa | Bicho | Físico | No estándar / variante |
| Guadaña Sedosa | Bicho | Especial | No estándar / variante |
| Hecatombe Pírica | Fuego | Físico | No estándar / variante |
| Hecatombe Pírica | Fuego | Especial | No estándar / variante |
| Hidrovórtice Abisal | Agua | Físico | No estándar / variante |
| Hidrovórtice Abisal | Agua | Especial | No estándar / variante |
| Hielo Oscuro | Sombra | Especial | No estándar / variante |
| Hiperplancha Oscura | Siniestro | Físico | No estándar / variante |
| Hélice Trepanadora | Acero | Físico | No estándar / variante |
| Hélice Trepanadora | Acero | Especial | No estándar / variante |
| Maxiataque | Normal | Físico | No estándar / variante |
| Maxibarrera | Normal | Estado | No estándar / variante |
| Maxichorro | Agua | Físico | No estándar / variante |
| Maxiciclón | Volador | Físico | No estándar / variante |
| Maxidraco | Dragón | Físico | No estándar / variante |
| Maxiespectro | Fantasma | Físico | No estándar / variante |
| Maxiestela | Hada | Físico | No estándar / variante |
| Maxiflora | Planta | Físico | No estándar / variante |
| Maxignición | Fuego | Físico | No estándar / variante |
| Maxihelada | Hielo | Físico | No estándar / variante |
| Maxilito | Roca | Físico | No estándar / variante |
| Maximetal | Acero | Físico | No estándar / variante |
| Maxinsecto | Bicho | Físico | No estándar / variante |
| Maxionda | Psíquico | Físico | No estándar / variante |
| Maxipuño | Lucha | Físico | No estándar / variante |
| Maxisombra | Siniestro | Físico | No estándar / variante |
| Maxitemblor | Tierra | Físico | No estándar / variante |
| Maxitormenta | Eléctrico | Físico | No estándar / variante |
| Maxiácido | Veneno | Físico | No estándar / variante |
| Megatón Floral | Planta | Físico | No estándar / variante |
| Megatón Floral | Planta | Especial | No estándar / variante |
| Miedo Oscuro | Sombra | Estado | No estándar / variante |
| Mitad Oscura | Sombra | Especial | No estándar / variante |
| Muda Oscura | Sombra | Estado | No estándar / variante |
| Novena Potencia | Normal | Estado | No estándar / variante |
| Ocaso Oscuro | Sombra | Estado | No estándar / variante |
| Onda Oscura | Sombra | Especial | No estándar / variante |
| Picado Supersónico | Volador | Físico | No estándar / variante |
| Picado Supersónico | Volador | Especial | No estándar / variante |
| Pikapicado | Volador | Físico | No estándar / variante |
| Pikatormenta | Eléctrico | Especial | No estándar / variante |
| Pikaturbo | Eléctrico | Físico | No estándar / variante |
| Pikavoltio Letal | Eléctrico | Físico | No estándar / variante |
| Poder Oculto | Normal | Especial | No estándar / variante |
| Presa Espectral | Fantasma | Físico | No estándar / variante |
| Presa Espectral | Fantasma | Especial | No estándar / variante |
| Rabia Oscura | Sombra | Especial | No estándar / variante |
| Rayo Oscuro | Sombra | Especial | No estándar / variante |
| Ráfaga Demoledora | Lucha | Físico | No estándar / variante |
| Ráfaga Demoledora | Lucha | Especial | No estándar / variante |
| Sinfonía de la Diva Marina | Agua | Especial | No estándar / variante |
| Somanta Amistosa | Hada | Físico | No estándar / variante |
| Soplo Oscuro | Sombra | Físico | No estándar / variante |
| Supernova Original | Psíquico | Especial | No estándar / variante |
| Surfeo Galvánico | Eléctrico | Especial | No estándar / variante |
| Sylveotornado | Hada | Especial | No estándar / variante |
| Tempestad Rocosa | Roca | Físico | No estándar / variante |
| Tifón Oscuro | Sombra | Especial | No estándar / variante |
| Traba Oscura | Sombra | Estado | No estándar / variante |
| Umbreozona | Siniestro | Especial | No estándar / variante |
| Vapodrenaje | Agua | Especial | No estándar / variante |
| Vigor Oscuro | Sombra | Físico | No estándar / variante |

### Cambio de Pokémon (pivot) (8)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Autotomía | Normal | Estado | Cambio de Pokémon (pivot) |
| Fría Acogida | Hielo | Estado | Cambio de Pokémon (pivot) |
| Ida y Vuelta | Bicho | Físico | Cambio de Pokémon (pivot) |
| Relevo | Normal | Estado | Cambio de Pokémon (pivot) |
| Teletransporte | Psíquico | Estado | Cambio de Pokémon (pivot) |
| Viraje | Agua | Físico | Cambio de Pokémon (pivot) |
| Voltiocambio | Eléctrico | Especial | Cambio de Pokémon (pivot) |
| Última Palabra | Siniestro | Estado | Cambio de Pokémon (pivot) |

### Cambio forzado (4)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Cola Dragón | Dragón | Físico | Cambio forzado |
| Llave Giro | Lucha | Físico | Cambio forzado |
| Remolino | Normal | Estado | Cambio forzado |
| Rugido | Normal | Estado | Cambio forzado |

### Prioridad condicional (6)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Escaramuza | Bicho | Físico | Prioridad condicional |
| Fitoimpulso | Planta | Físico | Prioridad condicional |
| Golpe Bajo | Siniestro | Físico | Prioridad condicional |
| Palma Rauda | Lucha | Físico | Prioridad condicional |
| Relámpago Súbito | Eléctrico | Especial | Prioridad condicional |
| Sorpresa | Normal | Físico | Prioridad condicional |

### Contraataque (5)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Contraataque | Lucha | Físico | Contraataque |
| Manto Espejo | Psíquico | Especial | Contraataque |
| Mismo Destino | Fantasma | Estado | Contraataque |
| Resarcimiento | Siniestro | Físico | Contraataque |
| Venganza | Normal | Físico | Contraataque |

### Semiinvulnerabilidad (6)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Bote | Volador | Físico | Semiinvulnerabilidad |
| Buceo | Agua | Físico | Semiinvulnerabilidad |
| Caída Libre | Volador | Físico | Semiinvulnerabilidad |
| Excavar | Tierra | Físico | Semiinvulnerabilidad |
| Golpe Fantasma | Fantasma | Físico | Semiinvulnerabilidad |
| Vuelo | Volador | Físico | Semiinvulnerabilidad |

### Limpieza/creación de campo (8)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Cambio de Cancha | Normal | Estado | Limpieza/creación de campo |
| Despejar | Volador | Estado | Limpieza/creación de campo |
| Giro Mortífero | Veneno | Físico | Limpieza/creación de campo |
| Giro Rápido | Normal | Físico | Limpieza/creación de campo |
| Hachazo Pétreo | Roca | Físico | Limpieza/creación de campo |
| Limpieza General | Normal | Estado | Limpieza/creación de campo |
| Red Viscosa | Bicho | Estado | Limpieza/creación de campo |
| Tajo Metralla | Siniestro | Físico | Limpieza/creación de campo |

### Objetos (14)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Antojo | Normal | Físico | Objetos |
| Calcinación | Fuego | Especial | Objetos |
| Don Natural | Normal | Físico | Objetos |
| Eructo | Veneno | Especial | Objetos |
| Gas Corrosivo | Veneno | Estado | Objetos |
| Hora del Té | Normal | Estado | Objetos |
| Lanzamiento | Siniestro | Físico | Objetos |
| Multiataque | Normal | Físico | Objetos |
| Ofrenda | Normal | Estado | Objetos |
| Picadura | Bicho | Físico | Objetos |
| Picoteo | Volador | Físico | Objetos |
| Poltergeist | Fantasma | Físico | Objetos |
| Tecno Shock | Normal | Especial | Objetos |
| Trapicheo | Siniestro | Estado | Objetos |

### Copia/transformación (21)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Abatidoras | Planta | Estado | Copia/transformación |
| Adaptación | Normal | Estado | Copia/transformación |
| Ayuda | Normal | Estado | Copia/transformación |
| Cambiaalmas | Psíquico | Estado | Copia/transformación |
| Clonatipo | Normal | Estado | Copia/transformación |
| Copión | Normal | Estado | Copia/transformación |
| Danza Amiga | Normal | Estado | Copia/transformación |
| Decalcomanía | Normal | Estado | Copia/transformación |
| Espejo | Volador | Estado | Copia/transformación |
| Esquema | Normal | Estado | Copia/transformación |
| Isofuerza | Psíquico | Estado | Copia/transformación |
| Isoguardia | Psíquico | Estado | Copia/transformación |
| Mandato | Psíquico | Estado | Copia/transformación |
| Metrónomo | Normal | Estado | Copia/transformación |
| Mimético | Normal | Estado | Copia/transformación |
| Onda Simple | Normal | Estado | Copia/transformación |
| Psicocambio | Psíquico | Estado | Copia/transformación |
| Sonámbulo | Normal | Estado | Copia/transformación |
| Transformación | Normal | Estado | Copia/transformación |
| Truco Fuerza | Psíquico | Estado | Copia/transformación |
| Yo Primero | Normal | Estado | Copia/transformación |

### Bloqueo de turnos (7)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Alboroto | Normal | Especial | Bloqueo de turnos |
| Danza Pétalo | Planta | Especial | Bloqueo de turnos |
| Enfado | Dragón | Físico | Bloqueo de turnos |
| Erupción de Ira | Fuego | Físico | Bloqueo de turnos |
| Furia | Normal | Físico | Bloqueo de turnos |
| Martillo Colosal | Acero | Físico | Bloqueo de turnos |
| Saña | Normal | Físico | Bloqueo de turnos |

### Estado no soportado (10)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Anticura | Psíquico | Estado | Estado no soportado |
| Arraigo | Planta | Estado | Estado no soportado |
| Atracción | Normal | Estado | Estado no soportado |
| Bostezo | Normal | Estado | Estado no soportado |
| Gran Ojo | Psíquico | Estado | Estado no soportado |
| Pesadilla | Fantasma | Estado | Estado no soportado |
| Profecía | Normal | Estado | Estado no soportado |
| Rastreo | Normal | Estado | Estado no soportado |
| Telequinesis | Psíquico | Estado | Estado no soportado |
| Tormento | Siniestro | Estado | Estado no soportado |

### Protección de equipo no soportada (4)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Anticipo | Lucha | Estado | Protección de equipo no soportada |
| Escudo Tatami | Lucha | Estado | Protección de equipo no soportada |
| Truco Defensa | Hada | Estado | Protección de equipo no soportada |
| Vasta Guardia | Roca | Estado | Protección de equipo no soportada |

### Campo especial no soportado (4)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Cerrojo Feérico | Hada | Estado | Campo especial no soportado |
| Cortina Plasma | Eléctrico | Estado | Campo especial no soportado |
| Gravedad | Psíquico | Estado | Campo especial no soportado |
| Zona Extraña | Psíquico | Estado | Campo especial no soportado |

### Cambio de estadísticas no soportado (1)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Seducción | Normal | Estado | Cambio de estadísticas no soportado |

### Mecánica no soportada (36)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Amago | Normal | Físico | Mecánica no soportada |
| As Oculto | Normal | Especial | Mecánica no soportada |
| Asalto Espadón | Dragón | Físico | Mecánica no soportada |
| Cambiapoder | Normal | Estado | Mecánica no soportada |
| Cerco Dimensión | Siniestro | Físico | Mecánica no soportada |
| Clemencia | Normal | Físico | Mecánica no soportada |
| Conjuro Funesto | Psíquico | Especial | Mecánica no soportada |
| Coraza Trampa | Fuego | Especial | Mecánica no soportada |
| Electropalmas | Eléctrico | Físico | Mecánica no soportada |
| Escupir | Normal | Especial | Mecánica no soportada |
| Falso Tortazo | Normal | Físico | Mecánica no soportada |
| Forcejeo | Normal | Físico | Mecánica no soportada |
| Frustración | Normal | Físico | Mecánica no soportada |
| Garrote Liana | Planta | Físico | Mecánica no soportada |
| Golpe Mordaza | Siniestro | Físico | Mecánica no soportada |
| Llama Final | Fuego | Especial | Mecánica no soportada |
| Llama Protectora | Fuego | Estado | Mecánica no soportada |
| Láser Veleidoso | Dragón | Especial | Mecánica no soportada |
| Mil Flechas | Tierra | Físico | Mecánica no soportada |
| Paso Dimensional | Psíquico | Especial | Mecánica no soportada |
| Patada Hacha | Lucha | Físico | Mecánica no soportada |
| Patada Salto | Lucha | Físico | Mecánica no soportada |
| Plancha Voladora | Lucha | Físico | Mecánica no soportada |
| Psicorruido | Psíquico | Especial | Mecánica no soportada |
| Puño Certero | Lucha | Físico | Mecánica no soportada |
| Puños Plasma | Eléctrico | Físico | Mecánica no soportada |
| Reserva | Normal | Estado | Mecánica no soportada |
| Retribución | Normal | Físico | Mecánica no soportada |
| Robasombra | Fantasma | Físico | Mecánica no soportada |
| Ronquido | Normal | Especial | Mecánica no soportada |
| Salazón | Roca | Físico | Mecánica no soportada |
| Telatrampa | Bicho | Estado | Mecánica no soportada |
| Teraclúster | Normal | Especial | Mecánica no soportada |
| Teraexplosión | Normal | Especial | Mecánica no soportada |
| Tragar | Normal | Estado | Mecánica no soportada |
| Última Baza | Normal | Físico | Mecánica no soportada |

### Efecto único no soportado (37)

| Movimiento | Tipo | Cat. | Motivo |
|---|---|---|---|
| Anulación | Normal | Estado | Efecto único no soportado |
| Barrera Espinosa | Planta | Estado | Efecto único no soportado |
| Bloqueo | Normal | Estado | Efecto único no soportado |
| Búnker | Veneno | Estado | Efecto único no soportado |
| Cambio de Banda | Psíquico | Estado | Efecto único no soportado |
| Capa Mágica | Psíquico | Estado | Efecto único no soportado |
| Cede Paso | Normal | Estado | Efecto único no soportado |
| Celebración | Normal | Estado | Efecto único no soportado |
| Condena Silvana | Planta | Estado | Efecto único no soportado |
| Conversión | Normal | Estado | Efecto único no soportado |
| Conversión 2 | Normal | Estado | Efecto único no soportado |
| Defensa Floral | Hada | Estado | Efecto único no soportado |
| Deseo | Normal | Estado | Efecto único no soportado |
| Electrificación | Eléctrico | Estado | Efecto único no soportado |
| Empapar | Agua | Estado | Efecto único no soportado |
| Escudo Real | Acero | Estado | Efecto único no soportado |
| Fijar Blanco | Normal | Estado | Efecto único no soportado |
| Foco | Normal | Estado | Efecto único no soportado |
| Halloween | Fantasma | Estado | Efecto único no soportado |
| Mal de Ojo | Normal | Estado | Efecto único no soportado |
| Manos Juntas | Normal | Estado | Efecto único no soportado |
| Obstrucción | Siniestro | Estado | Efecto único no soportado |
| Octopresa | Lucha | Estado | Efecto único no soportado |
| Otra Vez | Normal | Estado | Efecto único no soportado |
| Paga Extra | Normal | Estado | Efecto único no soportado |
| Polvo Explosivo | Bicho | Estado | Efecto único no soportado |
| Polvo Ira | Bicho | Estado | Efecto único no soportado |
| Polvo Mágico | Psíquico | Estado | Efecto único no soportado |
| Rabia | Fantasma | Estado | Efecto único no soportado |
| Refuerzo | Normal | Estado | Efecto único no soportado |
| Rencor | Fantasma | Estado | Efecto único no soportado |
| Robo | Siniestro | Estado | Efecto único no soportado |
| Sellar | Psíquico | Estado | Efecto único no soportado |
| Señuelo | Normal | Estado | Efecto único no soportado |
| Telaraña | Bicho | Estado | Efecto único no soportado |
| Telépata | Normal | Estado | Efecto único no soportado |
| Último Lugar | Siniestro | Estado | Efecto único no soportado |

## Notas de cierre

- **Protección básica:** entran Protección, Detección y Aguante. También entran Velo Sagrado, Neblina y Lucky Chant como protecciones de equipo. Las variantes de protección con daño o efectos extra (Escudo Real, Búnker, Obstrucción, etc.) quedan fuera.
- **Prioridad condicional:** Golpe Bajo, Sorpresa, Escaramuza, Golpe Relámpago, Hierba Trampa y Thunderclap quedan fuera.
- **Cambio tras ataque:** Ida y Vuelta, Voltiocambio, Flip Turn, Relevo, Teletransporte, Parting Shot, Shed Tail y Chilly Reception quedan fuera.
- **Contraataque:** Contraataque, Manto Espejo, Metal Burst, Comeuppance y Venganza quedan fuera.
- **Semiinvulnerabilidad:** Vuelo, Excavar, Buceo, Golpe Fantasma, Shadow Force, Caída Libre y Bote quedan fuera.
- **Objetos:** Lanzamiento, Don Natural, Bestow, Switcheroo, Poltergeist, Picoteo, Picadura, Calcinación, Antojo, Multiataque, Tecno Shock, Eructo y Tea Time quedan fuera.
- **Copia/transformación:** Transformación, Mimético, Espejo, Esquema, Yo Primero, Copycat, Asistencia, Metrónomo, Sonámbulo, Naturaleza, Instruct, etc. quedan fuera.
- **Campos:** se añaden clima (sol, lluvia, arena, granizo/nieve), agua/lodo y trampas; quedan fuera Gravedad, Wonder Room, Ion Deluge, Fairy Lock, Rapid Spin, Defog, Court Change, Tidy Up, Stone Axe y Ceaseless Edge.
- **Pikapicado** y **Golpe Mordaza** (`throat-chop`) quedan fuera por decisión previa.
- **Conjuro Funesto** queda fuera: elimina PP y el juego no usa PP.