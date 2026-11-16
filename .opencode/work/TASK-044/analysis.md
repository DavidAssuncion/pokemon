# TASK-044 — Análisis

- **Estado:** COMPLETADO (dos investigaciones en paralelo)
- **Analistas:**
  - Parte A — Contenido, roster y encaje geográfico: `analyst`, sesión `ses_e2f7ccbf1ffeXeG6XV5EihGNgl`.
  - Parte B — Infraestructura narrativa y misiones: `analyst`, sesión `ses_e2f7ccbeeffeyOJtXDE6e4bCT5` (informe completado en una segunda vuelta tras el límite de pasos).
- **Nota:** se reproducen los informes de los analistas sin alterar sus conclusiones. Las afirmaciones negativas se limitan al código y los datos revisados. No se modificó ningún archivo de aplicación.

---

## Parte A — Inventario de villanos/facciones y encaje con el mundo

He alcanzado el límite máximo de pasos del agente durante la fase final de citas. El informe siguiente se basa exclusivamente en las lecturas y consultas ya realizadas; **no se modificó ningún archivo**. Quedan pendientes únicamente verificaciones de línea exacta para algunas filas CSV, que señalo donde aplica.

---

# TASK-044 — Inventario de villanos/facciones y encaje con el mundo

**Estado:** análisis cerrado con la evidencia recogida. Solo lectura. No se ejecutaron migraciones, seeders, tests ni comandos de escritura.

## 1. Resumen ejecutivo

Existe una propuesta propia en `Tareas_pdte/mejoras` con **8 facciones** (Rocket, Magma, Aqua, Galaxia, Plasma, Flare, Skull, Yell), cada una con líder, 5 Pokémon y una lista de legendarios desbloqueables. No hay nada implementado: no hay tablas, modelos, NPCs, diálogos ni código de facciones.

El roster verificado (`storage/data/pokemon.csv`, 1176 filas) contiene **casi todo** el material: 22 de 24 especies de equipo se resuelven por nombre exacto; las excepciones son formas con nombre compuesto (`slowbro-galar`, `toxtricity`) y un nombre canónico incompleto (`giratina`). Las formas regionales existen como duplicados de nombre con otro `id` (p. ej. `slowbro` 10165 = Veneno/Psíquico).

Los 8 equipos tienen entre 4 y 5 especies asignadas a algún hábitat. Solo **Barbaracle** y **Salazzle** no están en ningún hábitat. `slowbro-galar` existe como forma 10165 en Pantano Ponzoña.

Hay 56 hábitats, pero **12 no tienen ningún Pokémon asignado** en `pokemon_habitat.csv`. El hábitat 49, `Legendarios` (provincia `Vacio`), concentra **100 especies** (82 legendarios/míticos + 18 UBs/paradojas), casi todas en nivel 2. Eso significa que hoy los legendarios ya son “salvajes” de un pool normal, sin desbloqueo, sin encuentro especial y sin captura garantizada.

Los gimnasios ya usan legendarios como rivales (Mewtwo, trío del lago, Lugia, Cresselia, Deoxys, Giratina, Darkrai, Registeel, Dialga, Rayquaza, Koraidon). Eso choca con la idea de “derrotar la facción desbloquea el legendario”.

Las mazmorras existen como mecanismo (pisos, jefe 3v1, progreso, cooldown, recompensa 7×), pero **ningún hábitat tiene `mazmorra` configurada en el repositorio**: la columna es nullable y no hay seeder/migración que la rellene. Solo los tests usan fixtures.

El encaje narrativo con TASK-043 es bueno: la campaña de 8 capítulos ya reserva un cierre por provincia y un desbloqueo duro de `Legendarios` en el capítulo 8. Pero el sistema de misiones no tiene NPCs ni diálogo; las facciones tendrían que entrar como “hitos” y no como árboles de conversación.

**Veredicto:** material viable como arco de facciones post-campaña o como cierre de capítulo, pero necesita decisiones de producto sobre número de combates, formato 3v3 vs 5 Pokémon, destino sacrifice de los legendarios y regla de captura.

---

## 2. Ficha de las 8 facciones (`Tareas_pdte/mejoras`)

Formato original: `líder - A B C | D E`. El pipe separa probablemente vanguardia/retaguardia o dos grupos de combate; **no está definido**. La propuesta dice “9 combates” por facción, pero solo hay 8 facciones y 5 Pokémon por facción: hay ambigüedad real.

| Facción | Líder | Equipo (5) | Legendarios desbloqueados | Ideología/tema deducido | Estado |
|---|---|---|---|---|---|
| Team Rocket | Giovanni | Nidoqueen, Nidoking, Rhyperior, Mewtwo, Persian | Mewtwo | Mafia criminal; experimentación; ciudad/industria; veneno/tierra | Idea, no implementado |
| Team Magma | Magno (Maxie) | Mightyena, Steelix, Blaziken, Groudon, Houndoom | Groudon; con Aqua, Rayquaza | Expansionismo terrestre; fuego/tierra | Idea, no implementado |
| Team Aqua | Aquiles (Archie) | Mightyena, Walrein, Swampert, Kyogre, Sharpedo | Kyogre; con Magma, Rayquaza | Expansionismo marino; agua/hielo | Idea, no implementado |
| Team Galaxia | Helio (Cyrus) | Giratina, Bronzong, Palkia, Weavile, Dialga | Dialga, Palkia, Giratina | Cosmos/tiempo/espacio; acero/psíquico/dragón | Idea, no implementado |
| Team Plasma | Ghechis (Ghetsis) | Cofagrigus, Kyurem, Zekrom, Reshiram, Hydreigon | Reshiram, Zekrom, Kyurem | Liberación demagógica; dragón/electricidad/fuego/hielo | Idea, no implementado |
| Team Flare | Lysson (Lysandre) | Goodra, Barbaracle, Xerneas, Yveltal, Gyarados | Xerneas, Yveltal | Belleza selectiva; arma definitiva; hada/siniestro | Idea, no implementado |
| Team Skull | Guzmán (Guzma) | Scizor, Golisopod, Salazzle, Escavalier, Kingdra | **Ninguno definido** | Banda callejera; rechazo al sistema; bicho/veneno | Idea, no implementado |
| Team Yell | Nerio (Piers) | Obstagoon, Perrserker, Toxtricity, Grimmsnarl, Slowbro-Galar | **Ninguno definido** | Fanatismo/punk; carretera; siniestro/hada/eléctrico | Idea, no implementado |

**Observaciones de ficha:**
- Rocket y Skull comparten parte del pool (Veneno/Bicho); Magma y Aqua comparten Mightyena y el eje Groudon/Kyogre.
- Flare lleva **dos legendarios** en el equipo base (Xerneas/Yveltal) además de desbloquearlos. Eso es incoherente: o son equipo de líder o son recompensa.
- Galaxia y Plasma llevan **tres legendarios** en el equipo base. Si el líder ya pelea con ellos, ¿qué queda por desbloquear?
- Skull y Yell no tienen legendario de cierre. Es el hueco más evidente del plan.
- “9 combates” no encaja con 5 Pokémon ni con el motor 3v3 actual.

---

## 3. Verificación del roster

Fuente: `storage/data/pokemon.csv` (columnas `id,name,species_id,...,is_default`) y `storage/data/pokemon_types.csv` (tipos traducidos con `pokeapi/data/v2/csv/type_names.csv`, `local_language_id=7`).

