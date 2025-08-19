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
        // Arrange
        $book = new Book([
            'title' => 'Old',
            'author' => 'Author',
            'published_year' => 2000,
            'isbn' => '1234567890123',
        ]);

        $payload = [
            'title' => 'New',
            'published_year' => 2020,
        ];

        // Simulamos el Book actualizado que el repositorio devolvería
        $updated = new Book(array_merge($book->toArray(), $payload));

        // Mock del repositorio con expectativa sobre update:
        // - identicalTo: misma instancia de $book
        // - equalTo: contenido del payload
        $repo = $this->createMock(BookRepository::class);
        $repo->expects($this->once())
            ->method('update')
            ->with($this->identicalTo($book), $this->equalTo($payload))
            ->willReturn($updated);

        $service = new BookService($repo); // DI del mock

        // Act
        $result = $service->updateBook($book, $payload);

        // Assert
        $this->assertInstanceOf(Book::class, $result);
        $this->assertSame('New', $result->title);
        $this->assertSame(2020, $result->published_year);
    }

    /** @test */
    public function it_deletes_a_book_via_repository()
    {
        // Arrange
        $book = new Book([
            'title' => 'Any',
            'author' => 'Any',
            'published_year' => 1999,
            'isbn' => '9999999999999',
        ]);

        // Para métodos void no configuramos willReturn.
        // Solo validamos que se haya invocado con la instancia correcta.
        $repo = $this->createMock(BookRepository::class);
        $repo->expects($this->once())
            ->method('delete')
            ->with($this->identicalTo($book));

        $service = new BookService($repo); // DI del mock

        // Act
        $service->deleteBook($book);

        // Assert: si llegamos aquí, la expectativa del mock se cumplió
        $this->addToAssertionCount(1);
    }
}
