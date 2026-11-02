# TASK-001 — Sendas: agrupación por nivel, filtro y experiencia con caramelos

## Petición literal

> En Senda hay poner divisores colapsables y agrupar los movimientos por nivel, tambien añadir un filtro de nombres, quitamos el aside para poner este filtro . No hay cap de niveles, solo la exp acumulada, y se podra usar caramelos para conseguir experiencia, como con en detalle pokemon

## Contexto

- Proyecto: `/home/david/pokemon` (Laravel 12, PHP 8.4, Blade + Alpine + Tailwind).
- Página afectada: detalle de Senda `GET /sendas/{tipo}` (`resources/views/sendas/show.blade.php`).
- Lógica actual: `app/Support/SendaProgreso.php`, `app/Support/ArbolSendas.php`, `app/Support/SendaCostes.php`, `app/Support/SendaNiveles.php`.
- Controlador: `app/Http/Controllers/SendasController.php`.
- Inventario de caramelos de tipo: `player_inventory`, clave `tipo:{slug}` vía `App\Support\ItemCatalogo::keyTipo()`.
- Referencia de consumo de caramelos para exp: `ReclutadoController::darCaramelo()` (1 caramelo = 100 exp) y `resources/views/reclutado/show.blade.php`.

## Decisiones de producto confirmadas

1. Se mantienen las 3 columnas por categoría (Físico, Especial, Estado). Dentro de cada columna, los movimientos se agrupan por nivel requerido en divisores colapsables.
2. Se elimina el `aside` de progreso vertical. El filtro de nombres se coloca en una barra superior a ancho completo, encima de las categorías.
3. Cada caramelo de tipo de la Senda aporta 100 exp. UI con botones x1/x10/x100, consumo limitado al inventario disponible (mismo patrón que el detalle del Pokémon).
4. No hay cap de nivel por nivel de cuenta. El nivel de la Senda se deriva únicamente de la exp acumulada. A partir del nivel 5 se usa una curva geométrica: cada tramo extra crece un 50 % respecto al tramo anterior.

## Resoluciones de diseño acordadas tras el análisis

1. La curva geométrica se aplica sobre el **delta** de cada tramo: se conservan los umbrales 1–5 (0/500/2.000/6.000/15.000) y cada tramo posterior crece ×1.5 respecto al anterior. U(6)=28.500, U(7)=48.750, etc.
2. Redondeo al entero más cercano en cada tramo (`round`), documentado en `SendaProgreso`.
3. Se elimina también el cap por nivel de cuenta. El nivel se deriva únicamente de la exp.
4. Se acepta que a partir del nivel 6 no haya nuevos nodos desbloqueables con el catálogo actual; no se rebalancean `required_level`.
5. Se mantiene `player_type_paths.level` como caché y se sincroniza en cada cambio de exp. Sin migración.
6. La agrupación por nivel es presentacional: se calcula en el frontend sobre la lista ya ordenada por el backend, sin cambiar la forma plana de `ramas` ni romper `ArbolSendas::nodo()`.
7. El filtro busca solo por nombre de movimiento, ignora mayúsculas/acentos y autoexpande los grupos mientras hay texto.

## Cambio de requisitos (2026-10-31, posterior a la aprobación v1)

El desarrollador ha corregido el alcance:

- **Los niveles son geométricos, pero solo existen 5 niveles.**
- **El máximo es 500.000 exp.**
- Esto sustituye la decisión anterior de "sin cap de niveles". Se mantiene la eliminación del cap por nivel de cuenta: la Senda se rige por su propia exp y su nivel 5.
- **Prohibido usar el modelo `pro`.** El desarrollador lo elegirá si lo considera necesario. Usar `opencode-go/deepseek-v4.1-flash` para cualquier subagente a partir de ahora.

Pendiente de confirmar la secuencia geométrica exacta y si la exp se capa en 500.000 o solo es el umbral del nivel 5.

### Curva definitiva confirmada (2026-10-31)

- 5 niveles.
- Umbrales de entrada: `0 / 15.625 / 31.250 / 62.500 / 125.000`.
- El nivel 5 se extiende hasta `500.000` exp (tope de referencia).
- La exp puede superar `500.000`; el nivel permanece en 5 y la UI muestra el tope.
- Fórmula geométrica: cada umbral duplica al anterior (15.625 ×2 ×2 ×2) y el nivel 5 usa el tramo 125.000 → 500.000.
- Se mantiene 100 exp por caramelo y la eliminación del cap por nivel de cuenta.

## Restricciones

- No sobrescribir los cambios locales preexistentes (hay WIP en `SendasController`, `sendas/index.blade.php` y otros módulos).
- No tocar `.env`, secretos ni credenciales.
- No instalar/actualizar dependencias.
- No ejecutar `git push`, `git reset --hard`, `git clean` ni `git checkout --`.
- Mantener el estilo y la arquitectura existentes; no introducir abstracciones innecesarias.
- Testing diferido según `AGENTS.md` del proyecto: no ejecutar tests durante el desarrollo; al cierre se decide si se añaden pruebas específicas.
- Respetar `.ai/rules/frontend-minimal-logic.md` y `.ai/rules/arrays-dto-collections.md`.

## Fuera de alcance (inicial)

- Rediseñar la portada `/sendas` más allá de corregir textos incoherentes por el fin del cap (nivel `/5`, mensajes de cap).
- Rebalancear los niveles requeridos de los movimientos existentes.
- Añadir nuevos movimientos o nodos a las Sendas.