| Facción | Especie pedida | ¿Existe? | id | species_id | Tipos | Nota |
|---|---|---|---|---|---|---|
| Rocket | nidoqueen | Sí | 31 | 31 | Veneno/Tierra | — |
| Rocket | nidoking | Sí | 34 | 34 | Veneno/Tierra | — |
| Rocket | rhyperior | Sí | 464 | 464 | Tierra/Roca | — |
| Rocket | mewtwo | Sí | 150 | 150 | Psíquico | — |
| Rocket | persian | Sí | 53 | 53 | Normal | También 10108 (Siniestro, forma Alola, sin hábitat) |
| Magma | mightyena | Sí | 262 | 262 | Siniestro | — |
| Magma | steelix | Sí | 208 | 208 | Acero/Tierra | — |
| Magma | blaziken | Sí | 257 | 257 | Fuego/Lucha | — |
| Magma | groudon | Sí | 383 | 383 | Tierra | — |
| Magma | houndoom | Sí | 229 | 229 | Siniestro/Fuego | — |
| Aqua | mightyena | Sí | 262 | 262 | Siniestro | — |
| Aqua | walrein | Sí | 365 | 365 | Hielo/Agua | — |
| Aqua | swampert | Sí | 260 | 260 | Agua/Tierra | — |
| Aqua | kyogre | Sí | 382 | 382 | Agua | — |
| Aqua | sharpedo | Sí | 319 | 319 | Agua/Siniestro | — |
| Galaxia | giratina | **No literal** | 487 | 487 | Fantasma/Dragón | El nombre real es `giratina-altered`; `giratina-origin` es 10007 |
| Galaxia | bronzong | Sí | 437 | 437 | Acero/Psíquico | — |
| Galaxia | palkia | Sí | 484 | 484 | Agua/Dragón | — |
| Galaxia | weavile | Sí | 461 | 461 | Siniestro/Hielo | — |
| Galaxia | dialga | Sí | 483 | 483 | Acero/Dragón | — |
| Plasma | cofagrigus | Sí | 563 | 563 | Fantasma | — |
| Plasma | kyurem | Sí | 646 | 646 | Dragón/Hielo | — |
| Plasma | zekrom | Sí | 644 | 644 | Dragón/Eléctrico | — |
| Plasma | reshiram | Sí | 643 | 643 | Dragón/Fuego | — |
| Plasma | hydreigon | Sí | 635 | 635 | Siniestro/Dragón | — |
| Flare | goodra | Sí | 706 | 706 | Dragón | — |
| Flare | barbaracle | Sí | 689 | 689 | Roca/Agua | **Sin hábitat asignado** |
| Flare | xerneas | Sí | 716 | 716 | Hada | — |
| Flare | yveltal | Sí | 717 | 717 | Siniestro/Volador | — |
| Flare | gyarados | Sí | 130 | 130 | Agua/Volador | — |
| Skull | scizor | Sí | 212 | 212 | Bicho/Acero | — |
| Skull | golisopod | Sí | 768 | 768 | Bicho/Agua | — |
| Skull | salazzle | Sí | 758 | 758 | Veneno/Fuego | **Sin hábitat asignado** |
| Skull | escavalier | Sí | 589 | 589 | Bicho/Acero | — |
| Skull | kingdra | Sí | 230 | 230 | Agua/Dragón | — |
| Yell | obstagoon | Sí | 862 | 862 | Siniestro/Normal | — |
| Yell | perrserker | Sí | 863 | 863 | Acero | — |
| Yell | toxtricity | **No literal** | 849 | 849 | Eléctrico/Veneno | El nombre real es `toxtricity-amped`; `toxtricity-low-key` es 10184 |
| Yell | grimmsnarl | Sí | 861 | 861 | Siniestro/Hada | — |
| Yell | slowbro-galar | **No literal** | 10165 | 80 | Veneno/Psíquico | La forma Galar existe como `slowbro` duplicado; no lleva sufijo `-galar` |

**Conclusión:** 22/24 por nombre exacto; 2/24 requieren resolución por forma (`giratina`, `toxtricity`) y 1/24 es un nombre inexistente pero con forma real (`slowbro-galar`). No hay especies inventadas.

**Riesgo de nombres duplicados:** `pokemon.csv` tiene 38 nombres repetidos (formas regionales con el mismo `name` que la forma base). Cualquier resolución por nombre es ambigua y debe usar `id` o `is_default`.

---

## 4. Hábitats actuales de las especies relevantes

Fuente: `storage/data/pokemon_habitat.csv` (787 asignaciones, 786 Pokémon distintos) y `storage/data/habitats.csv` (56 hábitats). Provincias: 1 Galicia, 2 Euskadi, 3 Barcelona, 4 Extremadura, 5 Nova Alacant, 6 Mutxamel, 7 Andalucía, 8 Vacío.

| Facción | Especie | Hábitat | Provincia | Nivel |
|---|---|---|---|---|
| Rocket | nidoqueen | Sabana | Andalucía (7) | 3 |
| Rocket | nidoking | Sabana | Andalucía (7) | 3 |
| Rocket | rhyperior | Cueva Granito | Andalucía (7) | 3 |
| Rocket | mewtwo | Legendarios | Vacío (8) | 2 |
| Rocket | persian | Pradera Salvaje | Nova Alacant (5) | 2 |
| Magma | mightyena | Las Ramblas | Barcelona (3) | 2 |
| Magma | steelix | Cueva Granito | Andalucía (7) | 2 |
| Magma | blaziken | Sierra Quemada | Andalucía (7) | 3 |
| Magma | groudon | Legendarios | Vacío (8) | 2 |
| Magma | houndoom | Sierra Quemada | Andalucía (7) | 2 |
| Aqua | mightyena | Las Ramblas | Barcelona (3) | 2 |
| Aqua | walrein | Playa Cubito | Galicia (1) | 3 |
| Aqua | swampert | Charca Renacuajo | Mutxamel (6) | 3 |
| Aqua | kyogre | Legendarios | Vacío (8) | 2 |
| Aqua | sharpedo | Mar Sereno | Galicia (1) | 2 |
| Galaxia | giratina-altered | Legendarios | Vacío (8) | 2 |
| Galaxia | bronzong | Mina Magnética | Galicia (1) | 3 |
| Galaxia | palkia | Legendarios | Vacío (8) | 2 |
| Galaxia | weavile | Caverna Gélida | Galicia (1) | 2 |
| Galaxia | dialga | Legendarios | Vacío (8) | 2 |
| Plasma | cofagrigus | Cámara Omega | Euskadi (2) | 2 |
| Plasma | kyurem | Legendarios | Vacío (8) | 2 |
| Plasma | zekrom | Legendarios | Vacío (8) | 2 |
| Plasma | reshiram | Legendarios | Vacío (8) | 2 |
| Plasma | hydreigon | Cueva Dragón | Euskadi (2) | 3 |
| Flare | goodra | Cueva Dragón | Euskadi (2) | 3 |
| Flare | barbaracle | **Sin hábitat** | — | — |
| Flare | xerneas | Legendarios | Vacío (8) | 2 |
| Flare | yveltal | Legendarios | Vacío (8) | 2 |
| Flare | gyarados | Mar Sereno | Galicia (1) | 2 |
| Skull | scizor | Bosque Crecido | Extremadura (4) | 2 |
| Skull | golisopod | Bosque Crecido | Extremadura (4) | 2 |
| Skull | salazzle | **Sin hábitat** | — | — |
| Skull | escavalier | Bosque Crecido | Extremadura (4) | 2 |
| Skull | kingdra | Estrecho de Gibraltar | Andalucía (7) | 3 |
| Yell | obstagoon | Calvari | Mutxamel (6) | 3 |
| Yell | perrserker | Pradera Salvaje | Nova Alacant (5) | 2 |
| Yell | toxtricity-amped | Prado Trueno | Andalucía (7) | 2 |
| Yell | grimmsnarl | Las Ramblas | Barcelona (3) | 3 |
| Yell | slowbro (10165, Galar) | Pantano Ponzoña | Barcelona (3) | 2 |

**Hábitats vacíos (sin ninguna asignación):** Bonalba (12, Mutxamel), Monte Disciplina (17, Galicia), Bosque Seta (30, Andalucía), Cañar (50, Mutxamel), Jungla (51, Nova Alacant), Santa Pola (52, Mutxamel), Monte Oscuro (53, Nova Alacant), Tierra Abrasada (54, Barcelona), Sierra Aitana (55, Galicia), Cueva del Eco (56, Barcelona), Fosa Abisal (57, Barcelona), Pradera Tranquila (58, Barcelona). Son 12 de 56; no pueden alojar una facción sin poblar antes.

**Tamaños de pool relevantes:** Legendarios 100, Bosque Crecido 36, Charca Renacuajo 31, Sierra Quemada 28, Las Ramblas 24, Mina Magnética 24, Selva 23, Cueva Dragón 23, Cueva Granito 20, Bosque Brumoso 20, Monte Verde 20, Pantano Ponzoña 19, Desierto Horno 19, Cráter 12, Mar Sereno 8, Central Energía 8, Cámara Omega 7, Vieja Central 6, Lago Místico 2.

