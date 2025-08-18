<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Este método es el punto de entrada para poblar la base de datos con datos iniciales.
     * Utiliza el método call() para ejecutar otros seeders, como BookSeeder.
     *
     * La sintaxis BookSeeder::class utiliza el operador de resolución de ámbito (::) en PHP,
     * que en este contexto retorna el nombre completo de la clase BookSeeder como string.
     * Esto permite a Laravel instanciar la clase y ejecutar su método run (que NO es estático).
     *
     * Ejemplo:
     *   $this->call(BookSeeder::class);
     *   // Instancia BookSeeder y ejecuta su método run para poblar la tabla de libros.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call(BookSeeder::class);
    }
}
