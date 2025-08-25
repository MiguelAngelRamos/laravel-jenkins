pipeline {
  agent any

  options {
    timestamps()
    disableConcurrentBuilds()
    timeout(time: 30, unit: 'MINUTES')
  }

  environment {
    APP_PORT = '8000'
    BASE_URL = "http://localhost:${APP_PORT}/api"
  }

  stages {

    stage('Checkout') {
      steps {
        checkout scm
        stash name: 'src', includes: '**/*', excludes: '.git/**'
      }
    }

    stage('Instalar & Preparar Laravel') {
      steps {
        unstash 'src'
        sh '''
          set -e

          # .env de CI (SQLite relativo para Linux)
          cp .env .env.ci || true
          sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|g" .env.ci
          sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${PWD}/database/database.sqlite|g" .env.ci
          cp .env.ci .env
          mkdir -p database
          touch database/database.sqlite

          # Composer + cacheo básico
          php -v
          composer -V
          composer install --no-interaction --prefer-dist --no-progress

          php artisan key:generate
          php artisan config:cache
          php artisan route:cache || true

          php artisan migrate --force
          php artisan db:seed --force

          # Permisos mínimos (por si acaso)
          chmod -R 0777 storage bootstrap/cache || true
        '''
      }
    }

    stage('Levantar API') {
      steps {
        sh '''
          set -e

          # Si quedó un server previo, lo bajamos
          if [ -f storage/ci-php.pid ]; then
            kill -9 $(cat storage/ci-php.pid) || true
            rm -f storage/ci-php.pid
          fi

          nohup php artisan serve --host=0.0.0.0 --port=${APP_PORT} > storage/logs/ci-server.log 2>&1 &
          echo $! > storage/ci-php.pid

          # Espera activa a /api/ping
          for i in $(seq 1 40); do
            if curl -fsS "${BASE_URL}/ping" > /dev/null; then
              echo "API arriba en ${BASE_URL}"
              exit 0
            fi
            sleep 1
          done

          echo "La API no respondió a ${BASE_URL}/ping a tiempo"
          exit 1
        '''
      }
    }

    stage('(Opcional) Hotfix Postman') {
      when { expression { return fileExists('tests/postman/APIREST-BIBLIOTECA.postman_collection.json') } }
      steps {
        sh '''
          # Corrige el typo conocido: pm.reponse.json() -> pm.response.json()
          sed -i 's/pm\\.reponse\\.json()/pm.response.json()/g' tests/postman/APIREST-BIBLIOTECA.postman_collection.json || true
        '''
      }
    }

    stage('Postman (Newman en Docker)') {
      steps {
        sh '''
          set -e
          rm -rf newman && mkdir -p newman

          # Ejecuta Newman en contenedor con red del host (Linux)
          # Nota: requiere que el usuario jenkins pertenezca al grupo docker
          #   sudo usermod -aG docker jenkins && sudo systemctl restart docker jenkins
          docker run --rm --network host \
            -v "$PWD/tests/postman":/etc/newman \
            -v "$PWD/newman":/etc/newman/newman \
            postman/newman:alpine sh -lc "
              npm i -g newman-reporter-htmlextra >/dev/null 2>&1 || true &&
              newman run /etc/newman/APIREST-BIBLIOTECA.postman_collection.json \
                --env-var base_url=${BASE_URL} \
                --reporters cli,junit,htmlextra \
                --reporter-junit-export /etc/newman/newman/results.xml \
                --reporter-htmlextra-export /etc/newman/newman/report.html \
                --timeout-request 10000 --delay-request 50
            "
        '''
      }
    }
  }

  post {
    always {
      // Bajamos el server embebido si quedó vivo
      sh '''
        if [ -f storage/ci-php.pid ]; then
          kill -9 $(cat storage/ci-php.pid) || true
          rm -f storage/ci-php.pid
        fi
      '''

      // Publica resultados y artefactos
      junit 'newman/results.xml'
      archiveArtifacts artifacts: 'newman/**, storage/logs/ci-server.log', fingerprint: false
    }
  }
}