---

## 5. Encaje geográfico propuesto

No invento hábitats: uso los existentes y justifico con tipos del pool y presencia de especies del equipo.

| Facción | Provincia candidata | Hábitats candidatos | Justificación con datos |
|---|---|---|---|
| Rocket | Barcelona (3) | Pantano Ponzoña + Las Ramblas | Pantano: 18 de 19 entradas son Veneno; Las Ramblas: 16 Siniestro/5 Veneno, 24 especies, nombre urbano. Alternativa industrial: Vieja Central (Galicia, Psíquico/Normal) o Cueva Granito (Rhyperior). |
| Magma | Andalucía (7) | Cráter + Sierra Quemada | Cráter: 12 Fuego/12; Sierra Quemada: 28 Fuego/28, con Blaziken y Houndoom. Es el eje fuego más puro del mapa. |
| Aqua | Galicia (1) | Corriente Marina + Mar Sereno | Corriente: 13 Agua/14; Mar Sereno: 8 Agua/8, con Sharpedo y Gyarados. Alternativa: Bahía Crustáceo (Mutxamel, 11 Agua/10 Roca). |
| Galaxia | Euskadi (2) | Monte Partido + Cámara Omega + Cueva Dragón | Cámara Omega: 4 Psíquico/3 Fantasma, nombre “Omega”; Cueva Dragón: 20 Dragón/23; Monte Partido: 21 especies, nexo entre ambos. Complemento: Mina Magnética (Galicia, 22 Acero) para Bronzong. |
| Plasma | Euskadi (2) + Galicia (1) | Cueva Dragón + Central Energía / Caverna Gélida | Cueva Dragón aporta 20 Dragón; Central Energía es 8 Eléctrico/8 (Zekrom); Caverna Gélida es 18 Hielo/21 (Kyurem). No hay un hábitat único con dragón+eléctrico+hielo. |
| Flare | Nova Alacant (5) + Barcelona (3) | Pradera Orgullosa + Cámara Alfa / Vieja Reliquia | Pradera Orgullosa: 12 Hada/18, con Togekiss; Cámara Alfa: 6 Fantasma/4 Psíquico/3 Fuego, nombre de “arma definitiva”; Vieja Reliquia: 8 Psíquico/11. |
| Skull | Extremadura (4) + Barcelona (3) | Bosque Crecido + Pantano Ponzoña | Bosque Crecido: pool 36 con Scizor, Golisopod y Escavalier; Pantano Ponzoña: 18 Veneno/19 para el eje veneno. Alternativa urbana: Las Ramblas (Barcelona). |
| Yell | Andalucía (7) + Mutxamel (6) | Prado Trueno + Calvari + Las Ramblas | Prado Trueno: 16 Eléctrico/16, con Toxtricity; Calvari: 7 Normal/7 Fantasma/5 Siniestro, con Obstagoon; Las Ramblas: 16 Siniestro, con Grimmsnarl. |

**Nota geográfica:** el reparto deja a Extremadura y Nova Alacant con poca presencia de facción si se sigue el canon (son bosque/pradera). Es una oportunidad para Skull (bosque/bicho) y Flare (pradera/hada), pero exigiría llevar allí sus equipos. No hay facción canónica de “bosque” ni de “pradera”; el encaje es temático, no canónico.

---

## 6. Gimnasios y mazmorras

### 6.1 Gimnasios existentes

18 gimnasios, 4 etapas cada uno, definidos en `src/Gimnasios/Domain/CatalogoGimnasios.php` y replicados en `Tareas_pdte/gyms.txt`. Niveles mínimos: Bicho 10, Veneno 15, Normal 20, Planta 25, Volador 31, Roca 36, Eléctrico 41, Hielo 46, Fuego 52, Agua 57, Tierra 62, Psíquico 67, Siniestro 73, Fantasma 78, Lucha 83, Hada 88, Acero 94, Dragón 100.

| Facción | Gimnasio base/cierre | Solape verificado | Comentario |
|---|---|---|---|
| Rocket | Veneno (nivel 15) | Líder con Nidoqueen/Nidoking/Roserade; el equipo Rocket lleva Nidoqueen/Nidoking | El solape es casi exacto. Es el mejor cierre de facción del juego. |
| Magma | Fuego (52) | Etapa 4 con Blaziken; el equipo Magma lleva Blaziken | Fuego + Tierra (62) como alternativa. |
| Aqua | Agua (57) | Etapa 4 con Swampert; el equipo Aqua lleva Swampert | Agua + Hielo (46) como alternativa. |
| Galaxia | Psíquico (67) / Dragón (100) / Acero (94) | Psíquico: Uxie/Mesprit/Azelf, Lugia, Cresselia/Deoxys, Mewtwo; Dragón: Giratina/Rayquaza/Koraidon; Acero: Registeel/Dialga | Ya usa legendarios del arco. Riesgo de canibalización. |
| Plasma | Eléctrico (41) / Hielo (46) / Dragón (100) | Eléctrico: Raging Bolt (paradoja); Hielo: sin Kyurem; Dragón: sin Kyurem/Reshiram/Zekrom | No hay gimnasio con el trío tao. Habría que usar Eléctrico para Zekrom y Hielo para Kyurem, sin cierre único. |
| Flare | Hada (88) / Siniestro (73) | Hada: Togekiss, Iron Valiant, Gardevoir; Siniestro: Mandibuzz, Obstagoon, Darkrai | Hada encaja con Xerneas; Siniestro con Yveltal/Darkrai. |
| Skull | Bicho (10) / Veneno (15) | Bicho: Shuckle, Scizor, Pinsir; el equipo Skull lleva Scizor | Bicho es el cierre natural. Veneno está ocupado por Rocket. |
| Yell | Siniestro (73) / Eléctrico (41) | Siniestro: Mandibuzz/Obstagoon/Darkrai; el equipo Yell lleva Obstagoon; Eléctrico: Toxtricity no está, pero el tipo sí | Siniestro es el mejor cierre; Eléctrico para Toxtricity. |

**Legendarios ya presentes en gimnasios:** Mewtwo (Psíquico, etapa 4), Uxie/Mesprit/Azelf (Psíquico, etapa 2), Lugia (Psíquico, etapa 3), Cresselia/Deoxys (Psíquico, etapa 4), Giratina (Fantasma etapa 4 y Dragón etapa 4), Darkrai (Siniestro, etapa 4), Registeel/Dialga (Acero, etapa 4), Rayquaza/Koraidon (Dragón, etapa 4). También hay paradojas: Raging Bolt (Eléctrico) e Iron Valiant (Hada).

### 6.2 Mazmorras

- Mecanismo implementado: `habitats.mazmorra` (JSON nullable), `ConfiguracionMazmorra` lee `pisos[]` con `piso` y `species_id`; combate 3v1; progreso por usuario/hábitat; cooldown 1 h; recompensa 7× gimnasio.
- **Configuración existente en el repositorio: 0 de 56 hábitats.** No hay seeder, migración ni catálogo que rellene `mazmorra`. Solo los tests crean fixtures (`tests/Feature/Mazmorras/...`). Si la base de datos de ejecución tiene mazmorras, no es reproducible desde el repositorio.
- Candidatos por nombre/tema, **sin configuración actual**: Rocket → Vieja Central o Cueva Granito; Magma → Cráter; Aqua → Fosa Abisal (vacío de pool); Galaxia → Cámara Omega; Plasma → Caverna Gélida; Flare → Cámara Alfa o Vieja Reliquia; Skull → Pantano Ponzoña; Yell → Cueva del Eco (vacío de pool).
- Los hábitats vacíos no pueden ser mazmorra hasta poblar su pool, porque el jefe se elige por `species_id` y el jugador necesita un pool previo para explorar.

---

## 7. Legendarios

### 7.1 Cobertura en el roster

La lista de `mejoras` (líneas 19-37) se resuelve casi completa. Los “no literales” son formas:

