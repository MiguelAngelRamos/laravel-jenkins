# Guía Postman: CRUD de Libros (API Biblioteca) con variables y tests (ChaiJS/Ajv)

Esta guía muestra cómo crear una colección en Postman para probar el CRUD de `books`, usando variables de colección, scripts Pre-request y Tests con ChaiJS/Ajv, y ejemplos de JSON.

## 0) Mapa rápido: ¿Dónde va cada cosa?

- Colección > Pre-request Script: Script que corre ANTES de cada request de la colección (sección 2). Genera variables (isbn_dynamic, year_now, request_id) y añade headers de trazabilidad.
- Colección > Headers: Accept: application/json (sección 2.1). Asegura respuestas JSON/422 en validación.
- Colección > Tests: Verificaciones de headers y content-type para TODAS las requests (sección 2.2). Corre DESPUÉS de cada respuesta.
- Request (cada endpoint) > Body: El JSON del ejemplo correspondiente (sección 3.x).
- Request (solo si hace falta) > Headers: Content-Type: application/json en POST/PUT/PATCH si Postman no lo añade automáticamente.
- Request (cada endpoint) > Tests: Los tests específicos del endpoint (sección 3.x). Corren DESPUÉS de recibir la respuesta (equivale a "post").

## 1) Crear la colección y variables

- Crear colección: "Biblioteca API"
- Variables de colección (tab Variables):
  - base_url (Initial/Current): http://localhost:8000/api
  - sofia_name: Sofia
  - sofia_email: sofia@correo.com
  - richard_email: richard@correo.com
  - isbn_dynamic: (déjalo vacío; se setea en pre-request)
  - book_id: (vacío; lo setearán los tests del POST)
  - year_now: (vacío; se setea en pre-request)
  - request_id: (vacío; se setea en pre-request)

Notas:
- Initial Value es lo que viaja al exportar/compartir; Current Value es local.
- Para compartir la colección, rellena Initial Value de las que quieras que viajen (p.ej., base_url) y deja datos sensibles solo en Current.

## 2) Pre-request Script a nivel colección (Ubicación: Colección > Pre-request)

Coloca esto en la colección (no en cada request):

```javascript
// Genera un request-id y timestamp
const now = new Date();
pm.collectionVariables.set('request_id', String(now.getTime()));
pm.collectionVariables.set('year_now', now.getFullYear());

// Genera un ISBN dinámico de 13 dígitos basado en timestamp (evita colisiones únicas)
const ts = String(now.getTime());
const isbn13 = (ts.padStart(13, '0')).slice(0, 13);
pm.collectionVariables.set('isbn_dynamic', isbn13);

// Opcional: rota el email "activo" para pruebas (Sofia o Richard)
const activeEmail = pm.collectionVariables.get('active_email') || pm.collectionVariables.get('sofia_email') || 'sofia@correo.com';
pm.collectionVariables.set('active_email', activeEmail);

// Agrega headers de trazabilidad (si no existen)
if (!pm.request.headers.has('X-Request-Id')) {
  pm.request.headers.add({ key: 'X-Request-Id', value: pm.collectionVariables.get('request_id') });
}
if (!pm.request.headers.has('X-Client-Email')) {
  pm.request.headers.add({ key: 'X-Client-Email', value: pm.collectionVariables.get('active_email') });
}
```

## 2.1) Headers por colección (obligatorios para 422) (Ubicación: Colección > Headers)

Para que Laravel devuelva errores de validación como JSON con status 422, la petición debe "esperar JSON".
- En Postman, agrega en la colección (tab Headers):
  - Accept: application/json
- Content-Type se establece por request cuando el Body es raw JSON (POST/PUT/PATCH). No lo pongas a nivel de colección si no quieres enviarlo también en GET/DELETE.

Pasos:
1) Click derecho sobre la colección > Edit.
2) Tab "Headers" > Add: Key = Accept, Value = application/json.
3) En cada request con Body raw JSON, Postman agregará automáticamente Content-Type: application/json; si no, añádelo en la request.

## 2.2) Tests de headers a nivel colección (Ubicación: Colección > Tests)

En la colección, tab "Tests", pega esto para verificar en cada request que los headers están bien y que la respuesta es JSON:

> Ubicación exacta: va en Tests (post-respuesta). No es Pre-request.

