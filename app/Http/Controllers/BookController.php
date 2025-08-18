<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Utiliza el modelo Book para obtener los libros ordenados por fecha de creación (más recientes primero)
     * y los pagina de 10 en 10. Luego, transforma la colección usando BookResource para controlar el formato
     * de la respuesta JSON.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        // Obtenemos los libros más recientes y los paginamos
        $books = Book::query()->latest()->paginate(10);
        // Transformamos la colección de libros usando BookResource
        return BookResource::collection($books);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Recibe una instancia de StoreBookRequest, que valida los datos antes de llegar al controlador.
     * Usa Book::create para crear el libro con los datos validados. Book::create utiliza asignación masiva,
     * por lo que solo los campos definidos en $fillable del modelo Book serán guardados.
     *
     * Retorna el libro creado envuelto en BookResource, con código HTTP 201 (creado).
     *
     * @param StoreBookRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBookRequest $request)
    {
        // Creamos el libro con los datos validados
        $book = Book::create($request->validated());
        // Retornamos el libro creado en formato JSON y código 201
        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     *
     * Muestra un libro específico.
     *
     * Recibe el modelo Book por Route Model Binding, lo que significa que Laravel busca el libro
     * automáticamente por su id en la ruta y lo inyecta como parámetro. Retorna el libro transformado
     * por BookResource, que controla el formato de la respuesta JSON.
     *
     * BookResource permite personalizar qué campos se exponen y cómo se presentan en la API.
     *
     * @param Book $book
     * @return BookResource
     */
    public function show(Book $book)
    {
        // Retornamos el libro en formato JSON usando BookResource
        return new BookResource($book);
    }

    /**
     * Update the specified resource in storage.
     *
     * Actualiza los datos de un libro existente.
     *
     * Recibe una instancia de UpdateBookRequest, que valida los datos antes de llegar al controlador.
     * Usa el método de instancia $book->update([...]) para modificar solo los campos enviados y definidos en $fillable.
     *
     * Diferencia entre métodos estáticos y de instancia:
     * - Book::create([...]) crea un nuevo libro (registro nuevo).
     * - $book->update([...]) actualiza el libro actual (registro existente).
     * - $book->delete() elimina el libro actual.
     *
     * No existe Book::update([...]) porque update solo tiene sentido en el contexto de un registro existente.
     *
     * Retorna el libro actualizado envuelto en BookResource, con código HTTP 200 (actualizado).
     *
     * @param UpdateBookRequest $request
     * @param Book $book
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        // Actualizamos el libro con los datos validados
        $book->update($request->validated());
        // Retornamos el libro actualizado en formato JSON y código 200
        return (new BookResource($book))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Elimina un libro de la base de datos.
     *
     * Recibe el modelo Book por Route Model Binding y lo elimina físicamente de la base de datos
     * usando el método de instancia $book->delete(). Este método elimina el registro actual.
     *
     * Retorna una respuesta vacía con código HTTP 204 (sin contenido), que indica que la operación fue exitosa
     * y no hay datos que retornar.
     *
     * @param Book $book
     * @return \Illuminate\Http\Response
     */
    public function destroy(Book $book)
    {
        // Eliminamos el libro actual
        $book->delete();
        // Retornamos una respuesta vacía con código 204
        return response()->noContent();
    }
}
