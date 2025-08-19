¡Claro, Miguel! Aquí tienes **la misma guía**, pero **actualizada** para la UI nueva de Postman (donde “Tests” ahora está en **Scripts → Post-response** y “Pre-request Script” está en **Scripts → Pre-request**). No cambié tu contenido técnico ni los scripts; solo ajusté las indicaciones de ubicación.

---

# Postman: Guía paso a paso para probar Biblioteca API

**Objetivo:** en pocos pasos tendrás una colección en Postman con variables, endpoints y tests para la API.

**API base (local):** `http://localhost:8000`
**Recursos:** `/api/ping`, `/api/books`, `/api/books/{id}`

> **Nota sobre la UI nueva:**
>
> * Antes: pestaña **“Tests”** → **Ahora:** **Scripts → Post-response**
> * Antes: **“Pre-request Script”** → **Ahora:** **Scripts → Pre-request**

---

## 1) Crear la colección

1. Abre Postman.
2. Clic en **New** → **Collection**.
3. Nombre: `Biblioteca API` → **Create**.
4. En la barra lateral, selecciona la colección recién creada.

---

## 2) Definir variables en la **COLECCIÓN** (no en Environment)

1. Con la colección seleccionada, abre la pestaña **Variables**.
2. Añade las siguientes variables (columna **Current Value**):

    * `base_url` → `http://localhost:8000`
    * `api_prefix` → `/api`
    * `books_base` → `{{api_prefix}}/books`
    * `ping_path` → `{{api_prefix}}/ping`
    * `book_id` → (déjalo vacío; se llenará en los tests)
    * `isbn` → (déjalo vacío; se generará en Pre-request)
    * (opcional) `accept` → `application/json`
    * (opcional) `content_type` → `application/json`
3. Clic en **Save**.

**Nota:** al usar variables de colección, no dependes de entornos externos y evitas conflictos con otras colecciones.

### 2.1 ¿Qué significan Variable / Initial Value / Current Value?

* **Variable:** el nombre de la clave que referenciarás como `{{clave}}` (ej. `{{base_url}}`).
* **Initial Value:** valor “compartible”. Se exporta cuando compartes la colección. Úsalo para defaults no sensibles.
* **Current Value:** valor “local”. No se exporta. Postman lo usa en tiempo de ejecución si está presente.

**Reglas prácticas**

* En ejecución: si **Current** existe, se usa; si no, cae en **Initial**.
* Buenas prácticas: datos locales/secretos en **Current**; valores que quieras compartir, en **Initial**.
* Para este proyecto: define **Current** mientras trabajas local. Antes de exportar, copia lo que necesites a **Initial** si quieres que otros lo reciban (o déjalo vacío si no corresponde compartirlo).

**Ejemplo claro (qué ve quien importa tu colección):**

* **Escenario A (solo Current):**

    * Tú tienes `base_url` con Initial = vacío y Current = `http://localhost:8000`.
    * Exportas la colección y se la envías a otra persona.
    * Esa persona importa y verá `base_url` con Initial = vacío; no verá tu Current. Deberá poner su Current local.
* **Escenario B (Initial + tu Current distinto):**

    * Tú pones `base_url` con Initial = `https://api.ejemplo.com` y Current = `http://localhost:8000`.
    * Exportas la colección.
    * Quien importa verá Initial = `https://api.ejemplo.com`. Podrá definir su propio Current (p. ej., `http://localhost:8001`).
* **Escenario C (quieres compartir valores por defecto):**

    * Copia a Initial los defaults que todos deberían ver (p. ej., `https://api.staging.ejemplo.com`).
    * Mantén en Current tus valores locales/secretos.

---

## 3) Crear los endpoints usando variables

Crea cada request dentro de la colección `Biblioteca API`.

### 3.1 Health: **GET ping**

* **Método:** GET
* **URL:** `{{base_url}}{{ping_path}}`
* **Guarda como:** `GET ping`

### 3.2 Crear libro: **POST books**

* **Método:** POST
* **URL:** `{{base_url}}{{books_base}}`
* **Headers:** `Content-Type: application/json` (o usa `{{content_type}}` si definiste la variable)
* **Scripts → Pre-request** (genera ISBN único):

```js
const uniqueIsbn = '978' + Date.now().toString().slice(-10);
pm.collectionVariables.set('isbn', uniqueIsbn);
```

* **Body (raw JSON):**

```json
{
  "title": "El misterio del bosque",
  "author": "Laura Martínez",
  "published_year": 2022,
  "isbn": "{{isbn}}",
  "description": "Una novela de suspenso sobre secretos ocultos en un pequeño pueblo."
}
```

* **Guarda como:** `POST crear libro`

### 3.3 Mostrar libro por id: **GET books/{id}**

* **Método:** GET
* **URL:** `{{base_url}}{{books_base}}/{{book_id}}`
* **Guarda como:** `GET libro por id`

### 3.4 Actualizar libro: **PATCH books/{id}**

* **Método:** PATCH (o PUT)
* **URL:** `{{base_url}}{{books_base}}/{{book_id}}`
* **Headers:** `Content-Type: application/json`
* **Body (raw JSON):**

```json
{ "title": "Título actualizado" }
```

* **Guarda como:** `PATCH actualizar libro`

### 3.5 Listar libros paginados: **GET books**

* **Método:** GET
* **URL:** `{{base_url}}{{books_base}}`
* **Guarda como:** `GET libros`

