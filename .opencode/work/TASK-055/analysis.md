# TASK-055 — Análisis

> Resultado de `analyst` (sesión `ses_e14b7305dffeMKt0ot8IoS7pCl`). El agente alcanzó el
> límite de pasos antes de consolidar el informe formal, pero completó la investigación.
> Se reproduce su resultado sin alterar las conclusiones.

## Estado de la investigación

Investigación **completada en su práctica totalidad**: leídos todos los archivos del alcance,
localizadas las líneas exactas, identificados los tests afectados y la documentación
desactualizada. Faltaba únicamente consolidar el informe formal.

## Hallazgos comprobados

### 1. Punto de aplicación del bonus actual

- `src/Mazmorras/Domain/ReglasMazmorra.php:22` — `BONUS_INICIAL_STATS_JEFE = 30.0`.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:72` — se transporta en `bonusInicialStats`.
- `src/Battle/Domain/EquipoBatalla.php:226-228` — se aplica al construir el combate vía
  `aplicarBonusInicialStats`.
- `src/Battle/Domain/Combatiente.php:449-452` →
  `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php:113-122`.
- **Hallazgo clave:** `StatsCombatiente::aplicarBonusInicialStats` itera `StatClave::cases()`
  (líneas 119-121), por lo que **incluye `PRECISION` y `EVASION`**. Hay que limitarlo
  explícitamente a los 5 stats de combate (`ATAQUE`, `DEFENSA`, `ATAQUE_ESPECIAL`,
  `DEFENSA_ESPECIAL`, `VELOCIDAD`; `StatClave` en `src/Battle/Domain/Enums/StatClave.php:7-15`).
  HP no es `StatClave`, así que ya queda fuera.

### 2. Vida ×10 y stats ×1

- `ReglasMazmorra.php:19` — `MULTIPLICADOR_HP_JEFE = 7` → `10`.
- `ReglasMazmorra.php:16` — `MULTIPLICADOR_STATS_JEFE = 10`; usado solo en
  `GeneradorJefeMazmorra.php:53-57`. Opciones: ponerlo a `1` con comentario «sin
  multiplicador directo» o eliminarlo y pasar los stats sin multiplicar.
- Nota: la multiplicación actual es sobre **base de especie**, antes de la fórmula de nivel
  (`src/Pokemon/Domain/Stats/BattleStats.php:73-81`), lo que coincide con «vida base ×10».

### 3. UI: +4 etapas / +200 %

- `src/Battle/Domain/CombatienteVista.php:167-196` — `stagesDetalle` convierte factor→etapa
  con `factorDesdeStages`; `MultiplicadoresStats::factorAEtapa` (`:325-332`) →
  `round(2*3-2)=4` y `porcentaje = round((3.0-1)*100)=200`.
- **Sin topes que lo impidan:** `DEFAULT_MAX = 4.0` (`MultiplicadoresStats.php:23`) es mayor
  que 3.0.
- `resources/views/livewire/_pokemon-card.blade.php:139-143` pinta `stagesDetalle`.
  **No requiere cambios** (su único cambio local es un `wire:key` ajeno a la tarea).
- `CombatienteVista.php` solo tiene cambios locales de tema (postura/clases); las líneas
  82-83 y 163-199 están intactas.

### 4. Fallback de Núcleo

- `src/Battle/Domain/ValueObjects/JefeEstado.php:112-114` — si `$resultado === []`, crea
  `ParteJefe('nucleo', 'Núcleo', hpMax*0.2)`. **Sí, genera un Núcleo implícito con
  `puntosDebiles: []`.**
- Propuesta: eliminar esas 3 líneas. Verificado que `daniarParte` (`:154-163`),
  `tienePartesRotas` (`:207-216`), `partes()` (`:202-205`) y `jefePartes`
  (`CombatienteVista.php:205-226`) funcionan con lista vacía; el blade itera vacío y no
  muestra nada. `JefeCombatiente::configurar` (`:48-55`) y `JefeEstado` siguen operativos
  para la futura habilidad (pasaría sus propias partes).
- `GeneradorJefeMazmorra.php:78-81` — eliminar el argumento `puntosDebiles`.

### 5. Tests afectados (identificados, NO modificar)

- `tests/Feature/Mazmorras/MazmorraApiTest.php:120-134` — hardcodea `BattleStats` con base
  `(343, 390, 290, 350, 400, 450)` (equivalente a ×7/×10) y espera factor `1.3` en ATAQUE y
  VELOCIDAD. **Fallará** con el nuevo balance.
- No se localizan tests que referencien `JefeEstado`, `PartesJefe`, `puntosDebiles` ni el
  fallback.
- `MazmorraLivewireTest` no depende de los multiplicadores; `PresentadorMazmorraTest`
  construye datos ad hoc.

### 6. Documentación desactualizada

- `src/Mazmorras/Domain/ReglasMazmorra.php:15,18,21` (comentarios de constantes).
- `src/Mazmorras/App/GeneradorJefeMazmorra.php:14-18` (docblock «stats ×10»).
- `src/Mazmorras/context.md:17,25,38-42,90-92,98,108-109`.
- `src/Mazmorras/README.md:11,19`.
- `src/Battle/context.md:1007-1010` (bonus +30 % y «todas las estadísticas»).
- `docs/context.md:35,206,219-220`.
- `docs/testing.md:229` («jefe 1×10»).

### 7. Efectos colaterales relevantes (riesgo)

- **Acoplamiento etapas↔mecánicas:** mostrar el buff como +4 etapas implica
  `bonificacionesPositivas() = 20` (`StatsCombatiente.php:101-111`), usado por:
  - `ManejadorDanioBase.php:131,136-137` → movimientos `Castigo`, `Poder Reserva`,
    `Chulería`, `Envidia Ardiente` (`storage/data/moves.json`) reciben +400 potencia o el
    doble contra/del jefe.
  - `AnalizadorAmenazasImpl.php:104-116` (percepción de amenaza de la IA).
  - `EjecutorCantoEncantador.php:23` (confusión garantizada si el jefe tiene boosts).
- **Manipulación de stats:** `EjecutorInvertirStats.php:22-24`,
  `EjecutorIntercambioStats.php:29-34` y `EjecutorPsicocarga.php:21` tratan la capa base
  como etapas; el «bonus permanente» puede invertirse, intercambiarse o copiarse.
  `Combatiente::revivir` (`:232-235` → `stats->reiniciar()`) borra multiplicadores.
- **Barreras:** se calculan desde `battleStats` sin multiplicadores (`BattleStats.php:64-65`).
  Con stats base ×1, las barreras caen ~10×; el +200 % de Defensa mitiga daño pero **no
  aumenta el máximo de la barra DEF**. Requiere confirmación de producto («las barreras se
  calculan sobre estos valores»).

### 8. Sesión/serialización

- `app/Support/BattleSessionService.php:50` — `SESSION_VERSION = 21`, mínimo 9.
- El cambio es semántico (factores persistidos en `MultiplicadoresStats::toArray`,
  `JefeCombatiente::serializar` usa `serialize()`), **sin cambiar la estructura del
  payload** → no hace falta bump de versión. Las sesiones vivas conservarán el jefe antiguo
  (×7/×10/+30 %).

## Decisiones necesarias (sin resolver)

1. `MULTIPLICADOR_STATS_JEFE`: mantener a `1` o eliminar la constante (evitar no-op
   engañoso).
2. Dónde ubicar la lista de 5 stats del bonus: constante en `StatsCombatiente`, método en
   `StatClave` (`recibeBonusInicial()`) o lista en `MultiplicadoresStats`.
3. Semántica de partes: eliminar fallback (recomendado, cero partes = sin partes) vs
   contrato `?array` (null = fallback, `[]` = ninguno). Recomiendo eliminar el fallback y
   documentarlo.
4. Aceptar (o no) los efectos colaterales de modelar el buff como etapas (punto 7).
5. Confirmar si las barreras deben verse afectadas por el ×3 o solo por la base ×1.

## Recomendaciones siguientes

- El `orchestrator` puede redactar `analysis.md` con este contenido y pasar a `architect`
  para las decisiones 1-5.
- El `architect` debe decidir el punto 3 (contrato de partes) y el tratamiento del
  acoplamiento por etapas (punto 7) antes de implementar.
- No tocar `CombatienteVista.php` ni `_pokemon-card.blade.php` (sin cambios necesarios;
  además tienen cambios locales ajenos).
- Actualizar `MazmorraApiTest` queda **fuera del alcance** de TASK-055; se registrará como
  test preexistente que fallará.
