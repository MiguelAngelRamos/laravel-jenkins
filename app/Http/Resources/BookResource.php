<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BookResource extiende JsonResource y se utiliza para transformar el modelo Book
 * en una representación JSON personalizada para respuestas de API en Laravel.
 *
 * Permite controlar qué atributos del libro se exponen, ocultar campos sensibles,
 * modificar formatos o agregar información adicional según sea necesario.
 */
class BookResource extends JsonResource
{
    /**
     * Transforma el recurso Book en un array para la respuesta JSON.
     *
     * Este método selecciona y transforma los atributos del modelo Book:
     * - Devuelve directamente los campos principales: id, title, author, published_year, isbn.
     * - 'description' se incluye solo si no es null, usando whenNotNull.
     * - 'created_at' y 'updated_at' se formatean como string ISO si existen.
     *
     * Así, puedes personalizar la estructura y formato de los datos que se envían al cliente.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'published_year' => $this->published_year,
            'isbn' => $this->isbn,
            'description' => $this->whenNotNull($this->description),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
