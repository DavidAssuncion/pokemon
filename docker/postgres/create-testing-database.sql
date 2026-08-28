SELECT 'CREATE DATABASE laravel_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'laravel_test')\gexec
