# TASK-050 — Petición

- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** ajuste de UI (Blade + Tailwind + Alpine), sin backend salvo que el análisis lo exija.
- **Estado:** ANALYZING

## Texto literal

> Entrenador 1, Entrenador 2... Deberia ser columnas en lugar de filas, dejando el lider siempre a la derecha
> Modal Configurar Formación mas ancho
> En equipos, estos deben tener la posibilidad de colapsarse para ver todos tus equipos facilmente, en el header se mostrara un icono del pokemon para facilitar su visibilidad, desplegarlo lo ves entero y puedes editarlo, las Composición se eliminan, y en su lugar ponemos icono editar, donde mostraremos para formacion, ataque, objeto... vanguardia retaguardia...

## Contexto conocido

- El listado de etapas de gimnasio muestra hoy `Entrenador 1`, `Entrenador 2`,
  `Entrenador 3` y `Líder` como tarjetas apiladas en vertical
  (`resources/views/gimnasios/show.blade.php`, bloque "Etapas").
- El modal "Configurar Formación" de gimnasios sigue en `max-w-md`; el de hábitats
  ya se amplió a `max-w-6xl` en TASK-049 (cambio local sin commitear).
- En `/equipos`, las tarjetas de equipo muestran siempre la composición
  (`composicionBadge`) y todos los controles de formación, postura y objeto de cada
  slot, sin posibilidad de colapsar la tarjeta.
- Los equipos tienen slots 1-3, `formacion` (vanguardia/retaguardia), `posturas`
  (atacar/guardia/cubrir) y objeto equipado por slot.

## Interpretación de trabajo (a confirmar)

1. **Gimnasios — etapas en columnas:** pasar las etapas de filas a columnas,
   manteniendo el orden 1 → 2 → 3 → Líder, con el líder siempre en la columna de
   la derecha en escritorio.
2. **Modal de formación más ancho:** ampliar el modal de "Configurar Formación"
   de gimnasios (pendiente de confirmar si también se toca el de hábitats).
3. **Equipos colapsables:**
   - tarjetas de equipo plegables para ver la lista completa de un vistazo;
   - icono de Pokémon en el header para identificar el equipo;
   - al desplegar se ve el equipo completo y se puede editar;
   - eliminar la badge de "Composición";
   - sustituirla por un icono de editar que muestre/gestione formación, postura,
     objeto, vanguardia/retaguardia.

## Decisiones del desarrollador (2026-11-17)

- **Modal a ampliar:** solo el de gimnasios. El de hábitats se queda como está
  (TASK-049, `max-w-6xl`).
- **Icono del header del equipo colapsado:** los 3 miembros en miniatura.
- **Colapso:** todas las tarjetas colapsadas por defecto; se pueden abrir varias a
  la vez.
- **Icono editar:** abre un modal de edición del equipo con formación, postura y
  objeto por slot (vanguardia/retaguardia incluida).
- **Ancho de la ficha de gimnasio:** ampliar el contenedor a `max-w-6xl` para dar aire
  a las 4 columnas.
- **Leyenda IV/EV del modal de gimnasio:** colapsable y con 3 columnas (patrón TASK-049).
- **Guardado del modal de edición:** un único botón "Guardar cambios" con los 3 PATCH
  en secuencia.
- **Cuerpo de la tarjeta desplegada:** sprites + nombre + resumen read-only de
  formación/postura/objeto.

## Ampliación v2 (2026-11-17)

### Texto literal

> En todos los configurar formacion añadir padding a la tabla de perfiles rivales
> En el equipo debera mostrarse los iconos de los integrantes y desplegar debe mostrar el layout de vanguardia/retaguardia como en el combate en 2 columnas

### Interpretación

1. **Padding en tablas rivales:** añadir separación interna a las tablas de perfiles
   IV/EV y de desglose por Pokémon del partial compartido
   `resources/views/partials/leyenda-ivs-evs.blade.php`, que usan los modales de
   formación de hábitats y gimnasios.
2. **Equipo en 2 columnas:** el cuerpo desplegable de la tarjeta de equipo muestra dos
   columnas, Vanguardia y Retaguardia, con los iconos de los integrantes y su resumen de
   postura/objeto. La cabecera conserva las 3 miniaturas y el modal de edición sigue
   siendo el único punto de edición.

### Decisión adicional

- La auditoría de seguridad se omite por instrucción explícita del desarrollador:
  `Salta security`.

## Restricciones

- No implementar antes de la aprobación explícita del plan.
- No crear, modificar ni ejecutar tests.
- No tocar `tests/`.
- No leer `.env` ni secretos.
- Respetar los cambios locales de TASK-049 sin sobrescribirlos.
- No cambiar backend, contratos ni datos sin que el análisis lo justifique y el plan lo autorice.
- Sin dependencias nuevas.
