<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Sample brand names for the demo data. Replace them with your real brands.
     */
    public function run(): void
    {
        foreach (['Farmhouse', 'Prime Cut', 'Blue Harbor', 'Meadow', 'Greenrow', 'Hearth', 'Quenchly'] as $name) {
           Brand::firstOrCreate(['name' => $name], ['description' => 'Sample brand from the demo seeder.']);
        }
    }
}
