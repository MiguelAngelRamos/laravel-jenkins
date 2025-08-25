# Jenkinsfile explicado (CI/CD para Biblioteca API)

Objetivo: documentar, línea a línea y en lenguaje simple, cómo funciona este pipeline de Jenkins que construye, prepara, levanta, prueba y publica reportes de la API Laravel dentro de contenedores Docker.


## Estructura general del pipeline

```groovy
pipeline {
  agent any

  options {
    timestamps()
    disableConcurrentBuilds()
    timeout(time: 30, unit: 'MINUTES')
  }

  environment {
    APP_PORT   = '8000'
    DOCKER_NET = 'laravel_ci'
    SERVICE    = 'laravel-api'
    CI_IMAGE   = 'laravel-ci:latest'
    BASE_URL   = "http://laravel-api:${APP_PORT}/api"
  }

  stages {
    stage('Checkout') { ... }
    stage('Build CI image & network') { ... }
    stage('Prep Laravel (dentro del contenedor)') { ... }
    stage('Levantar API (contenedor)') { ... }
    stage('(Opcional) Hotfix Postman') { ... }
    stage('Postman (Newman en contenedor)') { ... }
  }

  post {
    always { ... }
  }
}
```

- pipeline { … }: bloque raíz del pipeline declarativo de Jenkins.
- agent any: ejecuta en cualquier agente disponible.
- options:
  - timestamps(): agrega marcas de tiempo a los logs.
  - disableConcurrentBuilds(): evita builds simultáneos de este job.
  - timeout(30 min): aborta el build si excede 30 minutos.
- environment: variables de entorno globales para reusar en stages.
  - APP_PORT: puerto donde servirá Laravel dentro del contenedor (expuesto a host).
  - DOCKER_NET: nombre de la red Docker para comunicar contenedores por nombre.
  - SERVICE: nombre del contenedor que ejecuta la API (hostname dentro de la red).
  - CI_IMAGE: nombre de la imagen Docker que contiene PHP, Composer, Node, etc.
  - BASE_URL: URL base para que Newman apunte al contenedor por nombre y puerto.


## Stage: Checkout

```groovy
stage('Checkout') {
  steps {
    deleteDir()
    checkout scm
  }
}
```
- deleteDir(): limpia el workspace para evitar residuos de builds anteriores.
- checkout scm: descarga el código del repositorio configurado en el job.


## Stage: Build CI image & network

```groovy
stage('Build CI image & network') {
  steps {
    sh '''
      set -e
      docker network create ${DOCKER_NET} || true
      docker build --no-cache -t ${CI_IMAGE} -f Dockerfile.ci .
    '''
  }
}
```
- set -e: hace que el script falle si un comando falla.
- docker network create … || true: crea la red; si ya existe, no falla.
- docker build --no-cache -t ${CI_IMAGE} -f Dockerfile.ci .: construye una imagen limpia (sin cache) desde Dockerfile.ci y la etiqueta como CI_IMAGE.


## Stage: Prep Laravel (dentro del contenedor)

Este stage no arranca la API aún; prepara dependencias, .env y base de datos dentro de un contenedor efímero.

```groovy
stage('Prep Laravel (dentro del contenedor)') {
  steps {
    sh '''
      set -e

      UID_GID="$(id -u):$(id -g)"

      docker run --rm \
        --user "$UID_GID" \
        -e COMPOSER_HOME=/tmp/composer-home \
        -e COMPOSER_CACHE_DIR=/tmp/composer-home/cache \
        -e XDG_CACHE_HOME=/tmp/.cache \
        -v "$PWD":/app -w /app ${CI_IMAGE} bash -lc '
          set -e

          # .env.ci robusto
          if [ -f .env ]; then
            cp .env .env.ci
          elif [ -f .env.example ]; then
            cp .env.example .env.ci
          else
            cat > .env.ci <<EOF
APP_ENV=ci
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
DB_CONNECTION=sqlite
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
EOF
          fi

          DBFILE=/app/database/database.sqlite
          mkdir -p /app/database
          : > "$DBFILE"

          grep -q "^DB_CONNECTION=" .env.ci && sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/" .env.ci || echo "DB_CONNECTION=sqlite" >> .env.ci
          grep -q "^DB_DATABASE=" .env.ci   && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DBFILE|" .env.ci || echo "DB_DATABASE=$DBFILE" >> .env.ci
          grep -q "^DB_FOREIGN_KEYS=" .env.ci || echo "DB_FOREIGN_KEYS=true" >> .env.ci

          cp .env.ci .env

          # Dependencias PHP y caches de framework
          composer install --no-interaction --prefer-dist --no-progress

          php artisan key:generate

          # Limpia cachés para asegurar estado limpio
          php artisan optimize:clear

          php artisan config:cache
          php artisan route:cache || true

          php artisan migrate --force
          php artisan db:seed --force
        '
    '''
  }
}
```
- UID_GID: obtiene el uid:gid del usuario del agente Jenkins para mapear permisos dentro del contenedor (evita crear archivos root en el workspace).
- docker run …: ejecuta un contenedor temporal con la imagen CI_IMAGE, montando el repo en /app.
- Variables de entorno de Composer: redirigen cachés a /tmp para no ensuciar el workspace.
- .env.ci: 
  - Copia .env si existe; si no, .env.example; si ninguno, genera uno mínimo.
  - Ajusta DB_CONNECTION=sqlite y DB_DATABASE=path absoluto al archivo sqlite del repo.
  - DB_FOREIGN_KEYS=true: asegura llaves foráneas en SQLite.
  - Copia .env.ci -> .env para que Artisan lo use.
