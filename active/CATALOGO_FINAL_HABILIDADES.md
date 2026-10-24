# Catálogo final de habilidades

**Estado:** catálogo implementado. Incluye las 88 habilidades del cierre.

## Resumen

| Concepto | Cantidad |
|---|---:|
| Habilidades principales en PokeAPI | 314 |
| Habilidades canónicas ya cubiertas | 145 |
| — definidas en JSON | 113 |
| — heredadas homologables | 32 |
| Homebrew existentes | 53 |
| Entran en el cierre | 88 |
| Se excluyen | 81 |
| **Catálogo final canónico** | **233** |
| Cobertura principal | 74.2 % |
| **Catálogo final con homebrew** | **287** |

> Aviso: las **199 filas** actuales de la tabla no equivalen a 199 habilidades de PokeAPI. Son 113 definidas en JSON + 32 heredadas que coinciden con una habilidad canónica + 53 homebrew + 1 fila `(Vacio)`. La cobertura canónica real de partida es **145/314 = 46,2 %**, no 199/374.

## Criterio

- **Dentro:** ya existe y se conserva. Las 32 heredadas se homologan al nombre canónico de PokeAPI para evitar duplicados.
- **Entra:** habilidad principal de PokeAPI que se puede expresar con los efectos actuales o con una extensión directa (inmunidad, potenciador de tipo, stat, clima, contacto, entrada, stat al debilitar, etc.).
- **Fuera:** depende de objetos/bayas, cambio de Pokémon, copia/identidad, formas, prioridad, dobles/redirección, género, superclima, Tera o no tiene efecto de combate.

## Homologaciones heredadas (32)

| Nombre heredado | Habilidad canónica | Identificador |
|---|---|---|
| Acero templado | Acero Templado | `steelworker` |
| Antidoto | Antídoto | `poison-heal` |
| Armadura Batalla | Armadura Batalla | `battle-armor` |
| Bromista | Bromista | `prankster` |
| Cabeza roca | Cabeza Roca | `rock-head` |
| Caparazón | Caparazón | `shell-armor` |
| Clorofila | Clorofila | `chlorophyll` |
| Colector | Colector | `storm-drain` |
| Cuerpo llama | Cuerpo Llama | `flame-body` |
| Detonacion | Detonación | `aftermath` |
| Efecto Espora | Efecto Espora | `effect-spore` |
| Electricidad estatica | Electricidad Estática | `static` |
| Enjambre | Enjambre | `swarm` |
| Espesura | Espesura | `overgrow` |
| Flexibilidad | Flexibilidad | `limber` |
| Garra dura | Garra Dura | `tough-claws` |
| Intimidacion | Intimidación | `intimidate` |
| Llovizna | Llovizna | `drizzle` |
| Mal Sueño | Mal Sueño | `bad-dreams` |
| Mar Llamas | Mar Llamas | `blaze` |
| Muro Mágico | Muro Mágico | `magic-guard` |
| Pararrayos | Pararrayos | `lightning-rod` |
| Prediccion | Predicción | `forecast` |
| Presion | Presión | `pressure` |
| Punto toxico | Punto Tóxico | `poison-point` |
| Regeneracion | Regeneración | `regenerator` |
| Sequia | Sequía | `drought` |
| Torrente | Torrente | `torrent` |
| Veleta | Veleta | `moody` |
| Velo agua | Velo Agua | `water-veil` |
| Velo Arena | Velo Arena | `sand-veil` |
| impetu arena | Ímpetu Arena | `sand-rush` |

## Homebrew que se conservan (53)

No se cuentan como cobertura de PokeAPI. Se mantienen porque ya están asignados a especies y representan efectos propios del juego.

Absorción Agua, Absorción Electricidad, Absorción Fuego, Actualizacion software, Alma dragon, Baba Toxica, Bendicion, Campo Magnetico, Cañones, Comealmas, Crueldad, Cuadruple Ataque, Danza Lunar, Demoledor, Demoledor / Mandíbula Hidráulica, Depredador, Depredador / Depredador Prehistorico / Malvado, Depredador Marino, Depredador Prehistorico, Depredador de Arena, Divide dolor, Doble Ataque, Dormilon, Enjambre/Mar Llamas/Espesura, Enredaderas, Escamas, Espinas/piel seca, Espsura, Evasion, Flexibilidad/ Depredador Marino, Flor Aromática, Fotosíntesis, Granizo, Guardian de los mares, Hypnotizador, Ira, Ira Gyarados, Malvado, Mandíbula Hidráulica, Manto Helado, Meditacion, Pantallista, Predicion, Protector, Protector / Campo Magnetico, Refuerzos, Regeneracion/Sintesis/Bendicion, Sintesis, Sombra traviesa, Tormenta Arena, Triple Ataque, Veleta / Actualizacion software, espinas

