# TASK-055 — Informe final

- **ID:** TASK-055
- **Fecha:** 2026-11-18
- **Estado:** COMPLETED
- **Plan aprobado:** v1 («Aprobar plan v1»)
- **Proyecto:** `/home/david/pokemon`

## Resumen

Se rebalanceó el jefe de mazmorra: vida ×10, stats base distintos de HP ×1 y un efecto
permanente ×3 (+200 %) sobre ataque, defensa, ataque especial, defensa especial y
velocidad. El efecto no se modela como etapas: `MultiplicadoresStats` queda neutro, por lo
que no interactúa con Inversión, Psicocarga, Castigo, Poder Reserva ni Canto Encantador.
Las barreras de defensa física/especial se calculan sobre la defensa efectiva ×3.

Se eliminaron Núcleo/Coraza de los jefes de mazmorra y el fallback que creaba un Núcleo
implícito. La infraestructura de partes (`JefeEstado`, `JefeCombatiente`, `ParteJefe`,
`PartesJefeCollection`) se conserva para una futura habilidad de otro Pokémon, fuera del
alcance de esta tarea.

La tarjeta del jefe muestra un badge `+200 %` derivado de `bonusStatsJefe`; no se muestra
como etapa ni como buff temporal.

## Archivos creados

- `.opencode/work/TASK-055/` — 10 artefactos de coordinación.
- No se crearon archivos de código, tests, migraciones ni dependencias.

## Archivos modificados

Código:

- `src/Pokemon/Domain/Stats/BattleStats.php` — `multiplicadorStats` privado con default
  `1.0`, aplicado tras la fórmula de nivel a los cinco stats y a `defenseHp`/`spDefenseHp`;
  HP excluida.
- `src/Battle/Domain/DatosPokemonBatalla.php` — `multiplicadorStatsJefe` default `1.0`,
  getter y propagación en `conNivel()`.
- `src/Battle/Domain/EquipoBatalla.php` — construye `BattleStats` con el multiplicador del
  DTO.
- `src/Mazmorras/Domain/ReglasMazmorra.php` — HP ×10, efecto ×3
  (`MULTIPLICADOR_STATS_EFECTIVO_JEFE`); retirados ×10 de stats y bonus +30 %.
- `src/Mazmorras/App/GeneradorJefeMazmorra.php` — HP ×10, stats base ×1, multiplicador 3.0,
  sin `bonusInicialStats` ni `puntosDebiles`.
- `src/Battle/Domain/ValueObjects/JefeEstado.php` — sin fallback de Núcleo; lista vacía
  válida.
- `src/Battle/Domain/CombatienteVista.php` — `bonusStatsJefe` derivado del multiplicador.
- `resources/views/livewire/_pokemon-card.blade.php` — badge `+200 %` con tooltip derivado
  del porcentaje.

Documentación:

- `docs/context.md`
- `docs/testing.md`
- `src/Mazmorras/context.md`
- `src/Mazmorras/README.md`
- `src/Battle/context.md`

No se modificaron `tests/`, migraciones, `composer.json`, `package.json` ni
`SESSION_VERSION`.

## Decisiones y ADR

- El ×3 es un **multiplicador de stats efectivas**, no una etapa. Se aplica en
  `BattleStats` después de la fórmula de nivel para que el ×3 sea exacto y las barreras
  hereden el escalado.
- `DatosPokemonBatalla::$multiplicadorStatsJefe` y `BattleStats::$multiplicadorStats` usan
  default `1.0`; los payloads antiguos cargan sin migración.
- Las barreras se calculan con la defensa efectiva ×3 (post-fórmula), decisión confirmada
  por el desarrollador.
- `bonusInicialStats` se conserva para otros modos; mazmorra deja de enviarlo.
- No se creó un ADR formal; el borrador está en `architecture.md` y la decisión queda
  documentada en `src/Battle/context.md` y `src/Mazmorras/context.md`.

## Verificación ejecutada

- `php -l` sobre los 7 PHP modificados: sin errores.
- `vendor/bin/phpstan analyse` sobre los 7 PHP: `[OK] No errors`.
- `vendor/bin/pint --test` sobre los PHP modificados: `pass`.
- Simulación de serialización con las clases reales:
  - `BattleStats` nuevo con 3.0 → `multiplicadorStats() = 3.0`; barreras ×3; HP sin ×3.
  - Payload antiguo sin la clave `multiplicadorStats` → `1.0` sin excepción.
  - `PokemonEntity` anidado con payload antiguo → `1.0` sin excepción.
- `git status --porcelain tests` vacío.
- **No se ejecutó la suite de tests** (política del proyecto y plan v1).

## Hallazgos de revisión

- **BLOCKER encontrado y corregido:** `BattleStats::$multiplicadorStats` estaba promocionada
  en el constructor; el default no se restauraba al deserializar y las sesiones vivas
  podían lanzar `Error: Typed property ... must not be accessed before initialization`.
  Se cambió a propiedad privada no promocionada con default `1.0` y asignación explícita.
  Verificado con payload antiguo real.
- **NIT corregido:** el tooltip del badge fijaba «×3»; ahora deriva de `bonusStatsJefe`.
- **NIT corregido:** la propiedad pasó a `private` con getter, sin escritura externa.
- **Veredicto final del reviewer:** `APPROVED`.

## Seguridad

**No aplica.** El cambio es de balance de combate y presentación. No afecta a
autenticación, autorización, entrada de usuario, SQL, archivos, sesiones/cookies, secretos,
dependencias, endpoints públicos ni datos personales. No se desactivaron validaciones ni
controles.

## Riesgos residuales

- Las sesiones de mazmorra ya iniciadas conservan el jefe antiguo hasta terminar el
  combate; el nuevo balance aplica al iniciar una batalla nueva.
- `tests/Feature/Mazmorras/MazmorraApiTest.php` conserva el balance ×7/×10/+30 % y fallará
  si se ejecuta. Está declarado obsoleto en `docs/testing.md`; actualizarlo queda para el
  cierre del módulo, fuera de TASK-055.
- El equilibrio de dificultad (HP ×10 + ×3 efectivo) no se ha validado con datos de juego.
- `docs/context.md` y `docs/testing.md` mantienen referencias preexistentes a
  `SESSION_VERSION` antiguas, ajenas a esta tarea.
- No se ha creado commit; el árbol contiene cambios de otras tareas y el plan no autorizaba
  commit. El diff de TASK-055 queda listo para revisión/commit atómico.

## Próximos pasos

1. Probar manualmente una batalla de mazmorra nueva en local para validar sensación de
   dificultad y el badge `+200 %`.
2. Actualizar `MazmorraApiTest` al cierre del módulo (fuera de este flujo).
3. Si se quiere trasladar Núcleo/Coraza a Pokémon con Armadura Frágil, abrir una tarea
   nueva: son mecánicas distintas (reacción de stats vs. barreras de HP + Vulnerable) y
   requieren plan y aprobación propios.
4. Crear commit atómico solo cuando el desarrollador lo autorice.
