<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos;

use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoCurarEstado;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoCurarHp;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoMejorarCritico;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoMejorarPrecision;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoModificarStat;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoResistenciaTipo;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoRestaurarTodo;
use Src\Battle\Domain\UsoObjetos\Efectos\EfectoRevivir;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\Items\ClaveMejora;
use Src\Shared\Domain\Items\ClavePocion;
use Src\Shared\Domain\Items\ClaveReductor;
use Src\Shared\Domain\Items\ClaveResistencia;

/**
 * Catálogo estático de los 44 objetos usables en combate.
 *
 * Mapa clave de inventario → efecto. No reutiliza la fábrica de efectos de
 * habilidades/equipados: aquí el objeto lo consume el jugador manualmente.
 */
final class CatalogoEfectosItem
{
    /** @var array<string, EfectoItem>|null */
    private static ?array $efectos = null;

    public static function existe(string $clave): bool
    {
        return isset(self::mapa()[$clave]);
    }

    /**
     * @throws ItemDesconocido
     */
    public static function efecto(string $clave): EfectoItem
    {
        $efectos = self::mapa();

        if (! isset($efectos[$clave])) {
            throw ItemDesconocido::paraClave($clave);
        }

        return $efectos[$clave];
    }

    /**
     * @return list<EfectoItem>
     */
    public static function todos(): array
    {
        return array_values(self::mapa());
    }

    public static function total(): int
    {
        return count(self::mapa());
    }

    /**
     * @return array<string, EfectoItem>
     */
    private static function mapa(): array
    {
        if (self::$efectos !== null) {
            return self::$efectos;
        }

        $efectos = [];

        // 6 bayas base: curación directa y estados concretos.
        $efectos[ClaveBaya::ORAN->clave()] = new EfectoCurarHp(0.10);
        $efectos[ClaveBaya::SITRUS->clave()] = new EfectoCurarHp(0.25);
        $efectos[ClaveBaya::CHERI->clave()] = new EfectoCurarEstado(EstadoPokemon::PARALYSIS);
        $efectos[ClaveBaya::PECHA->clave()] = new EfectoCurarEstado(
            EstadoPokemon::POISON,
            EstadoPokemon::BAD_POISON,
        );
        $efectos[ClaveBaya::RAWST->clave()] = new EfectoCurarEstado(EstadoPokemon::BURN);
        $efectos[ClaveBaya::ASPEAR->clave()] = new EfectoCurarEstado(EstadoPokemon::FREEZE);

        // 8 pociones y restauradores.
        $efectos[ClavePocion::POCION->clave()] = new EfectoCurarHp(0.20);
        $efectos[ClavePocion::SUPER->clave()] = new EfectoCurarHp(0.40);
        $efectos[ClavePocion::HIPER->clave()] = new EfectoCurarHp(0.60);
        $efectos[ClavePocion::MAXIMA->clave()] = new EfectoCurarHp(1.00);
        $efectos[ClavePocion::CURATOTAL->clave()] = new EfectoCurarEstado(
            EstadoPokemon::BURN,
            EstadoPokemon::POISON,
            EstadoPokemon::BAD_POISON,
            EstadoPokemon::PARALYSIS,
            EstadoPokemon::SLEEP,
            EstadoPokemon::FREEZE,
            EstadoPokemon::CONFUSION,
        );
        $efectos[ClavePocion::RESTAURAR_TODO->clave()] = new EfectoRestaurarTodo();
        $efectos[ClavePocion::REVIVIR->clave()] = new EfectoRevivir(0.50);
        $efectos[ClavePocion::MAX_REVIVIR->clave()] = new EfectoRevivir(1.00, true);

        // 7 mejoras: +30 % temporal al aliado.
        foreach (ClaveMejora::cases() as $mejora) {
            $efectos[$mejora->clave()] = match ($mejora) {
                ClaveMejora::ATAQUE => new EfectoModificarStat(StatClave::ATAQUE, 1.30),
                ClaveMejora::DEFENSA => new EfectoModificarStat(StatClave::DEFENSA, 1.30),
                ClaveMejora::ATAQUE_ESPECIAL => new EfectoModificarStat(StatClave::ATAQUE_ESPECIAL, 1.30),
                ClaveMejora::DEFENSA_ESPECIAL => new EfectoModificarStat(StatClave::DEFENSA_ESPECIAL, 1.30),
                ClaveMejora::VELOCIDAD => new EfectoModificarStat(StatClave::VELOCIDAD, 1.30),
                ClaveMejora::PRECISION => new EfectoMejorarPrecision(),
                ClaveMejora::CRITICO => new EfectoMejorarCritico(),
            };
        }

        // 5 reductores: −30 % temporal al rival.
        foreach (ClaveReductor::cases() as $reductor) {
            $efectos[$reductor->clave()] = match ($reductor) {
                ClaveReductor::ATAQUE => new EfectoModificarStat(StatClave::ATAQUE, 0.70),
                ClaveReductor::DEFENSA => new EfectoModificarStat(StatClave::DEFENSA, 0.70),
                ClaveReductor::ATAQUE_ESPECIAL => new EfectoModificarStat(StatClave::ATAQUE_ESPECIAL, 0.70),
                ClaveReductor::DEFENSA_ESPECIAL => new EfectoModificarStat(StatClave::DEFENSA_ESPECIAL, 0.70),
                ClaveReductor::VELOCIDAD => new EfectoModificarStat(StatClave::VELOCIDAD, 0.70),
            };
        }

        // 18 resistencias: −30 % al daño recibido de un tipo.
        foreach (ClaveResistencia::cases() as $resistencia) {
            $efectos[$resistencia->clave()] = new EfectoResistenciaTipo($resistencia->tipo());
        }

        return self::$efectos = $efectos;
    }
}
