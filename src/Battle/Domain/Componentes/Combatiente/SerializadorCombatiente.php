<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Collections\CondicionesVolatilesCollection;
use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\ValueObjects\JefeEstado;
use Src\Pokemon\Domain\PokemonEntity;

/**
 * Mapeo del estado del combatiente al payload de sesión y viceversa.
 */
final class SerializadorCombatiente
{
    /**
     * Orden histórico de claves del payload de sesión.
     *
     * @var list<string>
     */
    private const ORDEN = [
        'hpActual',
        'defensaHpActual',
        'defensaEspHpActual',
        'vecesActuadoEstaRonda',
        'accionesExtra',
        'extraOtorgada',
        'estado',
        'contadorVenenoGrave',
        'cargasVeneno',
        'turnosEstado',
        'multiplicadores',
        'resistencias',
        'id',
        'nombre',
        'iconName',
        'speciesId',
        'formSuffix',
        'shiny',
        'item',
        'itemCargas',
        'itemConsumido',
        'itemBloqueadoHastaRonda',
        'movimientoBloqueado',
        'habilidadAnulada',
        'habilidadAnuladaPorGas',
        'efectosHabilidadGuardados',
        'jefe',
        'bonificadorDanio',
        'ultimoDanoRecibido',
        'ultimoAtacanteId',
        'danoRecibidoEstaRonda',
        'golpesRecibidos',
        'ultimaRondaProteccion',
        'costeVelocidad',
        'rondaActual',
        'costeVelocidadHastaRonda',
        'clima',
        'postura',
        'objetivoCubiertoId',
        'prioridadDeclarada',
        'cargasMovimiento',
        'usosMovimiento',
        'turnoRetrasado',
        'retrasoUsado',
        'combo',
        'comboMovimiento',
        'turnosInvocacion',
        'invocadoPorId',
        'debilidadNotificada',
        'condiciones',
        'effects',
        'pokemon',
        'posicion',
        'senda',
    ];

    /**
     * @param  list<array<string, mixed>>  $partes
     * @return array<string, mixed>
     */
    public static function serializar(array $partes): array
    {
        return array_replace(array_flip(self::ORDEN), array_merge(...$partes));
    }

    public static function serializarGuardados(ColeccionEfectos $guardados): string
    {
        return serialize($guardados->all());
    }

    public static function serializarJefe(?JefeEstado $jefe): string
    {
        return serialize($jefe);
    }

    public static function serializarCondiciones(CondicionesVolatilesCollection $condiciones): string
    {
        return serialize($condiciones);
    }

    public static function serializarPokemon(PokemonEntity $pokemon): string
    {
        return serialize($pokemon);
    }

    public static function deserializarGuardados(mixed $raw): ColeccionEfectos
    {
        $guardados = is_string($raw) ? unserialize($raw) : $raw;
        $guardados = is_array($guardados)
            ? array_values(array_filter(
                $guardados,
                static fn (mixed $efecto): bool => $efecto instanceof InterfazEfecto,
            ))
            : [];

        $coleccion = new ColeccionEfectos();
        foreach ($guardados as $efecto) {
            $coleccion->add($efecto);
        }

        return $coleccion;
    }

    public static function deserializarJefe(mixed $raw): ?JefeEstado
    {
        $jefe = is_string($raw) ? unserialize($raw) : $raw;

        return $jefe instanceof JefeEstado ? $jefe : null;
    }

    public static function deserializarCondiciones(mixed $raw): CondicionesVolatilesCollection
    {
        $condiciones = isset($raw) && is_string($raw) ? unserialize($raw) : null;

        return $condiciones instanceof CondicionesVolatilesCollection
            ? $condiciones
            : new CondicionesVolatilesCollection();
    }

    public static function deserializarEfectos(mixed $raw): ColeccionEfectos
    {
        $effects = isset($raw) && is_string($raw) ? unserialize($raw) : null;

        return $effects instanceof ColeccionEfectos ? $effects : new ColeccionEfectos();
    }

    public static function deserializarPokemon(mixed $raw): PokemonEntity
    {
        if (! isset($raw)) {
            throw new \RuntimeException('Missing pokemon data');
        }

        $pokemon = unserialize($raw);

        if (! $pokemon instanceof PokemonEntity) {
            throw new \RuntimeException('Missing pokemon data');
        }

        return $pokemon;
    }
}
