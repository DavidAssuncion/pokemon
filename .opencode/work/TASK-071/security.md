# TASK-071 — Seguridad

- **Fecha:** 2026-11-23
- **Plan:** v3

## Controles implementados

1. **Autenticación:** todas las rutas de vista y API se cargan dentro del grupo
   `auth` de `routes/web.php`.
2. **Autorización por recurso:** el `team_id` se valida con
   `Rule::exists('teams','id')->where('user_id', Auth::id())`; el equipo se carga con
   el global scope `BelongsToUser`.
3. **Anti-IDOR:** `RegistrarResultadoHabitatTemporal` solo avanza si
   `won === true` y `userId === authUserId`; el `user_id` del meta lo fija el servidor
   al iniciar la batalla.
4. **Validación de entrada:** `team_id` requerido, `formacion` opcional con valores
   `vanguardia|retaguardia`; oleada y combate acotados por la ruta numérica y
   contrastados con el progreso actual.
5. **CSRF:** el POST de combate usa el middleware web y la vista envía
   `X-CSRF-TOKEN`.
6. **XSS:** Blade escapa las variables; el slug se pasa a Alpine con `@js()`. Los
   colores e imágenes provienen del catálogo en código, no de entrada del usuario.
7. **Path traversal:** las imágenes se resuelven desde nombres fijos del catálogo
   (`Team_Rocket.jpg`, etc.); el slug de la URL solo se usa como clave del catálogo.
8. **SQL:** consultas con Query Builder/Eloquent y bindings; no hay SQL dinámico con
   entrada del usuario.
9. **Secretos:** no se leen ni escriben `.env`, tokens ni credenciales.
10. **Dependencias:** no se añadieron paquetes.
11. **Base de datos:** solo migración aditiva; no se ejecutan rollbacks ni borrados.
12. **Minimización de datos:** el detalle solo expone la oleada y el combate actuales;
    las oleadas y entrenadores futuros no se envían. El equipo rival nunca se envía y
    el equipo del jugador solo se muestra en el modal de combate.
13. **Comparación de PC:** el endpoint `/pc` valida que el `team_id` pertenezca al
    usuario y devuelve solo agregados (PC, veredicto, leyenda IV/EV); no expone
    composición, especies, tipos ni movimientos del rival.

## Riesgos residuales

- **Sin rate limiting específico:** el endpoint de combate sigue el patrón de los
  gimnasios; no se añade un limitador nuevo.
- **Contenido provisional:** los equipos y sprites pendientes no afectan a la
  seguridad.
- **Tablas de torres locales:** quedan sin uso; no se exponen por rutas ni código.

## Conclusión

No se identifican vulnerabilidades críticas o altas en el alcance implementado.
