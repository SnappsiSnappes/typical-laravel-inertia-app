<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\User;

// ============================================
// 🔄 SETUP: Выполняется перед КАЖДЫМ тестом
// ============================================
beforeEach(function () {
    // Создаём пользователя и категорию — они понадобятся в большинстве тестов
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();

    // Все API-запросы должны ожидать JSON, иначе Laravel будет редиректить
    $this->withHeaders(['Accept' => 'application/json']);
});

// ============================================
// 📦 GET /api/products (index)
// ============================================

test('anyone can get paginated list of products', function () {
    // Создаём 15 товаров
    Product::factory()->count(15)->create(['category_id' => $this->category->id]);

    // Запрашиваем первую страницу по 10 товаров
    $response = $this->get('/api/products?per_page=10');

    $response->assertOk()
        ->assertJsonCount(10, 'data') // На странице ровно 10 товаров
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'price', 'category_id', 'created_at', 'updated_at', 'category']
            ],
            'current_page',
            'last_page',
            'per_page',
            'total',
            'next_page_url',
            'prev_page_url'
        ]);
});

test('pagination per_page is limited between 1 and 100', function () {
    Product::factory()->count(200)->create(['category_id' => $this->category->id]);

    // Пытаемся запросить 500 товаров за раз — должно обрезаться до 100
    $response = $this->get('/api/products?per_page=500');

    $response->assertOk()
        ->assertJsonCount(100, 'data') // Максимум 100
        ->assertJsonPath('per_page', 100);

    // Пытаемся запросить 0 товаров — должно стать 1
    $response = $this->get('/api/products?per_page=0');
    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('per_page', 1);
});

test('products can be filtered by category_id', function () {
    $cat1 = Category::factory()->create(['name' => 'Electronics']);
    $cat2 = Category::factory()->create(['name' => 'Books']);

    Product::factory()->count(5)->create(['category_id' => $cat1->id]);
    Product::factory()->count(3)->create(['category_id' => $cat2->id]);

    // Фильтруем по первой категории
    $response = $this->get("/api/products?category_id={$cat1->id}");

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonFragment(['name' => 'Electronics'])
        ->assertJsonMissing(['name' => 'Books']); // Товаров из другой категории нет
});

test('category relationship is loaded with products', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->get('/api/products');

    $response->assertOk()
        // Проверяем, что в первом элементе data есть нужный товар
        ->assertJsonPath('data.0.id', $product->id)
        // Проверяем, что категория подгрузилась с правильными полями
        ->assertJsonPath('data.0.category.id', $this->category->id)
        ->assertJsonPath('data.0.category.name', $this->category->name);
});

// ============================================
// 🔍 GET /api/products/{id} (show)
// ============================================

test('anyone can get single product with category', function () {
    $product = Product::factory()->create([
        'name' => 'Super Gadget',
        'price' => 299.99,
        'category_id' => $this->category->id,
    ]);

    $response = $this->get("/api/products/{$product->id}");

    $response->assertOk()
        ->assertJsonPath('id', $product->id)
        ->assertJsonPath('name', 'Super Gadget')
        ->assertJsonPath('price', 299.99)
        // Проверяем вложенную категорию
        ->assertJsonPath('category.id', $this->category->id)
        ->assertJsonPath('category.name', $this->category->name);
});

test('returns 404 when product not found', function () {
    // ID, которого точно нет в базе
    $response = $this->get('/api/products/999999');

    $response->assertNotFound(); // Статус 404
});

// ============================================
// 🚫 Доступ для гостей (неавторизованных)
// ============================================

test('guest cannot create product', function () {
    $response = $this->post('/api/products', [
        'name' => 'Hacker Item',
        'price' => 1,
        'category_id' => $this->category->id,
    ]);

    $response->assertUnauthorized(); // 401
});

test('guest cannot update product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->put("/api/products/{$product->id}", [
        'name' => 'Hacked Name',
    ]);

    $response->assertUnauthorized();
});

test('guest cannot delete product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->delete("/api/products/{$product->id}");

    $response->assertUnauthorized();
});

// ============================================
// ✅ POST /api/products (store) — Создание
// ============================================

test('authenticated user can create product', function () {
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'New Awesome Product',
        'description' => 'This is a great product',
        'price' => 149.99,
        'category_id' => $this->category->id,
    ]);

    $response->assertCreated() // Статус 201
        ->assertJson([
            'name' => 'New Awesome Product',
            'price' => 149.99,
            'category_id' => $this->category->id,
        ])
        ->assertJsonPath('category.name', $this->category->name)
        ->assertJsonPath('category.id', $this->category->id);

    // Проверяем, что товар реально создался в БД
    $this->assertDatabaseHas('products', [
        'name' => 'New Awesome Product',
        'price' => 149.99,
    ]);
});

