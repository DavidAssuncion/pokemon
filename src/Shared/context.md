# Contexto del módulo `Shared`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Infraestructura transversal sin dependencias de módulos: colecciones tipadas, bus/UnitOfWork,
excepciones de dominio, tipos y efectividades, Value Objects y reglas compartidas por varios
módulos. Es el primer lugar donde buscar antes de duplicar código.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/Collection.php` | Colección base `Countable` + `IteratorAggregate` (add, filter, sort, map, reduce, sum…). |
| `Collections/IntCollection.php`, `StringCollection.php` | Colecciones de escalares. |
| `Domain/Collections/ItemModalCollection.php` | Items de modal de recompensas. |
| `Domain/DataTransferObjects/` | `PokemonResumen`, `ItemResuelto`, `ItemModal`. |
| `Domain/ValueObjects/MapaConteo.php` | Mapa `string => int` tipado. |
| `Domain/ValueObjects/MapaFactores.php` | Mapa de factores decimales. |
| `Domain/Exceptions/` | `DominioExcepcion` (400), `RecursoNoExiste` (404), `ViolacionReglaNegocio` (422), `PermisoDenegado` (403). |
| `Bus/` | `Command`, `CommandHandler`, `CommandBus`, `UnitOfWork`. |
| `Tipos/` | Enum `TipoPokemon`, `TiposCollection`, `TypeChart` (matriz 18×18). |
| `Domain/` | `ClasificadorOfensivaDefensiva`, `IvsRangoEntrenador`, `NivelHelper`, `SlugTipo`, `ProbabilidadCaptura`, `ProbabilidadesShiny`, `DistribuidorStatsValor`, `EscaladorNivelRival`, `RangoIvs`, `ReglasEquipo`. |
| `UI/TipoBadges.php` | Fuente única de presentación de los 18 tipos: resolución por id/slug/label, clases Tailwind e icono. |

## Decisiones de producto y funcionamiento

1. **Colecciones**: extienden `Src\Shared\Domain\Collection`, declaran `public string $type` y
   **nunca** `Illuminate\Support\Collection`.
2. **Excepciones**: las genéricas se mapean a HTTP en `bootstrap/app.php` (específicas primero);
   los controladores de módulo no usan try-catch.
3. **Bus/UnitOfWork**: flujos complejos (transacciones/eventos) pasan por
   `Src\Shared\Bus`; CRUD simple usa repositorio directo.
4. **Tipos**: `TipoPokemon` es el enum canónico; `TypeChart` solo expone
   `getEffectiveness()`; `TiposCollection` es la colección.
5. **Nivel**: `NivelHelper` usa curva media ×10 (`exp = 10 × nivel³`); nivel 100 = 10.000.000.
6. **Captura**: `ProbabilidadCaptura` aplica cap-25 sobre `capture_rate` y un factor de granja
   posterior (`probabilidadConFactor`).
7. **Shiny**: `ProbabilidadesShiny::SALVAJE = 2048`, `HUEVO = 512`; el roll es inyectable.
8. **Equipo**: `ReglasEquipo::MIEMBROS = 3`, `SLOT_MIN = 1`, `SLOT_MAX = 3`; fuente única para
   validaciones, mensajes y recompensas.
9. **`CadenasEvolutivas` vive en `app/Support`**, no en `src/Shared`: usa Eloquent (`App\Models`)
   y es infraestructura Laravel transversal. Se comparte entre Exploraciones, Entrenadores, Ruta y
   Reclutamiento; no añadir lógica de dominio allí.
10. **Badges de tipo** (`UI/TipoBadges`): fuente única por **slug ASCII** (`electrico`,
    `psiquico`, `dragon`). API: `porId`, `porSlug`, `porLabel`, `resolver`, `clases`, `icono`,
    `etiquetasPorId`, `paraJs`, `idsParaJs` y `DEFAULT`. Las clases Tailwind son literales
    (paleta del span de movimientos) para que el build las genere; el icono es
    `/images/type/{slug}.webp` y un tipo desconocido cae en `DEFAULT` (label «Desconocido» y sin
    icono). **La antigua constante `MAP` ya no existe**: no reintroducir mapas locales de tipos en
    las vistas.
    - Blade: componente `<x-tipo-badge>` con `tipo`/`slug`/`label` y tamaño `xs` (16 px), `sm`
      (20 px) o `md` (30 px).
    - JS: `window.tipoBadge(valor)` acepta id/slug/label y `window.tipoBadgePorId(id)`; los datos
      (`window.TIPOS_BADGE`, `window.TIPOS_BADGE_POR_ID`, `window.TIPOS_BADGE_DEFAULT`) los inyecta
      `resources/views/partials/tipos-badge-script.blade.php` antes de `@stack('scripts')`.
    - `etiquetasPorId()` queda para vistas que solo necesitan el texto (gimnasios).

## Contratos y puntos de entrada

- `Collection`: `add`, `filter`, `map`, `reduce`, `sort`, `sum`, `toList`, `toArray`.
- `CommandBus`: despacha `Command` a su `CommandHandler`; `UnitOfWork` coordina transacciones.
- `DominioExcepcion` y derivadas: contrato de error de dominio.
- `MapaConteo`/`MapaFactores`: mapas tipados para no usar arrays asociativos.
- `ReglasEquipo`: tamaño/slots de equipo.
- `TipoBadges`: presentación de tipos (label, slug, clases e icono) por id/slug/etiqueta, con
  serialización para JS; reutilizable por módulos.

## Dependencias

- `Src\Shared` **no depende de módulos** (`src/{{Modulo}}`).
- Puede depender de Laravel en `Bus`/infraestructura, pero `Domain` debe ser PHP puro.
- `app/Support/CadenasEvolutivas` es la excepción documentada: infraestructura Eloquent compartida.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Media] `RangoIvs` duplicado**: `Src\Shared\Domain\RangoIvs` (`{principal,resto}` para
  entrenador) y `Src\Pokemon\Domain\Stats\RangoIvs` (`{min,max}` para huevo). Renombrar para que
  `grep RangoIvs` no mezcle conceptos.
- **[Media] `CadenasEvolutivas` en `app/Support`**: correcto por Eloquent, pero su contrato
  devuelve `array<int, Collection<int, Pokemon>>`. Introducir un VO `MapaCadenas`/colección tipada
  si se consume desde más módulos.
- **[Media] `ProbabilidadCaptura` y `ProbabilidadesShiny` son estáticas**. Aceptable como cálculo
  puro; si se necesita configuración por producto, inyectar una política.
- **[Baja] `Collection` base es amplia** (muchos métodos). Mantenerla estable; no añadir métodos
  específicos de un módulo.
- **[Baja] `TipoBadges` vive en `UI/`**, acoplado a presentación; no usarlo desde Domain.

## Guía de búsqueda (grep)

- Clases: `Collection`, `IntCollection`, `StringCollection`, `CommandBus`, `UnitOfWork`,
  `DominioExcepcion`, `RecursoNoExiste`, `ViolacionReglaNegocio`, `TipoPokemon`, `TypeChart`,
  `TiposCollection`, `NivelHelper`, `ProbabilidadCaptura`, `ProbabilidadesShiny`, `ReglasEquipo`,
  `RangoIvs`, `MapaConteo`, `MapaFactores`, `TipoBadges`.
- Términos: `cap-25`, `curva media ×10`, `shiny`, `colección tipada`, `excepción de dominio`,
  `UnitOfWork`, `CadenasEvolutivas`, `TipoBadges`, `tipoBadge`, `tipoBadgePorId`, `porSlug`,
  `paraJs`.
- Tests: `tests/Unit/Shared/`, `tests/Unit/CollectionTest.php`, tests de tipos/efectividad.
