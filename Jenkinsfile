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

          echo "[CI] Preparando .env.ci"
          if [ -f .env ]; then
            cp .env .env.ci
          elif [ -f .env.example ]; then
            cp .env.example .env.ci
          else
            cat > .env.ci <<'EOF'
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

          DBFILE="${PWD}/database/database.sqlite"
          mkdir -p database
          touch "$DBFILE"

          if grep -q '^DB_CONNECTION=' .env.ci; then
            sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env.ci
          else
            echo "DB_CONNECTION=sqlite" >> .env.ci
          fi

          if grep -q '^DB_DATABASE=' .env.ci; then
            sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DBFILE}|" .env.ci
          else
            echo "DB_DATABASE=${DBFILE}" >> .env.ci
          fi

          if ! grep -q '^DB_FOREIGN_KEYS=' .env.ci; then
            echo "DB_FOREIGN_KEYS=true" >> .env.ci
          fi

          cp .env.ci .env

          echo "[CI] PHP/Composer"
          php -v
          composer -V
          composer install --no-interaction --prefer-dist --no-progress

          php artisan key:generate
          php artisan config:cache
          php artisan route:cache || true   # tolera closures

          echo "[CI] Migraciones/Seed"
          php artisan migrate --force
          php artisan db:seed --force

          chmod -R 0777 storage bootstrap/cache || true

          echo "[CI] Verificando SQLite habilitado en PHP:"
          php -m | grep -i sqlite || echo "ADVERTENCIA: Extensión sqlite no listada; si falla, instala php-sqlite3 en el agente."
        '''
      }
    }

    stage('Levantar API') {
      steps {
        sh '''
          set -e

          if [ -f storage/ci-php.pid ]; then
            kill -9 $(cat storage/ci-php.pid) || true
            rm -f storage/ci-php.pid
          fi

          nohup php artisan serve --host=0.0.0.0 --port=${APP_PORT} > storage/logs/ci-server.log 2>&1 &
          echo $! > storage/ci-php.pid

          echo "[CI] Esperando ${BASE_URL}/ping ..."
          for i in $(seq 1 40); do
            if curl -fsS "${BASE_URL}/ping" > /dev/null; then
              echo "API arriba en ${BASE_URL}"
              exit 0
            fi
            sleep 1
          done

          echo "La API no respondió a ${BASE_URL}/ping a tiempo. Últimas líneas del log:"
          tail -n 100 storage/logs/ci-server.log || true
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

    stage('Postman (Newman en Docker)') {
      steps {
        sh '''
          set -e
          rm -rf newman && mkdir -p newman

          # Requiere que el usuario jenkins esté en el grupo docker:
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
      sh '''
        if [ -f storage/ci-php.pid ]; then
          kill -9 $(cat storage/ci-php.pid) || true
          rm -f storage/ci-php.pid
        fi
      '''
      junit 'newman/results.xml'
      archiveArtifacts artifacts: 'newman/**, storage/logs/ci-server.log', fingerprint: false
    }
  }
}
