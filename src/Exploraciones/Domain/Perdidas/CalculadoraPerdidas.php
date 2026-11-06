<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Perdidas;

use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaItem;
use Src\Exploraciones\Domain\Recompensas\RecompensaItemCollection;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\CategoriaItem;

/**
 * Cálculo de pérdidas por derrota y resta de las mismas sobre las
 * recompensas finales de una expedición.
 */
final class CalculadoraPerdidas
{
    /** Calcula la pérdida de objetos: intdiv(cantidad, 2) de cada recompensa; sin derrota devuelve una colección vacía. */
    public function calcular(ResultadoRecompensas $recompensas, bool $huboDerrota): ColeccionObjetosPerdidos
    {
        if (! $huboDerrota) {
            return new ColeccionObjetosPerdidos();
        }
        return new ColeccionObjetosPerdidos(array_merge(
            $this->perdidasFamilia($recompensas),
            $this->perdidasEv($recompensas),
            $this->perdidasTipo($recompensas),
            $this->perdidasCapturas($recompensas),
            $this->perdidasItems($recompensas),
        ));
    }

    /** Resta las pérdidas de las recompensas finales devolviendo una nueva instancia inmutable. */
    public function restar(ResultadoRecompensas $recompensas, ColeccionObjetosPerdidos $perdidas): ResultadoRecompensas
    {
        $mapaPerdidas = $this->mapaDePerdidas($perdidas);
        return new ResultadoRecompensas(
            capturas: $this->reducirCapturas($recompensas->capturas, $mapaPerdidas),
            caramelosFamilia: $this->reducirFamilia($recompensas->caramelosFamilia, $mapaPerdidas),
            caramelosEv: $this->reducirEv($recompensas->caramelosEv, $mapaPerdidas),
            caramelosTipo: $this->reducirTipo($recompensas->caramelosTipo, $mapaPerdidas),
            expTotal: $recompensas->expTotal,
            expPorMiembro: $recompensas->expPorMiembro,
            expTipoPorMiembro: $recompensas->expTipoPorMiembro,
            expTipoCuenta: $recompensas->expTipoCuenta,
            items: new RecompensaItemCollection($this->reducirItems($recompensas->items->all(), $mapaPerdidas)),
        );
    }

    /** @return array<string, array<string, int>> */
    private function mapaDePerdidas(ColeccionObjetosPerdidos $perdidas): array
    {
        $mapa = [];
        foreach ($perdidas->all() as $perdida) {
            $mapa[$perdida->tipo][(string) $perdida->id] = $perdida->cantidad;
        }
        return $mapa;
    }

    /** @return list<ObjetoPerdido> */
    private function perdidasFamilia(ResultadoRecompensas $recompensas): array
    {
        return $this->perdidasDe($recompensas->caramelosFamilia, static fn (RecompensaFamilia $c): array => [$c->cantidad, $c->evolutionChainId, 'familia', null]);
    }

    /** @return list<ObjetoPerdido> */
    private function perdidasEv(ResultadoRecompensas $recompensas): array
    {
        return $this->perdidasDe($recompensas->caramelosEv, static fn (RecompensaEv $c): array => [$c->cantidad, $c->stat, 'ev', null]);
    }

    /** @return list<ObjetoPerdido> */
    private function perdidasTipo(ResultadoRecompensas $recompensas): array
    {
        return $this->perdidasDe($recompensas->caramelosTipo, static fn (RecompensaTipo $c): array => [$c->cantidad, $c->slug(), 'tipo', $c->tipo]);
    }

    /** @return list<ObjetoPerdido> */
    private function perdidasCapturas(ResultadoRecompensas $recompensas): array
    {
        return $this->perdidasDe($recompensas->capturas, static fn (RecompensaCaptura $c): array => [$c->cantidad, $c->pokemonId, 'captura', null]);
    }

    /** @return list<ObjetoPerdido> */
    private function perdidasItems(ResultadoRecompensas $recompensas): array
    {
        return $this->perdidasDe($recompensas->items->all(), fn (RecompensaItem $i): array => [$i->cantidad, $i->clave, $this->tipoDeItem($i->clave), CatalogoItems::definicion($i->clave)->nombre]);
    }

