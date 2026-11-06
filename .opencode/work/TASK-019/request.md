# TASK-019 — Solicitud

## Petición literal

> revisa front y back en busca de bugs o correcciones de codigo, queremos que sea mantenible, este bien estructurado, codigo legible... documenta los modulos, elimina codigo innecesario y propon mejoras para el MVP

## Interpretación inicial

1. **Auditoría funcional y técnica** de backend (`src/`, `app/`) y frontend (`resources/views`, `resources/js`, `resources/css`) para localizar bugs, inconsistencias de contrato, duplicación, deuda de mantenibilidad y código muerto.
2. **Documentación de módulos**: completar/actualizar los README de módulo y el índice `docs/modulos/README.md`, incluyendo los módulos sin README (`Forja`, `Granjas`) y corrigiendo enlaces o contenido desfasado.
3. **Eliminación de código innecesario**: código muerto, wrappers `@deprecated`, ramas inalcanzables y duplicaciones, siempre dentro de un plan aprobado y con verificación estática.
4. **Propuestas de mejora para el MVP**: priorizadas por impacto/esfuerzo, separando bugs, mantenibilidad, rendimiento, UX y documentación.

## Contexto del proyecto

- Proyecto: `/home/david/pokemon` — Laravel 12 / PHP 8.4.
- Frontend: Blade + Alpine.js + Tailwind CSS; componentes Livewire en `app/Livewire`.
- Backend: módulos en `src/` (Battle, CombateEntrenadores, CombateRuta, Equipos, Exploraciones, Forja, Gimnasios, Granjas, Habitats, Huevos, Mazmorras, Pokemon, Reclutamiento, Shared) + infraestructura Laravel en `app/`.
- Calidad: PHPStan nivel 6 (`phpstan.neon`, baseline), Pint, deptrac, PHPCS/PHPMD configurados.
- Documentación existente: `docs/architecture.md`, `docs/conventions.md`, `docs/modulos/README.md` y README por módulo; `docs/adr/` con ADR 0001–0003.
- Estado Git: rama `task-011`, 52 archivos rastreados modificados y trabajo nuevo sin commit (módulo `Forja`, ampliaciones de `Granjas`, sistema de ítems, ADR y migraciones). Hay cambios locales del desarrollador que no deben sobrescribirse.

## Restricciones conocidas

- No crear, modificar ni ejecutar tests (`tests/` fuera de alcance).
- No hacer `git push`.
- No sobrescribir cambios locales preexistentes.
- No modificar dependencias sin autorización.
- No tocar datos de producción ni secretos.
- Toda implementación requiere plan aprobado explícitamente.

## Decisiones confirmadas por el desarrollador (2026-11-05)

1. **Entrega:** solo auditoría e informe. No se implementan correcciones, limpieza ni cambios de documentación.
2. **Base:** commitear el working tree antes de auditar. Se creó el checkpoint `0317179` con 244 archivos y no se hizo `git push`.
3. **Prioridad:** equilibrada — bugs → mantenibilidad → documentación → limpieza de código.
4. **Alcance:** backend, frontend, documentación de módulos y seguridad, con propuesta de mejoras para el MVP.
5. **Fuera de alcance:** tests, cambios de dependencias, `git push` y cualquier implementación sin aprobación.
