# Contrato de repositorio

Glob: `src/**/Domain/Repositories/**`

Interfaces de repositorio en español (canónica: `docs/ddd.md`):

- `obtenerPorId(int $id): Entidad` — **NO devuelve null**; si no existe lanza la excepción del módulo (404).
- `insertar(CrearXData $datos): Entidad` — recibe DTO de creación **SIN id**; devuelve entidad **CON id** (lo asigna el repo).
- `insertarColeccion(CrearXColeccion $datos): void` — bulk de DTOs de creación, sin ids.
- `actualizar(Entidad $entidad): Entidad` — exige id no-null.
- `upsertColeccion(XColeccion $coleccion): void` — exige id en TODOS los miembros (matcher por id).
- `eliminar(int $id): void` — primero recupera (404 si no existe), luego borra.
- **No existe `getCollection` genérico**: los listados paginados/filtrados son responsabilidad de Datagrid (`app/Datagrid`).