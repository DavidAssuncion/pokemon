<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;

/**
 * Value Object inmutable con un evento de la bitácora de exploración (RF-04).
 *
 * Representa cualquier evento del pipeline (encuentro, hallazgo, emboscada,
 * contratiempo, neutral, descanso) tanto generado como resuelto. Los métodos
 * de negocio replican la lógica que antes vivía en EvaluadorExploracion y
 * FinalizarExploracionHandler sobre arrays.
 *
 * aArray() es FRONTERA: emite únicamente las claves no nulas en el orden
 * canónico del pipeline actual para preservar el JSON persistido en
 * exploracion->eventos y el contrato de la vista (_evento.blade.php).
 */
final readonly class EventoExploracion
{
    /**
     * @param  list<array{pokemon_id: int, victoria: bool}>|null  $subCombates
     */
    public function __construct(
        public readonly string $tipo = '',
        public readonly ?string $subtype = null,
        public readonly ?string $detalle = null,
        public readonly ?int $pokemonId = null,
        public readonly IntCollection $pokemonIds = new IntCollection(),
        public readonly ?int $stat = null,
        public readonly ?int $tipoId = null,
        public readonly ?int $cantidad = null,
        public readonly ?string $origen = null,
        public readonly ?string $timestamp = null,
        public readonly ?string $resolucion = null,
        public readonly ?bool $victoria = null,
        public readonly ?float $hpFinal = null,
        public readonly ?float $barreraFisicaFinal = null,
        public readonly ?float $barreraEspecialFinal = null,
        public readonly ?float $barreraFisicaMax = null,
        public readonly ?float $barreraEspecialMax = null,
        public readonly StringCollection $log = new StringCollection(),
        public readonly ?int $durationLoss = null,
        public readonly ?array $subCombates = null,
        public readonly ?bool $derrota = null,
        public readonly ?bool $evitada = null,
        public readonly ?bool $retiradaProbable = null,
        public readonly ?string $reason = null,
        public readonly ?int $duracionMinutos = null,
        public readonly ?float $hpRecuperado = null,
    ) {
    }

    /**
     * Frontera — lectura del contrato persistido/generado de un evento.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            tipo: (string) ($datos['tipo'] ?? ''),
            subtype: isset($datos['subtype']) ? (string) $datos['subtype'] : null,
            detalle: isset($datos['detalle']) ? (string) $datos['detalle'] : null,
            pokemonId: isset($datos['pokemon_id']) ? (int) $datos['pokemon_id'] : null,
            pokemonIds: new IntCollection(array_map(
                static fn (mixed $id): int => (int) $id,
                (array) ($datos['pokemon_ids'] ?? []),
            )),
            stat: isset($datos['stat']) ? (int) $datos['stat'] : null,
            tipoId: isset($datos['tipo_id']) ? (int) $datos['tipo_id'] : null,
            cantidad: isset($datos['cantidad']) ? (int) $datos['cantidad'] : null,
            origen: isset($datos['origen']) ? (string) $datos['origen'] : null,
            timestamp: isset($datos['timestamp']) ? (string) $datos['timestamp'] : null,
            resolucion: isset($datos['resolucion']) ? (string) $datos['resolucion'] : null,
            victoria: isset($datos['victoria']) ? (bool) $datos['victoria'] : null,
            hpFinal: isset($datos['hp_final']) ? (float) $datos['hp_final'] : null,
            barreraFisicaFinal: isset($datos['barrera_fisica_final']) ? (float) $datos['barrera_fisica_final'] : null,
            barreraEspecialFinal: isset($datos['barrera_especial_final']) ? (float) $datos['barrera_especial_final'] : null,
            barreraFisicaMax: isset($datos['barrera_fisica_max']) ? (float) $datos['barrera_fisica_max'] : null,
            barreraEspecialMax: isset($datos['barrera_especial_max']) ? (float) $datos['barrera_especial_max'] : null,
            log: new StringCollection(array_map(
                static fn (mixed $linea): string => (string) $linea,
                (array) ($datos['log'] ?? []),
            )),
            durationLoss: isset($datos['duration_loss']) ? (int) $datos['duration_loss'] : null,
            subCombates: isset($datos['sub_combates']) ? $datos['sub_combates'] : null,
            derrota: isset($datos['derrota']) ? (bool) $datos['derrota'] : null,
            evitada: isset($datos['evitada']) ? (bool) $datos['evitada'] : null,
            retiradaProbable: isset($datos['retirada_probable']) ? (bool) $datos['retirada_probable'] : null,
            reason: isset($datos['reason']) ? (string) $datos['reason'] : null,
            duracionMinutos: isset($datos['duracion_minutos']) ? (int) $datos['duracion_minutos'] : null,
            hpRecuperado: isset($datos['hp_recuperado']) ? (float) $datos['hp_recuperado'] : null,
        );
    }

    /**
     * Frontera — shape exacto del contrato previo del evento: emite SOLO las
     * claves no nulas, en un orden canónico estable. El orden de claves no
     * forma parte del contrato (la vista lee por clave y PHPUnit assertEquals
     * ignora el orden); lo que importa es el conjunto de claves y sus valores,
     * idéntico al `array_merge($evento, $resolucion)` del pipeline.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        $datos = [];

        if ($this->tipo !== '') {
            $datos['tipo'] = $this->tipo;
        }

        if ($this->subtype !== null) {
            $datos['subtype'] = $this->subtype;
        }

        if ($this->detalle !== null) {
            $datos['detalle'] = $this->detalle;
        }

        if ($this->pokemonId !== null) {
            $datos['pokemon_id'] = $this->pokemonId;
        }

        if (! $this->pokemonIds->isEmpty()) {
            $datos['pokemon_ids'] = $this->pokemonIds->all();
        }

        if ($this->stat !== null) {
            $datos['stat'] = $this->stat;
        }

        if ($this->tipoId !== null) {
            $datos['tipo_id'] = $this->tipoId;
        }

        if ($this->cantidad !== null) {
            $datos['cantidad'] = $this->cantidad;
        }

        if ($this->origen !== null) {
            $datos['origen'] = $this->origen;
        }

        if ($this->timestamp !== null) {
            $datos['timestamp'] = $this->timestamp;
        }

        if ($this->resolucion !== null) {
            $datos['resolucion'] = $this->resolucion;
        }

        if ($this->victoria !== null) {
            $datos['victoria'] = $this->victoria;
        }

        if ($this->hpFinal !== null) {
            $datos['hp_final'] = $this->hpFinal;
        }

        if ($this->barreraFisicaFinal !== null) {
            $datos['barrera_fisica_final'] = $this->barreraFisicaFinal;
        }

        if ($this->barreraEspecialFinal !== null) {
            $datos['barrera_especial_final'] = $this->barreraEspecialFinal;
        }

        if ($this->barreraFisicaMax !== null) {
            $datos['barrera_fisica_max'] = $this->barreraFisicaMax;
        }

        if ($this->barreraEspecialMax !== null) {
            $datos['barrera_especial_max'] = $this->barreraEspecialMax;
        }

        if (! $this->log->isEmpty()) {
            $datos['log'] = $this->log->toList();
        }

        if ($this->durationLoss !== null) {
            $datos['duration_loss'] = $this->durationLoss;
        }

        if ($this->subCombates !== null) {
            $datos['sub_combates'] = $this->subCombates;
        }

        if ($this->derrota !== null) {
            $datos['derrota'] = $this->derrota;
        }

        if ($this->evitada !== null) {
            $datos['evitada'] = $this->evitada;
        }

        if ($this->retiradaProbable !== null) {
            $datos['retirada_probable'] = $this->retiradaProbable;
        }

        if ($this->reason !== null) {
            $datos['reason'] = $this->reason;
        }

        if ($this->duracionMinutos !== null) {
            $datos['duracion_minutos'] = $this->duracionMinutos;
        }

        if ($this->hpRecuperado !== null) {
            $datos['hp_recuperado'] = $this->hpRecuperado;
        }

        return $datos;
    }

    /**
     * RF-07: un evento cuenta como victoria (derrotado) si su resolución es
     * 'victoria', 'evitada' (emboscada esquivada: doble premio) o si NO tiene
     * resolución (retrocompat de bitácoras antiguas). Solo los eventos de
     * combate (encuentro/pokemon/emboscada) pueden ser derrotas.
     */
    public function esVictoria(): bool
    {
        $tiposCombate = ['encuentro', 'pokemon', 'emboscada'];

        if ($this->resolucion === null && ! in_array($this->tipo, $tiposCombate, true)) {
            return false;
        }

        return $this->resolucion === null || in_array($this->resolucion, ['victoria', 'evitada'], true);
    }

    /**
     * ¿El evento es un encuentro con pokémon que puede reportar avistamiento?
     */
    public function esAvistamiento(): bool
    {
        return in_array($this->tipo, ['pokemon', 'encuentro', 'emboscada', 'huida'], true);
    }

    /**
     * ¿El evento es un hallazgo de caramelos (familia/EV/tipo)?
     */
    public function esHallazgo(): bool
    {
        return $this->tipo === 'hallazgo';
    }

    /**
     * ¿El evento es una emboscada evitada (detección)? Otorga un hallazgo
     * equivalente de 1 caramelo de familia (doble premio con esVictoria).
     */
    public function esEmboscadaEvitada(): bool
    {
        return $this->tipo === 'emboscada' && $this->resolucion === 'evitada';
    }

    /**
     * ¿El evento se resolvió con derrota (termina la exploración)?
     */
    public function esDerrota(): bool
    {
        return $this->resolucion === 'derrota';
    }

    /**
     * IDs de pokémon del evento (pokemon_ids o, en su defecto, pokemon_id).
     *
     * @return IntCollection Lista tipada de ids (vacía si no hay pokémon).
     */
    public function pokemonIds(): IntCollection
    {
        if (! $this->pokemonIds->isEmpty()) {
            return $this->pokemonIds;
        }

        return new IntCollection($this->pokemonId !== null ? [$this->pokemonId] : []);
    }
}
