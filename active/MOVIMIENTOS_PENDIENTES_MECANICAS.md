# Movimientos ofensivos: estado y pendientes

> **Cierre de catálogo:** la decisión final está en `active/REVISION_FINAL_CATALOGO.md`.
> El catálogo queda en **665 movimientos canónicos + 7 internos + 1 duplicado = 673**.
> Las cifras antiguas de 513 movimientos contaban filas, no cobertura canónica.
> Las habilidades quedan en **233 canónicas + 53 homebrew + 1 fila técnica = 287**.

Actualizado tras la segunda tanda ofensiva. El catálogo ya incluye las mecánicas
de daño según estadísticas/HP, poder condicional, daño diferido y clima/terreno/tipo.

## Implementado en esta iteración

### Daño según estadísticas o HP

- Psyshock, Secret Sword (usan Defensa física del objetivo)
- Body Press (usa la Defensa del atacante)
- Foul Play (usa el Ataque del objetivo)
- Gyro Ball, Electro Ball (potencia según velocidad)
- Heavy Slam, Heat Crash (potencia según peso)
- Low Kick, Grass Knot (potencia según peso del objetivo)
- Final Gambit (daño igual al HP restante del atacante; el usuario cae)
- Endeavor (diferencia de HP)
- Nature's Madness, Ruination (mitad del HP actual)
- Super Fang (ya existía; se conserva el ejecutor de mitad de HP)

### Poder condicional

- Hex, Infernal Parade (estado alterado)
- Venoshock, Barb Barrage (envenenamiento)
- Brine (objetivo al 50 % o menos)
- Facade (atacante con quemadura, veneno o parálisis)
- Avalanche (atacante dañado en la ronda)
- Payback (objetivo ya actuó)
- Assurance (objetivo dañado en la ronda)
- Acrobatics (sin objeto activo)
- Stored Power, Punishment (boosts positivos)
- Rage Fist (golpes recibidos)
- Last Respects (aliados debilitados)
- Rollout, Ice Ball, Fury Cutter (potencia consecutiva)
- Beat Up (potencia según aliados)
- Population Bomb, Triple Axel, Triple Kick (multigolpe con precisión por golpe)
- Dragon Darts (dos golpes)

### Daño diferido

- Future Sight (Premonición)
- Doom Desire (Deseo Oculto)

Se guardan en la cola de la batalla y se resuelven al final de la ronda indicada.

### Clima, terreno y tipo

- Freeze-Dry (supereficaz contra Agua)
- Weather Ball (tipo y potencia según clima)
- Terrain Pulse (tipo y potencia según terreno)
- Hydro Steam (potenciado por sol, sin penalización de Agua)
- Psyblade (potenciado por terreno eléctrico)
- Expanding Force (potenciado por terreno psíquico)
- Rising Voltage (potenciado por terreno eléctrico)
- Misty Explosion (potenciado por terreno de niebla y autodestrucción)
- Steel Roller (falla sin terreno y lo elimina)
- Collision Course, Electro Drift (bonus si es supereficaz)
- Raging Bull (tipo según forma y rompe pantallas)

### Segunda tanda simple

Overheat, Leaf Storm, Draco Meteor, Superpower, Thunder, Blizzard,
Double-Edge, Body Slam, Boomburst, Muddy Water, Hurricane, Megahorn,
Power Whip, Aqua Tail, Dragon Claw, Zen Headbutt, Waterfall, Icicle Crash,
Air Slash, Mach Punch, Aqua Jet, Ice Shard, Shadow Sneak y Accelerock.

## Pendiente de mecánicas nuevas

### Cambio de Pokémon después de atacar

- U-turn
- Volt Switch
- Flip Turn

### Prioridad condicional

- Sucker Punch
- Upper Hand
- Fake Out
- First Impression
- Grassy Glide
- Thunderclap

### Limpieza o creación de campo

- Rapid Spin
- Mortal Spin
- Stone Axe
- Ceaseless Edge

### Contrataque

