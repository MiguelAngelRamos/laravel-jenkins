# PHPUnit + Laravel: setUp/tearDown, bootstrapping, bindings y Request::create

Este documento explica, con enfoque práctico, los conceptos usados en los tests unitarios del BookController.

## Operador :: en PHP
- `::` es el operador de resolución de ámbito.
- Usos típicos:
  - Métodos estáticos: `Clase::metodoEstatico()`
  - Constantes de clase: `Clase::CONSTANTE`
  - Miembros de la clase padre: `parent::metodo()`
  - Referencias dentro de la misma clase: `self::metodo()` o de enlace tardío: `static::metodo()`

## ¿Qué es parent::setUp() y parent::tearDown()?
- Origen: hooks de PHPUnit que se ejecutan antes y después de cada test.
- En Laravel, tus tests extienden `Tests\TestCase`, que a su vez se apoya en PHPUnit y en el trait `CreatesApplication`.
- `parent::setUp()`:
  - Ejecuta la inicialización (bootstrapping) del TestCase de Laravel.
  - Prepara la “app” (contenedor IoC), carga providers, configura el entorno de pruebas y habilita helpers (request(), response(), facades, etc.).
  - Sin esta llamada, `$this->app` no existe y los helpers que dependen del contenedor fallan.
- `parent::tearDown()`:
  - Limpia y resetea la aplicación/contendor tras cada test.
  - Evita fuga de estado entre pruebas.

## ¿Qué es “bootstrapping” aquí?
- Es el proceso de “encender” el entorno mínimo de la app de Laravel en modo prueba:
  - Crear la instancia de `Illuminate\Foundation\Application` (contenedor IoC).
  - Registrar proveedores de servicios (“service providers”).
  - Configurar bindings necesarios para helpers y servicios.
- Esto permite usar utilidades del framework en tests sin levantar el kernel HTTP completo.

## ¿De dónde viene $this->app?
- `Tests\TestCase` crea la aplicación de Laravel mediante `CreatesApplication`.
- Esa instancia de aplicación (contenedor IoC) queda disponible en `$this->app` durante el test.
- Con ella puedes:
  - Registrar providers: `$this->app->register(Provider::class)`
  - Registrar/forzar instancias: `$this->app->instance('clave', $objeto)`
  - Resolver dependencias manualmente: `$this->app->make(Clase::class)`

## ¿Qué es un binding en el contenedor?
- Un “binding” es un registro en el contenedor IoC que dice cómo resolver una dependencia.
- Ejemplos prácticos:
  - `instance('request', Request::create(...))`: cuando alguien pida `request()` (o la clave `request`) el contenedor devolverá esa instancia exacta.
  - Registrar `RoutingServiceProvider`: agrega bindings como `Illuminate\Contracts\Routing\ResponseFactory` (necesario para `response()->noContent()`).
- Beneficio en tests: puedes inyectar versiones controladas o dobles de prueba de dependencias globales.

## Request::create: FQN vs import
- FQN (Fully Qualified Name): `\Illuminate\Http\Request::create('/_unit', 'GET')` funciona sin `use`.
- Import limpio y legible:
  ```php
  use Illuminate\Http\Request;
  // ...
  $this->app->instance('request', Request::create('/_unit', 'GET'));
  ```
- Ambos son equivalentes. Importar mejora la legibilidad y homogeneidad con el resto de imports.

## ¿Por qué usamos la ruta '/_unit'?
- Es un valor arbitrario para construir una instancia de `Request` en pruebas.
- No se hace una llamada HTTP real ni se “golpea” ninguna ruta.
- Sirve como marcador/URI de contexto para recursos que consultan `request()` (por ejemplo, algunos JsonResource requieren una Request actual para comportamientos condicionales).
- Podría ser `'/testing'`, `'/'` o cualquier otra cadena; `'/_unit'` comunica claramente “request de entorno unitario”.

## Resumen del setUp “mínimo” en estos tests
- Objetivo: mantener unitario el test del controlador, pero habilitar utilidades necesarias del framework.
- Pasos:
  1. `parent::setUp()` para bootstrapping.
  2. Crear mock del servicio e instanciar el controlador (SUT).
  3. Bindear una `Request` en el contenedor: `instance('request', Request::create('/_unit','GET'))`.
  4. Registrar `RoutingServiceProvider` para que `response()` funcione.
- Con esto:
  - No levantamos el kernel HTTP (no hay routing/middleware reales).
  - No tocamos la base de datos (modelos en memoria).
  - Podemos seguir usando `JsonResource` y `response()` en el controlador bajo prueba.

## Nota sobre alternativas
- Feature tests: usan `$this->postJson()`, `$this->getJson()`, etc. Ejecutan el pipeline HTTP completo (routing, middleware, validaciones), por eso son de integración.
- Orchestra Testbench: útil para paquetes; “levanta” un entorno Laravel liviano en tests (sigue siendo más integración que unitario puro).

## Snippet ilustrativo (comentado)
```php
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\RoutingServiceProvider;

protected function setUp(): void
{
    parent::setUp(); // Bootstrapping de Laravel: crea $this->app y registra providers base

    $this->bookService = Mockery::mock(BookService::class); // Dependencia mockeada
    $this->controller  = new BookController($this->bookService); // SUT

    // Bind de la Request “actual” para recursos/ayudantes que dependen de request()
    $this->app->instance('request', HttpRequest::create('/_unit', 'GET'));

    // Registrar provider de routing para que el helper response() tenga su fábrica disponible
    $this->app->register(RoutingServiceProvider::class);
}
```

## Errores comunes si se omiten estos pasos

- Sin bind de request en el contenedor:
  - Error: `BindingResolutionException: Target class [request] does not exist`.
  - Cuándo ocurre: al ejecutar algo que llama a `request()` dentro del recurso/controlador, por ejemplo:
    ```php
    $resource = new BookResource($book);
    $array = $resource->toArray(request()); // falla si no hay request bindeada
    ```

- Sin registrar RoutingServiceProvider (ResponseFactory):
  - Error: `BindingResolutionException: Target [Illuminate\Contracts\Routing\ResponseFactory] is not instantiable`.
  - Cuándo ocurre: al usar `response()->noContent()` (o helpers de respuesta) en el controlador durante un test unitario.

## Versión simple (qué hacen esas dos líneas)

- `$this->app->instance('request', Request::create('/_unit', 'GET'));`
  - “Pon una request de mentira en Laravel para que `request()` funcione en el test”.
  - No hace red ni routing; solo da contexto mínimo a recursos/controladores que consultan la request actual.
  - `/_unit` es un URI cualquiera para indicar que estamos en pruebas unitarias.

- `$this->app->register(\Illuminate\Routing\RoutingServiceProvider::class);`
  - “Enciende el módulo de respuestas” para poder usar `response()->noContent()` sin levantar toda la app.
  - Registra en el contenedor la fábrica de respuestas (ResponseFactory) que esos helpers necesitan.

---

Si quieres, puedo refactorizar el test para importar `Illuminate\Http\Request` y reemplazar el FQN por `Request::create('/_unit','GET')`, dejando comentarios precisos en esas líneas. ¿Lo aplico ahora?
