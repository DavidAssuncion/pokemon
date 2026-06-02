<?php

namespace Src\Battle\Domain\Effects;

class EffectFactory
{
    public function createFromAbility(array $abilityData): array
    {
        $effects = [];

        $efectos = $abilityData['efectos'] ?? [];

        foreach ($efectos as $efecto) {
            $effects[] = new AbilityEffect(
                clave: $efecto['clave'] ?? '',
                habilidadNombre: $abilityData['nombre'] ?? '',
                unico: ($efecto['tipo'] ?? '') === 'UNICO',
            );
        }

        return $effects;
    }

    public function createFromItem(array $itemData): array
    {
        return [];
    }
}
