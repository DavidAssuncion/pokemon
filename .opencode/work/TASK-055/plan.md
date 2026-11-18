# TASK-055 — Plan v1

- **ID:** TASK-055
- **Versión:** v1
- **Fecha:** 2026-11-17
- **Estado:** AWAITING_APPROVAL
- **Proyecto:** `/home/david/pokemon`

## Objetivo

Rebalancear el jefe de mazmorra para que tenga vida ×10, stats base ×1 y un efecto
permanente ×3 (+200 %) visible pero **no** modelado como etapas, con barreras calculadas
sobre la defensa efectiva ×3 y sin Núcleo/Coraza. La infraestructura de partes se conserva
para una futura habilidad de otro Pokémon, fuera de esta tarea.

## Alcance

### Dentro

- Vida del jefe ×10.
- Stats base distintos de HP ×1.
- Multiplicador permanente ×3 solo en ataque, defensa, ataque especial, defensa especial y
  velocidad.
- Barreras físicas/especiales calculadas sobre la defensa efectiva ×3.
- Indicador visual `+200 %` en la tarjeta del jefe, sin etapas reales.
- Eliminación de Núcleo/Coraza de los jefes de mazmorra y del fallback implícito de
  `JefeEstado`.
- Documentación del módulo y del contrato de batalla.

### Fuera

- Implementar la habilidad que dará Núcleo/Coraza a un Pokémon concreto.
- Rebalancear gimnasios, exploraciones, ruta u otros modos.
- Cambiar recompensas de mazmorra.
- Crear, modificar o ejecutar tests.
- Añadir dependencias, migraciones o cambios de base de datos.

## Arquitectura y contratos

Se sigue el diseño de `architecture.md`:

- `BattleStats` incorpora `multiplicadorStats` (default `1.0`) y lo aplica **después** de
  la fórmula de nivel a los cinco stats de combate y a `defenseHp`/`spDefenseHp`. **HP no
  se multiplica.**
- `DatosPokemonBatalla` incorpora `multiplicadorStatsJefe` (default `1.0`), con propiedad
  privada y getter para compatibilidad con payloads serializados antiguos. `conNivel()` lo
  propaga.
- `EquipoBatalla::fromData` pasa el multiplicador a `BattleStats` al construir el
  combatiente.
- `GeneradorJefeMazmorra` envía `multiplicadorStatsJefe = 3.0`, HP ×10, stats base ×1, sin
  `bonusInicialStats` y sin `puntosDebiles`.
- `JefeEstado` deja de crear un Núcleo implícito cuando no hay partes.
- `MultiplicadoresStats` **no se toca**: el ×3 no es una etapa y no interactúa con
  Inversión, Psicocarga, Castigo, Poder Reserva ni Canto Encantador.
- `CombatienteVista` expone `bonusStatsJefe = 200`; el Blade pinta un badge `+200 %` con
  tooltip `×3`.
- No se cambia `SESSION_VERSION`: los campos nuevos tienen default y los payloads antiguos
  cargan con multiplicador `1.0`.

### Decisiones resueltas

1. **Barreras:** exactamente 3× la barra base, aplicando el multiplicador después de la
   fórmula de nivel (decisión del desarrollador: “sobre la defensa efectiva con ×3”).
2. **`bonusInicialStats`:** se conserva intacto para otros modos; mazmorra deja de enviarlo.
3. **Sesiones vivas:** conservan el jefe antiguo hasta terminar el combate; el nuevo balance
   aplica al iniciar una batalla nueva. No se migran.
4. **Tests:** fuera de alcance. `MazmorraApiTest` quedará obsoleto y fallará si se ejecuta;
   no se modifica. Se registrará en el informe final.
5. **Nomenclatura:** `MULTIPLICADOR_STATS_EFECTIVO_JEFE = 3.0`.
6. **UI:** badge `+200 %` con tooltip `×3`; sin etapas.

## Archivos y responsables

| Archivo | Cambio | Responsable |
|---|---|---|
| `src/Pokemon/Domain/Stats/BattleStats.php` | Nuevo `multiplicadorStats` (default 1.0), aplicación a 5 stats y barreras, getter | `backend` |
| `src/Battle/Domain/DatosPokemonBatalla.php` | Nuevo `multiplicadorStatsJefe` (default 1.0), getter, propagación en `conNivel()` | `backend` |
| `src/Battle/Domain/EquipoBatalla.php` | Pasar el multiplicador a `BattleStats` al construir | `backend` |
| `src/Mazmorras/Domain/ReglasMazmorra.php` | HP ×10, stats base ×1, constante de efecto ×3, retirar bonus +30 % | `backend` |
| `src/Mazmorras/App/GeneradorJefeMazmorra.php` | HP ×10, stats base ×1, `multiplicadorStatsJefe=3.0`, sin `bonusInicialStats`, sin `puntosDebiles` | `backend` |
| `src/Battle/Domain/ValueObjects/JefeEstado.php` | Eliminar fallback de Núcleo; permitir cero partes | `backend` |
| `src/Battle/Domain/CombatienteVista.php` | Exponer `bonusStatsJefe` sin tocar etapas ni líneas ajenas | `backend` |
| `resources/views/livewire/_pokemon-card.blade.php` | Badge `+200 %` dentro de `@if($p['esJefe'])`, sin tocar líneas ajenas | `frontend` |
| `src/Mazmorras/context.md` | Reglas nuevas | `documenter` |
| `src/Mazmorras/README.md` | Resumen de reglas nuevas | `documenter` |
| `src/Battle/context.md` | Contrato de `multiplicadorStats` y retirada de Núcleo/Coraza | `documenter` |
| `docs/context.md` | Referencias al jefe ×7/×10/+30 % | `documenter` |
| `docs/testing.md` | Referencia al “jefe 1×10”, si aplica | `documenter` |
| `src/Mazmorras/App/IniciarCombateMazmorra.php` | Solo docblock “jefe ×10”, si el documenter lo incluye | `documenter` |

