# TASK-011 — Módulo Forja+Granja: bonguris, bayas, fabricación y uso en combate

- **Fecha de recepción:** 2026-11-04
- **Estado:** `ANALYZING`
- **Proyecto:** `/home/david/pokemon` (Laravel 12 / PHP 8.4, rama `master`)
- **Solicitado por:** desarrollador
- **Origen:** sección «Modulo Forja+Granja» de `MVP.txt`, ampliada en la conversación.

## Petición literal

> Analicemos esta tarea Las exploraciones daran bonguiris+bayas
> Esta bayas se podran equipar o consumir durante un combate. En turno de un aliado puedo darle un objeto a cualquier otro aliado o usarlo contra el enemigo
>
> Aranja x3 -> poción
> Zidra -> poción
> poción x2 = super poción
> Superpoción x3 = hiperpocion
> Hiperpoción x2 = pocion maxima
> pocion maxima + curatotal = restaurar todo
> Zreza + Meloc + Safre + Perasi = curatotal
> Curatotal+super poción=revivir
> revirir+restaurar todo = max revivir
>
> Las siguientes bayas no seran usables en batalla, solo sus fabricaciones
> Cualquier bonguiri -> Recipiente
> Baya de Stats + Recipiente
> Algama		Reduce ataque
> Ispero		Reduce defensa
> Meluce		Reduce ataque especial
> Uvav		Reduce defensa especial
> Tamate		Reduce velocidad
> -
> Baya de stat + Recipiente, aumenta stat 30% 5 turnos, sirve para crear: Ataque X, Defensa X, Ataque Especial X, Defensa Especial X, Velocidad X, Precisión X, Crítico X
> Baya Lichi		Aumenta el ataque
> Baya Gonlan		Aumenta la defensa
> Baya Aslac		Aumenta la velocidad
> Baya Yapati		Aumenta el ataque especial
> Baya Aricoc		Aumenta la defensa especial
> Baya Zonlan		Aumenta el índice de golpe crítico (+1 nivel)
> Baya Lagro		Aumenta el precision y evasion +1 nivel
> -
> Baya de Tipos + Recipiente, reduce mi daño recibido 30% 5 turnos
> Baya Caoca		Fuego
> Baya Pasio		Agua
> Baya Gualot		Eléctrico
> Baya Tamar		Planta
> Baya Rimoya		Hielo
> Baya Pomaro		Lucha
> Baya Kebia		Veneno
> Baya Acardo		Tierra
> Baya Kouba		Volador
> Baya Payapa		Psíquico
> Baya Yecana		Bicho
> Baya Alcho		Roca
> Baya Drasi		Fantasma
> Baya Anjiro		Dragón
> Baya Dillo		Siniestro
> Baya Baribá		Acero
> Baya Chilan		Normal
> Baya Hibis		Hada

> «Analiza, luego buscamos los iconos para los objetos»

## Interpretación operativa inicial

La petición define un sistema de objetos en dos capas:

1. **Obtención:** las exploraciones entregan bonguris y bayas.
2. **Uso:**
   - Las bayas pueden **equiparse** o **consumirse** durante un combate.
   - En el turno de un aliado se puede **dar un objeto a cualquier otro aliado** o **usarlo contra el enemigo**.
3. **Fabricación (Forja/Granja):** los bonguris se transforman en recipientes; los recipientes se combinan con bayas para crear objetos de combate (pociones, restauradores, revivir, potenciadores de stats, reductores de daño por tipo).
4. **Bayas no usables directamente en batalla:** las de stats (reductoras y potenciadoras) y las de tipos solo sirven como materiales de fabricación.
5. **Iconos:** fase posterior; el usuario pide localizar los iconos de los objetos después del análisis.

## Contexto mínimo conocido

- Proyecto Laravel 12 con módulos DDD en `src/` (`Battle`, `Exploraciones`, `Granjas`, `Equipos`, etc.) y capa Laravel en `app/`.
- Existe un motor de combate con objetos equipados: `src/Battle/Domain/Catalog/CatalogoObjetos.php`, `ClaveItem.php`, `EstadoObjeto.php`, `ManejadorObjetosEquipados.php` y efectos como `EfectoBayaSitrus` y `EfectoBayaLum`.
- Existe inventario unificado `player_inventory` (`app/Models/PlayerInventory.php`) con claves como `familia:{chain}`, `ev:{stat}` y `tipo:{slug}`.
- Existe módulo `Granjas` con nidos, refugios, complementos y caramelos; no está confirmado que exista un sistema de recetas o forja.
- Existe módulo `Exploraciones` con cálculo y persistencia de recompensas; hay un `TODO(futuro): bayas` en `app/Models/ExploracionActiva.php` y comentarios «sin bayas» en rutas/controladores.
- Existen assets de bayas y bonguris:
  - `public/images/berry/`: 65 `.webp` (nombres ingleses, p. ej. `oran-berry.webp`, `sitrus-berry.webp`, `liechi-berry.webp`, `occa-berry.webp`).
  - `public/images/bonguri/`: 7 PNG (amarillo, azul, blanco, negro, rojo, rosa, verde).
