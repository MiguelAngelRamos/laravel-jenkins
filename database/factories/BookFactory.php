<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Título ficticio de 3 palabras
            'title' => fake()->sentence(3),
            // Nombre aleatorio del autor
            'author' => fake()->name(),
            // Año de publicación entre 1980 y el actual
            'published_year' => (int) fake()->numberBetween(1980, (int) date('Y')),
            // ISBN-13 único
            'isbn' => fake()->unique()->isbn13(),
            // Descripción opcional
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