## Orden de ejecución y dependencias

1. `backend`: `BattleStats` (base del contrato).
2. `backend`: `DatosPokemonBatalla` y `EquipoBatalla`.
3. `backend`: `ReglasMazmorra`, `GeneradorJefeMazmorra`, `JefeEstado`.
4. `backend`: `CombatienteVista`.
5. `frontend`: badge en `_pokemon-card.blade.php`.
6. `documenter`: documentación.
7. `reviewer`: revisión independiente.
8. `security`: **no aplica** previsiblemente; se confirma en el informe.

Dependencias: 2 depende de 1; 3 depende de 1-2; 4 depende de 1; 5 depende de 4; 6 después
del código; 7 después de 5-6. No hay trabajo paralelo sobre archivos compartidos.

## Criterios de aceptación

- [ ] Una batalla nueva de mazmorra genera al jefe con vida `base ×10`.
- [ ] Los stats base del jefe son los de especie, sin ×10.
- [ ] Ataque, defensa, ataque especial, defensa especial y velocidad efectivos son
      exactamente ×3 respecto a la misma especie sin efecto.
- [ ] `defenseHp` y `spDefenseHp` son exactamente ×3; `hp` no recibe el ×3.
- [ ] `MultiplicadoresStats` del jefe permanece neutro: `obtenerEtapas()` todo 0,
      `bonificacionesPositivas() === 0`, `buffTurns === 0`.
- [ ] Inversión, Psicocarga, Castigo, Poder Reserva y Canto Encantador no interactúan con el
      ×3.
- [ ] `jefePartes` está vacío y la tarjeta no muestra Núcleo/Coraza.
- [ ] `JefeEstado` con lista vacía no crea una parte Núcleo.
- [ ] La infraestructura de partes sigue existiendo y acepta partes explícitas para la
      futura habilidad.
- [ ] `bonusStatsJefe === 200` y la tarjeta muestra `+200 %` con tooltip `×3`.
- [ ] Los modos distintos de mazmorra no cambian: multiplicador default `1.0`.
- [ ] No se modifica ningún archivo de `tests/`.
- [ ] No se añaden dependencias, migraciones ni cambios de base de datos.

## Verificación requerida

Sin tests. Se ejecutará:

1. `php -l` sobre cada PHP modificado.
2. `vendor/bin/pint --dirty --format agent`.
3. `composer run analyse` (PHPStan) o, si el entorno no lo permite, análisis estático
   equivalente y registro del resultado.
4. Comprobación manual de los criterios de aceptación mediante lectura del diff y, si el
   desarrollador lo autoriza, una batalla de prueba en local.
5. Revisión independiente del diff por `reviewer`.

No se ejecutará `php artisan test` ni ninguna suite de tests.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| `MazmorraApiTest` hardcodea ×7/×10/+30 % y fallará. | Fuera de alcance; no se modifica. Se registra como deuda de tests al cierre del módulo. |
| `CombatienteVista.php` y `_pokemon-card.blade.php` tienen cambios locales ajenos. | Añadir solo líneas nuevas aisladas; no reformatear ni tocar líneas ajenas. |
| Doble aplicación del ×3. | El generador no multiplica stats; solo envía `multiplicadorStatsJefe=3.0` y no envía `bonusInicialStats`. |
| Payloads antiguos sin los campos nuevos. | Defaults `1.0` en `BattleStats` y `DatosPokemonBatalla`; sin bump de `SESSION_VERSION`. |
| Sesiones vivas con el jefe antiguo. | Comportamiento esperado; el nuevo balance aplica al iniciar combate. |
| Interacciones no deseadas con etapas. | `MultiplicadoresStats` no se modifica y la UI no usa `stagesDetalle` para el ×3. |
| Regresión en CP/ruta por `BattleStats`/DTO. | Multiplicador default `1.0`; solo mazmorra pasa `3.0`. |
| Pérdida de la infraestructura de partes. | Solo se elimina el fallback; `JefeEstado`/`JefeCombatiente`/`ParteJefe` se conservan. |

## Rollback

- No hay migraciones ni cambios de datos.
- Revertir solo los archivos de la tabla anterior, aplicando el diff inverso o restaurando
  su contenido previo.
- No usar `git reset --hard`, `git clean` ni `git checkout --` global: el árbol contiene
  cambios ajenos.
- Si una batalla ya está en sesión, el rollback no la altera; se reinicia el combate.

## Aprobación

Este plan no autoriza implementación hasta que el desarrollador lo apruebe mencionando
`plan v1`. La aprobación se registrará en `approval.md` con el texto literal.
