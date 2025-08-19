<?php

namespace App\Services;

use App\Models\Book;
use App\Repositories\BookRepository;

class BookService
{
    public function __construct(private BookRepository $repository)
    {
    }

    /**
     * Crea un nuevo libro con los datos validados.
     */
    public function createBook(array $data): Book
    {
        return $this->repository->create($data);
    }

    /**
     * Actualiza un libro existente con los datos validados.
     */
    public function updateBook(Book $book, array $data): Book
    {
        return $this->repository->update($book, $data);
    }

    /**
     * Elimina un libro existente.
     */
    public function deleteBook(Book $book): void
    {
        $this->repository->delete($book);
    }
}
