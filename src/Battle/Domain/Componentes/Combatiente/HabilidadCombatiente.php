<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EstadoHabilidad;

/**
 * Anulación, copia e intercambio de habilidad.
 */
final class HabilidadCombatiente
{
    public function __construct(
        private EstadoHabilidad $habilidad = new EstadoHabilidad(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        $habilidad = new EstadoHabilidad();
        $habilidad->restaurarDesde(
            (bool) ($data['habilidadAnulada'] ?? false),
            SerializadorCombatiente::deserializarGuardados($data['efectosHabilidadGuardados'] ?? []),
            (bool) ($data['habilidadAnuladaPorGas'] ?? false),
        );

        return new self($habilidad);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'habilidadAnulada' => $this->habilidad->anulada(),
            'habilidadAnuladaPorGas' => $this->habilidad->anuladaPorGas(),
            'efectosHabilidadGuardados' => SerializadorCombatiente::serializarGuardados($this->habilidad->guardados()),
        ];
    }

    public function __clone(): void
    {
        $this->habilidad = clone $this->habilidad;
    }

    public function anulada(): bool
    {
        return $this->habilidad->anulada();
    }

    public function anuladaPorGas(): bool
    {
        return $this->habilidad->anuladaPorGas();
    }

    public function anular(
        int $turnos,
        bool $porGas,
        EfectosCombatiente $effects,
        CondicionesCombatiente $condiciones,
    ): void {
        if ($this->habilidad->anulada()) {
            return;
        }

        $this->habilidad->marcarAnulada($this->efectosHabilidad($effects), $porGas);

        $restantes = [];
        foreach ($effects->all() as $efecto) {
            if ($this->esEfectoDeObjeto($efecto)) {
                $restantes[] = $efecto;
            }
        }
        $effects->reemplazar($restantes);

        $condiciones->reemplazar(new CondicionVolatil(
            clave: ClaveCondicion::HABILIDAD_ANULADA,
            turnos: max(1, $turnos),
        ));
    }

    public function restaurar(EfectosCombatiente $effects, CondicionesCombatiente $condiciones): void
    {
        foreach ($this->habilidad->restaurar() as $efecto) {
            $effects->coleccion()->add($efecto);
        }

        $condiciones->quitar(ClaveCondicion::HABILIDAD_ANULADA);
    }

    public function efectosHabilidad(EfectosCombatiente $effects): ColeccionEfectos
    {
        $efectos = new ColeccionEfectos();
        foreach ($effects->all() as $efecto) {
            if (! $this->esEfectoDeObjeto($efecto)) {
                $efectos->add($efecto);
            }
        }

        return $efectos;
    }

    public function copiarDe(
        self $otro,
        EfectosCombatiente $propios,
        CondicionesCombatiente $condiciones,
        EfectosCombatiente $ajenos,
    ): void {
        $this->restaurar($propios, $condiciones);
        $copias = array_map(
            static fn (InterfazEfecto $efecto): InterfazEfecto => clone $efecto,
            $otro->efectosHabilidad($ajenos)->all(),
        );

        $this->reemplazarEfectosHabilidad($propios, $copias);
    }

    public function intercambiarCon(
        self $otro,
        EfectosCombatiente $propios,
        CondicionesCombatiente $condicionesPropias,
        EfectosCombatiente $ajenos,
        CondicionesCombatiente $condicionesAjenas,
    ): void {
        $this->restaurar($propios, $condicionesPropias);
        $otro->restaurar($ajenos, $condicionesAjenas);

        $propias = $this->efectosHabilidad($propios);
        $otras = $otro->efectosHabilidad($ajenos);

        $this->reemplazarEfectosHabilidad($propios, $otras->all());
        $otro->reemplazarEfectosHabilidad($ajenos, $propias->all());
    }

    /**
     * @param  list<InterfazEfecto>  $efectos
     */
    private function reemplazarEfectosHabilidad(EfectosCombatiente $effects, array $efectos): void
    {
        $items = [];
        foreach ($effects->all() as $efecto) {
            if ($this->esEfectoDeObjeto($efecto)) {
                $items[] = $efecto;
            }
        }

        $effects->reemplazar([...$items, ...$efectos]);
    }

    private function esEfectoDeObjeto(InterfazEfecto $efecto): bool
    {
        return CatalogoObjetos::clavesDeEfecto()->contiene($efecto->obtenerClave());
    }

    public function reiniciar(): void
    {
        $this->habilidad = new EstadoHabilidad();
    }
}
