# TASK-056 — Petición

- **ID:** TASK-056
- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon` (Laravel 12, PHP 8.4, módulos DDD en `src/`, Blade + Alpine + Tailwind 4).
- **Tipo:** funcionalidad nueva de producto (equipamiento fabricable por familia/nido). Requiere análisis y diseño antes de implementar.
- **Estado:** RECEIVED → CLARIFYING.

## Texto literal

> En la forja debemos añadir una fila para los pokemon de los que tengamos nidos construidos en los biomas. Podremos construir sus Armas/Armaduras/Amuleto
> Requiere que tengas el nido a cierto nivel, 1-3-5 debloquea los articulos buenos
> 2-4 para los que no tengan 3 evos, y 3 para los que no tengan evolucion
>
> Armas requieren ataque/spA/spd
> Armadura requieren def/spD/hp
> + Los bonguiris segun la zona Cada pokemon tendra un set, que intentara replicar sus stats y habilidades
> Arma gengar 130 atksp + 65 atk
> Armadura gengar 60 def 75 defsp + 60hp
> Amuleto gengar 110spd + 75 atksp (50% del mayor fisico/especial ataque o defensa) 1 gastly, 3 haunter, 5 gengar, 2 slowpoke, 4 slowbro, 3 pinsir, como ejemplo de distribucion de los objetos. Arma gastly se usara para la fabricacion de Arma Haunter, subira nivel y sera equipable para darle extra de stats y algunos podran dar habilidades a los pokemons, se podran equipar hasta 3 objetos, pudiendo ser 3 del mismo tipo y de diferente pokemon, arma gengar, arma alakazam...

## Decisiones aclaradas por el desarrollador (2026-11-17)

1. **Enfoque:** análisis + diseño primero. No se implementa nada hasta que exista un plan
   aprobado explícitamente.
2. **Nidos:** «1 nido por bioma». Cada familia puede tener un nido por hábitat/bioma; el
   desbloqueo se vincula al nido del bioma correspondiente. Queda por concretar en diseño si la
   Forja muestra una fila por nido/bioma o una sola fila por familia.
3. **Amuleto:** Velocidad + 50 % del mayor entre Atk/SpA/Def/SpD, excluyendo Velocidad y HP. Para
   Gengar: 110 Vel + 65 (el 75 del ejemplo no cuadra con la regla; se tratará como errata).
4. **Bonguris:** cada hábitat tiene un bonguri. El bonguri de la zona es el que se usa y/o aporta el
   bono al objeto.
5. **Progresión:** una pieza única que evoluciona y sube de nivel consumiendo la anterior.
6. **Equipamiento:** 3 slots por Pokémon reclutado, persistentes y aplicados en todos los modos de
   combate.
7. **Habilidades:** las piezas pueden otorgar una habilidad pasiva extra acumulable.
8. **Sin tests:** no se crean, modifican ni ejecutan tests en este flujo.

## Contexto conocido (sin análisis)

- La Forja existe en `src/Forja/` y `resources/views/forja/index.blade.php`: agrupa recetas por
  categorías (pociones, mejoras, reductores, resistencias, materiales), las sirve por
  `GET /api/forja/recetas` y fabrica contra el inventario del jugador por `POST /api/forja/fabricar`.
- Las granjas (`src/Granjas/`, tablas `farms`, `nests`, `shelters`, `complements`) tienen un nido
  por `user_id + habitat_id + evolution_chain_id`, con nivel 0–5. Los hábitats son las zonas del
  mapa; se asume que «biomas» se refiere a los hábitats actuales (confirmar si no).
- Los ítems actuales viven en `Src\Shared\Domain\Items\CatalogoItems` y `CategoriaItem`; no existe
  categoría de arma, armadura ni amuleto, ni equipamiento persistente por Pokémon. El `loadout`
  actual es de movimientos/habilidad, no de objetos.
- El motor de batalla aplica objetos equipados mediante efectos Strategy (`FabricaEfectos`,
  `ManejadorObjetosEquipados`), pero no existe un sistema de slots de equipamiento por Pokémon.
- El catálogo de recetas es estático (`CatalogoRecetas`) y el inventario se consulta por claves
  `categoria:slug`.

## Interpretación preliminar (sujeta a confirmación)

1. La «fila» es una nueva sección/categoría de la Forja dedicada a las familias con nido construido
   (nivel ≥ 1) en algún hábitat del jugador.
2. Cada especie de la familia tiene su juego de Arma/Armadura/Amuleto; el nivel del nido desbloquea
   la pieza de esa especie:
   - cadena de 3 etapas: niveles 1, 3 y 5;
   - cadena de 2 etapas: niveles 2 y 4;
   - sin evolución: nivel 3.
3. Las piezas se fabrican con materiales/bonguris y la pieza de la etapa anterior puede ser
   ingrediente de la siguiente.
4. Cada Pokémon puede equipar hasta 3 piezas (mismo tipo o tipos distintos, de especies distintas)
   y las piezas aportan stats y, algunas, habilidades.

## Preguntas abiertas para análisis y diseño

- ¿La Forja muestra una fila por nido/bioma o una sola fila por familia cuando hay varios nidos?
- ¿Cómo sube de nivel la pieza, cuántos niveles tiene y qué consume en cada uno? ¿La evolución de la
  pieza depende del nivel del nido o solo de consumir la pieza anterior?
- ¿Se pueden fabricar copias múltiples de la misma pieza? ¿Se transfieren entre reclutados? ¿Qué
  ocurre al evolucionar el Pokémon que la lleva?
- ¿Los stats de la pieza son exactamente los base de la especie? ¿Escalan con el nivel de la pieza?
- ¿Qué ingredientes y cantidades acompañan al bonguri del hábitat? ¿El nivel del nido es solo un
  gate o también un coste?
- ¿Qué habilidad otorga una pieza si la especie tiene varias? ¿Se acumulan las habilidades de los 3
  objetos?
- ¿Cómo convive el equipamiento con los objetos de combate existentes (`life_orb`, `leftovers`) y
  con el límite de 3 objetos?
- ¿«Armas requieren ataque/spA/spd» significa que el arma usa Atk + SpA, o también Speed? El
  ejemplo de Gengar no incluye Speed en el arma.
- ¿La sección de Forja debe mostrar familias con nido nivel 0 o solo con nido construido (nivel ≥ 1)?

## Fuera de alcance provisional

- Implementar sin plan aprobado.
- Tests (política del proyecto: no se crean, modifican ni ejecutan en el flujo).
- Tocar los cambios locales preexistentes de otras tareas.

## Artefactos

Se crean los 10 artefactos de la plantilla. `analysis.md` y `architecture.md` se completarán con el
resultado de `analyst` y `architect`; no se rellenan con análisis propio del orchestrator.
