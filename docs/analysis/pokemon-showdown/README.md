# Pokemon Showdown — Análisis de Referencia

> **Objetivo:** Documentar la arquitectura, fórmulas, constantes y patrones de diseño de Pokemon Showdown como referencia para la migración a nuestro sistema de batalla 3v3 en Laravel/Livewire.

## Alcance

Este análisis **no** pretende ser un port 1:1 de Pokemon Showdown. Sirve como fuente de conocimiento para:

- Entender las fórmulas matemáticas oficiales de daño, estadísticas y efectos secundarios.
- Identificar patrones de diseño reutilizables (Chain of Responsibility, Event System, Data-Driven Design).
- Extraer constantes y datos estáticos (tipos, naturalezas, movimientos, habilidades, objetos).
- Tomar decisiones informadas sobre qué adaptar, qué rechazar y qué inventar.

## Archivos

| Archivo | Contenido |
|---------|-----------|
| [01-arquitectura.md](01-arquitectura.md) | Estructura general del proyecto, separación de capas, patrones principales |
| [02-formulas-constantes.md](02-formulas-constantes.md) | Fórmulas matemáticas de daño, stats, precisión, estados, etc. |
| [03-mecanicas-batalla.md](03-mecanicas-batalla.md) | Flujo de batalla, turnos, cola de acciones, targeting, estados |
| [04-datos-estaticos.md](04-datos-estaticos.md) | Tipos, naturalezas, movimientos, habilidades, objetos, datos |
| [05-diseno-patrones.md](05-diseno-patrones.md) | Patrones de diseño, arquitectónicos y de código |
| [06-recomendaciones-migracion.md](06-recomendaciones-migracion.md) | Recomendaciones concretas para nuestra implementación |
| [07-indice-cruzado.md](07-indice-cruzado.md) | Índice cruzado de navegación rápida: concepto→ubicación, guía "quiero X", tabla de tipos, mapeo de archivos y glosario PS↔Laravel |

## Convenciones de este análisis

- Fórmulas matemáticas en notación LaTeX dentro de bloques de código.
- **Comparación con nuestro sistema** al final de cada sección principal.
- Español para narrativa, inglés para nombres de clases y funciones.
- Referencias directas al código fuente de Showdown con rutas y líneas.

## Fuentes

- Repositorio: [smogon/pokemon-showdown](https://github.com/smogon/pokemon-showdown)
- Código fuente analizado: `sim/` (motor de batalla), `data/` (datos estáticos)
- Generación de referencia: Gen 9 (Scarlet/Violet)