- Counter
- Mirror Coat
- Metal Burst
- Comeuppance
- Bide

### Objetos

- Fling
- Natural Gift
- Poltergeist

### Semiinvulnerabilidad y reglas de turno

- Fly
- Dig
- Dive
- Phantom Force
- Shadow Force
- Gigaton Hammer


## Tercera tanda implementada

Se añadieron 119 movimientos ofensivos simples para completar la base temprana
y los niveles intermedios. No requieren mecánicas nuevas: reutilizan multigolpe,
prioridad fija, estados, cambios de estadísticas, drenaje, recoil, trampa,
precisión perfecta, crítico alto y potencia por boosts.

### Base temprana (P0)

Placaje, Arañazo, Ataque Rápido, Eco Voz, Meteoros, Doble Patada, Golpe Roca,
Picotazo, Ala Bis, Picotazo Veneno, Polución, Ácido, Bomba Ácida, Bofetón Lodo,
Bucle Arena, Pedrada, Pin Misil, Acoso, Lengüetazo, Impresionar, Ascuas, Giro
Fuego, Pistola Agua, Burbuja, Shuriken de Agua, Torbellino, Absorber, Megaagotar,
Látigo Cepa, Follaje, Semilladora, Impactrueno, Moflete Estático, Confusión,
Nieve Polvo, Ciclón, Golpe Bis, Ráfaga Escamas, Chulería, Viento Feérico y Voz
Cautivadora.

### Intermedios (P1)

Corte, Golpe Cabeza, Cuchillada, Megapuño, Fuerza, Golpe Kárate, Patada Giro,
Palmeo, Puntapié, Puño Drenaje, Sumisión, Esfera Aural, Demolición, Ataque Ala,
Golpe Aéreo, Aire Afilado, Colmillo Veneno, Residuos, Veneno X, Puya Nociva,
Disparo Lodo, Terratemblor, Bomba Fango, Hueso Palo, Lanzarrocas, Tumba Rocas,
Joya de Luz, Viento Plata, Chupavidas, Tijera X, Zumbido, Viento Aciago, Garra
Umbría, Hueso Sombrío, Garra Metal, Bomba Imán, Disparo Espejo, Ala de Acero,
Nitrocarga, Rueda Fuego, Colmillo Ígneo, Puño Fuego, Pirotecnia, Hidropulso,
Rayo Burbuja, Pulpocañón, Escaldar, Hoja Afilada, Hoja Mágica, Ciclón de Hojas,
Asta Drenaje, Bomba Germen, Rayo Carga, Electrotela, Onda Voltio, Chispa,
Colmillo Rayo, Puño Trueno, Electrojaula, Psicorrayo, Psicocorte, Paranormal,
Psicocolmillo, Rayo Aurora, Colmillo Hielo, Puño Hielo, Dragoaliento, Pulso
Dragón, Alarido, Mordisco, Finta, Tajo Umbrío, Triturar, Pulso Noche, Beso
Drenaje, Choque Anímico, Carantoña y Cautivapor.

### Rompepantallas

**Demolición** y **Psicocolmillo** eliminan Reflejo y Pantalla de Luz del equipo
rival al conectar. La misma lógica queda disponible para Furia Taurina y futuros
movimientos mediante `rompe_pantallas` en `moves.data`.

## Datos actuales

- Catálogo: **513 movimientos**.
- Asignaciones: **13.476** en **1.077 especies**.
- Loadout de combate: **hasta 6 movimientos**.
- Movimientos disponibles para seleccionar: **sin límite** por especie.

## Movimientos de trampa añadidos

Atadura, Constricción, Cepo, Anclaje, Puntada Sombría, Mil Temblores y Presa
Maxilar. También se actualizó **Lluvia Ígnea** (Magma Storm) para que aplique
la condición de atrapado. Todos usan la condición `ATRAPADO` existente y no
requieren lógica nueva.

## Cuarta tanda implementada: multigolpe, cobertura y utilidad