### 3.6 Eliminar libro: **DELETE books/{id}**

* **Método:** DELETE
* **URL:** `{{base_url}}{{books_base}}/{{book_id}}`
* **Guarda como:** `DELETE eliminar libro`

---

## 4) Añadir tests a cada request

> **Ahora:** pega los tests en **Scripts → Post-response** de cada request.

### 4.1 Tests: **GET ping** (Scripts → Post-response)

/*
  Explicación detallada de los métodos usados en los tests de Postman:
  - pm: objeto global de Postman para scripting en colecciones y requests.
  - pm.test(nombre, función): define un test con nombre y lógica; si la condición falla, el test aparece como fallido en Postman.
  - pm.response: objeto que representa la respuesta HTTP recibida.
  - pm.response.code: código de estado HTTP (ejemplo: 200 para OK, 201 para creado).
  - pm.response.json(): convierte el cuerpo de la respuesta en un objeto JSON.
  - pm.expect(valor): función de aserción que permite comparar valores y propiedades; usa la librería Chai.js integrada en Postman.
    - to.have.property('pong'): verifica que el objeto tenga la propiedad 'pong'.
    - to.eql(valor): compara igualdad estricta.
    - below(valor): verifica que el valor sea menor que el dado.
  - pm.response.responseTime: tiempo en milisegundos que tardó la respuesta.

  Estos tests validan que el endpoint /api/ping responde correctamente, con el código esperado, la propiedad 'pong' en el JSON y un tiempo de respuesta aceptable.
*/

```js
pm.test('status 200', () => {
  pm.expect(pm.response.code).to.eql(200);
});

pm.test('tiene pong', () => {
  pm.expect(pm.response.json()).to.have.property('pong');
});

pm.test('tiempo < 1000ms', () => {
  pm.expect(pm.response.responseTime).below(1000);
});
```

### 4.2 Tests: **POST crear libro** (Scripts → Post-response)

```js
pm.test('status 201', () => pm.response.code === 201);
const json = pm.response.json();
pm.test('estructura data', () => {
  pm.expect(json).to.have.property('data');
  pm.expect(json.data).to.include.keys('id','title','author','published_year','isbn');
});
pm.collectionVariables.set('book_id', json.data.id);
```

### 4.3 Tests: **GET libro por id** (Scripts → Post-response)

```js
pm.test('status 200', () => pm.response.code === 200);
pm.test('coincide id', () => {
  const json = pm.response.json();
  const id = parseInt(pm.collectionVariables.get('book_id'), 10);
  pm.expect(json.data.id).to.eql(id);
});
```

### 4.4 Tests: **PATCH actualizar libro** (Scripts → Post-response)

```js
pm.test('status 200', () => pm.response.code === 200);
pm.test('titulo actualizado', () => {
  const json = pm.response.json();
  pm.expect(json.data.title).to.eql('Título actualizado');
});
```

### 4.5 Tests: **GET libros** (Scripts → Post-response)

```js
pm.test('status 200', () => pm.response.code === 200);
pm.test('estructura paginada', () => {
  const json = pm.response.json();
  pm.expect(json).to.have.property('data');
  pm.expect(json).to.have.property('links');
  pm.expect(json).to.have.property('meta');
  if (Array.isArray(json.data) && json.data.length > 0) {
    pm.collectionVariables.set('book_id', json.data[0].id);
  }
});
```

### 4.6 Tests: **DELETE eliminar libro** (Scripts → Post-response)

```js
pm.test('status 204', () => pm.response.code === 204);
pm.collectionVariables.unset('book_id');
```

---

## 5) Ejecutar en Runner (orden recomendado)

1. Abre la colección `Biblioteca API` → botón **Run**.
2. **Orden:** `GET ping` → `POST crear libro` → `GET libro por id` → `PATCH actualizar libro` → `GET libros` → `DELETE eliminar libro`.
3. Ejecuta y revisa que todos los tests estén en verde (puedes ver **Test Results** en cada respuesta y el resumen del Runner).

---

## 6) Checklist rápida

* [ ] Colección creada y variables de colección guardadas.
* [ ] Endpoints creados con URLs basadas en `{{base_url}}`.
* [ ] Tests pegados en **Scripts → Post-response** y `book_id` manejado con collection variables.
* [ ] Colección ejecutada en Runner sin errores.

---

## 7) Exportar e importar la colección (prácticas recomendadas)

1. Revisa variables de la colección (**Variables**):

    * Copia a **Initial Value** solo lo que quieras compartir (p.ej., `base_url` si aplica). Mantén secretos solo en **Current**.
2. **Exportar** colección:

    * Clic derecho sobre `Biblioteca API` → **Export** → formato 2.1 → guarda el JSON.
    * El export incluye los **Initial Value** de variables de colección, **no** los **Current** (quien importa no verá tus Current).
    * Recomendación: si quieres que otros tengan un valor por defecto al importar, rellena **Initial** antes de exportar.
3. **Importar** en otra máquina:

    * **Import** (botón superior) → arrastra el JSON de la colección.
    * Abre la colección, pestaña **Variables**, ajusta **Current Value** según el entorno local (p. ej., `http://localhost:8000`).
4. (Opcional) Si decides usar **Environments**:

    * Exporta también el Environment (**Manage Environments** → selecciona → **Download as JSON**).
    * Recuerda que en Environments también se exportan los **Initial**, no los **Current**.

---
