<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Escenarios deterministas del arnés IA vs IA.
 *
 * `humo()` es la matriz rápida de CI; `priorizados()` cubre categorías de
 * riesgo; `paresRiesgo()` combina efectos que suelen interactuar mal y
 * `completo()` barre todas las claves registradas en la fábrica real.
 */
final class CatalogoEscenarios
{
    /** @return list<EscenarioBatalla> */
    public static function humo(): array
    {
        return [
            self::climaGranizo(),
            self::climasContrarios(),
            self::restosYOrbeVida(),
            self::espejoMagicoEstado(),
            self::veloFlorEstado(),
            self::inmunidadVeneno(),
            self::safeguardEquipo(),
            self::dotVeneno(),
            self::pantallaReflejo(),
            self::sustitutoActivo(),
        ];
    }

    public static function sinDano(): EscenarioBatalla
    {
        $pazMental = self::movimiento(
            nombre: 'Paz Mental',
            potencia: 0,
            tipo: TipoPokemon::PSIQUICO,
            categoria: CategoriaMovimiento::ESTADO,
        );

        return self::duelo(
            nombre: 'sin_dano_1v1',
            equipo1: self::pokemon(
                id: 't1',
                nombre: 'Meditador',
                tipos: [TipoPokemon::PSIQUICO],
                move: $pazMental,
            ),
            equipo2: self::pokemon(
                id: 't2',
                nombre: 'Contemplador',
                tipos: [TipoPokemon::PSIQUICO],
                move: $pazMental,
            ),
            semilla: 3,
            maxRondas: 5,
        );
    }

    /** @return list<EscenarioBatalla> */
    public static function priorizados(): array
    {
        return [
            ...self::priorizadosEstados(),
            ...self::priorizadosClima(),
            ...self::priorizadosTerrenos(),
            ...self::priorizadosStats(),
            ...self::priorizadosObjetos(),
            ...self::priorizadosCombate(),
            ...self::priorizadosKO(),
            ...self::priorizadosDano(),
            ...self::priorizadosProteccion(),
            ...self::priorizadosInvocaciones(),
        ];
    }

