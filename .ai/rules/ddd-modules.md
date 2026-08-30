# Convención de módulo DDD

Glob: `src/**`

Convención de módulo (canónica: `docs/ddd.md`):

- **Todo vive en `src/{{Modulo}}/`** con capas `Domain/`, `App/`, `Infra/` (controllers, modelos Eloquent, FormRequests, rutas y repos incluidos en el módulo).
- **`Domain/` es PURO**: sin Eloquent, sin HTTP, sin facades, sin `Request`. `App/` e `Infra/` usan Laravel libremente.
- **Español en TODO `src/`** (clases, métodos, propiedades, mensajes). Tablas/columnas en inglés; URLs kebab-case inglés.
- **Entidades**: `int $id` no nullable, propiedades `public`, `toArray(): array`. Sin `__get`/`__set`. El id lo asigna el repositorio al crear (el DTO de creación va sin id).
- **Colecciones**: extienden `Src\Shared\Domain\Collection` con `public string $type = Entidad::class` (NUNCA `Illuminate\Support\Collection`).
- **DTOs** en `Domain/DataTransferObjects`, inmutables con `public readonly` (datos y presentación; la capa `Presentation/` desaparece como destino).
- **Excepciones de dominio**: genéricas en `src/Shared/Domain/Exceptions/` (`DominioExcepcion` 400 base, `RecursoNoExiste` 404, `ViolacionReglaNegocio` 422, `PermisoDenegado` 403) + específicas por módulo; mapa HTTP en `bootstrap/app.php` con las específicas PRIMERO.
- **`src/Shared` no depende de módulos** (infraestructura transversal compartida).