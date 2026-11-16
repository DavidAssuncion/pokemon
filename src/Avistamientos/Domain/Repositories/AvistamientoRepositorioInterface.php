<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\Repositories;

use Src\Avistamientos\Domain\DataTransferObjects\AvistamientoRegistrado;
use Src\Avistamientos\Domain\DataTransferObjects\EspeciesEnHabitatCollection;
use Src\Avistamientos\Domain\DataTransferObjects\EspeciesVistasCollection;
use Src\Avistamientos\Domain\DataTransferObjects\HabitatAvistamiento;
use Src\Avistamientos\Domain\ReglasAvistamiento;
use Src\Avistamientos\Domain\ValueObjects\FamiliasCandidatasCollection;
use Src\Avistamientos\Domain\ValueObjects\HabitatsCandidatosCollection;

/**
 * Lectura y escritura de las ventanas de avistamiento.
 *
 * La única escritura es idempotente por índice de ventana: la primera fila
 * insertada gana y nunca se actualiza.
 */
interface AvistamientoRepositorioInterface
{
    /**
     * Fila persistida de la ventana o null si aún no se ha registrado.
     */
    public function buscarPorVentana(int $indice): ?AvistamientoRegistrado;

    /**
     * Registra la ventana si no existe; si ya existe, no hace nada.
     */
    public function registrarSiNoExiste(AvistamientoRegistrado $registro): void;

    /**
     * Hábitats elegibles, ordenados por id ascendente.
     */
    public function habitatsCandidatos(): HabitatsCandidatosCollection;

    /**
     * Familias del hábitat con el mínimo de especies indicado, ordenadas por
     * cadena evolutiva.
     */
    public function familiasCandidatas(
        int $habitatId,
        int $minimoEspecies = ReglasAvistamiento::MIN_ESPECIES_FAMILIA,
    ): FamiliasCandidatasCollection;

    /**
     * Hábitat del avistamiento o null si ya no existe.
     */
    public function habitat(int $habitatId): ?HabitatAvistamiento;

    /**
     * Especies de la familia presentes en el hábitat.
     */
    public function especiesDeFamilia(int $habitatId, int $evolutionChainId): EspeciesEnHabitatCollection;

    /**
     * Nombre visible de la familia a partir de su miembro de menor species_id,
     * aunque ya no esté en el hábitat.
     */
    public function nombreGlobalDeFamilia(int $evolutionChainId): ?string;

    /**
     * Estado «visto» de cada especie para el jugador indicado.
     */
    public function vistosDelJugador(
        int $userId,
        EspeciesEnHabitatCollection $especies,
        bool $desbloqueoTodo = false,
    ): EspeciesVistasCollection;
}
