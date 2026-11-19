<?php

declare(strict_types=1);

namespace Src\Granjas\App;

/**
 * Fachada de acciones de mejora de la granja. Agrupa los casos de uso para que
 * el controller no dependa de demasiados servicios.
 */
final class AccionesGranja
{
    public function __construct(
        private readonly MejorarNido $mejorarNido,
        private readonly MejorarRefugio $mejorarRefugio,
        private readonly AlternarCondicionesInsalubres $alternarCondiciones,
        private readonly MejorarComplemento $mejorarComplemento,
    ) {
    }

    public function nido(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        bool $incluirNoAvistadas = false,
    ): void {
        $this->mejorarNido->handle($userId, $habitatId, $evolutionChainId, $incluirNoAvistadas);
    }

    public function refugio(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        bool $incluirNoAvistadas = false,
    ): void {
        $this->mejorarRefugio->handle($userId, $habitatId, $evolutionChainId, $incluirNoAvistadas);
    }

    public function condiciones(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        bool $incluirNoAvistadas = false,
    ): void {
        $this->alternarCondiciones->handle($userId, $habitatId, $evolutionChainId, $incluirNoAvistadas);
    }

    public function complemento(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        string $stat,
        bool $incluirNoAvistadas = false,
    ): void {
        $this->mejorarComplemento->handle($userId, $habitatId, $evolutionChainId, $stat, $incluirNoAvistadas);
    }
}
