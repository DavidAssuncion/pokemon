<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Galicia',
                'Vasco',
                'Barcelona',
                'Extremadura',
                'Nova Alacant',
                'Mutxamel',
                'Andalucia',
            ]),
        ];
    }
}