- DBFILE y : > "$DBFILE": asegura que el archivo SQLite exista y esté vacío (truncate) para un estado reproducible.
- composer install: instala dependencias PHP.
- php artisan key:generate: genera APP_KEY requerido por Laravel.
- php artisan optimize:clear: limpia caches (config, route, view, events).
- php artisan config:cache y route:cache: recompilan caches (route:cache tolera fallar si no hay rutas cerrables).
- php artisan migrate --force y db:seed --force: migraciones y seeders en modo no interactivo.


## Stage: Levantar API (contenedor)

Arranca la API en segundo plano y espera que el endpoint de salud responda.

```groovy
stage('Levantar API (contenedor)') {
  steps {
    sh '''
      set -e
      docker rm -f ${SERVICE} || true

      UID_GID="$(id -u):$(id -g)"

      docker run -d --rm --name ${SERVICE} \
        --user "$UID_GID" \
        --network ${DOCKER_NET} \
        -v "$PWD":/app -w /app \
        -p ${APP_PORT}:8000 \
        ${CI_IMAGE} bash -lc "php -d opcache.enable=0 artisan serve --host=0.0.0.0 --port=8000"

      echo "[CI] Esperando a ${SERVICE}:8000/api/ping ..."
      for i in $(seq 1 40); do
        docker run --rm --network ${DOCKER_NET} curlimages/curl:8.8.0 \
          -fsS http://${SERVICE}:8000/api/ping && exit 0 || true
        sleep 1
      done

      echo "La API no respondió a tiempo. Logs del contenedor:"
      docker logs ${SERVICE} || true
      exit 1
    '''
  }
}
```
- docker rm -f ${SERVICE} || true: limpia un contenedor previo si quedó vivo.
- docker run -d --rm --name ${SERVICE}: arranca el servidor de Laravel en background
  - --network ${DOCKER_NET}: lo conecta a la red para que otros contenedores lo resuelvan por nombre.
  - -p ${APP_PORT}:8000: expone el puerto 8000 del contenedor hacia el host en APP_PORT.
  - php -d opcache.enable=0 artisan serve …: desactiva OPcache para evitar servir código cacheado en CI.
- Espera activa del healthcheck:
  - Usa una imagen de curl en la misma red para llamar http://laravel-api:8000/api/ping.
  - Intenta 40 veces (1 vez/segundo). Si responde, sale con éxito. Si no, muestra logs y falla.

Requisito: debe existir una ruta GET /api/ping en tu proyecto (p. ej. en routes/api.php) que responda 200.


## Stage: (Opcional) Hotfix Postman

```groovy
stage('(Opcional) Hotfix Postman') {
  when { expression { return fileExists('tests/postman/APIREST-BIBLIOTECA.postman_collection.json') } }
  steps {
    sh '''
      sed -i 's/pm\\.reponse\\.json()/pm.response.json()/g' tests/postman/APIREST-BIBLIOTECA.postman_collection.json || true
    '''
  }
}
```
- when fileExists(...): sólo corre si existe la colección de Postman.
- sed -i …: corrige un typo común `pm.reponse.json()` -> `pm.response.json()` dentro de la colección.


## Stage: Postman (Newman en contenedor)

