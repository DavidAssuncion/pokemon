<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Preferencias de moveset derivadas del artefacto build-time de Showdown.
 *
 * El artefacto se carga una sola vez por instancia. Si no existe o no es
 * válido, todas las consultas devuelven 0.0 sin romper el selector.
 */
final class PreferenciasShowdown
{
    private const BONUS_MOVEPOOL = 10.0;

    /** @var array{species: array<string, array<string, mixed>>, moves: array<string, array<string, mixed>>}|null */
    private ?array $datos = null;

    /** @var array<string, string>|null */
    private ?array $slugPorAlias = null;

    public function __construct(
        private readonly ?string $ruta = null,
    ) {
    }

    /**
     * Bonus de preferencia si el movimiento forma parte del movepool de la
     * especie en el artefacto de Showdown.
     */
    public function bonus(string $nombrePokemon, string $nombreMovimiento): float
    {
        $datos = $this->datos();
        $especie = $datos['species'][$this->normalizar($nombrePokemon)] ?? null;

        if (! is_array($especie)) {
            return 0.0;
        }

        $slug = $this->slugDeMovimiento($nombreMovimiento);
        if ($slug === null) {
            return 0.0;
        }

        $movepool = $especie['movepool'] ?? [];
        if (! is_array($movepool)) {
            return 0.0;
        }

        return in_array($slug, $movepool, true) ? self::BONUS_MOVEPOOL : 0.0;
    }

    private function slugDeMovimiento(string $nombreMovimiento): ?string
    {
        $alias = $this->alias();
        $normalizado = $this->normalizar($nombreMovimiento);

        if ($normalizado === '') {
            return null;
        }

        return $alias[$normalizado] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function alias(): array
    {
        if ($this->slugPorAlias !== null) {
            return $this->slugPorAlias;
        }

        $datos = $this->datos();
        $alias = [];

        foreach ($datos['moves'] as $slug => $info) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $alias[$this->normalizar($slug)] = $slug;

            if (! is_array($info)) {
                continue;
            }

            foreach (['showdown_name', 'game_name'] as $campo) {
                if (isset($info[$campo]) && is_string($info[$campo])) {
                    $clave = $this->normalizar($info[$campo]);
                    if ($clave !== '') {
                        $alias[$clave] = $slug;
                    }
                }
            }
        }

        $this->slugPorAlias = $alias;

        return $this->slugPorAlias;
    }

    /**
     * @return array{species: array<string, array<string, mixed>>, moves: array<string, array<string, mixed>>}
     */
    private function datos(): array
    {
        if ($this->datos !== null) {
            return $this->datos;
        }

        $ruta = $this->ruta ?? base_path('resources/data/showdown_movesets.json');
        $vacio = ['species' => [], 'moves' => []];

        if (! is_file($ruta) || ! is_readable($ruta)) {
            $this->datos = $vacio;

            return $this->datos;
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            $this->datos = $vacio;

            return $this->datos;
        }

        $decodificado = json_decode($contenido, true);
        if (! is_array($decodificado)) {
            $this->datos = $vacio;

            return $this->datos;
        }

        $this->datos = [
            'species' => is_array($decodificado['species'] ?? null) ? $decodificado['species'] : [],
            'moves' => is_array($decodificado['moves'] ?? null) ? $decodificado['moves'] : [],
        ];
        $this->slugPorAlias = null;

        return $this->datos;
    }

    private function normalizar(string $valor): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($valor))) ?? '';
    }
}