    /** @return list<EscenarioBatalla> */
    public static function paresRiesgo(): array
    {
        $dragoaliento = self::movimiento(
            'Dragoaliento',
            60,
            TipoPokemon::DRAGON,
            CategoriaMovimiento::ESPECIAL,
            EstadoPokemon::PARALYSIS,
        );

        $safeguard = self::movimiento(
            'Velo Sagrado',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            claveEfecto: ClaveEfectoMovimiento::SAFEGUARD,
        );

        $multiobjetivo = self::movimiento(
            'Gruñido',
            0,
            TipoPokemon::NORMAL,
            CategoriaMovimiento::ESTADO,
            objetivo: ObjetivoMovimiento::ALL_ADJACENT_FOES,
            targetStatChanges: self::cambios(CambioStat::desdeEtapas(StatClave::ATAQUE, -1)),
        );

        return [
            self::duelo(
                'par_orbe_vida_focus_sash',
                self::pokemon('orbe', 'Orbe', [TipoPokemon::NORMAL], self::movimiento('Golpe', 100, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), item: 'life_orb', atk: 120, speed: 110),
                self::pokemon('sash', 'Sash', [TipoPokemon::NORMAL], self::movimiento('Golpe', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), item: 'focus_sash', def: 90),
                201,
            ),
            self::duelo(
                'par_restos_dano_fin_turno',
                self::pokemon('restos', 'Restos', [TipoPokemon::ACERO], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), item: 'leftovers', hp: 90, speed: 40),
                self::pokemon('quemador', 'Quemador', [TipoPokemon::FUEGO], self::movimiento('Ascuas', 60, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL, EstadoPokemon::BURN), speed: 120),
                202,
            ),
            self::duelo(
                'par_espejo_magico_estado',
                self::pokemon('estado', 'Estado', [TipoPokemon::ELECTRICO], self::movimiento('Onda Trueno', 0, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESTADO, EstadoPokemon::PARALYSIS), speed: 120),
                self::pokemon('espejo', 'Espejo', [TipoPokemon::PSIQUICO], self::movimiento('Psíquico', 90, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL), effectKeys: ['espejo_magico']),
                203,
            ),
            self::duelo(
                'par_velo_flor_secundario',
                self::pokemon('dragon', 'Dragoncito', [TipoPokemon::DRAGON], $dragoaliento),
                self::pokemon('flor', 'Flor', [TipoPokemon::PLANTA], self::movimiento('Hoja Afilada', 90, TipoPokemon::PLANTA, CategoriaMovimiento::FISICO), effectKeys: ['velo_flor']),
                204,
            ),
            self::escenario(
                'par_safeguard_multiobjetivo',
                self::equipo(self::pokemon('multi', 'Multi', [TipoPokemon::NORMAL], $multiobjetivo, speed: 50)),
                self::equipo(
                    self::pokemon('guardian', 'Guardián', [TipoPokemon::HADA], $safeguard, hp: 80, speed: 120),
                    self::pokemon('aliado', 'Aliado', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), speed: 10),
                ),
                205,
            ),
            self::duelo(
                'par_cuerpo_aureo_estado',
                self::pokemon('estado', 'Estado', [TipoPokemon::FUEGO], self::movimiento('Fuego Fatuo', 0, TipoPokemon::FUEGO, CategoriaMovimiento::ESTADO, EstadoPokemon::BURN), speed: 120),
                self::pokemon('aureo', 'Áureo', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), effectKeys: ['cuerpo_aureo']),
                206,
            ),
            self::duelo(
                'par_sincronia_veneno_grave',
                self::pokemon('toxico', 'Tóxico', [TipoPokemon::VENENO], self::movimiento('Tóxico', 0, TipoPokemon::VENENO, CategoriaMovimiento::ESTADO, EstadoPokemon::BAD_POISON), speed: 120),
                self::pokemon('sincronia', 'Sincronía', [TipoPokemon::PSIQUICO], self::movimiento('Psíquico', 90, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL), effectKeys: ['sincronia']),
                207,
            ),
            self::duelo(
                'par_clima_contrario',
                self::pokemon('sol', 'Solar', [TipoPokemon::FUEGO], self::movimiento('Llamarada', 90, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL), effectKeys: ['sequia']),
                self::pokemon('lluvia', 'Lluvioso', [TipoPokemon::AGUA], self::movimiento('Hidrobomba', 90, TipoPokemon::AGUA, CategoriaMovimiento::ESPECIAL), effectKeys: ['llovizna']),
                208,
            ),
            self::duelo(
                'par_trick_room_prioridad',
                self::pokemon('prioridad', 'Prioridad', [TipoPokemon::NORMAL], self::movimiento('Golpe Rápido', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO, priority: 1), speed: 10),
                self::pokemon('trick', 'Trick', [TipoPokemon::PSIQUICO], self::movimiento('Espacio Raro', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::TRICK_ROOM), speed: 120),
                209,
            ),
            self::duelo(
                'par_madrugar_sueno',
                self::pokemon('sueno', 'Sueño', [TipoPokemon::PSIQUICO], self::movimiento('Hipnosis', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, EstadoPokemon::SLEEP), speed: 120),
                self::pokemon('madrugar', 'Madrugador', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), effectKeys: ['madrugar']),
                210,
            ),
            // `polvo_escudo` está registrado como marcador pasivo sin lógica
            // de estado; se conserva el par para vigilar que no rompa
            // invariantes. La cobertura funcional queda en la batería B2.
            self::duelo(
                'par_polvo_escudo_secundarios',
                self::pokemon('secundario', 'Secundario', [TipoPokemon::NORMAL], self::movimiento('Golpe', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO, EstadoPokemon::PARALYSIS), speed: 120),
                self::pokemon('polvo', 'Polvo', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), effectKeys: ['polvo_escudo']),
                211,
            ),
            self::duelo(
                'par_banda_focus_multigolpe',
                self::pokemon('multi', 'Multi', [TipoPokemon::NORMAL], self::movimiento('Doble Golpe', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO, golpes: 2, fraccionPorGolpe: 0.5), speed: 120),
                self::pokemon('sash', 'Sash', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), item: 'focus_sash'),
                212,
            ),
        ];
    }

    /**
     * Un escenario por cada clave registrada en la fábrica real de efectos.
     *
     * @return list<EscenarioBatalla>
     */
    public static function completo(): array
    {
        $fabrica = app(FabricaEfectos::class);
        $escenarios = [];

        foreach ($fabrica->clavesEfectos()->toList() as $clave) {
            $escenarios[] = self::escenarioConClave('completo_efecto_'.$clave, [$clave], null);
        }

        foreach ($fabrica->clavesItems()->toList() as $clave) {
            $escenarios[] = self::escenarioConClave('completo_item_'.$clave, [], $clave);
        }

        return $escenarios;
    }

    private static function climaGranizo(): EscenarioBatalla
    {
        return self::duelo(
            'clima_granizo',
            self::pokemon('hielo', 'Glaciar', [TipoPokemon::HIELO], self::movimiento('Rayo Hielo', 90, TipoPokemon::HIELO, CategoriaMovimiento::ESPECIAL), effectKeys: ['manto_helado']),
            self::pokemon('roca', 'Pedrusco', [TipoPokemon::ROCA, TipoPokemon::TIERRA], self::movimiento('Roca Afilada', 100, TipoPokemon::ROCA, CategoriaMovimiento::FISICO)),
            11,
            clima: TipoClima::GRANIZO,
        );
    }

    private static function climasContrarios(): EscenarioBatalla
    {
        return self::duelo(
            'climas_contrarios',
            self::pokemon('sol', 'Solar', [TipoPokemon::FUEGO], self::movimiento('Llamarada', 90, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL), effectKeys: ['sequia']),
            self::pokemon('lluvia', 'Lluvioso', [TipoPokemon::AGUA], self::movimiento('Hidrobomba', 90, TipoPokemon::AGUA, CategoriaMovimiento::ESPECIAL), effectKeys: ['llovizna']),
            13,
        );
    }

    private static function restosYOrbeVida(): EscenarioBatalla
    {
        return self::duelo(
            'restos_orbe_vida',
            self::pokemon('tanque', 'Tanque', [TipoPokemon::ACERO], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), item: 'leftovers', hp: 75),
            self::pokemon('cristal', 'Cristal', [TipoPokemon::SINIESTRO], self::movimiento('Triturar', 90, TipoPokemon::SINIESTRO, CategoriaMovimiento::FISICO), item: 'life_orb', atk: 120),
            17,
        );
    }

    private static function espejoMagicoEstado(): EscenarioBatalla
    {
        $ondaTrueno = self::movimiento('Onda Trueno', 0, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESTADO, EstadoPokemon::PARALYSIS);

        return self::duelo(
            'espejo_magico_estado',
            self::pokemon('paralizador', 'Paralizador', [TipoPokemon::ELECTRICO], $ondaTrueno, hp: 100),
            self::pokemon('espejo', 'Espejo', [TipoPokemon::PSIQUICO], self::movimiento('Psíquico', 90, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESPECIAL), effectKeys: ['espejo_magico']),
            19,
        );
    }

    private static function veloFlorEstado(): EscenarioBatalla
    {
        $dragoaliento = self::movimiento('Dragoaliento', 60, TipoPokemon::DRAGON, CategoriaMovimiento::ESPECIAL, EstadoPokemon::PARALYSIS);

        return self::duelo(
            'velo_flor_estado',
            self::pokemon('dragon', 'Dragoncito', [TipoPokemon::DRAGON], $dragoaliento),
            self::pokemon('flor', 'Flor', [TipoPokemon::PLANTA], self::movimiento('Hoja Afilada', 90, TipoPokemon::PLANTA, CategoriaMovimiento::FISICO), effectKeys: ['velo_flor']),
            23,
        );
    }

    private static function inmunidadVeneno(): EscenarioBatalla
    {
        $toxico = self::movimiento('Tóxico', 0, TipoPokemon::VENENO, CategoriaMovimiento::ESTADO, EstadoPokemon::POISON);

        return self::duelo(
            'inmunidad_veneno',
            self::pokemon('toxico', 'Toxiquín', [TipoPokemon::VENENO], $toxico, hp: 100),
            self::pokemon('inmune', 'Inmune', [TipoPokemon::VENENO], self::movimiento('Puya Nociva', 80, TipoPokemon::VENENO, CategoriaMovimiento::FISICO), effectKeys: ['inmunidad']),
            29,
        );
    }

    private static function safeguardEquipo(): EscenarioBatalla
    {
        $ondaTrueno = self::movimiento('Onda Trueno', 0, TipoPokemon::ELECTRICO, CategoriaMovimiento::ESTADO, EstadoPokemon::PARALYSIS);
        $safeguard = self::movimiento('Velo Sagrado', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::SAFEGUARD);

        return self::duelo(
            'safeguard_equipo',
            self::pokemon('atacante', 'Atacante', [TipoPokemon::ELECTRICO], $ondaTrueno, hp: 100, speed: 50),
            self::pokemon('guardian', 'Guardián', [TipoPokemon::HADA], $safeguard, hp: 100, speed: 120),
            31,
            maxRondas: 6,
        );
    }

    private static function dotVeneno(): EscenarioBatalla
    {
        return self::duelo(
            'dot_veneno',
            self::pokemon('dot', 'Dot', [TipoPokemon::VENENO], self::movimiento('Puya Nociva', 80, TipoPokemon::VENENO, CategoriaMovimiento::FISICO, EstadoPokemon::POISON), speed: 120),
            self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
            37,
            maxRondas: 15,
        );
    }

    private static function pantallaReflejo(): EscenarioBatalla
    {
        $reflejo = self::movimiento('Reflejo', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::REFLEJO);

        return self::duelo(
            'pantalla_reflejo',
            self::pokemon('pantalla', 'Pantalla', [TipoPokemon::PSIQUICO], $reflejo, speed: 120),
            self::pokemon('golpeador', 'Golpeador', [TipoPokemon::NORMAL], self::movimiento('Placaje', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
            41,
            maxRondas: 15,
        );
    }

    private static function sustitutoActivo(): EscenarioBatalla
    {
        $sustituto = self::movimiento('Sustituto', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::SUSTITUTO);

        return self::duelo(
            'sustituto_activo',
            self::pokemon('clon', 'Clon', [TipoPokemon::NORMAL], $sustituto, hp: 100, speed: 120),
            self::pokemon('golpeador', 'Golpeador', [TipoPokemon::NORMAL], self::movimiento('Placaje', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
            43,
            maxRondas: 15,
        );
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosEstados(): array
    {
        $definiciones = [
            'paralisis' => ['Onda Trueno', EstadoPokemon::PARALYSIS, TipoPokemon::ELECTRICO],
            'sueno' => ['Hipnosis', EstadoPokemon::SLEEP, TipoPokemon::PSIQUICO],
            'veneno' => ['Tóxico', EstadoPokemon::POISON, TipoPokemon::VENENO],
            'quemadura' => ['Fuego Fatuo', EstadoPokemon::BURN, TipoPokemon::FUEGO],
            'congelacion' => ['Rayo Hielo', EstadoPokemon::FREEZE, TipoPokemon::HIELO],
            'confusion' => ['Psicorrayo', EstadoPokemon::CONFUSION, TipoPokemon::PSIQUICO],
        ];

        $escenarios = [];
        $semilla = 101;

        foreach ($definiciones as $clave => [$nombreMovimiento, $estado, $tipo]) {
            $escenarios[] = self::duelo(
                'prio_estado_'.$clave,
                self::pokemon(
                    'estado_'.$clave,
                    'Estado '.$clave,
                    [$tipo],
                    self::movimiento($nombreMovimiento, 0, $tipo, CategoriaMovimiento::ESTADO, $estado),
                    speed: 120,
                ),
                self::pokemon(
                    'objetivo_'.$clave,
                    'Objetivo',
                    [TipoPokemon::NORMAL],
                    self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                ),
                $semilla++,
            );
        }

        return $escenarios;
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosClima(): array
    {
        $definiciones = [
            'sequia' => ['sequia', 'Llamarada', TipoPokemon::FUEGO],
            'diluvio' => ['llovizna', 'Hidrobomba', TipoPokemon::AGUA],
            'niebla' => ['niebla_summoner', 'Viento Hielo', TipoPokemon::HIELO],
            'granizo' => ['granizo', 'Rayo Hielo', TipoPokemon::HIELO],
            'tormenta_arena' => ['tormenta_arena', 'Roca Afilada', TipoPokemon::ROCA],
            'turbulencias' => ['turbulencias_summoner', 'Aire Afilado', TipoPokemon::VOLADOR],
        ];

        $escenarios = [];
        $semilla = 111;

        foreach ($definiciones as $clave => [$effectKey, $nombreMovimiento, $tipo]) {
            $escenarios[] = self::duelo(
                'prio_clima_'.$clave,
                self::pokemon(
                    'clima_'.$clave,
                    'Clima '.$clave,
                    [$tipo],
                    self::movimiento($nombreMovimiento, 90, $tipo, CategoriaMovimiento::ESPECIAL),
                    effectKeys: [$effectKey],
                ),
                self::pokemon(
                    'rival_'.$clave,
                    'Rival',
                    [TipoPokemon::NORMAL],
                    self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                ),
                $semilla++,
            );
        }

        return $escenarios;
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosTerrenos(): array
    {
        $definiciones = [
            'electrico' => ['electrogenesis', 'Rayo', TipoPokemon::ELECTRICO],
            'hierba' => ['herbogenesis', 'Hoja Afilada', TipoPokemon::PLANTA],
            'psiquico' => ['psicogenesis', 'Psíquico', TipoPokemon::PSIQUICO],
            'niebla' => ['nebulogenesis', 'Brillo Mágico', TipoPokemon::HADA],
        ];

        $escenarios = [];
        $semilla = 121;

        foreach ($definiciones as $clave => [$effectKey, $nombreMovimiento, $tipo]) {
            $escenarios[] = self::duelo(
                'prio_terreno_'.$clave,
                self::pokemon(
                    'terreno_'.$clave,
                    'Terreno '.$clave,
                    [$tipo],
                    self::movimiento($nombreMovimiento, 90, $tipo, CategoriaMovimiento::ESPECIAL),
                    effectKeys: [$effectKey],
                ),
                self::pokemon(
                    'rival_'.$clave,
                    'Rival',
                    [TipoPokemon::NORMAL],
                    self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                ),
                $semilla++,
            );
        }

        return $escenarios;
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosStats(): array
    {
        return [
            self::duelo(
                'prio_stats_subida',
                self::pokemon('subida', 'Subida', [TipoPokemon::NORMAL], self::movimiento('Danza Espada', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, selfStatChanges: self::cambios(CambioStat::desdeEtapas(StatClave::ATAQUE, 2))), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                131,
            ),
            self::duelo(
                'prio_stats_bajada',
                self::pokemon('bajada', 'Bajada', [TipoPokemon::NORMAL], self::movimiento('Gruñido', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, targetStatChanges: self::cambios(CambioStat::desdeEtapas(StatClave::ATAQUE, -1))), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                132,
            ),
            self::duelo(
                'prio_stats_inversion',
                self::pokemon('inversion', 'Inversión', [TipoPokemon::PSIQUICO], self::movimiento('Inversión', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::INVERTIR_STATS), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                133,
            ),
            // Limpieza de cambios de estadísticas (Haze/Niebla).
            self::duelo(
                'prio_stats_limpieza',
                self::pokemon('limpieza', 'Limpieza', [TipoPokemon::PSIQUICO], self::movimiento('Niebla', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::NIEBLA), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                134,
            ),
        ];
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosObjetos(): array
    {
        $items = [
            'restos' => 'leftovers',
            'orbe_vida' => 'life_orb',
            'banda_focus' => 'focus_sash',
            'baya_sitrus' => 'sitrus_berry',
            'baya_lum' => 'lum_berry',
            'chaleco_asalto' => 'assault_vest',
        ];

        $escenarios = [];
        $semilla = 141;

        foreach ($items as $clave => $item) {
            $escenarios[] = self::duelo(
                'prio_objeto_'.$clave,
                self::pokemon(
                    'objeto_'.$clave,
                    'Objeto '.$clave,
                    [TipoPokemon::ACERO],
                    self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                    item: $item,
                    hp: 90,
                ),
                self::pokemon(
                    'rival_'.$clave,
                    'Rival',
                    [TipoPokemon::NORMAL],
                    self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                ),
                $semilla++,
            );
        }

        return $escenarios;
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosCombate(): array
    {
        return [
            self::duelo(
                'prio_multigolpe',
                self::pokemon('multi', 'Multi', [TipoPokemon::NORMAL], self::movimiento('Doble Golpe', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO, golpes: 2, fraccionPorGolpe: 0.5), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                151,
            ),
            self::duelo(
                'prio_secundario_estado',
                self::pokemon('secundario', 'Secundario', [TipoPokemon::FUEGO], self::movimiento('Ascuas', 60, TipoPokemon::FUEGO, CategoriaMovimiento::ESPECIAL, EstadoPokemon::BURN), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                152,
            ),
            self::duelo(
                'prio_control_mofa',
                self::pokemon('mofa', 'Mofa', [TipoPokemon::SINIESTRO], self::movimiento('Mofa', 0, TipoPokemon::SINIESTRO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::MOFA), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                153,
            ),
        ];
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosKO(): array
    {
        return [
            self::duelo(
                'prio_al_debilitar_detonacion',
                self::pokemon('verdugo', 'Verdugo', [TipoPokemon::NORMAL], self::movimiento('Golpe', 120, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), atk: 120, speed: 120),
                self::pokemon('bomba', 'Bomba', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), effectKeys: ['detonacion'], hp: 30),
                161,
            ),
            // La campaña no garantiza un KO previo; el ejecutor de Revivir se
            // ejercita y registra "sin aliados debilitados". El caso completo
            // (KO + revivir) queda para la batería determinista B2.
            self::duelo(
                'prio_revivir_sin_aliado',
                self::pokemon('revivir', 'Revivir', [TipoPokemon::PSIQUICO], self::movimiento('Revivir', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::REVIVIR), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                162,
            ),
        ];
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosDano(): array
    {
        return [
            self::duelo(
                'prio_dano_directo',
                self::pokemon('directo', 'Directo', [TipoPokemon::NORMAL], self::movimiento('Golpe', 120, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                171,
            ),
            self::duelo(
                'prio_recoil',
                self::pokemon('recoil', 'Recoil', [TipoPokemon::NORMAL], self::movimiento('Derribo', 90, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO, recoil: 0.33), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                172,
            ),
            self::duelo(
                'prio_drenaje',
                self::pokemon('drenaje', 'Drenaje', [TipoPokemon::PLANTA], self::movimiento('Absorber', 60, TipoPokemon::PLANTA, CategoriaMovimiento::ESPECIAL, drenaje: 0.5), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                173,
            ),
        ];
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosProteccion(): array
    {
        return [
            self::duelo(
                'prio_pantalla_luz',
                self::pokemon('pantalla', 'Pantalla', [TipoPokemon::PSIQUICO], self::movimiento('Pantalla de Luz', 0, TipoPokemon::PSIQUICO, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::PANTALLA_LUZ), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                181,
            ),
            self::duelo(
                'prio_trampa_rocas',
                self::pokemon('trampa', 'Trampa', [TipoPokemon::ROCA], self::movimiento('Trampa Rocas', 0, TipoPokemon::ROCA, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::TRAMPA_ROCAS), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                182,
            ),
            self::duelo(
                'prio_proteccion',
                self::pokemon('proteccion', 'Protección', [TipoPokemon::NORMAL], self::movimiento('Protección', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::PROTECCION), speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                183,
            ),
        ];
    }

    /** @return list<EscenarioBatalla> */
    private static function priorizadosInvocaciones(): array
    {
        return [
            self::duelo(
                'prio_sustituto',
                self::pokemon('sustituto', 'Sustituto', [TipoPokemon::NORMAL], self::movimiento('Sustituto', 0, TipoPokemon::NORMAL, CategoriaMovimiento::ESTADO, claveEfecto: ClaveEfectoMovimiento::SUSTITUTO), hp: 100, speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                191,
            ),
            // Invocación estable: Refuerzos crea un clon al recibir el primer
            // daño. El clon limpia efectos, así que no puede volver a invocar.
            self::duelo(
                'prio_invocacion_refuerzos',
                self::pokemon('invocador', 'Invocador', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO), effectKeys: ['refuerzos'], hp: 120, speed: 120),
                self::pokemon('rival', 'Rival', [TipoPokemon::NORMAL], self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)),
                192,
            ),
        ];
    }

    private static function escenarioConClave(string $nombre, array $effectKeys, ?string $item): EscenarioBatalla
    {
        return self::duelo(
            $nombre,
            self::pokemon(
                'sujeto',
                'Sujeto',
                [TipoPokemon::NORMAL],
                self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
                effectKeys: $effectKeys,
                item: $item,
                speed: 120,
            ),
            self::pokemon(
                'rival',
                'Rival',
                [TipoPokemon::NORMAL],
                self::movimiento('Placaje', 60, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            ),
            1,
            maxRondas: 30,
        );
    }

    private static function escenario(
        string $nombre,
        DatosPokemonBatallaCollection $equipo1,
        DatosPokemonBatallaCollection $equipo2,
        int $semilla,
        int $maxRondas = 20,
        ?TipoClima $clima = null,
        ?TipoTerreno $terreno = null,
    ): EscenarioBatalla {
        return new EscenarioBatalla(
            nombre: $nombre,
            equipo1: $equipo1,
            equipo2: $equipo2,
            clima: $clima,
            terreno: $terreno,
            semilla: $semilla,
            maxRondas: $maxRondas,
        );
    }

    private static function duelo(
        string $nombre,
        DatosPokemonBatalla $equipo1,
        DatosPokemonBatalla $equipo2,
        int $semilla,
        int $maxRondas = 20,
        ?TipoClima $clima = null,
        ?TipoTerreno $terreno = null,
    ): EscenarioBatalla {
        return self::escenario(
            $nombre,
            self::equipo($equipo1),
            self::equipo($equipo2),
            $semilla,
            $maxRondas,
            $clima,
            $terreno,
        );
    }

    private static function equipo(DatosPokemonBatalla ...$datos): DatosPokemonBatallaCollection
    {
        return new DatosPokemonBatallaCollection(array_values($datos));
    }

    private static function cambios(CambioStat ...$cambios): CambiosStatsCollection
    {
        return new CambiosStatsCollection(array_values($cambios));
    }

    private static function movimiento(
        string $nombre,
        int $potencia,
        TipoPokemon $tipo,
        CategoriaMovimiento $categoria,
        ?EstadoPokemon $estado = null,
        ?ClaveEfectoMovimiento $claveEfecto = null,
        int $priority = 0,
        ?CambiosStatsCollection $selfStatChanges = null,
        ?CambiosStatsCollection $targetStatChanges = null,
        int $golpes = 1,
        float $fraccionPorGolpe = 1.0,
        float $drenaje = 0.0,
        float $recoil = 0.0,
        ObjetivoMovimiento $objetivo = ObjetivoMovimiento::NORMAL,
    ): MovimientoBatalla {
        return new MovimientoBatalla(
            nombre: $nombre,
            potencia: $potencia,
            tipo: $tipo,
            categoria: $categoria,
            statusEffect: $estado ?? EstadoPokemon::NONE,
            priority: $priority,
            selfStatChanges: $selfStatChanges ?? new CambiosStatsCollection(),
            targetStatChanges: $targetStatChanges ?? new CambiosStatsCollection(),
            perfil: new PerfilMovimiento(
                golpesMin: $golpes,
                golpesMax: $golpes,
                fraccionPotenciaPorGolpe: $fraccionPorGolpe,
                probabilidadEstado: 1.0,
                drenajeFraccion: $drenaje,
                recoilFraccion: $recoil,
                claveEfecto: $claveEfecto,
            ),
            objetivo: $objetivo,
        );
    }

    /**
     * @param  list<TipoPokemon>  $tipos
     * @param  list<string>  $effectKeys
     */
    private static function pokemon(
        string $id,
        string $nombre,
        array $tipos,
        MovimientoBatalla $move,
        array $effectKeys = [],
        ?string $item = null,
        int $hp = 60,
        int $atk = 90,
        int $def = 60,
        int $spAtk = 90,
        int $spDef = 60,
        int $speed = 70,
    ): DatosPokemonBatalla {
        return ConstructorBatallaHarness::dato([
            'id' => $id,
            'nombre' => $nombre,
            'hp' => $hp,
            'atk' => $atk,
            'def' => $def,
            'spAtk' => $spAtk,
            'spDef' => $spDef,
            'speed' => $speed,
            'tipos' => $tipos,
            'posicion' => Posicion::VANGUARDIA,
            'moves' => [$move],
            'effectKeys' => $effectKeys,
            'item' => $item,
            'nivel' => 50,
        ], GeneradorIvs::maximos());
    }
}
