<?php

declare(strict_types=1);

namespace Src\Forja\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de forma para fabricar. La existencia de la receta y el stock
 * se comprueban en la capa de aplicación.
 */
final class FabricarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'receta' => ['required', 'string', 'max:64'],
            'cantidad' => ['sometimes', 'integer', 'in:1'],
        ];
    }
}