```javascript
// Verifica que la request pide JSON a la API (POST-Response)
pm.test('request incluye Accept: application/json', () => {
  const accept = pm.request.headers.get('Accept');
  pm.expect(accept, 'Debe incluir application/json').to.include('application/json');
});

// Verifica Content-Type en requests con body (POST-Response)
pm.test('request con body usa Content-Type: application/json', () => {
  const methodsWithBody = ['POST','PUT','PATCH'];
  if (methodsWithBody.includes(pm.request.method)) {
    const ct = pm.request.headers.get('Content-Type');
    pm.expect(ct, 'Content-Type debe ser JSON').to.include('application/json');
  }
});

// La respuesta de la API debe ser JSON (POST-Response)
pm.test('response Content-Type es JSON', () => {
  const rct = pm.response.headers.get('Content-Type') || '';
  pm.expect(rct).to.include('application/json');
});

// Ayuda de diagnóstico cuando falta Accept
if (!pm.request.headers.get('Accept')) {
  console.warn('[Diagnóstico] Falta Accept: application/json. Laravel podría responder 302 (redirect) en errores de validación y terminar viendo 200.');
}
```

Nota (opcional): si prefieres forzar el header ANTES de enviar, puedes añadir en la colección, tab "Pre-request Script":

```javascript
// Fuerza Accept: application/json antes de enviar (Pre-request)
if (!pm.request.headers.has('Accept')) {
  pm.request.headers.add({ key: 'Accept', value: 'application/json' });
}
```

## 2.3) Por qué importa (Accept vs Content-Type)

- Accept: le dice al servidor qué formato de respuesta espera el cliente. En Laravel, activa wantsJson()/expectsJson(). Si está presente con application/json, los errores de validación se devuelven como JSON con 422.
- Content-Type: indica cómo está codificado el cuerpo de la request. Para enviar JSON, debe ser application/json. Sin esto, Laravel podría no parsear el body correctamente.

Consecuencia práctica: si no envías Accept: application/json, al fallar la validación Laravel puede redirigir (302) y Postman mostrar un 200 final, ocultando el 422 real. Con Accept correcto, verás el 422 y el payload de errores.

## 3) Endpoints y ejemplos

Base URL: `{{base_url}}`

