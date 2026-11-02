<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Sendas;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Niveles de las Sendas de tipo de un jugador, indexados por tipo.
 *
 * Es un valor inmutable: la construcción normaliza el nivel a 1-5 e ignora
 * los tipos fuera del catálogo (1-18). Los tipos sin fila se consideran N1,
 * es decir, sin beneficios pasivos. Los rivales usan {@see self::vacio()}.
 */
final readonly class MapaSendaJugador
{
    /**
     * @param  array<int, NivelSenda>  $niveles
     * @param  array<int, mixed>  $beneficios  nivel => porcentaje absoluto (0..1)
     */
    private function __construct(
        private array $niveles,
        private array $beneficios = [],
    ) {
    }

    public static function vacio(): self
    {
        return new self([]);
    }

    /**
     * @param  array<int, int>  $niveles  tipo_id => nivel
     * @param  array<int, mixed>  $beneficios  nivel => porcentaje absoluto (0..1)
     */
    public static function desdeArray(array $niveles, array $beneficios = []): self
    {
        $normalizados = [];

        foreach ($niveles as $tipoId => $nivel) {
            $tipo = TipoPokemon::tryFrom($tipoId);
            if ($tipo === null) {
                continue;
            }

            $normalizados[$tipo->value] = NivelSenda::desdeInt($nivel);
        }

        return new self($normalizados, $beneficios);
    }

    /**
     * Nivel de la Senda del tipo. Sin fila registrada se devuelve N1.
     */
    public function nivel(TipoPokemon $tipo): NivelSenda
    {
        return $this->niveles[$tipo->value] ?? NivelSenda::N1;
    }

    public function beneficios(TipoPokemon $tipo): BeneficiosSenda
    {
        return BeneficiosSenda::para($this->nivel($tipo), $this->beneficios);
    }

    /**
     * Bonus de captura de un Pokémon: el máximo de sus tipos. Sin tipos, 0.
     *
     * @param  TipoPokemon[]  $tipos
     */
    public function bonoCaptura(array $tipos): float
    {
        $maximo = 0.0;

        foreach ($tipos as $tipo) {
            $maximo = max($maximo, $this->beneficios($tipo)->captura);
        }

        return $maximo;
    }

    public function bonoDano(TipoPokemon $tipo): float
    {
        return $this->beneficios($tipo)->danoInfligido;
    }

    public function bonoDefensa(TipoPokemon $tipo): float
    {
        return $this->beneficios($tipo)->danoRecibido;
    }

    public function bonoCaramelo(TipoPokemon $tipo): float
    {
        return $this->beneficios($tipo)->carameloTipo;
    }

    /**
     * @return array<int, int>
     */
    public function toArray(): array
    {
        $niveles = [];

        foreach ($this->niveles as $tipoId => $nivel) {
            $niveles[$tipoId] = $nivel->value;
        }

        return $niveles;
    }
}
