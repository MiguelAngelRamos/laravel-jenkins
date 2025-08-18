<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * IMPORTANTE PARA ESTUDIANTES:
     * Por defecto, este método retorna false, lo que provoca que Laravel responda
     * con un error 403 Forbidden y no permita ejecutar el controlador ni la validación.
     * Para permitir el acceso a la API y que la validación funcione, debes retornar true.
     *
     * Si en el futuro quieres agregar lógica de autorización (por ejemplo, solo usuarios
     * autenticados pueden actualizar libros), puedes implementar esa lógica aquí.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTA IMPORTANTE SOBRE LA VALIDACIÓN DE ISBN AL ACTUALIZAR:
     *
     * Cuando se actualiza un libro, la regla 'unique:books,isbn,<id>' le indica a Laravel
     * que el campo ISBN debe ser único en la tabla books, EXCEPTO para el libro con este id.
     * Esto significa que, para el libro que se está editando, se permite que el ISBN sea el mismo
     * que ya tiene registrado, sin que se considere duplicado.
     *
     * Si intentas poner un ISBN que ya existe en otro libro (con diferente id), la validación FALLA
     * y no te deja guardar ese ISBN. Pero si el ISBN es el mismo que el del libro actual, la validación PASA.
     *
     * Así, se protege la unicidad del ISBN en toda la tabla, pero se permite que el libro mantenga su propio ISBN
     * al actualizar sus datos.
     *
     * Explicación sobre sometimes y nullable en las reglas de validación:
     *
     * - sometimes: Esta regla indica que la validación solo se aplica si el campo está presente en la solicitud.
     *   Es útil en actualizaciones parciales (PATCH/PUT), permitiendo enviar solo los campos que se desean modificar.
     *   Si el campo no se envía, no se valida ni se requiere.
     *
     * - nullable: Permite que el campo acepte valores nulos. Si el campo se envía con valor null, la validación no falla.
     *   Es útil para campos opcionales, como 'description', donde se puede omitir o enviar como null.
     *
     * Ejemplo:
     *   'description' => ['nullable','string']
     *   Permite que 'description' sea null o un string válido.
     */
    public function rules(): array
    {
        $id = $this->route('book'); // por Route Model Binding
        return [
            'title' => ['sometimes','string','max:255'],
            'author' => ['sometimes','string','max:255'],
            'published_year' => ['sometimes','integer','between:1500,'.date('Y')],
            'isbn' => ['sometimes','string','max:20','unique:books,isbn,'.($id?->id ?? $id)],
            'description' => ['nullable','string'],
        ];
    }
}
