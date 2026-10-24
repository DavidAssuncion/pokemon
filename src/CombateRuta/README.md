# CombateRuta

Combate de ruta: el jugador se enfrenta a 3 pokémon salvajes elegidos del pool del
hábitat y nivel. Reutiliza el equipo del jugador y el motor de `Battle`.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `App/IniciarCombateRuta.php` | Caso de uso de inicio: pool, rivales, batalla |
| `App/GeneradorEquipoRuta.php` | Selección ponderada con reemplazo por slot |
| `App/OtorgarRecompensasRuta.php` | Exp, caramelos y capturas tras la victoria |
| `App/RegistrarResultadoRuta.php` | Persistencia del resultado |
| `App/Concerns/` | Helpers compartidos del módulo |
| `Domain/DataTransferObjects/ResultadoRuta.php` | Resultado tipado del combate |
| `Infra/Controllers/CombateRutaController.php` | Endpoints `/api/ruta…` |

## Flujo

```
POST /api/ruta/{habitat}/combatir
  → IniciarCombateRuta
      → GeneradorEquipoRuta::generar()   // DatosPokemonBatallaCollection
      → ConstruirEquipoJugador::desdeEquipo(Team, FormacionEquipo)
      → CreadorBatallaSesion::crearYGuardar(...)
```

`GET /api/ruta/{habitat}/rivales?nivel=N` devuelve el preview de rivales (JSON).

## Reglas

- 3 rivales por combate, con reemplazo (pueden repetirse).
- Peso = `capture_rate / hatch`; `capture_rate <= 0` excluye la especie.
- Posición calculada por `ClasificadorOfensivaDefensiva`.
- `ResultadoRuta` usa `ItemModalCollection` (caramelos) y `RecompensaCapturaCollection`
  (capturas); `aArray()` es la frontera snake_case del modal.

## Tests

`tests/Feature/CombateRuta/` (generador, iniciar combate, recompensas, presentador).

## Dependencias

`Battle`, `CombateEntrenadores` (mapeador y equipo jugador), `Exploraciones`
(pool ponderado y recompensas), `Shared`.
