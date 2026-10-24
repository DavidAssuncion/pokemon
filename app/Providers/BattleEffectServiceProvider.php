<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Battle\Domain\Effects\EfectoAclimatacion;
use Src\Battle\Domain\Effects\EfectoAjustaCritico;
use Src\Battle\Domain\Effects\EfectoAlDebilitar;
use Src\Battle\Domain\Effects\EfectoAlmaCorazon;
use Src\Battle\Domain\Effects\EfectoAlmaDragon;
use Src\Battle\Domain\Effects\EfectoAlRomperEscudo;
use Src\Battle\Domain\Effects\EfectoAntidoto;
use Src\Battle\Domain\Effects\EfectoAromaVelo;
use Src\Battle\Domain\Effects\EfectoAuraAliada;
use Src\Battle\Domain\Effects\EfectoBandaFocus;
use Src\Battle\Domain\Effects\EfectoBayaLum;
use Src\Battle\Domain\Effects\EfectoBayaSitrus;
use Src\Battle\Domain\Effects\EfectoBerserk;
use Src\Battle\Domain\Effects\EfectoBoostAbsorcion;
use Src\Battle\Domain\Effects\EfectoComealmas;
use Src\Battle\Domain\Effects\EfectoContactoEstado;
use Src\Battle\Domain\Effects\EfectoContactoEstadoAleatorio;
use Src\Battle\Domain\Effects\EfectoContraataque;
use Src\Battle\Domain\Effects\EfectoCorazaIra;
use Src\Battle\Domain\Effects\EfectoCuracionEstado;
use Src\Battle\Domain\Effects\EfectoCuracionFinTurno;
use Src\Battle\Domain\Effects\EfectoCuraEquipo;
use Src\Battle\Domain\Effects\EfectoDanioFinTurno;
use Src\Battle\Domain\Effects\EfectoDanioPorVidaFaltante;
use Src\Battle\Domain\Effects\EfectoDisemillar;
use Src\Battle\Domain\Effects\EfectoDivideDolor;
use Src\Battle\Domain\Effects\EfectoDobleStab;
use Src\Battle\Domain\Effects\EfectoDormilon;
use Src\Battle\Domain\Effects\EfectoEnredaderas;
use Src\Battle\Domain\Effects\EfectoEspejoMagico;
use Src\Battle\Domain\Effects\EfectoEspinas;
use Src\Battle\Domain\Effects\EfectoEvasion;
use Src\Battle\Domain\Effects\EfectoExpulsarArena;
use Src\Battle\Domain\Effects\EfectoFlexibilidad;
use Src\Battle\Domain\Effects\EfectoFlorAromatica;
use Src\Battle\Domain\Effects\EfectoGasReactivo;
use Src\Battle\Domain\Effects\EfectoGolpesMultiples;
use Src\Battle\Domain\Effects\EfectoGuardiaAmiga;
use Src\Battle\Domain\Effects\EfectoHipnotizador;
use Src\Battle\Domain\Effects\EfectoIgnoraHabilidad;
use Src\Battle\Domain\Effects\EfectoImpulso;
use Src\Battle\Domain\Effects\EfectoInmuneClima;
use Src\Battle\Domain\Effects\EfectoInmuneCritico;
use Src\Battle\Domain\Effects\EfectoInmuneEstado;
use Src\Battle\Domain\Effects\EfectoInmuneFlag;
use Src\Battle\Domain\Effects\EfectoInmuneRecoil;
use Src\Battle\Domain\Effects\EfectoInmunidadTipo;
use Src\Battle\Domain\Effects\EfectoIntimidacion;
use Src\Battle\Domain\Effects\EfectoInvocadorClima;
use Src\Battle\Domain\Effects\EfectoInvocadorTerreno;
use Src\Battle\Domain\Effects\EfectoIra;
use Src\Battle\Domain\Effects\EfectoMeditacion;
use Src\Battle\Domain\Effects\EfectoModificadorDanio;
use Src\Battle\Domain\Effects\EfectoModificadorDanioEscudo;
use Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica;
use Src\Battle\Domain\Effects\EfectoMultiplicadorEstado;
use Src\Battle\Domain\Effects\EfectoMuroMagico;
use Src\Battle\Domain\Effects\EfectoOrbeVida;
use Src\Battle\Domain\Effects\EfectoPantallista;
use Src\Battle\Domain\Effects\EfectoPerforacionArmadura;
use Src\Battle\Domain\Effects\EfectoPielMilagrosa;
use Src\Battle\Domain\Effects\EfectoPrecision;
use Src\Battle\Domain\Effects\EfectoReaccionDanioStats;
use Src\Battle\Domain\Effects\EfectoRedireccionTipo;
use Src\Battle\Domain\Effects\EfectoRefuerzos;
use Src\Battle\Domain\Effects\EfectoRegeneracionDefensa;
use Src\Battle\Domain\Effects\EfectoRestos;
use Src\Battle\Domain\Effects\EfectoStabTipo;
use Src\Battle\Domain\Effects\EfectoSuperguarda;
use Src\Battle\Domain\Effects\EfectoTransformaTipo;
use Src\Battle\Domain\Effects\EfectoUltraimpulso;
use Src\Battle\Domain\Effects\EfectoUndying;
use Src\Battle\Domain\Effects\EfectoVeleta;
use Src\Battle\Domain\Effects\EfectoVeloDulce;
use Src\Battle\Domain\Effects\EfectoVeloFlor;
use Src\Battle\Domain\Effects\EfectoVeloPastel;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\AmbitoDanio;
use Src\Battle\Domain\Enums\ClaveEfecto;
use Src\Battle\Domain\Enums\ClaveItem;
use Src\Battle\Domain\Enums\CondicionDanio;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\Enums\ObjetivoEfecto;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

class BattleEffectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FabricaEfectos::class, function (): FabricaEfectos {
            $fabrica = new FabricaEfectos();

            // Registrar efectos de habilidad
            $fabrica->registrarEfecto(ClaveEfecto::PERFORACION_ARMADURA, EfectoPerforacionArmadura::class, 0.10);
            $fabrica->registrarEfecto(ClaveEfecto::REGEN_DEF, EfectoRegeneracionDefensa::class, 10.0);
            $fabrica->registrarEfecto(ClaveEfecto::TORMENTA_ARENA, EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

            // Climas (EfectoInvocadorClima recibe el tipo de clima como 2º argumento)
            $fabrica->registrarEfecto(ClaveEfecto::SEQUIA, EfectoInvocadorClima::class, TipoClima::SEQUIA);
            $fabrica->registrarEfecto(ClaveEfecto::DILUVIO, EfectoInvocadorClima::class, TipoClima::DILUVIO);
            $fabrica->registrarEfecto(ClaveEfecto::NIEBLA, EfectoInvocadorClima::class, TipoClima::NIEBLA);
            $fabrica->registrarEfecto(ClaveEfecto::GRANIZO, EfectoInvocadorClima::class, TipoClima::GRANIZO);
            $fabrica->registrarEfecto(ClaveEfecto::TURBULENCIAS, EfectoInvocadorClima::class, TipoClima::TURBULENCIAS);

            $this->registrarHabilidades($fabrica);

            // Registrar efectos de objetos equipados
            $fabrica->registrarItem(ClaveItem::RESTOS, EfectoRestos::class);
            $fabrica->registrarItem(ClaveItem::ORBE_VIDA, EfectoOrbeVida::class);
            $fabrica->registrarItem(ClaveItem::BANDA_FOCUS, EfectoBandaFocus::class);
            $fabrica->registrarItem(ClaveItem::BAYA_SITRUS, EfectoBayaSitrus::class);
            $fabrica->registrarItem(ClaveItem::BAYA_LUM, EfectoBayaLum::class);
            $fabrica->registrarItem(
                ClaveItem::CHALECO_ASALTO,
                EfectoMultiplicadorEstadistica::class,
                StatClave::DEFENSA_ESPECIAL,
                1.5,
            );

            return $fabrica;
        });
    }

    /**
     * Registra las habilidades con sus parámetros. Las claves son slugs
     * canónicos; los alias (Mar Llamas, Síntesis, Campo Magnético…) comparten
     * el mismo efecto para que los datos del CSV funcionen sin traducción.
     */
    private function registrarHabilidades(FabricaEfectos $fabrica): void
    {
        // Inmunidades y redirecciones
        $fabrica->registrarEfecto('absorcion_agua', EfectoInmunidadTipo::class, TipoPokemon::AGUA, 0.25);
        $fabrica->registrarEfecto('absorcion_electricidad', EfectoInmunidadTipo::class, TipoPokemon::ELECTRICO, 0.25);
        $fabrica->registrarEfecto('absorcion_fuego', EfectoInmunidadTipo::class, TipoPokemon::FUEGO, 0.0);
        $fabrica->registrarEfecto('absorcion_fuego', EfectoBoostAbsorcion::class, TipoPokemon::FUEGO, 1.5);
        $fabrica->registrarEfecto('colector', EfectoInmunidadTipo::class, TipoPokemon::AGUA, 0.0);
        $fabrica->registrarEfecto('colector', EfectoRedireccionTipo::class, TipoPokemon::AGUA, StatClave::DEFENSA_ESPECIAL, 1);
        $fabrica->registrarEfecto('pararrayos', EfectoInmunidadTipo::class, TipoPokemon::ELECTRICO, 0.0);
        $fabrica->registrarEfecto('pararrayos', EfectoRedireccionTipo::class, TipoPokemon::ELECTRICO, StatClave::DEFENSA_ESPECIAL, 1);

        // Modificadores de daño
        $fabrica->registrarEfecto('armadura_batalla', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.90, CondicionDanio::MOVIMIENTO_FISICO);
        $fabrica->registrarEfecto('garra_dura', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.15, CondicionDanio::MOVIMIENTO_FISICO);
        $fabrica->registrarEfecto('enjambre', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO_VIDA_BAJA, TipoPokemon::BICHO);
        $fabrica->registrarEfecto('mar_llamas', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO_VIDA_BAJA, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('espesura', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO_VIDA_BAJA, TipoPokemon::PLANTA);
        $fabrica->registrarEfecto('torrente', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO_VIDA_BAJA, TipoPokemon::AGUA);
        $fabrica->registrarEfecto('crueldad', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::OBJETIVO_CON_ESTADO);
        $fabrica->registrarEfecto('acero_templado', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ACERO);
        $fabrica->registrarEfecto('escamas', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.875, CondicionDanio::VIDA_COMPLETA);
        $fabrica->registrarEfecto('sombra_traviesa', EfectoPerforacionArmadura::class, 0.15);
        $fabrica->registrarEfecto('demoledor', EfectoModificadorDanioEscudo::class, 1.25);
        $fabrica->registrarEfecto('depredador', EfectoDanioPorVidaFaltante::class);
        $fabrica->registrarEfecto('depredador_prehistorico', EfectoDanioPorVidaFaltante::class);
        $fabrica->registrarEfecto('malvado', EfectoDanioPorVidaFaltante::class);

        // Clima: estadísticas y daño
        $fabrica->registrarEfecto('clorofila', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 1.25, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('impetu_arena', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 1.25, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('velo_agua', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 1.25, TipoClima::DILUVIO);
        $fabrica->registrarEfecto('depredador_arena', EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.50, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('depredador_arena', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 1.50, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('velo_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::TIERRA, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('velo_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ROCA, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('velo_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ACERO, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('velo_arena', EfectoInmuneClima::class, TipoClima::TORMENTA_ARENA);

        // Invocadores de clima con los nombres del CSV
        $fabrica->registrarEfecto('sequia', EfectoInvocadorClima::class, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('llovizna', EfectoInvocadorClima::class, TipoClima::DILUVIO);
        $fabrica->registrarEfecto('granizo', EfectoInvocadorClima::class, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('tormenta_arena', EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);

        // Curación de fin de turno
        $fabrica->registrarEfecto('regeneracion', EfectoCuracionFinTurno::class, 0.125);
        $fabrica->registrarEfecto('sintesis', EfectoCuracionFinTurno::class, 0.125);
        $fabrica->registrarEfecto('bendicion', EfectoCuracionFinTurno::class, 0.125);
        $fabrica->registrarEfecto('protector', EfectoCuracionFinTurno::class, 0.0625, true);
        $fabrica->registrarEfecto('campo_magnetico', EfectoCuracionFinTurno::class, 0.0625, true);
        $fabrica->registrarEfecto('fotosintesis', EfectoCuracionFinTurno::class, 0.125, false, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('manto_helado', EfectoCuracionFinTurno::class, 0.125, false, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('danza_lunar', EfectoCuracionFinTurno::class, 0.1667, false, null, true);

        // Daño de fin de turno
        $fabrica->registrarEfecto('mal_sueno', EfectoDanioFinTurno::class, 0.125, ObjetivoEfecto::ENEMIGOS_DORMIDOS, true, true);
        $fabrica->registrarEfecto('presion', EfectoDanioFinTurno::class, 0.125, ObjetivoEfecto::ESCUDOS_ENEMIGOS, true, false, true);
        $fabrica->registrarEfecto('flor_aromatica', EfectoFlorAromatica::class, 0.0625);

        // Contacto
        $fabrica->registrarEfecto('electricidad_estatica', EfectoContactoEstado::class, EstadoPokemon::PARALYSIS, 0.0625);
        $fabrica->registrarEfecto('punto_toxico', EfectoContactoEstado::class, EstadoPokemon::POISON, 0.0625);
        $fabrica->registrarEfecto('cuerpo_llama', EfectoContactoEstado::class, EstadoPokemon::BURN, 0.30);
        $fabrica->registrarEfecto('efecto_espora', EfectoContactoEstadoAleatorio::class, 0.30, EstadoPokemon::SLEEP, EstadoPokemon::PARALYSIS, EstadoPokemon::POISON);
        $fabrica->registrarEfecto('baba_toxica', EfectoContactoEstado::class, EstadoPokemon::POISON, 0.25, StatClave::VELOCIDAD, -1);
        $fabrica->registrarEfecto('enredaderas', EfectoEnredaderas::class, 0.20, 0.0625);

        // Reacciones y pasivas
        $fabrica->registrarEfecto('bromista', EfectoAlRomperEscudo::class, 0.10);
        $fabrica->registrarEfecto('cabeza_roca', EfectoInmuneRecoil::class);
        $fabrica->registrarEfecto('detonacion', EfectoAlDebilitar::class, 0.25, true);
        $fabrica->registrarEfecto('muro_magico', EfectoMuroMagico::class);
        $fabrica->registrarEfecto('prediccion', EfectoInmuneCritico::class);
        $fabrica->registrarEfecto('prediccion', EfectoAjustaCritico::class, 2.0);
        $fabrica->registrarEfecto('caparazon', EfectoInmuneCritico::class);
        $fabrica->registrarEfecto('mandibula_hidraulica', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::MORDISCO);
        $fabrica->registrarEfecto('doble_ataque', EfectoGolpesMultiples::class, 2, 0.50);
        $fabrica->registrarEfecto('triple_ataque', EfectoGolpesMultiples::class, 3, 0.33);
        $fabrica->registrarEfecto('cuadruple_ataque', EfectoGolpesMultiples::class, 4, 0.25);
        $fabrica->registrarEfecto('pantallista', EfectoPantallista::class);
        $fabrica->registrarEfecto('refuerzos', EfectoRefuerzos::class);
        $fabrica->registrarEfecto('dormilon', EfectoDormilon::class);
        $fabrica->registrarEfecto('meditacion', EfectoMeditacion::class);
        $fabrica->registrarEfecto('comealmas', EfectoComealmas::class);
        $fabrica->registrarEfecto('divide_dolor', EfectoDivideDolor::class);
        $fabrica->registrarEfecto('gas_reactivo', EfectoGasReactivo::class);
        $fabrica->registrarEfecto('neutralizing_gas', EfectoGasReactivo::class);
        $fabrica->registrarEfecto('flexibilidad', EfectoFlexibilidad::class, 0.15);
        $fabrica->registrarEfecto('depredador_marino', EfectoFlexibilidad::class, 0.15);
        $fabrica->registrarEfecto('veleta', EfectoVeleta::class, 1.25);
        $fabrica->registrarEfecto('actualizacion_software', EfectoVeleta::class, 1.25);
        $fabrica->registrarEfecto('hipnotizador', EfectoHipnotizador::class, 0.125);
        $fabrica->registrarEfecto('hypnotizador', EfectoHipnotizador::class, 0.125);
        $fabrica->registrarEfecto('ira', EfectoIra::class, 0.0625);
        $fabrica->registrarEfecto('ira_gyarados', EfectoIra::class, 0.0625);
        $fabrica->registrarEfecto('espinas', EfectoEspinas::class, 0.125);
        // Piel Seca se registra más abajo con sus tres componentes.
        $fabrica->registrarEfecto('evasion', EfectoEvasion::class, 0.125);
        $fabrica->registrarEfecto('alma_dragon', EfectoAlmaDragon::class);
        $fabrica->registrarEfecto('antidoto', EfectoAntidoto::class, 0.125);
        $fabrica->registrarEfecto('vacio', EfectoDobleStab::class);
        $fabrica->registrarEfecto('guardian_mares', EfectoStabTipo::class, TipoPokemon::AGUA);
        $fabrica->registrarEfecto('intimidacion', EfectoIntimidacion::class);
        $fabrica->registrarEfecto('canones', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.25, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::PUÑETAZO);
        $fabrica->registrarEfecto('canones', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.25, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::PULSO);
        $fabrica->registrarEfecto('canones', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.25, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::BALA);
        $fabrica->registrarEfecto('canones', EfectoModificadorDanioEscudo::class, 1.25);
        $fabrica->registrarEfecto('contraataque', EfectoContraataque::class, 30, 40);

        // Supervivencia con 1 HP. Los alias cubren el nombre custom y las
        // variantes habituales del CSV (Robustez/Sturdy).
        $fabrica->registrarEfecto('undying', EfectoUndying::class);
        $fabrica->registrarEfecto('robustez', EfectoUndying::class);
        $fabrica->registrarEfecto('sturdy', EfectoUndying::class);

        // Ignorar habilidades defensivas (Rompemoldes y variantes).
        $fabrica->registrarEfecto('rompemoldes', EfectoIgnoraHabilidad::class);
        $fabrica->registrarEfecto('mold_breaker', EfectoIgnoraHabilidad::class);

        // Habilidades de la siguiente tanda
        $fabrica->registrarEfecto('nado_rapido', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 1.25, TipoClima::DILUVIO);
        $fabrica->registrarEfecto('poder_solar', EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE_ESPECIAL, 1.50, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('cura_lluvia', EfectoCuracionFinTurno::class, 0.125, false, TipoClima::DILUVIO);
        $fabrica->registrarEfecto('multiescamas', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::VIDA_COMPLETA);
        $fabrica->registrarEfecto('adaptable', EfectoDobleStab::class);
        $fabrica->registrarEfecto('armadura_magica', EfectoMuroMagico::class);
        $fabrica->registrarEfecto('puno_fierro', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.20, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::PUÑETAZO);
        $fabrica->registrarEfecto('punk_rock', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::SONIDO);
        $fabrica->registrarEfecto('mandibula_fuerte', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::MORDISCO);
        $fabrica->registrarEfecto('piel_tosca', EfectoEspinas::class, 0.125);
        $fabrica->registrarEfecto('sebo', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('sebo', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::HIELO);
        $fabrica->registrarEfecto('herbivoro', EfectoInmunidadTipo::class, TipoPokemon::PLANTA, 0.0, StatClave::ATAQUE, 1);
        $fabrica->registrarEfecto('filtro', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.75, CondicionDanio::MOVIMIENTO_SUPEREFICAZ);
        $fabrica->registrarEfecto('roca_solida', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.75, CondicionDanio::MOVIMIENTO_SUPEREFICAZ);
        $fabrica->registrarEfecto('entusiasmo', EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.50);

        // Terrenos
        $fabrica->registrarEfecto('electrogenesis', EfectoInvocadorTerreno::class, \Src\Battle\Domain\Enums\TipoTerreno::ELECTRICO);
        $fabrica->registrarEfecto('herbogenesis', EfectoInvocadorTerreno::class, \Src\Battle\Domain\Enums\TipoTerreno::HIERBA);
        $fabrica->registrarEfecto('psicogenesis', EfectoInvocadorTerreno::class, \Src\Battle\Domain\Enums\TipoTerreno::PSIQUICO);
        $fabrica->registrarEfecto('nebulogenesis', EfectoInvocadorTerreno::class, \Src\Battle\Domain\Enums\TipoTerreno::NIEBLA);

        // Control y estados
        $fabrica->registrarEfecto('aclimatacion', EfectoAclimatacion::class);
        $fabrica->registrarEfecto('ritmo_propio', EfectoInmuneEstado::class, [EstadoPokemon::CONFUSION]);
        $fabrica->registrarEfecto('insomnio', EfectoInmuneEstado::class, [EstadoPokemon::SLEEP]);
        $fabrica->registrarEfecto('inmunidad', EfectoInmuneEstado::class, [EstadoPokemon::POISON, EstadoPokemon::BAD_POISON]);
        $fabrica->registrarEfecto('intrepido', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('insonoridad', EfectoInmuneFlag::class, FlagMovimiento::SONIDO);
        $fabrica->registrarEfecto('antibalas', EfectoInmuneFlag::class, FlagMovimiento::BALA);
        $fabrica->registrarEfecto('hidratacion', EfectoCuracionEstado::class, 1.0, TipoClima::DILUVIO);
        $fabrica->registrarEfecto('mudar', EfectoCuracionEstado::class, 0.33);
        $fabrica->registrarEfecto('viscosidad', EfectoContactoEstado::class, EstadoPokemon::NONE, 1.0, StatClave::VELOCIDAD, -1);

        // Daño y precisión
        $fabrica->registrarEfecto('ojo_compuesto', EfectoPrecision::class, 1.30);
        $fabrica->registrarEfecto('potencia_bruta', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::MOVIMIENTO_CON_SECUNDARIO);
        $fabrica->registrarEfecto('armadura_prisma', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.75, CondicionDanio::MOVIMIENTO_SUPEREFICAZ);
        $fabrica->registrarEfecto('piel_milagrosa', EfectoPielMilagrosa::class, 0.75);
        $fabrica->registrarEfecto('guardia_amiga', EfectoGuardiaAmiga::class);
        $fabrica->registrarEfecto('aroma_velo', EfectoAromaVelo::class);
        $fabrica->registrarEfecto('velo_flor', EfectoVeloFlor::class);
        $fabrica->registrarEfecto('espejo_magico', EfectoEspejoMagico::class);

        // 9A: clima y terreno
        $fabrica->registrarEfecto('nevada', EfectoInvocadorClima::class, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('poder_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ROCA, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('poder_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::TIERRA, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('poder_arena', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ACERO, 0.33, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('manto_nivoso', EfectoEvasion::class, 0.125, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('barrera_aerea', EfectoAclimatacion::class);
        $fabrica->registrarEfecto('velo_dulce', EfectoVeloDulce::class);
        $fabrica->registrarEfecto('rizos_enredados', EfectoContactoEstado::class, EstadoPokemon::NONE, 1.0, StatClave::VELOCIDAD, -1);
        // Habilidades reactivas
        $fabrica->registrarEfecto('electromotor', EfectoInmunidadTipo::class, TipoPokemon::ELECTRICO, 0.0, StatClave::VELOCIDAD, 1);
        $fabrica->registrarEfecto('justiciero', EfectoReaccionDanioStats::class, [[StatClave::ATAQUE, 1]], [TipoPokemon::SINIESTRO]);
        $fabrica->registrarEfecto('cobardia', EfectoReaccionDanioStats::class, [[StatClave::VELOCIDAD, 1]], [TipoPokemon::BICHO, TipoPokemon::SINIESTRO, TipoPokemon::FANTASMA]);
        $fabrica->registrarEfecto('firmeza', EfectoReaccionDanioStats::class, [[StatClave::DEFENSA, 1]]);
        $fabrica->registrarEfecto('quitanieves', EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 2.0, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('armadura_fragil', EfectoReaccionDanioStats::class, [[StatClave::DEFENSA, -1], [StatClave::VELOCIDAD, 2]], [], true);
        $fabrica->registrarEfecto('colera', EfectoBerserk::class);
        $fabrica->registrarEfecto('tenacidad', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('competitivo', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('combustible', EfectoReaccionDanioStats::class, [[StatClave::VELOCIDAD, 2]], [TipoPokemon::FUEGO, TipoPokemon::AGUA]);
        $fabrica->registrarEfecto('cuerpo_horneado', EfectoInmunidadTipo::class, TipoPokemon::FUEGO, 0.0, StatClave::DEFENSA, 2);
        $fabrica->registrarEfecto('geofagia', EfectoInmunidadTipo::class, TipoPokemon::TIERRA, 0.25);
        $fabrica->registrarEfecto('sal_purificadora', EfectoInmuneEstado::class, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS, EstadoPokemon::SLEEP, EstadoPokemon::FREEZE, EstadoPokemon::CONFUSION]);
        $fabrica->registrarEfecto('sal_purificadora', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FANTASMA);
        $fabrica->registrarEfecto('cuerpo_aureo', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('expulsarena', EfectoExpulsarArena::class);
        $fabrica->registrarEfecto('disemillar', EfectoDisemillar::class);
        // Habilidades defensivas y potenciadores de tipo
        $fabrica->registrarEfecto('cuerpo_puro', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('humo_blanco', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('corte_fuerte', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('sacapecho', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('vista_lince', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('pelaje_recio', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::MOVIMIENTO_FISICO);
        $fabrica->registrarEfecto('escama_hielo', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::MOVIMIENTO_ESPECIAL);
        $fabrica->registrarEfecto('guardia_espectro', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::VIDA_COMPLETA);
        $fabrica->registrarEfecto('ignifugo', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('peluche', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::CONTACTO);
        $fabrica->registrarEfecto('peluche', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 2.00, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('funda', EfectoInmuneFlag::class, FlagMovimiento::POLVO);
        $fabrica->registrarEfecto('funda', EfectoInmuneClima::class, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('funda', EfectoInmuneClima::class, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('pompa', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 2.00, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::AGUA);
        $fabrica->registrarEfecto('pompa', EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 0.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('pompa', EfectoInmuneEstado::class, [EstadoPokemon::BURN]);
        $fabrica->registrarEfecto('termoconversion', EfectoReaccionDanioStats::class, [[StatClave::ATAQUE, 1]], [TipoPokemon::FUEGO]);
        $fabrica->registrarEfecto('termoconversion', EfectoInmuneEstado::class, [EstadoPokemon::BURN]);
        $fabrica->registrarEfecto('hidrorrefuerzo', EfectoReaccionDanioStats::class, [[StatClave::DEFENSA, 2]], [TipoPokemon::AGUA]);
        $fabrica->registrarEfecto('coraza_ira', EfectoCorazaIra::class);
        $fabrica->registrarEfecto('velo_pastel', EfectoVeloPastel::class);
        $fabrica->registrarEfecto('mandibula_dragon', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::DRAGON);
        $fabrica->registrarEfecto('transistor', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ELECTRICO);
        $fabrica->registrarEfecto('transportarrocas', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ROCA);
        // Siguiente tanda de habilidades
        $fabrica->registrarEfecto('superguarda', EfectoSuperguarda::class);
        $fabrica->registrarEfecto('levitacion', EfectoInmunidadTipo::class, TipoPokemon::TIERRA, 0.0);
        $fabrica->registrarEfecto('telepatia', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('ventosas', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('espiritu_vital', EfectoInmuneEstado::class, [EstadoPokemon::SLEEP]);
        $fabrica->registrarEfecto('escudo_magma', EfectoInmuneEstado::class, [EstadoPokemon::FREEZE]);
        $fabrica->registrarEfecto('despiste', EfectoInmuneEstado::class, [EstadoPokemon::CONFUSION]);
        $fabrica->registrarEfecto('perro_guardian', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('agallas', EfectoMultiplicadorEstado::class, StatClave::ATAQUE, 1.50, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS, EstadoPokemon::SLEEP, EstadoPokemon::FREEZE]);
        $fabrica->registrarEfecto('pies_rapidos', EfectoMultiplicadorEstado::class, StatClave::VELOCIDAD, 1.50, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS, EstadoPokemon::SLEEP, EstadoPokemon::FREEZE]);
        $fabrica->registrarEfecto('impetu_toxico', EfectoMultiplicadorEstado::class, StatClave::ATAQUE, 1.50, [EstadoPokemon::POISON, EstadoPokemon::BAD_POISON]);
        $fabrica->registrarEfecto('impetu_ardiente', EfectoMultiplicadorEstado::class, StatClave::ATAQUE_ESPECIAL, 1.50, [EstadoPokemon::BURN]);
        $fabrica->registrarEfecto('escama_especial', EfectoMultiplicadorEstado::class, StatClave::DEFENSA, 1.50, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS, EstadoPokemon::SLEEP, EstadoPokemon::FREEZE]);
        $fabrica->registrarEfecto('afortunado', EfectoAjustaCritico::class, 2.0);
        $fabrica->registrarEfecto('ultraimpulso', EfectoUltraimpulso::class);
        $fabrica->registrarEfecto('coranimas', EfectoAlmaCorazon::class);
        $fabrica->registrarEfecto('impulso', EfectoImpulso::class);
        $fabrica->registrarEfecto('don_floral', EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.50, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('don_floral', EfectoMultiplicadorEstadistica::class, StatClave::DEFENSA_ESPECIAL, 1.50, TipoClima::SEQUIA);
        // Tanda 12: stats, tipado y equipo
        $fabrica->registrarEfecto('simple', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('inversion', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('ignorante', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('punto_ira', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('francotirador', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('encadenamiento', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('experto', EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::MOVIMIENTO_POTENCIA_BAJA);
        $fabrica->registrarEfecto('aerilacion', EfectoTransformaTipo::class, TipoPokemon::VOLADOR, true);
        $fabrica->registrarEfecto('pixilacion', EfectoTransformaTipo::class, TipoPokemon::HADA, true);
        $fabrica->registrarEfecto('refrigeracion', EfectoTransformaTipo::class, TipoPokemon::HIELO, true);
        $fabrica->registrarEfecto('galvanizacion', EfectoTransformaTipo::class, TipoPokemon::ELECTRICO, true);
        $fabrica->registrarEfecto('normalizacion', EfectoTransformaTipo::class, TipoPokemon::NORMAL, false);
        $fabrica->registrarEfecto('voz_liquida', EfectoTransformaTipo::class, TipoPokemon::AGUA, false, FlagMovimiento::SONIDO);
        $fabrica->registrarEfecto('valentia', EfectoInmuneEstado::class, []);
        $fabrica->registrarEfecto('bateria', EfectoAuraAliada::class, 1.30, CondicionDanio::MOVIMIENTO_ESPECIAL);
        $fabrica->registrarEfecto('punto_energia', EfectoAuraAliada::class, 1.30);
        $fabrica->registrarEfecto('espiritu_acerado', EfectoAuraAliada::class, 1.50, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::ACERO);
        $fabrica->registrarEfecto('curacion', EfectoCuraEquipo::class, 0.30);

        // Cierre de catálogo: habilidades principales
        $fabrica->registrarEfecto('contacto_condicion', \Src\Battle\Domain\Effects\EfectoContactoCondicion::class, \Src\Battle\Domain\Enums\ClaveCondicion::FLINCH, 0.10);
        $fabrica->registrarEfecto('humedad', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('cambio_color', \Src\Battle\Domain\Effects\EfectoCambioColor::class);
        $fabrica->registrarEfecto('polvo_escudo', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('sincronia', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('dicha', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('potencia', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 2.0);
        $fabrica->registrarEfecto('chorro_arena', EfectoInvocadorClima::class, TipoClima::TORMENTA_ARENA);
        $fabrica->registrarEfecto('madrugar', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('ausente', \Src\Battle\Domain\Effects\EfectoTruant::class);
        $fabrica->registrarEfecto('lodo_liquido', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('energia_pura', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 2.0);
        $fabrica->registrarEfecto('tumbos', \Src\Battle\Domain\Effects\EfectoTangledFeet::class);
        $fabrica->registrarEfecto('impasible', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('descarga', \Src\Battle\Domain\Effects\EfectoDescarga::class);
        $fabrica->registrarEfecto('indefenso', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('defensa_hoja', \Src\Battle\Domain\Effects\EfectoInmuneEstadoClima::class, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS, EstadoPokemon::SLEEP, EstadoPokemon::FREEZE, EstadoPokemon::CONFUSION], TipoClima::SEQUIA);
        $fabrica->registrarEfecto('cromolente', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 2.0, CondicionDanio::MOVIMIENTO_POCO_EFICAZ);
        $fabrica->registrarEfecto('inicio_lento', \Src\Battle\Domain\Effects\EfectoInicioLento::class);
        $fabrica->registrarEfecto('gelido', EfectoCuracionFinTurno::class, 0.0625, false, TipoClima::GRANIZO);
        $fabrica->registrarEfecto('audaz', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.20, CondicionDanio::MOVIMIENTO_CON_RECOIL);
        $fabrica->registrarEfecto('flaqueza', \Src\Battle\Domain\Effects\EfectoEstadisticaVida::class, StatClave::ATAQUE, 0.50);
        $fabrica->registrarEfecto('flaqueza', \Src\Battle\Domain\Effects\EfectoEstadisticaVida::class, StatClave::ATAQUE_ESPECIAL, 0.50);
        $fabrica->registrarEfecto('cuerpo_maldito', \Src\Battle\Domain\Effects\EfectoContactoCondicion::class, \Src\Battle\Domain\Enums\ClaveCondicion::SILENCIO, 0.30, 2);
        $fabrica->registrarEfecto('metal_pesado', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('metal_liviano', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('toque_toxico', EfectoContactoEstado::class, EstadoPokemon::POISON, 0.30);
        $fabrica->registrarEfecto('calculo_final', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.30);
        $fabrica->registrarEfecto('allanamiento', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('autoestima', \Src\Battle\Domain\Effects\EfectoMoxie::class, StatClave::ATAQUE, 1);
        $fabrica->registrarEfecto('punta_acero', EfectoEspinas::class, 0.125);
        $fabrica->registrarEfecto('tinovictoria', \Src\Battle\Domain\Effects\EfectoAuraPrecision::class);
        $fabrica->registrarEfecto('turbollama', EfectoIgnoraHabilidad::class);
        $fabrica->registrarEfecto('terravoltaje', EfectoIgnoraHabilidad::class);
        $fabrica->registrarEfecto('mutatipo', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('libero', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('megadisparador', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::PULSO);
        $fabrica->registrarEfecto('manto_frondoso', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::DEFENSA, 1.50);
        $fabrica->registrarEfecto('amor_filial', EfectoGolpesMultiples::class, 2, 0.50);
        $fabrica->registrarEfecto('aura_oscura', \Src\Battle\Domain\Effects\EfectoAuraAliada::class, 1.33, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::SINIESTRO);
        $fabrica->registrarEfecto('aura_feerica', \Src\Battle\Domain\Effects\EfectoAuraAliada::class, 1.33, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::HADA);
        $fabrica->registrarEfecto('rompeaura', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('ensañamiento', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('remoto', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('cola_surf', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::VELOCIDAD, 2.0);
        $fabrica->registrarEfecto('corrosion', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('letargo_perenne', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('reves', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('guardia_metalica', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('fuerza_cerebral', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.25, CondicionDanio::MOVIMIENTO_SUPEREFICAZ);
        $fabrica->registrarEfecto('espada_indomita', \Src\Battle\Domain\Effects\EfectoEntradaStats::class, [[StatClave::ATAQUE, 1]]);
        $fabrica->registrarEfecto('escudo_recio', \Src\Battle\Domain\Effects\EfectoEntradaStats::class, [[StatClave::DEFENSA, 1]]);
        $fabrica->registrarEfecto('pelusa', \Src\Battle\Domain\Effects\EfectoReaccionRivalesStats::class, StatClave::VELOCIDAD, -1);
        $fabrica->registrarEfecto('coraza_reflejo', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('mimetismo', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('antibarrera', \Src\Battle\Domain\Effects\EfectoScreenCleaner::class);
        $fabrica->registrarEfecto('cuerpo_mortal', \Src\Battle\Domain\Effects\EfectoPerishBody::class);
        $fabrica->registrarEfecto('medicina_extrana', \Src\Battle\Domain\Effects\EfectoCuriousMedicine::class);
        $fabrica->registrarEfecto('relincho_blanco', \Src\Battle\Domain\Effects\EfectoMoxie::class, StatClave::ATAQUE, 1);
        $fabrica->registrarEfecto('relincho_negro', \Src\Battle\Domain\Effects\EfectoMoxie::class, StatClave::ATAQUE_ESPECIAL, 1);
        $fabrica->registrarEfecto('energia_eolica', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('dinamo', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('paleosintesis', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.30, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('carga_cuark', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.30);
        $fabrica->registrarEfecto('caldero_debacle', \Src\Battle\Domain\Effects\EfectoRuin::class, StatClave::ATAQUE_ESPECIAL, -1);
        $fabrica->registrarEfecto('espada_debacle', \Src\Battle\Domain\Effects\EfectoRuin::class, StatClave::DEFENSA, -1);
        $fabrica->registrarEfecto('tablilla_debacle', \Src\Battle\Domain\Effects\EfectoRuin::class, StatClave::ATAQUE, -1);
        $fabrica->registrarEfecto('abalorio_debacle', \Src\Battle\Domain\Effects\EfectoRuin::class, StatClave::DEFENSA_ESPECIAL, -1);
        $fabrica->registrarEfecto('latido_oricalco', EfectoInvocadorClima::class, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('latido_oricalco', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE, 1.33, TipoClima::SEQUIA);
        $fabrica->registrarEfecto('motor_hadronico', EfectoInvocadorTerreno::class, \Src\Battle\Domain\Enums\TipoTerreno::ELECTRICO);
        $fabrica->registrarEfecto('motor_hadronico', \Src\Battle\Domain\Effects\EfectoMultiplicadorEstadistica::class, StatClave::ATAQUE_ESPECIAL, 1.33);
        $fabrica->registrarEfecto('oportunista', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('cortante', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::INFLIGIDO, 1.50, CondicionDanio::FLAG_MOVIMIENTO, null, 0.33, null, FlagMovimiento::CORTE);
        $fabrica->registrarEfecto('general_supremo', \Src\Battle\Domain\Effects\EfectoSupremeOverlord::class);
        $fabrica->registrarEfecto('unisono', \Src\Battle\Domain\Effects\EfectoCostar::class);
        $fabrica->registrarEfecto('capa_toxica', \Src\Battle\Domain\Effects\EfectoToxicDebris::class);
        $fabrica->registrarEfecto('ojo_mental', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('nectar_dulce', \Src\Battle\Domain\Effects\EfectoEntradaStats::class, [[StatClave::EVASION, -1]], true);
        $fabrica->registrarEfecto('hospitalidad', \Src\Battle\Domain\Effects\EfectoHospitality::class);
        $fabrica->registrarEfecto('cadena_toxica', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('titere_toxico', \Src\Battle\Domain\Effects\EfectoMarcador::class);
        $fabrica->registrarEfecto('piel_dragontina', \Src\Battle\Domain\Effects\EfectoTransformaTipo::class, TipoPokemon::DRAGON, true);
        $fabrica->registrarEfecto('salpicante', EfectoContactoEstado::class, EstadoPokemon::BURN, 1.0);
        $fabrica->registrarEfecto('piel_seca', EfectoInmunidadTipo::class, TipoPokemon::AGUA, 0.25);
        $fabrica->registrarEfecto('piel_seca', \Src\Battle\Domain\Effects\EfectoModificadorDanio::class, AmbitoDanio::RECIBIDO, 1.25, CondicionDanio::TIPO_MOVIMIENTO, TipoPokemon::FUEGO);
        $fabrica->registrarEfecto('piel_seca', \Src\Battle\Domain\Effects\EfectoDanioFinTurno::class, 0.0625, \Src\Battle\Domain\Enums\ObjetivoEfecto::PROPIO, false, true, false, TipoClima::SEQUIA);

    }




}