| Grupo | Especies | id | Tipos | Hábitat |
|---|---|---|---|---|
| Aves | Articuno, Zapdos, Moltres | 144, 145, 146 | Hielo/Volador, Eléctrico/Volador, Fuego/Volador | Legendarios (49), n2 |
| Aves de Galar | Articuno/Zapdos/Moltres de Galar | 10169, 10170, 10171 | Psíquico/Volador, Lucha/Volador, Siniestro/Volador | Legendarios (49), n2; **nombre literal no existe** |
| Perros | Raikou, Entei, Suicune | 243, 244, 245 | Eléctrico, Fuego, Agua | Raikou/Entei en 49 n2; **Suicune también en Corriente Marina (n2)** |
| Torres | Lugia, Ho-Oh | 249, 250 | Psíquico/Volador, Fuego/Volador | 49, n2 |
| Gigantes | Regirock, Regice, Registeel, Regigigas, Regieleki, Regidrago | 377, 378, 379, 486, 894, 895 | Roca, Hielo, Acero, Normal, Eléctrico, Dragón | 49, n2 |
| Ancestral | Kyogre, Groudon, Rayquaza | 382, 383, 384 | Agua, Tierra, Dragón/Volador | 49, n2 |
| Eón | Latias, Latios | 380, 381 | Dragón/Psíquico | 49, n2 |
| Lago | Uxie, Mesprit, Azelf | 480, 481, 482 | Psíquico | 49, n2 |
| Creador | Dialga, Palkia, Giratina | 483, 484, 487 | Acero/Dragón, Agua/Dragón, Fantasma/Dragón | 49, n2; **Giratina literal = `giratina-altered`** |
| Sinnoh | Heatran, Shaymin, Cresselia, Darkrai, Arceus | 485, 492, 488, 491, 493 | Fuego/Acero, Planta, Psíquico, Siniestro, Normal | 49, n2; **Shaymin literal = `shaymin-land`** |
| Kalos singulares | Diancie, Volcanion, Hoopa | 719, 721, 720 | Roca/Hada, Fuego/Agua, Psíquico/Fantasma | 49, n2 |
| Equilibrio | Xerneas, Yveltal, Zygarde | 716, 717, 718 | Hada, Siniestro/Volador, Dragón/Tierra | 49, n2; **Zygarde literal = `zygarde-50`** |
| Espadachines | Cobalion, Terrakion, Virizion | 638, 639, 640 | Acero/Lucha, Roca/Lucha, Planta/Lucha | 49, n2 |
| Naturaleza | Tornadus, Thundurus, Landorus | 641, 642, 645 | Volador, Eléctrico/Volador, Tierra/Volador | 49, n2; **literal = `-incarnate`** |
| Guardianes | Tapu Koko, Lele, Bulu, Fini | 785, 786, 787, 788 | Eléctrico/Hada, Psíquico/Hada, Planta/Hada, Agua/Hada | 49, n2 |
| Luz | Solgaleo, Lunala, Necrozma | 791, 792, 800 | Psíquico/Acero, Psíquico/Fantasma, Psíquico | 49: Solgaleo/Lunala **n3**, Necrozma n2 |
| Paradojas | Koraidon, Miraidon | 1007, 1008 | Lucha/Dragón, Eléctrico/Dragón | 49, n2 |

**Fuera de la lista de `mejoras`, pero legendarios/míticos y ya asignados:** Meltan (808) en Mina Magnética n1 y Melmetal (809) en Mina Magnética n3. Es el único caso de legendario integrado en un hábitat temático, no en el 49.

### 7.2 Composición real del hábitat 49

- 100 entradas, 94 en nivel 2, 3 en nivel 1 y 3 en nivel 3.
- Nivel 1: Cosmog, Phione, Poipole.
- Nivel 3: Lunala, Manaphy, Solgaleo.
- 82 son `is_legendary` o `is_mythical`; las otras 18 son UBs/paradojas (Nihilego, Buzzwole, Pheromosa, Xurkitree, Celesteela, Kartana, Guzzlord, Poipole, Naganadel, Stakataka, Blacephalon, Rotom, Walking Wake, Iron Leaves, Iron Boulder, Iron Crown, Raging Bolt, Gouging Fire). **No es un pool “solo legendarios”.**
- Ninguna entrada tiene `capture_rate = 0`.
- El hábitat 49 no tiene ningún filtro por `is_legendary`: es un pool normal más, con peligro orientativo 4 y provincia `Vacio`.

### 7.3 Mecanismo de encuentro/captura actual

- **Encuentro:** no existe un sistema de legendarios. Se pueden encontrar como salvajes en el hábitat 49 (ruta/exploración) o como rivales de gimnasio. No hay “encuentro desbloqueado”, ni tabla de legendarios derrotados, ni flag por jugador.
- **Captura:** la captura de ruta es un roll por Pokémon derrotado, con probabilidad base máxima del 25 % (`ProbabilidadCaptura::BASE_MAXIMA`), modulada por hábitat/refugio/Senda. No hay captura garantizada salvo shiny salvaje. En exploraciones se usa el mismo marco.
- **Shiny:** salvaje 1/2048 (`ProbabilidadesShiny::SALVAJE`); huevo 1/512 (`HUEVO`). Un shiny salvaje capturado recibe IV 15/15 (`GeneradorIvs::maximos`).
- **IVs:** escala 0-15, no 0-31. Salvaje no shiny: 0-15 aleatorio. Reclutamiento: mínimo según nido, máximo 15. Huevo: rango según nido.
- **No existe:** IV mínimo 10 para legendarios; 1/512 shiny específico de legendario; rechazar captura; +1 IV acumulable; +10 % de stats acumulable; estado de “legendario rechazado” o contador de rechazos; reclutamiento garantizado tras derrotar.

### 7.4 Contraste con la regla propuesta en `mejoras` (línea 38)

| Regla propuesta | Estado real | Brecha |
|---|---|---|
| 10 IV mínimo | IVs 0-15; salvaje 0-15; shiny 15 | No hay suelo de 10 para legendarios |
| 1/512 shiny | Salvaje 1/2048; huevo 1/512 | El 1/512 existe, pero no para legendarios salvajes |
| Rechazar captura para +1 IV | No existe flujo de rechazo | Requiere estado persistente por jugador/legendario |
| +10 % stats acumulable | No existe modificador de stats acumulable | Requiere campo nuevo y tope; riesgo de bola de nieve |
| Derrotar asegura reclutamiento | La captura es probabilística (≤25 %) | Cambia el contrato de captura solo para legendarios |
| Desbloqueo por facción o Sendas | No hay desbloqueo; Sendas son progresión de movimientos por tipo | No hay “combate de leyenda” en Sendas |

---

## 8. Nombres canónicos vs inventados

| Nombre en `mejoras` | Nombre canónico internacional | Tipo | Observación |
|---|---|---|---|
| Giovanni | Giovanni | Canónico | Se mantiene igual en español |
| Magno | Maxie | Localización española | No es inventado; es el nombre oficial en España |
| Aquiles | Archie | Localización española | Ídem |
| Helio | Cyrus | Localización española | Ídem |
| Ghechis | Ghetsis | Localización española | Ídem |
| Lysson | Lysandre | Localización española (a confirmar) | No pude verificar la fuente oficial en esta sesión; tratar como adaptación |
| Guzmán | Guzma | Localización española | Ídem |
| Nerio | Piers | Localización española (a confirmar) | Piers no es exactamente “villano”; es líder de Yell y personaje trágico |

**Riesgos de tono/propiedad intelectual:**
- Los nombres y equipos son material oficial de Nintendo/Game Freak/Creatures/TCPi. El proyecto ya usa nombres de Pokémon y gimnasios; añadir líderes y tramas aumenta la superficie derivada. No es asesoría legal: si el juego se publica, conviene revisar licencia, atribución y ausencia de arte/diálogo copiado.
- `Guzmán`/Skull y `Nerio`/Yell son villanos “de barrio” y “fan club”, no amenazas mundiales. Ponerles legendarios de cierre puede romper el tono. Rocket/Magma/Aqua/Galaxia/Plasma/Flare sí admiten un arco apocalíptico.
- El mechanic de “rechazar captura para hacerlo más fuerte” puede leerse como gambling/grind; con acumulación infinita de +10 %, premia la paciencia sobre la habilidad. Es un riesgo de diseño, no solo de IP.

---

## 9. Huecos de contenido

