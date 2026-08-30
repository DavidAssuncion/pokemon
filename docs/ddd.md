# Convención DDD para módulos en Laravel

> Documento canónico de la convención de arquitectura por módulos del proyecto Pokémon Battle
> Game. Adoptada en debate de equipo el 2026-08-30. Este documento sustituye al borrador raíz
> `laravel_ddd_architecture.md` (absorbido aquí) y es la fuente de verdad para la estructura de
> los módulos `src/{{Modulo}}/`.

## Visión general

**El acoplamiento a Laravel está aceptado y abrazado, pero ordenado.** La regla de dependencias
es: la capa `Domain` de cada módulo es **pura** — sin Eloquent, sin HTTP, sin facades, sin
`Request` — mientras que las capas `App` e `Infra` **usan Laravel libremente**.

Esta regla **sustituye** a la antigua "los módulos `src/` no dependen de Laravel". Ya no se
esconde el framework: conviven dominio puro e infraestructura Laravel dentro del mismo módulo,
cada una en su capa.

- **Domain puro**: sin `Illuminate\Http\Request`, sin facades, sin Eloquent, sin dependencias
  HTTP ni de otros módulos. Solo PHP + clases del propio módulo + `Src\Shared` (colección base,
  bus, excepciones genéricas).
- **App/Infra con Laravel libre**: Eloquent, FormRequests, controladores HTTP, rutas, jobs,
  container.
- **`src/Shared` sigue sin depender de módulos** (infraestructura transversal compartida: bus,
  colección base, exceptions, tipos).

## Estructura de módulo

**Todo vive en `src/{{Modulo}}/`** (opción fuerte: controllers, modelos Eloquent, Livewire,
rutas, repos y factories también pertenecen al módulo):

```
src/{{Modulo}}/
├── Domain/
│   ├── Entities/            # entidades con identidad SIEMPRE (int $id NO nullable), props public, toArray()
│   ├── ValueObjects/        # value objects (si aplica)
│   ├── Collections/         # colecciones que extienden Src\Shared\Domain\Collection
│   ├── DataTransferObjects/ # DTOs de datos (creación/actualización/filtros) Y DTOs de presentación
│   ├── Exceptions/          # excepciones específicas del módulo
│   └── Repositories/        # interfaces de repositorio (contrato en español)
├── App/                     # casos de uso / comandos vía Src\Shared\Bus o servicios de aplicación
└── Infra/
    ├── Repositories/        # implementaciones Eloquent de las interfaces
    ├── Models/              # modelos Eloquent (solo relaciones y scopes, sin lógica de dominio)
    ├── Factories/           # factorías de mapeo modelo → entidad (patrón desdeArray)
    ├── Requests/            # FormRequests (uno por rol)
    ├── Controllers/         # controladores HTTP
    ├── Livewire/            # componentes Livewire (si aplica)
    └── routes.php           # rutas del módulo, IMPORTADAS desde routes/web.php (el "genérico")
```

La antigua capa `Presentation/` **desaparece**: sus DTOs pasan a `Domain/DataTransferObjects`.
El mapeo `src/{{Modulo}}/Infra/routes.php` se hace con un `require` en
`routes/web.php`; los ficheros existentes bajo `routes/` se conservan y migran gradualmente.

## Entidades

- Identidad **SIEMPRE** con `int $id` **no nullable**.
- Propiedades **`public`**.
- Método **`toArray(): array`**.
- **Sin** métodos mágicos `__get`/`__set`.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\Entities;

