<?php

namespace Tests\Unit;
use App\Http\Controllers\BookController;            // SUT: Controlador bajo prueba
use App\Http\Requests\StoreBookRequest;             // FormRequest para crear (se mockea ->validated())
use App\Http\Requests\UpdateBookRequest;            // FormRequest para actualizar (se mockea ->validated())
use App\Http\Resources\BookResource;                // JsonResource usado por el controlador
use App\Models\Book;                                 // Modelo Eloquent (instancias en memoria, sin DB)
use App\Services\BookService;                        // Servicio inyectado (se mockea)
use Illuminate\Http\Request;                          // Import: para usar Request::create de forma legible
use Illuminate\Routing\RoutingServiceProvider;        // Import: para registrar el ResponseFactory sin FQN
use Mockery;                                          // Librería de mocks
use Mockery\MockInterface;                            // Tipo de interfaz para mocks
use Tests\TestCase;                                   // TestCase de Laravel: provee el contenedor (app) y helpers
use Symfony\Component\HttpFoundation\Response;      // Tipo base de respuesta HTTP (status code, body)

/**
 * Pruebas UNITARIAS del BookController.
 *
 * Objetivo didáctico:
 * - Aislar la lógica del controlador: NO HTTP real, NO DB, NO routing real.
 * - Mockear el servicio y las FormRequest (método validated()).
 * - Usar un "ciclo de vida mínimo" del contenedor para que BookResource y response() funcionen.
 *
 * Importante: extendemos Tests\TestCase para tener un Application Container (app) disponible,
 * pero seguimos en un contexto unitario porque:
 * - No ejecutamos rutas ni middleware.
 * - No usamos Eloquent contra una DB (instancias de modelo en memoria).
 * - No llamamos a $this->postJson()/getJson() (eso sería Feature/Integración).
 */
class BookControllerTest extends TestCase
{
    /** @var MockInterface */
    private $bookService;  // Dependencia del controlador, se reemplaza por un mock

    /** @var BookController */
    private $controller;   // SUT (System Under Test)

    /**
     * setUp: hook del ciclo de vida de PHPUnit que corre ANTES de cada test.
     * - Origen: PHPUnit\Framework\TestCase define setUp/tearDown; Laravel extiende ese TestCase.
     * - parent::setUp(): muy importante; inicializa el TestCase de Laravel (contendor "app",
     *   providers, helpers), sin esto $this->app no existe y fallan request()/response().
     * - Aquí preparamos mocks y un "ciclo mínimo" del contenedor para que el controlador funcione
     *   en modo unitario (sin rutas, sin middleware, sin DB, sin red).
     */
    protected function setUp(): void
    {
        // Llama a la implementación base (Laravel TestCase + PHPUnit) para bootstrapping del framework
        parent::setUp();

        // Arrange: mock del servicio que el controlador inyecta por constructor
        $this->bookService = Mockery::mock(BookService::class);

        // Arrange: instancia del SUT (System Under Test) con su dependencia mockeada
        $this->controller = new BookController($this->bookService);

        /**
         * ¿Por qué estos dos registros son necesarios en un test UNITARIO?
         *
         * Caso 1: request() sin bind
         * - Muchos JsonResource (BookResource) necesitan la Request actual para toArray()/additional().
         * - Si NO bindeas una instancia de Request, el helper request() intenta resolver 'request'
         *   en el contenedor y falla con: BindingResolutionException: Target class [request] does not exist.
         * - Ejemplo mínimo que fallaría sin este bind:
         *     $resource = new BookResource($book);
         *     $resource->toArray(request()); // request() no existe en el contenedor => excepción
         *
         * Caso 2: response() sin ResponseFactory (RoutingServiceProvider)
         * - El método destroy retorna response()->noContent(). Ese helper requiere que el contenedor
         *   tenga resuelta la interfaz Illuminate\Contracts\Routing\ResponseFactory.
         * - Si NO registras el RoutingServiceProvider, verás:
         *   BindingResolutionException: Target [Illuminate\Contracts\Routing\ResponseFactory] is not instantiable.
         * - Ejemplo mínimo que fallaría sin registrar el provider:
         *     $resp = response()->noContent(); // el contenedor no sabe crear ResponseFactory => excepción
         *
         * Con estos dos pasos, no levantamos el kernel HTTP completo, pero proveemos el "ciclo mínimo"
         * para que el código del controlador funcione en un entorno unitario aislado (sin DB ni rutas).
         */

        // --- Versión simple para todos ---
        // 1) Colocamos una "request" de mentira dentro de Laravel para que, cuando el código
        //    llame a request(), exista algo que devolver. No hace ninguna llamada real, solo
        //    evita errores y da contexto a los recursos. (Usamos el import de Request para legibilidad)
        // 2) Encendemos el "módulo de respuestas" de Laravel para poder usar response()->noContent()
        //    sin arrancar toda la app. Es como darle las herramientas mínimas al controlador
        //    para crear respuestas HTTP en pruebas unitarias.
        // ---------------------------------
        // Bind explícito de una Request "falsa" en el contenedor => request() la devolverá
        $this->app->instance('request', Request::create('/_unit', 'GET'));
        // Registramos el proveedor de routing para tener el ResponseFactory (response()) disponible
        $this->app->register(RoutingServiceProvider::class);
    }

