# Análisis previo — Tooling de calidad: PHPStan 2.x, deptrac 4, hooks y scripts

## Objetivo

Sanear el pendiente 17 (PHPStan/deptrac/baselines/CRAP) y dejarlo operativo en el flujo
local: configuración válida de PHPStan 2.2.9 con baselines que congele la deuda preexistente,
migración del shim abandonado `qossmic/deptrac-shim` a `deptrac/deptrac` 4, hooks Git de
pre-commit/pre-push y scripts `composer` de calidad. Sin tocar código de `app/` ni `src/`,
sin ejecutar PHPUnit (testing diferido) y sin tocar el WIP de otra sesión.

## Diagnóstico (Fase 1)

### 1. Versión de PHPStan

```text
$ vendor/bin/phpstan --version
PHPStan - PHP Static Analysis Tool 2.2.9        # exit 0
```

Tras `composer require --dev deptrac/deptrac:^4.0 --with-all-dependencies`, Composer elevó
transitivamente `phpstan/phpstan` a 2.3.1. Se restauró 2.2.9 con
`composer update phpstan/phpstan:2.2.9 --with-dependencies` (actualización dirigida, no general)
para no introducir un cambio de analizador no aprobado. Versión final verificada: **2.2.9**.

### 2. PHPStan L6 (antes)

```text
$ vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
[ERROR] Found 282 errors                       # exit 1
```

- **280 errores reales** (tipado, genéricos, llamadas dinámicas a Eloquent…).
- **2 errores de configuración muerta**: `Ignored error pattern ... was not matched` para los
  `ignoreErrors` con `path: tests/*`. `paths` solo contiene `src/` y `app/`, y además `tests/`
  está excluido: esos patrones nunca pueden casar.
- `excludePaths.analyse` incluye `tests/Unit/Infra (?)` y `tests/Feature/Infra (?)`:
  entradas muertas (subsumidas por `tests/`; el sufijo `(?)` solo evita el aviso de ruta
  inexistente).

### 3. PHPStan L8 (antes)

```text
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon --memory-limit=512M
Invalid configuration:
Unexpected item 'parameters › checkMissingIterableValueType'.
Unexpected item 'parameters › checkGenericClassInNonGenericObjectType'.
Unexpected item 'parameters › checkAlwaysTrueInstanceof'.
Unexpected item 'parameters › checkAlwaysTrueStrictComparison'.
                                                    # exit 1
```

PHPStan 2.x eliminó esos cuatro parámetros (los comportamientos asociados pasan a estar
siempre activos). `treatPhpDocTypesAsCertain` y `reportUnmatchedIgnoredErrors` siguen siendo
válidos en 2.x.

### 4. deptrac con el shim (antes)

```text
$ vendor/bin/deptrac --version
deptrac 1.0.2                                   # exit 0

$ vendor/bin/deptrac analyse
Syntax Error on File .../src/Battle/Domain/ValueObjects/MultiplicadoresStats.php
Syntax error, unexpected T_STRING, expecting '=' on line 22
Report: Violations 0 · Uncovered 0 · Allowed 0  # exit 0
```

El shim no parsea constantes tipadas (`public const float`). El «0 violaciones» era un falso
verde: `exclude_files` vaciaba el análisis (`src/*/Infra/*`, `app/Http/*`, `app/Console/*`,
`app/Exceptions/*`, `app/Providers/*`…).

### 5. Diagnóstico de capas vs. realidad

- `src/<Modulo>/Domain/` en todos los módulos.
- `src/<Modulo>/App/` + `Infra/` (canónico actual) y `src/Battle/Infrastructure/` +
  `Infrastructure/`/`Presentation/` (legado); `src/Exploraciones/`, `src/Habitats/` y
  `src/Battle/` conservan `Presentation/`.
- Legado Laravel en `app/`: `Http`, `Livewire`, `Console`, `View`; además `Models`, `Jobs`,
  `Support`, `Datagrid`, `Crud`, `Bus`, `Enums` (fuera de las capas DDD, quedan uncovered).
