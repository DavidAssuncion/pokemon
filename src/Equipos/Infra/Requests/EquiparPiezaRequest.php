<?php

declare(strict_types=1);

namespace Src\Equipos\Infra\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Domain\Items\ClavePieza;

/**
 * Validación de forma para equipar una pieza. El slot llega por la URL y el
 * stock, la propiedad del reclutado y el duplicado se comprueban en la capa
 * de aplicación.
 */
final class EquiparPiezaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        return [
            'clave' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! ClavePieza::esClaveValida($value)) {
                        $fail('La clave de la pieza no es válida.');
                    }
                },
            ],
        ];
    }

    public function clave(): string
    {
        return (string) $this->validated('clave');
    }
}
