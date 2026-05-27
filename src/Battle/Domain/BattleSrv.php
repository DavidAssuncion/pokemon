<?php

namespace Src\Battle\Domain;

use Src\Pokemon\Domain\PokemonEntity;

class BattleSrv
{
    public function __construct() {}

    public function atacar(PokemonEntity $atacante, PokemonEntity $defensor, array $movimiento)
    {
        $daño = $this->calcularDaño($atacante, $defensor, $movimiento);
        return $daño;
    }

    public function calcularDaño(PokemonEntity $atacante, PokemonEntity $defensor, array $movimiento)
    {
        $nivel = 50; // Nivel del Pokémon atacante, esto podría ser un atributo de PokemonEntity
        //tipo de movimiento fisico /special
        $daño = ((((2 * $nivel / 5 + 2) * $movimiento['potencia'] * $atacante->battleStats->attack / $defensor->battleStats->defense) / 50) + 2);
        return $daño;
    }
    
    public function stab() {}
    public function calcularEfectividad() {}
    public function esCritico() {}
    public function clima() {}
}