- `src/Shared/`: `Bus`, `Collections`, `Domain`, `Tipos`, `UI`; solo `Shared\Domain` cae en
  la capa Domain.

El `deptrac.yaml` previo no colectaba `App`/`Infrastructure` y excluía las capas reales, por
lo que no podía detectar nada.

### 6. CRAP y cobertura

```text
$ vendor/bin/phpunit --help | grep coverage-crap4j
  --coverage-crap4j <file>             Write code coverage report in Crap4J XML format to file
```

PHPUnit 11.5.55 soporta `--coverage-crap4j`. El CRAP necesita cobertura, que pertenece a la
fase de testing diferida. Receta para documentar en el cierre (no ejecutada):

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-crap4j build/crap4j.xml
# requiere extensión de cobertura (Xdebug/PCOV) y <source> en phpunit.xml
```

## Diseño

- **PHPStan**: `phpstan-baseline.php` (L6) incluido desde `phpstan.neon` y
  `phpstan-hardener-baseline.php` (errores exclusivos de L8) incluido desde
  `phpstan-hardener.neon`, que a su vez incluye el anterior. Los baselines congelan la deuda:
  no se añaden entradas nuevas y se regeneran al reducirla.
- **deptrac 4**: `classNameRegex` (sustituye a `className`; usa `value` con regex PCRE
  delimitada) y reglas estrictas Domain → ∅, Application → Domain, Infrastructure →
  Domain+Application, Presentation → todas. `exclude_files` reducido a `tests/*` y `vendor/*`.
- **Hooks**: `.githooks/pre-commit` (whitespace + Pint sobre PHP staged, excluyendo los
  baselines generados) y `.githooks/pre-push` (`composer run analyse`), POSIX sh y ejecutables.
- **Scripts composer**: `analyse`, `analyse:hard`, `arch`, `lint`, `hooks:install`, `check`.
  `check` no incluye `@arch` mientras deptrac tenga violaciones preexistentes.

## Resultados de la implementación

```text
PHPStan L6: 282 errores (2 de config muerta) -> baseline 280 -> 279 tras limpiar WIP
PHPStan L8: 196 errores adicionales           -> baseline 196 (sin cambio tras limpiar WIP)
L6/L8 en worktree limpio: [OK] No errors                                     (exit 0)
deptrac 4.7.2: Violations 71 · Uncovered 1126 · Allowed 572 · 0 warnings     (exit 1)
Pint: baselines excluidos; 588 ficheros PASS                                 (exit 0)
composer lint: PASS                                                          (exit 0)
Árbol actual con WIP: 1 error esperado en PlayerController:126 (L6 y L8)     (exit 1)
```

### Inventario deptrac completo (71 violaciones)

Las 71 violaciones tienen destino `Presentation`. El desglose anterior (32+17+16=65) era
incompleto: omitía 6 dependencias de controladores de módulo hacia el `Controller` base de
Laravel (`App\Http\Controllers\Controller`), que el colector clasifica como `Presentation`
(legado). Pares de capas reales:

| Origen → destino | Nº |
|---|---:|
| Infrastructure → Presentation | 38 |
| Application → Presentation | 17 |
| Domain → Presentation | 16 |
| **Total** | **71** |

Tokens destino:

| Token destino | Nº |
|---|---:|
| `Src\Exploraciones\Presentation\DTOObjetoPerdido` | 7 |
| `App\Http\Controllers\Controller` (base Laravel) | 6 |
| `Src\Habitats\Presentation\DTOFamiliaDisponible` | 6 |
| `Src\Habitats\Presentation\DTOFamiliasDisponibles` | 6 |
| `Src\Habitats\Presentation\DTOHabitatDetalle` | 6 |
| `Src\Habitats\Presentation\DTOPokemonFamilia` | 6 |
| `Src\Habitats\Presentation\DTOFamiliaEliminada` | 5 |
| `Src\Habitats\Presentation\DTOPokemonNivelActualizado` | 5 |
| `Src\Habitats\Presentation\DTOFamiliasSinHabitat` | 5 |
| `Src\Battle\Presentation\DTOAccionBatalla` | 3 |
| `Src\Exploraciones\Presentation\DTOIncidentesExploracion` | 3 |
| `Src\Habitats\Presentation\ColeccionPokemonFamilia` | 3 |
| `Src\Habitats\Presentation\DTOPokemonTipo` | 3 |
| `Src\Battle\Presentation\DTOResultadoDanio` | 2 |
| `Src\Habitats\Presentation\DTOFamiliaSinHabitat` | 2 |
| `Src\Habitats\Presentation\ColeccionTiposFamilia` | 2 |
| `Src\Exploraciones\Presentation\TransformadorResultadoExploracion` | 1 |
| **Total** | **71** |

Ficheros origen:

| Fichero origen | Capas | Nº |
|---|---|---:|
| `src/Habitats/Infra/HabitatRepository.php` | Infra → Presentation | 32 |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | App → Presentation | 11 |
| `src/Habitats/Domain/Repositories/HabitatRepositoryInterface.php` | Domain → Presentation | 6 |
| `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php` | Domain → Presentation | 5 |
| `src/Battle/Domain/AgregadoBatalla.php` | Domain → Presentation | 3 |
| `src/Battle/Domain/ServicioEjecucionBatalla.php` | Domain → Presentation | 2 |
| `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php` | Infra → Presentation | 1 |
| `src/CombateRuta/Infra/Controllers/CombateRutaController.php` | Infra → Presentation | 1 |
| `src/Gimnasios/Infra/Controllers/AdminGymController.php` | Infra → Presentation | 1 |
| `src/Gimnasios/Infra/Controllers/GimnasioController.php` | Infra → Presentation | 1 |
| `src/Habitats/App/AsignarFamiliaAHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/EliminarFamiliaDeHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/MoverPokemonDeNivel.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerFamiliasDisponibles.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerFamiliasSinHabitat.php` | App → Presentation | 1 |
| `src/Habitats/App/ObtenerHabitatDetalle.php` | App → Presentation | 1 |
| `src/Huevos/Infra/Controllers/HuevoController.php` | Infra → Presentation | 1 |
| `src/Mazmorras/Infra/Controllers/MazmorraController.php` | Infra → Presentation | 1 |

Los 6 controladores de módulo (`Entrenador`, `CombateRuta`, `AdminGym`, `Gimnasio`, `Huevo`,
`Mazmorra`) extienden el base `App\Http\Controllers\Controller`. Confirma el pendiente 12
(`AgregadoBatalla` → `DTOAccionBatalla`, 3 usos) y amplía la deuda a `Habitats` y
`Exploraciones`. No se arregla (fuera de alcance); por eso deptrac no entra en hooks ni en
`composer check`.

### Iteración QA — baselines regenerados contra el árbol limpio

El baseline anterior se había generado con el WIP de `PlayerController.php` presente. Se
regeneró en un worktree detached de `4d657f5` (`/tmp/pokemon-baseline`), sin el WIP:

```text
$ git worktree add /tmp/pokemon-baseline 4d657f5                  # exit 0
$ composer install --no-scripts --no-interaction                  # exit 0
  (primer intento de PHPStan falló: Laravel no arrancaba sin .env; se copió .env del repo
   principal y se repitió)
$ vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline=phpstan-baseline.php
[OK] Baseline generated with 279 errors                           # exit 0 (antes 280)
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon --generate-baseline=...
[OK] Baseline generated with 196 errors                           # exit 0 (antes 196)
$ vendor/bin/phpstan analyse -c phpstan.neon
[OK] No errors                                                    # exit 0
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon
[OK] No errors                                                    # exit 0
```

Diferencia entrada a entrada (comparando mensaje/path/count):

| Nivel | Antes | Después | Eliminadas | Añadidas |
|---|---:|---:|---:|---:|
| L6 | 280 errores (222 entradas) | 279 errores (221 entradas) | 1 | 0 |
| L8 | 196 errores (157 entradas) | 196 errores (157 entradas) | 0 | 0 |

La única entrada contaminada era de L6:
`PlayerController.php` → `Dynamic call to static method Guard::id()` (WIP). Las otras 3
entradas de `PlayerController` del baseline **no** eran WIP: `Guard::user()`,
`Builder<Reclutable>::orderBy()` y el nullsafe `?->name` existen en la versión commiteada
(`git show 4d657f5:app/Http/Controllers/PlayerController.php`, líneas 49, 54 y login). La
entrada de L8 (`SinergiaEquipo::sinergiaPara($roles)`) tampoco era WIP: la versión commiteada
ya llama a `sinergiaPara` en la línea 80. Por tanto: **L6 4→1 entrada eliminada; L8 0**.

En el árbol de trabajo actual (con el WIP) el resultado esperado es:

```text
$ vendor/bin/phpstan analyse -c phpstan.neon
  app/Http/Controllers/PlayerController.php:126
  Dynamic call to static method Illuminate\Contracts\Auth\Guard::id().
[ERROR] Found 1 error                                             # exit 1
$ vendor/bin/phpstan analyse -c phpstan-hardener.neon
  (el mismo error) [ERROR] Found 1 error                          # exit 1
```

Es deuda del WIP hasta que aterrice; no se toca ni se excluye. Si bloquea un push puntual,
el escape documentado es `git push --no-verify`; el pre-commit equivalente es
`git commit --no-verify`. Cuando el WIP aterrice, regenerar baselines si cambian sus errores.
El worktree se eliminó (`git worktree remove --force` + `git worktree prune`).

## Hallazgos menores de la iteración QA

- **`.deptrac.cache` trackeado**: confirmado con `git ls-files .deptrac.cache`. Se destrackeó
  con `git rm --cached .deptrac.cache`; el fichero sigue en disco y ya estaba en `.gitignore`.
  Así `deptrac analyse` deja de ensuciar `git status`.
- **Pint en ficheros ajenos**: `database/seeders/CatalogoSeeder.php` (import del propio
  namespace sin uso) y `tests/Feature/HabitatsViewTest.php` (falta newline final) normalizados
  con Pint, sin cambios de lógica. `pint --test` global y `composer lint` quedan en verde
  (588 ficheros, exit 0).
- **Borde `pint.json notPath` + pre-commit**: al pasar ficheros explícitos, Pint no aplica
  `notPath`; el hook ahora filtra `phpstan-baseline.php` y `phpstan-hardener-baseline.php`
  antes de invocar `pint --test`, de modo que un stage de baselines regenerados no bloquea.
  `sh -n .githooks/pre-commit` OK.

## Comportamientos a cubrir en el testing futuro

No aplica lógica de aplicación. La verificación de esta tarea es la ejecución de las propias
herramientas (L6, L8, deptrac, Pint, hooks), ya realizada. En el cierre del módulo tocará:

- **Unit/Feature**: ninguno (testing PHPUnit diferido, no se han tocado tests).

## Riesgos

- El baseline congela 475 errores preexistentes (279 L6 + 196 L8). Cualquier error nuevo falla.
- El árbol actual tiene **1 error esperado** de PHPStan por el WIP de `PlayerController`
  (`Guard::id()` en línea 126); desaparecerá al aterrizar ese cambio. No se regenera el
  baseline hasta entonces.
- `composer check` (`@analyse` + `@lint`) queda verde en árbol limpio, pero en el árbol actual
  fallará por ese error WIP (esperado); `composer lint` ya es verde.
- `composer.lock` arrastra actualizaciones transitivas de Symfony/php-parser
  (`--with-all-dependencies`). `phpstan/phpstan` se restauró a 2.2.9 con actualización dirigida.
- deptrac deja 1126 tokens uncovered (legado `app/*` y `src/Shared/*`): las dependencias hacia
  esos tokens no se auditan todavía. Las 71 violaciones son deuda preexistente (pendiente 12 +
  DTOs de `Presentation` en `Habitats`/`Exploraciones` + base Controller).
