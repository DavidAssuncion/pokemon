#!/usr/bin/env bash
set -e

# Rol del contenedor: app | worker | scheduler | horizon
ROLE=${CONTAINER_ROLE:-app}

# Crear directorios de escritura necesarios en runtime (el bind-mount de ./storage
# oculta los creados en build-time, y en hosts como Windows el storage/ no los trae).
mkdir -p /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache || true

# Crear .env si no existe (el .env está excluido del build por .dockerignore;
# la mayoría de valores se inyectan por environment, pero key:generate lo necesita).
if [ ! -f /var/www/html/.env ]; then
    echo "[entrypoint] Creando .env desde .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# App key: se genera automáticamente si no se provee una (las sesiones/livewire lo requieren)
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] Generando APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

# Asegurar permisos de escritura en storage y bootstrap/cache para el usuario de Apache (www-data)
# necesario porque storage/ suele ser un bind-mount del host con otro uid/gid.
php artisan storage:link --no-interaction >/dev/null 2>&1 || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Espera a que la base de datos esté lista antes de arrancar
WAIT_FOR_DB=${WAIT_FOR_DB:-true}

if [[ "$WAIT_FOR_DB" == "true" ]]; then
    echo "[entrypoint] Esperando a PostgreSQL en ${DB_HOST:-db}:${DB_PORT:-5432}..."
    until php -r "
        \$c = @pg_connect('host=${DB_HOST:-db} port=${DB_PORT:-5432} dbname=${DB_DATABASE:-pokemon} user=${DB_USERNAME:-pokemon} password=${DB_PASSWORD:-secret}');
        exit(\$c ? 0 : 1);
    "; do
        sleep 2
    done
    echo "[entrypoint] PostgreSQL disponible."
fi

# Gestión de migraciones (solo en el rol app, controlado por RUN_MIGRATIONS)
if [[ "$ROLE" == "app" && "${RUN_MIGRATIONS:-false}" == "true" ]]; then
    echo "[entrypoint] Ejecutando migraciones..."
    php artisan migrate --force --no-interaction

    if [[ "${RUN_SEEDERS:-false}" == "true" ]]; then
        echo "[entrypoint] Actualizando datos de catálogo (idempotente)..."
        php artisan db:seed --class=CatalogoSeeder --force --no-interaction

        if php -r "
            \$pdo = new PDO('pgsql:host=${DB_HOST:-db};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-pokemon}', '${DB_USERNAME:-pokemon}', '${DB_PASSWORD:-secret}');
            \$users = (int) \$pdo->query('SELECT count(*) FROM users')->fetchColumn();
            exit(\$users === 0 ? 0 : 1);
        " >/dev/null 2>&1; then
            echo "[entrypoint] Instalación limpia, ejecutando datos de jugador..."
            php artisan db:seed --class=ReclutadosSeeder --force --no-interaction
        else
            echo "[entrypoint] Datos de jugador ya presentes (omitidos)."
        fi
    fi
else
    # Worker/scheduler: esperar a que la tabla "users" exista (migraciones del rol app terminadas)
    # porque AppServiceProvider::boot() consulta User::first() y falla si la tabla no existe.
    if [[ "$ROLE" != "app" ]]; then
        echo "[entrypoint] Esperando a que las migraciones se apliquen (tabla 'users')..."
        until php -r "
            \$pdo = @new PDO('pgsql:host=${DB_HOST:-db};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-pokemon}', '${DB_USERNAME:-pokemon}', '${DB_PASSWORD:-secret}');
            \$exists = \$pdo->query(\"SELECT to_regclass('public.users') IS NOT NULL AS ok\")->fetchColumn();
            exit(\$exists ? 0 : 1);
        " 2>/dev/null; do
            sleep 3
        done
        echo "[entrypoint] Migraciones listas."
    fi
fi

# Optimizaciones de producción
if [[ "${APP_ENV:-production}" == "production" ]]; then
    echo "[entrypoint] Optimizando (config/cache/route/event views)..."
    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan event:cache --no-interaction || true
    php artisan view:cache --no-interaction || true
fi

umask 002

case "$ROLE" in
    app)
        echo "[entrypoint] Arrancando Apache (rol: app)..."
        exec "$@"
        ;;
    worker)
        echo "[entrypoint] Arrancando queue:work (rol: worker)..."
        exec php artisan queue:work --tries=1 --timeout=0
        ;;
    scheduler)
        echo "[entrypoint] Arrancando schedule:work (rol: scheduler)..."
        exec php artisan schedule:work
        ;;
    *)
        echo "[entrypoint] Rol desconocido: $ROLE"
        exit 1
        ;;
esac
