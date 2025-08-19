<?php

namespace App\Repositories;

use App\Models\Book;

interface BookRepository
{
    /**
     * Crea un libro y retorna la instancia persistida.
     *
     * @param array $data
     * @return Book
     */
    public function create(array $data): Book;

    /**
     * Actualiza un libro existente y retorna la instancia actualizada.
     *
     * @param Book $book
     * @param array $data
     * @return Book
     */
    public function update(Book $book, array $data): Book;

    /**
     * Elimina un libro existente.
     *
     * @param Book $book
     * @return void
     */
    public function delete(Book $book): void;
}

