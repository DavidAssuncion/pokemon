# TASK-037 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING
- **Relacionada con:** TASK-035 (comparador de PC en Configurar Formación).

## Texto literal

> Añade tambien a la interfaz web los stats, iv / ev maximos en la mayor stat, todo a 0, todo maximos... Para que el usuario lo tenga en cuenta

## Interpretación inicial

Mostrar en la interfaz web información de stats e IV/EV que ayude a interpretar la comparación de PC y el poder real de los Pokémon:

- Stats (base y/o calculados al nivel de combate).
- IV/EV reales del equipo del jugador.
- Perfiles de IV/EV usados por los rivales: todo a 0, máximos en la stat principal, todos máximos, etc.
- Probablemente como desglose/leyenda junto al comparador de PC en el modal de Configurar Formación, o en la pantalla de equipos.

## Contexto conocido

- Proyecto: `/home/david/pokemon` (Laravel 12 + Blade + Alpine; dominio en `src/`).
- TASK-035 ya implementó el comparador de PC en ruta, entrenadores y gimnasios, con partial `resources/views/partials/comparacion-pc.blade.php` y mixin `comparacion-pc-script.blade.php`.
- El CP se calcula en backend reutilizando `CalculadorCP` + `BattleStats`; el frontend solo presenta.
- Los perfiles de IV/EV viven en `src/Shared/Domain/PoliticaIvsEvs.php` y `PerfilIvsEvs`:
  - Ruta: nivel 1 → Nulo; nivel 2 → TopDos; nivel 3 → Completo.
  - Entrenador: nivel 1 → TopUno; nivel 2 → TopDos; nivel 3 → Completo.
  - Gimnasio: según índice y líder.
- `ReclutadoSerializer` ya expone `stats_calculados` y `cp` en `/equipos`.
- La pantalla `/equipos` ya muestra stats/IV/EV de los reclutados en algún grado; hay que verificar qué falta y qué duplicaría.

## Decisiones de producto acordadas

1. **Ubicación:** modal "Configurar Formación" (hábitats y gimnasios), junto al comparador de PC.
2. **Contenido:** leyenda de perfiles IV/EV con estadísticas reales del rival, sin desglose por miembro de nuestro equipo.
3. **Datos del rival:** el backend debe usar el **equipo exacto contra el que se va a pelear** y mostrar stats anónimos por Pokémon (stats base, IV/EV y stats calculados), sin nombres, especies, tipos ni movimientos.
4. **Ruta 3v3:** el rival se **fija al abrir el modal** y se guarda en servidor; el combate debe reutilizar exactamente ese rival.
5. **Visibilidad:** la leyenda debe estar **siempre visible** dentro del modal, no oculta tras un desplegable.
6. **Perfiles:** explicar todo a 0, máximo en la stat principal, máximo en las dos principales y todos máximos; incluir el perfil que aplica al combate actual.
7. **Stats:** mostrar stats base y calculados al nivel de combate.
8. No cambiar fórmulas, balance, IV/EV ni esquema.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No modificar archivos en esta fase de análisis.
- No añadir dependencias.
- No cambiar fórmulas, balance, IV/EV ni esquema sin aprobación.
- Respetar el patrón Blade/Alpine existente y la accesibilidad.
- No exponer datos del rival más allá de lo ya aprobado en TASK-035 (agregados, sin composición).
- No tocar cambios concurrentes de otras tareas.

## Preguntas para el análisis

1. ¿Qué información de stats/IV/EV ya está disponible en `/equipos` y en el modal, y qué falta?
2. ¿Qué perfiles de IV/EV existen exactamente y cómo se nombran/representan para el jugador?
3. ¿Conviene un contrato backend aditivo (stats/IV/EV del equipo) o reutilizar `stats_calculados` y los datos ya enviados?
4. ¿Dónde encaja mejor la leyenda/desglose sin sobrecargar el modal?
5. ¿Cómo mostrar "todo a 0", "máximo en la mayor stat" y "todos máximos" de forma comprensible y accesible?
6. ¿Afecta al PC mostrado o es solo informativo?
7. ¿Qué archivos habría que tocar y qué verificación estática/manual se propone?
