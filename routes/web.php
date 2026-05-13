<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;

// 1. ГЛАВНАЯ СТРАНИЦА
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// 2. ПУБЛИЧНЫЕ API МАРШРУТЫ (Без auth)
// Важно: Маршрут с параметром {product} должен быть понятен Laravel

// Получить список всех товаров (публично)
Route::get('/api/products', [ProductController::class, 'index']);

// Получить один товар по ID (публично)
// Обратите внимание: {product} — это имя параметра, оно должно совпадать 
// с именем переменной в методе контроллера (Route Model Binding)
Route::get('/api/products/{product}', [ProductController::class, 'show']);

// Категории (публично)
Route::get('/api/categories', [CategoryController::class, 'index']);


// 3. ЗАЩИЩЕННЫЕ API МАРШРУТЫ (Требуют auth:sanctum)
// Сюда переносим только те действия, которые меняют данные (POST, PUT, DELETE)
Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    // Создание, обновление, удаление теперь защищены
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}', [ProductController::class, 'update']); // Для частичного обновления
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
});

// 4. ЗАЩИЩЕННЫЕ WEB СТРАНИЦЫ (Inertia)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/admin/products', 'admin/Products')->name('AdminProducts');
});

require __DIR__.'/settings.php';