## Entran en el cierre

### Estadísticas y crítico (21)

| Habilidad | Efecto |
|---|---|
| Autoestima | Al debilitar a un objetivo, su confianza se refuerza de tal manera que aumenta su Ataque. |
| Cálculo Final | Aumenta la potencia de su movimiento si es el último en atacar. |
| Descarga | Compara la Defensa y la Defensa Especial del rival para ver cuál es inferior y aumenta su propio Ataque o Ataque Especial según sea lo más eficaz. |
| Dicha | Aumenta la probabilidad de que los movimientos causen efectos secundarios. |
| Energía Pura | Duplica la potencia de sus ataques físicos gracias al yoga. |
| Ensañamiento | Hace que sus movimientos asesten siempre un golpe crítico si el rival está envenenado. |
| Escudo Recio | Aumenta su Defensa al entrar en combate. |
| Espada Indómita | Aumenta su Ataque al entrar en combate. |
| Flaqueza | Se debilita tanto cuando sus PS se ven reducidos a la mitad que su Ataque y su Ataque Especial bajan. |
| General Supremo | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Impasible | Cada vez que se amedrenta sube su Velocidad, debido a su voluntad inquebrantable. |
| Inicio Lento | Baja a la mitad su Ataque y su Velocidad durante cinco turnos. |
| Metal Liviano | Reduce a la mitad su peso. |
| Metal Pesado | Duplica su peso. |
| Oportunista | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Potencia | Duplica la potencia de sus ataques físicos. |
| Relincho Blanco | Al derrotar a un objetivo, emite un relincho gélido y aumenta su Ataque. |
| Relincho Negro | Al derrotar a un objetivo, emite un relincho aterrador y aumenta su Ataque Especial. |
| Tinovictoria | Sube su Precisión y la de sus aliados. |
| Tumbos | Sube su Evasión si está confuso. |
| Unísono | Sin descripción oficial en PokeAPI; efecto según Showdown. |

### Tipos y auras (13)

| Habilidad | Efecto |
|---|---|
| Audaz | Potencia los movimientos que también dañan al usuario. |
| Aura Feérica | Aumenta la potencia de todos los movimientos de tipo Hada. |
| Aura Oscura | Aumenta la potencia de todos los movimientos de tipo Siniestro. |
| Cambio Color | Adopta el tipo del último movimiento del que es blanco. |
| Cortante | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Cromolente | Potencia los movimientos que no son muy eficaces, que infligen ahora un daño normal. |
| Fuerza Cerebral | Potencia los ataques supereficaces. |
| Líbero | Cambia su tipo al del movimiento que va a usar. |
| Megadisparador | Aumenta la potencia de algunos movimientos de pulsos y auras. |
| Mimetismo | Cambia su tipo según el campo que haya en el terreno de combate. |
| Mutatipo | Cambia su tipo al del movimiento que va a usar. |
| Piel Dragontina | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Rompeaura | Invierte los efectos de las auras, por lo que baja la potencia de ciertos movimientos en vez de subirla. |

### Clima y entrada (10)

| Habilidad | Efecto |
|---|---|
| Carga Cuark | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Chorro Arena | Crea una tormenta de arena al entrar en combate. |
| Cola Surf | Duplica su Velocidad si hay un campo eléctrico en el terreno de combate. |
| Dinamo | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Energía Eólica | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Gélido | Recupera PS de forma gradual cuando hay tormentas de granizo. |
| Latido Oricalco | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Motor Hadrónico | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Paleosíntesis | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Piel Seca | Pierde PS si hace sol y los recupera si llueve o recibe un movimiento de tipo Agua. Los movimientos de tipo Fuego, por su parte, le hacen más daño de lo normal. |

### Contacto y represalia (10)

| Habilidad | Efecto |
|---|---|
| Capa Tóxica | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Cuerpo Maldito | Puede anular el movimiento usado en su contra. |
| Cuerpo Mortal | Si le alcanza un movimiento de contacto, se debilitará al cabo de 3 turnos, así como su agresor, a menos que abandonen el terreno de combate. |
| Hedor | Puede amedrentar al rival al atacarlo debido al mal olor que emana. |
| Lodo Líquido | Exuda una sustancia tóxica de intenso hedor que hiere a quienes intentan drenarle PS. |
| Pelusa | Al ser alcanzado por un ataque, suelta una pelusa de algodón que reduce la Velocidad de todos los demás Pokémon. |
| Punta Acero | Inflige daño al rival si este le golpea con un movimiento de contacto. |
| Revés | Al caer debilitado, inflige al rival un daño equivalente a los PS que le quedaran. |
| Salpicante | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Toque Tóxico | Puede envenenar al objetivo con solo tocarlo. |

