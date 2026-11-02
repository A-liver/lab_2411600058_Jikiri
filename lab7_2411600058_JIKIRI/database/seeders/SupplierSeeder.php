<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        // Sample values, just to give the new module realistic data.
        $terms = ['Net 30', 'Net 15', 'COD', 'Net 45'];
        $leadTimes = [3, 5, 7, 10, 14];

        $names = Product::whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->distinct()
            ->orderBy('supplier')
            ->pluck('supplier');

        foreach ($names as $i => $name) {
            $supplier = Supplier::firstOrCreate(
                ['name' => $name],
                [
                    'contact_person' => 'Sales Desk',
                    'email' => Str::slug($name) . '@example.com',
                    'phone' => '0917 000 ' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'address' => 'Sample supplier address',
                    'payment_terms' => $terms[$i % count($terms)],
                    'lead_time_days' => $leadTimes[$i % count($leadTimes)],
                    'minimum_order_amount' => 50,
                    'is_active' => true,
                ]
            );

            foreach (Product::where('supplier', $name)->get() as $product) {
                $supplier->products()->syncWithoutDetaching([
                    $product->id => [
                        'unit_cost' => round($product->unit_price * 0.7, 2),   // sample: cost = 70% of selling price
                        'min_order_quantity' => max($product->reorder_level, 1),
                        'pack_size' => 1,
                        'is_preferred' => true,
                    ],
                ]);
            }
        }
    }
}