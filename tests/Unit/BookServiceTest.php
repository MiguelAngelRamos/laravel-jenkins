<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Repositories\BookRepository;
use App\Services\BookService;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas UNITARIAS del servicio usando mocks de PHPUnit.
 *
 * Claves didácticas:
 * - Patrón Repository: desacopla el servicio de Eloquent y de la BD.
 * - Inyección por constructor (DI): permite pasar un mock del repositorio al servicio.
 *   Así, probamos SOLO la lógica/contrato del servicio sin tocar la base de datos.
 * - Estructura AAA (Arrange, Act, Assert) y una sola responsabilidad por test.
 */
class BookServiceTest extends TestCase
{
    /** @test */
    public function it_creates_a_book_via_repository()
    {
        // Arrange: datos de entrada simulados (no se toca BD)
        $data = [
            'title' => 'Clean Architecture',
            'author' => 'Robert C. Martin',
            'published_year' => 2017,
            'isbn' => '9780134494166',
            'description' => 'Guide to software structure and discipline.'
        ];

        // Creamos un Book como valor de retorno simulado del repositorio
        $expected = new Book($data);

        // Mock del contrato BookRepository (NO la implementación concreta):
        // esto permite validar que el servicio invoca al repositorio con el payload correcto
        // "payload": es el conjunto de datos de entrada que el servicio DEBE enviar al repositorio (aquí, $data).
        $repo = $this->createMock(BookRepository::class);
        $repo->expects($this->once()) // "delegar exactamente una vez": si se invoca 0 o >1 veces, la prueba falla
            ->method('create')        // método del contrato que esperamos que se invoque
            ->with($this->equalTo($data)) // validamos que reciba el payload exacto (mismo contenido)
            ->willReturn($expected);  // stub del resultado: el mock devuelve este Book (sin tocar BD)
        // Nota: los mocks no se limitan al "happy path"; también puedes simular fallos, por ej.:
        // ->willThrowException(new \RuntimeException('Fallo al crear')) y validar el manejo de errores.

        // Inyección de dependencias: pasamos el mock al constructor del servicio.
        // Gracias a DI + Repository, el servicio es fácilmente testeable con mocks.
        $service = new BookService($repo);

        // Act: ejecutamos la unidad a probar
        // PUNTO CLAVE: esta llamada dispara la simulación del mock.
        // - El servicio delega en $repo->create($data) (mock), NO accede a la BD real.
        // - El mock valida las expectativas configuradas: expects(once) y with($data).
        // - Luego retorna el valor stubbed via willReturn($expected).
        // - PHPUnit verifica estas expectativas al finalizar el test; si no se cumplen, la prueba falla.
        $book = $service->createBook($data);

        // Assert: verificamos el contrato de salida sin tocar la BD
        $this->assertInstanceOf(Book::class, $book);
        $this->assertSame('Clean Architecture', $book->title);
        $this->assertSame('Robert C. Martin', $book->author);
        $this->assertSame(2017, $book->published_year);
        $this->assertSame('9780134494166', $book->isbn);
        // Además de equalTo, existen:
        // - identicalTo($obj): exige la MISMA instancia;
        // - never(): para expresar que NO debe invocarse un método en determinado flujo.
    }