final class Usuario
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }

    /** @return array{id: int, name: string, email: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
```

Regla práctica: **para crear vamos por el DTO del request; el repositorio devuelve el id tras la
creación**. Nunca una entidad con id nullable ni con "id pendiente de asignar".

## Colecciones

Las colecciones de `src/` extienden `Src\Shared\Domain\Collection` (**prohibido** extender
`Illuminate\Support\Collection`), se tipan con `public string $type = Entidad::class` y definen
`toArray()` propio cuando aplique:

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\Collections;

use Src\Shared\Domain\Collection;
use Src\Usuario\Domain\Entities\Usuario;

final class UsuarioColeccion extends Collection
{
    public string $type = Usuario::class;

    public function toArray(): array
    {
        return array_map(fn (Usuario $usuario): array => $usuario->toArray(), $this->items);
    }
}
```

(Mismo patrón que `HabitatsCollection`/`HabitatEntity` en el proyecto real.)

## DTOs en Domain

Viven en `Domain/DataTransferObjects`, son **inmutables** con `public readonly` y cubren dos
roles:

1. **DTOs de datos** — entrada/salida de operaciones (creación, actualización, filtros).
2. **DTOs de presentación** — heredan el rol de la antigua capa `Presentation/`.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\DataTransferObjects;

final class CrearUsuarioData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/** Bulk de DTOs de creación (sin ids: el repositorio los asigna). */
final class CrearUsuarioColeccion extends Collection
{
    public string $type = CrearUsuarioData::class;
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\DataTransferObjects;

final class UsuarioDetalleResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $activo,
    ) {
    }
}
```

## Excepciones de dominio

Genéricas en `src/Shared/Domain/Exceptions/`:

| Clase | Extiende | HTTP | Uso |
|---|---|---|---|
| `DominioExcepcion` | `Exception` (abstract) | 400 (base) | marcador base: el error viene del dominio |
| `RecursoNoExiste` | `DominioExcepcion` | 404 | recurso con id inexistente (factory `para`) |
| `ViolacionReglaNegocio` | `DominioExcepcion` | 422 | regla de negocio rota |
| `PermisoDenegado` | `DominioExcepcion` | 403 | acceso denegado (opcional) |

```php
<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

use Exception;

