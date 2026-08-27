<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use App\Bus\DatabaseUnitOfWork;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseUnitOfWorkTest extends TestCase
{
    use RefreshDatabase;

    private function uow(): DatabaseUnitOfWork
    {
        return new DatabaseUnitOfWork();
    }

    public function test_transaction_rollback_total_cuando_el_callback_lanza(): void
    {
        $this->assertDatabaseCount('provinces', 0);

        try {
            $this->uow()->transaction(function (): void {
                Province::create(['id' => 1, 'name' => 'Kanto']);
                throw new RuntimeException('fallo');
            });
            $this->fail('Se esperaba una excepción');
        } catch (RuntimeException) {
            // esperado
        }

        // El insert se revirtió por completo
        $this->assertDatabaseCount('provinces', 0);
    }

    public function test_transaction_commit_persiste_cuando_no_lanza(): void
    {
        $resultado = $this->uow()->transaction(fn (): string => Province::create(['id' => 1, 'name' => 'Kanto'])->name);

        $this->assertSame('Kanto', $resultado);
        $this->assertDatabaseHas('provinces', ['id' => 1, 'name' => 'Kanto']);
    }

    public function test_after_commit_se_ejecuta_solo_tras_commit_exitoso(): void
    {
        $ejecutados = [];

        $this->uow()->transaction(function () use (&$ejecutados): void {
            Province::create(['id' => 1, 'name' => 'Kanto']);

            $this->uow()->afterCommit(function () use (&$ejecutados): void {
                $ejecutados[] = 'commit';
            });
        });

        $this->assertSame(['commit'], $ejecutados);
    }

    public function test_after_commit_no_se_ejecuta_tras_rollback(): void
    {
        $ejecutados = [];

        try {
            $this->uow()->transaction(function () use (&$ejecutados): void {
                Province::create(['id' => 1, 'name' => 'Kanto']);

                $this->uow()->afterCommit(function () use (&$ejecutados): void {
                    $ejecutados[] = 'commit';
                });

                throw new RuntimeException('fallo');
            });
            $this->fail('Se esperaba una excepción');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame([], $ejecutados);
        $this->assertDatabaseCount('provinces', 0);
    }
}
