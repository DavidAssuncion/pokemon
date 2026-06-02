<?php

namespace App\Crud\Base;

use Illuminate\Database\Eloquent\Model;

abstract class BaseCrudService
{
    protected Model $model;

    public function search(array $filters = [], ?string $sort = null, string $order = 'asc')
    {
        $query = $this->model->newQuery();

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') continue;

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        if ($sort) {
            $query->orderBy($sort, $order);
        }

        return $query->paginate();
    }

    public function create(BaseDTO $dto)
    {
        return $this->model->create($dto->toArray());
    }

    public function update(int $id, BaseDTO $dto)
    {
        $record = $this->model->findOrFail($id);

        $record->update($dto->toArray());

        return $record;
    }

    public function delete(int $id): void
    {
        $this->model->destroy($id);
    }
}