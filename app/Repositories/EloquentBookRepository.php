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
        $book->update($data);
        return $book;
    }

    public function delete(Book $book): void
    {
        $book->delete();
    }
}

