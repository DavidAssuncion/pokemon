<?php

namespace Database\Factories;

use App\Models\Habitat;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Habitat>
 */
class HabitatFactory extends Factory
{
    protected $model = Habitat::class;

    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