| Facción | Hueco | Alternativa con roster existente |
|---|---|---|
| Flare | Barbaracle no está en ningún hábitat | Usar Carracosta (Mar Sereno, Roca/Agua), Drednaw (Pozas de Valencia, Agua/Roca) o Crawdaunt (Bahía Crustáceo, Agua/Siniestro). Mantiene el eje Roca/Agua. |
| Skull | Salazzle no está en ningún hábitat | Usar Dragalge (Pantano Ponzoña, Veneno/Dragón) o Toxapex (Pantano Ponzoña, Veneno/Agua); ambos ya asignados y encajan con el eje veneno. |
| Yell | `slowbro-galar` no existe por nombre; la forma 10165 sí está en Pantano Ponzoña | Usar el id 10165 o el nombre `slowbro` con `is_default=0`; no crear especie nueva. |
| Skull | Sin legendario de cierre | Candidatos ya verificados en el pool 49: **Guzzlord** (UB, tema “devorar”) o **Tapu Bulu** (guardián de Alola). Ambos existen y están en el 49. |
| Yell | Sin legendario de cierre | No hay legendario canónico de Yell. Candidatos ya verificados: **Meltan/Melmetal** (Mina Magnética, no villanos) o **Eternatus/Zacian/Zamazenta** (no verificados en este informe). Si se exige uno, verificar su id y asignación antes de prometerlo. |
| Plasma | No hay gimnasio con el trío tao | Usar Eléctrico para Zekrom y Hielo para Kyurem; Reshiram se queda sin gimnasio. Alternativa: cerrar Plasma en Dragón (genérico) y dejar el trío como recompensa. |
| Galaxia | Sus 3 legendarios ya están en el equipo base y en gimnasios | Decidir si el líder pelea con ellos (como jefe) o si son la recompensa. No pueden ser ambas cosas sin romper la fantasía. |
| Todas | 12 hábitats sin pool | No asignar facciones a Bonalba, Monte Disciplina, Bosque Seta, Cañar, Jungla, Santa Pola, Monte Oscuro, Tierra Abrasada, Sierra Aitana, Cueva del Eco, Fosa Abisal y Pradera Tranquila hasta poblarlos. |
| Todas | 5 Pokémon por facción vs motor 3v3 | Partir cada facción en 2 combates (3+2) o recortar a 3. “9 combates” no es viable con el formato actual sin diseño nuevo. |
| Todas | Sin NPCs/diálogo | TASK-043 propone 4 voces y notas de una línea; las facciones necesitan un emisor (Consejo/archivista) y texto de una línea, no conversaciones. |
| Todas | Sin sistema de “derrota de facción” | No hay tabla de progreso de facción; habría que usar `campaign_progress` o equivalente. |
| Todas | Sin recompensa de legendario | No hay tabla de “legendario desbloqueado/capturado” ni flag por jugador. |

**Cobertura canónica:** la propuesta cubre 8 facciones, pero “todos los villanos” incluiría también Team Star (Paldea), Team Cipher (Colosseum), Team Break (Masters), Team Dim Sun (Ranger), Macro Cosmos/Rose (Galar), etc. No están en `mejoras`. Si el objetivo es “todos”, faltan; si el objetivo es “los 8 principales”, está completo.

---

## 10. Evidencia

| Archivo | Línea/fila | Hallazgo |
|---|---|---|
| `Tareas_pdte/mejoras` | 1-18 | 8 facciones, equipos, desbloqueos; “9 combates” |
| `Tareas_pdte/mejoras` | 19-37 | Lista de legendarios y grupos |
| `Tareas_pdte/mejoras` | 38 | Regla 10 IV, 1/512 shiny, rechazo, +1 IV, +10 % acumulable |
| `Tareas_pdte/tabla legendarios.md` | 19-95 | Lista canónica de legendarios por generación |
| `Tareas_pdte/gyms.txt` | 1-18 | 18 medallas y niveles |
| `Tareas_pdte/gyms.txt` | 20-127 | Equipos de gimnasio; solapes Rocket/Skull/Yell |
| `Tareas_pdte/plan ruta.txt` | 24 | “Legendarios” está en el roadmap 0.6, no implementado |
| `storage/data/pokemon.csv` | 1-1177 (1176 datos) | Roster; 38 nombres duplicados por formas |
| `storage/data/pokemon.csv` | 32, 151, 384, 383, 488, 647, 717, 863, 1103, 850 | Nidoqueen, Mewtwo, Groudon, Kyogre, Giratina-Altered, Kyurem, Xerneas, Obstagoon, Slowbro 10165, Toxtricity-Amped |
| `storage/data/pokemon_types.csv` | 1-2116 | Tipos por `pokemon_id` |
| `storage/data/pokemon_habitat.csv` | 1 (cabecera), 341-470, 770 | Asignaciones; hábitat 49 con 100 filas, niveles 1/2/3 |
| `storage/data/habitats.csv` | 13, 48, 55 | Bonalba, Legendarios, Cueva del Eco |
| `storage/data/habitats.csv` | 1-57 | 56 hábitats; 12 sin pool |
| `src/Gimnasios/Domain/CatalogoGimnasios.php` | 27, 39, 99, 111, 123, 135, 147, 159, 171, 207, 231 | Definición de los 18 gimnasios |
| `src/Gimnasios/Domain/CatalogoGimnasios.php` | 166, 168, 180, 192, 228, 240 | Legendarios en gimnasios (lago, Mewtwo, Darkrai, Giratina, Registeel/Dialga, Rayquaza/Koraidon) |
| `database/migrations/2026_09_06_000001_add_mazmorra_to_habitats_table.php` | 17 | Columna `mazmorra` nullable; sin población |
| `src/Mazmorras/Domain/ConfiguracionMazmorra.php` | 22-40 | Lectura de `pisos[]` con `species_id`; sin config → null |
| `src/Mazmorras/README.md` | 1-40 | Mecanismo de mazmorra y recompensas |
| `src/Shared/Domain/ProbabilidadesShiny.php` | 13-14 | Salvaje 1/2048; huevo 1/512 |
| `src/Pokemon/Domain/Stats/GeneradorIvs.php` | 16-49 | IVs 0-15; shiny 15; reclutamiento con mínimo de nido |
| `src/Shared/Domain/ProbabilidadCaptura.php` | 24-28 | Base 25 %, cap 25 %, cap Senda 30 % |
| `src/CombateRuta/App/OtorgarRecompensasRuta.php` | 48 | Shiny salvaje = captura garantizada; resto probabilístico |
| `.opencode/work/TASK-043/architecture.md` | 226-237 | 8 capítulos por provincia, cierre con gimnasio/mazmorra, `Legendarios` al final |
| `.opencode/work/TASK-043/architecture.md` | 260-295 | Desbloqueo blando, válvula por nivel, abuelo |
| `.opencode/work/TASK-043/architecture.md` | 95-103 | 4 tipos de misión; sin NPCs/diálogo |

---

## Riesgos, supuestos y preguntas bloqueantes

**Riesgos**
- **[Alto] Canibalización de legendarios:** Mewtwo, Rayquaza, Dialga, Giratina y el trío del lago ya son rivales de gimnasio. Si además son recompensa de facción, el jugador los ve dos veces con reglas distintas.
- **[Alto] “9 combates” sin definición:** no hay formato para 5 Pokémon por facción en un motor 3v3; puede convertirse en 2-3 combates o en un formato nuevo no previsto.
- **[Alto] Regla de rechazo acumulable:** sin tope, permite granjear +10 % indefinidamente; sin persistencia, no se puede implementar.
- **[Medio] Hábitat 49 sin filtro:** incluye UBs y paradojas; una facción “legendaria” puede encontrarse con Nihilego o Iron Crown.
- **[Medio] 12 hábitats vacíos:** limitan el encaje geográfico y las mazmorras.
- **[Medio] Nombres duplicados:** resolver por nombre puede devolver la forma equivocada (Persian normal vs Alola; Slowbro normal vs Galar).
- **[Medio] IP:** nombres, equipos y líderes son material oficial; el proyecto ya es derivado, pero el arco narrativo aumenta la exposición.
- **[Bajo] Tono:** Skull y Yell no sostienen un arco apocalíptico sin reescritura de personaje.

