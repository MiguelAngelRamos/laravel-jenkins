<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    /**
     * Book extiende Model y representa la entidad libro en la base de datos.
     *
     * Explicación sobre Eloquent y $fillable:
     * - Eloquent es el ORM de Laravel que permite interactuar con la base de datos usando modelos PHP.
     * - $fillable es un array que define qué atributos pueden ser asignados en masa (mass-assignment),
     *   es decir, cuando usas métodos como create() o update() pasando un array de datos, solo los campos
     *   definidos en $fillable serán guardados. Esto protege contra asignación de campos no deseados.
     *
     * Ejemplo:
     *   Book::create([...]); // Crea un libro usando asignación masiva
     *   $book->update([...]); // Actualiza un libro existente usando asignación masiva
     *
     * Métodos estáticos vs. de instancia:
     * - Book::create([...]) es un método estático de Eloquent que crea un nuevo registro y retorna el modelo.
     * - $book->update([...]) es un método de instancia que actualiza el registro actual.
     * - $book->delete() es un método de instancia que elimina el registro actual.
     *
     * No existe Book::update([...]) porque update solo tiene sentido en el contexto de un registro existente.
     * Por eso se usa $book->update([...]) para modificar un libro específico.
     *
     * Los métodos de instancia ($book->update, $book->delete) requieren que ya tengas una instancia del modelo,
     * normalmente obtenida por consulta o por Route Model Binding en el controlador.
     */

    /**
     * Atributos asignables en masa (mass-assignment).
     */
    protected $fillable = [
        'title',
        'author',
        'published_year',
        'isbn',
        'description',
    ];

    /**
     * Casting de tipos para entregar/recibir datos con tipos correctos.
     */
    protected $casts = [
        'published_year' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /*
     * Notas:
     * - La tabla por defecto será "books" (plural del nombre del modelo).
     * - Con Route Model Binding, {book} en rutas API resolverá a una instancia de Book.
     * - HasFactory permite usar database/factories/BookFactory.php en seeders/pruebas.
     */
}
