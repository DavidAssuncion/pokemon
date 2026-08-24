<?php

declare(strict_types=1);

namespace App\Crud\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BaseCrudController
{
    public function __construct(
        private BaseCrudService $service
    ) {
    }

    protected string $modelClass;

    protected string $dtoClass;

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->service->search(
            $this->modelClass,
            $request->get('filter', []),
            $request->get('sort'),
            $request->get('order', 'asc')
        );
    }

    public function store(Request $request): Model
    {
        return $this->service->create(
            $this->modelClass,
            $request->all()
        );
    }

    public function update(int $id, Request $request): Model
    {
        return $this->service->update(
            $this->modelClass,
            $id,
            $request->all()
        );
    }

    public function destroy(int $id): void
    {
        $this->service->delete(
            $this->modelClass,
            $id
        );
    }
}
