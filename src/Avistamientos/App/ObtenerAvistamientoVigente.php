<?php

declare(strict_types=1);

namespace Src\Avistamientos\App;

use DateTimeInterface;
use Src\Avistamientos\Domain\DataTransferObjects\AvistamientoPagina;
use Src\Avistamientos\Domain\DataTransferObjects\EspecieAvistada;
use Src\Avistamientos\Domain\DataTransferObjects\EspeciesAvistadasCollection;
use Src\Avistamientos\Domain\DataTransferObjects\NivelAvistamiento;
use Src\Avistamientos\Domain\DataTransferObjects\NivelesAvistamientoCollection;
use Src\Avistamientos\Domain\Repositories\AvistamientoRepositorioInterface;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Compone el contrato de la ventana vigente para la UI.
 *
 * Hábitat, familia, especies, niveles y estado «visto» se resuelven aquí; la
 * vista/modal solo formatea. Si la familia persistida ya no está en el hábitat,
 * el evento sigue activo pero se marca `sinEfecto`.
 */
final class ObtenerAvistamientoVigente
{
    public function __construct(
        private readonly ObtenerSeleccionVigente $obtenerSeleccion,
        private readonly AvistamientoRepositorioInterface $repositorio,
        private readonly Reloj $reloj,
    ) {
    }

    public function __invoke(int $userId): AvistamientoPagina
    {
        $seleccion = ($this->obtenerSeleccion)();

        if ($seleccion === null) {
            return AvistamientoPagina::vacio();
        }

        $registro = $seleccion->registro;
        $habitat = $this->repositorio->habitat($registro->habitatId);
        $especies = $this->repositorio->especiesDeFamilia(
            $registro->habitatId,
            $registro->evolutionChainId,
        );
        $familiaNombre = $this->repositorio->nombreGlobalDeFamilia($registro->evolutionChainId);
        $vistos = $this->repositorio->vistosDelJugador($userId, $especies);

        $vistoPorPokemon = [];
        foreach ($vistos as $vista) {
            $vistoPorPokemon[$vista->pokemonId] = $vista->visto;
        }

        $porNivel = [];
        foreach ($especies as $especie) {
            $porNivel[$especie->nivel][] = new EspecieAvistada(
                pokemonId: $especie->pokemonId,
                nombre: $especie->nombre,
                visto: $vistoPorPokemon[$especie->pokemonId] ?? false,
            );
        }

        ksort($porNivel);

        $niveles = new NivelesAvistamientoCollection();
        foreach ($porNivel as $nivel => $especiesDelNivel) {
            $niveles->add(new NivelAvistamiento(
                nivel: $nivel,
                especies: new EspeciesAvistadasCollection($especiesDelNivel),
            ));
        }

        $segundosRestantes = $seleccion->ventana->segundosRestantes($this->reloj->ahora());

        return new AvistamientoPagina(
            activo: true,
            indice: $registro->windowIndex,
            habitatId: $registro->habitatId,
            habitatNombre: $habitat?->nombre,
            familiaNombre: $familiaNombre,
            evolutionChainId: $registro->evolutionChainId,
            factor: $registro->factor,
            terminaEn: $registro->endsAt->format(DateTimeInterface::ATOM),
            restanteTexto: $this->textoRestante($segundosRestantes),
            sinEfecto: $especies->isEmpty(),
            niveles: $niveles,
        );
    }

    private function textoRestante(int $segundos): string
    {
        $dias = intdiv($segundos, 86400);
        $horas = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        if ($dias > 0) {
            return sprintf('Termina en %d d %d h', $dias, $horas);
        }

        if ($horas > 0) {
            return sprintf('Termina en %d h %d min', $horas, $minutos);
        }

        return sprintf('Termina en %d min', max(1, $minutos));
    }
}
