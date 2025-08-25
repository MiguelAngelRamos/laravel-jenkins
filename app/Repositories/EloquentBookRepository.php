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

        // 2. ¡SOLUCIÓN! Se obtiene una instancia completamente nueva y fresca del modelo desde la BBDD.
        //    fresh() es más robusto que refresh() en algunos casos límite.
        return $book->fresh();
    }

    public function delete(Book $book): void
    {
        $book->delete();
    }
}