- Multigolpe: Doble Golpe, Rueda Doble, Ferropuño Doble, Clavo Cañón, Plumerazo, Bombardeo, Ataque Furia y Puño Cometa.
- Cobertura: Plancha, Golpe Rastrero, Batería Asalto, Onda Ígnea, Onda Tóxica y Martillo Dragón.
- Estado: Gruñido, Malicioso, Agitacola, Ataque Arena, Pantalla de Humo, Chirrido, Eco Metálico, Cara Susto, Encanto, Ojitos Tiernos y Confidencia.
- Curación: Recuperación, Respiro, Fotosíntesis, Luz Lunar y Sol Matinal.
- Los movimientos de estado con objetivo múltiple ya resuelven a todos los rivales.

## Quinta tanda: cobertura, utilidad y fórmulas

- Cobertura y ataques simples: Derribo, Bomba Huevo, Hipertaladro, Pisotón, Cornada, Agarre, Atizar, Puño Mareo, Garra Brutal, Treparrocas, Canon, Tajo Cruzado, Puño Dinámico, Machada, Gancho Alto, Patada Relámpago, Llave Vital, Llave Corsé, Ala Mortífera, Vendaval Gélido, Cháchara, Cadena Virulenta, Ponzochoque, Fuerza Equina, Fuerza Telúrica, Taladradora, Simún de Arena, Tormenta de Diamantes, Al Ataque, Rodillo de Púas, Rencor Reprimido, Orbes Espectro, Cola Férrea, Tajo Taquión, Quemarrueda, Infierno, Bomba Ígnea, Espada Lamento, Canto Ardiente, Llama Fusión, Estallido, Hidroariete, Salpikasurf, Chorro de Vapor, Salpicar, Tormenta Floral, Ácido Málico, Fuerza G, Cañón Batidor, Abrecaminos, Fulgor Semilla, Mazazo, Voltio Cruel, Amplificador, Electropunzada, Joltioparálisis, Carga Parábola, Rayo Fusión, Resplandor, Bola Neblina, Ala Aural, Mirada Heladora, Asalto Barrera, Poder Místico, Fotocolisión, Martillo Hielo, Viento Carámbano, Lanza Glacial, Carga Dragón, Fragor Escamas, Dracoenergía, Golpe Oscuro, Genufendiente, Irreverencia, Furia Candente, Cañón Floral, Luz Aniquiladora, Ciclón Primavera y Feerichoque.
- Fórmulas por HP: Agarrón, Estrujón, Estallido, Salpicar y Dracoenergía.
- Estado: Danza Espada, Maquinación, Danza Dragón, Corpulencia, Defensa Férrea, Agilidad, Pulimento, Danza Aleteo, Rompecoraza, Enrosque, Afilagarras, Avivar, Onda Trueno, Fuego Fatuo, Tóxico, Rayo Confuso, Somnífero, Paralizador y Polvo Veneno.

## Sexta tanda: cobertura, estado y mecánicas puntuales

- Ataques: Ariete, Gas Venenoso, Trampa Venenosa, Armadura Ácida, Recogearena, Chapoteo Lodo, Fertilizante, A Defender, Auxilio, Luminicola, Estoicismo, Látigo Ígneo, Balón Ígneo, Llama Azul, V de Fuego, Envite Acuático, Danza Acuática, Puño Jet, Triple Inmersión, Disparo Certero, Leafitobombas, Onda Anómala, Aura Magnética, Carga, Psicoataque, Vasto Impacto, Legado, Decoración, Luminicola, Látigo Ígneo y compañía.
- Estado: Desarrollo, Fortaleza, Refugio, Afilar, Meditación, Doble Equipo, Reducción, Amnesia, Barrera, Masa Cósmica, Hipnosis, Canto, Beso Amoroso, Silbato, Supersónico, Beso Dulce, Danza Caos, Fanfarronear, Camelo y Deslumbrar.
- Velo Aurora (pantalla física y especial).
- Campos Eléctrico, de Hierba, Psíquico y de Niebla.
- Fórmulas fijas: Tinieblas, Sísmico, Furia Dragón, Bomba Sónica y Psicoonda.
- Otras reglas: Comesueños solo contra dormidos, Azote/Inversión por HP, Metaláser con recoil del 50 % del HP máximo, Pirueta Helada elimina terreno.
- Chapoteo Lodo reduce el daño eléctrico; Fertilizante potencia a los Pokémon de tipo Planta.

