# TASK-052 — Informe final

## Resumen

Se evaluó la viabilidad de usar los 572 sprites de entrenador de
`josewp808/pokemon-trainer-sprites` en rutas, gimnasios y futuros equipos rivales
(Magma/Aqua/Team Rocket). La tarea fue **solo análisis**: no se modificó código,
vistas, assets ni datos.

## Estado final

**COMPLETED (solo análisis).** No aplica revisión de código ni auditoría de seguridad
de la aplicación: no hubo cambios.

## Veredicto por caso de uso

| Caso de uso | Veredicto | Qué falta |
|---|---|---|
| Gimnasios (etapas y líder) | **Viable con cambios** | El dominio no tiene identidad de líder; hoy solo existe `Entrenador 1/2/3` y `Líder`. El mapeo es una decisión de producto. |
| Entrenadores de hábitat | **Viable con cambios (solo presentación)** | Asignar sprite por `(habitat, nivel, índice)`; no requiere dominio nuevo. |
| Rival de ruta | **Viable con cambios** | El rival es un snapshot de equipo sin identidad; hay que decidir una clase por hábitat. |
| Facciones (Magma/Aqua/Rocket) | **No listo** | No existe ningún concepto de facción, rango, progresión ni combate asociado. La brecha es de dominio completo. |

## Evidencia principal

- **Procedencia:** 570 de 572 PNG son byte a byte idénticos a los sprites de
  Pokémon Showdown (537 con el mismo nombre y 33 con alias). Los 2 restantes son
  revisiones antiguas de archivos que existen upstream. No hay arte de otra fuente.
- **Licencia:** el repo externo no tiene `LICENSE` ni `README` ni atribución; por
  defecto son todos los derechos reservados. El arte es IP de Nintendo/Game
  Freak/Creatures y Showdown añade arte de fans con créditos que este repo elimina.
  Copiarlo es un **riesgo alto** si el proyecto se publica. No es un dictamen legal.
- **Calidad:** 572 PNG de 80×80, 441 KB totales, sin duplicados, 571 indexados con
  transparencia y 1 RGBA. A 40 px se ven nítidos; a 64 px el re-muestreo no es entero
  y conviene `image-rendering: pixelated`.
- **Gimnasios:** el juego define 18 gimnasios por tipo, sin nombre de líder. Los 8
  líderes de Kanto y los candidatos de los 18 tipos existen como PNG. El orden del
  juego no es regional, así que cualquier mapeo mezcla regiones.
- **Rutas/hábitats:** 56 hábitats y entrenadores identificados solo por índice; el
  rival de ruta no tiene nombre ni clase. Hay clases genéricas suficientes; falta
  `fisher` y `aquagrunt`.
- **Facciones:** hay sprites de `archie`, `maxie`, `giovanni`, grunts y admins, pero
  cero dominio de facción en `src/`, `app/`, `routes/` y `database/`.

## Recomendación

**Adoptar con condiciones, en dos tiempos:**

1. **Fase 1 (~1–1,5 días, solo presentación):** subset curado de 30–45 PNG en
   `public/images/trainers/`, manifiesto JSON, componente `<x-trainer-sprite>` con
   fallback, y aplicación en gimnasios, entrenadores de hábitat y rutas. Sin tocar
   dominio ni contratos.
2. **Fase 3 (aplazar):** facciones/rivales. Requiere entidades, progresión, rutas,
   combates y recompensas: **2–4 semanas** de dominio y contenido, no solo imágenes.

No adoptar solo si el proyecto se publica como open source o comercialmente; en ese
caso, arte propio o placeholders.

## Decisiones pendientes

1. ¿El proyecto será privado o público? Determina el riesgo real de licencia.
2. ¿Se acepta arte de fans/Showdown con atribución? ¿Dónde se atribuye?
3. ¿Los líderes de gimnasio tendrán nombre propio o solo un sprite mudo sobre "Líder"?
4. ¿Mapeo canónico por tipo o nombres inventados?
5. ¿Etapas 1–3 con clases genéricas del tipo (recomendado) o tres líderes por gimnasio?
6. ¿Clase fija por hábitat (56 entradas) o un único sprite genérico de ruta?
7. ¿Tamaño visual de 40 px (nítido) o 64 px (pixelated)?

## Archivos

- `.opencode/work/TASK-052/analysis.md` — análisis completo del `analyst`.
- `.opencode/work/TASK-052/execution.md` — preflight y verificación.
- `.opencode/work/TASK-052/request.md` — petición y alcance.

No se modificó ningún archivo de la aplicación. Los cambios locales de TASK-049,
TASK-050 y TASK-051 quedaron intactos.

## Verificación

- Lectura estática y comprobaciones de solo lectura (hashes, dimensiones, chunks PNG,
  `curl` a URLs públicas).
- Sin tests.
- Sin cambios en `public/`, `src/`, `app/`, `resources/`, rutas ni datos.

## Riesgos residuales

- **Licencia (alto):** arte sin licencia y sin créditos; requiere decisión explícita.
- **Escalado (medio):** 80×80 a 64 px no es divisible; usar 40/80 px o `pixelated`.
- **Semántica (medio):** los gimnasios no tienen líder canónico; el mapeo será una
  mezcla regional o inventada.
- **Mantenimiento (medio):** el repo externo es un volcado de 2 commits sin issues ni
  versiones; conviene congelar un subset y registrar el hash de origen.
- **Facciones:** `aquagrunt.png` falta en el clon y el dominio de facciones no existe.

## Próximos pasos

1. Responder a las 7 decisiones pendientes.
2. Si se aprueba la fase 1, abrir una tarea nueva con plan aprobado y alcance de solo
   presentación.
3. Dejar las facciones para una tarea de dominio independiente.
