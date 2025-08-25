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

/*
|-------------------------------------------------------------
| ¿Qué es Illuminate\Http\Request?
|-------------------------------------------------------------
| Esta clase representa una petición HTTP en Laravel.
| - En producción, Laravel la instancia automáticamente cuando recibe una petición real.
| - En las rutas (como el closure de /user), Laravel inyecta el objeto Request con los datos reales.
| - En los tests unitarios, puedes crear manualmente una instancia de Request para simular una petición,
|   pero NO se envía nada por la red ni se ejecuta el ciclo HTTP completo.
| - Así, puedes probar la lógica de los controladores de forma aislada, sin dependencias externas.
| - Es útil para comprender la diferencia entre una petición real y una simulada en pruebas.
|-------------------------------------------------------------
*/
