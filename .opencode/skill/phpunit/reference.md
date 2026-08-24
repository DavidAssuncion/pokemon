# PHPUnit Skill Reference

## Config

- `phpunit.xml` — Unit (tests/Unit), Feature (tests/Feature)
- SQLite in memory for tests

## Commands

```bash
# All tests
php artisan test --compact

# Specific suite
php artisan test --compact --testsuite=Unit
php artisan test --compact --testsuite=Feature

# Filter
php artisan test --compact --filter=TestMethodName
php artisan test --compact --filter=TestClassName

# Specific file
php artisan test --compact tests/Feature/BatallaTest.php

# With coverage
php artisan test --compact --coverage --min=80

# Parallel (if pest-parallel or paratest)
php artisan test --compact --parallel
```

## Test Structure

### Unit (Domain) — `tests/Unit/Domain/`
```php
// tests/Unit/Domain/CombatienteTest.php
class CombatienteTest extends TestCase {
    public function test_recibe_danio_reduce_hp(): void {
        $combatiente = CombatienteFactory::create(['hp_actual' => 100]);
        $combatiente->recibirDanio(30);
        $this->assertEquals(70, $combatiente->getHpActual());
    }
}
```

### Feature (Use Cases) — `tests/Feature/`
```php
// tests/Feature/Batalla/IniciarBatallaTest.php
class IniciarBatallaTest extends TestCase {
    public function test_inicia_batalla_con_dos_equipos(): void {
        $equipo1 = EquipoFactory::create()->createPokemon(3);
        $equipo2 = EquipoFactory::create()->createPokemon(3);
        
        $response = $this->postJson('/api/batallas', [
            'equipo_id_1' => $equipo1->id,
            'equipo_id_2' => $equipo2->id,
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'estado', 'turno_actual']);
    }
}
```

### Browser (E2E) — `tests/Browser/` (Dusk)
```php
// tests/Browser/EvolucionTest.php
class EvolucionTest extends DuskTestCase {
    public function test_usuario_puede_evolucionar_pokemon(): void {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(UserFactory::create())
                ->visit('/equipos/1/pokemon/5')
                ->click('@evolucionar-btn')
                ->waitForText('¡Felicidades! Tu Pokémon ha evolucionado')
                ->assertSee('Charizard');
        });
    }
}
```

## Factories

```php
// database/factories/CombatienteFactory.php
class CombatienteFactory extends Factory {
    protected $model = Combatiente::class;
    public function definition(): array {
        return [
            'pokemon_id' => Pokemon::factory(),
            'hp_actual' => fake()->numberBetween(1, 200),
            'hp_maximo' => fake()->numberBetween(200, 300),
            'estado' => EstadoPokemon::NONE,
        ];
    }
    public function conEstado(EstadoPokemon $estado): static {
        return $this->state(fn() => ['estado' => $estado]);
    }
}
```

## Assertions Comunes

```php
$this->assertEquals($expected, $actual);
$this->assertSame($expected, $actual); // strict
$this->assertTrue($condition);
$this->assertFalse($condition);
$this->assertNull($value);
$this->assertNotNull($value);
$this->assertInstanceOf(ClassName::class, $object);
$this->assertCount($count, $array);
$this->assertArrayHasKey($key, $array);
$this->assertStringContainsString($needle, $haystack);
$this->assertJson($jsonString);
$this->assertJsonStructure($structure, $json);
```

## TDD Flow (Obligatorio para Coder)

1. **Rojo** — Escribir test que falle
2. **Verde** — Código mínimo para pasar
3. **Refactor** — Limpiar sin romper tests

```bash
# Ciclo rápido
php artisan test --compact --filter=test_nombre_metodo
# codear...
php artisan test --compact --filter=test_nombre_metodo
```

## Mocking (Mockery)

```php
$mock = Mockery::mock(UserRepositoryInterface::class);
$mock->shouldReceive('save')
    ->once()
    ->with(Mockery::type(User::class))
    ->andReturn(true);

$this->app->instance(UserRepositoryInterface::class, $mock);
```

## Data Providers

```php
public static function casosDanio(): array {
    return [
        'danio_normal' => [100, 30, 70],
        'danio_muerte' => [50, 60, 0],
        'danio_cero' => [100, 0, 100],
    ];
}

#[DataProvider('casosDanio')]
public function test_danio_varios(int $hpInicial, int $danio, int $hpEsperado): void {
    // ...
}
```