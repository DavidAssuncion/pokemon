<?php

namespace App\Crud\Base;

abstract class BaseDTO
{
    public static function fromArray(array $data): static
    {
        $dto = new static();

        foreach ($data as $k => $v) {
            if (property_exists($dto, $k)) {
                $dto->$k = $v;
            }
        }

        return $dto;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}