### 3.1 Salud (ping)
- Método: GET
- URL: `{{base_url}}/ping`
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 200', () => pm.expect(pm.response.code).to.eql(200));
pm.test('content-type json', () => pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json'));
pm.test('tiene pong y formato ISO', () => {
  const json = pm.response.json();
  pm.expect(json).to.have.property('pong');
  pm.expect(json.pong).to.match(/\d{4}-\d{2}-\d{2}T/);
});
pm.test('tiempo < 1000ms', () => pm.expect(pm.response.responseTime).below(1000));
```

### 3.2 Listar libros (index)
- Método: GET
- URL: `{{base_url}}/books`
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 200', () => pm.expect(pm.response.code).to.eql(200));
pm.test('content-type json', () => pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json'));
pm.test('estructura paginada', () => {
  const json = pm.response.json();
  pm.expect(json).to.have.property('data').that.is.an('array');
  pm.expect(json).to.have.property('links');
  pm.expect(json).to.have.property('meta');
});
```

### 3.3 Crear libro (store)
- Método: POST
- URL: `{{base_url}}/books`
- Headers: Content-Type: application/json (Accept heredado de la colección)
- Body (Ubicación: Request > Body > raw JSON):
```json
{
  "title": "El misterio del bosque (by {{sofia_name}})",
  "author": "Sofia",
  "published_year": {{year_now}},
  "isbn": "{{isbn_dynamic}}",
  "description": "Creado por {{sofia_email}}"
}
```
- Tests (Ubicación: Request > Tests):
```javascript
// Esquema esperado para BookResource
const bookSchema = {
  type: 'object',
  required: ['data'],
  properties: {
    data: {
      type: 'object',
      required: ['id','title','author','published_year','isbn'],
      properties: {
        id: { type: ['integer','number'] },
        title: { type: 'string' },
        author: { type: 'string' },
        published_year: { type: ['integer','number'] },
        isbn: { type: 'string' },
        description: { type: ['string','null'] },
        created_at: { type: ['string','null'] },
        updated_at: { type: ['string','null'] }
      }
    }
  }
};

pm.test('status 201', () => pm.expect(pm.response.code).to.eql(201));
pm.test('content-type json', () => pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json'));
pm.test('cumple esquema de BookResource', () => pm.response.to.have.jsonSchema(bookSchema));

// Guarda id e isbn para siguientes requests
const json = pm.response.json();
pm.collectionVariables.set('book_id', json.data.id);
pm.collectionVariables.set('isbn_last', json.data.isbn);
```

### 3.4 Mostrar libro (show)
- Método: GET
- URL: `{{base_url}}/books/{{book_id}}`
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 200', () => pm.expect(pm.response.code).to.eql(200));
pm.test('tiene id solicitado', () => {
  const json = pm.response.json();
  pm.expect(json).to.have.property('data');
  pm.expect(json.data.id).to.eql(Number(pm.collectionVariables.get('book_id')));
});
```

### 3.5 Actualizar libro (update)
- Método: PUT
- URL: `{{base_url}}/books/{{book_id}}`
- Body (Ubicación: Request > Body > raw JSON):
```json
{
  "author": "Richard",
  "description": "Actualizado por {{richard_email}}"
}
```
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 200', () => pm.expect(pm.response.code).to.eql(200));
pm.test('autor actualizado', () => {
  const json = pm.response.json();
  pm.expect(json.data.author).to.eql('Richard');
});
```

### 3.6 Intento de duplicar ISBN (debe fallar 422)
- Método: POST
- URL: `{{base_url}}/books`
- Body (Ubicación: Request > Body > raw JSON) (usa el mismo ISBN guardado):
```json
{
  "title": "Duplicado ISBN",
  "author": "QA",
  "published_year": {{year_now}},
  "isbn": "{{isbn_last}}",
  "description": "Debe fallar por unique"
}
```
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 422', () => pm.expect(pm.response.code).to.eql(422));
pm.test('errores de validación incluyen isbn', () => {
  const json = pm.response.json();
  pm.expect(json).to.have.property('errors');
  pm.expect(json.errors).to.have.property('isbn');
});
```

### 3.7 Eliminar libro (destroy)
- Método: DELETE
- URL: `{{base_url}}/books/{{book_id}}`
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 204', () => pm.expect(pm.response.code).to.eql(204));
pm.test('sin cuerpo', () => pm.expect(pm.response.text()).to.eql(''));
```

### 3.8 Verificar 404 tras eliminar
- Método: GET
- URL: `{{base_url}}/books/{{book_id}}`
- Tests (Ubicación: Request > Tests):
```javascript
pm.test('status 404', () => pm.expect(pm.response.code).to.eql(404));
```

## 4) Batería de tests recomendada (nivel senior)

- Disponibilidad y rendimiento:
  - status esperado y content-type json en todos.
  - tiempo de respuesta < 1000ms en ping y < 2000ms en CRUD.
- Esquemas JSON (Ajv integrado en Postman):
  - Validar `BookResource` en create/update/show.
  - Validar estructura paginada en index (`data`, `links`, `meta`).
- Persistencia/estado:
  - POST guarda `book_id` e `isbn_last` para chaining.
  - GET by id devuelve el mismo id.
  - PUT cambia solo los campos enviados (author/description), conservando `isbn` y `title`.
  - DELETE responde 204 y GET posterior 404.
- Validaciones negativas (422):
  - ISBN duplicado.
  - Campos requeridos ausentes (title/author/isbn/published_year).
  - Tipos inválidos (published_year no numérico, isbn vacío).
- Cabeceras:
  - Verificar `Content-Type: application/json`.
  - Incluir y revisar `X-Request-Id` y `X-Client-Email` si agregaste middlewares de trazabilidad (opcional).

## 5) Notas sobre variables

- Colección vs Entorno:
  - Colección: buena para compartir defaults como `base_url`, y para estados encadenados (`book_id`).
  - Entorno: ideal para credenciales/tokens distintos por máquina (ocúltalos en Current Value).
- Exportar/compartir:
  - Initial Value es lo que viaja al exportar. Ajusta `base_url` y deja `book_id` vacío antes de exportar.

## 6) Sugerencia de pre-request adicional (logs) (Ubicación: Colección > Pre-request)

- Si necesitas logging básico, añade:
```javascript
console.log('[PRE]', {
  request_id: pm.collectionVariables.get('request_id'),
  email: pm.collectionVariables.get('active_email'),
  time: new Date().toISOString()
});
```
Esto queda en la consola de Postman (View > Show Postman Console) y ayuda a trazar ejecuciones.

---

Checklist de uso rápido:
1) Crear colección y variables como arriba.
2) Pegar el Pre-request de colección.
3) Configurar Accept: application/json en Headers de la colección.
4) Crear los requests en el orden: ping -> index -> store -> show -> update -> post-duplicado -> delete -> show-404.
5) Pegar los tests (incluye los de headers a nivel colección).
6) Ejecutar con Runner para ver la batería completa y métricas.
