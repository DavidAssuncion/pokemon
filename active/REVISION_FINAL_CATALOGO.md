# Revisión final del catálogo

**Fecha:** 2026-10-24

Documento de cierre para decidir de una vez qué movimientos y habilidades entran o quedan fuera. No se ha modificado código de combate.

## Resultado

| Catálogo | Antes | Cierre | Final | Cobertura |
|---|---:|---:|---:|---:|
| Movimientos canónicos | 505 | 160 | 665 | 71.0 % de 937 |
| Habilidades canónicas | 145 | 88 | 233 | 74.2 % de 314 |

- Movimientos totales con internos y duplicado: **673**.
- Habilidades totales con homebrew: **287**.

## Implementación

- **160 movimientos** nuevos en `storage/data/moves.json` y asignaciones en `storage/data/pokemon_moves_lote9.json`.
- **88 habilidades** nuevas en `storage/data/abilities_lote3.json` (incluidas Absorbe Agua, Absorbe Electricidad, Absorbe Fuego y Piel Seca).
- Nuevos ejecutores de movimiento para protección, clima, curación, revivir, cambios de tipo/stats, trampas y utilidades.
- Nuevas clases de efecto para habilidades de entrada, contacto, clima, absorción, stats, ruinas, equipo y reacciones.
- Seeders actualizados: `AbilitySeeder`, `PokemonAbilitySeeder` y `PokemonMoveSeeder`.
- Asignaciones finales: **32.815 movimientos** (668 de 673 movimientos con al menos una especie) y **1.995 habilidades** (278 de 287; las 9 restantes son alias o filas técnicas).
- Los **160 movimientos** y las **88 habilidades** nuevos tienen al menos una especie asignada.
- Verificado: **PHPStan sin errores**, **Pint pass**, **view:cache OK** y runtime de **673 movimientos + 287 habilidades** sin errores.
- Pendiente: PHPUnit y commit (no ejecutados por indicación).

## Hallazgos

1. El catálogo de movimientos estaba subestimado: hay **505 canónicos**, no 474. La cobertura real de partida es **53,9 %**, no 50,6 %.
2. El catálogo de habilidades estaba inflado: de 199 filas, solo **145** son canónicas. La cobertura real de partida es **46,2 %**, no 53,2 %.
3. Hay **53 homebrew** de habilidades y **7 movimientos internos + 1 duplicado**; se conservan fuera del recuento PokeAPI.
4. Las familias Z, Max, G-Max, Shadow, LGPE, CAP, futuro y variantes de Poder Oculto quedan fuera.
5. Se cierra la puerta a nuevas tandas: esta lista es la definitiva.

## Enlaces

- [Movimientos](CATALOGO_FINAL_MOVIMIENTOS.md)
- [Habilidades](CATALOGO_FINAL_HABILIDADES.md)

## Lo que entra y lo que no, en una frase

- **Entra** todo lo que reutiliza el motor actual: daño, multigolpe, prioridad fija, estados soportados, estadísticas, curación, clima, campo, pantallas, trampas, OHKO, carga/recarga, recoil y drenaje.
- **No entra** nada que dependa de objetos, cambio de Pokémon, prioridad condicional, contraataque, semiinvulnerabilidad, copia/transformación, formas, Tera, Dynamax o Z.
- **Protección básica** sí entra: Protección, Detección y Aguante. Las variantes con efectos extra quedan fuera.