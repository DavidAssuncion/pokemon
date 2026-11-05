<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Closure;
use Src\Exploraciones\Domain\Recompensas\RecompensaItem;
use Src\Exploraciones\Domain\Recompensas\RecompensaItemCollection;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\ValueObjects\MapaFactores;

/**
 * Calcula el botín de bayas y bonguris de una expedición (dominio puro).
 *
 * La selección de bayas es ponderada por rareza y sin reemplazo dentro de la
 * misma expedición. El número de tiradas y la probabilidad del bonguri del
 * hábitat dependen del modo. No hay tope duro adicional: el balance se regula
 * con los pesos y probabilidades centralizados en esta clase.
 *
 * F3: `ContextoBotin::$factoresPlantacion` multiplica el peso de cada baya
 * salvaje (`baya:{slug}` => factor) y `ContextoBotin::$delHabitat` concede el
 * bonus de Pokémon nativo: +25 % de tiradas de baya y +10 pp de probabilidad
 * de bonguri con tope del 85 %. Los factores de plantación no afectan a los
 * bonguris. `nivelHabitat` y `huboDerrota` se aceptan pero no alteran todavía
 * el resultado.
 *
 * `ContextoBotin::$factorTiempo` (acotado a [0, 1]) evita el farmeo
 * instantáneo: cada tirada de baya solo se intenta con esa probabilidad y la
 * probabilidad del bonguri se multiplica por él. Con 1.0 el botín es el
 * nominal y con 0.0 no se entrega nada. `ContextoBotin::$multiplicadorBotin`
 * escala la probabilidad de bonguri según el modo.
 */
final class CalculadorBotinExploracion
{
    private const PESO_BAYA_BASE = 10;
    private const PESO_BAYA_REDUCTORA = 6;
    private const PESO_BAYA_RARA = 3;
    private const PESO_BAYA_TIPO = 2;

    private const PROBABILIDAD_BONGURI_MIXTO = 30;
    private const PROBABILIDAD_BONGURI_EXPERIENCIA = 25;
    private const PROBABILIDAD_BONGURI_RECLUTAMIENTO = 25;
    private const PROBABILIDAD_BONGURI_BOTIN = 60;

    /** @var list<ClaveBaya> */
    private const BAYAS_BASE = [
        ClaveBaya::ORAN,
        ClaveBaya::SITRUS,
        ClaveBaya::CHERI,
        ClaveBaya::PECHA,
        ClaveBaya::RAWST,
        ClaveBaya::ASPEAR,
    ];

    /** @var list<ClaveBaya> */
    private const BAYAS_REDUCTORAS = [
        ClaveBaya::KELPSY,
        ClaveBaya::QUALOT,
        ClaveBaya::HONDEW,
        ClaveBaya::GREPA,
        ClaveBaya::TAMATO,
    ];

    /** @var list<ClaveBaya> */
    private const BAYAS_RARAS = [
        ClaveBaya::LIECHI,
        ClaveBaya::GANLON,
        ClaveBaya::SALAC,
        ClaveBaya::PETAYA,
        ClaveBaya::APICOT,
        ClaveBaya::LANSAT,
        ClaveBaya::MICLE,
    ];

    /** @var Closure(): float */
    private readonly Closure $aleatorio;

    /**
     * @param  (callable(): float)|null  $aleatorio  Fuente de aleatoriedad en
     *                                               [0, 1); por defecto usa
     *                                               `mt_rand()`.
     */
    public function __construct(?callable $aleatorio = null)
    {
        $this->aleatorio = $aleatorio === null
            ? static fn (): float => mt_rand() / mt_getrandmax()
            : Closure::fromCallable($aleatorio);
    }

    public function calcular(ContextoBotin $contexto): RecompensaItemCollection
    {
        $items = [];
        $pesosBayas = $this->pesosBayas($contexto->factoresPlantacion);
        $tiradas = $this->tiradasDeBayas($contexto->modo, $contexto->delHabitat);
        $factorTiempo = max(0.0, min(1.0, $contexto->factorTiempo));

        for ($i = 0; $i < $tiradas && $pesosBayas !== []; $i++) {
            // Con poco tiempo transcurrido cada tirada puede no llegar a
            // realizarse, de modo que una recogida forzada no rinde el botín
            // completo de una expedición planificada.
            if (($this->aleatorio)() >= $factorTiempo) {
                continue;
            }

            $baya = $this->elegirBaya($pesosBayas);

            if ($baya === null) {
                break;
            }

            // Sin reemplazo: una baya seleccionada no vuelve a salir en esta
            // misma expedición.
            unset($pesosBayas[$baya->value]);

            $items[] = new RecompensaItem($baya->clave(), 1);
        }

        if ($contexto->bonguriHabitat !== null
            && $this->caeBonguri($contexto->modo, $contexto->multiplicadorBotin, $factorTiempo, $contexto->delHabitat)) {
            $items[] = new RecompensaItem($contexto->bonguriHabitat->clave(), 1);
        }

        return new RecompensaItemCollection($items);
    }

