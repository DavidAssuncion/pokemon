<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\ManejadorDanioBase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Daño diferido del agregado: fórmula base, efectividad, STAB, redondeo,
 * muerte y log. Sin estado: recibe la lista pendiente y devuelve la restante.
 */
final class ProcesadorDanioDiferido
{
    /**
     * @param  list<array<string, mixed>>  $pendientes
     * @return list<array<string, mixed>>
     */
    public function procesar(AgregadoBatalla $batalla, array $pendientes): array
    {
        $ronda = $batalla->turnManager()->round();
        $restantes = [];

        foreach ($pendientes as $dato) {
            $rondaDato = isset($dato['ronda']) ? (int) $dato['ronda'] : 0;
            if ($rondaDato <= $ronda) {
                $this->aplicar($batalla, $dato);
            } else {
                $restantes[] = $dato;
            }
        }

        return $restantes;
    }

    /**
     * @param  array<string, mixed>  $dato
     */
    private function aplicar(AgregadoBatalla $batalla, array $dato): void
    {
        $objId = isset($dato['objetivoId']) ? $dato['objetivoId'] : '';
        $objetivo = $this->buscar($batalla, $objId);
        if ($objetivo !== null && ! $objetivo->estaVivo()) {
            $rival = $batalla->equipoDe($objetivo);
            $objetivo = $rival?->combatientesCollection()->vivos()->primero();
        }

        if ($objetivo === null || ! $objetivo->estaVivo()) {
            return;
        }

        $atacante = $this->buscar(
            $batalla,
            isset($dato['atacanteId']) ? $dato['atacanteId'] : '',
        );
        $potencia = max(1, (int) ($dato['potencia'] ?? 1));
        $nivel = max(1, (int) ($dato['nivel'] ?? 50));
        $atk = max(1.0, (float) ($dato['atk'] ?? 1.0));
        $def = max(1.0, $objetivo->obtenerStatEfectivo(StatClave::DEFENSA_ESPECIAL));
        $tipo = TipoPokemon::tryFrom((int) ($dato['tipo'] ?? 1)) ?? TipoPokemon::NORMAL;
        $dano = ManejadorDanioBase::formulaBase($nivel, $potencia, $atk, $def);
        $dano *= $objetivo->efectividadContra($tipo);
        $dano *= (float) ($dato['stab'] ?? 1.0);
        $objetivo->recibirDaño(max(1.0, floor($dano)), true, 0.0);
        $mov = isset($dato['movimiento']) ? $dato['movimiento'] : 'Ataque diferido';
        $batalla->agregarLog($mov.' inflige daño a '.$objetivo->nombre());
        $objetivo->registrarDanoRecibido(max(1.0, floor($dano)), $atacante?->id() ?? '');

        if (! $objetivo->estaVivo()) {
            $batalla->agregarLog('¡'.$objetivo->nombre().' se ha debilitado!');
            $objetivo->notificarDebilitamiento($batalla);
        }
    }

    private function buscar(AgregadoBatalla $batalla, mixed $id): ?Combatiente
    {
        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente->id() === $id) {
                return $combatiente;
            }
        }

        return null;
    }
}
