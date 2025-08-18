<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /*
     * Este método ejecuta el seeder para crear 20 libros usando la factory de Book.
     * Puedes modificar la cantidad o la lógica según tus necesidades.
     */
    public function run(): void
    {
        \App\Models\Book::factory(20)->create();
    }
}
