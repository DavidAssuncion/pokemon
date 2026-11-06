# TASK-020 — Solicitud

## Petición literal

> 1-2-3 no preocupan. Las SESSION_VERSION pueden desaparecer, no habra desfases, no importan, src/cruds desaparecio por desuso, se borra, livewire tambien, el bug de dropdown no aplica, se puede ocultar sin problemas o seleccionar. Centrate en la deuda estructural, divide en componentes, encapsula codigo y que sea facilmente testeable y usable, principios SOLID, clean code

## Aclaraciones del desarrollador (2026-11-05)

1. **Livewire se queda.** La mención «livewire también» era un lapsus: se refería a **Filament**.
2. **Filament no está instalado** en el proyecto: no aparece en `composer.json`, `composer.lock` ni en código. El equivalente real es el CRUD propio (`app/Crud/Base`, comando `crud:generate` y ruta `/cruds`), que se propone eliminar por desuso junto con `src/Crud` (inexistente).
3. **Prioridad:** backend de dominio primero (`Combatiente`, `AgregadoBatalla`, `ServicioEjecucionBatalla`, handlers de Exploraciones); después frontend y limpieza.
4. **Testabilidad:** diseñar puertos, funciones puras y seams testeables, pero **no crear ni ejecutar tests** (regla vigente del proyecto).
5. **Descartado:** autorización admin, concurrencia, rate limiting, `SESSION_VERSION`, dropdown y cualquier trabajo de seguridad.

## Restricciones

- No crear, modificar ni ejecutar tests (`tests/` fuera de alcance).
- No `git push`.
- No cambiar dependencias sin autorización.
- No implementar sin plan aprobado.