### Defensa y protección (10)

| Habilidad | Efecto |
|---|---|
| Absorbe Agua | Si le alcanza un movimiento de tipo Agua, recupera PS en vez de sufrir daño. |
| Absorbe Electricidad | Si le alcanza un movimiento de tipo Eléctrico, recupera PS en vez de sufrir daño. |
| Absorbe Fuego | Si le alcanza algún movimiento de tipo Fuego, potencia sus propios movimientos de dicho tipo. |
| Coraza Reflejo | Refleja los efectos que reducen las características. |
| Guardia Metálica | Evita que bajen sus características a causa de movimientos o habilidades de otros Pokémon. |
| Humedad | Aumenta la humedad del entorno y evita que se puedan utilizar movimientos explosivos, tales como Autodestrucción. |
| Indefenso | Al quedar ambos expuestos, tanto sus movimientos como los del rival acertarán siempre. |
| Manto Frondoso | Aumenta su Defensa si hay un campo de hierba en el terreno de combate. |
| Polvo Escudo | El polvo de escamas que lo envuelve lo protege de los efectos secundarios de los ataques recibidos. |
| Robustez | Evita que el rival pueda debilitarlo de un solo golpe cuando tiene los PS al máximo. También evita los movimientos fulminantes. |

### Equipo y control (9)

| Habilidad | Efecto |
|---|---|
| Allanamiento | Ataca sorteando la barrera o el sustituto del rival. |
| Antibarrera | Anula los efectos de Pantalla de Luz, Reflejo y Velo Aurora tanto de rivales como de aliados al entrar en combate. |
| Gas Reactivo | Anula los efectos de las habilidades de los demás Pokémon presentes mientras esté en el terreno de combate. |
| Hospitalidad | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Medicina Extraña | Al entrar en combate, rezuma una substancia medicinal por la caracola que revierte los cambios en las características de los aliados. |
| Remoto | Puede usar cualquier movimiento sin entrar en contacto con el rival. |
| Rompemoldes | Las habilidades del objetivo no afectan a los movimientos que emplea. |
| Terravoltaje | Las habilidades del objetivo no afectan a los movimientos que emplea. |
| Turbollama | Las habilidades del objetivo no afectan a los movimientos que emplea. |

### Precisión y estados (9)

| Habilidad | Efecto |
|---|---|
| Cadena Tóxica | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Corrosión | Puede envenenar incluso a rivales de tipo Acero o Veneno. |
| Defensa Hoja | Evita los problemas de estado si hace sol. |
| Letargo Perenne | No despierta jamás de su profundo letargo e incluso ataca dormido. |
| Madrugar | Si se duerme, tardará la mitad de tiempo en despertarse. |
| Néctar Dulce | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Ojo Mental | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Sincronía | Contagia el envenenamiento, las quemaduras o la parálisis al Pokémon que le cause ese estado. |
| Títere Tóxico | Sin descripción oficial en PokeAPI; efecto según Showdown. |

### Ruina (4)

| Habilidad | Efecto |
|---|---|
| Abalorio Debacle | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Caldero Debacle | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Espada Debacle | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Tablilla Debacle | Sin descripción oficial en PokeAPI; efecto según Showdown. |

### Daño y multiataque (1)

| Habilidad | Efecto |
|---|---|
| Amor Filial | Une fuerzas con su cría y ataca dos veces. |

### Otras (1)

| Habilidad | Efecto |
|---|---|
| Ausente | Al ejecutar un movimiento, descansará en el turno siguiente. |

## Se excluyen

Quedan fuera **81** habilidades principales.

### Forma/cambio (18)

