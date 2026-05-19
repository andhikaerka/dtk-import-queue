<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Bisa diakses tanpa token)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Wajib membawa Bearer Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Autentikasi
    Route::post('/logout', [AuthController::class, 'logout']);

    // Fitur Utama Import Produk
    Route::post('/import/products', [ProductImportController::class, 'upload']);
    Route::get('/import/status/{id}', [ProductImportController::class, 'status']);
});