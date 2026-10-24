<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\Collections;

use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;
use Src\Shared\Domain\Collection;

final class EvaluacionAmenazaCollection extends Collection
{
    public string $type = EvaluacionAmenaza::class;

    /**
     * @param  list<EvaluacionAmenaza>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<EvaluacionAmenaza> */
    public function all(): array
    {
        return $this->items;
    }

    public function ordenadasPorScoreDesc(): self
    {
        return new self($this->sort(
            fn (EvaluacionAmenaza $a, EvaluacionAmenaza $b): int => $b->score <=> $a->score,
        )->all());
    }
}
