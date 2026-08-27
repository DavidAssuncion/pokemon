<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resultado agregado del cálculo de recompensas de una exploración.
 * Inmutable; valida el tipo de cada item de sus colecciones.
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
     */
    public function __construct(
        Collection|array $capturas,
        Collection|array $caramelosFamilia,
        Collection|array $caramelosEv,
        Collection|array $caramelosTipo,
        public readonly int $expTotal,
    ) {
        $this->capturas = self::coleccionTipada($capturas, RecompensaCaptura::class);
        $this->caramelosFamilia = self::coleccionTipada($caramelosFamilia, RecompensaFamilia::class);
        $this->caramelosEv = self::coleccionTipada($caramelosEv, RecompensaEv::class);
        $this->caramelosTipo = self::coleccionTipada($caramelosTipo, RecompensaTipo::class);
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
