<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_api_requires_an_authenticated_user(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_dashboard_api_data(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Dry Goods']);
        $lowStock = Product::create([
            'name' => 'Rice',
            'sku' => 'RICE-1',
            'category_id' => $category->id,
            'quantity' => 2,
            'reorder_level' => 5,
            'unit_price' => 10,
        ]);
        $outOfStock = Product::create([
            'name' => 'Beans',
            'sku' => 'BEANS-1',
            'category_id' => $category->id,
            'quantity' => 0,
            'reorder_level' => 3,
            'unit_price' => 4,
        ]);

        $this->actingAs($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total_value', 20)
            ->assertJsonPath('data.low_stock', 1);

        $transaction = InventoryTransaction::record(
            $lowStock,
            InventoryTransaction::TYPE_IN,
            1,
            userId: $user->id,
        );

        $this->actingAs($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total_products', 2)
            ->assertJsonPath('data.total_value', 30)
            ->assertJsonPath('data.low_stock', 1)
            ->assertJsonPath('data.out_of_stock', 1)
            ->assertJsonPath('data.in_stock', 0);

        $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $outOfStock->id)
            ->assertJsonPath('data.0.status', 'out-of-stock');

        $this->getJson("/api/products/{$lowStock->id}/quantity")
            ->assertOk()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.reorder_level', 5);

        $this->getJson('/api/transactions/recent')
            ->assertOk()
            ->assertJsonPath('data.0.id', $transaction->id)
            ->assertJsonPath('data.0.change', 1)
            ->assertJsonPath('data.0.type_color', 'movement')
            ->assertJsonPath('data.0.product.name', 'Rice');
    }

    public function test_dashboard_renders_recent_movements_and_the_live_polling_script(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Recent Movements')
            ->assertSee('dashboard-poll.js')
            ->assertSee('\/api\/dashboard\/stats')
            ->assertSee('\/api\/products\/low-stock')
            ->assertSee('\/api\/transactions\/recent');
    }
}