    /**
     * @template T
     * @param iterable<int, T> $elementos
     * @param callable(T): array{int, int|string, string, string|null} $datos
     * @return list<ObjetoPerdido>
     */
    private function perdidasDe(iterable $elementos, callable $datos): array
    {
        $perdidas = [];
        foreach ($elementos as $elemento) {
            [$cantidad, $id, $tipo, $label] = $datos($elemento);
            $perdida = intdiv($cantidad, 2);
            if ($perdida > 0) {
                $perdidas[] = new ObjetoPerdido($tipo, $id, $label, $perdida);
            }
        }
        return $perdidas;
    }

    /**
     * @param iterable<int, RecompensaCaptura> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @return list<RecompensaCaptura>
     */
    private function reducirCapturas(iterable $elementos, array $mapaPerdidas): array
    {
        return $this->reducirElementos($elementos, $mapaPerdidas, static fn (RecompensaCaptura $c): array => [$c->cantidad, $c->pokemonId, 'captura'], static fn (RecompensaCaptura $c, int $n): RecompensaCaptura => new RecompensaCaptura($c->pokemonId, $n, $c->esShiny));
    }

    /**
     * @param iterable<int, RecompensaFamilia> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @return list<RecompensaFamilia>
     */
    private function reducirFamilia(iterable $elementos, array $mapaPerdidas): array
    {
        return $this->reducirElementos($elementos, $mapaPerdidas, static fn (RecompensaFamilia $c): array => [$c->cantidad, $c->evolutionChainId, 'familia'], static fn (RecompensaFamilia $c, int $n): RecompensaFamilia => new RecompensaFamilia($c->evolutionChainId, $n));
    }

    /**
     * @param iterable<int, RecompensaEv> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @return list<RecompensaEv>
     */
    private function reducirEv(iterable $elementos, array $mapaPerdidas): array
    {
        return $this->reducirElementos($elementos, $mapaPerdidas, static fn (RecompensaEv $c): array => [$c->cantidad, $c->stat, 'ev'], static fn (RecompensaEv $c, int $n): RecompensaEv => new RecompensaEv($c->stat, $n));
    }

    /**
     * @param iterable<int, RecompensaTipo> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @return list<RecompensaTipo>
     */
    private function reducirTipo(iterable $elementos, array $mapaPerdidas): array
    {
        return $this->reducirElementos($elementos, $mapaPerdidas, static fn (RecompensaTipo $c): array => [$c->cantidad, $c->slug(), 'tipo'], static fn (RecompensaTipo $c, int $n): RecompensaTipo => new RecompensaTipo($c->tipo, $n));
    }

    /**
     * @param iterable<int, RecompensaItem> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @return list<RecompensaItem>
     */
    private function reducirItems(iterable $elementos, array $mapaPerdidas): array
    {
        return $this->reducirElementos($elementos, $mapaPerdidas, fn (RecompensaItem $i): array => [$i->cantidad, $i->clave, $this->tipoDeItem($i->clave)], static fn (RecompensaItem $i, int $n): RecompensaItem => new RecompensaItem($i->clave, $n));
    }

    /**
     * @template T
     * @param iterable<int, T> $elementos
     * @param array<string, array<string, int>> $mapaPerdidas
     * @param callable(T): array{int, int|string, string} $datos
     * @param callable(T, int): T $crear
     * @return list<T>
     */
    private function reducirElementos(iterable $elementos, array $mapaPerdidas, callable $datos, callable $crear): array
    {
        $resultado = [];
        foreach ($elementos as $elemento) {
            [$cantidad, $clave, $tipo] = $datos($elemento);
            $nuevaCantidad = $this->reducir($cantidad, $clave, $tipo, $mapaPerdidas);
            if ($nuevaCantidad > 0) {
                $resultado[] = $crear($elemento, $nuevaCantidad);
            }
        }
        return $resultado;
    }

    /** @param array<string, array<string, int>> $mapaPerdidas */
    private function reducir(int $cantidad, int|string $clave, string $tipo, array $mapaPerdidas): int
    {
        $perdida = $mapaPerdidas[$tipo][(string) $clave] ?? 0;
        return max(0, $cantidad - $perdida);
    }

    /** Categoría de pérdida de un item según el prefijo de su clave (`baya:` / `bonguri:`). */
    private function tipoDeItem(string $clave): string
    {
        return str_starts_with($clave, CategoriaItem::BAYA->prefijo())
            ? CategoriaItem::BAYA->value
            : CategoriaItem::BONGURI->value;
    }
}
