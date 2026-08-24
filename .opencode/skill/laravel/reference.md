# Laravel Skill Reference

## Commands

```bash
# Artisan
php artisan list
php artisan make:model Model -mfsr
php artisan make:controller Controller --resource
php artisan make:request Request
php artisan make:service Service
php artisan make:dto DTO
php artisan make:enum Enum
php artisan make:value-object VO
php artisan make:repository Repository
php artisan migrate
php artisan migrate:fresh --seed
php artisan test --compact --filter=TestName
php artisan test --compact tests/Feature/XTest.php
php artisan config:clear
php artisan route:list --method=GET --name=api
php artisan db:show
php artisan tinker --execute 'Code;'
```

## Key Patterns

### Models
```php
// Constructor promotion + casts()
class User extends Authenticatable {
    public function __construct(
        public readonly string $name,
        public readonly Email $email,
    ) {}
    protected function casts(): array { return ['email' => Email::class]; }
}
```

### Form Requests
```php
class StoreUserRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email' => ['required', 'email', Rule::unique(User::class)]]; }
    public function dto(): StoreUserDTO { return new StoreUserDTO($this->validated()); }
}
```

### Services (Domain)
```php
// src/Domain/Service.php - NO App\ or Illuminate\ imports
interface UserRepositoryInterface { public function save(User $user): void; }
class UserService {
    public function __construct(private UserRepositoryInterface $repo) {}
    public function register(RegisterUserDTO $dto): User { /* ... */ }
}
```

### DTOs (readonly)
```php
readonly class RegisterUserDTO {
    public function __construct(
        public Email $email,
        public Password $password,
        public ?string $referralCode = null,
    ) {}
}
```

### Enums (backed)
```php
enum UserStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BANNED = 'banned';
}
```

### Value Objects
```php
readonly class Email {
    public function __construct(public string $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email');
    }
    public function __toString(): string { return $this->value; }
}
```

## Architecture Rules (from docs/architecture.md)

- `src/` NO importa `App\` ni `Illuminate\` (excepto `src/*/Infra/`)
- Domain ↔ Infra por interfaces
- DTOs en fronteras (3+ params primitivos)
- Propiedades `private`/`readonly`, getters tipados
- Colecciones tipadas (extienden `Collection`)
- `declare(strict_types=1)` en todos los archivos
- Enums/Value Objects para primitivas cerradas

## Testing

```bash
# Unit (Domain)
php artisan test --compact tests/Unit/Domain/

# Feature (Use Cases)
php artisan test --compact tests/Feature/

# Browser (E2E)
php artisan dusk --filter=TestName
```

## Quality Tools

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --level=6 src/
vendor/bin/infection --min-msi=80
vendor/bin/deptrac analyse
```