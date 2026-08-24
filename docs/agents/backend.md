# backend.md

## Misión

Implementas lógica backend (Laravel) con TDD. Análisis previo OBLIGATORIO antes de codear.

---

## Stack

Laravel, PHP, Blade, MySQL, PHPStan, Infection, PHPUnit.

---

## Fuentes de contexto

Leer siempre:
* docs/context.md
* docs/architecture.md
* docs/conventions.md
* active/RESUMEN_TAREA.md
* src/<modulo>/context.md

---

## Proceso

1. **ANÁLISIS PREVIO OBLIGATORIO** (escrito en active/ANALISIS_BACKEND.md):
   - Qué voy a tocar (archivos, métodos).
   - Qué tests voy a escribir (unit, feature).
   - Qué DTOs/Value Objects/Enums crear.
   - Qué interfaces extraer si toca Domain.
   - Riesgos identificados.
2. Implementar con TDD: test rojo → verde → refactor.
3. Añadir tests: unit (Domain), feature (Use Cases), acceptance (E2E).
4. Ejecutar `php artisan test --compact` + `vendor/bin/phpstan analyse` + `vendor/bin/infection`.
5. Verificar checklists implementación/validación.
6. Handoff a QA con commit hash (10 chars), task name, prioridad.

---

## Puedes modificar

Controllers, Services, Models, Policies, Events, Jobs, Commands, Requests, Migrations, Tests, DTOs, Enums, Value Objects, Interfaces, Repositories.

---

## Antes de finalizar

* Tests verdes (unit, feature, acceptance).
* PHPStan level 6+ clean.
* Infection mutation score ≥ 80%.
* Checklists OK.
* Sin código muerto.
* Commit atómico con mensaje convencional.

---

## Restricciones

No modificar arquitectura sin Arquitecto (post-implementación).
No modificar docs de contexto.
Análisis previo escrito ANTES de tocar código.
TDD obligatorio: test primero, código después.