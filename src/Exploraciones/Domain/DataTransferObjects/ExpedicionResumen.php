<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

use Src\Exploraciones\Domain\EstadoExpedicion;

/**
 * Representación compacta de una expedición para el panel y la colección
 * cacheable del cliente.
 *
 * Todos los valores derivados (estado, progreso, tiempos y flags de permiso)
 * llegan ya calculados desde el backend; el frontend no los recalcula.
 */
final readonly class ExpedicionResumen
{
    public function __construct(
        public int $id,
        public int $reclutadoId,
        public int $pokemonId,
        public string $explorador,
        public string $pokemon,
        public string $icono,
        public string $habitat,
        public int $nivel,
        public EstadoExpedicion $estado,
        public string $estadoLabel,
        public string $estadoClase,
        public int $progreso,
        public ?int $restanteMin,
        public ?string $restanteLabel,
        public string $inicio,
        public ?string $inicioVuelta,
        public ?string $fin,
        public bool $indefinido,
        public bool $archivada,
        public bool $puedeCancelar,
        public bool $puedeRecoger,
        public bool $puedeCerrar,
        public bool $puedeArchivar,
    ) {
    }

    /**
     * Frontera — shape exacto consumido por la API/UI.
     *
     * @return array{
     *     id: int,
     *     reclutado_id: int,
     *     pokemon_id: int,
     *     explorador: string,
     *     pokemon: string,
     *     icono: string,
     *     habitat: string,
     *     nivel: int,
     *     estado: string,
     *     estado_label: string,
     *     estado_clase: string,
     *     progreso: int,
     *     restante_min: int|null,
     *     restante_label: string|null,
     *     inicio: string,
     *     inicio_vuelta: string|null,
     *     fin: string|null,
     *     indefinido: bool,
     *     archivada: bool,
     *     puede_cancelar: bool,
     *     puede_recoger: bool,
     *     puede_cerrar: bool,
     *     puede_archivar: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reclutado_id' => $this->reclutadoId,
            'pokemon_id' => $this->pokemonId,
            'explorador' => $this->explorador,
            'pokemon' => $this->pokemon,
            'icono' => $this->icono,
            'habitat' => $this->habitat,
            'nivel' => $this->nivel,
            'estado' => $this->estado->value,
            'estado_label' => $this->estadoLabel,
            'estado_clase' => $this->estadoClase,
            'progreso' => $this->progreso,
            'restante_min' => $this->restanteMin,
            'restante_label' => $this->restanteLabel,
            'inicio' => $this->inicio,
            'inicio_vuelta' => $this->inicioVuelta,
            'fin' => $this->fin,
            'indefinido' => $this->indefinido,
            'archivada' => $this->archivada,
            'puede_cancelar' => $this->puedeCancelar,
            'puede_recoger' => $this->puedeRecoger,
            'puede_cerrar' => $this->puedeCerrar,
            'puede_archivar' => $this->puedeArchivar,
        ];
    }
}
