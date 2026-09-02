<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;

/**
 * Registra información observada del combate para que la IA tome decisiones informadas.
 * Almacena: acciones enemigas observadas, daño recibido, KO realizados y turnos desde eventos.
 */
final class MemoriaCombateIA
{
    /** @var array<int, array{turno: int, accion: AccionBatalla, dano: float}> */
    private array $accionesEnemigasObservadas = [];

    /** @var array<int, array{turno: int, actorId: string, objetivoId: string, dano: float}> */
    private array $danioRecibido = [];

    /** @var array<int, array{turno: int, actorId: string, objetivoId: string}> */
    private array $koRealizados = [];

    private int $ultimoTurnoRegistrado = 0;

    public function __construct(
        private readonly PesosAmenaza $pesos,
    ) {
    }

    public function registrarAccionEnemiga(int $turno, AccionBatalla $accion, float $dano): void
    {
        $this->accionesEnemigasObservadas[] = [
            'turno' => $turno,
            'accion' => $accion,
            'dano' => $dano,
        ];
        $this->ultimoTurnoRegistrado = max($this->ultimoTurnoRegistrado, $turno);
    }

    public function registrarDanioRecibido(int $turno, string $actorId, string $objetivoId, float $dano): void
    {
        $this->danioRecibido[] = [
            'turno' => $turno,
            'actorId' => $actorId,
            'objetivoId' => $objetivoId,
            'dano' => $dano,
        ];
        $this->ultimoTurnoRegistrado = max($this->ultimoTurnoRegistrado, $turno);
    }

    public function registrarKO(int $turno, string $actorId, string $objetivoId): void
    {
        $this->koRealizados[] = [
            'turno' => $turno,
            'actorId' => $actorId,
            'objetivoId' => $objetivoId,
        ];
        $this->ultimoTurnoRegistrado = max($this->ultimoTurnoRegistrado, $turno);
    }

    public function turnosDesdeUltimaAccionEnemigaContra(string $actorId): int
    {
        $ultimo = -1;
        foreach ($this->accionesEnemigasObservadas as $registro) {
            if ($registro['accion']->defender->id() === $actorId) {
                $ultimo = $registro['turno'];
            }
        }

        return $ultimo >= 0 ? $this->ultimoTurnoRegistrado - $ultimo : PHP_INT_MAX;
    }

    public function danoTotalRecibidoPor(string $actorId): float
    {
        $total = 0.0;
        foreach ($this->danioRecibido as $registro) {
            if ($registro['objetivoId'] === $actorId) {
                $total += $registro['dano'];
            }
        }

        return $total;
    }

    public function koRealizadosPor(string $actorId): int
    {
        $count = 0;
        foreach ($this->koRealizados as $registro) {
            if ($registro['actorId'] === $actorId) {
                $count++;
            }
        }

        return $count;
    }

    public function enemigoMasActivoContra(string $equipoActorId): ?string
    {
        $conteo = [];
        foreach ($this->accionesEnemigasObservadas as $registro) {
            $actorAccion = $registro['accion']->attacker->id();
            $conteo[$actorAccion] = ($conteo[$actorAccion] ?? 0) + 1;
        }

        if ($conteo === []) {
            return null;
        }

        arsort($conteo);

        return array_key_first($conteo);
    }

    public function estaVacio(): bool
    {
        return $this->accionesEnemigasObservadas === []
            && $this->danioRecibido === []
            && $this->koRealizados === [];
    }
}
