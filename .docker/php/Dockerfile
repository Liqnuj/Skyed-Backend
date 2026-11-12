# .docker/php/Dockerfile
#
# Dockerfile para Desarrollo Local - API Laravel 13 (Con PostgreSQL)
# ------------------------------------------------------------------
# Optimizado para una arquitectura API pura (Sin Node.js).
# Incluye parches de seguridad del sistema, soporte para PostgreSQL 18 (libpq-dev)
# y soporte gráfico completo para generación de reportes en PDF (DomPDF) y Excel.

# Usamos la imagen oficial de PHP 8.4 FPM (Puedes cambiar a 8.5-fpm si lo requieres)
FROM php:8.4-fpm

# --- 1. Argumentos de Permisos ---
#
# Argumentos para el ID de usuario (UID) y grupo (GID).
# Estos valores (por defecto 1000) coinciden con tu usuario del host en Linux/WSL2
# para evitar problemas de permisos en archivos creados por Artisan o Composer.
ARG UID=1000
ARG GID=1000

# Establece el directorio de trabajo principal dentro del contenedor.
WORKDIR /var/www/html

# --- 2. Instalación de Dependencias del Sistema y Parches de Seguridad ---
#
# Se incluye 'apt-get upgrade -y' para aplicar los últimos parches de seguridad de Debian.
# Instalamos las bibliotecas necesarias para compilar las extensiones de PHP.
RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    nano \
    # ¡CRÍTICO PARA POSTGRESQL! Librería de desarrollo de Postgres necesaria para pdo_pgsql
    libpq-dev \
    # Librerías para zip, imágenes (DomPDF/Excel), textos multibyte, XML e internacionalización
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libssl-dev \
    # Limpiar la caché de apt para mantener la imagen ligera.
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- 3. Configuración e Instalación de Extensiones de PHP ---
#
# 1. Configuramos 'gd' con soporte para FreeType (fuentes) y JPEG (Requerido por DomPDF/Excel).
# 2. Instalamos 'pdo_pgsql' y 'pgsql' en lugar de MySQL para conectar con PostgreSQL 18.
# 3. 'opcache' guarda el código PHP ya compilado en memoria (mucho más rápido en Docker sobre Windows).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        mbstring \
        exif \
        pcntl \
        bcmath \
        xml \
        intl \
        opcache

# Instalación de la extensión de Redis mediante PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# --- 4. Instalación de Composer ---
#
# Instala Composer globalmente copiando el binario desde su imagen oficial.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- 5. Configuración de PHP y FPM ---
#
# Copia nuestra configuración personalizada de PHP (memory_limit, upload_max_filesize, etc.)
COPY ./.docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# *** CORRECCIÓN CRUCIAL DE FPM ***
# Reemplazamos el usuario por defecto 'www-data' por nuestro usuario 'laravel'
# para que PHP-FPM tenga los mismos permisos que nuestro usuario en el host.
RUN sed -i "s/user = www-data/user = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/group = www-data/group = laravel/" /usr/local/etc/php-fpm.d/www.conf

# --- 6. Creación del Usuario de Desarrollo ---
#
# Crea el grupo y usuario 'laravel' con los UID/GID del host.
RUN groupadd -g $GID laravel && useradd -u$UID -g laravel -m laravel

# La carpeta vendor vive en un volumen de Docker (ver docker-compose.yml), no en
# la carpeta de Windows: así Laravel carga mucho más rápido. Se crea aquí con
# dueño 'laravel' para que Composer pueda escribir en ella (evita el "Error 1:
# Permisos" del anexo de la guía).
RUN mkdir -p /var/www/html/vendor && chown -R laravel:laravel /var/www/html

# --- 7. Cambio de Usuario ---
#
# Todas las operaciones subsiguientes (Composer, Artisan, PHP-FPM) se ejecutarán como 'laravel'.
USER laravel

# El CMD por defecto de la imagen base ('php-fpm') se hereda y arranca automáticamente.