- El combate se juega desde `app/Livewire/Combate.php` y vistas `resources/views/livewire/combate*`.
- `MVP.txt` ya contiene esta especificación y marca «Items equipables: Postergado».

## Restricciones conocidas

- Esta fase es **solo análisis**: no se modifica código, migraciones, vistas, configuración ni documentación de proyecto.
- No se crean, actualizan, reparan ni ejecutan tests; `tests/` queda fuera de alcance.
- No se leen ni transmiten `.env`, credenciales, tokens ni secretos.
- No se instalan ni actualizan dependencias.
- No se implementa nada hasta que exista un plan aprobado explícitamente.
- Respetar `.ai/rules/` y las convenciones DDD del proyecto.
- Los cambios locales preexistentes (`.githooks/pre-commit`, `MVP.txt`) son ajenos a esta tarea y no se tocan.

## Preguntas que debe resolver el análisis

1. **Estado actual del inventario y objetos:** ¿cómo se representan los objetos del jugador, los equipados y los consumibles? ¿Qué claves y catálogos existen? ¿Qué falta para soportar las bayas y bonguris de la especificación?
2. **Combate:** ¿cómo funciona el turno, la selección de objetivo y las acciones? ¿Existe ya una acción de «usar objeto»? ¿Se puede actuar sobre aliados y enemigos? ¿Cómo se gestionan objetos equipados, cargas y consumo?
3. **Exploraciones:** ¿dónde se calculan y persisten las recompensas? ¿Cómo se añadirían bayas y bonguris sin romper el contrato actual? ¿Qué tipos/cantidades y con qué probabilidad?
4. **Forja/Granja:** ¿qué existe realmente del módulo Granjas? ¿Hay recetas, cola de fabricación, consumo de materiales o alguna base reutilizable? ¿Dónde encajaría la forja?
5. **Modelo de datos:** ¿hace falta un catálogo de objetos, una tabla de inventario por categoría, una tabla de recetas, una tabla de equipamiento por Pokémon? ¿O basta con extender `player_inventory` y `ClaveItem`?
6. **Reglas de la especificación:** contradicciones y ambigüedades:
   - «Baya de Stats + Recipiente» aparece asociada a bayas que reducen stats y a bayas que aumentan stats. ¿Son dos familias distintas? ¿Los objetos «Ataque X» etc. aumentan un 30 % durante 5 turnos?
   - ¿Las bayas de stats y de tipos son solo materiales, o también pueden equiparse?
   - ¿«Usarlo contra el enemigo» significa aplicar el efecto del objeto al enemigo (p. ej. baya que reduce stats) o lanzar un objeto de daño?
   - ¿«En turno de un aliado» implica que cada Pokémon aliado tiene una acción por turno, o que el entrenador usa el turno de un Pokémon para dar el objeto?
   - ¿«Equipar» y «consumir» son excluyentes? ¿Cuántos objetos equipados por Pokémon? ¿El objeto equipado se consume al activarse?
   - ¿Las pociones y revivires son objetos de inventario que se usan desde el menú de combate? ¿«Restaurar todo» y «máx revivir» restauran HP, estados y PP?
   - ¿La fabricación es instantánea o por turnos/tiempo? ¿Dónde se realiza (menú Forja, Granja, ambos)?
7. **Iconos:** inventariar los assets existentes y mapear los nombres españoles de la especificación a los archivos ingleses; señalar los que faltan. No implementar todavía.
8. **Alcance:** separar lo que puede ser MVP de lo diferido (equipables, forja completa, animaciones, iconos) y enumerar las decisiones que necesita el desarrollador.

## Criterios de aceptación del análisis

- Identifica con evidencia (`archivo:línea`) el estado actual de inventario, combate, exploraciones y granja.
- Distingue claramente entre lo que ya existe, lo que falta y lo que propone.
- Propone un modelo de datos y un flujo de combate compatibles con la arquitectura DDD existente.
- Enumera recetas y efectos de la especificación, incluyendo las ambigüedades.
- Incluye inventario de assets e identifica huecos para la fase de iconos.
- Formula preguntas concretas al desarrollador y una propuesta de fases.
- No modifica archivos ni ejecuta tests.

## Ampliación de requisitos y decisiones (2026-11-04)

Respuesta literal del desarrollador a las decisiones planteadas:

