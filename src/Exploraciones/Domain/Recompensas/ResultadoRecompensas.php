<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resultado agregado del cálculo de recompensas de una expedición.
 * Inmutable; valida el tipo de cada item de sus colecciones.
 *
 * `expTotal` es la EXP al jugador (100 %); `expPorMiembro` es la EXP que recibe
 * CADA integrante del equipo (reparto 80 % entre 3, D3/RF-14).
 */
final class ResultadoRecompensas
{
    /** @var Collection<int, RecompensaCaptura> */
    public readonly Collection $capturas;

    /** @var Collection<int, RecompensaFamilia> */
    public readonly Collection $caramelosFamilia;

    /** @var Collection<int, RecompensaEv> */
    public readonly Collection $caramelosEv;

    /** @var Collection<int, RecompensaTipo> */
    public readonly Collection $caramelosTipo;

    /**
     * @param  Collection<int, RecompensaCaptura>|array<int, RecompensaCaptura>  $capturas
     * @param  Collection<int, RecompensaFamilia>|array<int, RecompensaFamilia>  $caramelosFamilia
     * @param  Collection<int, RecompensaEv>|array<int, RecompensaEv>  $caramelosEv
     * @param  Collection<int, RecompensaTipo>|array<int, RecompensaTipo>  $caramelosTipo
     * @param  array<string, int>  $expTipoPorMiembro  EXP de tipo que recibe CADA
     *                                                 integrante (clave = label de tipo)
     */
    public function __construct(
        Collection|array $capturas,
        Collection|array $caramelosFamilia,
        Collection|array $caramelosEv,
        Collection|array $caramelosTipo,
        public readonly int $expTotal,
        public readonly int $expPorMiembro = 0,
        public readonly array $expTipoPorMiembro = [],
    ) {
        $this->capturas = self::coleccionTipada($capturas, RecompensaCaptura::class);
        $this->caramelosFamilia = self::coleccionTipada($caramelosFamilia, RecompensaFamilia::class);
        $this->caramelosEv = self::coleccionTipada($caramelosEv, RecompensaEv::class);
        $this->caramelosTipo = self::coleccionTipada($caramelosTipo, RecompensaTipo::class);
    }

    /**
     * Combina los caramelos de los hallazgos (familia/EV/tipo) con los de las
     * derrotas, agrupando por clave y sumando cantidades. Devuelve una instancia
     * nueva (inmutabilidad).
     *
     * @param  Collection<int, RecompensaFamilia>  $caramelosFamilia
     * @param  Collection<int, RecompensaEv>  $caramelosEv
     * @param  Collection<int, RecompensaTipo>  $caramelosTipo
     */
    public function sumarHallazgos(
        Collection $caramelosFamilia,
        Collection $caramelosEv,
        Collection $caramelosTipo,
    ): self {
        return new self(
            capturas: $this->capturas,
            caramelosFamilia: $this->combinarFamilia($caramelosFamilia),
            caramelosEv: $this->combinarEv($caramelosEv),
            caramelosTipo: $this->combinarTipo($caramelosTipo),
            expTotal: $this->expTotal,
            expPorMiembro: $this->expPorMiembro,
            expTipoPorMiembro: $this->expTipoPorMiembro,
        );
    }

    /**
     * @param  Collection<int, RecompensaFamilia>  $extra
     * @return Collection<int, RecompensaFamilia>
     */
    private function combinarFamilia(Collection $extra): Collection
    {
        return $this->caramelosFamilia
            ->concat($extra)
            ->groupBy('evolutionChainId')
            ->map(fn (Collection $grupo): RecompensaFamilia => new RecompensaFamilia(
                evolutionChainId: $grupo->first()->evolutionChainId,
                cantidad: $grupo->sum('cantidad'),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, RecompensaEv>  $extra
     * @return Collection<int, RecompensaEv>
     */
    private function combinarEv(Collection $extra): Collection
    {
        return $this->caramelosEv
            ->concat($extra)
            ->groupBy('stat')
            ->map(fn (Collection $grupo): RecompensaEv => new RecompensaEv(
                stat: $grupo->first()->stat,
                cantidad: $grupo->sum('cantidad'),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, RecompensaTipo>  $extra
     * @return Collection<int, RecompensaTipo>
     */
    private function combinarTipo(Collection $extra): Collection
    {
        return $this->caramelosTipo
            ->concat($extra)
            ->groupBy('tipo')
            ->map(fn (Collection $grupo): RecompensaTipo => new RecompensaTipo(
                tipo: $grupo->first()->tipo,
                cantidad: $grupo->sum('cantidad'),
            ))
            ->values();
    }

    /**
     * @template T of object
     *
     * @param  Collection<int, T>|array<int, T>  $items
     * @param  class-string<T>  $clase
     * @return Collection<int, T>
     */
    private static function coleccionTipada(Collection|array $items, string $clase): Collection
    {
        $coleccion = $items instanceof Collection ? $items : collect($items);

        foreach ($coleccion as $item) {
            if (! $item instanceof $clase) {
                throw new InvalidArgumentException(sprintf('Item inválido en %s: se esperaba %s.', $clase, $item::class));
            }
        }

        return $coleccion->values();
    }
}
