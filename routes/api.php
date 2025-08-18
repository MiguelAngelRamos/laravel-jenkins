<?php

use App\Http\Controllers\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Este archivo define las rutas de la API RESTful de la biblioteca.
| Aquí se registran los endpoints que permiten interactuar con los recursos
| del sistema, como libros y utilidades de salud.
|
| Todas las rutas aquí definidas están bajo el grupo 'api', lo que significa
| que estarán disponibles bajo el prefijo /api en la URL.
|
| Principales rutas:
| - Route::apiResource('books', BookController::class):
|   Genera automáticamente las rutas RESTful para el recurso libros, enlazando
|   los métodos del controlador BookController (index, show, store, update, destroy).
| - Route::get('ping', ...):
|   Ruta de salud para verificar rápidamente que la API está activa y responde.
|   Útil para monitoreo y pruebas rápidas.
|
| Puedes agregar aquí más rutas personalizadas según las necesidades del proyecto.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rutas para la API de libros
Route::apiResource('books', BookController::class);

// Ruta de salud para probar rápido
Route::get('ping', fn () => ['pong' => now()->toISOString()]);
