# Copilot instrucciones del proyecto

Propósito
- Proporcionar instrucciones persistentes que el asistente debe seguir al trabajar en este repositorio.

Lenguaje y tono
- Usar español por defecto para comunicaciones y nombres de use-cases.
- Ser conciso, directo y colaborador: explicar cambios brevemente, y proponer pasos siguientes.

Estructura y convenciones
- Proyecto híbrido: `app/` para integraciones Laravel tradicionales y `src/` para módulos DDD/Hexagonal.
- Use-cases en `src/*/App` deben ser puros y dependientes de interfaces del Domain.
- Infra implementa las interfaces de Domain y utiliza Eloquent o queries cuando haga falta.
- Nombres: Use-cases `ObtenerXPorY`, repositorios `XRepository`, entidades `XEntity`.

Persistencia y seeders
- Preferir modelos Eloquent y métodos como `updateOrCreate`/`firstOrCreate`.
- Seeders deben ser idempotentes y documentar CSVs en `storage/data/`.

Front-end y assets
- CSS base en `public/css/app.css`.

Tests y calidad
- Escribir tests unitarios para use-cases y tests funcionales para flujos importantes.
- Mantener PSR-12 en PHP. Evitar cambios masivos de formato en PRs pequeños.

Prácticas operativas
- Pequeños commits y PRs enfocados. Explicar el porqué de los cambios.
- Antes de migraciones destructivas, sugerir `php artisan migrate:status` y un plan de rollback.

Memoria y contexto
- Usar `/memories/repo/context.md` como fuente canonical de decisiones y convenciones del proyecto.

Acciones automáticas preferidas por el asistente
- Realizar cambios mínimos y verificables por cada petición.
- Ejecutar comprobaciones sintácticas (`php -l`) después de editar PHP.
- Instar al usuario a correr compilación de assets y migraciones en su entorno cuando sea necesario.

Cómo solicitar cambios
- Indicar módulo y objetivo (p.ej. "Modificar PokemonSeeder para incluir X").
- Si se requieren comandos remotos o instalación de dependencias preguntar antes de ejecutar.

Generado: 2026-05-27
