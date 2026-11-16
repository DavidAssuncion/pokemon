# TASK-047 — Seguridad

No aplica: el cambio es un seeder de datos internos. No introduce endpoints,
entrada de usuario, autenticación, autorización, SQL dinámico, secretos ni
dependencias nuevas. No se ha ejecutado auditoría de seguridad.

Nota: el hallazgo colateral del análisis QoL sobre el botón "Admin - Gestion"
(`resources/views/habitats/partials/columna-izquierda.blade.php:101-112`) sigue
pendiente de verificación en una tarea aparte; no forma parte de este cambio.
