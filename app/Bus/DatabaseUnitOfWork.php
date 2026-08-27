<?php

declare(strict_types=1);

namespace App\Bus;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Src\Shared\Bus\UnitOfWork;

final class DatabaseUnitOfWork implements UnitOfWork
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction(function (Connection $connection) use ($callback): mixed {
            return $callback();
        });
    }

    public function afterCommit(callable $callback): void
    {
        DB::afterCommit($callback);
    }
}
