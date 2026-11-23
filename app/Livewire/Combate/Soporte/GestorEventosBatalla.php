<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use App\Support\BattleSessionService;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Orquesta la grabación de eventos de animación sobre las propiedades
 * `eventos` y `eventoTick` del componente. El flag `grabando` se recibe por
 * referencia: solo `iniciar()` y `cerrar()` lo escriben.
 */
final class GestorEventosBatalla
{
    public function __construct(
        private readonly GrabadorEventosBatalla $grabador,
        private readonly BattleSessionService $session,
    ) {
    }

    public function iniciar(Combate $componente, bool &$grabando): void
    {
        $componente->eventos = [];
        $componente->eventoTick++;
        $grabando = true;
    }

    public function cerrar(Combate $componente, bool &$grabando): void
    {
        if ($grabando) {
            $battle = $this->session->cargar($componente->battleId);

            if ($battle !== null) {
                foreach ($battle->consumirDetonaciones() as $detonacion) {
                    $componente->eventos[] = [
                        'id' => $componente->eventoTick.'.'.(count($componente->eventos) + 1),
                        'tipo' => 'detonacion',
                        'cambios' => [],
                        'muertos' => [],
                        'portadorId' => $detonacion['portadorId'],
                        'objetivos' => $detonacion['objetivos'],
                        'texto' => '¡Detonación!',
                    ];
                }

                foreach ($this->agruparResiduos($battle->consumirResiduos()) as $grupo) {
                    $muertos = [];
                    foreach ($grupo['objetivos'] as $objetivo) {
                        if ($objetivo['hpDespues'] <= 0.0) {
                            $muertos[] = $objetivo['objetivoId'];
                        }
                    }

                    $componente->eventos[] = [
                        'id' => $componente->eventoTick.'.'.(count($componente->eventos) + 1),
                        'tipo' => 'residuo',
                        'cambios' => [],
                        'muertos' => $muertos,
                        'subtipo' => $grupo['subtipo'],
                        'texto' => $grupo['texto'],
                        'movimientoSlug' => $grupo['movimientoSlug'],
                        'origenId' => $grupo['origenId'],
                        'objetivos' => $grupo['objetivos'],
                    ];
                }

                $componente->eventos[] = $this->grabador->eventoFin(
                    $this->capturar($battle),
                    $componente->eventoTick,
                    count($componente->eventos) + 1,
                );
            }
        }

        $grabando = false;
    }

    public function capturar(AgregadoBatalla $battle): SnapshotBatalla
    {
        return $this->grabador->capturar($battle);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function registrar(
        Combate $componente,
        bool $grabando,
        string $tipo,
        SnapshotBatalla $antes,
        SnapshotBatalla $despues,
        array $extra = [],
    ): void {
        if (! $grabando) {
            return;
        }

        $evento = $this->grabador->registrar(
            $tipo,
            $antes,
            $despues,
            $componente->eventoTick,
            count($componente->eventos) + 1,
            $extra,
        );
        if ($evento !== null) {
            $componente->eventos[] = $evento;
        }
    }

    /**
     * Agrupa los cambios residuales por efecto para que todos los objetivos de
     * un mismo efecto (granizo, Púas, quemadura…) se animen a la vez.
     *
     * @param  list<array{objetivoId: string, hpAntes: float, hpDespues: float, defAntes: float, defDespues: float, spDefAntes: float, spDefDespues: float, tipo: string, label: string, movimientoSlug: string, origenId: string}>  $residuos
     * @return list<array{subtipo: string, texto: string, movimientoSlug: string, origenId: string, objetivos: list<array{objetivoId: string, hpAntes: float, hpDespues: float, defAntes: float, defDespues: float, spDefAntes: float, spDefDespues: float}>}>
     */
    private function agruparResiduos(array $residuos): array
    {
        $prioridad = [
            'trampa' => 10,
            'habilidad' => 20,
            'condicion' => 30,
            'estado' => 40,
            'clima' => 50,
        ];
        $grupos = [];

        foreach ($residuos as $residuo) {
            $clave = implode('|', [
                $residuo['tipo'],
                $residuo['label'],
                $residuo['movimientoSlug'],
                $residuo['origenId'],
            ]);

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'orden' => $prioridad[$residuo['tipo']] ?? 99,
                    'secuencia' => count($grupos),
                    'subtipo' => $residuo['tipo'],
                    'texto' => $residuo['label'],
                    'movimientoSlug' => $residuo['movimientoSlug'],
                    'origenId' => $residuo['origenId'],
                    'objetivos' => [],
                ];
            }

            $grupos[$clave]['objetivos'][] = [
                'objetivoId' => $residuo['objetivoId'],
                'hpAntes' => $residuo['hpAntes'],
                'hpDespues' => $residuo['hpDespues'],
                'defAntes' => $residuo['defAntes'],
                'defDespues' => $residuo['defDespues'],
                'spDefAntes' => $residuo['spDefAntes'],
                'spDefDespues' => $residuo['spDefDespues'],
            ];
        }

        $grupos = array_values($grupos);
        usort(
            $grupos,
            static fn (array $a, array $b): int => [$a['orden'], $a['secuencia']] <=> [$b['orden'], $b['secuencia']],
        );

        return $grupos;
    }

    public function batallaTerminada(
        Combate $componente,
        bool $grabando,
        string $texto,
        bool $victoria,
    ): void {
        if (! $grabando) {
            return;
        }

        $componente->eventos[] = $this->grabador->eventoBatallaTerminada(
            $texto,
            $victoria,
            $componente->eventoTick,
            count($componente->eventos) + 1,
        );
    }
}
