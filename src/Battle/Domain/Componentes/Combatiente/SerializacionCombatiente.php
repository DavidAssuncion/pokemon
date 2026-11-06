<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

trait SerializacionCombatiente
{
    public function __clone(): void
    {
        $this->identidad = clone $this->identidad;
        $this->vitalidad = clone $this->vitalidad;
        $this->estados = clone $this->estados;
        $this->condiciones = clone $this->condiciones;
        $this->turno = clone $this->turno;
        $this->movimientos = clone $this->movimientos;
        $this->objeto = clone $this->objeto;
        $this->habilidad = clone $this->habilidad;
        $this->efectos = clone $this->efectos;
        $this->stats = clone $this->stats;
        $this->registro = clone $this->registro;
        $this->recursos = clone $this->recursos;
        $this->invocacion = clone $this->invocacion;
        $this->jefe = clone $this->jefe;
    }

    public function __serialize(): array
    {
        return SerializadorCombatiente::serializar([
            $this->vitalidad->serializar(),
            $this->estados->serializar(),
            $this->stats->serializar(),
            $this->identidad->serializar(),
            $this->objeto->serializar(),
            $this->habilidad->serializar(),
            $this->jefe->serializar(),
            ['bonificadorDanio' => $this->bonificadorDanio],
            $this->registro->serializar(),
            ['clima' => $this->clima->value],
            $this->turno->serializar(),
            $this->movimientos->serializar(),
            $this->recursos->serializar(),
            $this->invocacion->serializar(),
            $this->condiciones->serializar(),
            $this->efectos->serializar(),
            ['pokemon' => SerializadorCombatiente::serializarPokemon($this->pokemon)],
            ['senda' => $this->senda->toArray()],
        ]);
    }

    public function __unserialize(array $data): void
    {
        $this->pokemon = SerializadorCombatiente::deserializarPokemon($data['pokemon'] ?? null);
        $this->senda = MapaSendaJugador::desdeArray(is_array($data['senda'] ?? null) ? $data['senda'] : []);
        $this->clima = TipoClima::tryFrom((string) ($data['clima'] ?? TipoClima::NONE->value)) ?? TipoClima::NONE;
        $this->bonificadorDanio = (float) ($data['bonificadorDanio'] ?? 1.0);
        $this->identidad = IdentidadCombatiente::desdeArray($data);
        $this->vitalidad = VitalidadCombatiente::desdeArray($data);
        $this->condiciones = CondicionesCombatiente::desdeArray($data);
        $this->estados = EstadosCombatiente::desdeArray($data, $this->condiciones);
        $this->turno = TurnoCombatiente::desdeArray($data);
        $this->movimientos = MovimientosCombatiente::desdeArray($data);
        $this->objeto = ObjetoCombatiente::desdeArray($data);
        $this->habilidad = HabilidadCombatiente::desdeArray($data);
        $this->efectos = EfectosCombatiente::desdeArray($data);
        $this->stats = StatsCombatiente::desdeArray($data);
        $this->registro = RegistroDanio::desdeArray($data);
        $this->recursos = RecursosCombatiente::desdeArray($data);
        $this->invocacion = InvocacionCombatiente::desdeArray($data);
        $this->jefe = JefeCombatiente::desdeArray($data);
    }
}