    /**
     * Número de tiradas de baya según el modo:
     * BOTIN 1–3, EXPERIENCIA 0–1, RECLUTAMIENTO 0–1 y MIXTO 0–2.
     *
     * Con el bonus de hábitat se aplica +25 % redondeado hacia arriba, con
     * tope de 4 tiradas.
     */
    private function tiradasDeBayas(ModoExploracion $modo, bool $delHabitat): int
    {
        $base = match ($modo) {
            ModoExploracion::BOTIN => 1 + $this->enteroHasta(2),
            ModoExploracion::EXPERIENCIA => $this->enteroHasta(1),
            ModoExploracion::RECLUTAMIENTO => $this->enteroHasta(1),
            ModoExploracion::MIXTO => $this->enteroHasta(2),
        };

        return $delHabitat ? min(4, (int) ceil($base * 1.25)) : $base;
    }

    /**
     * Entero uniforme en [0, $maximoInclusivo].
     */
    private function enteroHasta(int $maximoInclusivo): int
    {
        return min(
            $maximoInclusivo,
            (int) floor(($this->aleatorio)() * ($maximoInclusivo + 1)),
        );
    }

    /**
     * Pesos base por rareza, multiplicados por el factor de plantación de cada
     * baya. Las bayas de tipo son el resto del catálogo.
     *
     * @return array<string, float> clave `baya:{slug}` => peso
     */
    private function pesosBayas(MapaFactores $factores): array
    {
        $pesos = [];

        foreach (self::BAYAS_BASE as $baya) {
            $pesos[$baya->value] = self::PESO_BAYA_BASE;
        }

        foreach (self::BAYAS_REDUCTORAS as $baya) {
            $pesos[$baya->value] = self::PESO_BAYA_REDUCTORA;
        }

        foreach (self::BAYAS_RARAS as $baya) {
            $pesos[$baya->value] = self::PESO_BAYA_RARA;
        }

        foreach (ClaveBaya::cases() as $baya) {
            $pesos[$baya->value] ??= self::PESO_BAYA_TIPO;
        }

        foreach (ClaveBaya::cases() as $baya) {
            $pesos[$baya->value] *= $factores->obtener($baya->clave());
        }

        return $pesos;
    }

    /**
     * Selección ponderada sobre las bayas todavía disponibles.
     *
     * @param  array<string, float>  $pesos  slug de baya => peso
     */
    private function elegirBaya(array $pesos): ?ClaveBaya
    {
        $total = array_sum($pesos);

        if ($total <= 0) {
            return null;
        }

        $objetivo = ($this->aleatorio)() * $total;
        $acumulado = 0;
        $ultima = null;

        foreach ($pesos as $slug => $peso) {
            $ultima = $slug;
            $acumulado += $peso;

            if ($objetivo < $acumulado) {
                return ClaveBaya::from($slug);
            }
        }

        return $ultima === null ? null : ClaveBaya::from($ultima);
    }

    /**
     * Una única tirada por expedición; si acierta se entrega una unidad del
     * bonguri del hábitat. La probabilidad base del modo recibe +10 pp si el
     * explorador es nativo del hábitat, y se escala por `multiplicadorBotin`
     * (balance del modo) y `factorTiempo` (tiempo efectivo de la expedición),
     * con tope del 85 %. Los factores de plantación no intervienen.
     */
    private function caeBonguri(
        ModoExploracion $modo,
        float $multiplicadorBotin,
        float $factorTiempo,
        bool $delHabitat,
    ): bool {
        $probabilidadBase = match ($modo) {
            ModoExploracion::MIXTO => self::PROBABILIDAD_BONGURI_MIXTO,
            ModoExploracion::EXPERIENCIA => self::PROBABILIDAD_BONGURI_EXPERIENCIA,
            ModoExploracion::RECLUTAMIENTO => self::PROBABILIDAD_BONGURI_RECLUTAMIENTO,
            ModoExploracion::BOTIN => self::PROBABILIDAD_BONGURI_BOTIN,
        };

        $probabilidad = min(
            85.0,
            ($probabilidadBase + ($delHabitat ? 10.0 : 0.0)) * $multiplicadorBotin * $factorTiempo,
        );

        return ($this->aleatorio)() * 100 < $probabilidad;
    }
}
