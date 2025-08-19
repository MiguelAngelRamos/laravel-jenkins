<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\BookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * NOTA IMPORTANTE SOBRE PRUEBAS DE INTEGRACIÓN EN LARAVEL Y PHPUNIT:
     *
     * Este archivo contiene pruebas de integración (feature), no pruebas unitarias puras.
     * ¿Por qué?
     * - Usa el trait RefreshDatabase, que crea y limpia la base de datos antes de cada prueba.
     * - Los métodos del servicio BookService interactúan directamente con Eloquent y la base de datos real.
     * - Las aserciones como assertDatabaseHas y assertDatabaseMissing verifican el estado real de la base de datos.
     *
     * En el contexto de PHPUnit y Laravel, esto se considera integración porque:
     * - Se prueba el funcionamiento conjunto del servicio, el modelo y la base de datos.
     * - Se valida que los datos realmente se creen, actualicen y eliminen en la base de datos.
     * - No se usan mocks ni stubs para simular el modelo o la base de datos.
     *
     * Tecnologías y componentes de integración usados:
     * - PHPUnit: framework de pruebas que ejecuta los tests y provee aserciones.
     * - Laravel Eloquent: ORM que interactúa con la base de datos.
     * - RefreshDatabase: trait de Laravel que gestiona la base de datos de pruebas.
     * - SQLite (por defecto): base de datos usada en entorno de testing.
     *
     * Justificación:
     * Estas pruebas aseguran que el servicio BookService funciona correctamente en conjunto con la base de datos y el modelo,
     * detectando errores de integración y problemas reales que no se verían en pruebas unitarias puras.
     *
     * Si se usaran mocks para el modelo Book y no se accediera a la base de datos, serían pruebas unitarias.
     *
     * Mantener estas pruebas en la carpeta Feature es la convención correcta en Laravel.
     */

    /** @test */
    public function it_creates_a_book_with_valid_data()
    {
        // Arrange
        $service = $this->app->make(\App\Services\BookService::class);
        $data = [
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'published_year' => 2008,
            'isbn' => '9780132350884',
            'description' => 'A handbook of agile software craftsmanship.'
        ];

        // Act
        $book = $service->createBook($data);

        // Assert
        $this->assertInstanceOf(Book::class, $book);
        $this->assertDatabaseHas('books', [
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'published_year' => 2008,
            'isbn' => '9780132350884',
        ]);
    }

    /** @test */
    public function it_updates_a_book_with_valid_data()
    {
        // Arrange
        $service = $this->app->make(\App\Services\BookService::class);
        $book = Book::factory()->create([
            'title' => 'Old Title',
            'author' => 'Author',
            'published_year' => 2000,
            'isbn' => '1234567890123',
        ]);
        $data = [
            'title' => 'New Title',
            'published_year' => 2020,
        ];

        // Act
        $updatedBook = $service->updateBook($book, $data);

        // Assert
        $this->assertEquals('New Title', $updatedBook->title);
        $this->assertEquals(2020, $updatedBook->published_year);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'New Title',
            'published_year' => 2020,
        ]);
    }

    /** @test */
    public function it_deletes_a_book()
    {
        // Arrange
        $service = $this->app->make(\App\Services\BookService::class);
        $book = Book::factory()->create();

        // Act
        $service->deleteBook($book);

        // Assert
        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }
}