**Supuestos**
- `mejoras` es una idea del desarrollador, no un contrato cerrado.
- El pipe `|` separa dos grupos de combate, no una jerarquía; no está confirmado.
- La base de datos de ejecución podría tener mazmorras configuradas manualmente; el repositorio no las reproduce.
- “9 combates” se refiere a combates por facción, no a 9 facciones.
- Las formas regionales se identifican por `id`, no por sufijo de nombre.

**Preguntas bloqueantes**
1. ¿8 facciones = 8 capítulos de TASK-043 o arco posterior a la campaña?
2. ¿Cada facción son 9 combates, 3 combates o 1 combate de líder?
3. ¿El líder pelea con 5 Pokémon en un solo combate? Si no, ¿cómo se reparten?
4. ¿Los legendarios del equipo base son equipo de líder o recompensa? No pueden ser ambas.
5. ¿Se implementa la regla de rechazo/+1 IV/+10 % o se descarta?
6. ¿Skull y Yell reciben legendario? ¿Cuál, con id verificado?
7. ¿Se mantienen los nombres localizados oficiales o se renombran para reducir IP?
8. ¿Se pueblan los 12 hábitats vacíos o se excluyen del arco?

---

## Decisiones de producto para el arquitecto (máximo 8)

1. **Alcance del arco:** ¿las 8 facciones son los 8 capítulos de TASK-043 o un arco post-campaña separado?
2. **Formato de combate:** ¿cada facción se resuelve en 1 combate de líder, 2 combates (3+2) o 3 combates? “9 combates” debe concretarse.
3. **Relación líder/legendario:** ¿el líder lleva a sus legendarios en el equipo o estos son solo la recompensa final? Resolver la duplicidad de Galaxia, Plasma y Flare.
4. **Legendarios ya usados en gimnasios:** ¿Mewtwo, Rayquaza, Dialga, Giratina y el trío del lago se excluyen del arco de facciones, se reutilizan con otro rol o se mantienen en ambos?
5. **Regla de captura legendaria:** ¿se implementa el rechazo con +1 IV y +10 % acumulable? ¿Con qué tope, persistencia y coste? ¿O se sustituye por captura garantizada sin acumulación?
6. **Hábitat 49:** ¿se mantiene como pool único de 100 (incluye UBs/paradojas) o se segmenta por facción/grupo? ¿Se le pone desbloqueo duro de TASK-043?
7. **Huecos de roster:** ¿se aceptan las alternativas (Carracosta/Drednaw por Barbaracle; Dragalge/Toxapex por Salazzle; id 10165 por Slowbro-Galar) o se prefiere asignar las especies originales a hábitats nuevos?
8. **Nombres y tono:** ¿se conservan los nombres localizados (Magno, Aquiles, Helio, Ghechis, Lysson, Guzmán, Nerio) con el riesgo IP, o se crean nombres originales para el arco? ¿Skull/Yell mantienen su tono de barrio o se elevan a amenaza?

---

## Parte B — Historias de villanos: viabilidad e integración

# TASK-044 — Historias de villanos y facciones: viabilidad e integración

## 1. Resumen ejecutivo

El juego puede hoy contar *qué* hacer (combate, gimnasio, mazmorra, exploración) pero no *quién* lo pide ni *por qué*: no existe ninguna entidad narrativa. Los entrenadores se rotulan por índice (`Entrenador 1..3`), los líderes de gimnasio por posición (`Líder`) y los jefes de mazmorra por número de piso; el único texto con autoría son las frases deterministas del diario de exploración. No hay tabla, seeder ni catálogo de NPCs, facciones o diálogos; el nombre de un Pokémon del jugador es el único nombre propio del sistema.

La propuesta de `Tareas_pdte/mejoras` (8 facciones, líderes, legendarios desbloqueables) es compatible con el motor, pero **no con su formato actual de combate**: los equipos de líder tienen 5 Pokémon y el juego solo enfrenta 3 contra 3 (o 3 contra 1 en mazmorra). Tampoco encaja tal cual en la economía de TASK-043, que prohíbe shiny garantizado y limita a una captura asegurada semanal.

Lo que ya existe y se puede reutilizar: un modal de victoria con canal de texto libre (`log`), un generador de jefes con fases y bonus de stats, creación directa de reclutados con IV y shiny, y un contenedor de mazmorra por hábitat con progresión y cooldown. Lo que falta es **identidad persistente** (catálogo o tabla), **superficie de entrega de texto** (tarjeta, dossier, post-combate) y **estado de facción/legendario por jugador**.

El mayor coste no es técnico: es **autoría de contenido en español y arte de líderes/facciones**, hoy inexistente. El segundo coste es de producto: ocho facciones simultáneas compiten con la Bruma como mitología y con gimnasios y mazmorras como escalera de jefes.

## 2. Infraestructura narrativa actual

| Sistema | Dónde se muestra el texto | Identidad | Limitación |
|---|---|---|---|
| Entrenadores | `resources/views/habitats/partials/columna-derecha.blade.php:292` (nivel) y `:308` (`'Entrenador ' + indice`) | Índice 1-3 por nivel 1-3; sin nombre | Equipo generado por semilla diaria (`GeneradorEquipoEntrenador.php:77`), no persistente |
| Gimnasios | `resources/views/gimnasios/show.blade.php:120-130` (avatar SVG genérico, `etapa.nombre`) | `Gimnasio::nombreEtapa()` devuelve `Entrenador 1/2/3/Líder` (`src/Gimnasios/Domain/Gimnasio.php:32-41`) | Sin nombre de líder ni texto; catálogo con `slug`, `medalla`, `tipo`, `nivelMinimo` y equipos (`CatalogoGimnasios.php:24-30`) |
| Mazmorras | `resources/views/habitats/partials/modal-mazmorra.blade.php` (lista de pisos) y `ObtenerPisosMazmorra.php:44` (`'Piso '.$piso`) | Jefe = nombre del Pokémon (`GeneradorJefeMazmorra.php:41`) | Sin nombre de guardián ni texto por piso; copy de UI dice «el pokémon más débil del hábitat» mientras la config usa `species_id` explícito |
| Exploraciones | `resources/views/exploraciones/_diario.blade.php` y `_evento.blade.php`; textos en `DiarioExploracion.php` y `FraseAnimo.php` | Solo `explorador_nombre` (nombre opcional del reclutado del jugador) | Es bitácora de eventos, no diálogo; frases fijas por HP/rol/categoría |
| Modal de victoria | `resources/views/livewire/combate.blade.php:124-259`; contrato en `DatosModalVictoria.php` y `ResultadoRuta::aArray()` | — | Solo recompensas: EXP, caramelos, capturas, medalla. El único texto libre es `log` (`PresentadorResultadoBatalla.php:44`) y solo lo usa mazmorra (`:229-235`) |
| Misiones | `resources/views/misiones/index.blade.php` (stub) | — | Sin backend: llama a `/misiones`, `/misiones/activa`, `/aceptar`, `/cancelar` (líneas 252, 269, 393, 427) y **no existe ninguna ruta** (`routes/web.php:36-55`). Nav comentado (`layouts/app.blade.php:54`) |

No hay capa de textos centralizada ni i18n visible: los literales viven en Blade y en DTOs. Los componentes reutilizables existentes son `x-sprite`, `x-tipo-badge` y `x-ui.{alert,button,card,input,modal}` (`resources/views/components/`), suficientes para tarjetas y dossieres sin inventar UI.

## 3. Identidad de NPCs

**Cómo se generan hoy los nombres:** no se generan. No hay tabla `npcs`, ni seeder de nombres, ni generador onomástico. Lo que hay es:

- Entrenador: `(habitat_id, nivel 1-3, índice 1-3)`; el log los identifica por esas tres claves más la fecha (`trainer_combat_log`, unique `user+habitat+level+trainer_index+fought_at`).
- Líder: la etapa 4 del gimnasio se rotula «Líder».
- Jefe de mazmorra: el `name` del Pokémon configurado en `habitats.mazmorra`.
- Explorador: el `nombre` que el jugador puso a su reclutado, si lo puso.

**Qué haría falta para dar nombre y una línea a un villano sin sistema de diálogo:**

