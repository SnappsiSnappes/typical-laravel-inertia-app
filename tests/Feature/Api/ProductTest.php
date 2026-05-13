<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\User;

// Группа тестов для продукта
beforeEach(function () {
    // Этот код выполнится перед КАЖДЫМ тестом в этом файле
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();
});

// ==================== ПУБЛИЧНЫЕ ТЕСТЫ (не требуют авторизации) ====================

test('anyone can get list of products', function () {
    // Создаём 5 товаров в БД
    Product::factory()->count(5)->create(['category_id' => $this->category->id]);

    // Делаем запрос (как гость)
    $response = $this->get('/api/products');

    // Проверяем ответ
    $response->assertOk()                    // Статус 200
             ->assertJsonCount(5, 'data')    // В массиве "data" ровно 5 товаров
             ->assertJsonStructure([         // Проверяем структуру ответа
                 'data' => [
                     '*' => ['id', 'name', 'price', 'category_id', 'category']
                 ],
                 'current_page',
                 'last_page',
                 'total',
             ]);
});

test('anyone can get single product by id', function () {
    $product = Product::factory()->create([
        'name' => 'Test iPhone',
        'price' => 999.99,
        'category_id' => $this->category->id,
    ]);

    $response = $this->get("/api/products/{$product->id}");

    $response->assertOk()
             ->assertJson([
                 'id' => $product->id,
                 'name' => 'Test iPhone',
                 'price' => 999.99,
             ])
             ->assertJsonFragment(['name' => $this->category->name]); // Категория внутри
});

test('returns 404 when product not found', function () {
    $response = $this->get('/api/products/99999'); // ID, которого нет
    $response->assertNotFound(); // Статус 404
});

// ==================== ЗАЩИЩЁННЫЕ ТЕСТЫ (требуют авторизации) ====================

test('guest cannot create product', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',  // <-- Ключевая строка!
    ])->post('/api/products', [
        'name' => 'Hacker Product',
        'price' => 1,
        'category_id' => $this->category->id,
    ]);

    // Теперь будет 401, а не 302
    $response->assertUnauthorized();
});

test('authenticated user can create product', function () {
    $response = $this->actingAs($this->user) // «Входим» как пользователь
        ->post('/api/products', [
            'name' => 'New Awesome Product',
            'description' => 'Very good',
            'price' => 149.99,
            'category_id' => $this->category->id,
        ]);

    $response->assertCreated() // Статус 201 — создано
             ->assertJson([
                 'name' => 'New Awesome Product',
                 'price' => 149.99,
             ]);

    // Дополнительно: проверяем, что товар реально появился в БД
    $this->assertDatabaseHas('products', [
        'name' => 'New Awesome Product',
        'price' => 149.99,
    ]);
});

test('product validation: name is required', function () {
    $response = $this->withHeaders([
        'Accept' => 'application/json',  // <-- Обязательно!
    ])->actingAs($this->user)
        ->post('/api/products', [
            // name пропущен!
            'price' => 100,
            'category_id' => $this->category->id,
        ]);

    $response->assertUnprocessable() // Статус 422
             ->assertJsonValidationErrors(['name']); // Ошибка в поле "name"
});

test('authenticated user can update product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->user)
        ->put("/api/products/{$product->id}", [
            'name' => 'Updated Name',
            'price' => 299.99,
            'category_id' => $this->category->id,
        ]);

    $response->assertOk()
             ->assertJson(['name' => 'Updated Name']);

    // Проверяем в БД
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
        'price' => 299.99,
    ]);
});

test('authenticated user can delete product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->user)
        ->delete("/api/products/{$product->id}");

    $response->assertNoContent(); // Статус 204 — удалено, без тела ответа

    // Проверяем, что товара больше нет в БД
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

// ==================== ФИЛЬТРАЦИЯ ====================

test('products can be filtered by category', function () {
    $category1 = Category::factory()->create(['name' => 'Electronics']);
    $category2 = Category::factory()->create(['name' => 'Books']);

    Product::factory()->count(3)->create(['category_id' => $category1->id]);
    Product::factory()->count(2)->create(['category_id' => $category2->id]);

    // Запрашиваем только товары категории 1
    $response = $this->get("/api/products?category_id={$category1->id}");

    $response->assertOk()
             ->assertJsonCount(3, 'data') // Должно быть ровно 3 товара
             ->assertJsonFragment(['name' => 'Electronics']); // И все они из этой категории
});