abstract class DominioExcepcion extends Exception
{
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

final class RecursoNoExiste extends DominioExcepcion
{
    public static function para(string $recurso, int $id): self
    {
        return new self("No existe el {$recurso} con id {$id}.");
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

final class ViolacionReglaNegocio extends DominioExcepcion
{
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

final class PermisoDenegado extends DominioExcepcion
{
}
```

Excepciones por módulo que extienden las genéricas y fijan el literal:

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\RecursoNoExiste;

final class UsuarioNoExiste extends RecursoNoExiste
{
    public function __construct()
    {
        parent::__construct('El usuario no existe.');
    }
}
```

### Registro en bootstrap/app.php (mapa HTTP)

`withExceptions` + `renderable` **en orden**: PRIMERO las específicas de módulo, luego las
genéricas de la más específica a la base, y al final `DominioExcepcion` (400). Laravel usa la
**primera** callback que devuelva respuesta para la clase lanzada; como una específica también
es una genérica (`UsuarioNoExiste` es `RecursoNoExiste`), el orden importa.

```php
->withExceptions(function (Exceptions $exceptions): void {
    // 1) Específicas de módulo — SIEMPRE antes que las genéricas.
    $exceptions->renderable(function (UsuarioNoExiste $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 404);
    });

    // 2) Genéricas, de la más específica a la base.
    $exceptions->renderable(function (RecursoNoExiste $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 404);
    });
    $exceptions->renderable(function (ViolacionReglaNegocio $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 422);
    });
    $exceptions->renderable(function (PermisoDenegado $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 403);
    });
    $exceptions->renderable(function (DominioExcepcion $e, Request $request) {
        return response()->json(['error' => $e->getMessage()], 400);
    });
})
```

Respuesta JSON siempre `['error' => $e->getMessage()]` con su status.

## Contrato de repositorio

Interfaces en español en `Domain/Repositories/`. **No existe `getCollection` genérico**: los
listados paginados/filtrados son responsabilidad de Datagrid (`app/Datagrid`), que se mantiene.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Domain\Repositories;

use Src\Usuario\Domain\Collections\UsuarioColeccion;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioColeccion;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioData;
use Src\Usuario\Domain\Entities\Usuario;

interface UsuarioRepositoryInterface
{
    /** NO devuelve null: si no existe lanza UsuarioNoExiste. */
    public function obtenerPorId(int $id): Usuario;

    /** Recibe DTO de creación SIN id; devuelve la entidad CON id (el repo lo asigna). */
    public function insertar(CrearUsuarioData $datos): Usuario;

    /** Bulk de DTOs de creación, sin ids. */
    public function insertarColeccion(CrearUsuarioColeccion $datos): void;

    /** Exige id no-null. */
    public function actualizar(Usuario $usuario): Usuario;

    /** Exige id en TODOS los miembros (matcher por id). */
    public function upsertColeccion(UsuarioColeccion $coleccion): void;

    /** Primero recupera (404 si no existe) y luego borra. */
    public function eliminar(int $id): void;
}
```

### Implementación Eloquent (Infra)

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Infra\Repositories;

use Src\Usuario\Domain\Collections\UsuarioColeccion;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioColeccion;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioData;
use Src\Usuario\Domain\Entities\Usuario;
use Src\Usuario\Domain\Exceptions\UsuarioNoExiste;
use Src\Usuario\Domain\Repositories\UsuarioRepositoryInterface;
use Src\Usuario\Infra\Factories\UsuarioFactory;
use Src\Usuario\Infra\Models\Usuario as UsuarioModel;

final class EloquentUsuarioRepository implements UsuarioRepositoryInterface
{
    public function obtenerPorId(int $id): Usuario
    {
        return UsuarioFactory::desdeArray($this->obtenerModelo($id)->toArray());
    }

    public function insertar(CrearUsuarioData $datos): Usuario
    {
        $modelo = UsuarioModel::query()->create([
            'name' => $datos->name,
            'email' => $datos->email,
        ]);

        return UsuarioFactory::desdeArray($modelo->toArray());
    }

    public function insertarColeccion(CrearUsuarioColeccion $datos): void
    {
        foreach ($datos as $crearUsuarioData) {
            $this->insertar($crearUsuarioData);
        }
    }

    public function actualizar(Usuario $usuario): Usuario
    {
        $modelo = $this->obtenerModelo($usuario->id);

        $modelo->update([
            'name' => $usuario->name,
            'email' => $usuario->email,
        ]);
        $modelo->refresh();

        return UsuarioFactory::desdeArray($modelo->toArray());
    }

    public function upsertColeccion(UsuarioColeccion $coleccion): void
    {
        $filas = array_map(
            fn (Usuario $usuario): array => $usuario->toArray(),
            [...$coleccion],
        );

        UsuarioModel::query()->upsert($filas, ['id'], ['name', 'email']);
    }

    public function eliminar(int $id): void
    {
        // obtenerModelo lanza UsuarioNoExiste (404) si el id no existe; luego se borra.
        $this->obtenerModelo($id)->delete();
    }

    /** @throws UsuarioNoExiste */
    private function obtenerModelo(int $id): UsuarioModel
    {
        $modelo = UsuarioModel::query()->find($id);

        if ($modelo === null) {
            throw new UsuarioNoExiste();
        }

        return $modelo;
    }
}
```

### Factoría de mapeo modelo → entidad

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Infra\Factories;

use Src\Usuario\Domain\Entities\Usuario;

final class UsuarioFactory
{
    /** @param  array{id: int, name: string, email: string}  $datos */
    public static function desdeArray(array $datos): Usuario
    {
        return new Usuario(
            id: (int) $datos['id'],
            name: (string) $datos['name'],
            email: (string) $datos['email'],
        );
    }
}
```

### Registro de la interfaz (composition root)

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(UsuarioRepositoryInterface::class, EloquentUsuarioRepository::class);
}
```

## FormRequests y controladores

FormRequests en `Infra/Requests/`, **separados por rol** (`UsuarioSaveRequest` y
`UsuarioIndexRequest`) con reglas adecuadas a cada uno: `required` en save,
`nullable`/`sometimes` en index.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioData;

final class UsuarioSaveRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function aDatosCreacion(): CrearUsuarioData
    {
        return new CrearUsuarioData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
        );
    }
}
```

Controladores en `Infra/Controllers/`, **sin try-catch**: delegan en el handler global de
`bootstrap/app.php`. **IDs por parámetro de URL, NUNCA por body — salvo POST.** Prohibido
`array_merge(validated, ['id' => ...])`: en `update` se construye la entidad pasándole el id
(int, del path) y los campos del FormRequest.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\Infra\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Src\Usuario\Domain\Entities\Usuario;
use Src\Usuario\Domain\Repositories\UsuarioRepositoryInterface;
use Src\Usuario\Infra\Requests\UsuarioSaveRequest;

final class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
    ) {
    }

    public function store(UsuarioSaveRequest $request): JsonResponse
    {
        $usuario = $this->usuarios->insertar($request->aDatosCreacion());

        return response()->json($usuario->toArray(), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->usuarios->obtenerPorId($id)->toArray());
    }

    public function update(UsuarioSaveRequest $request, int $id): JsonResponse
    {
        $usuario = new Usuario(
            id: $id,
            name: (string) $request->validated('name'),
            email: (string) $request->validated('email'),
        );

        return response()->json($this->usuarios->actualizar($usuario)->toArray());
    }

    public function destroy(int $id): JsonResponse
    {
        $this->usuarios->eliminar($id);

        return response()->json(['success' => true]);
    }
}
```

En `update`/`destroy` el id viene del path (nunca del body); en `store` el id no se recibe (lo
asigna el repositorio). `UsuarioNoExiste`/`RecursoNoExiste` se propagan al handler global, que
responde 404 sin try-catch en el controlador.

## Capa App con Bus / UnitOfWork

Para **CRUD simple** se puede llamar al repositorio directamente (ver controlador). El
`Src\Shared\Bus` (`CommandBus`/`Command`/`CommandHandler` + `UnitOfWork`) es para **flujos
complejos**: transacciones, eventos, trabajo tras commit — patrón ya usado en Exploraciones.

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\App;

use Src\Shared\Bus\Command;
use Src\Usuario\Domain\DataTransferObjects\CrearUsuarioColeccion;

final class ImportarUsuariosCommand implements Command
{
    public function __construct(
        public readonly CrearUsuarioColeccion $usuarios,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Src\Usuario\App;

use LogicException;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;
use Src\Usuario\Domain\Repositories\UsuarioRepositoryInterface;

final class ImportarUsuariosHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UsuarioRepositoryInterface $usuarios,
    ) {
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof ImportarUsuariosCommand) {
            throw new LogicException('ImportarUsuariosHandler requires an ImportarUsuariosCommand.');
        }