1. Una fuente de identidad estable: catálogo en código (patrón `CatalogoGimnasios`) o tabla `factions`/`faction_npcs`. El catálogo en código es más barato y coherente con el proyecto; la tabla solo se justifica si el contenido se edita sin despliegue.
2. Un identificador estable por NPC (`faction_id`, `role`, `order`) y un campo de texto corto (`linea`, ≤140 caracteres), más un `ace_species_id` para el retrato.
3. Un punto de render: ficha de facción, tarjeta de misión y modal post-combate.

**Advertencia de diseño:** renombrar los entrenadores genéricos existentes crea identidad falsa. Su equipo cambia cada día por semilla de fecha, así que el «mismo» NPC tendría equipo distinto mañana y el jugador no podría derrotarlo dos veces el mismo día (unique del log). Un villano con nombre debe ser un **tipo de combate nuevo con equipo fijo**, no un alias del entrenador genérico. Esto coincide con TASK-043, que aplaza los «contratos de entrenador» a v2 precisamente porque exigen identidad de NPC (`architecture.md:103`).

## 4. Entrega de historia sin diálogos ramificados

| Opción | Coste | Encaje | Riesgo |
|---|---|---|---|
| **Tarjeta de misión con 1-2 líneas** | Bajo | Alto: TASK-043 ya define título + objetivo + sabor ≤140 caracteres (`architecture.md:224`) | Repetición y deriva de tono si se generan; requiere autoría por arquetipo |
| **Texto post-combate en el modal de victoria** | Bajo | Alto: el modal existe y el contrato ya admite `log` (`PresentadorResultadoBatalla.php:44`) | Compite con la recompensa; debe ser una línea, no un párrafo; el `log` se pinta como registro de batalla |
| **Notas de campo** | Bajo | Alto: `field_notes` ya está propuesta (`architecture.md:432`) y es coleccionable sin efecto económico | Poca visibilidad si no hay pestaña propia; se confunde con Pokédex |
| **Ficha de expediente por facción** | Medio | Alto con la campaña; una tarjeta por facción con emblema, líder, territorio y estado | Duplica el dossier; ocupa sitio en un nav ya saturado (6 ítems, `layouts/app.blade.php:47-56`) |
| **Dossier de facción** (pestaña o ruta propia) | Medio | Alto si agrupa ficha + notas + hitos + legendario | Solapa con `/misiones`; exige decidir si es pestaña o ruta; volumen de texto |
| **Escenas estáticas** (modal con texto + arte) | Medio-alto | Medio: bueno para apertura/cierre de arco | Interrumpe el bucle; necesita arte que no existe; contenido de un solo uso; riesgo de convertirse en «lore dumps» |

**Recomendación:** tarjeta + una línea post-combate + notas de campo; dossier como índice de facción, sin escenas estáticas en v1. No abrir un sistema de diálogo: TASK-043 fija «cero diálogos ramificados» como principio (`architecture.md:59`).

## 5. Integración con misiones de TASK-043

Los cuatro tipos de TASK-043 (encargo, parte diaria, semanal, hito) y el ledger `mission_events` + `mission_event_applications` son suficientes para sostener facciones **si las misiones de facción son autoradas, no generadas**. La generación procedural por semilla disolvería la identidad: un villano no puede pedir «derrota 7 Pokémon al azar».

| Arquetipo nuevo | Fuente de evento existente | Hueco |
|---|---|---|
| **Vencer N grunts** | `PresentadorResultadoBatalla::procesarEntrenador` + `trainer_combat_log` | Etiqueta de facción en la identidad del rival; hoy no existe |
| **Vencer al líder** | `RegistrarResultadoGimnasio` (etapa 4) o nuevo tipo de combate | No hay tipo «jefe de facción»; el `match` de `procesar()` cae en `entrenador` por defecto (`:58-63`) |
| **Infiltrar base** | `RegistrarResultadoMazmorra` + `dungeon_progress` | La base debe mapearse a un hábitat con `mazmorra`; no hay campo de propietario |
| **Sabotear** | Ninguno. Lo más cercano es `Fabricar` o completar piso | Requiere acción o evento nuevos; no hay «sabotaje» en el dominio |
| **Rescatar** | Ninguno. Podría ser un resultado de exploración | Requiere acción nueva; no hay NPC rescatable |
| **Legendario** | Ninguno. La captura existe vía `CrearReclutado` | Falta estado de encuentro, elegibilidad y unicidad por usuario |

**Dónde encaja en el modelo:** los arquetipos de grunt y líder caben como `mission_events` con `event_type` propio y `source_id` = `battleId`. Las cadenas de facción encajan mejor como **hitos de campaña** (`campaign_progress`) que como quinto tipo de misión: son secuenciales, autorados y de un solo uso, exactamente la semántica de `HitoCampania`. Añadir un quinto tipo rompe el límite de 6 tarjetas que TASK-043 considera estructural.

**Recompensas:** pagar en las monedas existentes (caramelos, objetos, EXP) y reservar el legendario como **desbloqueo de hito**, fuera del tope semanal. Si el legendario se paga como recompensa de misión, rompe el guardarraíl «ningún shiny garantizado» y «una captura asegurada por semana» (`architecture.md:319`).

**Desbloqueo:** `habitat_unlocks` puede marcar la base como hábitat desbloqueado por facción; el hábitat 49 «Legendarios» ya existe (`storage/data/habitats.csv`) y es el destino natural del epílogo.

## 6. Bases de facción y mazmorras

**Cómo se define una mazmorra hoy:** columna JSON `habitats.mazmorra` (`2026_09_06_000001_add_mazmorra_to_habitats_table.php`), parseada por `ConfiguracionMazmorra::desdeJson()` con forma `{pisos: [{piso, species_id}]}`. Progreso en `dungeon_progress` (unique `user+habitat`, `current_floor`, `completed_at`) y auditoría en `dungeon_log`. Reglas en `ReglasMazmorra.php`: jefe ×10 stats, ×7 HP, +30 % inicial, recompensa ×7 gimnasio, cooldown 1 hora solo tras derrota.

**¿Se puede reutilizar como base de facción?** Sí, para la escalera de pisos, pero con cuatro límites duros:

| Límite | Consecuencia |
|---|---|
| Un solo jefe por piso, 3 contra 1 | Un líder con equipo de 5 Pokémon no cabe; el combate de líder debe ser 3v3 (estilo entrenador/gym) o reducirse a 3 |
| Sin metadatos de propiedad | La base no se distingue de una mazmorra salvo por el nombre del hábitat |
| Sin CRUD ni seeder | `habitats.mazmorra` no se rellena en ningún seeder ni pantalla de admin; hoy solo se escribe por SQL |
| Pisos lineales y no repetibles | No hay pisos opcionales, secretos ni recompensa por piso; el botín es único y global |

Además, la UI promete «el pokémon más débil del hábitat» cuando la configuración usa `species_id` explícito: conviene corregir el copy antes de vender una base temática.

**Alternativas:** (a) reutilizar `mazmorra` con un campo de propietario y nombre de base; (b) entidad `faction_bases` que referencie un hábitat y reutilice el combate de mazmorra; (c) no crear bases y usar hábitats existentes como territorio. La opción (b) es la más limpia si las facciones sobreviven a v1; la (a) es la más barata para prototipar.

## 7. Legendarios

**Qué existe:** combate de jefe con `esJefe`, `bonusInicialStats` y fases (`DatosPokemonBatalla.php:50-56`, aplicado en `EquipoBatalla.php:222-227`); creación directa de reclutado con IV y shiny (`app/Support/CrearReclutado.php`, `DatosNuevoReclutado`); IV 0-15 con `GeneradorIvs::maximos()` a 15; shiny salvaje 1/2048 y de huevo 1/512 (`ProbabilidadesShiny.php`); captura salvaje con `ProbabilidadCaptura` (base 25 %).

**Qué falta:**

| Pieza | Estado |
|---|---|
| Flag `is_legendary` en BD | No existe en `pokemon`; solo en el CSV crudo `storage/data/pokemon_species.csv` |
| Estado de encuentro por usuario (derrotado, rechazado, capturado) | No existe |
| Captura garantizada tras derrota | No existe un flujo de captura 100 %; `CrearReclutado` permitiría crearlo |
| IV mínimo 10 | No existe suelo por especie; los rangos actuales dependen del nido (`GeneradorIvs::rangoHuevo`) |
| Shiny 1/512 en legendario | Existe la constante, pero no se aplica a este flujo |
| Rechazo → re-encuentro con +1 IV y +10 % stats acumulable | No existe; el IV tope 15 agota el margen en 5 rechazos, así que el +10 % necesita almacenamiento propio, no IVs |
| Unicidad por usuario | No hay unique de reclutado por especie; un legendario podría duplicarse |

