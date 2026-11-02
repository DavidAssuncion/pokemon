# Arrays, DTOs, colecciones y constantes

Glob: `src/**`, `app/**`, `resources/**`

## Regla principal

**Prohibido usar `array` como contrato entre capas, métodos públicos, casos de uso, repositorios,
controladores o APIs.** Usar, en este orden:

1. Entidad de dominio (identidad).
2. Value Object / Enum (primitiva cerrada).
3. DTO readonly (`final readonly class`).
4. Colección tipada (`Src\Shared\Domain\Collection` o `Illuminate\Support\Collection` solo en
   infraestructura/presentación justificada).

## Excepciones toleradas

- `toArray()` interno requerido por Eloquent/JSON en la **frontera** de persistencia o vista.
- Arrays internos de PHP/Laravel para configuración, validación o payloads de framework.
- Arrays de datos ya normalizados que solo consume una función privada y no cruzan capas.

Si un array representa una regla, un contrato o un dato de negocio, **no es una excepción**.

## Reducir arrays al máximo

- No crear arrays nuevos si existe un DTO, VO, Enum o colección adecuada.
- No devolver `array{a: int, b: string}` desde un método público; crear `ResultadoX` readonly.
- No usar arrays asociativos como sustituto de DTOs, entidades, Value Objects o contratos.
- No usar arrays posicionales (`[principal, resto]`) para conceptos de negocio; usar VO.
- Los mapas de dominio usan `MapaConteo`/`MapaFactores` o colecciones tipadas.
- Los listados devuelven colecciones tipadas, no `list<array<string, mixed>>`.
- En presentación, un `toArray()` de DTO es aceptable como serialización final.

## Constantes y valores mágicos

- Los valores de negocio repetidos o no evidentes van a constantes/enums/VOs:
  `ReglasEquipo`, `ReglasMazmorra`, `ReglasExploracion`, `ReglasHuevo`, `ReglasBatalla`,
  `ProbabilidadCaptura`, `ProbabilidadesShiny`.
- No dejar literales como `3`, `255`, `0.8`, `1.5`, `10`, `25` en reglas de negocio si el nombre
  aporta significado.
- Una constante debe tener una sola fuente; no duplicar el mismo valor en dos módulos.
- Si el valor depende de configuración de producto, usar un VO/DTO de configuración, no un array.

## Frontend: solo presentación

- `resources/**` no calcula reglas de negocio, stats, CP, niveles, probabilidades, recompensas ni
  autorización.
- El backend entrega datos derivados listos (nivel, CP, progreso, porcentajes, flags de permiso).
- El frontend puede formatear (fechas, números, monedas), animar y filtrar visualmente.
- No replicar fórmulas de `src/` en JavaScript/Alpine/Blade. Si la UI necesita un cálculo, crear el
  dato en backend.
- Las vistas no consultan BD ni modelos; reciben DTOs/colecciones ya preparados.

## `app/` delgada y `src/` reutilizable

- `app/` es infraestructura Laravel y presentación; no debe acumular reglas de negocio.
- La lógica de un módulo vive en `src/{{Modulo}}/Domain` (pura) y `src/{{Modulo}}/App` (casos de uso).
- Código usado por un solo módulo vive en ese módulo.
- Código usado por dos o más módulos vive en `src/Shared` (o `app/Support` solo si es infraestructura
  Laravel transversal, como `CadenasEvolutivas`).
- No duplicar bloques de más de 5 líneas; extraer servicio/VO/colección.

## Checklist de revisión

- [ ] ¿Hay `array` como retorno de un método público o contrato de capa?
- [ ] ¿Hay arrays asociativos que representan un DTO/entidad/contrato?
- [ ] ¿Hay valores mágicos repetidos que deberían ser constantes/enums?
- [ ] ¿La vista calcula reglas de negocio o consulta datos?
- [ ] ¿El controlador/Livewire concentra lógica que debería vivir en `src/`?
- [ ] ¿El código reutilizable está en `src/Shared` en lugar de duplicado?
- [ ] ¿El `toArray()` está solo en la frontera de persistencia/vista?
