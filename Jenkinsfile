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
    // Desde el contenedor de newman apuntamos por nombre de servicio del contenedor
    BASE_URL   = "http://laravel-api:${APP_PORT}/api"
  }

  stages {

    stage('Checkout') {
      steps {
        // Workspace limpio para no arrastrar archivos root de corridas previas
        deleteDir()
        checkout scm
      }
    }

    stage('Build CI image & network') {
      steps {
        sh '''
          set -e
          docker network create ${DOCKER_NET} || true
          docker build -t ${CI_IMAGE} -f Dockerfile.ci .
        '''
      }
    }

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

              # Limpia todas las cachés de Laravel para asegurar un estado limpio
              php artisan optimize:clear

              php artisan config:cache
              php artisan route:cache || true

              php artisan migrate --force
              php artisan db:seed --force
            '
        '''
      }
    }

    stage('Levantar API (contenedor)') {
      steps {
        sh '''
          set -e
          # Detén previo si existe
          docker rm -f ${SERVICE} || true

          UID_GID="$(id -u):$(id -g)"

          # Arranca servidor Laravel deshabilitando OPcache (-d opcache.enable=0) para asegurar que se lee el código fuente fresco
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

    stage('(Opcional) Hotfix Postman') {
      when { expression { return fileExists('tests/postman/APIREST-BIBLIOTECA.postman_collection.json') } }
      steps {
        sh '''
          sed -i 's/pm\\.reponse\\.json()/pm.response.json()/g' tests/postman/APIREST-BIBLIOTECA.postman_collection.json || true
        '''
      }
    }

    stage('Postman (Newman en contenedor)') {
      steps {
        sh '''
          set -e
          rm -rf newman && mkdir -p newman

          # Imagen que incluye htmlextra preinstalado
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
  }

  post {
    always {
      sh '''
        docker rm -f ${SERVICE} || true
      '''
      junit testResults: 'newman/results.xml', allowEmptyResults: true
      archiveArtifacts artifacts: 'newman/**', fingerprint: false
    }
  }
}
