<?php

namespace Src\Shared\Domain;

class Collection
{
    public string $type;
    protected array $items = [];

    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }
    
    public function add($item): void
    {
        $this->validateType($item);
        $this->items[] = $item;
    }

    protected function validateType($item): void
    {
        if (!($item instanceof $this->type)) {
            throw new \InvalidArgumentException("Invalid type");
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function filter(callable $callback): static
    {
        return new static(array_filter($this->items, $callback));
    }
}
