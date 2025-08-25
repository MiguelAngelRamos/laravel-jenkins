# Guía paso a paso de Dockerfile.ci (orientado a CI)

Objetivo: explicar línea a línea qué hace este Dockerfile y por qué está diseñado para Integración Continua (CI).


## ¿Por qué un Dockerfile “CI”?

- Reproducibilidad: la misma imagen se usa en cada build del pipeline (ver Jenkinsfile), evitando “en mi máquina funciona”.
- Minimalista y efímera: provee lo justo para instalar dependencias, ejecutar Artisan, correr tests y servir la API de forma temporal.
- Separación de entornos: no es la imagen de producción (no FPM/Nginx), ni la de desarrollo interactivo. Es un “runner” de CI.
- Consistencia con Jenkinsfile: el pipeline construye esta imagen y luego la usa para preparar Laravel, levantar el servidor y ejecutar tests (PHPUnit/Postman).


## El Dockerfile, línea a línea

```dockerfile
FROM php:8.3-cli
```
- Base oficial de PHP 8.3 en modo CLI (Command-Line Interface).
- Elección consciente para CI: ejecuta comandos (composer, artisan, phpunit) y puede levantar `artisan serve` para smoke tests.
- Diferente a producción (usualmente php-fpm + Nginx/Apache) y a dev (con herramientas extra).

```dockerfile
ARG DEBIAN_FRONTEND=noninteractive
```
- Evita prompts interactivos durante `apt-get` (útil en entornos automatizados como CI).

```dockerfile
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    git unzip ca-certificates curl pkg-config \
    libzip-dev libsqlite3-dev libonig-dev \
 && docker-php-ext-install pdo_sqlite \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip mbstring \
 && rm -rf /var/lib/apt/lists/*
```
- `apt-get update`: refresca índices de paquetes.
- `apt-get install -y --no-install-recommends`: instala solo lo esencial para reducir el tamaño de la imagen.
  - git: Composer descarga repos con git si hace falta.
  - unzip: Composer extrae paquetes zip.
  - ca-certificates: certificados TLS para llamadas HTTPS (Composer, curl).
  - curl: útil para diagnósticos/healthchecks (aunque en CI el Jenkinsfile usa un contenedor específico de curl).
  - pkg-config: ayuda a compilar/extender algunas extensiones.
  - libzip-dev: headers para compilar la extensión zip.
  - libsqlite3-dev: headers para compilar pdo_sqlite.
  - libonig-dev: legado histórico para mbstring; hoy muchos builds no lo requieren, pero no estorba si está disponible.
- `docker-php-ext-install pdo_sqlite`: habilita PDO SQLite en PHP (necesario si la BD en CI es SQLite).
- `docker-php-ext-configure zip && docker-php-ext-install zip mbstring`: configura y compila extensiones zip y mbstring.
  - zip: requerido por Composer y a veces por librerías que manejan archivos.
  - mbstring: soporte de cadenas multibyte (común en apps con UTF-8).
- `rm -rf /var/lib/apt/lists/*`: limpia capas apt para reducir la imagen.

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```
- Copia el binario oficial de Composer v2 desde la imagen `composer:2` (multistage copy).
- Ventaja: no instala paquetes extra; usa el binario oficial ya construido y mantenido.

```dockerfile
WORKDIR /app
```
- Define `/app` como directorio de trabajo para comandos posteriores (en el pipeline se monta el repositorio aquí).


## Cómo encaja con el Jenkinsfile

- Stage “Build CI image & network”: construye esta imagen con `--no-cache` para garantizar limpieza.
- Stage “Prep Laravel”: ejecuta un contenedor de esta imagen para:
  - Generar `.env.ci` y apuntar a `database/database.sqlite`.
  - Composer install, `php artisan key:generate`, limpiar caches, migrar y seed.
- Stage “Levantar API”: corre `php artisan serve` dentro de esta imagen, escucha en 0.0.0.0:8000 y hace healthcheck `/api/ping`.
- Stage “Postman (Newman)”: otra imagen (newman + htmlextra) se conecta por red Docker a `laravel-api` y ejecuta la colección.


## Buenas prácticas aplicadas

- Imagen base oficial, actualizada a PHP 8.3.
- `--no-install-recommends` y limpieza de listas apt para reducir tamaño.
- Extensiones PHP solo las necesarias (pdo_sqlite, zip, mbstring) para este proyecto/CI.
- Composer traído por multistage copy, sin dependencias extra.
- El usuario real del agente Jenkins se inyecta en tiempo de ejecución (con `--user`) desde el pipeline; así evitamos archivos root en el workspace sin acoplar el Dockerfile a un UID fijo.


## Posibles extensiones (según necesidades)

- Cobertura de código: añadir Xdebug y habilitarlo en CI para `--coverage-clover` (costo: imagen más pesada y ejecución más lenta).
- Bases distintas a SQLite: instalar `pdo_mysql`/`pdo_pgsql` si el pipeline usa MySQL/PostgreSQL en contenedor.
- Node/Vite: si el CI necesitara build de frontend, añadir Node o usar un contenedor separado para el build.
- Timezone/locale: instalar `tzdata` y configurar zona horaria si los tests dependen de ello.


## Diagnóstico rápido de fallos comunes

- “pdo_sqlite no encontrado”: confirmar que `libsqlite3-dev` está instalado y que `docker-php-ext-install pdo_sqlite` no falló.
- “Composer SSL error”: asegurar `ca-certificates` presentes; revisar reloj del contenedor.
- “Falla zip/mbstring”: revisar que las librerías dev estén y que no haya conflictos de versiones.


## Resumen

Este Dockerfile.ci crea una imagen ligera y determinista para CI: suficiente para instalar dependencias PHP, habilitar extensiones claves (SQLite, zip, mbstring), ejecutar Artisan y correr la app en modo servidor simple durante los tests. Su foco es la automatización limpia y predecible dentro del pipeline, no reemplaza imágenes de desarrollo o producción.

