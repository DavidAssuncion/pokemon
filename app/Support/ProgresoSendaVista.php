<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modelo de presentación de la barra de EXP de una Senda de tipo.
 *
 * `exp_antes` / `exp_despues` son la EXP real acumulada; `exp_mostrada_*`
 * están clampadas al tope de presentación de la Senda.
 */
final readonly class ProgresoSendaVista
{
    /**
     * @param  list<ProgresoSegmentoVista>  $segmentos
     */
    public function __construct(
        public int $tipoId,
        public string $label,
        public string $slug,
        public string $color,
        public string $icono,
        public int $expAntes,
        public int $expDespues,
        public int $expGanada,
        public int $expMostradaAntes,
        public int $expMostradaDespues,
        public int $expMaxima,
        public int $nivelAntes,
        public int $nivelDespues,
        public int $nivelMaximo,
        public int $nivelesGanados,
        public int $porcentajeAntes,
        public int $porcentajeDespues,
        public int $expInicioNivel,
        public int $expSiguiente,
        public bool $tope,
        public array $segmentos,
        public int $nivelesOmitidos,
    ) {
    }

    /**
     * Frontera de vista (contrato snake_case del modal de recompensas).
     *
     * @return array{
     *     tipo_id: int,
     *     label: string,
     *     slug: string,
     *     color: string,
     *     icono: string,
     *     exp_antes: int,
     *     exp_despues: int,
     *     exp_ganada: int,
     *     exp_mostrada_antes: int,
     *     exp_mostrada_despues: int,
     *     exp_maxima: int,
     *     nivel_antes: int,
     *     nivel_despues: int,
     *     nivel_maximo: int,
     *     niveles_ganados: int,
     *     porcentaje_antes: int,
     *     porcentaje_despues: int,
     *     exp_inicio_nivel: int,
     *     exp_siguiente: int,
     *     tope: bool,
     *     segmentos: list<array{nivel: int, desde: int, hasta: int}>,
     *     niveles_omitidos: int
     * }
     */
    public function aArray(): array
    {
        return [
            'tipo_id' => $this->tipoId,
            'label' => $this->label,
            'slug' => $this->slug,
            'color' => $this->color,
            'icono' => $this->icono,
            'exp_antes' => $this->expAntes,
            'exp_despues' => $this->expDespues,
            'exp_ganada' => $this->expGanada,
            'exp_mostrada_antes' => $this->expMostradaAntes,
            'exp_mostrada_despues' => $this->expMostradaDespues,
            'exp_maxima' => $this->expMaxima,
            'nivel_antes' => $this->nivelAntes,
            'nivel_despues' => $this->nivelDespues,
            'nivel_maximo' => $this->nivelMaximo,
            'niveles_ganados' => $this->nivelesGanados,
            'porcentaje_antes' => $this->porcentajeAntes,
            'porcentaje_despues' => $this->porcentajeDespues,
            'exp_inicio_nivel' => $this->expInicioNivel,
            'exp_siguiente' => $this->expSiguiente,
            'tope' => $this->tope,
            'segmentos' => array_map(
                static fn (ProgresoSegmentoVista $segmento): array => $segmento->aArray(),
                $this->segmentos,
            ),
            'niveles_omitidos' => $this->nivelesOmitidos,
        ];
    }
}