        return $this->unitOfWork->transaction(function () use ($command): mixed {
            $this->usuarios->insertarColeccion($command->usuarios);

            $this->unitOfWork->afterCommit(function (): void {
                // Tras un commit con éxito: notificar, invalidar cache, despachar jobs...
            });

            return true;
        });
    }
}
```

La implementación concreta de `UnitOfWork` es `App\Bus\DatabaseUnitOfWork` (DB::transaction +
DB::afterCommit).

## Rutas por módulo

Cada módulo define sus rutas en `src/{{Modulo}}/Infra/routes.php` y el "genérico"
`routes/web.php` las importa con un `require`. Las URLs son kebab-case inglés (convención
existente, se mantiene).

```php
<?php

declare(strict_types=1);

// src/Usuario/Infra/routes.php
use Illuminate\Support\Facades\Route;
use Src\Usuario\Infra\Controllers\UsuarioController;

Route::get('/api/users/{id}', [UsuarioController::class, 'show']);
Route::post('/api/users', [UsuarioController::class, 'store'])->name('usuarios.store');
Route::put('/api/users/{id}', [UsuarioController::class, 'update']);
Route::delete('/api/users/{id}', [UsuarioController::class, 'destroy']);
```

```php
<?php

declare(strict_types=1);

// routes/web.php (extracto — el "genérico")
Route::middleware('auth')->group(function (): void {
    // ...

    require __DIR__.'/../src/Usuario/Infra/routes.php';
});
```

## Lo que se mantiene

La convención **no sustituye** estos subsistemas; se mantienen explícitamente:

- **Testing**: PHPUnit con `tests/Unit/` y `tests/Feature/`.
- **Datagrid** (`app/Datagrid`): consultas JSON de solo lectura con whitelist por modelo.
  Responsable de los listados paginados/filtrados (por eso el contrato de repositorio no tiene
  `getCollection`).
- **Transacciones/UnitOfWork**: `app/Bus/DatabaseUnitOfWork` (implementación de
  `Src\Shared\Bus\UnitOfWork`).
- **Iconos WebP**: `app/Support/WebpConverter` + comando `iconos:optimize-webp`.
- **Frontend**: Livewire 3 + Alpine + Tailwind 4.

## Infraestructura transversal

`Datagrid`, `app/Support`/`app/Console` de iconos y `src/Shared` permanecen como infraestructura
compartida (en `app/` y `src/Shared/`) hasta decidir su módulo anfitrión.

## Migración

La estructura definida aquí es el **destino documentado**. El código actual —que hoy usa
`Presentation/`, métodos en inglés en alguna interfaz, entidades sin `toArray` uniforme,
controllers en `app/Http/Controllers` y rutas en `routes/`— se migra **por módulo, cuando se
toque** (estrategia strangler). No es un refactor masivo inmediato, y este documento no afirma
que ningún módulo esté ya migrado.