    /**
     * tearDown: hook del ciclo de vida de PHPUnit que corre DESPUÉS de cada test.
     * - Origen: PHPUnit\Framework\TestCase.
     * - Mockery::close(): verifica expectativas de los mocks y libera recursos.
     * - parent::tearDown(): limpia el contenedor/aplicación de Laravel y restaura el estado.
     *   Asegura aislamiento entre pruebas.
     */
    protected function tearDown(): void
    {
        // Cierra y verifica los mocks de Mockery para evitar fugas de memoria/estado compartido
        Mockery::close();
        // Limpieza del TestCase de Laravel (resetea el contenedor y otros recursos del framework)
        parent::tearDown();
    }

    /** @test */
    public function store_deberia_crear_libro_y_responder_201_usando_el_servicio()
    {
        // ============ Arrange ============
        // Payload que normalmente llegaría en la petición; aquí lo definimos manualmente
        $payload = [
            'title'  => 'Clean Architecture',
            'author' => 'Robert C. Martin',
        ];

        // Mock del FormRequest: NO ejecuta validación real; solo devuelve lo que diría validated()
        $request = Mockery::mock(StoreBookRequest::class);
        $request->shouldReceive('validated')   // AAA: entrada validada del caso
            ->once()
            ->andReturn($payload);

        // Stub del modelo que devolverá el servicio (objeto en memoria; no se persiste)
        $book = $this->fakeBook(1, $payload['title'], $payload['author']);

        // Expectativa: el controlador DEBE delegar la creación al servicio con los datos validados
        $this->bookService->shouldReceive('createBook')
            ->once()
            ->with($payload)
            ->andReturn($book);

        // ============ Act ============
        // store retorna (new BookResource($book))->response()->setStatusCode(201)
        // Gracias al contenedor, response() funciona sin bootear toda la app
        $response = $this->controller->store($request);

        // ============ Assert ============
        $this->assertInstanceOf(Response::class, $response); // Respuesta HTTP
        $this->assertSame(201, $response->getStatusCode());  // 201 Created

        // Materializamos el cuerpo JSON para verificar campos clave sin acoplar todo el schema
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);      // JsonResource envuelve por defecto en "data"
        $this->assertArrayHasKey('id', $json['data']);
        $this->assertSame(1, $json['data']['id']);
    }

    /** @test */
    public function show_deberia_retornar_book_resource_sin_tocar_db()
    {
        // ============ Arrange ============
        $book = $this->fakeBook(10, 'DDD', 'Eric Evans'); // Modelo en memoria, sin DB

        // ============ Act ============
        // show retorna una instancia de BookResource (no JsonResponse)
        $resource = $this->controller->show($book);

        // ============ Assert ============
        $this->assertInstanceOf(BookResource::class, $resource);

        // Materializamos el recurso a array; toArray() usa la Request actual (helper request())
        // La request fue bindeada en setUp(): es una request mínima de prueba
        $array = $resource->toArray(request());
        $this->assertArrayHasKey('id', $array);
        $this->assertSame(10, $array['id']);
    }

    /** @test */
    public function update_deberia_actualizar_libro_y_responder_200_usando_el_servicio()
    {
        // ============ Arrange ============
        $book = $this->fakeBook(7, 'Old Title', 'Anon');        // Entidad existente (en memoria)
        $payload = ['title' => 'New Title'];                    // Datos que llegarían por HTTP

        // Mock del UpdateBookRequest: solo nos interesa validated()
        $request = Mockery::mock(UpdateBookRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($payload);

        // Expectativa: el servicio recibe la entidad y el payload, y devuelve la entidad actualizada
        $updated = $this->fakeBook(7, 'New Title', 'Anon');
        $this->bookService->shouldReceive('updateBook')
            ->once()
            ->with($book, $payload)
            ->andReturn($updated);

        // ============ Act ============
        $response = $this->controller->update($request, $book);

        // ============ Assert ============
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertSame(7, $json['data']['id']);
    }

    /** @test */
    public function destroy_deberia_eliminar_libro_y_responder_204()
    {
        // ============ Arrange ============
        $book = $this->fakeBook(3, 'Any', 'Any');

        // El controlador debe delegar la eliminación al servicio
        $this->bookService->shouldReceive('deleteBook')
            ->once()
            ->with($book);

        // ============ Act ============
        // destroy retorna response()->noContent() (204). Ese helper depende del ResponseFactory
        // que registramos en setUp() via RoutingServiceProvider.
        $response = $this->controller->destroy($book);

        // ============ Assert ============
        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent()); // 204 no lleva body
    }

    // ----------------- Helpers -----------------

    /**
     * Crea un Book "en memoria" sin persistir (unit test puro, sin DB).
     * - Evitamos $fillable, casts, y conexión: solo seteamos atributos necesarios.
     * - Marcamos exists=true si quisiéramos simular un modelo ya persistido.
     */
    private function fakeBook(int $id, string $title, string $author): Book
    {
        $book = new Book();
        $book->id = $id;       // Asignación directa: suficiente para asserts
        $book->title = $title;
        $book->author = $author;
        $book->exists = true;  // Sugerencia: indica que "existe" sin ir a DB (no requerido aquí)
        return $book;
    }
}
