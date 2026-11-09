<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlayerTypePath;
use App\Models\User;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Construye el modelo de presentación de la EXP ganada tras un combate.
 *
 * Solo compara dos instantáneas de la base de datos: no otorga experiencia,
 * no recalcula fórmulas y no escribe nada. La diferencia real entre las dos
 * lecturas es la fuente de verdad.
 */
final class ProgresoRecompensas
{
    /**
     * Máximo de segmentos animados por barra.
     */
    public const MAX_SEGMENTOS = 4;

    /**
     * Instantánea inmutable del estado de EXP del usuario.
     *
     * Dos SELECT de solo lectura (`users` y `player_type_paths`).
     */
    public function capturar(int $userId): SnapshotProgreso
    {
        $expCuenta = (int) User::query()->whereKey($userId)->value('experiencia');

        $filas = PlayerTypePath::query()
            ->withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)
            ->where('user_id', $userId)
            ->pluck('exp', 'type_id')
            ->all();

        $porTipo = [];

        foreach ($filas as $tipoId => $exp) {
            $porTipo[(int) $tipoId] = (int) $exp;
        }

        return new SnapshotProgreso($expCuenta, $porTipo);
    }

    /**
     * Compara dos instantáneas y construye el modelo de presentación.
     *
     * Devuelve `null` cuando no hay ninguna ganancia de cuenta ni de Senda.
     */
    public function comparar(
        SnapshotProgreso $antes,
        SnapshotProgreso $despues,
    ): ?ProgresoRecompensasVista {
        $cuenta = $this->cuenta($antes->experienciaCuenta, $despues->experienciaCuenta);

        $tipos = array_unique(array_merge(
            $antes->tiposConExperiencia(),
            $despues->tiposConExperiencia(),
        ));

        $sendas = [];

        foreach ($tipos as $tipoId) {
            $senda = $this->senda(
                $tipoId,
                $antes->experienciaDe($tipoId),
                $despues->experienciaDe($tipoId),
            );

            if ($senda !== null) {
                $sendas[] = $senda;
            }
        }

        if ($cuenta === null && $sendas === []) {
            return null;
        }

        $sendas = $this->ordenarSendas($sendas);

        return new ProgresoRecompensasVista(
            $cuenta,
            $sendas,
            $this->resumenAccesible($cuenta, $sendas),
        );
    }

    private function cuenta(int $expAntes, int $expDespues): ?ProgresoCuentaVista
    {
        if ($expDespues <= $expAntes) {
            return null;
        }

        $nivelAntes = NivelHelper::nivelDesdeExperiencia($expAntes);
        $nivelDespues = NivelHelper::nivelDesdeExperiencia($expDespues);
        $porcentajeAntes = NivelHelper::progresoHaciaSiguienteNivel($expAntes);
        $porcentajeDespues = NivelHelper::progresoHaciaSiguienteNivel($expDespues);

        return new ProgresoCuentaVista(
            expAntes: $expAntes,
            expDespues: $expDespues,
            expGanada: $expDespues - $expAntes,
            nivelAntes: $nivelAntes,
            nivelDespues: $nivelDespues,
            nivelesGanados: max(0, $nivelDespues - $nivelAntes),
            porcentajeAntes: $porcentajeAntes,
            porcentajeDespues: $porcentajeDespues,
            expInicioNivel: NivelHelper::experienciaParaNivel($nivelDespues),
            expSiguiente: NivelHelper::experienciaParaNivel($nivelDespues + 1),
            segmentos: $this->segmentos(
                $nivelAntes,
                $nivelDespues,
                $porcentajeAntes,
                $porcentajeDespues,
                false,
            ),
            nivelesOmitidos: $this->nivelesOmitidos($nivelAntes, $nivelDespues, false),
            tope: false,
        );
    }

    private function senda(int $tipoId, int $expAntes, int $expDespues): ?ProgresoSendaVista
    {
        if ($expDespues <= $expAntes) {
            return null;
        }

        $tipo = TipoPokemon::tryFrom($tipoId);

        if ($tipo === null) {
            return null;
        }

        $resumenAntes = SendaProgreso::resumen($expAntes);
        $resumenDespues = SendaProgreso::resumen($expDespues);
        $nivelAntes = $resumenAntes['nivel'];
        $nivelDespues = $resumenDespues['nivel'];
        $tope = $nivelDespues >= SendaProgreso::NIVEL_MAXIMO;
        $porcentajeDespues = $tope ? 100 : $resumenDespues['porcentaje'];

        return new ProgresoSendaVista(
            tipoId: $tipoId,
            label: $tipo->label(),
            slug: $tipo->slug(),
            color: $tipo->color(),
            icono: '/images/type/'.$tipo->slug().'.webp',
            expAntes: $expAntes,
            expDespues: $expDespues,
            expGanada: $expDespues - $expAntes,
            expMostradaAntes: $resumenAntes['exp'],
            expMostradaDespues: $resumenDespues['exp'],
            expMaxima: $resumenDespues['exp_maxima'],
            nivelAntes: $nivelAntes,
            nivelDespues: $nivelDespues,
            nivelMaximo: $resumenDespues['nivel_maximo'],
            nivelesGanados: max(0, $nivelDespues - $nivelAntes),
            porcentajeAntes: $resumenAntes['porcentaje'],
            porcentajeDespues: $porcentajeDespues,
            expInicioNivel: SendaProgreso::umbral($nivelDespues),
            expSiguiente: $resumenDespues['exp_siguiente'],
            tope: $tope,
            segmentos: $this->segmentos(
                $nivelAntes,
                $nivelDespues,
                $resumenAntes['porcentaje'],
                $porcentajeDespues,
                $tope,
            ),
            nivelesOmitidos: $this->nivelesOmitidos($nivelAntes, $nivelDespues, $tope),
        );
    }

    /**
     * @param  list<ProgresoSendaVista>  $sendas
     * @return list<ProgresoSendaVista>
     */
    private function ordenarSendas(array $sendas): array
    {
        usort($sendas, static function (ProgresoSendaVista $a, ProgresoSendaVista $b): int {
            if ($a->expGanada !== $b->expGanada) {
                return $b->expGanada <=> $a->expGanada;
            }

            if ($a->expDespues !== $b->expDespues) {
                return $b->expDespues <=> $a->expDespues;
            }

            return $a->tipoId <=> $b->tipoId;
        });

        return $sendas;
    }

    /**
     * Segmentos animables de una barra.
     *
     * Primero + intermedios repartidos uniformemente + último, con un máximo
     * de {@see self::MAX_SEGMENTOS}. Una Senda en el tope solo llena el tramo
     * actual: no simula un sexto nivel.
     *
     * @return list<ProgresoSegmentoVista>
     */
    private function segmentos(
        int $nivelAntes,
        int $nivelDespues,
        int $porcentajeAntes,
        int $porcentajeDespues,
        bool $tope,
    ): array {
        if ($tope) {
            if ($nivelAntes >= SendaProgreso::NIVEL_MAXIMO) {
                return [];
            }

            return [new ProgresoSegmentoVista($nivelAntes, $porcentajeAntes, 100)];
        }

        $nivelesGanados = max(0, $nivelDespues - $nivelAntes);

        if ($nivelesGanados === 0) {
            return [new ProgresoSegmentoVista($nivelAntes, $porcentajeAntes, $porcentajeDespues)];
        }

        $segmentos = [new ProgresoSegmentoVista($nivelAntes, $porcentajeAntes, 100)];

        if ($nivelesGanados + 1 <= self::MAX_SEGMENTOS) {
            for ($nivel = $nivelAntes + 1; $nivel < $nivelDespues; $nivel++) {
                $segmentos[] = new ProgresoSegmentoVista($nivel, 0, 100);
            }
        } else {
            $intermedios = self::MAX_SEGMENTOS - 2;
            $rango = $nivelDespues - $nivelAntes - 1;

            for ($i = 1; $i <= $intermedios; $i++) {
                $nivel = $nivelAntes + (int) round($rango * $i / ($intermedios + 1));
                $segmentos[] = new ProgresoSegmentoVista($nivel, 0, 100);
            }
        }

        $segmentos[] = new ProgresoSegmentoVista($nivelDespues, 0, $porcentajeDespues);

        return $segmentos;
    }

    private function nivelesOmitidos(int $nivelAntes, int $nivelDespues, bool $tope): int
    {
        if ($tope) {
            return 0;
        }

        $nivelesGanados = max(0, $nivelDespues - $nivelAntes);

        if ($nivelesGanados + 1 <= self::MAX_SEGMENTOS) {
            return 0;
        }

        return $nivelesGanados - (self::MAX_SEGMENTOS - 1);
    }

    /**
     * Texto final para lectores de pantalla. Describe la cuenta y cada Senda
     * por separado: nunca suma acumuladores distintos.
     *
     * @param  list<ProgresoSendaVista>  $sendas
     */
    private function resumenAccesible(?ProgresoCuentaVista $cuenta, array $sendas): string
    {
        $partes = [];

        if ($cuenta !== null) {
            $partes[] = $this->resumenCuenta($cuenta);
        }

        foreach ($sendas as $senda) {
            $partes[] = $this->resumenSenda($senda);
        }

        return implode(' ', $partes);
    }

    private function resumenCuenta(ProgresoCuentaVista $cuenta): string
    {
        $exp = $this->formatear($cuenta->expGanada);

        if ($cuenta->nivelesGanados > 0) {
            return sprintf(
                'Cuenta: nivel %d a %d, más %s de experiencia.',
                $cuenta->nivelAntes,
                $cuenta->nivelDespues,
                $exp,
            );
        }

        return sprintf('Cuenta: nivel %d, más %s de experiencia.', $cuenta->nivelDespues, $exp);
    }

    private function resumenSenda(ProgresoSendaVista $senda): string
    {
        $exp = $this->formatear($senda->expGanada);

        if ($senda->tope) {
            return sprintf(
                'Senda de %s: nivel %d, tope alcanzado, más %s de experiencia.',
                $senda->label,
                $senda->nivelDespues,
                $exp,
            );
        }

        if ($senda->nivelesGanados > 0) {
            return sprintf(
                'Senda de %s: nivel %d a %d, más %s de experiencia.',
                $senda->label,
                $senda->nivelAntes,
                $senda->nivelDespues,
                $exp,
            );
        }

        return sprintf(
            'Senda de %s: nivel %d, más %s de experiencia.',
            $senda->label,
            $senda->nivelDespues,
            $exp,
        );
    }

    private function formatear(int $numero): string
    {
        return number_format($numero, 0, ',', '.');
    }
}
