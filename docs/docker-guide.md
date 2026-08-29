# Guía de instalación con Docker y actualización

Guía para **instalar el juego** desde cero usando Docker y para **recibir actualizaciones** cuando salgan versiones nuevas. Pensada tanto para ti como para amigos que quieran jugar sin montar PHP/Composer/Node localmente.

## Requisitos

- **Docker Desktop** (Windows/Mac) o **Docker Engine** (Linux)
- **Git** (para descargar el código y recibir actualizaciones)

No necesitas PHP, Composer, Node ni PostgreSQL instalados: todo vive dentro de los contenedores.

## 1. Instalación desde cero

```bash
git clone git@github.com:DavidAssuncion/pokemon.git
cd pokemon
```

Crea el `.env` para configurar los puertos (opcional, si no lo creas usa los valores por defecto):

```bash
cp .env.example .env
```

Edita solo estos valores si los necesitas:

```
APP_PORT=80          # puerto de la web (si 80 está ocupado, usa 8080 y abre http://localhost:8080)
RUN_MIGRATIONS=true  # corre las migraciones al arrancar
RUN_SEEDERS=true     # carga los datos del juego al arrancar (solo si la BBDD está vacía)
```

Levanta el stack por primera vez:

```bash
docker compose up -d --build
```

Esto:

1. Construye la imagen (`pokemon-app:latest`) con PHP 8.4 + Apache + el código compilado
2. Levanta 4 contenedores:
   - `pokemon_db` → PostgreSQL 18 (la BBDD, guardada en un volumen persistente)
   - `pokemon_app` → la web (Apache + PHP)
   - `pokemon_worker` → cola de trabajos (`queue:work`)
   - `pokemon_scheduler` → tareas programadas (`schedule:work`)
3. Espera a que la BBDD esté sana, corre **migraciones** y, si la BBDD está vacía, los **seeders** (datos del juego: 1350 pokemons, habitats, usuarios)

Entra en la web:

```
http://localhost          (o http://localhost:8080 si cambiaste APP_PORT)
```

Rutas habituales: `/pokedex`, `/habitats`.

## 2. Recibir actualizaciones

Cuando saques una versión nueva (o un amigo quiera actualizarse), el flujo es siempre el mismo:

```bash
git pull
docker compose up -d --build
```

- **`git pull`** → descarga el código nuevo (migraciones, features, arreglos)
- **`docker compose up -d --build`** → reconstruye la imagen con el código nuevo y recrea los contenedores
  - `--build` es **obligatorio**: sin él Docker reutilizaría la imagen vieja y el juego no cambiaría

### Qué pasa con tus datos (la BBDD)

**No se pierde nada.** La BBDD está en el volumen `db_data`, que persiste entre actualizaciones. Al arrancar:

- Se aplican automáticamente las **migraciones nuevas** (no tocan datos existentes)
- Los **seeders** solo corren si la BBDD está vacía, así que no duplican datos en cada update

### Borrar y volver a instalar todo

Si alguna vez quieres empezar de cero (también borra la BBDD):

```bash
docker compose down -v --rmi all
```

- `down` → para y borra los contenedores
- `-v` → borra el volumen de la BBDD
- `--rmi all` → borra las imágenes

> ⚠️ `-v` borra la BBDD. Úsalo solo si quieres resetearlo todo.

Para limpiar también cachés e imágenes huérfanas de Docker:

```bash
docker system prune -a --volumes
```

## 3. Comandos útiles

| Comando | Para qué |
|---|---|
| `docker compose ps` | Ver el estado de los contenedores y sus puertos |
| `docker compose logs -f app` | Ver los logs de la web en vivo |
| `docker compose exec app php artisan db:seed --force` | Ejecutar los seeders manualmente (idempotente, no duplica) |
| `docker compose exec app php artisan migrate` | Ejecutar migraciones manualmente |
| `docker compose exec app php artisan tinker` | Consola de Laravel dentro del contenedor |
| `docker compose restart app` | Reiniciar solo la web |
| `docker compose down` | Parar todo (conserva la BBDD) |

## 4. Solución de problemas

### "La BBDD del mundo: no puedo entrar a `http://localhost`"

Otro servicio (XAMPP, IIS, otro contenedor) ocupa el puerto 80. Cambia `APP_PORT` en el `.env` a otro puerto libre (ej. `8080`) y haz `docker compose up -d`.

### No veo los cambios tras un `git pull`

Te falta reconstruir la imagen. Usa `docker compose up -d --build`, no `docker compose up -d` a secas.

### La web da error 500 con `MissingAppKeyException`

Borra el `.env` del contenedor y reintenta (el entrypoint regenera la key automáticamente):

```bash
docker compose exec app rm -f .env
docker compose restart app
```

### Mismos nombres de contenedor en conflicto ("already in use")

Si hay contenedores viejos de un proyecto anterior con los mismos nombres:

```bash
docker ps -a --filter name=pokemon_   # verlos
docker rm -f pokemon_db pokemon_app pokemon_worker pokemon_scheduler
```

### El puerto 5432 (PostgreSQL) conflicto

Si tu máquina ya tiene un PostgreSQL local usando el puerto 5432, cambia el puerto expuesto en el `.env`:

```
DB_PORT=5433
```

## Notas

- Windows: ejecuta git y Docker en **PowerShell** o **CMD**. El proyecto suele estar en `C:\Users\<tú>\...`.
- El listado de puertos real se ve con `docker compose ps` (columna PORTS).
- La imagen incluye el código compilado; los cambios de código requieren SIEMPRE `--build`.
- Si editas archivos de la BBDD (seeders/CSVs) en `storage/data/`, también necesitan rebuild o `docker compose exec app php artisan db:seed --force` para aplicarse.