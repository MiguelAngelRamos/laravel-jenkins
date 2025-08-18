<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NOTA IMPORTANTE SOBRE VALIDACIÓN EN LARAVEL:
 *
 * Las reglas definidas en esta clase StoreBookRequest se aplican AUTOMÁTICAMENTE
 * antes de que los datos lleguen al controlador. Cuando el controlador recibe
 * una instancia de StoreBookRequest como parámetro, Laravel valida la solicitud
 * usando el método rules() de esta clase.
 *
 * Si la validación falla, el controlador NO se ejecuta y Laravel retorna una
 * respuesta con los errores de validación. Si la validación es exitosa, el
 * controlador recibe los datos ya validados y puede usarlos con confianza.
 *
 * Esto permite centralizar y asegurar la validación de datos en un solo lugar,
 * manteniendo el controlador limpio y seguro.
 */

/**
 * Esta clase StoreBookRequest extiende FormRequest y se utiliza en Laravel para validar
 * los datos enviados al crear un nuevo libro. Permite definir reglas de validación y
 * controlar la autorización de la solicitud.
 */
class StoreBookRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * IMPORTANTE PARA ESTUDIANTES:
     * Por defecto, este método retorna false, lo que provoca que Laravel responda
     * con un error 403 Forbidden y no permita ejecutar el controlador ni la validación.
     * Para permitir el acceso a la API y que la validación funcione, debes retornar true.
     *
     * Si en el futuro quieres agregar lógica de autorización (por ejemplo, solo usuarios
     * autenticados pueden crear libros), puedes implementar esa lógica aquí.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Devuelve las reglas de validación que se aplican a la solicitud.
     * Cada campo tiene reglas específicas:
     * - 'title': requerido, tipo string, máximo 255 caracteres.
     * - 'author': requerido, tipo string, máximo 255 caracteres.
     * - 'published_year': requerido, tipo entero, debe estar entre 1500 y el año actual (date('Y')).
     * - 'isbn': requerido, tipo string, máximo 20 caracteres, debe ser único en la tabla books.
     * - 'description': opcional (nullable), tipo string.
     * La función date('Y') obtiene el año actual dinámicamente.
     * La regla 'unique:books,isbn' asegura que no se repita el ISBN en la base de datos.
     */
    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'author' => ['required','string','max:255'],
            'published_year' => ['required','integer','between:1500,'.date('Y')],
            'isbn' => ['required','string','max:20','unique:books,isbn'],
            'description' => ['nullable','string'],
        ];
    }
}
