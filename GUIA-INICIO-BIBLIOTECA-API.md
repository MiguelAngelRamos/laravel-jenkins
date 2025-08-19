# Guía de Inicio Rápido — Biblioteca API

Esta guía explica cómo clonar, configurar y ejecutar el proyecto localmente, poblar la base de datos con datos de ejemplo, probar los endpoints y correr las pruebas.

## Requisitos
- PHP 8.1+
- Composer 2+
- Extensión SQLite3 habilitada en PHP
- Git (opcional, para clonar)

## 1) Clonar e instalar dependencias
```bash
git clone <URL_DEL_REPO>
cd biblioteca-api
composer install
```

## 2) Configurar entorno (.env) y clave de aplicación
1. Copiar el archivo de entorno base y generar la APP_KEY:
   ```bash
   copy .env.example .env   # Windows PowerShell/CMD
   # cp .env.example .env   # macOS/Linux (alternativa)
   php artisan key:generate
   ```
2. En .env, usar SQLite (recomendado para local):
   ```dotenv
   DB_CONNECTION=sqlite
   DB_DATABASE="C:/ruta/completa/al/proyecto/database/database.sqlite"
   DB_FOREIGN_KEYS=true
   ```
   - En Windows, usa ruta absoluta (con / o \\). Ejemplo: `C:/Users/TuUsuario/Proyecto/database/database.sqlite`.
   - Asegúrate de que la carpeta `database/` exista (viene en el repo).

3. Crear el archivo de base de datos vacío si no existe aún:
   - Crea un archivo en `database/database.sqlite` (vacío). En Windows puedes crearlo desde el Explorador o:
     ```bash
     type NUL > database\database.sqlite
     ```

4. Limpiar caché de configuración siempre que cambies .env:
   ```bash
   php artisan config:clear
   ```

## 3) Migraciones y datos de ejemplo
Ejecuta migraciones desde cero y siembra datos de prueba con factories:
```bash
php artisan migrate:fresh --seed
```
- Crea la tabla `books` y genera ~20 libros usando `BookSeeder` y `BookFactory`.
- Si ves errores de columnas faltantes: revisa la migración `create_books_table` y vuelve a ejecutar el comando anterior.

## 4) Levantar el servidor
```bash
php artisan serve
```
- Servirá en `http://127.0.0.1:8000` por defecto.

## 5) Endpoints disponibles
Las rutas están bajo prefijo `/api`.
- Salud (healthcheck): `GET /api/ping` → `{ "pong": "<ISO timestamp>" }`
- Libros (REST):
  - `GET /api/books` — listar
  - `POST /api/books` — crear
  - `GET /api/books/{book}` — detalle
  - `PUT/PATCH /api/books/{book}` — actualizar
  - `DELETE /api/books/{book}` — eliminar

Ejemplo de payload para crear un libro (POST /api/books):
```json
{
  "title": "El misterio del bosque",
  "author": "Laura Martínez",
  "published_year": 2022,
  "isbn": "9781234567890",
  "description": "Una novela de suspenso sobre secretos ocultos."
}
```

## 6) Pruebas
- Ejecutar todo el suite:
  ```bash
  php artisan test
  # o
  vendor/bin/phpunit
  ```
- Nota sobre la base de datos en pruebas:
  - `phpunit.xml` define `APP_ENV=testing`, pero no fuerza BD en memoria. Si quieres aislar la BD de pruebas, usa `.env.testing` con SQLite en memoria (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) o añade esos `env` en `phpunit.xml`, y agrega el trait `RefreshDatabase` en pruebas de integración.
- Consulta la guía: `PHPUnit-Guia-Pruebas-Unitarias.md` para detalles y convenciones.

## 7) Postman (colección y variables)
- Para probar con Postman, revisa `Postman-Guia-Biblioteca-API.md`.
- Variables típicas de colección: `base_url` (p.ej. `http://127.0.0.1:8000/api`).

## 8) Troubleshooting (comunes)
- BD vacía tras levantar el servidor:
  - Ejecuta `php artisan migrate:fresh --seed` y verifica que `.env` apunte al mismo `database.sqlite` que inspeccionas.
- Error 403 con FormRequest:
  - Asegura que `authorize()` en `StoreBookRequest` y `UpdateBookRequest` retorne `true` si no hay auth.
- Error 500 por columna faltante (p.ej. `published_year`):
  - Revisa la migración `create_books_table` y vuelve a migrar en limpio con `--seed`.
- Cambié .env y nada pasa:
  - Ejecuta `php artisan config:clear` y reinicia el servidor.
- Windows: ruta SQLite
  - Usa ruta absoluta en `.env` y asegúrate que el archivo existe.

## 9) Arquitectura (breve)
- Eloquent Model: `App\Models\Book` con `$fillable` para `title, author, published_year, isbn, description`.
- Rutas REST: `Route::apiResource('books', BookController::class)` y `GET /api/ping`.
- Semillas: `DatabaseSeeder` → `BookSeeder` → `BookFactory`.
- Patrón repositorio y servicio:
  - `App\Repositories\...` y `App\Services\BookService` para desacoplar acceso a datos de la lógica de aplicación.
  - Ver `REPORTE-REFACTOR-01.md` para detalles de refactor e inyección de dependencias.

## 10) Comandos útiles
```bash
# Limpiar caché de config
php artisan config:clear

# Migrar desde cero y sembrar
php artisan migrate:fresh --seed

# Listar rutas API
php artisan route:list --path=api

# Servidor local
php artisan serve

# Ejecutar pruebas
php artisan test
```

## 11) Verificación rápida
- `GET http://127.0.0.1:8000/api/ping` debe responder con `{ "pong": "..." }`.
- `GET http://127.0.0.1:8000/api/books` debe listar ~20 libros.

---
Esta guía es complementaria a: `README.md`, `PHPUnit-Guia-Pruebas-Unitarias.md`, `Postman-Guia-Biblioteca-API.md` y `REPORTE-REFACTOR-01.md`. Ajusta según tu entorno.