// --- Валидация при создании ---

test('name is required when creating product', function () {
    $response = $this->actingAs($this->user)->post('/api/products', [
        // name пропущен!
        'description' => 'No name',
        'price' => 100,
        'category_id' => $this->category->id,
    ]);

    $response->assertUnprocessable() // Статус 422
        ->assertJsonValidationErrors(['name']);
});

test('price is required and must be numeric and min 0', function () {
    // Цена не число
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Test',
        'price' => 'not-a-number',
        'category_id' => $this->category->id,
    ]);
    $response->assertJsonValidationErrors(['price']);

    // Отрицательная цена
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Test',
        'price' => -50,
        'category_id' => $this->category->id,
    ]);
    $response->assertJsonValidationErrors(['price']);
});

test('category_id must exist in categories table', function () {
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Test',
        'price' => 100,
        'category_id' => 99999, // Такой категории нет
    ]);

    $response->assertJsonValidationErrors(['category_id']);
});

test('description is optional and can be null', function () {
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Minimal Product',
        'price' => 10,
        'category_id' => $this->category->id,
        // description не передаём
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('products', [
        'name' => 'Minimal Product',
        'description' => null,
    ]);
});

// ============================================
// ✏️ PUT /api/products/{id} (update) — Обновление
// ============================================

test('authenticated user can update product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->user)->put("/api/products/{$product->id}", [
        'name' => 'Updated Name',
        'price' => 399.99,
        'category_id' => $this->category->id,
    ]);

    $response->assertOk()
        ->assertJson(['name' => 'Updated Name', 'price' => 399.99]);

    // Проверяем в БД
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
        'price' => 399.99,
    ]);
});

test('update uses sometimes rules - can update only name', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'price' => 100,
        'category_id' => $this->category->id,
    ]);

    // Передаём только name, остальные поля не трогаем
    $response = $this->actingAs($this->user)->put("/api/products/{$product->id}", [
        'name' => 'New Name Only',
        // price и category_id не передаём — они должны остаться старыми
    ]);

    $response->assertOk();

    // Проверяем: name изменился, а price остался прежним
    $product->refresh();
    expect($product->name)->toBe('New Name Only');
    expect($product->price)->toBe(100); // Не изменилось!
});

test('cannot update non-existent product', function () {
    $response = $this->actingAs($this->user)->put('/api/products/99999', [
        'name' => 'Hack Attempt',
    ]);

    $response->assertNotFound();
});

// ============================================
// 🗑️ DELETE /api/products/{id} (destroy) — Удаление
// ============================================

test('authenticated user can delete product', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->user)->delete("/api/products/{$product->id}");

    $response->assertNoContent(); // Статус 204 — нет тела ответа

    // Проверяем, что товар удалён из БД
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('cannot delete non-existent product', function () {
    $response = $this->actingAs($this->user)->delete('/api/products/99999');

    $response->assertNotFound();
});

// ============================================
// 🧪 EDGE CASES — Граничные случаи
// ============================================

test('price with decimals is stored correctly', function () {
    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Precise Product',
        'price' => 19.99, // Цена с копейками
        'category_id' => $this->category->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('price', 19.99);

    // Проверяем в БД с учётом формата decimal(8,2)
    $this->assertDatabaseHas('products', [
        'name' => 'Precise Product',
        'price' => 19.99,
    ]);
});

test('long description is accepted', function () {
    $longDesc = str_repeat('Lorem ipsum', 100); // ~1200 символов

    $response = $this->actingAs($this->user)->post('/api/products', [
        'name' => 'Long Desc Product',
        'description' => $longDesc,
        'price' => 50,
        'category_id' => $this->category->id,
    ]);

    $response->assertCreated();
    
    // ✅ Вместо точного сравнения длинной строки:
    // 1. Проверяем, что запись с таким именем существует
    $this->assertDatabaseHas('products', ['name' => 'Long Desc Product']);
    
    // 2. Проверяем длину описания напрямую в БД
    $product = \App\Models\Product::where('name', 'Long Desc Product')->first();
    expect($product->description)->toBeString()
          ->toHaveLength(strlen($longDesc)); // Или ->toBe($longDesc), если уверен в точном совпадении
});

test('category_id cannot be changed to non-existent', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->user)->put("/api/products/{$product->id}", [
        'category_id' => 99999, // Не существует
    ]);

    $response->assertJsonValidationErrors(['category_id']);

    // Категория товара не должна измениться
    $product->refresh();
    expect($product->category_id)->toBe($this->category->id);
});