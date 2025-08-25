<?php

namespace App\Repositories;

use App\Models\Book;

class EloquentBookRepository implements BookRepository
{
    public function create(array $data): Book
    {
        return Book::create($data);
    }

    public function update(Book $book, array $data): Book
    {
        // 1. Se actualiza la base de datos
        $book->update($data);

        // 2. Se refresca el objeto $book desde la base de datos
        $book->refresh();

        // 3. Se devuelve el objeto ya con los datos nuevos ("Richard")
        return $book;
    }

    public function delete(Book $book): void
    {
        $book->delete();
    }
}

