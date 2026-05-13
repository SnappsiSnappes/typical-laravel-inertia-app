<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);

        // Начинаем запрос
        $query = Product::with('category');

        // Если передан category_id — фильтруем
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        // ИСПРАВЛЕНО:
        // Мы используем уже найденный экземпляр $product.
        // load('category') загружает связь "на лету", если она еще не загружена.
        return response()->json($product->load('category'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        // validated() возвращает только разрешенные поля
        $product = Product::create($request->validated());

        // Возвращаем созданный товар с загруженной категорией
        return response()->json($product->load('category'), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json($product->load('category'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        // 204 No Content - стандартный ответ для успешного удаления без тела ответа
        return response()->json(null, 204);
    }
}