# Biblioteca API

API RESTful desarrollada con Laravel para gestionar libros en una biblioteca. Permite crear, consultar, actualizar y eliminar libros mediante endpoints HTTP. Ideal para aprender buenas prácticas de Laravel y Eloquent.

## Requisitos
- PHP >= 8.1
- Composer
- SQLite (o MySQL, pero por defecto usa SQLite)
- Laravel >= 10

## Instalación y primeros pasos

1. **Clona el repositorio:**
   ```bash
   git clone <url-del-repositorio>
   cd biblioteca-api
   ```

2. **Instala dependencias:**
   ```bash
   composer install
   ```

3. **Configura el entorno:**
   - Copia el archivo `.env.example` a `.env` y ajusta la configuración de la base de datos. Por defecto, el proyecto usa SQLite:
     ```env
     DB_CONNECTION=sqlite
     DB_DATABASE=absolute/path/to/database/database.sqlite
     DB_FOREIGN_KEYS=true
     ```
   - Crea el archivo vacío `database/database.sqlite` si no existe.

4. **Ejecuta migraciones y seeders:**
   ```bash
   php artisan migrate --seed
   ```
   Esto crea las tablas y pobla la base de datos con 20 libros de ejemplo.

5. **Levanta el servidor de desarrollo:**
   ```bash
   php artisan serve
   ```
   Accede a la API en [http://localhost:8000](http://localhost:8000)

## Endpoints disponibles

Todos los endpoints están bajo `/api/books`.

### 1. Listar libros
- **GET** `/api/books`
- Respuesta:
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "Ejemplo",
        "author": "Autor",
        "published_year": 2023,
        "isbn": "1234567890",
        "description": "Descripción",
        "created_at": "2025-08-18T00:00:00.000000Z",
        "updated_at": "2025-08-18T00:00:00.000000Z"
      },
      // ...más libros
    ],
    "links": { ... },
    "meta": { ... }
  }
  ```

### 2. Consultar libro por ID
- **GET** `/api/books/{id}`
- Respuesta:
  ```json
  {
    "data": {
      "id": 1,
      "title": "Ejemplo",
      "author": "Autor",
      "published_year": 2023,
      "isbn": "1234567890",
      "description": "Descripción",
      "created_at": "2025-08-18T00:00:00.000000Z",
      "updated_at": "2025-08-18T00:00:00.000000Z"
    }
  }
  ```

### 3. Crear libro
- **POST** `/api/books`
- Body (JSON):
  ```json
  {
    "title": "Nuevo libro",
    "author": "Autor",
    "published_year": 2024,
    "isbn": "9876543210",
    "description": "Descripción opcional"
  }
  ```
- Respuesta (201):
  ```json
  {
    "data": {
      "id": 21,
      "title": "Nuevo libro",
      "author": "Autor",
      "published_year": 2024,
      "isbn": "9876543210",
      "description": "Descripción opcional",
      "created_at": "2025-08-18T00:00:00.000000Z",
      "updated_at": "2025-08-18T00:00:00.000000Z"
    }
  }
  ```

### 4. Actualizar libro
- **PUT/PATCH** `/api/books/{id}`
- Body (JSON):
  ```json
  {
    "title": "Título actualizado"
  }
  ```
- Respuesta (200):
  ```json
  {
    "data": {
      "id": 1,
      "title": "Título actualizado",
      "author": "Autor",
      "published_year": 2023,
      "isbn": "1234567890",
      "description": "Descripción",
      "created_at": "2025-08-18T00:00:00.000000Z",
      "updated_at": "2025-08-18T00:00:00.000000Z"
    }
  }
  ```

### 5. Eliminar libro
- **DELETE** `/api/books/{id}`
- Respuesta (204):
  ```
  (Sin contenido)
  ```

## Ejemplo de uso con Postman

1. **Listar libros:**
   - Método: GET
   - URL: `http://localhost:8000/api/books`

2. **Crear libro:**
   - Método: POST
   - URL: `http://localhost:8000/api/books`
   - Body: JSON (ver ejemplo arriba)

3. **Actualizar libro:**
   - Método: PATCH
   - URL: `http://localhost:8000/api/books/1`
   - Body: JSON (solo los campos a modificar)

4. **Eliminar libro:**
   - Método: DELETE
   - URL: `http://localhost:8000/api/books/1`

