<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // ИСПРАВЛЕНО: words() возвращает массив, нужно склеить его в строку
            'name' => implode(' ', fake()->words(2)), 
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 5000),
            // category_id будет установлен вручную в ProductSeeder, 
            // поэтому здесь можно оставить null или любое значение, оно перезапишется.
            'category_id' => null, 
        ];
    }
}