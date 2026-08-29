<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_is_not_exploring_when_no_exploraciones(): void
    {
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $this->assertFalse($team->isExploring());
    }

    public function test_team_is_exploring_when_has_active_exploration(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);

        $this->assertTrue($team->isExploring());
    }

    public function test_team_is_not_exploring_after_exploration_completes(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $exploracion->update(['regreso' => now()]);

        $this->assertFalse($team->isExploring());
    }

    public function test_team_is_exploring_when_one_of_multiple_explorations_is_active(): void
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);
        $team = Team::create(['name' => 'Alpha', 'user_id' => User::factory()->create()->id]);

        $completed = ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
        ]);
        $completed->update(['regreso' => now()]);

        ExploracionActiva::create([
            'user_id' => $team->user_id,
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 2,
        ]);

        $this->assertTrue($team->isExploring());
    }
}
