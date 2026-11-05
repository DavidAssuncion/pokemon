# TASK-014 — Solicitud

## Petición literal

> Quitamos del menu el circulo de ?, en su lugar, ponemos un `<i class="fa-solid fa-seedling"></i>`, al pulsarlo, se cosecharan todos los habitats y nidos. Por lo que ya no es necesario mandar a explorar para recoger ni ir uno por uno. Las bayas plantadas afectan a lo explorado aumentando el porcentaje de obtencion de estas. en public\images\items tienes las imagenes, hay que corregir nombres y pasarlo a webp para optimizar los tamaños. Eter sera el recipiente

## Interpretación

1. **Cosecha global desde el menú:**
   - Sustituir el botón de ayuda `?` del layout por un botón con `<i class="fa-solid fa-seedling"></i>`.
   - Al pulsarlo, cosechar todas las plantaciones listas del usuario en todos sus hábitats y nidos.
   - Mostrar el resultado (total de bayas cosechadas) y permitir reintentar.
   - Eliminar el popup de ayuda asociado al `?`.
2. **Bonus de plantaciones en exploración:**
   - Confirmar y, si hace falta, ajustar que las bayas plantadas aumentan la probabilidad de obtener esas bayas en las exploraciones.
   - El comportamiento actual de F3 aplica un factor de peso por planta (`1 + 0.20 * plantas`, tope ×2.0); revisar si cumple y exponerlo en la UI si procede.
3. **Assets de items:**
   - En `public/images/items` hay 16 PNG con nombres tipo `Poción_(Dream_World).png`.
   - Corregir nombres a slugs limpios y convertir a WebP optimizado.
   - Mapear los iconos a `ClavePocion`, `ClaveMejora` y `ClaveMaterial`.
   - `Éter` será el recipiente (`material:recipiente`), tanto en nombre visible como en icono.
   - Reductores y resistencias no tienen imágenes propias en esa carpeta; conservan iconos de baya/tipo.

## Contexto

- Proyecto `/home/david/pokemon`, Laravel 12 / PHP 8.4 / Alpine.
- TASK-012 implementó plantación/cosecha, forja y objetos de combate.
- TASK-013 implementó el catálogo de 36 bayas, el modal de selección y las tabs por familia.
- `public/images/items` contiene 16 PNG (112 KB) con nombres en español y sufijo `(Dream_World)`.
- No hay Font Awesome cargado actualmente; el layout usa SVGs y texto.
- `cwebp` está disponible en el sistema.
- El botón `?` vive en `resources/views/layouts/app.blade.php` con un popup de ayuda basado en `config('ayuda.secciones')`.

## Restricciones

- No tests; `tests/` fuera de alcance.
- No commit/push ni despliegue.
- No modificar bases de datos de producción.
- No instalar dependencias sin autorización; si se añade Font Awesome, debe estar en el plan aprobado.
- La cosecha global debe ser atómica por plantación y no duplicar stock.
- No romper la cosecha individual, el modal de bayas, la forja ni el combate.
- No eliminar assets originales sin que el plan lo autorice.

## Criterio de cierre

Botón de cosecha global operativo, bonus de plantaciones verificado/ajustado, iconos de items convertidos a WebP y mapeados, verificación estática, revisión, seguridad e informe final. Sin tests.
