<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\EstadoManager;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\ResultadoAccion;

/**
 * Estados mayores, veneno y veredicto de acción.
 */
final class EstadosCombatiente
{
    public function __construct(
        private EstadoPokemon $estado = EstadoPokemon::NONE,
        private int $contadorVenenoGrave = 0,
        private int $cargasVeneno = 0,
        private int $turnosEstado = 0,
        private EstadoManager $estadoManager = new EstadoManager(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data, CondicionesCombatiente $condiciones): self
    {
        $estado = EstadoPokemon::tryFrom($data['estado'] ?? EstadoPokemon::NONE->value) ?? EstadoPokemon::NONE;
        $turnosEstado = (int) ($data['turnosEstado'] ?? 0);

        // Compatibilidad v≤13: confusión vivía en 'estado'.
        if ($estado === EstadoPokemon::CONFUSION) {
            $estado = EstadoPokemon::NONE;
            if (! $condiciones->tiene(ClaveCondicion::CONFUSION)) {
                $condiciones->agregar(new CondicionVolatil(ClaveCondicion::CONFUSION, $turnosEstado));
            }
        }

        return new self(
            estado: $estado,
            contadorVenenoGrave: (int) ($data['contadorVenenoGrave'] ?? 0),
            cargasVeneno: (int) ($data['cargasVeneno'] ?? 0),
            turnosEstado: $turnosEstado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'estado' => $this->estado->value,
            'contadorVenenoGrave' => $this->contadorVenenoGrave,
            'cargasVeneno' => $this->cargasVeneno,
            'turnosEstado' => $this->turnosEstado,
        ];
    }

    public function __clone(): void
    {
        $this->estadoManager = new EstadoManager();
    }

    public function estadoMayor(): EstadoPokemon
    {
        return $this->estado;
    }

    public function turnosEstadoMayor(): int
    {
        return $this->turnosEstado;
    }

    public function turnosEstado(CondicionesCombatiente $condiciones): int
    {
        $confusion = $condiciones->buscar(ClaveCondicion::CONFUSION);

        return $confusion !== null ? $confusion->turnos : $this->turnosEstado;
    }

    public function contadorVenenoGrave(): int
    {
        return $this->contadorVenenoGrave;
    }

    public function cargasVeneno(): int
    {
        return $this->cargasVeneno;
    }

    public function aplicarCargaVeneno(int $cargas = 1): void
    {
        $this->cargasVeneno = max(0, min(8, $this->cargasVeneno + max(1, $cargas)));
    }

    public function setEstado(EstadoPokemon $estado, CondicionesCombatiente $condiciones): void
    {
        if ($estado === EstadoPokemon::CONFUSION) {
            if (! $condiciones->tiene(ClaveCondicion::CONFUSION)) {
                $condiciones->agregar(new CondicionVolatil(
                    clave: ClaveCondicion::CONFUSION,
                    turnos: 2,
                    origenId: '',
                ));
            }

            return;
        }

        if ($estado !== EstadoPokemon::POISON && $estado !== EstadoPokemon::BAD_POISON) {
            $this->cargasVeneno = 0;
        }

        $this->estado = $estado;
    }

    public function setContadorVenenoGrave(int $contadorVenenoGrave): void
    {
        $this->contadorVenenoGrave = $contadorVenenoGrave;
    }

    public function setTurnosEstado(int $turnosEstado, CondicionesCombatiente $condiciones): void
    {
        $confusion = $condiciones->buscar(ClaveCondicion::CONFUSION);
        if ($confusion !== null) {
            $confusion->turnos = $turnosEstado;

            return;
        }

        $this->turnosEstado = $turnosEstado;
    }

    /**
     * @param  callable(StatClave): float  $obtenerStatEfectivo
     */
    public function puedeActuar(
        CondicionesCombatiente $condiciones,
        VitalidadCombatiente $vitalidad,
        MovimientosCombatiente $movimientos,
        RecursosCombatiente $recursos,
        callable $obtenerStatEfectivo,
    ): ResultadoAccion {
        if (! $vitalidad->estaVivo()) {
            return ResultadoAccion::permitida();
        }

        $bloqueo = $this->veredictoCondicionBloqueante($condiciones, $movimientos, $recursos);
        if ($bloqueo !== null) {
            return $bloqueo;
        }

        $confusion = $condiciones->buscar(ClaveCondicion::CONFUSION);
        if ($confusion !== null) {
            return $this->resolverConfusion(
                $confusion,
                $condiciones,
                $vitalidad,
                $recursos,
                $obtenerStatEfectivo,
            );
        }

        return $this->resolverEstadoMayor($vitalidad, $recursos, $obtenerStatEfectivo);
    }

    private function veredictoCondicionBloqueante(
        CondicionesCombatiente $condiciones,
        MovimientosCombatiente $movimientos,
        RecursosCombatiente $recursos,
    ): ?ResultadoAccion {
        if ($condiciones->tiene(ClaveCondicion::INTERRUPCION)) {
            $condiciones->quitar(ClaveCondicion::INTERRUPCION);
            $movimientos->cancelarCargas();
            $recursos->romperCombo();

            return ResultadoAccion::denegada('fue interrumpido');
        }

        if ($condiciones->tiene(ClaveCondicion::ATURDIDO)) {
            $condiciones->quitar(ClaveCondicion::ATURDIDO);
            $recursos->romperCombo();

            return ResultadoAccion::denegada('está aturdido');
        }

        if ($condiciones->tiene(ClaveCondicion::RECARGA)) {
            $condiciones->quitar(ClaveCondicion::RECARGA);
            $recursos->romperCombo();

            return ResultadoAccion::denegada('está recargando');
        }

        if ($condiciones->tiene(ClaveCondicion::FLINCH)) {
            $condiciones->quitar(ClaveCondicion::FLINCH);
            $recursos->romperCombo();

            return ResultadoAccion::denegada('retrocede');
        }

        return null;
    }

    private function resolverConfusion(
        CondicionVolatil $confusion,
        CondicionesCombatiente $condiciones,
        VitalidadCombatiente $vitalidad,
        RecursosCombatiente $recursos,
        callable $obtenerStatEfectivo,
    ): ResultadoAccion {
        $resultado = $this->estadoManager->puedeActuar(
            EstadoPokemon::CONFUSION,
            $confusion->turnos,
            $vitalidad->hpActual(),
            $obtenerStatEfectivo,
        );

        $confusion->turnos = $resultado['turnosResultado'];
        $vitalidad->setHpActual($resultado['hpResultado']);

        if ($resultado['estadoResultado'] === EstadoPokemon::NONE) {
            $condiciones->quitar(ClaveCondicion::CONFUSION);
        }

        if (! $resultado['canAct']) {
            $recursos->romperCombo();
        }

        return self::resultadoAccionDesde($resultado);
    }

    private function resolverEstadoMayor(
        VitalidadCombatiente $vitalidad,
        RecursosCombatiente $recursos,
        callable $obtenerStatEfectivo,
    ): ResultadoAccion {
        if ($this->estado === EstadoPokemon::NONE) {
            return ResultadoAccion::permitida();
        }

        $resultado = $this->estadoManager->puedeActuar(
            $this->estado,
            $this->turnosEstado,
            $vitalidad->hpActual(),
            $obtenerStatEfectivo,
        );

        $this->estado = $resultado['estadoResultado'];
        $this->turnosEstado = $resultado['turnosResultado'];
        $vitalidad->setHpActual($resultado['hpResultado']);

        if (! $resultado['canAct']) {
            $recursos->romperCombo();
        }

        return self::resultadoAccionDesde($resultado);
    }

    public function aplicarDanoStatus(float $hpMax, VitalidadCombatiente $vitalidad, bool $divideDolor): float
    {
        if (! $vitalidad->estaVivo() || $this->estado === EstadoPokemon::NONE) {
            return 0;
        }

        $resultado = $this->estadoManager->calcularDanoStatus(
            $this->estado,
            $this->contadorVenenoGrave,
            $hpMax,
            $this->cargasVeneno,
        );

        $this->contadorVenenoGrave = $resultado['contadorNuevo'];

        if ($resultado['dano'] > 0) {
            $vitalidad->recibirDano($resultado['dano'], true, 0.0, 1.0, 1.0, $divideDolor);
        }

        return $resultado['dano'];
    }

    public function reiniciar(): void
    {
        $this->estado = EstadoPokemon::NONE;
        $this->contadorVenenoGrave = 0;
        $this->cargasVeneno = 0;
        $this->turnosEstado = 0;
    }

    /**
     * @param  array{canAct: bool, reason: string, selfDamage: float}  $resultado
     */
    private static function resultadoAccionDesde(array $resultado): ResultadoAccion
    {
        if (! $resultado['canAct']) {
            return $resultado['selfDamage'] > 0
                ? ResultadoAccion::denegadaConAutoDanio($resultado['reason'], $resultado['selfDamage'])
                : ResultadoAccion::denegada($resultado['reason']);
        }

        return $resultado['reason'] === ''
            ? ResultadoAccion::permitida()
            : ResultadoAccion::permitidaConMotivo($resultado['reason']);
    }
}
