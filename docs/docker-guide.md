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

## 5. Conectar un cliente SQL (DBeaver) a la BBDD del container

¿Quieres mirar la BBDD con un cliente gráfico (DBeaver, HeidiSQL, etc.)? Primero confirma que el container está levantado (`docker compose ps`) y usa estos datos:

| Dato | Valor |
|---|---|
| Host | `localhost` (con Docker Desktop) o la IP de WSL (ver abajo) |
| Puerto | `5432` por defecto, o el que pongas en `DB_PORT` del `.env` (ej. `5433`) |
| Database | `pokemon` |
| Usuario | `pokemon` |
| Password | `secret` |

### Preparar el `.env` (y cambiar de puerto si hay conflicto)

El `.env` no existe al clonar el proyecto; créalo copiando el ejemplo:

- **Windows:** `copy .env.example .env`
- **Linux/Mac:** `cp .env.example .env`

Si tu máquina ya tiene un PostgreSQL propio ocupando el puerto 5432, cambia el puerto del container en el `.env`:

```
DB_PORT=5433
```

Recrea el container para que el cambio se aplique:

```bash
docker compose up -d --force-recreate db
```

A partir de ahí conecta al puerto `5433` en vez de `5432`.

### Problemas típicos al conectar desde Windows

| Síntoma | Causa más probable | Solución rápida |
|---|---|---|
| `password failed` en `localhost:5432` | Un PostgreSQL nativo de Windows ocupa el 5432; DBeaver conecta a ese, no al container | Parar el servicio nativo o cambiar el puerto del container (`DB_PORT=5433`) |
| `connection refused` en `localhost` | Docker corre en WSL2 y el `localhost` de Windows no llega a WSL (solo Docker Desktop hace ese reenvío) | Usar la IP de WSL: `wsl hostname -I` |
| La IP de WSL tampoco responde | Firewall de Windows bloqueando (Windows 11 22H2+ activa el firewall "Hyper-V" por defecto) | Mirrored mode, regla de firewall o portproxy (abajo) |
| `password authentication failed` ya entrando al container | El volumen `db_data` se inicializó con otras credenciales (`POSTGRES_PASSWORD` solo aplica en la primera inicialización del volumen) | Resetear la contraseña con `ALTER USER` (abajo) |

### `password failed` en `localhost:5432` → PostgreSQL nativo ocupando el puerto

Casi siempre hay un PostgreSQL nativo de Windows escuchando en el 5432, así que DBeaver conecta a ese y no al container. Solución (elige una):

1. Parar el servicio nativo: `services.msc` → busca PostgreSQL → detener (o ponerlo en manual).
2. O cambiar el puerto del container a `5433` (ver "Preparar el `.env`" más arriba).

### `connection refused` en `localhost` → usar la IP de WSL

Con Docker en WSL2 (no Docker Desktop), el `localhost` de Windows no llega al container. Averigua la IP de WSL:

```bash
wsl hostname -I
```

Usa la primera IP que devuelva como Host en DBeaver. ⚠️ La IP cambia al reiniciar WSL o Windows: repite el comando y actualiza DBeaver.

### La IP de WSL tampoco responde → firewall de Windows

El firewall de Windows bloquea la conexión (en Windows 11 22H2+ el firewall "Hyper-V" está activo por defecto). Tres soluciones, de más simple a más compleja:

**(a) Mirrored mode** (solo Windows 11 22H2+; permite volver a usar `localhost`). Crea el archivo `%UserProfile%\.wslconfig` con:

```
[wsl2]
networkingMode=mirrored
```

Y reinicia WSL:

```bash
wsl --shutdown
```

**(b) Regla de firewall** (PowerShell como administrador). Windows 11:

```powershell
New-NetFirewallHyperVRule -Name "pokemon-pg" -DisplayName "Pokemon PG" -Direction Inbound -VMCreatorId '{40E0AC32-46A5-438A-A0B2-2B479E8F2E90}' -Protocol TCP -LocalPorts 5432
```

Windows 10:

```powershell
New-NetFirewallRule -DisplayName "WSL Postgres 5432" -Direction Inbound -Protocol TCP -LocalPort 5432 -Action Allow
```

**(c) Portproxy** (puente TCP desde Windows hacia WSL; PowerShell como administrador, sustituye `<IP_DE_WSL>`):

```powershell
netsh interface portproxy add v4tov4 listenport=5433 listenaddress=127.0.0.1 connectport=5433 connectaddress=<IP_DE_WSL>
netsh advfirewall firewall add rule name="WSL-PG" dir=in action=allow protocol=TCP localport=5433
```

Conecta en DBeaver a `127.0.0.1:5433`. Si la IP de WSL cambia al reiniciar, repite el primer comando con la IP nueva.

### `password authentication failed` ya entrando al container → resetear credenciales

El volumen `db_data` se inicializó con otras credenciales: `POSTGRES_PASSWORD` solo se aplica en la primera inicialización del volumen, no cuando el volumen ya existe. Arréglalo así:

```bash
docker exec -it pokemon_db psql -U postgres -d pokemon -c "ALTER USER pokemon WITH PASSWORD 'secret';"
```

Dentro del container el socket local es "trust", así que no pide contraseña.

### ⚠️ No borres el volumen: tus datos viven ahí

Los datos de la BBDD están en el volumen `db_data`. **NO** uses `docker compose down -v` ni `docker volume rm db_data`: borran la BBDD entera.

Backup opcional (genera `backup_pokemon.sql` en la carpeta actual):

```bash
docker exec pokemon_db pg_dump -U pokemon pokemon > backup_pokemon.sql
```

## Notas

- Windows: ejecuta git y Docker en **PowerShell** o **CMD**. El proyecto suele estar en `C:\Users\<tú>\...`.
- El listado de puertos real se ve con `docker compose ps` (columna PORTS).
- La imagen incluye el código compilado; los cambios de código requieren SIEMPRE `--build`.
- Si editas archivos de la BBDD (seeders/CSVs) en `storage/data/`, también necesitan rebuild o `docker compose exec app php artisan db:seed --force` para aplicarse.