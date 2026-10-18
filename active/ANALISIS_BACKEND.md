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
- **Hooks**: `.githooks/pre-commit` (whitespace + Pint sobre PHP staged) y
  `.githooks/pre-push` (`composer run analyse`), POSIX sh y ejecutables.
- **Scripts composer**: `analyse`, `analyse:hard`, `arch`, `lint`, `hooks:install`, `check`.
  `check` no incluye `@arch` mientras deptrac tenga violaciones preexistentes.

## Resultados de la implementación

```text
PHPStan L6: 282 errores (2 de config muerta) -> baseline 280 -> [OK] No errors     (exit 0)
PHPStan L8: 196 errores adicionales           -> baseline 196 -> [OK] No errors     (exit 0)
deptrac 4.7.2: Violations 71 · Uncovered 1126 · Allowed 572 · 0 warnings/errors     (exit 1)
Pint: baselines excluidos; 588 ficheros, 2 issues preexistentes ajenos              (exit 1)
```

Violaciones deptrac (todas con destino `Presentation`): **Infra → Presentation 32**,
**App → Presentation 17**, **Domain → Presentation 16**. Confirma el pendiente 12
(`AgregadoBatalla` → `DTOAccionBatalla`) y amplía el alcance a `Habitats` y `Exploraciones`.
No se arreglan (fuera de alcance). Por eso deptrac no entra en hooks ni en `composer check`.

Pint marca dos problemas preexistentes ajenos a esta tarea: `database/seeders/CatalogoSeeder.php`
(`no_unused_imports`) y `tests/Feature/HabitatsViewTest.php` (`single_blank_line_at_eof`).

## Comportamientos a cubrir en el testing futuro

No aplica lógica de aplicación. La verificación de esta tarea es la ejecución de las propias
herramientas (L6, L8, deptrac, Pint, hooks), ya realizada. En el cierre del módulo tocará:

- **Unit/Feature**: ninguno (testing PHPUnit diferido, no se han tocado tests).

## Riesgos

- El baseline congela 476 errores preexistentes (280 L6 + 196 L8). Cualquier error nuevo falla.
- Los baselines se generaron con el WIP ajeno presente. `app/Support/AvistamientosPokedex.php` y
  `app/Support/CargadorPokemonsDerrotados.php` se analizaron sin generar entradas; al aterrizar
  el WIP habrá que regenerar el baseline si cambian.
- `composer.lock` arrastra actualizaciones transitivas de Symfony/php-parser (`--with-all-dependencies`).
  `phpstan/phpstan` se restauró a 2.2.9 con actualización dirigida.
- deptrac deja 1126 tokens uncovered (legado `app/*` y `src/Shared/*`): las dependencias hacia
  esos tokens no se auditan todavía.