**Contraste con `mejoras`:** el mínimo de 10 IV es coherente con la escala 0-15 (equivale a un nido nivel 3). El 1/512 coincide con la tasa de huevo, no con la salvaje (1/2048): hay que decidir explícitamente cuál se usa. «Derrotar asegura su reclutamiento» elimina la tirada de captura y es mucho más generoso que cualquier recompensa actual. El bucle de rechazo es un farmeo de poder: sin tope, el jugador convierte un legendario en un objeto de cultivo. Y Rayquaza exige derrotar Magma **y** Aqua: es una condición cruzada que `campaign_progress` no expresa sin estado adicional.

## 8. Assets

| Recurso | Disponible | Falta |
|---|---|---|
| Sprites de Pokémon | `public/images/iconos_webp/` 2.397 + `shiny/` 1.196 (incluye variantes y legendarios) | — |
| Iconos de tipo | `public/images/type/` 18 | — |
| Medallas | `public/images/medallas/` 19 (18 + 0) | — |
| Bonguris | `public/images/bonguri/` 7 | Solo 7 colores para 8 facciones |
| Misc | `public/images/misc/` 6 (`trainer`, `raid`, `farm`, `coin`, `sparkles`, `unknown`) | Retrato genérico de entrenador únicamente |
| NPCs / villanos | Nada | 8 emblemas, 8 retratos de líder, fondos de base, arte de encuentro legendario |

**¿Se puede empezar solo con iconos y texto?** Sí. La vía barata es: sprite del Pokémon insignia como retrato del líder, `x-tipo-badge` para el color de facción y un marco de color en la tarjeta. El límite es que 8 facciones no se distinguen con 7 colores de bonguri; habría que aceptar reutilización o introducir una paleta propia. Los assets actuales ya son propiedad de terceros y el repo incluye `THIRD_PARTY_NOTICES.md`; añadir nombres y emblemas de facciones aumenta la exposición, no la crea.

## 9. Riesgos de producto y técnicos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| Alta | Ocho facciones simultáneas | Dilución narrativa, elección paralizante, 8 arcos que el jugador nunca termina | 2-3 facciones en v1, una por capítulo; el resto como menciones |
| Alta | Fatiga de villanos | «Derrota 5 grunts» repetido se convierte en chore | Mezclar arquetipos, tope por arco y caducidad sin castigo |
| Alta | Volumen de texto | ~100-150 cadenas autoradas en español sin i18n | Presupuesto por facción (apertura, 5 hitos, líder, legendario) y recorte a lo esencial |
| Alta | Coherencia con la Bruma | Dos mitologías compitiendo: Bruma vs. legendarios de facción | Regla de mundo: las facciones son anteriores o explotan la Bruma; el legendario es consecuencia, no causa |
| Alta | Canibalización de gimnasios y mazmorras | Tres escaleras de jefes con la misma recompensa | Diferenciar rol: gimnasio = medalla, mazmorra = botín, facción = historia/legendario |
| Alta | Recompensa legendaria | Power creep y farmeo por rechazo | Unicidad por usuario, tope de rechazos, captura fuera del tope semanal |
| Media | Identidad efímera de entrenadores | Nombrar un NPC cuyo equipo cambia a diario rompe la ficción | No renombrar genéricos; crear tipo de combate de facción con equipo fijo |
| Media | Enrutado de combate | `procesar()` cae en `entrenador` para cualquier tipo desconocido (`:58-63`) | Añadir el tipo nuevo explícitamente y probar recompensas |
| Media | Equipos de 5 Pokémon | `mejoras` define líderes con 5; el motor usa 3 | Recortar a 3 o diseñar oleadas |
| Media | Autoría de mazmorras | Sin seeder ni admin para `habitats.mazmorra` | Seed de bases o CRUD mínimo antes de vender la función |
| Baja | Propiedad intelectual | Nombres y emblemas de facciones de TPC | Mantener aviso de terceros y evitar logos oficiales; usar emblemas propios |

## 10. Evidencia

- `.opencode/work/TASK-043/architecture.md:24-30` (tipos y MVP), `:59` (narrativa mínima), `:103` (contratos de entrenador aplazados por falta de identidad), `:140-167` (arquetipos y `EventoJuego`), `:224` (mínimo texto), `:319` (sin shiny garantizado), `:423-433` (tablas), `:451-458` (puntos de emisión).
- `.opencode/work/TASK-043/plan.md:20-26` (seis arquetipos del MVP).
- `Tareas_pdte/mejoras:1-38` (8 facciones, equipos de 5, legendarios, 10 IV, 1/512, rechazo +1 IV y +10 %).
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php:44-68` (contrato y `match`), `:75-109`, `:116-172`, `:179-238` (`log` de mazmorra), `:246-286`.
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php:77` (semilla diaria), `ObtenerEntrenadoresHabitat.php` (3×3 sin nombre).
- `src/Gimnasios/Domain/Gimnasio.php:32-41`; `CatalogoGimnasios.php:24-30`; `resources/views/gimnasios/show.blade.php:120-130`.
- `src/Mazmorras/Domain/ReglasMazmorra.php`; `ConfiguracionMazmorra.php:22-56`; `ObtenerPisosMazmorra.php:44`; `GeneradorJefeMazmorra.php:41-88`; `resources/views/habitats/partials/modal-mazmorra.blade.php`.
- `src/Exploraciones/Presentation/DiarioExploracion.php`, `FraseAnimo.php`; `resources/views/exploraciones/_diario.blade.php`, `_evento.blade.php`.
- `resources/views/livewire/combate.blade.php:124-259`; `resources/views/habitats/partials/columna-derecha.blade.php:292,308`; `resources/views/layouts/app.blade.php:47-56`.
- `resources/views/misiones/index.blade.php:252,269,393,427`; `routes/web.php:36-55`.
- `src/Shared/Domain/ProbabilidadesShiny.php`; `src/Pokemon/Domain/Stats/GeneradorIvs.php`; `src/Shared/Domain/PoliticaIvsEvs.php`; `app/Support/CrearReclutado.php`; `src/Reclutamiento/Domain/DataTransferObjects/DatosNuevoReclutado.php`.
- `database/migrations/2026_09_06_000001_add_mazmorra_to_habitats_table.php`, `2026_09_06_000002_create_dungeon_tables.php`, `2026_08_31_000001_create_trainer_combat_log_table.php`, `2026_09_01_000001_create_gym_progress_table.php`.
- `storage/data/habitats.csv` (56 hábitats, 8 provincias, hábitat 49 «Legendarios»); `database/seeders/ProvinceSeeder.php`.
- `public/images/` (conteos por directorio); `resources/views/components/`.

## Decisiones técnicas para el arquitecto

1. **Identidad:** ¿catálogo en código o tabla `factions`/`faction_npcs`? ¿Se renombran los entrenadores genéricos o solo los NPCs de facción?
2. **Tipo de misión:** ¿las facciones son un quinto tipo autorado o una cadena de hitos de campaña con repetibles de grunt?
3. **Base:** ¿se reutiliza `habitats.mazmorra` con metadatos de propietario o se crea `faction_bases`?
4. **Líder:** ¿combate 3v3 estilo gimnasio o jefe 3v1 estilo mazmorra? ¿Se recortan los equipos de 5 de `mejoras` a 3?
5. **Legendario:** contrato de encuentro por usuario, unicidad, captura 100 % y tope de rechazos.
6. **Estadísticas:** dónde viven el IV mínimo 10 y el +10 % acumulable (columnas nuevas frente a cálculo en batalla).
7. **Shiny:** ¿1/512 fijo en legendario? ¿Cómo se concilia con «ningún shiny garantizado» de TASK-043?
8. **Alcance v1:** cuántas facciones, en qué capítulos, y superficie de entrega (tarjeta + post-combate + dossier en `/misiones` o ruta propia).