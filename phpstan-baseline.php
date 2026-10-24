<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Console\\\\Commands\\\\CrudGenerateCommand\\:\\:handle\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Console/Commands/CrudGenerateCommand.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a ternary operator condition, string\\|null given\\.$#',
    'identifier' => 'ternary.condNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Console/Commands/CrudGenerateCommand.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Console/Commands/ProcesarExploraciones.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<Illuminate\\\\Database\\\\Eloquent\\\\Model\\>\\:\\:create\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<Illuminate\\\\Database\\\\Eloquent\\\\Model\\>\\:\\:findOrFail\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<Illuminate\\\\Database\\\\Eloquent\\\\Model\\>\\:\\:orderBy\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<Illuminate\\\\Database\\\\Eloquent\\\\Model\\>\\:\\:whereIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Model\\:\\:destroy\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\BaseCrudService\\:\\:search\\(\\) has parameter \\$filters with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\BaseCrudService\\:\\:search\\(\\) return type with generic interface Illuminate\\\\Contracts\\\\Pagination\\\\LengthAwarePaginator does not specify its types\\: TKey, TValue$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, string\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseCrudService.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\BaseDTO\\:\\:fromArray\\(\\) has parameter \\$data with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseDTO.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\BaseDTO\\:\\:toArray\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseDTO.php',
];
$ignoreErrors[] = [
    'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
    'identifier' => 'new.static',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseDTO.php',
];
$ignoreErrors[] = [
    'message' => '#^Variable property access on static\\(App\\\\Crud\\\\Base\\\\BaseDTO\\)\\.$#',
    'identifier' => 'property.dynamicName',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/BaseDTO.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\CrudGenerator\\:\\:generate\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\CrudGenerator\\:\\:generateFiles\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\CrudGenerator\\:\\:generateFiles\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\CrudGenerator\\:\\:makeStructure\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\CrudGenerator\\:\\:makeStructure\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ControllerGenerator\\:\\:make\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ControllerGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, string given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ControllerGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\DtoGenerator\\:\\:make\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/DtoGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ModelGenerator\\:\\:make\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ModelGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\RequestGenerator\\:\\:make\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/RequestGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:columnValue\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:foreignTable\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:form\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:index\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:isForeignKey\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:make\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:make\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\Generators\\\\ViewGenerator\\:\\:show\\(\\) has parameter \\$schema with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, string\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 2,
    'path' => __DIR__ . '/app/Crud/Base/Generators/ViewGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\PostgreSqlSchemaReader\\:\\:getColumns\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/PostgreSqlSchemaReader.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\PostgreSqlSchemaReader\\:\\:getRelations\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/PostgreSqlSchemaReader.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\PostgreSqlSchemaReader\\:\\:getSchema\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/PostgreSqlSchemaReader.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Crud\\\\Base\\\\PostgreSqlSchemaReader\\:\\:getTables\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/PostgreSqlSchemaReader.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Enums\\\\TipoEnum\\:\\:options\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Enums/TipoEnum.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/AuthController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$view of function view expects view\\-string\\|null, string given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/DashboardController.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Short ternary operator is not allowed\\. Use null coalesce operator if applicable or consider using long ternary\\.$#',
    'identifier' => 'ternary.shortNotAllowed',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>peligro" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access on non\\-nullable type Src\\\\Exploraciones\\\\Domain\\\\RolExploracion\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'id\' on array\\{id\\: int, name\\: string, icon\\: string\\} on left side of \\?\\? always exists and is not nullable\\.$#',
    'identifier' => 'nullCoalesce.offset',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'species_id\' on \\*NEVER\\* on left side of \\?\\? always exists and is not nullable\\.$#',
    'identifier' => 'nullCoalesce.offset',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>name" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/HabitatsController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Http\\\\Controllers\\\\IconoController\\:\\:shiny\\(\\) should return Illuminate\\\\Http\\\\Response but returns Symfony\\\\Component\\\\HttpFoundation\\\\BinaryFileResponse\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/IconoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Http\\\\Controllers\\\\IconoController\\:\\:show\\(\\) should return Illuminate\\\\Http\\\\Response but returns Symfony\\\\Component\\\\HttpFoundation\\\\BinaryFileResponse\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/IconoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Team\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/OnboardingController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/OnboardingController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:user\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/PlayerController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Reclutable\\>\\:\\:orderBy\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/PlayerController.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>name" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/PlayerController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\Reclutamiento\\\\Domain\\\\Collections\\\\OpcionEvolucionCollection\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\Reclutamiento\\\\Domain\\\\Collections\\\\RequisitoEvolucionCollection\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:id\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 8,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:user\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 4,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:globales\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:paraHabitat\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\PlayerInventory\\>\\:\\:lockForUpdate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 6,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:id\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 3,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, int\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\TeamMember\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 7,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in &&, App\\\\Models\\\\Pokedex\\|null given on the left side\\.$#',
    'identifier' => 'booleanAnd.leftNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/ActualizarPokedexJob.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/ActualizarPokedexJob.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to method get\\(\\) on an unknown class Illuminate\\\\Support\\\\EloquentCollection\\.$#',
    'identifier' => 'class.notFound',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/RecompilarHabitatJsonJob.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, App\\\\Models\\\\Habitat\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/RecompilarHabitatJsonJob.php',
];
$ignoreErrors[] = [
    'message' => '#^PHPDoc tag @var for variable \\$pokedexMap contains unknown class Illuminate\\\\Support\\\\EloquentCollection\\.$#',
    'identifier' => 'class.notFound',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/RecompilarHabitatJsonJob.php',
];
$ignoreErrors[] = [
    'message' => '#^PHPDoc tag @var with type Illuminate\\\\Support\\\\EloquentCollection\\<int, App\\\\Models\\\\Pokedex\\> is not subtype of type Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<\\(int\\|string\\), App\\\\Models\\\\Pokedex\\>\\.$#',
    'identifier' => 'varTag.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/RecompilarHabitatJsonJob.php',
];
$ignoreErrors[] = [
    'message' => '#^Variable \\$pokemonData in PHPDoc tag @var does not match assigned variable \\$pokemonIds\\.$#',
    'identifier' => 'varTag.differentVariable',
    'count' => 1,
    'path' => __DIR__ . '/app/Jobs/RecompilarHabitatJsonJob.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method combatants\\(\\) of class Src\\\\Battle\\\\Domain\\\\EquipoBatalla\\:
Frontera — migrar a combatientesCollection\\(\\)$#',
    'identifier' => 'method.deprecated',
    'count' => 3,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\View\\\\View\\:\\:extends\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\View\\\\View\\:\\:section\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Combate\\:\\:findPokemonViewData\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, Src\\\\Battle\\\\Domain\\\\Combatiente\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$currentMoves type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$log type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$rewards type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$team1 type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$team2 type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Property App\\\\Livewire\\\\Combate\\:\\:\\$turnQueue type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\CombateEntrenadores\\\\Domain\\\\DataTransferObjects\\\\DatosModalVictoria\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 2,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Presenters\\\\PresentadorResultadoBatalla\\:\\:procesarEntrenador\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Presenters\\\\PresentadorResultadoBatalla\\:\\:procesarGimnasio\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Presenters\\\\PresentadorResultadoBatalla\\:\\:procesarMazmorra\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Presenters\\\\PresentadorResultadoBatalla\\:\\:procesarRuta\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:count\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Models/Favorito.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Favorito.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:globales\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Favorito.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:paraHabitat\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Favorito.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Favorito\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Favorito.php',
];
$ignoreErrors[] = [
    'message' => '#^Class App\\\\Models\\\\Habitat uses generic trait Illuminate\\\\Database\\\\Eloquent\\\\Factories\\\\HasFactory but does not specify its types\\: TFactory$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Habitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Class App\\\\Models\\\\Pokemon uses generic trait Illuminate\\\\Database\\\\Eloquent\\\\Factories\\\\HasFactory but does not specify its types\\: TFactory$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Pokemon.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Models\\\\PokemonEvolution\\:\\:evolvedSpecies\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/PokemonEvolution.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Models\\\\PokemonStat\\:\\:pokemon\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/PokemonStat.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Models\\\\PokemonType\\:\\:pokemon\\(\\) has no return type specified\\.$#',
    'identifier' => 'missingType.return',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/PokemonType.php',
];
$ignoreErrors[] = [
    'message' => '#^Class App\\\\Models\\\\Province uses generic trait Illuminate\\\\Database\\\\Eloquent\\\\Factories\\\\HasFactory but does not specify its types\\: TFactory$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Province.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Team.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:whereIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Team.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\TeamMember\\>\\:\\:pluck\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/Team.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\<0, max\\>\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/app/Models/User.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Session\\\\Store\\:\\:forget\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 6,
    'path' => __DIR__ . '/app/Support/BattleSessionService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Session\\\\Store\\:\\:put\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Support/BattleSessionService.php',
];
$ignoreErrors[] = [
    'message' => '#^Instanceof between Src\\\\Battle\\\\Domain\\\\AgregadoBatalla and Src\\\\Battle\\\\Domain\\\\AgregadoBatalla will always evaluate to true\\.$#',
    'identifier' => 'instanceof.alwaysTrue',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/BattleSessionService.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Support\\\\BattleSessionService\\:\\:cargarMeta\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/BattleSessionService.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Support\\\\BattleSessionService\\:\\:guardarMeta\\(\\) has parameter \\$meta with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/BattleSessionService.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:orderBy\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/app/Support/ItemCatalogo.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>name" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$array \\(list\\<Src\\\\Battle\\\\Domain\\\\AI\\\\ValueObjects\\\\EvaluacionAmenaza\\>\\) of array_values is already a list, call has no effect\\.$#',
    'identifier' => 'arrayValues.list',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/AnalizadorAmenazasImpl.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\AI\\\\EvaluadorAccionIAImpl\\:\\:calcularReduccionAmenaza\\(\\) has parameter \\$amenazas with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/EvaluadorAccionIAImpl.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Battle\\\\Domain\\\\AI\\\\MemoriaCombateIA\\:\\:\\$pesos is never read, only written\\.$#',
    'identifier' => 'property.onlyWritten',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/MemoriaCombateIA.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Battle\\\\Domain\\\\AI\\\\SelectorAccionIA\\:\\:\\$evaluadorPosicion is never read, only written\\.$#',
    'identifier' => 'property.onlyWritten',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/SelectorAccionIA.php',
];
$ignoreErrors[] = [
    'message' => '#^Match arm comparison between Src\\\\Battle\\\\Domain\\\\Enums\\\\TipoClima\\:\\:TURBULENCIAS and Src\\\\Battle\\\\Domain\\\\Enums\\\\TipoClima\\:\\:TURBULENCIAS is always true\\.$#',
    'identifier' => 'match.alwaysTrue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Chain/ManejadorClima.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\Combatiente\\:\\:__unserialize\\(\\) has parameter \\$data with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Combatiente.php',
];
$ignoreErrors[] = [
    'message' => '#^Instanceof between Src\\\\Battle\\\\Domain\\\\MovimientoBatalla and Src\\\\Battle\\\\Domain\\\\MovimientoBatalla will always evaluate to true\\.$#',
    'identifier' => 'instanceof.alwaysTrue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/DatosPokemonBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Instanceof between Src\\\\Shared\\\\Tipos\\\\TipoPokemon and Src\\\\Shared\\\\Tipos\\\\TipoPokemon will always evaluate to true\\.$#',
    'identifier' => 'instanceof.alwaysTrue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/DatosPokemonBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Effects/ColeccionEfectos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\Effects\\\\ColeccionEfectos\\:\\:all\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Effects/ColeccionEfectos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:procesarConfusion\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:procesarFreeze\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:procesarParalysis\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:procesarSleep\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:resultadoLimpio\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Class Src\\\\Battle\\\\Domain\\\\ValueObjects\\\\ColeccionMovimientos implements generic interface ArrayAccess but does not specify its types\\: TKey, TValue$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/ValueObjects/ColeccionMovimientos.php',
];
$ignoreErrors[] = [
    'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/ValueObjects/ColeccionMovimientos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\ValueObjects\\\\ColeccionMovimientos\\:\\:getIterator\\(\\) return type with generic class ArrayIterator does not specify its types\\: TKey, TValue$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/ValueObjects/ColeccionMovimientos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Presentation\\\\DTOAccionBatalla\\:\\:fromLivewire\\(\\) has parameter \\$value with no type specified\\.$#',
    'identifier' => 'missingType.parameter',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Presentation/DTOAccionBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Presentation\\\\DTOAccionBatalla\\:\\:toLivewire\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Presentation/DTOAccionBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
    'identifier' => 'new.static',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Presentation/DTOAccionBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to bool something that\'s already bool\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/App/ConstruirEquipoJugador.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\<0, max\\>\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/App/ConstruirEquipoJugador.php',
];
$ignoreErrors[] = [
    'message' => '#^Short ternary operator is not allowed\\. Use null coalesce operator if applicable or consider using long ternary\\.$#',
    'identifier' => 'ternary.shortNotAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/App/ConstruirEquipoJugador.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 2,
    'path' => __DIR__ . '/src/CombateEntrenadores/App/MapeadorPokemonBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\TrainerCombatLog\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/Infra/EloquentEntrenadorLogRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Equipos\\\\Domain\\\\TeamAggregate\\:\\:__construct\\(\\) has parameter \\$formacion with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Equipos/Domain/TeamAggregate.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\TeamMember\\>\\:\\:delete\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/src/Equipos/Infra/EloquentTeamRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\TeamMember\\>\\:\\:whereNotIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Equipos/Infra/EloquentTeamRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to float something that\'s already float\\.$#',
    'identifier' => 'cast.useless',
    'count' => 3,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'barrera_especial\' on array\\{hp\\: float, barrera_fisica\\: float, barrera_especial\\: float\\} on left side of \\?\\? always exists and is not nullable\\.$#',
    'identifier' => 'nullCoalesce.offset',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'barrera_fisica\' on array\\{hp\\: float, barrera_fisica\\: float, barrera_especial\\: float\\} on left side of \\?\\? always exists and is not nullable\\.$#',
    'identifier' => 'nullCoalesce.offset',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'hp\' on array\\{hp\\: float, barrera_fisica\\: float, barrera_especial\\: float\\} on left side of \\?\\? always exists and is not nullable\\.$#',
    'identifier' => 'nullCoalesce.offset',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FabricaCapacidadesStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FinalizarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>peligro" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FinalizarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Using nullsafe property access "\\?\\-\\>peligro" on left side of \\?\\? is unnecessary\\. Use \\-\\> instead\\.$#',
    'identifier' => 'nullsafe.neverNull',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:whereIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/App/GeneradorPokemonGimnasio.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Domain\\\\Repositories\\\\GymCatalogoRepositoryInterface\\:\\:insertar\\(\\) has parameter \\$equipos with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Domain/Repositories/GymCatalogoRepositoryInterface.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/AdminGymController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 3,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/AdminGymController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Infra\\\\Controllers\\\\AdminGymController\\:\\:serializar\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/AdminGymController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Infra\\\\Controllers\\\\AdminGymController\\:\\:serializarEquipoEtapa\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/AdminGymController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\Gimnasios\\\\Domain\\\\Collections\\\\GimnasioResumenCollection\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/GimnasioController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\Gimnasios\\\\Domain\\\\DataTransferObjects\\\\DetalleGimnasio\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/GimnasioController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/GimnasioController.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 3,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymCatalogoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\GymStage\\>\\:\\:pluck\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymCatalogoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Infra\\\\EloquentGymCatalogoRepository\\:\\:insertar\\(\\) has parameter \\$equipos with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymCatalogoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Infra\\\\EloquentGymCatalogoRepository\\:\\:persistirEtapas\\(\\) has parameter \\$equipos with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymCatalogoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\GymProgress\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymProgressRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\App\\\\ObtenerPokemonsPorHabitat\\:\\:handle\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/App/ObtenerPokemonsPorHabitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/src/Habitats/App/ValidadorExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\ExploracionActiva\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 3,
    'path' => __DIR__ . '/src/Habitats/App/ValidadorExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\Domain\\\\HabitatEntity\\:\\:toArray\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Domain/HabitatEntity.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\Domain\\\\HabitatsCollection\\:\\:toArray\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Domain/HabitatsCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\Domain\\\\ProvinceEntity\\:\\:toArray\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Domain/ProvinceEntity.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\Domain\\\\ProvinciasCollection\\:\\:toArray\\(\\) return type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Domain/ProvinciasCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Access to an undefined property App\\\\Models\\\\Pokemon\\:\\:\\$pivot\\.$#',
    'identifier' => 'property.notFound',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Called \'pluck\' on Laravel collection, but could have been retrieved as a query\\.$#',
    'identifier' => 'larastan.noUnnecessaryCollectionCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 2,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to string something that\'s already string\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:distinct\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:select\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:whereNotIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
    'identifier' => 'new.static',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliasDisponibles.php',
];
$ignoreErrors[] = [
    'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
    'identifier' => 'new.static',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliasSinHabitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Huevo\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/CrearHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Strict comparison using \\=\\=\\= between int and null will always evaluate to false\\.$#',
    'identifier' => 'identical.alwaysFalse',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/CrearHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\<0, max\\>\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/EclosionarHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Huevo\\>\\:\\:orderByDesc\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Huevo\\>\\:\\:whereNotNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Huevo\\>\\:\\:whereNull\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:id\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 3,
    'path' => __DIR__ . '/src/Huevos/Infra/Controllers/HuevoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Contracts\\\\Auth\\\\Guard\\:\\:user\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/Infra/Controllers/HuevoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/Infra/Controllers/HuevoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to deprecated method toArray\\(\\) of class Src\\\\Mazmorras\\\\Domain\\\\DataTransferObjects\\\\ResultadoPisosMazmorra\\:
Usar las propiedades tipadas\\.$#',
    'identifier' => 'method.deprecated',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/Controllers/MazmorraController.php',
];
$ignoreErrors[] = [
    'message' => '#^Casting to int something that\'s already int\\.$#',
    'identifier' => 'cast.useless',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/Controllers/MazmorraController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Http\\\\Request\\:\\:validate\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/Controllers/MazmorraController.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\DungeonLog\\>\\:\\:exists\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/EloquentDungeonProgresoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Mazmorras\\\\Infra\\\\EloquentDungeonProgresoRepository\\:\\:cooldownHasta\\(\\) should return Illuminate\\\\Support\\\\Carbon\\|null but returns Carbon\\\\Carbon\\|null\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/EloquentDungeonProgresoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioCaptura.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\PlayerInventory\\>\\:\\:whereIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\Pokemon\\>\\:\\:whereIn\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Dynamic call to static method Illuminate\\\\Database\\\\Eloquent\\\\Builder\\<App\\\\Models\\\\PokemonEvolution\\>\\:\\:orderBy\\(\\)\\.$#',
    'identifier' => 'staticMethod.dynamicCall',
    'count' => 2,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Collections\\\\IntCollection\\:\\:validateType\\(\\) has parameter \\$item with no type specified\\.$#',
    'identifier' => 'missingType.parameter',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Collections/IntCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Class Src\\\\Shared\\\\Domain\\\\Collection implements generic interface IteratorAggregate but does not specify its types\\: TKey, TValue$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
    'identifier' => 'empty.notAllowed',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:__construct\\(\\) has parameter \\$items with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:add\\(\\) has parameter \\$item with no type specified\\.$#',
    'identifier' => 'missingType.parameter',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:getIterator\\(\\) return type with generic class ArrayIterator does not specify its types\\: TKey, TValue$#',
    'identifier' => 'missingType.generics',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:validateType\\(\\) has parameter \\$item with no type specified\\.$#',
    'identifier' => 'missingType.parameter',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^PHPDoc tag @param for parameter \\$extractor with type \\(callable\\)\\|int is not subtype of native type callable\\.$#',
    'identifier' => 'parameter.phpDocType',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Shared\\\\Domain\\\\Collection\\:\\:\\$items type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Unable to resolve the template type TPluck in call to method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\)$#',
    'identifier' => 'argument.templateType',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
    'identifier' => 'new.static',
    'count' => 2,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Tipos\\\\TiposCollection\\:\\:__construct\\(\\) has parameter \\$items with no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Tipos/TiposCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Shared\\\\Tipos\\\\TypeChart\\:\\:\\$chart type has no value type specified in iterable type array\\.$#',
    'identifier' => 'missingType.iterableValue',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Tipos/TypeChart.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