> 1. Correcto, solo puede equipar 1
> 2. Usar un objeto consume la acción del Pokémon aliado
> 3. solo debuff
> 4. Si
> 5. Refactor exploracion para botin, forja/creacion -> uso combate
> Cada habitat da un unico tipo de bonguiri, en las exploraciones se podra configurar el comportamiento, puedes querer pelear para experiencia, buscar para reclutar, o explorar para tener items. Mandar un pokemon del habitat a explorar su habitat trae mas y mejores recompensas, porque se conoce el habitat y los mejores lugares
>
> en los nidos pueden seleccionar que bayas plantar y nuestro explorador recolector ira a cosecharlas
> Cada nivel desbloquea 2 huecos, pueden repetirse, tardan 1 dia en crecer, y el maximo acumulable son 3 cosechas. Plantar bayas en los nidos hace que sea mas probable encontrar bayas salvajes de ese tipo
>
> Posteriormente en los combates podremos recibir tambien algun bonguiri o baya del lugar

### Decisiones registradas

| ID | Decisión | Consecuencia |
|----|----------|--------------|
| D-01 | Solo se equipa **1 objeto por Pokémon**. | Se mantiene `reclutados.obj_equipados` como lista de una clave. |
| D-02 | Usar un objeto **consume la acción del Pokémon aliado** en ese turno. | No hay acción extra de entrenador; el aliado renuncia a su movimiento. |
| D-03 | Los objetos usados **contra el enemigo solo aplican debuff**. | No hay objetos de daño directo en el alcance actual. |
| D-04 | **Equipar descuenta inventario** y exige propiedad del objeto. | Hay que validar y descontar en el caso de uso de equipamiento. |
| D-05 | Orden de implementación: **refactor de exploración para botín → forja/creación → uso en combate**. | Las fases se planificarán en ese orden; los iconos van después. |
| D-06 | **Bonguri por hábitat** con mapping provincial: Galicia → blanco, Euskadi → amarillo, Barcelona → negro, Extremadura → verde, Nova Alacant → rosa, Mutxamel → azul, Andalucía → rojo; “Vacío” sin color, con fallback. | Se implementa `habitats.bonguri` con backfill desde `province_id`. |
| D-07 | **Sin tope duro por expedición**: el botín se regula con pesos, probabilidades y constantes ajustables. | Se eliminan los topes fijos de 3 bayas + 2 bonguris del plan v1; el balance se ajusta por configuración. |

### Nuevos requisitos funcionales

1. **Bonguri por hábitat:** cada hábitat produce un único tipo de bonguri. La exploración en ese hábitat debe devolver ese bonguri.
2. **Modos de exploración configurables:** al enviar a explorar se elige comportamiento:
   - pelear para obtener experiencia;
   - buscar para reclutar;
   - explorar para obtener objetos.
3. **Bonus por conocimiento del hábitat:** enviar a un Pokémon del hábitat a explorar su propio hábitat produce más y mejores recompensas, porque conoce el terreno y los mejores lugares.
4. **Plantación de bayas en nidos:** en los nidos se elige qué bayas plantar; un explorador recolector irá a cosecharlas.
5. **Reglas de los nidos:**
   - cada nivel del nido desbloquea **2 huecos**;
   - los huecos pueden repetir el mismo tipo de baya;
   - cada plantación tarda **1 día** en crecer;
   - se acumulan como máximo **3 cosechas** por hueco;
   - plantar bayas en los nidos aumenta la probabilidad de encontrar bayas salvajes de ese tipo.
6. **Botín en combates (futuro):** posteriormente, los combates podrán entregar algún bonguri o baya del lugar. Queda fuera del alcance inmediato, pero debe preverse el punto de extensión.

### Preguntas nuevas que debe resolver el análisis complementario

1. ¿Cómo se modela el modo de exploración (`experiencia`, `reclutamiento`, `botin`) y cómo cambia las probabilidades de eventos, recompensas y capturas?
2. ¿Qué significa exactamente «Pokémon del hábitat»: especie nativa del pool, capturado en ese hábitat o un campo de origen? ¿Cómo se calcula el bonus?
3. ¿Cómo se modelan los huecos de plantación de cada nido, su crecimiento diario y las 3 cosechas acumulables? ¿Quién cosecha y cuándo?
4. ¿Cómo se asigna un bonguri a cada hábitat y cómo se refleja en el botín?
5. ¿Cómo influye la plantación en la probabilidad de bayas salvajes: solo en el mismo hábitat, con qué fórmula y con qué límite?
6. ¿Qué parte del botín de exploración se calcula al finalizar y qué parte se genera por evento?
7. ¿Cómo encajan los nuevos modos y el botín con la UI actual de exploraciones y granjas?
8. ¿Qué se difiere exactamente a la fase posterior de botín en combate?
