<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de forma para plantar. El rango real del hueco depende del nivel
 * del nido y la validez de la baya se comprueba en la capa de aplicación.
 */
final class PlantarBayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'slot' => ['required', 'integer', 'min:1', 'max:10'],
            'berry' => ['required', 'string', 'max:32'],
        ];
    }
}
