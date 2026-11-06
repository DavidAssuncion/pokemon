<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

/**
 * Criaturas temporales invocadas por otro combatiente.
 */
final class InvocacionCombatiente
{
    public function __construct(
        private int $turnosInvocacion = 0,
        private string $invocadoPorId = '',
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            turnosInvocacion: max(0, (int) ($data['turnosInvocacion'] ?? 0)),
            invocadoPorId: (string) ($data['invocadoPorId'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'turnosInvocacion' => $this->turnosInvocacion,
            'invocadoPorId' => $this->invocadoPorId,
        ];
    }

    public function esInvocacion(): bool
    {
        return $this->invocadoPorId !== '';
    }

    public function invocadoPorId(): string
    {
        return $this->invocadoPorId;
    }

    public function turnosInvocacion(): int
    {
        return $this->turnosInvocacion;
    }

    public function avanzarTurnoInvocacion(): bool
    {
        if (! $this->esInvocacion()) {
            return false;
        }

        $this->turnosInvocacion = max(0, $this->turnosInvocacion - 1);

        return $this->turnosInvocacion > 0;
    }

    public function prepararComoInvocacion(
        string $id,
        string $nombre,
        string $invocadoPorId,
        int $turnos,
        float $fraccionHp,
        float $maxHp,
        IdentidadCombatiente $identidad,
        VitalidadCombatiente $vitalidad,
        EstadosCombatiente $estados,
        CondicionesCombatiente $condiciones,
        EfectosCombatiente $efectos,
        StatsCombatiente $stats,
        RecursosCombatiente $recursos,
        MovimientosCombatiente $movimientos,
        TurnoCombatiente $turno,
        RegistroDanio $registro,
        ObjetoCombatiente $objeto,
        HabilidadCombatiente $habilidad,
        JefeCombatiente $jefe,
    ): void {
        $identidad->setId($id);
        $identidad->setNombre($nombre);
        $this->invocadoPorId = $invocadoPorId;
        $this->turnosInvocacion = max(1, $turnos);

        $vitalidad->prepararInvocacion($fraccionHp, $maxHp);
        $estados->reiniciar();
        $condiciones->reiniciar();
        $efectos->reiniciar();
        $stats->reiniciarMultiplicadores();
        $recursos->reiniciar();
        $movimientos->reiniciar();
        $turno->reiniciar();
        $registro->reiniciarParaInvocacion();
        $objeto->reiniciar();
        $habilidad->reiniciar();
        $jefe->reiniciar();
    }
}
