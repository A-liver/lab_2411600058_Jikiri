<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_navigation_and_run_button_use_design_classes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/reports')
            ->assertOk()
            ->assertSee('report-tabs', false)
            ->assertSee('report-submit', false);
    }

    public function test_inventory_valuation_paginates_products(): void
    {
        $category = Category::create(['name' => 'Dry Goods']);

        for ($i = 1; $i <= 26; $i++) {
            Product::create([
                'name' => "Product {$i}",
                'sku' => "SKU-{$i}",
                'category_id' => $category->id,
                'quantity' => $i,
                'reorder_level' => 2,
                'unit_price' => 1,
            ]);
        }

        $response = $this->actingAs(User::factory()->create())
            ->get('/reports?report=valuation')
            ->assertOk()
            ->assertSee('Next')
            ->assertSee('₱351.00');

        $this->assertCount(25, $response->viewData('products'));
        $this->assertSame(26, $response->viewData('products')->total());
    }

    public function test_inventory_prices_use_php_currency_across_reports_and_product_pages(): void
    {
        $category = Category::create(['name' => 'Dry Goods']);
        $product = Product::create([
            'name' => 'Rice',
            'sku' => 'RICE-PRICE',
            'category_id' => $category->id,
            'quantity' => 10,
            'reorder_level' => 2,
            'unit_price' => 2.5,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/reports?report=valuation')
            ->assertOk()
            ->assertSee('₱25.00')
            ->assertSee('₱2.50');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('₱25.00');

        $this->get('/products')
            ->assertOk()
            ->assertSee('₱2.50')
            ->assertSee('₱25.00');

        $this->get("/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('Unit Price (₱)');
    }
}
