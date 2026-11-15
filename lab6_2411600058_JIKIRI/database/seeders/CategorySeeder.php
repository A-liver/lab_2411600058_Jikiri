<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Meat & Poultry' => 'Fresh and chilled meat, kept in the walk-in cooler.',
            'Seafood' => 'Fish and shellfish, delivered fresh or frozen.',
            'Vegetables & Produce' => 'Fresh vegetables and salad greens.',
            'Dairy & Eggs' => 'Milk, cheese, butter and eggs.',
            'Beverages' => 'Soft drinks, water and coffee.',
            'Dry Goods & Spices' => 'Flour, rice and dry seasonings.',
            'Bakery & Bread' => 'Bread and rolls from the daily bake.',
        ];

        foreach ($categories as $name => $description) {
            Category::updateOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
