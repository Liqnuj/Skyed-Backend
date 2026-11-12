# Makefile - Skyed-Backend (API Laravel + PostgreSQL 18)
#
# Atajos para no escribir "docker compose exec api php artisan ..." cada vez.
# Uso: make up, make shell, make fresh-db...  (requiere 'make': WSL/Linux/macOS,
# o en Windows: winget install GnuWin32.Make)
# ¡OJO! Las líneas de cada comando van con TAB, no con espacios.

.PHONY: network up stop down rebuild install setup shell psql tinker migrate fresh-cache fresh-db test pint logs ps

# Crea la red compartida con el frontend (solo la primera vez)
network:
	docker network create skyed_net || true

# Inicia todos los contenedores del backend en segundo plano
up:
	docker compose up -d

# Detiene los contenedores liberando RAM (sin borrarlos)
stop:
	docker compose stop

# Detiene y elimina los contenedores (conserva los datos de Postgres)
down:
	docker compose down

# Reconstruye las imágenes desde cero y reinicia
rebuild:
	docker compose down
	docker compose up -d --build

# Instala las dependencias de PHP dentro del contenedor
install:
	docker compose exec api composer install

# Primer arranque completo (Paso 5 de la guía)
setup:
	docker compose up -d --build
	docker compose exec api composer install
	docker compose exec api php artisan key:generate
	docker compose exec api php artisan migrate --seed --force
	docker compose exec api php artisan optimize:clear

# Entra a la terminal bash del contenedor PHP (como usuario laravel)
shell:
	docker compose exec api bash

# Entra a la consola SQL de PostgreSQL 18 (usa las credenciales del .env)
psql:
	docker compose exec db sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

# Ejecuta Laravel Tinker
tinker:
	docker compose exec api php artisan tinker

# Ejecuta las migraciones pendientes (cuando un compañero sube migraciones nuevas)
migrate:
	docker compose exec api php artisan migrate

# Limpia todas las cachés de Laravel (config, rutas, vistas, caché de Redis)
fresh-cache:
	docker compose exec api php artisan optimize:clear

# Reinicia la base de datos desde cero con los Seeders (¡borra los datos!)
fresh-db:
	docker compose exec api php artisan migrate:fresh --seed --force

# Corre las pruebas (Pest)
test:
	docker compose exec api php artisan test

# Corre el formateador de código oficial (Laravel Pint)
pint:
	docker compose exec api ./vendor/bin/pint

# Muestra los logs de la API y Nginx en tiempo real
logs:
	docker compose logs -f api nginx_api

# Muestra el estado de los contenedores
ps:
	docker compose ps
