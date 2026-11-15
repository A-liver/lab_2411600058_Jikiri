<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_product_and_inventory_value_cards_link_to_their_details(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="' . route('products.index') . '"', false)
            ->assertSee('href="' . route('reports.index', ['report' => 'valuation']) . '"', false);
    }

    public function test_topbar_welcome_menu_contains_profile_and_logout(): void
    {
        $user = User::factory()->create(['name' => 'Inventory Admin']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Inventory Admin')
            ->assertSee('href="' . route('profile.edit') . '"', false)
            ->assertSee('action="' . route('logout') . '"', false)
            ->assertSee('class="nav-item logout-nav-item"', false);
    }

    public function test_dashboard_shows_inventory_sections_without_charts(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Dry Goods']);
        $product = Product::create([
            'name' => 'Rice',
            'sku' => 'RICE-CHART',
            'category_id' => $category->id,
            'quantity' => 10,
            'reorder_level' => 2,
            'unit_price' => 2.5,
        ]);

        InventoryTransaction::record($product, InventoryTransaction::TYPE_IN, 2, userId: $user->id);
        InventoryTransaction::record($product, InventoryTransaction::TYPE_OUT, 3, userId: $user->id);
        InventoryTransaction::record($product, InventoryTransaction::TYPE_ADJUSTMENT, -1, userId: $user->id);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Stock by Category')
            ->assertSee('Stock by Brand')
            ->assertSee('Recent Movements')
            ->assertDontSee('<canvas', false)
            ->assertDontSee('chart.umd.min.js')
            ->assertDontSee('stockStatusChart')
            ->assertDontSee('topProductsChart')
            ->assertDontSee('stockMovementChart');
    }
}
