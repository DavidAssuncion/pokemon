<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use Src\Exploraciones\Domain\EvaluadorExploracion;

/**
 * Despacha la resolución de un evento según su tipo.
 *
 * Los encuentros y emboscadas delegan en sus resolutores; los contratiempos
 * usan el evaluador de dominio con el rol del reclutado. Cualquier otro tipo
 * se devuelve sin cambios, conservando el evento original.
 */
final class ResolutorEventoExploracion
{
    public function __construct(
        private readonly ResolutorEncuentro $encuentro,
        private readonly ResolutorEmboscada $emboscada,
    ) {
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    public function resolver(array $evento, ContextoTickExploracion $contexto): array
    {
        return match ($evento['tipo'] ?? '') {
            'emboscada' => $this->emboscada->resolver($evento, $contexto),
            'encuentro' => $this->encuentro->resolver($evento, $contexto),
            'contratiempo' => $this->resolverContratiempo($evento, $contexto),
            default => $evento,
        };
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    private function resolverContratiempo(array $evento, ContextoTickExploracion $contexto): array
    {
        $roles = $contexto->exploracion->reclutado !== null
            ? [$contexto->exploracion->reclutado->rol()]
            : [];

        $resolucion = EvaluadorExploracion::resolverContratiempo(
            subtipo: (string) ($evento['subtype'] ?? 'terreno'),
            roles: $roles,
        );

        return array_merge($evento, $resolucion->toArray());
    }
}
