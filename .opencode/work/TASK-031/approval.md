# TASK-031 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-031
- **Versión del plan:** v1
- **Texto literal de la aprobación:** «Aprobar plan v1»
- **Fecha:** 2026-11-07
- **Alcance autorizado:**
  - F1: dominio base (9 curas + 25 buffs).
  - F2: dominio estados y crítico (Alivio, Foco Energía, Aguzar).
  - F3: IA B0 (decisión única y autoobjetivo).
  - F4: IA B1 (generación y evaluación mínima de soporte con compuerta conservadora).
  - F5: verificación estática y smoke manual.
  - F6: revisión independiente y auditoría de seguridad.
  - F7: cierre, informe final y commit sin `git push`.
- **Excluido:** Purificar, Bálsamo Osado, Bastión Final, Acupresión, `STATS_ALIADO`/equipo, prioridades con objetivo aliado, cambios de datos, migraciones, `SESSION_VERSION`, Blade, JS y tests.
- **Condiciones:** sin `git push`; sin tests; sin modificar `app/Livewire/Combate.php`; sin romper modo propio/rival; sin sobrescribir cambios ajenos.
