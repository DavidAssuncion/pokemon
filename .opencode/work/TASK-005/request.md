# TASK-005 — Diario de exploraciones: imágenes, tipo desconocido, nombre y explicación de derrota

## Petición literal

> En el diario, Caramelos de Pokémon ×1, poner la imagen como en emboscada, Caramelo de tipo Desconocido ×1 no debe ponerse, "Sin reclutado repele el ataque" reberia poner el nombre a todo caso. Expliquemos el porque de la derota, mostremos los PV que tenia cuando enfrento

## Interpretación operativa (a validar por `analyst`)

1. **Caramelos de familia en el diario**: la entrada «Caramelos de Pokémon ×1» debe mostrar una imagen reconocible, con el mismo lenguaje visual que la entrada de emboscada, y no el literal genérico «Pokémon».
2. **Caramelo de tipo**: una entrada «Caramelo de tipo Desconocido ×1» no debe renderizarse. Si el tipo se puede resolver (`tipo_id` → `TipoEnum`/`SlugTipo`), debe mostrarse el tipo real y su imagen; si no, se omite el evento.
3. **Nombre del explorador**: «Sin reclutado repele el ataque» debe pasar a mostrar el nombre real en todos los casos. Si el reclutado no tiene nombre propio, usar el nombre de su Pokémon; nunca inyectar «Sin reclutado» en la narrativa del diario.
4. **Derrota explicada**: el diario debe explicar por qué cayó el explorador y mostrar los PV con los que entró al combate (y, si es posible, los del rival), en lugar de un motivo técnico como «explorador_debilitado».

## Contexto

- Proyecto `/home/david/pokemon`, Laravel 12 / PHP 8.4, rama `master`, WIP local extenso.
- Módulo `src/Exploraciones` con Fase 1 cerrada en TASK-003.
- No hay que tocar migraciones, dependencias ni la economía del módulo.
- El diario se compone en `DiarioExploracion` + `EventoExploracionVista` y se pinta en `_evento.blade.php`.
- El evento de combate se persiste en la bitácora de `ExploracionActiva::eventos`.

## Restricciones conocidas

- No commit ni `git push`.
- No revertir WIP ajeno.
- No cambiar constantes de balance ni reglas de combate.
- No introducir migraciones ni dependencias.
- Mantener compatibilidad con eventos históricos ya persistidos (sin `hp_inicial` ni `tipo_id` resuelto).
- Los tests se ejecutan al cierre del módulo según la convención del proyecto.

## Criterios de aceptación preliminares

- La entrada de caramelo de familia muestra imagen y nombre concreto (o un fallback visual claro).
- `caramelo_tipo` con `tipo_id` resoluble muestra tipo e imagen correctos; sin resolución, no aparece.
- Ninguna narrativa del diario contiene «Sin reclutado»; se usa nombre propio o nombre del Pokémon.
- La derrota muestra una explicación legible y los PV de entrada al combate.
- Tests unitarios/feature del diario, presentador y combate en verde.
- PHPStan y Pint sin errores nuevos en los archivos tocados.

## Ampliación v3 (2026-11-02)

> Entonces muestra el motivo de la perdida de items

Interpretación: en el bloque «⚠️ Perdiste objetos» (tarjeta de resultados y diario) debe explicarse **por qué** se perdieron: la expedición terminó en derrota porque el explorador cayó debilitado. No se cambia la regla de pérdida ni el contrato persistido.

## Ampliación v4 (2026-11-02)

> No pongas que perdiste objetos en el diario, ya se muestra arriba

Interpretación: eliminar la inclusión de `_perdidas` dentro del diario; el aviso «⚠️ Perdiste objetos» se mantiene **solo en la tarjeta de resultados**. El diario vuelve a mostrarse únicamente cuando tiene eventos.

## Ampliación v5 (2026-11-02)

> En lugar de mostrar los objetos perdidos de esa forma, añadelo en otra columna como de recompensas, pero con fondo rojo y la imagen grisacea, derrotados, recompensas perdidas, y recompensas btenidas

Interpretación: retirar el bloque de aviso de pérdidas y convertirlo en una **columna** del grid de resultados, con fondo rojo e imágenes en gris. El grid queda en tres columnas: **Derrotados**, **Recompensas perdidas** y **Recompensas obtenidas** (los capturados pasan dentro de «Recompensas obtenidas»).
