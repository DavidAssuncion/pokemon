<?php

declare(strict_types=1);

namespace Src\Battle\Infra\Repositories;

use App\Models\Move;
use Src\Battle\Domain\Collections\MovimientoCollection;
use Src\Battle\Domain\Exceptions\MovimientoNoExiste;
use Src\Battle\Domain\Movimiento;
use Src\Battle\Domain\Repositories\MovimientoRepository;
use Src\Battle\Infra\Factories\MovimientoFactory;

final class EloquentMovimientoRepository implements MovimientoRepository
{
    public function __construct(
        private readonly MovimientoFactory $factory,
    ) {
    }

    public function obtenerPorId(int $id): Movimiento
    {
        /** @var Move|null $modelo */
        $modelo = Move::find($id);
        if ($modelo === null) {
            throw MovimientoNoExiste::porId($id);
        }

        return $this->factory->desdeModelo($modelo);
    }

    public function obtenerPorSlug(string $slug): Movimiento
    {
        /** @var Move|null $modelo */
        $modelo = Move::where('slug', $slug)->first();
        if ($modelo === null) {
            throw MovimientoNoExiste::porSlug($slug);
        }

        return $this->factory->desdeModelo($modelo);
    }

    public function todos(): MovimientoCollection
    {
        /** @var list<Movimiento> $movimientos */
        $movimientos = Move::orderBy('name')
            ->get()
            ->map(fn (Move $modelo): Movimiento => $this->factory->desdeModelo($modelo))
            ->all();

        return new MovimientoCollection($movimientos);
    }
}
