# TASK-053 — Petición

- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** implementación de fase 1 (sprites de entrenador) + cambio pequeño de dominio (nombre de líder).
- **Estado:** RECEIVED → ANALYZING

## Texto literal de las decisiones

> Privado, aceptamos arte, lideres con nombre propio, canonico, etapas 1-3 generico, clases fijas pero con sentido a los pokemon del habitat, 40px

## Decisiones registradas

1. **Distribución:** proyecto privado; el riesgo de licencia se acepta y se documenta.
2. **Arte:** se acepta el arte de fans/Showdown con atribución.
3. **Líderes de gimnasio:** tendrán nombre propio y canónico.
4. **Etapas 1-3 de gimnasio:** entrenadores genéricos, sin nombre propio.
5. **Hábitats/rutas:** una clase fija por hábitat, elegida por coherencia con los Pokémon del hábitat.
6. **Tamaño:** sprites a 40 px CSS (nítidos, 80×80 reales en HiDPI).

## Alcance de la fase 1

- Gimnasios: nombre canónico del líder y sprites en las 4 etapas (1-3 genéricas, 4 líder).
- Entrenadores de hábitat: sprite de clase fija por hábitat.
- Rival de ruta: sprite de la clase fija del hábitat.
- Assets: subset curado en `public/images/trainers/`.
- Manifiesto de mapeo y componente Blade reutilizable.
- Atribución en `THIRD_PARTY_NOTICES.md` y metadatos del manifiesto.

## Fuera de alcance

- Facciones Magma/Aqua/Rocket y equipos rivales (dominio nuevo; fase posterior).
- Sprites de entrenador dentro de la pantalla de combate (se puede evaluar después).
- Cambios de balance, combate, recompensas o datos.
- Tests.

## Restricciones

- No implementar sin plan aprobado.
- No tocar `resources/views/gimnasios/show.blade.php` hasta que TASK-050 esté cerrada.
- No tocar los cambios locales de TASK-049, TASK-050 ni TASK-051.
- No leer `.env` ni secretos.
- No crear, modificar ni ejecutar tests.
- Sin dependencias nuevas.

## Ajuste v2 (2026-11-17)

> Ponlos a 80x80, lo permiten tanto en gym como en rutas

- Subir los sprites de entrenador de 40×40 a 80×80 en gimnasios y rutas.
- Mantener el botón lateral «Entrenadores» a 40 px para no romper la navegación.
- Actualizar el manifiesto (`tamano_render_px`) y el valor por defecto del componente.
