# TASK-055 — Revisión

> Resultado de `reviewer` (sesión `ses_e12804954ffexb1085RpcY4117`).

## Veredicto final

**APPROVED** — tras corregir el blocker de serialización y los dos NITs.

## Veredicto inicial

**CHANGES_REQUESTED**

## Resumen

El balance funciona para batallas nuevas: HP ×10, stats base ×1, efecto permanente ×3
aplicado después de la fórmula de nivel a los cinco stats y a las barreras, `hp` excluida,
`MultiplicadoresStats` neutro, sin Núcleo/Coraza, `bonusStatsJefe = 200` y badge limitado
al bloque de jefe. PHPStan y Pint pasan.

## Blocker

`BattleStats::$multiplicadorStats` está promocionada en el constructor. En PHP, las
propiedades promocionadas no conservan el valor por defecto al deserializar. Los payloads
de sesión anteriores (v9–v21) reconstruyen `BattleStats` sin inicializar esa propiedad y
`CombatienteVista::para()` la consulta en cada render, provocando:

`Error: Typed property ... $multiplicadorStats must not be accessed before initialization`

Afecta a cualquier combate en curso de cualquier modo tras el despliegue. Contradice el
criterio de no regresión por default `1.0` y la mitigación del plan.

- Evidencia: `src/Pokemon/Domain/Stats/BattleStats.php:44`; impacto en
  `src/Battle/Domain/CombatienteVista.php:130`, `Combatiente::aArrayVista()` y
  `SincronizadorVistaBatalla`.
- Recomendación: declarar la propiedad **no promocionada** con default
  (`private float $multiplicadorStats = 1.0;`) y asignarla desde un parámetro normal del
  constructor, como ya se hizo con `DatosPokemonBatalla::$multiplicadorStatsJefe`.
- Re-verificar con un payload v21 real que `multiplicadorStats()` devuelve `1.0` sin
  excepción.

## NITs

- `resources/views/livewire/_pokemon-card.blade.php:103-104`: el tooltip fija «×3».
  Derivar el texto del porcentaje o dejarlo sin factor para que no mienta si cambia la
  constante.
- `src/Pokemon/Domain/Stats/BattleStats.php:44`: propiedad pública mutable. Con la
  corrección del blocker puede quedar privada con getter.

## Criterios verificados

- Vida ×10: PASA.
- Stats base ×1: PASA.
- ×3 efectivo en los cinco stats: PASA.
- Barreras ×3 y HP sin ×3: PASA.
- `MultiplicadoresStats` neutro: PASA.
- Sin Núcleo/Coraza y sin fallback: PASA.
- Infraestructura de partes conservada: PASA.
- `bonusStatsJefe = 200` / 0: PASA.
- Sin cambios en tests: PASA.
- Sin dependencias/migraciones: PASA.
- Otros modos sin regresión: **FALLA para sesiones ya serializadas** por el blocker.

## Riesgos residuales

- El balance de dificultad no se valida con datos de juego.
- Las sesiones vivas de mazmorra mantienen el jefe antiguo; tras corregir el blocker
  seguirán funcionando, con badge 0 porque el multiplicador es 1.0.
- `tests/Feature/Mazmorras/MazmorraApiTest.php` sigue obsoleto y fallará si se ejecuta;
  fuera de alcance.
- Un bump de `SESSION_VERSION` descartaría todos los combates en curso; no se aprueba.

## Verificación ejecutada por el revisor

- `php -l` de los 7 PHP modificados: sin errores.
- `vendor/bin/phpstan analyse` sobre los 7 PHP: `[OK] No errors`.
- `vendor/bin/pint --test` sobre los 7 PHP: `pass`.
- Comprobación numérica de `BattleStats`: HP 160 sin escalar; barreras 480 (×3); cinco
  stats 315 vs 105 (×3).
- Simulación de payloads antiguos: `DatosPokemonBatalla` carga con 1.0; `BattleStats` y
  `PokemonEntity` lanzan `Error` al acceder a `multiplicadorStats`.
- Tests no ejecutados.

## Acción requerida

Corregir el blocker en `BattleStats` y el NIT del tooltip; después repetir la revisión.

---

## Re-revisión final (2026-11-18)

**Veredicto: APPROVED**

### Blocker resuelto

- `BattleStats::$multiplicadorStats` ya no es promocionada: `private float $multiplicadorStats = 1.0;`
  con parámetro normal y asignación en el constructor.
- `ReflectionProperty`: `hasDefaultValue=yes`, `isPromoted=no`, `private`.
- Payload antiguo real de `BattleStats` y de `PokemonEntity` anidado: `multiplicadorStats()`
  devuelve `1.00` sin excepción; stats antiguos sin escalar (`hp=160`, `defHp=160`,
  `atk=105`).
- Payload nuevo: `3.00`; `defHp=480`, `atk=315`; roundtrip conserva `3.00`.
- `SESSION_VERSION` sigue sin cambios.

### NITs resueltos

- Tooltip del badge derivado de `bonusStatsJefe`; ya no fija «×3».
- Propiedad `private` con getter; sin escritura externa.

### Criterios verificados

- Vida ×10, stats base ×1, ×3 efectivo en los cinco stats: PASA.
- Barreras ×3 y HP sin ×3: PASA.
- `MultiplicadoresStats` neutro: PASA.
- Sin Núcleo/Coraza y sin fallback: PASA.
- Infraestructura de partes conservada: PASA.
- `bonusStatsJefe = 200` / 0: PASA.
- Compatibilidad de sesiones antiguas: PASA (corregido).
- Sin tests modificados, sin dependencias, sin migraciones: PASA.

### Riesgos residuales

- Sesiones de mazmorra ya iniciadas conservan el jefe antiguo hasta terminar el combate.
- `tests/Feature/Mazmorras/MazmorraApiTest.php` sigue obsoleto y fallará si se ejecuta;
  fuera de alcance.
- El equilibrio de dificultad no se valida con datos de juego.
- Cambios locales ajenos en `_pokemon-card.blade.php:3` y en las líneas de postura/clases
  de `CombatienteVista.php` no se imputan a TASK-055.

### Tests

No ejecutados. Verificación con `php -l`, PHPStan, Pint y simulación de serialización.
