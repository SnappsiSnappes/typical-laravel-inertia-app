<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        if ($categories->isEmpty()) {
            $this->command->error('No categories found.');
            return;
        }

        foreach ($categories as $category) {
            // Создаем 4 товара для каждой категории
            // Мы передаем category_id напрямую в метод create() или state()
            Product::factory()
                ->count(4)
                ->create([
                    'category_id' => $category->id,
                ]);
        }

        $this->command->info('Products seeded successfully!');
    }
}