| Habilidad | Efecto / motivo |
|---|---|
| Agrupamiento | Cuando sus PS se ven reducidos a la mitad, las células se reagrupan y adopta su Forma Completa. |
| Banco | Forma bancos con sus congéneres cuando tiene muchos PS, lo cual le otorga más fuerza. Cuando le quedan pocos PS, el banco se dispersa. |
| Cambio Heroico | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Cambio Táctico | Adopta la Forma Filo al lanzar un ataque, o bien la Forma Escudo si usa el movimiento Escudo Real. |
| Cara de Hielo | Absorbe el daño de un ataque físico con el hielo de la cabeza, tras lo cual cambia de forma. El hielo se regenerará la próxima vez que gran… |
| Comandar | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Disfraz | Puede eludir un ataque valiéndose de la tela que le cubre el cuerpo una vez por combate. |
| Escudo Limitado | Rompe su coraza cuando sus PS se ven reducidos a la mitad y adopta una forma ofensiva. |
| Evocarrecuerdos | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Fuerte Afecto | Al derrotar a un rival, los vínculos con su Entrenador se refuerzan y se convierte en Greninja Ash. Su Shuriken de Agua también se ve poten… |
| Modo Daruma | Cambia de forma si sus PS se ven reducidos a la mitad. |
| Mutapetito | Alterna entre su Forma Saciada y Forma Voraz al final de cada turno. |
| Teracambio | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Teracaparazón | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Teraformación 0 | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Tragamisil | Tras usar Surf o Buceo, emerge con una presa en la boca. Al recibir daño, ataca escupiéndola al rival. |
| Unidad Ecuestre | El Pokémon tiene dos habilidades: Relincho Blanco de Glastrier y Nerviosismo de Calyrex. |
| Unidad Ecuestre | El Pokémon tiene dos habilidades: Relincho Negro de Spectrier y Nerviosismo de Calyrex. |

### Objetos/bayas (17)

| Habilidad | Efecto / motivo |
|---|---|
| Cacheo | Puede ver el objeto que lleva el rival al entrar en combate. |
| Carrillo | Recupera PS al comer cualquier baya. |
| Cosecha | Puede reutilizar varias veces una misma baya. |
| Gula | Cuando sus PS se ven reducidos a la mitad, engulle la baya que normalmente solo se comería cuando le quedasen pocos PS. |
| Hurto | Si el rival usa un movimiento de contacto al atacar, le roba el objeto. |
| Liviano | Sube su Velocidad si usa o pierde el objeto que lleva. |
| Maduración | Hace madurar las bayas, por lo que duplica sus efectos. |
| Monotema | Potencia su Ataque, pero solo puede usar el primer movimiento escogido. |
| Nerviosismo | Pone nervioso al rival y le impide usar bayas. |
| Prestidigitador | Roba el objeto del Pokémon al que alcance con un movimiento. |
| Recogebolas | Si no lleva equipado ningún objeto, recupera la Poké Ball del primer intento de captura fallido. |
| Recogemiel | Puede que encuentre Miel una vez concluido el combate. |
| Recogida | Puede recoger objetos que el rival haya usado, o bien otros que encuentre en plena aventura. |
| Rumia | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Simbiosis | Pasa su objeto a un aliado que ya haya utilizado el suyo. |
| Viscosidad | Los objetos se quedan pegados a su cuerpo, por lo que no pueden robárselos. |
| Zoquete | No puede usar objetos equipados. |

### Copia/identidad (9)

| Habilidad | Efecto / motivo |
|---|---|
| Alma Errante | Si le alcanza un movimiento de contacto, intercambia su habilidad con la del agresor. |
| Calco | Al entrar en combate copia la habilidad del rival. |
| Ilusión | Adopta el aspecto del último Pokémon del equipo al entrar en combate para desconcertar al rival. |
| Impostor | Se transforma en el Pokémon que tiene enfrente. |
| Momia | Contagia la habilidad Momia al rival que entre en contacto con él. |
| Olor Persistente | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Pareja de Baile | Puede copiar inmediatamente cualquier movimiento de baile que haya usado otro Pokémon presente en el combate. |
| Reacción Química | Reacciona copiando la habilidad de un aliado debilitado. |
| Receptor | Adquiere la habilidad de un aliado debilitado. |

### Prioridad/orden (9)

| Habilidad | Efecto / motivo |
|---|---|
| Alas Vendaval | Da prioridad a los movimientos de tipo Volador si sus PS están al máximo. |
| Cola Armadura | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Cuerpo Vívido | Desconcierta al rival y le impide utilizar movimientos con prioridad en su contra. |
| Mano Rápida | A veces, puede atacar el primero. |
| Megasolar | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Poder Fúngico | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| Primer Auxilio | Da prioridad a los movimientos que restauran PS. |
| Regia Presencia | Intimida al objetivo y le impide usar movimientos con prioridad. |
| Rezagado | Ejecuta su movimiento tras todos los demás. |

### Cambio/huida (8)

