<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use DateTimeImmutable;
use Exception;
use Traversable;

/**
 * Diario de una expedición: fusiona la bitácora con los eventos raíz de cierre
 * (`retirada`, `derrota`, `cancelada`), los ordena cronológicamente, los
 * transforma con EventoExploracionVista y los agrupa por fecha local.
 *
 * Es presentación pura: no consulta la base de datos ni inventa eventos.
 */
final class DiarioExploracion
{
    /** @var list<string> */
    private const EVENTOS_RAIZ = ['retirada', 'derrota', 'cancelada'];

    /**
     * @param  array<string, mixed>  $eventos  eventos completos de la exploración
     * @param  array<array-key, string>  $nombres
     * @param  bool  $completarStats  completa stat_nombre/stat_slug del catálogo (bitácora de la vista activa)
     * @return array{
     *     total: int,
     *     eventos: list<array<string, mixed>>,
     *     grupos: list<array{fecha: string, etiqueta: string, eventos: list<array<string, mixed>>}>,
     *     hitos: list<array<string, mixed>>
     * }
     */
    public static function toView(array $eventos, array $nombres = [], ?string $exploradorNombre = null, bool $completarStats = false): array
    {
        $bitacora = self::lista($eventos['bitacora'] ?? []);
        $eventosLista = $bitacora;

        $tiposEnBitacora = [];
        foreach ($bitacora as $evento) {
            $tiposEnBitacora[(string) ($evento['tipo'] ?? '')] = true;
        }

        foreach (self::EVENTOS_RAIZ as $tipo) {
            if (isset($tiposEnBitacora[$tipo])) {
                continue;
            }

            $raiz = $eventos[$tipo] ?? null;
            if (! is_array($raiz)) {
                continue;
            }

            $raiz['tipo'] = $tipo;
            if ($tipo === 'cancelada' && ! isset($raiz['motivo'])) {
                $raiz['motivo'] = 'manual';
            }

            $eventosLista[] = $raiz;
        }

        $nombre = is_string($exploradorNombre) && trim($exploradorNombre) !== ''
            ? $exploradorNombre
            : null;

        $preparados = [];
        foreach ($eventosLista as $orden => $evento) {
            if ($nombre !== null) {
                $evento['explorador_nombre'] = $nombre;
            }

            $preparados[] = [
                'evento' => $evento,
                'ts' => self::timestamp($evento['timestamp'] ?? null),
                'orden' => $orden,
            ];
        }

        usort($preparados, static function (array $a, array $b): int {
            /** @var DateTimeImmutable|null $tsA */
            $tsA = $a['ts'];
            /** @var DateTimeImmutable|null $tsB */
            $tsB = $b['ts'];

            if ($tsA === null && $tsB === null) {
                return $a['orden'] <=> $b['orden'];
            }

            if ($tsA === null) {
                return 1;
            }

            if ($tsB === null) {
                return -1;
            }

            $porTimestamp = $tsA->getTimestamp() <=> $tsB->getTimestamp();

            return $porTimestamp !== 0 ? $porTimestamp : ($a['orden'] <=> $b['orden']);
        });

        $hoy = new DateTimeImmutable('now');
        $ayer = $hoy->modify('-1 day');

        $transformados = [];
        $grupos = [];

        foreach ($preparados as $preparado) {
            $vista = EventoExploracionVista::toView($preparado['evento'], $nombres, $completarStats);

            if (($vista['oculto'] ?? false) === true) {
                continue;
            }

            $transformados[] = $vista;

            /** @var DateTimeImmutable|null $ts */
            $ts = $preparado['ts'];
            $fecha = $ts?->format('Y-m-d') ?? '';

            if (! isset($grupos[$fecha])) {
                $grupos[$fecha] = [
                    'fecha' => $fecha,
                    'etiqueta' => self::etiqueta($fecha, $hoy, $ayer),
                    'eventos' => [],
                ];
            }

            $grupos[$fecha]['eventos'][] = $vista;
        }

        $resultado = $eventos['resultado'] ?? null;

        return [
            'total' => count($transformados),
            'eventos' => $transformados,
            'grupos' => array_values($grupos),
            'hitos' => HitoExploracion::desde(is_array($resultado) ? $resultado : []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lista(mixed $valor): array
    {
        if (is_array($valor)) {
            $lista = [];
            foreach ($valor as $item) {
                if (is_array($item)) {
                    $lista[] = $item;
                }
            }

            return $lista;
        }

        if ($valor instanceof Traversable) {
            return self::lista(iterator_to_array($valor));
        }

        return [];
    }

    private static function timestamp(mixed $valor): ?DateTimeImmutable
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($valor);
        } catch (Exception) {
            return null;
        }
    }

    private static function etiqueta(string $fecha, DateTimeImmutable $hoy, DateTimeImmutable $ayer): string
    {
        if ($fecha === '') {
            return 'Sin fecha';
        }

        if ($fecha === $hoy->format('Y-m-d')) {
            return 'Hoy';
        }

        if ($fecha === $ayer->format('Y-m-d')) {
            return 'Ayer';
        }

        $fechaObj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $fechaObj instanceof DateTimeImmutable ? $fechaObj->format('d/m/Y') : $fecha;
    }
}
