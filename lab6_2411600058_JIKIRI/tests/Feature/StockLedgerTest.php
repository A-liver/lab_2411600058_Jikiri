<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $quantity = 50, int $reorder = 10): Product
    {
        return Product::create([
            'name' => 'Test Rice',
            'sku' => 'T-' . uniqid(),
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'unit_price' => 2.5,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_stock_in_and_stock_out_change_the_quantity_and_balance(): void
    {
        $product = $this->product(50);

        InventoryTransaction::record($product, 'stock_in', 10);
        $this->assertSame(60, $product->fresh()->quantity);

        InventoryTransaction::record($product, 'stock_out', 25);
        $this->assertSame(35, $product->fresh()->quantity);
        $this->assertSame(35, InventoryTransaction::latest('id')->first()->balance_after);
    }

    public function test_stock_out_above_the_quantity_is_rejected_and_nothing_is_saved(): void
    {
        $product = $this->product(3);

        try {
            InventoryTransaction::record($product, 'stock_out', 4);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Only 3', $e->getMessage());
        }

        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_low_stock_alert_is_sent_once_when_stock_crosses_the_reorder_level(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $staff = User::factory()->create();
        $product = $this->product(15, 10);

        InventoryTransaction::record($product, 'stock_out', 6); // 15 to 9: crosses
        InventoryTransaction::record($product, 'stock_out', 1); // already low: no second alert

        Notification::assertSentToTimes($admin, LowStockNotification::class, 1);
        Notification::assertNotSentTo($staff, LowStockNotification::class);
    }

    public function test_low_stock_alert_is_saved_for_the_admin_without_a_queue_worker(): void
    {
        $admin = $this->admin();
        $product = $this->product(15, 10);

        InventoryTransaction::record($product, 'stock_out', 6, userId: $admin->id);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
            'type' => LowStockNotification::class,
        ]);
    }

    public function test_admin_can_adjust_stock_to_a_physical_count(): void
    {
        $product = $this->product(50);

        $this->actingAs($this->admin())->post(route('adjustments.store'), [
            'product_id' => $product->id,
            'system_quantity' => 50,
            'physical_count' => 42,
            'reason' => 'damaged',
        ])->assertRedirect();

        $this->assertSame(42, $product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -8,
            'balance_after' => 42,
            'reference' => 'Damaged goods',
        ]);
    }

    public function test_adjustment_is_refused_when_stock_changed_while_counting(): void
    {
        $product = $this->product(50);

        $this->actingAs($this->admin())->post(route('adjustments.store'), [
            'product_id' => $product->id,
            'system_quantity' => 45, // what the screen showed; the real number is 50
            'physical_count' => 40,
            'reason' => 'count',
        ])->assertSessionHasErrors('physical_count');

        $this->assertSame(50, $product->fresh()->quantity);
    }

    public function test_staff_cannot_reach_admin_only_features(): void
    {
        $staff = User::factory()->create();
        $transaction = InventoryTransaction::record($this->product(), 'stock_in', 5);

        $this->actingAs($staff)->get('/users')->assertForbidden();
        $this->actingAs($staff)->get(route('adjustments.create'))->assertForbidden();
        $this->actingAs($staff)->delete(route('transactions.destroy', $transaction))->assertForbidden();
    }

    public function test_reversing_a_transaction_records_an_opposite_entry_once(): void
    {
        $product = $this->product(10);
        $transaction = InventoryTransaction::record($product, 'stock_in', 5);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('transactions.destroy', $transaction))->assertRedirect();
        $this->assertSame(10, $product->fresh()->quantity);

        $this->actingAs($admin)->delete(route('transactions.destroy', $transaction))->assertSessionHas('error');
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_movement_form_validates_input(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $cases = [
            [['product_id' => $product->id, 'type' => 'stock_in', 'quantity' => 0], 'quantity'],
            [['product_id' => $product->id, 'type' => 'stock_in', 'quantity' => 'abc'], 'quantity'],
            [['product_id' => $product->id, 'type' => 'sideways', 'quantity' => 1], 'type'],
            [['product_id' => 9999, 'type' => 'stock_in', 'quantity' => 1], 'product_id'],
        ];

        foreach ($cases as [$payload, $field]) {
            $this->actingAs($user)->post(route('transactions.store'), $payload)->assertSessionHasErrors($field);
        }
    }

    public function test_api_requires_login_and_returns_the_stats_shape(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();

        $this->product(0, 5);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['total_products', 'total_value', 'low_stock', 'out_of_stock', 'in_stock'],
                'meta' => ['generated_at'],
            ])
            ->assertJsonPath('data.out_of_stock', 1);
    }
}