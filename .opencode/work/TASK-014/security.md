# TASK-014 — Seguridad

- **Estado:** No aplica
- **Motivo:** el desarrollador indicó explícitamente «Sin reviewer ni security» para esta tarea (2026-11-04).
- **Controles revisados informalmente durante la implementación:** endpoint bajo `auth` + CSRF; `user_id` desde `Guard`; transacciones/locks por nido; sin entrada de usuario en el POST; assets servidos desde rutas estáticas.
- **Riesgo asumido:** no se realizó auditoría de seguridad independiente. Queda registrado en el informe final.
