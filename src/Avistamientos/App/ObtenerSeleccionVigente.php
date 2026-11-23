<?php

declare(strict_types=1);

namespace Src\Avistamientos\App;

use Src\Avistamientos\Domain\DataTransferObjects\AvistamientoRegistrado;
use Src\Avistamientos\Domain\DataTransferObjects\SeleccionVigente;
use Src\Avistamientos\Domain\ReglasAvistamiento;
use Src\Avistamientos\Domain\Repositories\AvistamientoRepositorioInterface;
use Src\Avistamientos\Domain\Services\CalculadoraVentanaAvistamiento;
use Src\Avistamientos\Domain\Services\SelectorAvistamiento;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Resuelve la selección de la ventana vigente.
 *
 * La primera lectura de una ventana elige hábitat y familia de forma
 * determinista y persiste la fila (`insertOrIgnore`). Las siguientes lecturas
 * devuelven la fila congelada, de modo que un cambio del pool a mitad de
 * ventana no altera el evento.
 */
final class ObtenerSeleccionVigente
{
    public function __construct(
        private readonly CalculadoraVentanaAvistamiento $calculadora,
        private readonly SelectorAvistamiento $selector,
        private readonly AvistamientoRepositorioInterface $repositorio,
        private readonly Reloj $reloj,
    ) {
    }

    public function __invoke(): ?SeleccionVigente
    {
        $ventana = $this->calculadora->actual($this->reloj->ahora());

        $existente = $this->repositorio->buscarPorVentana($ventana->indice);
        if ($existente !== null) {
            return new SeleccionVigente($ventana, $existente);
        }

        $habitats = $this->repositorio->habitatsCandidatos();
        if ($habitats->isEmpty()) {
            return null;
        }

        $habitat = $this->selector->habitat($habitats, $ventana);

        $familias = $this->repositorio->familiasCandidatas(
            $habitat->id,
            ReglasAvistamiento::MIN_ESPECIES_FAMILIA,
        );

        if ($familias->isEmpty()) {
            $familias = $this->repositorio->familiasCandidatas(
                $habitat->id,
                ReglasAvistamiento::MIN_ESPECIES_FAMILIA_FALLBACK,
            );
        }

        if ($familias->isEmpty()) {
            return null;
        }

        $familia = $this->selector->familia($familias, $ventana, $habitat->id);

        $registro = new AvistamientoRegistrado(
            windowIndex: $ventana->indice,
            startsAt: $ventana->inicio,
            endsAt: $ventana->fin,
            habitatId: $habitat->id,
            evolutionChainId: $familia->evolutionChainId,
            factor: ReglasAvistamiento::FACTOR_APARICION,
        );

        $this->repositorio->registrarSiNoExiste($registro);

        // Si dos peticiones compiten, la fila persistida puede ser la de la
        // otra petición: se relee para devolver siempre la misma selección.
        $persistido = $this->repositorio->buscarPorVentana($ventana->indice) ?? $registro;

        return new SeleccionVigente($ventana, $persistido);
    }
}