    /** @test */
    public function it_updates_a_book_via_repository()
    {
        /**
         * Arrange
         * - Creamos un modelo Book en memoria (no persistido) que actuará como entidad a actualizar.
         */
        $book = new Book([
            'title' => 'Old',          // estado inicial del título
            'author' => 'Author',      // autor inicial
            'published_year' => 2000,  // año inicial
            'isbn' => '1234567890123', // isbn inicial
        ]);

        /**
         * "Payload" (datos de entrada):
         * - Es el conjunto de campos y valores que el servicio enviará al repositorio para ejecutar la operación.
         * - Debe contener SOLO lo que queremos cambiar, manteniendo el contrato claro y explícito.
         * - Aquí representa la actualización parcial: título y año publicados.
         */
        $payload = [
            'title' => 'New',          // nuevo título que deseamos guardar
            'published_year' => 2020,  // nuevo año
        ];

        /**
         * Simulación del resultado que devolvería el repositorio tras aplicar la actualización.
         * - Tomamos el array actual del $book y le "mezclamos" el $payload para reflejar el cambio.
         * - No se toca la BD: es puramente en memoria.
         */
        $updated = new Book(array_merge($book->toArray(), $payload));

        /*
         Mock del repositorio (explicación simple con ejemplo):
         Caso de uso: queremos ACTUALIZAR un libro para que su título sea 'New' y su año 2020.
         ¿Qué esperamos que haga el servicio?
         - Que llame UNA sola vez al repositorio ->update(...).
         - Que le pase EXACTAMENTE el mismo objeto $book (no una copia),
           porque es ese libro el que queremos modificar.
         - Que le pase los datos del cambio tal cual los armamos en $payload
           (mismo contenido: título 'New' y published_year 2020).
         ¿Qué devolvemos en la simulación?
         - Fingimos que el repositorio devuelve $updated, es decir, el libro ya con esos cambios aplicados.
        */
        $repo = $this->createMock(BookRepository::class); // creamos el "doble" (simulación) del repositorio
        $repo->expects($this->once())                     // debe llamarse exactamente 1 vez; si 0 o >1, falla el test
            ->method('update')                            // el método del repositorio que esperamos que se invoque
            ->with(
                $this->identicalTo($book),                // MISMA instancia $book (identidad), no otra distinta
                $this->equalTo($payload)                  // Mismo contenido que $payload (por valor)
            )
            ->willReturn($updated);                       // el mock responde con el libro ya actualizado ($updated)

        // Inyectamos el mock en el servicio (DI) para aislar la unidad bajo prueba del acceso a datos real
        $service = new BookService($repo);

        /**
         * Act
         * - Ejecutamos la operación a probar. Esta llamada DISPARA la simulación del mock:
         *   el servicio delega en $repo->update($book, $payload) y el mock aplica las expectativas.
         */
        $result = $service->updateBook($book, $payload);

        /**
         * Assert
         * - Verificamos tipo y que los campos modificados tengan los valores esperados.
         */
        $this->assertInstanceOf(Book::class, $result);
        $this->assertSame('New', $result->title);
        $this->assertSame(2020, $result->published_year);
    }

    /** @test */
    public function it_deletes_a_book_via_repository()
    {
        // Arrange: preparamos el escenario de prueba
        // Creamos un Book en memoria (NO se persiste en BD); será el que "eliminaremos".
        $book = new Book([
            'title' => 'Any',            // título de ejemplo (no afecta la lógica del borrado)
            'author' => 'Any',           // autor de ejemplo
            'published_year' => 1999,    // año de ejemplo
            'isbn' => '9999999999999',   // isbn de ejemplo; nos sirve para identificar el objeto
        ]);

        // Creamos un mock del repositorio (doble de prueba): no toca la base de datos real
        $repo = $this->createMock(BookRepository::class); // simulación del contrato BookRepository
        // Definimos EXPECTATIVAS sobre el mock:
        // - Debe llamarse exactamente UNA VEZ al método delete
        // - Debe recibir la MISMA instancia de $book (identidad, no copia)
        $repo->expects($this->once())                 // esperamos 1 sola invocación; si 0 o >1, la prueba falla
            ->method('delete')                        // el método concreto que debe invocarse en el repositorio
            ->with($this->identicalTo($book));        // el argumento debe ser el mismo objeto $book (identicalTo)

        // Inyectamos el mock en el servicio (Dependency Injection)
        // De esta forma, el servicio delega en el mock y NO en Eloquent/BD real.
        $service = new BookService($repo);

        // Act: ejecutamos la unidad bajo prueba
        // Esta llamada dispara la invocación al mock: $repo->delete($book)
        $service->deleteBook($book);

        // Assert: si las expectativas del mock no se cumplieran, PHPUnit marcaría fallo
        // Añadimos una aserción explícita para dejar constancia en el conteo
        $this->addToAssertionCount(1);
    }
}
