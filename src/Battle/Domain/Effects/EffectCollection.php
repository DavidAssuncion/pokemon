<?php

namespace Src\Battle\Domain\Effects;

class EffectCollection
{
    /** @var EffectInterface[] */
    private array $effects = [];

    public function add(EffectInterface $effect): void
    {
        $this->effects[] = $effect;
    }

    public function all(): array
    {
        return $this->effects;
    }

    public function unicos(): array
    {
        return array_values(array_filter(
            $this->effects,
            fn(EffectInterface $e) => $e->esUnico()
        ));
    }

    public function count(): int
    {
        return count($this->effects);
    }

    public function isEmpty(): bool
    {
        return empty($this->effects);
    }
}
