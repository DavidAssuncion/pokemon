<?php

declare(strict_types=1);

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Value Object + custom cast de `reclutados.exp` (jsonb).
 *
 * Shape: `{"total": int, "tipos": array<string, int>}`. Compatible con datos
 * legacy: `{"total": N}` sin 'tipos', `{}` o null → total 0 y tipos vacíos.
 * Inmutable: sumarExpTipo()/consumirTipos() devuelven una instancia nueva para
 * que Eloquent detecte el cambio al asignar (`$reclutado->exp = $reclutado->exp->sumarExpTipo(...)`).
 *
 * @implements CastsAttributes<self, mixed>
 */
final class ExpReclutado implements CastsAttributes
{
    /**
     * @param  array<string, int>  $tipos
     */
    public function __construct(
        public readonly int $total = 0,
        public readonly array $tipos = [],
    ) {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): self
    {
        return self::fromRaw($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return self::fromRaw($value)->toJson();
    }

    /**
     * Normaliza cualquier valor legacy (string json, array, null) al VO.
     */
    public static function fromRaw(mixed $raw): self
    {
        if ($raw instanceof self) {
            return $raw;
        }

        if ($raw === null || $raw === '') {
            return new self();
        }

        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($data)) {
            return new self();
        }

        return new self(
            total: (int) ($data['total'] ?? 0),
            tipos: array_map('intval', (array) ($data['tipos'] ?? [])),
        );
    }

    public function total(): int
    {
        return $this->total;
    }

    public function expTipo(string $tipo): int
    {
        return $this->tipos[$tipo] ?? 0;
    }

    /**
     * Suma exp de tipo devolviendo una instancia nueva (no muta el total).
     */
    public function sumarExpTipo(string $tipo, int $cantidad): self
    {
        $tipos = $this->tipos;
        $tipos[$tipo] = $this->expTipo($tipo) + $cantidad;

        return new self(total: $this->total, tipos: $tipos);
    }

    /**
     * Resta el umbral a cada tipo indicado; los tipos que llegan a 0 o menos
     * se eliminan (misma semántica que la antigua tabla reclutados_exp_tipo).
     *
     * @param  list<string>  $tipos
     */
    public function consumirTipos(array $tipos, int $umbral): self
    {
        $restantes = $this->tipos;

        foreach ($tipos as $tipo) {
            if (! isset($restantes[$tipo])) {
                continue;
            }

            if ($restantes[$tipo] <= $umbral) {
                unset($restantes[$tipo]);
            } else {
                $restantes[$tipo] -= $umbral;
            }
        }

        return new self(total: $this->total, tipos: $restantes);
    }

    /**
     * @return array{total: int, tipos: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'tipos' => $this->tipos,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