| Habilidad | Efecto / motivo |
|---|---|
| Cura Natural | Cura sus problemas de estado al cambiarse con otro Pokémon. |
| Fuga | Puede escaparse de todos los Pokémon salvajes. |
| Huida | Se asusta y abandona el terreno de combate cuando sus PS se ven reducidos a la mitad. |
| Imán | Su magnetismo atrae a los Pokémon de tipo Acero y les impide huir. |
| Retirada | Abandona el terreno de combate cuando sus PS se ven reducidos a la mitad para evitar males mayores. |
| Sombra Trampa | Impide que el enemigo huya o sea cambiado por otro. |
| Trampa Arena | Evita que el rival huya. |
| Vigilante | Si el objetivo de su ataque es sustituido por otro, duplica el daño que infligirá. |

### Dobles/redirección (4)

| Habilidad | Efecto / motivo |
|---|---|
| Acérrimo | Impide que el rival pueda atraer ataques hacia sí mismo por efecto de su habilidad o sus movimientos. |
| Hélice Caudal | Impide que el rival pueda atraer ataques hacia sí mismo por efecto de su habilidad o sus movimientos. |
| Menos | Potencia su Ataque Especial si un Pokémon aliado tiene la habilidad Más o Menos. |
| Más | Potencia su Ataque Especial si un Pokémon aliado tiene la habilidad Más o Menos. |

### Sin datos/futuro (3)

| Habilidad | Efecto / motivo |
|---|---|
| aura-guard | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| eelevate | Sin descripción oficial en PokeAPI; efecto según Showdown. |
| fire-mane | Sin descripción oficial en PokeAPI; efecto según Showdown. |

### Sin efecto de combate (3)

| Habilidad | Efecto / motivo |
|---|---|
| Alerta | Indica el movimiento más potente del rival al entrar en combate. |
| Anticipación | Prevé los movimientos peligrosos del rival. |
| Iluminación | Aumenta la probabilidad de encontrar Pokémon al iluminar el entorno. |

### Superclima (3)

| Habilidad | Efecto / motivo |
|---|---|
| Mar del Albor | Altera el clima para anular los ataques de tipo Fuego. |
| Ráfaga Delta | Altera el clima para anular las vulnerabilidades del tipo Volador. |
| Tierra del Ocaso | Altera el clima para anular los ataques de tipo Agua. |

### Género (2)

| Habilidad | Efecto / motivo |
|---|---|
| Gran Encanto | Puede causar enamoramiento al rival que lo toque. |
| Rivalidad | Si el objetivo es del mismo sexo, su competitividad le lleva a infligir más daño. Si es del sexo contrario, en cambio, el daño será menor. |

### Objetos/formas (2)

| Habilidad | Efecto / motivo |
|---|---|
| Multitipo | Cambia su tipo al de la tabla o al del Cristal Z que lleve. |
| Sistema Alfa | Cambia su tipo según el disco que lleve instalado. |

### Protección (2)

| Habilidad | Efecto / motivo |
|---|---|
| Puño Invisible | Si usa un movimiento de contacto, puede infligir daño al objetivo aunque este se proteja. |
| Turbotaladro | Sin descripción oficial en PokeAPI; efecto según Showdown. |

### Inmunidad (restricción previa) (1)

| Habilidad | Efecto / motivo |
|---|---|
| Surcavientos | Sin descripción oficial en PokeAPI; efecto según Showdown. |

## Fuera de PokeAPI mainline

- **Conquest:** 60 habilidades de Pokémon Conquest, excluidas por no ser mainline.
- **CAP:** Mountaineer, Rebound y Persistent, excluidas por no ser oficiales.
- **Showdown:** las cuatro variantes `Embody Aspect` se colapsan en la habilidad canónica `embody-aspect`, que queda fuera por Tera.
- **No Ability / (Vacio):** fila técnica, no es una habilidad.

## Notas de cierre

- **Alas Vendaval** queda fuera: exige prioridad condicional real y ya estaba pendiente.
- **Absorbe Agua, Absorbe Electricidad, Absorbe Fuego y Piel Seca** entran por petición expresa. **Surcavientos** sigue fuera. Las inmunidades que ya existían se conservan.
- **Ojo Mental** entra: sí existe en PokeAPI como `minds-eye`; la nota anterior de que no existía era incorrecta.
- **Moody/Veleta, Regeneración, Limber, Magic Guard, Storm Drain, Tough Claws, Steelworker, Aftermath, Bad Dreams** ya estaban cubiertas por nombres heredados y no se duplican.
- **Tera, Dynamax, Z, formas y objetos** no se incorporan.