## Notas importantes
- La validación de datos se realiza automáticamente antes de crear o actualizar libros.
- El campo `isbn` debe ser único.
- Puedes modificar la cantidad de libros generados en el seeder `BookSeeder.php`.
- Si usas otra base de datos (MySQL, PostgreSQL), ajusta la configuración en `.env`.

## Comandos ejecutados en este proyecto
- `composer install` — Instala dependencias.
- `php artisan migrate --seed` — Crea y pobla la base de datos.
- `php artisan serve` — Levanta el servidor de desarrollo.

## Estructura del proyecto
- `app/Models/Book.php` — Modelo Eloquent del libro.
- `app/Http/Controllers/BookController.php` — Controlador principal de la API.
- `app/Http/Requests/StoreBookRequest.php` y `UpdateBookRequest.php` — Validación de datos.
- `app/Http/Resources/BookResource.php` — Formato de respuesta JSON.
- `database/seeders/BookSeeder.php` — Seeder para poblar libros de ejemplo.
- `routes/api.php` — Rutas de la API.

## Solución a errores comunes de migración y seeders

### Error: "table books has no column named publish_year"
Si al ejecutar `php artisan migrate --seed` o `php artisan migrate:fresh --seed` ves un error similar a:

```
SQLSTATE[HY000]: General error: 1 table books has no column named publish_year
```

Esto significa que hay una **inconsistencia en el nombre de la columna** entre la migración y el resto del código (modelo, factory, seeder, requests, controlador).

#### ¿Por qué ocurre?
- En la migración, la columna se llamó `publish_year`.
- En el modelo y el resto del código se usa `published_year`.
- Laravel/Eloquent espera que los nombres sean exactamente iguales.

#### ¿Cómo solucionarlo?
1. **Corrige el nombre de la columna en la migración:**
   - Abre el archivo de migración en `database/migrations/xxxx_xx_xx_xxxxxx_create_books_table.php`.
   - Cambia `publish_year` por `published_year`.
2. **Corrige el nombre en el factory:**
   - Abre `database/factories/BookFactory.php`.
   - Cambia `'publish_year' => ...` por `'published_year' => ...`.
3. **Elimina o vacía el archivo `database/database.sqlite`.**
4. **Ejecuta:**
   ```bash
   php artisan migrate:fresh --seed
   ```

Esto recreará la base de datos con la columna correcta y poblará los datos de ejemplo sin errores.

#### Nota para estudiantes
Siempre revisa que los nombres de los campos en migraciones, modelos, factories, seeders y requests sean **idénticos**. Laravel no corrige ni adapta los nombres automáticamente.

## Tipos de pruebas en Laravel con PHPUnit

Laravel utiliza PHPUnit para ejecutar pruebas automatizadas. Existen dos tipos principales de pruebas:

### Pruebas Unitarias (Unit)
- **Ubicación:** `tests/Unit/`
- **Propósito:** Verifican el funcionamiento de pequeñas partes del código (funciones, métodos, clases) de forma aislada, sin depender de la base de datos ni de otros componentes del sistema.
- **Ejemplo:** Probar que un método de la clase Book calcula correctamente el año de publicación, o que una función retorna el valor esperado.
- **Ventaja:** Son rápidas y ayudan a detectar errores en la lógica interna del código.

### Pruebas de Característica (Feature) = Pruebas de Integración
- **Ubicación:** `tests/Feature/`
- **Propósito:** Verifican el comportamiento de la aplicación completa, incluyendo la interacción entre varios componentes: base de datos, rutas, controladores, modelos y respuestas HTTP.
- **Ejemplo:** Probar que el endpoint POST `/api/books` crea un libro correctamente y retorna el código 201, o que el endpoint GET `/api/books` retorna la lista de libros.
- **Ventaja:** Simulan el uso real de la aplicación y ayudan a asegurar que los componentes funcionan juntos como se espera. Por eso, en Laravel, las pruebas Feature son consideradas pruebas de integración.

### ¿Cómo ejecutar las pruebas?
Puedes ejecutar todas las pruebas con:
```bash
php artisan test
```
O directamente con PHPUnit:
```bash
vendor/bin/phpunit
```

### Recomendación para estudiantes
- Escribe pruebas unitarias para la lógica interna y funciones pequeñas.
- Escribe pruebas de característica para verificar el funcionamiento de la API y los endpoints.
- Las pruebas ayudan a detectar errores antes de desplegar y facilitan el mantenimiento del código.

---

Si tienes dudas, revisa los comentarios en el código fuente para entender cada parte del flujo y la lógica de la API.
