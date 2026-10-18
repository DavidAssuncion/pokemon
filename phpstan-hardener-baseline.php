<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Argument of an invalid type list\\<string\\>\\|false supplied for foreach, only iterables are supported\\.$#',
    'identifier' => 'foreach.nonIterable',
    'count' => 1,
    'path' => __DIR__ . '/app/Crud/Base/CrudGenerator.php',
];
$ignoreErrors[] = [
    'message' => '#^Argument of an invalid type list\\<string\\>\\|false supplied for foreach, only iterables are supported\\.$#',
    'identifier' => 'foreach.nonIterable',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/DashboardController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on App\\\\Models\\\\ExploracionActiva\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method nivel\\(\\) on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Http\\\\Controllers\\\\ExploracionActivaController\\:\\:poolParaEstimador\\(\\) should return list\\<array\\{id\\: int, capture_rate\\: int, hatch\\: int\\|null, tipos\\: list\\<Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\>, stats\\: list\\<array\\{stat\\: int, effort\\: int\\}\\>, base_experience\\: int, evolution_chain_id\\: int\\|null, species_id\\: int\\}\\> but returns array\\<int, array\\{id\\: int, capture_rate\\: int, hatch\\: int\\|null, tipos\\: array\\<int, Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ACERO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:AGUA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:BICHO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:DRAGON\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ELECTRICO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:FANTASMA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:FUEGO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:HADA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:HIELO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:LUCHA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:NORMAL\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:PLANTA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:PSIQUICO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ROCA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:SINIESTRO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:TIERRA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:VENENO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:VOLADOR\\>, stats\\: array\\<int, array\\{stat\\: 1\\|2\\|3\\|4\\|5\\|6, effort\\: int\\}\\>, base_experience\\: int, evolution_chain_id\\: int\\|null, species_id\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$user of static method Src\\\\Exploraciones\\\\App\\\\FabricaCapacidadesStats\\:\\:desdeReclutado\\(\\) expects App\\\\Models\\\\User, App\\\\Models\\\\User\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ExploracionActivaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, int\\|false given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/IconoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$roles of static method Src\\\\Exploraciones\\\\Domain\\\\SinergiaEquipo\\:\\:sinergiaPara\\(\\) expects list\\<Src\\\\Exploraciones\\\\Domain\\\\RolExploracion\\>, array\\<int, Src\\\\Exploraciones\\\\Domain\\\\RolExploracion\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/PlayerController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$behavior on App\\\\Models\\\\Reclutado\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method conStat\\(\\) on Src\\\\Pokemon\\\\Domain\\\\Stats\\\\StatsValue\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method expTipo\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method sumarExpTipo\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method valorDe\\(\\) on Src\\\\Pokemon\\\\Domain\\\\Stats\\\\StatsValue\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, bool\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 3,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:opcionesEvolucion\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:siguienteEvolucion\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$reclutado of static method App\\\\Support\\\\ReclutadoSerializer\\:\\:serializar\\(\\) expects App\\\\Models\\\\Reclutado, App\\\\Models\\\\Reclutado\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$userId of job class App\\\\Jobs\\\\ActualizarPokedexJob constructor expects int in App\\\\Jobs\\\\ActualizarPokedexJob\\:\\:dispatch\\(\\), int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$user of static method Src\\\\Exploraciones\\\\App\\\\FabricaCapacidadesStats\\:\\:desdeReclutado\\(\\) expects App\\\\Models\\\\User, App\\\\Models\\\\User\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$userId of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:puedeEvolucionar\\(\\) expects int, int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$userId of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:puedeEvolucionarPara\\(\\) expects int, int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$userId of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:requisitos\\(\\) expects int, int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$userId of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:requisitosDeOpciones\\(\\) expects int, int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutadoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Access to an undefined property App\\\\Models\\\\Reclutable\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Reclutable\\>\\:\\:\\$cantidad\\.$#',
    'identifier' => 'property.notFound',
    'count' => 3,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Access to an undefined property App\\\\Models\\\\Reclutable\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Reclutable\\>\\:\\:\\$pokemon_id\\.$#',
    'identifier' => 'property.notFound',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to an undefined method App\\\\Models\\\\Reclutable\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Reclutable\\>\\:\\:decrement\\(\\)\\.$#',
    'identifier' => 'method.notFound',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to an undefined method App\\\\Models\\\\Reclutable\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Reclutable\\>\\:\\:delete\\(\\)\\.$#',
    'identifier' => 'method.notFound',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$reclutables of method App\\\\Http\\\\Controllers\\\\ReclutamientoController\\:\\:otorgarCaramelos\\(\\) expects array\\<int, App\\\\Models\\\\Reclutable\\>, array\\<int, App\\\\Models\\\\Reclutable\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Reclutable\\>\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$userId of method App\\\\Http\\\\Controllers\\\\ReclutamientoController\\:\\:otorgarCaramelos\\(\\) expects int\\|null, int\\|string\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/ReclutamientoController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to an undefined method App\\\\Models\\\\TeamMember\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\TeamMember\\>\\:\\:delete\\(\\)\\.$#',
    'identifier' => 'method.notFound',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Call to an undefined method App\\\\Models\\\\Team\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\Team\\>\\:\\:isExploring\\(\\)\\.$#',
    'identifier' => 'method.notFound',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, bool\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 2,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$member of method App\\\\Http\\\\Controllers\\\\TeamController\\:\\:ejecutarCambioBehavior\\(\\) expects App\\\\Models\\\\TeamMember, App\\\\Models\\\\TeamMember\\|Illuminate\\\\Database\\\\Eloquent\\\\Collection\\<int, App\\\\Models\\\\TeamMember\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Http/Controllers/TeamController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Combate\\:\\:buildTurnQueue\\(\\) should return list\\<array\\{team\\: int, index\\: int\\}\\> but returns list\\<array\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Combate.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Livewire\\\\Presenters\\\\PresentadorResultadoBatalla\\:\\:procesar\\(\\) should return array\\{log\\: list\\<string\\>, rewards\\: array\\<string, mixed\\>, habitatId\\: int\\} but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: bool, Closure\\(Src\\\\Battle\\\\Domain\\\\Combatiente\\)\\: bool given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: int, Closure\\(Src\\\\Battle\\\\Domain\\\\Combatiente\\)\\: int given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/app/Livewire/Presenters/PresentadorResultadoBatalla.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method toArray\\(\\) on Src\\\\Pokemon\\\\Domain\\\\Stats\\\\StatsValue\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 3,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 3,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Support\\\\ReclutadoSerializer\\:\\:statsDe\\(\\) should return list\\<array\\{name\\: string, value\\: int\\}\\> but returns array\\<int, array\\{name\\: string, value\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Method App\\\\Support\\\\ReclutadoSerializer\\:\\:tiposDe\\(\\) should return list\\<array\\<string, mixed\\>\\> but returns array\\<int, non\\-empty\\-array\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in an if condition, bool\\|null given\\.$#',
    'identifier' => 'if.condNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$evs of class Src\\\\Pokemon\\\\Domain\\\\Stats\\\\BattleStats constructor expects Src\\\\Pokemon\\\\Domain\\\\Stats\\\\StatsValue, Src\\\\Pokemon\\\\Domain\\\\Stats\\\\StatsValue\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/app/Support/ReclutadoSerializer.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$clave of method Src\\\\Battle\\\\Domain\\\\Combatiente\\:\\:tieneEfecto\\(\\) expects string, int\\|string given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/AnalizadorAmenazasImpl.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$cmp of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:sort\\(\\) expects callable\\(object, object\\)\\: int, Closure\\(Src\\\\Battle\\\\Domain\\\\AccionBatalla, Src\\\\Battle\\\\Domain\\\\AccionBatalla\\)\\: int\\<\\-1, 1\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/RespuestaRival.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: Src\\\\Battle\\\\Domain\\\\AI\\\\ValueObjects\\\\EvaluacionAccion, Closure\\(Src\\\\Battle\\\\Domain\\\\AccionBatalla\\)\\: Src\\\\Battle\\\\Domain\\\\AI\\\\ValueObjects\\\\EvaluacionAccion given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/SelectorAccionIA.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$defender of class Src\\\\Battle\\\\Domain\\\\AccionBatalla constructor expects Src\\\\Battle\\\\Domain\\\\Combatiente, object given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/AI/SelectorAccionIA.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\Collections\\\\CambiosStatsCollection\\:\\:all\\(\\) should return list\\<Src\\\\Battle\\\\Domain\\\\ValueObjects\\\\CambioStat\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Collections/CambiosStatsCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\Collections\\\\CombatientesCollection\\:\\:all\\(\\) should return list\\<Src\\\\Battle\\\\Domain\\\\Combatiente\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Collections/CombatientesCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\Collections\\\\MovimientoBatallaCollection\\:\\:porNombre\\(\\) should return Src\\\\Battle\\\\Domain\\\\MovimientoBatalla\\|null but returns object\\|null\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Collections/MovimientoBatallaCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\<string, mixed\\>, Closure\\(Src\\\\Battle\\\\Domain\\\\MovimientoBatalla\\)\\: array\\<string, mixed\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Collections/MovimientoBatallaCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$predicate of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:first\\(\\) expects \\(callable\\(object\\)\\: bool\\)\\|null, Closure\\(Src\\\\Battle\\\\Domain\\\\MovimientoBatalla\\)\\: bool given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Collections/MovimientoBatallaCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Battle\\\\Domain\\\\Effects\\\\FabricaEfectos\\:\\:\\$efectosRegistrados \\(array\\<string, array\\{clase\\: class\\-string\\<Src\\\\Battle\\\\Domain\\\\Effects\\\\InterfazEfecto\\>, args\\: array\\}\\>\\) does not accept non\\-empty\\-array\\<string, array\\{clase\\: string, args\\: array\\}\\>\\.$#',
    'identifier' => 'assign.propertyType',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Effects/FabricaEfectos.php',
];
$ignoreErrors[] = [
    'message' => '#^Property Src\\\\Battle\\\\Domain\\\\Effects\\\\FabricaEfectos\\:\\:\\$itemsRegistrados \\(array\\<string, class\\-string\\<Src\\\\Battle\\\\Domain\\\\Effects\\\\InterfazEfecto\\>\\>\\) does not accept array\\<string, string\\>\\.$#',
    'identifier' => 'assign.propertyType',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/Effects/FabricaEfectos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\EstadoManager\\:\\:puedeActuar\\(\\) should return array\\{canAct\\: bool, reason\\: string, selfDamage\\: float, estadoResultado\\: Src\\\\Battle\\\\Domain\\\\Enums\\\\EstadoPokemon, turnosResultado\\: int, hpResultado\\: float\\} but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 2,
    'path' => __DIR__ . '/src/Battle/Domain/EstadoManager.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Battle\\\\Domain\\\\ValueObjects\\\\ModificadoresStatsCollection\\:\\:all\\(\\) should return list\\<Src\\\\Battle\\\\Domain\\\\ValueObjects\\\\ModificadorStat\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Battle/Domain/ValueObjects/ModificadoresStatsCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\CombateEntrenadores\\\\App\\\\GeneradorEquipoEntrenador\\:\\:poolDelHabitat\\(\\) should return list\\<App\\\\Models\\\\Pokemon\\> but returns array\\<int, App\\\\Models\\\\Pokemon\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{indice\\: int, desbloqueado\\: bool\\}, Closure\\(Src\\\\CombateEntrenadores\\\\Domain\\\\DataTransferObjects\\\\EstadoEntrenador\\)\\: array\\{indice\\: int, desbloqueado\\: bool\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/Domain/Collections/EstadoEntrenadorCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method nivel\\(\\) on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\CombateRuta\\\\App\\\\OtorgarRecompensasRuta\\:\\:caramelosEvDe\\(\\) should return list\\<array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\> but returns array\\<int, array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateRuta/App/OtorgarRecompensasRuta.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\CombateRuta\\\\App\\\\OtorgarRecompensasRuta\\:\\:caramelosFamiliaDe\\(\\) should return list\\<array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\> but returns array\\<int, array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateRuta/App/OtorgarRecompensasRuta.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\CombateRuta\\\\App\\\\OtorgarRecompensasRuta\\:\\:caramelosTipoDe\\(\\) should return list\\<array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\> but returns array\\<int, array\\{src\\: string, alt\\: string, cantidad\\: int, nombre\\: string\\|null\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateRuta/App/OtorgarRecompensasRuta.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$capturas of class Src\\\\CombateRuta\\\\Domain\\\\DataTransferObjects\\\\ResultadoRuta constructor expects list\\<Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaCaptura\\>, array\\<int, Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaCaptura\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateRuta/App/OtorgarRecompensasRuta.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/CombateRuta/Infra/Controllers/CombateRutaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method nivel\\(\\) on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 2,
    'path' => __DIR__ . '/src/CombateRuta/Infra/Controllers/CombateRutaController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$formacion of class Src\\\\Equipos\\\\Domain\\\\TeamAggregate constructor expects array, array\\|string given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Equipos/Infra/EloquentTeamRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$name on App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$pokemon of method Src\\\\CombateEntrenadores\\\\App\\\\MapeadorPokemonBatalla\\:\\:desdePokemon\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/CombateExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$stats on App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FabricaCapacidadesStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FabricaCapacidadesStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\App\\\\FinalizarExploracionHandler\\:\\:chainPorPokemonDeEventos\\(\\) should return array\\<int, int\\> but returns array\\<int, int\\|null\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FinalizarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$extractor of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) expects callable\\(object\\)\\: int, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\EventoExploracion\\)\\: \\(int\\|null\\) given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/FinalizarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\App\\\\NormalizadorPokemonDerrotado\\:\\:tipos\\(\\) should return list\\<string\\> but returns array\\<int, non\\-falsy\\-string\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/NormalizadorPokemonDerrotado.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method toArray\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/PersistirRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\App\\\\ProcesarExploracionHandler\\:\\:poolHabitat\\(\\) should return list\\<array\\{id\\: int, capture_rate\\: int, hatch\\: int\\|null, tipos\\: list\\<Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\>, stats\\: list\\<array\\{stat\\: int, effort\\: int\\}\\>\\}\\> but returns list\\<array\\<string, mixed\\>\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\App\\\\ProcesarExploracionHandler\\:\\:tiposDe\\(\\) should return list\\<Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\> but returns array\\<int, Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ACERO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:AGUA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:BICHO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:DRAGON\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ELECTRICO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:FANTASMA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:FUEGO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:HADA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:HIELO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:LUCHA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:NORMAL\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:PLANTA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:PSIQUICO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:ROCA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:SINIESTRO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:TIERRA\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:VENENO\\|Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\:\\:VOLADOR\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pool of static method Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\PoolHabitat\\:\\:desdeArray\\(\\) expects list\\<array\\<string, mixed\\>\\>, array\\<int, array\\{id\\: int, capture_rate\\: int, hatch\\: int\\|null, tipos\\: list\\<Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\>, stats\\: array\\<int, array\\{stat\\: 1\\|2\\|3\\|4\\|5\\|6, effort\\: int\\}\\>\\}\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$reclutado of method Src\\\\Exploraciones\\\\App\\\\CombateExploracion\\:\\:combatir\\(\\) expects App\\\\Models\\\\Reclutado, App\\\\Models\\\\Reclutado\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/App/ProcesarExploracionHandler.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$evolutionChainId on Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\PokemonDerrotado\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/CalculadorRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\PokemonDerrotado\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/CalculadorRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Offset \'stat\' might not exist on array\\{stat\\: int, effort\\: int\\<1, max\\>\\}\\|null\\.$#',
    'identifier' => 'offsetAccess.notFound',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/CalculadorRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\$evolutionChainId of class Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaFamilia constructor expects int, int\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/CalculadorRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$extractor of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) expects callable\\(object\\)\\: string, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\EventoExploracion\\)\\: \\(string\\|null\\) given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/EvaluadorExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$evolutionChainId on Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaFamilia\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$stat on Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaEv\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$tipo on Src\\\\Exploraciones\\\\Domain\\\\Recompensas\\\\RecompensaTipo\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pool of static method Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\PoolHabitat\\:\\:desdeArray\\(\\) expects list\\<array\\<string, mixed\\>\\>, array\\<int, array\\{id\\: int, capture_rate\\: int, hatch\\: int\\|null, tipos\\?\\: list\\<Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\>, stats\\?\\: list\\<array\\{stat\\: int, effort\\: int\\}\\>\\}\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/SimuladorEncuentros.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$predicate of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:first\\(\\) expects \\(callable\\(object\\)\\: bool\\)\\|null, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\PokemonDelPool\\)\\: bool given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/src/Exploraciones/Domain/SimuladorEncuentros.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\<string, mixed\\>, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\EventoExploracion\\)\\: array\\<string, mixed\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/ValueObjects/ColeccionEventosExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{id\\: int, peso\\: float\\}, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\PokemonPoolPonderado\\)\\: array\\{id\\: int, peso\\: float\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/ValueObjects/ColeccionPoolPonderado.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\ColeccionStatsDelPool\\:\\:aLista\\(\\) should return list\\<array\\{stat\\: int, effort\\: int\\}\\> but returns array\\<array\\{stat\\: int, effort\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/ValueObjects/ColeccionStatsDelPool.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\<string, mixed\\>, Closure\\(Src\\\\Exploraciones\\\\Domain\\\\ValueObjects\\\\PokemonDelPool\\)\\: array\\<string, mixed\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Domain/ValueObjects/PoolHabitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\Presentation\\\\TransformadorResultadoExploracion\\:\\:capturados\\(\\) should return list\\<array\\{pokemon_id\\: int, nombre\\: string, cantidad\\: int\\}\\> but returns array\\<int, array\\{pokemon_id\\: int, nombre\\: string, cantidad\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Presentation/TransformadorResultadoExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\Presentation\\\\TransformadorResultadoExploracion\\:\\:caramelosEv\\(\\) should return list\\<array\\{stat\\: int, cantidad\\: int\\}\\> but returns array\\<int, array\\{stat\\: int, cantidad\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Presentation/TransformadorResultadoExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\Presentation\\\\TransformadorResultadoExploracion\\:\\:caramelosFamilia\\(\\) should return list\\<array\\{evolution_chain_id\\: int, nombre\\: string\\|null, pokemon_id\\: int\\|null, cantidad\\: int\\}\\> but returns array\\<int, array\\{evolution_chain_id\\: int, nombre\\: string\\|null, pokemon_id\\: int\\|null, cantidad\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Presentation/TransformadorResultadoExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Exploraciones\\\\Presentation\\\\TransformadorResultadoExploracion\\:\\:caramelosTipo\\(\\) should return list\\<array\\{tipo\\: string, slug\\: string, cantidad\\: int\\}\\> but returns array\\<int, array\\{tipo\\: string, slug\\: string, cantidad\\: int\\}\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Exploraciones/Presentation/TransformadorResultadoExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{etapa\\: int, nombre\\: string\\}, Closure\\(Src\\\\Gimnasios\\\\Domain\\\\DataTransferObjects\\\\EtapaGimnasio\\)\\: array\\{etapa\\: int, nombre\\: string\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Domain/Collections/EtapaGimnasioCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{slug\\: string, medalla\\: string, tipo\\: int, tipo_nombre\\: string, tipo_slug\\: string, tipo_medalla\\: string, tipo_color\\: string, nivel_minimo\\: int, nivel_jugador\\: int, etapa_actual\\: int, estado\\: string\\}, Closure\\(Src\\\\Gimnasios\\\\Domain\\\\DataTransferObjects\\\\GimnasioResumen\\)\\: array\\{slug\\: string, medalla\\: string, tipo\\: int, tipo_nombre\\: string, tipo_slug\\: string, tipo_medalla\\: string, tipo_color\\: string, nivel_minimo\\: int, nivel_jugador\\: int, etapa_actual\\: int, estado\\: string\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Domain/Collections/GimnasioResumenCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 3,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/GimnasioController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method nivel\\(\\) on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 3,
    'path' => __DIR__ . '/src/Gimnasios/Infra/Controllers/GimnasioController.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Gimnasios\\\\Infra\\\\EloquentGymCatalogoRepository\\:\\:obtenerTodos\\(\\) should return list\\<Src\\\\Gimnasios\\\\Domain\\\\Gimnasio\\> but returns array\\<int, Src\\\\Gimnasios\\\\Domain\\\\Gimnasio\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Gimnasios/Infra/EloquentGymCatalogoRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Only booleans are allowed in a negated boolean, bool\\|null given\\.$#',
    'identifier' => 'booleanNot.exprNotBoolean',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/App/ValidadorExploracion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\.\\.\\.\\$arg1 of function max expects non\\-empty\\-array, list\\<int\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Domain/ResolvedorCadenasEvolutivas.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of method Src\\\\Habitats\\\\Domain\\\\ResolvedorCadenasEvolutivas\\:\\:getFamilyMembersByChain\\(\\) expects list\\<array\\{id\\: int, name\\: string, species_id\\: int\\}\\>, array\\<mixed\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$evolutions of method Src\\\\Habitats\\\\Domain\\\\ResolvedorCadenasEvolutivas\\:\\:getFamilyMembersByChain\\(\\) expects list\\<array\\{evolved_species_id\\: int, evolves_from_species_id\\: int\\|null\\}\\>, array\\<mixed\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Infra/HabitatRepository.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{id\\: int, name\\: string, icon\\: string, level\\?\\: int\\}, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: array\\{id\\: int, name\\: string, icon\\: string, level\\?\\: int\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionPokemonFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$carry of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:reduce\\(\\) expects callable\\(\\(TReduce&null\\)\\|Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null, object\\)\\: \\(\\(TReduce&null\\)\\|Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null\\), Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null, Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: \\(\\(TReduce&null\\)\\|Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null\\) given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionPokemonFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$carry of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:reduce\\(\\) expects callable\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null, object\\)\\: \\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null\\), Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\|null, Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionPokemonFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$extractor of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) expects callable\\(object\\)\\: int, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: int given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionPokemonFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{id\\: int, name\\: string\\}, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonTipo\\)\\: array\\{id\\: int, name\\: string\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionTiposFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$extractor of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) expects callable\\(object\\)\\: int, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOPokemonTipo\\)\\: int given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/ColeccionTiposFamilia.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Habitats\\\\Presentation\\\\DTOFamiliaDisponible\\:\\:toArray\\(\\) should return array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}, evolutions\\: array\\<int, array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}\\>, types\\: array\\<int, array\\{id\\: int, name\\: string\\}\\>\\} but returns array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string, level\\?\\: int\\}, evolutions\\: list\\<array\\{id\\: int, name\\: string, icon\\: string, level\\?\\: int\\}\\>, types\\: list\\<array\\{id\\: int, name\\: string\\}\\>\\}\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliaDisponible.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$carry of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:reduce\\(\\) expects callable\\(int, object\\)\\: int, Closure\\(int, Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: int given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliaDisponible.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$carry of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:reduce\\(\\) expects callable\\(int, object\\)\\: int, Closure\\(int, Src\\\\Habitats\\\\Presentation\\\\DTOPokemonFamilia\\)\\: int given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliaSinHabitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}, evolutions\\: array\\<int, array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}\\>, types\\: array\\<int, array\\{id\\: int, name\\: string\\}\\>\\}, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOFamiliaDisponible\\)\\: array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}, evolutions\\: array\\<int, array\\{id\\: int, name\\: string, icon\\: string, level\\: int\\}\\>, types\\: array\\<int, array\\{id\\: int, name\\: string\\}\\>\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliasDisponibles.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string\\}, evolutions\\: array\\<int, array\\{id\\: int, name\\: string, icon\\: string\\}\\>, types\\: array\\<int, array\\{id\\: int, name\\: string\\}\\>\\}, Closure\\(Src\\\\Habitats\\\\Presentation\\\\DTOFamiliaSinHabitat\\)\\: array\\{evolution_chain_id\\: int, base\\: array\\{id\\: int, name\\: string, icon\\: string\\}, evolutions\\: array\\<int, array\\{id\\: int, name\\: string, icon\\: string\\}\\>, types\\: array\\<int, array\\{id\\: int, name\\: string\\}\\>\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Habitats/Presentation/DTOFamiliasSinHabitat.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$species_id on App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/CrearHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$creadoEn of static method Src\\\\Huevos\\\\Domain\\\\CalculadorEstadoHuevo\\:\\:estadoDe\\(\\) expects DateTimeInterface, Carbon\\\\Carbon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/CrearHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$creadoEn of static method Src\\\\Huevos\\\\Domain\\\\CalculadorEstadoHuevo\\:\\:estadoDe\\(\\) expects DateTimeInterface, Carbon\\\\Carbon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/EclosionarHuevo.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$evolution_chain_id on App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$species_id on App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Huevos\\\\App\\\\ListarHuevos\\:\\:listar\\(\\) should return array\\{huevos\\: list\\<array\\{id\\: int, estado\\: string, tiempo_total_horas\\: float, tiempo_restante\\: float, progreso\\: int, puede_eclosionar\\: bool, pokemon\\: array\\{id\\: int, name\\: string, icon\\: string\\}\\}\\>, disponibles\\: list\\<array\\{evolution_chain_id\\: int, pokemon\\: array\\{id\\: int, name\\: string, icon\\: string\\}, capture_rate\\: int, hatch\\: int\\}\\>, limites\\: array\\{max_gestacion\\: int, max_incubando\\: int, gestacion_activa\\: bool, incubacion_activa\\: bool\\}\\} but returns array\\{huevos\\: array\\<int, array\\{id\\: int, estado\\: string, tiempo_total_horas\\: float, tiempo_restante\\: float, progreso\\: int, puede_eclosionar\\: bool, pokemon\\: array\\{id\\: int, name\\: string, icon\\: string\\}\\}\\>, disponibles\\: list\\<array\\{evolution_chain_id\\: int, pokemon\\: array\\{id\\: int, name\\: string, icon\\: string\\}, capture_rate\\: int, hatch\\: int\\}\\>, limites\\: array\\{max_gestacion\\: int, max_incubando\\: int, gestacion_activa\\: bool, incubacion_activa\\: bool\\}\\}\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$creadoEn of static method Src\\\\Huevos\\\\Domain\\\\CalculadorEstadoHuevo\\:\\:estadoDe\\(\\) expects DateTimeInterface, Carbon\\\\Carbon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$groupBy of method Illuminate\\\\Support\\\\Collection\\<int,App\\\\Models\\\\Reclutado\\>\\:\\:groupBy\\(\\) expects \\(callable\\(App\\\\Models\\\\Reclutado, int\\)\\: int\\)\\|list\\<Closure\\|string\\>\\|string, Closure\\(App\\\\Models\\\\Reclutado\\)\\: \\(int\\|null\\) given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of static method Src\\\\Huevos\\\\App\\\\ListarHuevos\\:\\:serializarPokemon\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Huevos/App/ListarHuevos.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{piso\\: int, nombre\\: string, estado\\: string, cooldown_hasta\\: string\\|null\\}, Closure\\(Src\\\\Mazmorras\\\\Domain\\\\DataTransferObjects\\\\PisoMazmorra\\)\\: array\\{piso\\: int, nombre\\: string, estado\\: string, cooldown_hasta\\: string\\|null\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Domain/Collections/PisoMazmorraCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot access property \\$id on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'property.nonObject',
    'count' => 2,
    'path' => __DIR__ . '/src/Mazmorras/Infra/Controllers/MazmorraController.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method nivel\\(\\) on App\\\\Models\\\\User\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Mazmorras/Infra/Controllers/MazmorraController.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$base of method Src\\\\Pokemon\\\\Domain\\\\Stats\\\\BattleStats\\:\\:calcularHp\\(\\) expects float, float\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 3,
    'path' => __DIR__ . '/src/Pokemon/Domain/Stats/BattleStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$base of method Src\\\\Pokemon\\\\Domain\\\\Stats\\\\BattleStats\\:\\:calcularStat\\(\\) expects float, float\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 5,
    'path' => __DIR__ . '/src/Pokemon/Domain/Stats/BattleStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$evs of method Src\\\\Pokemon\\\\Domain\\\\Stats\\\\BattleStats\\:\\:calcularHp\\(\\) expects float, float\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 3,
    'path' => __DIR__ . '/src/Pokemon/Domain/Stats/BattleStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#2 \\$evs of method Src\\\\Pokemon\\\\Domain\\\\Stats\\\\BattleStats\\:\\:calcularStat\\(\\) expects float, float\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 5,
    'path' => __DIR__ . '/src/Pokemon/Domain/Stats/BattleStats.php',
];
$ignoreErrors[] = [
    'message' => '#^Anonymous function should return App\\\\Models\\\\Pokemon but returns App\\\\Models\\\\Pokemon\\|null\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method consumirTipos\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method expTipo\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot call method total\\(\\) on App\\\\Models\\\\Casts\\\\ExpReclutado\\|null\\.$#',
    'identifier' => 'method.nonObject',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:opcionesEvolucion\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$pokemon of static method Src\\\\Reclutamiento\\\\App\\\\ServicioEvolucion\\:\\:siguienteEvolucion\\(\\) expects App\\\\Models\\\\Pokemon, App\\\\Models\\\\Pokemon\\|null given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/src/Reclutamiento/App/ServicioEvolucion.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{pokemon_id\\: int, nombre\\: string, imagen\\: string, requisitos\\: list\\<array\\{tipo\\: string, slug\\: string, necesario\\: int, actual\\: int, caramelosDisponibles\\: int\\}\\>, puede_evolucionar\\: bool\\}, Closure\\(Src\\\\Reclutamiento\\\\Domain\\\\DataTransferObjects\\\\OpcionEvolucion\\)\\: array\\{pokemon_id\\: int, nombre\\: string, imagen\\: string, requisitos\\: list\\<array\\{tipo\\: string, slug\\: string, necesario\\: int, actual\\: int, caramelosDisponibles\\: int\\}\\>, puede_evolucionar\\: bool\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/Domain/Collections/OpcionEvolucionCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{tipo\\: string, slug\\: string, necesario\\: int, actual\\: int, caramelosDisponibles\\: int\\}, Closure\\(Src\\\\Reclutamiento\\\\Domain\\\\DataTransferObjects\\\\RequisitoEvolucion\\)\\: array\\{tipo\\: string, slug\\: string, necesario\\: int, actual\\: int, caramelosDisponibles\\: int\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Reclutamiento/Domain/Collections/RequisitoEvolucionCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Collections\\\\IntCollection\\:\\:all\\(\\) should return list\\<int\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Collections/IntCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Collections\\\\StringCollection\\:\\:toList\\(\\) should return list\\<string\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Collections/StringCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\ClasificadorOfensivaDefensiva\\:\\:generarFormacion\\(\\) should return list\\<Src\\\\Battle\\\\Domain\\\\Posicion\\> but returns non\\-empty\\-array\\<int, Src\\\\Battle\\\\Domain\\\\Posicion\\:\\:RETAGUARDIA\\|Src\\\\Battle\\\\Domain\\\\Posicion\\:\\:VANGUARDIA\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/ClasificadorOfensivaDefensiva.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$statsPorSlot of method Src\\\\Shared\\\\Domain\\\\ClasificadorOfensivaDefensiva\\:\\:indiceMayorValor\\(\\) expects non\\-empty\\-list\\<Src\\\\Pokemon\\\\Domain\\\\Stats\\\\DatosStats\\>, list\\<Src\\\\Pokemon\\\\Domain\\\\Stats\\\\DatosStats\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/src/Shared/Domain/ClasificadorOfensivaDefensiva.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) should return list\\<TMap\\> but returns array\\<TMap\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) should return list\\<TPluck of bool\\|float\\|int\\|string\\> but returns array\\<TPluck of bool\\|float\\|int\\|string\\>\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:toList\\(\\) should return list\\<mixed\\> but returns array\\.$#',
    'identifier' => 'return.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\.\\.\\.\\$arg1 of function max expects non\\-empty\\-array, list\\<bool\\|float\\|int\\|string\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\.\\.\\.\\$arg1 of function min expects non\\-empty\\-array, list\\<bool\\|float\\|int\\|string\\> given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: array\\{nombre\\: string, imagen\\: string, cantidad\\: int\\}, Closure\\(Src\\\\Shared\\\\Domain\\\\DataTransferObjects\\\\ItemCaramelo\\)\\: array\\{nombre\\: string, imagen\\: string, cantidad\\: int\\} given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Domain/Collections/ItemCarameloCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$callback of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:map\\(\\) expects callable\\(object\\)\\: string, Closure\\(Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\)\\: string given\\.$#',
    'identifier' => 'argument.type',
    'count' => 2,
    'path' => __DIR__ . '/src/Shared/Tipos/TiposCollection.php',
];
$ignoreErrors[] = [
    'message' => '#^Parameter \\#1 \\$extractor of method Src\\\\Shared\\\\Domain\\\\Collection\\:\\:pluck\\(\\) expects callable\\(object\\)\\: \\(1\\|2\\|3\\|4\\|5\\|6\\|7\\|8\\|9\\|10\\|11\\|12\\|13\\|14\\|15\\|16\\|17\\|18\\), Closure\\(Src\\\\Shared\\\\Tipos\\\\TipoPokemon\\)\\: \\(1\\|2\\|3\\|4\\|5\\|6\\|7\\|8\\|9\\|10\\|11\\|12\\|13\\|14\\|15\\|16\\|17\\|18\\) given\\.$#',
    'identifier' => 'argument.type',
    'count' => 1,
    'path' => __DIR__ . '/src/Shared/Tipos/TiposCollection.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
