<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Condición volátil de un combatiente: duración, origen y daño residual.
 *
 * Es mutable porque los contadores/turnos cambian durante la batalla.
 */
class CondicionVolatil
{
    public function __construct(
        public ClaveCondicion $clave,
        public int $turnos = 0,
        public string $origenId = '',
        public float $fraccionDano = 0.0,
        public bool $vaDirectoAHp = false,
        public bool $esFisico = false,
        public string $objetivoForzadoId = '',
        // MARCADO: multiplicador de daño recibido mientras dure la marca.
        public float $multiplicadorDanoRecibido = 1.0,
        // VINCULO_DANO: fracción del daño que se transfiere al aliado vinculado.
        public float $fraccionDanioCompartido = 0.0,
        // VINCULO_DANO: id del combatiente que recibe la fracción compartida.
        public string $idVinculo = '',
        // SUSTITUTO: PS actuales y máximos del clon que absorbe daño.
        public float $hpSustituto = 0.0,
        public float $hpMaxSustituto = 0.0,
        // TIPO_ALTERADO: tipo defensivo temporal del portador.
        public ?TipoPokemon $tipoOverride = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave->value,
            'turnos' => $this->turnos,
            'origenId' => $this->origenId,
            'fraccionDano' => $this->fraccionDano,
            'vaDirectoAHp' => $this->vaDirectoAHp,
            'esFisico' => $this->esFisico,
            'objetivoForzadoId' => $this->objetivoForzadoId,
            'multiplicadorDanoRecibido' => $this->multiplicadorDanoRecibido,
            'fraccionDanioCompartido' => $this->fraccionDanioCompartido,
            'idVinculo' => $this->idVinculo,
            'hpSustituto' => $this->hpSustituto,
            'hpMaxSustituto' => $this->hpMaxSustituto,
            'tipoOverride' => $this->tipoOverride?->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            clave: ClaveCondicion::from((string) ($datos['clave'] ?? '')),
            turnos: (int) ($datos['turnos'] ?? 0),
            origenId: (string) ($datos['origenId'] ?? ''),
            fraccionDano: (float) ($datos['fraccionDano'] ?? 0.0),
            vaDirectoAHp: (bool) ($datos['vaDirectoAHp'] ?? false),
            esFisico: (bool) ($datos['esFisico'] ?? false),
            objetivoForzadoId: (string) ($datos['objetivoForzadoId'] ?? ''),
            multiplicadorDanoRecibido: (float) ($datos['multiplicadorDanoRecibido'] ?? 1.0),
            fraccionDanioCompartido: (float) ($datos['fraccionDanioCompartido'] ?? 0.0),
            idVinculo: (string) ($datos['idVinculo'] ?? ''),
            hpSustituto: max(0.0, (float) ($datos['hpSustituto'] ?? 0.0)),
            hpMaxSustituto: max(0.0, (float) ($datos['hpMaxSustituto'] ?? 0.0)),
            tipoOverride: isset($datos['tipoOverride'])
                ? TipoPokemon::tryFrom((int) $datos['tipoOverride'])
                : null,
        );
    }
}
