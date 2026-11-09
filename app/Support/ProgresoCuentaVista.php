<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modelo de presentación de la barra de EXP de la cuenta.
 *
 * Todos los valores derivados (nivel, porcentaje, umbrales y segmentos) se
 * calculan en servidor; la vista solo pinta y el cliente solo anima.
 */
final readonly class ProgresoCuentaVista
{
    /**
     * @param  list<ProgresoSegmentoVista>  $segmentos
     */
    public function __construct(
        public int $expAntes,
        public int $expDespues,
        public int $expGanada,
        public int $nivelAntes,
        public int $nivelDespues,
        public int $nivelesGanados,
        public int $porcentajeAntes,
        public int $porcentajeDespues,
        public int $expInicioNivel,
        public int $expSiguiente,
        public array $segmentos,
        public int $nivelesOmitidos,
        public bool $tope,
    ) {
    }

    /**
     * Frontera de vista (contrato snake_case del modal de recompensas).
     *
     * @return array{
     *     exp_antes: int,
     *     exp_despues: int,
     *     exp_ganada: int,
     *     nivel_antes: int,
     *     nivel_despues: int,
     *     niveles_ganados: int,
     *     porcentaje_antes: int,
     *     porcentaje_despues: int,
     *     exp_inicio_nivel: int,
     *     exp_siguiente: int,
     *     segmentos: list<array{nivel: int, desde: int, hasta: int}>,
     *     niveles_omitidos: int,
     *     tope: bool
     * }
     */
    public function aArray(): array
    {
        return [
            'exp_antes' => $this->expAntes,
            'exp_despues' => $this->expDespues,
            'exp_ganada' => $this->expGanada,
            'nivel_antes' => $this->nivelAntes,
            'nivel_despues' => $this->nivelDespues,
            'niveles_ganados' => $this->nivelesGanados,
            'porcentaje_antes' => $this->porcentajeAntes,
            'porcentaje_despues' => $this->porcentajeDespues,
            'exp_inicio_nivel' => $this->expInicioNivel,
            'exp_siguiente' => $this->expSiguiente,
            'segmentos' => array_map(
                static fn (ProgresoSegmentoVista $segmento): array => $segmento->aArray(),
                $this->segmentos,
            ),
            'niveles_omitidos' => $this->nivelesOmitidos,
            'tope' => $this->tope,
        ];
    }
}