```groovy
stage('Postman (Newman en contenedor)') {
  steps {
    sh '''
      set -e
      rm -rf newman && mkdir -p newman

      docker run --rm --network ${DOCKER_NET} \
        -v "$PWD/tests/postman":/etc/newman \
        -v "$PWD/newman":/etc/newman/newman \
        dannydainton/htmlextra run /etc/newman/APIREST-BIBLIOTECA.postman_collection.json \
          --env-var base_url=${BASE_URL} \
          --reporters cli,junit,htmlextra \
          --reporter-junit-export /etc/newman/newman/results.xml \
          --reporter-htmlextra-export /etc/newman/newman/report.html \
          --timeout-request 10000 --delay-request 50
    '''
  }
}
```
- Prepara carpeta newman/ para guardar reportes.
- Ejecuta la imagen `dannydainton/htmlextra` que trae Newman + reporte HTML.
- Monta `tests/postman` (colección) y `newman` (salidas) dentro del contenedor.
- Pasa `base_url` como env-var a la colección (apunta a http://laravel-api:APP_PORT/api).
- Reporters:
  - cli: salida en consola.
  - junit: exporta un XML consumible por Jenkins (results.xml).
  - htmlextra: exporta un reporte HTML rico (report.html).
- timeout/delay: configuraciones conservadoras para redes lentas.


## post { always }

```groovy
post {
  always {
    sh '''
      docker rm -f ${SERVICE} || true
    '''
    junit testResults: 'newman/results.xml', allowEmptyResults: true
    archiveArtifacts artifacts: 'newman/**', fingerprint: false
  }
}
```
- Se ejecuta siempre (éxito o fallo del pipeline):
  - docker rm -f …: asegura que el contenedor de la API se detenga.
  - junit …: publica el XML de resultados de Newman para que Jenkins muestre tests.
  - archiveArtifacts …: guarda los artefactos (HTML y demás) como evidencias.


## Notas didácticas clave

- ¿Por qué Docker para todo?
  - Reproducibilidad: el entorno de CI es idéntico para todos los builds.
  - Aislamiento: sin contaminar el agente con PHP o Node locales.
  - Red Docker: permite que Newman llame a la API por nombre `laravel-api`.

- .env y SQLite efímeros
  - Se crea un `.env.ci` y se copia a `.env` sólo para CI.
  - La base `database.sqlite` se trunca en cada build para un estado limpio.

- Healthcheck /api/ping
  - Asegura que el server está arriba antes de correr Postman.
  - Si no existe, créalo en `routes/api.php`:
    ```php
    Route::get('ping', fn () => ['pong' => now()->toISOString()]);
    ```

- Permisos de archivos
  - Ejecutar contenedores con `--user "$(id -u):$(id -g)"` evita archivos root en el workspace de Jenkins.

- OPcache deshabilitado
  - `php -d opcache.enable=0` garantiza que se sirva el código recién montado (sin cacheo) en CI.

- Newman y reports
  - Al montar `tests/postman`, no se acopla a una versión de Newman instalada en el host.
  - Se publican reports JUnit (para histórico de Jenkins) y HTML (para revisión manual).

- Condición Hotfix Postman
  - `when { fileExists(...) }` evita fallos si el repo aún no trae la colección.


## Problemas comunes y soluciones rápidas

- Falla healthcheck (no responde /api/ping):
  - Verifica que la ruta exista y no esté cacheada mal. Ejecuta `php artisan route:clear` en tu máquina local si estás depurando.

- Migración/seed falla:
  - Revisa `database/migrations` y `database/seeders`.
  - Asegura que el archivo `database/database.sqlite` puede crearse en el contenedor.

- Permisos de archivos trash (propietario root) tras el build:
  - Confirma que el agente Jenkins permite `id -u`/`id -g` y que `--user` está aplicado en los `docker run`.

- Colección Postman no encuentra base_url:
  - La colección debe referirse a `{{base_url}}`. En CI se inyecta con `--env-var base_url=${BASE_URL}`.


## Cómo extender este pipeline

- Agregar pruebas PHPUnit: añade un stage antes de levantar la API:
  ```groovy
  stage('PHPUnit') {
    steps {
      sh 'docker run --rm -v "$PWD":/app -w /app ${CI_IMAGE} bash -lc "php -d opcache.enable=0 vendor/bin/phpunit --testsuite Unit"'
    }
  }
  ```
- Publicar coverage: instala Xdebug en tu Dockerfile.ci y añade `--coverage-clover clover.xml` + `publishCoverage`.
- Cache de Composer: monta un volumen persistente si deseas acelerar builds (a cambio de reproducibilidad).


## Resumen

Este Jenkinsfile empaqueta un flujo CI limpio y reproducible: construye una imagen controlada, prepara Laravel y la BD en un contenedor efímero, levanta la API, valida con Postman/Newman en la misma red Docker, y publica reportes. Cada paso busca minimizar sorpresas (limpieza de cachés, OPcache off, healthcheck), haciendo que los builds fallen rápido con diagnósticos útiles.