## Séptima tanda: habilidades y movimientos de control

- Habilidades añadidas: Nado Rápido, Poder Solar, Cura Lluvia, Multiescamas, Adaptable, Puño Férreo, Punk Rock, Mandíbula Fuerte, Piel Tosca, Sebo, Herbívoro, Filtro, Roca Sólida y Entusiasmo. El catálogo queda en 100 habilidades.
- Movimientos: Desquite, Branquibocado, Electropico, Aguijón Letal, Niebla Clara, Aria Burbuja, Canto Encantador, Envidia Ardiente, Frío Polar (OHKO), Niebla, Alivio, Autosugestión, Foco Energía, Aguzar, Mofa, Descanso, Divide Dolor, Cambiafuerza, Cambiadefensa, Cambiavelocidad, Aromaterapia y Cascabel Cura.

## Octava tanda: habilidades de terreno, control y equipo

- Terrenos: Electrogénesis, Herbogénesis, Psicogénesis y Nebulogénesis.
- Entorno: Aclimatación.
- Control: Ritmo Propio, Intrépido, Insonoridad, Insomnio, Inmunidad, Hidratación, Mudar, Viscosidad y Antibalas.
- Daño/precisión: Ojo Compuesto, Potencia Bruta, Armadura Prisma y Piel Milagrosa.
- Equipo: Compiescolta, Velo Aroma, Velo Flor y Espejo Mágico.
- Catálogo de habilidades: **199**.

## Novena tanda: habilidades reactivas

- Electromotor, Justiciero, Cobardía, Firmeza y Quitanieves.
- Armadura Frágil, Cólera, Tenacidad, Competitivo y Combustible.
- Cuerpo Horneado, Geofagia, Sal Purificadora, Cuerpo Áureo, Expulsarena y Disemillar.
- Total: **144 habilidades** y **998 asignaciones** de habilidades.

## Décima tanda: defensa y potenciadores de tipo

- Inmunidad a bajadas: Cuerpo Puro, Humo Blanco, Corte Fuerte, Sacapecho y Vista Lince.
- Reducción de daño: Pelaje Recio, Escama de Hielo, Guardia Espectro, Ignífugo, Peluche, Funda y Pompa.
- Reacciones: Termoconversión, Hidrorrefuerzo, Coraza Ira y Velo Pastel.
- Potenciadores: Mandíbula Dragón, Transistor y Transportarrocas.
- Total: **163 habilidades** y **1.139 asignaciones** de habilidades.

## Undécima tanda: inmunidades, estados y disparos

- Superguarda, Levitación, Telepatía, Ventosas, Espíritu Vital, Escudo Magma y Despiste.
- Perro Guardián, Agallas, Pies Rápidos, Ímpetu Tóxico, Ímpetu Ardiente y Escama Especial.
- Afortunado, Ultraimpulso, Coránima, Impulso y Don Floral.
- Total: **181 habilidades** y **1.324 asignaciones** de habilidades.

## Duodécima tanda: stats, tipado y equipo

- Stats: Simple, Respondón, Ignorante, Irascible, Francotirador, Encadenado y Experto.
- Tipado: Piel Celeste, Piel Feérica, Piel Helada, Piel Eléctrica, Normalidad, Voz Fluida y Valentía.
- Equipo: Batería, Fuente Energía, Alma Acerada y Alma Cura.
- Total: **199 habilidades** y **1.452 asignaciones** de habilidades.
- Pendiente de esta tanda: Alas Vendaval, porque necesita prioridad condicional real en el orden de turnos.
