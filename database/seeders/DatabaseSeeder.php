<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Сначала создаем категории
        $this->call(CategorySeeder::class);
        
        // Затем создаем товары (они привяжутся к категориям)
        $this->call(ProductSeeder::class);
    }
}
