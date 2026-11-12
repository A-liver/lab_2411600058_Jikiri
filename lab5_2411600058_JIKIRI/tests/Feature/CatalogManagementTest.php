<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create($overrides + [
            'name' => 'Rice',
            'sku' => 'INV-100',
            'category_id' => Category::firstOrCreate(['name' => 'Dry Goods'])->id,
            'quantity' => 10,
            'reorder_level' => 5,
            'unit_price' => 1.5,
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/categories', '/brands', '/users', '/profile', '/products'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_every_page_renders_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Seafood']);
        $brand = Brand::create(['name' => 'Blue Harbor']);
        $product = $this->product(['category_id' => $category->id, 'brand_id' => $brand->id]);

        foreach ([
            '/dashboard', '/products', '/products/create', "/products/{$product->id}", "/products/{$product->id}/edit",
            '/categories', '/categories/create', "/categories/{$category->id}/edit",
            '/brands', '/brands/create', "/brands/{$brand->id}/edit",
            '/users', '/users/create', "/users/{$user->id}/edit", '/profile',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_profile_page_shows_the_forms(): void
    {
        $user = User::factory()->create(['name' => 'Maria Santos']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('Change Password')
            ->assertSee('Delete Account')
            ->assertSee('Maria Santos');
    }

    public function test_category_crud(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/categories', ['name' => 'Seafood', 'description' => 'Fish'])
            ->assertRedirect('/categories');
        $category = Category::where('name', 'Seafood')->firstOrFail();

        $this->actingAs($user)->post('/categories', ['name' => 'Seafood'])->assertSessionHasErrors('name');

        $this->actingAs($user)->put("/categories/{$category->id}", ['name' => 'Fish & Seafood'])
            ->assertRedirect('/categories');
        $this->assertSame('Fish & Seafood', $category->fresh()->name);

        $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect('/categories');
        $this->assertModelMissing($category);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user)->delete("/categories/{$product->category_id}")
            ->assertRedirect('/categories')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_brand_crud_and_delete_guard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/brands', ['name' => 'Farmhouse'])->assertRedirect('/brands');
        $brand = Brand::where('name', 'Farmhouse')->firstOrFail();

        $this->actingAs($user)->put("/brands/{$brand->id}", ['name' => 'Farmhouse Co.'])->assertRedirect('/brands');
        $this->assertSame('Farmhouse Co.', $brand->fresh()->name);

        $this->product(['brand_id' => $brand->id]);
        $this->actingAs($user)->delete("/brands/{$brand->id}")->assertSessionHas('error');
        $this->assertModelExists($brand);

        Product::query()->delete();
        $this->actingAs($user)->delete("/brands/{$brand->id}")->assertRedirect('/brands');
        $this->assertModelMissing($brand);
    }

    public function test_user_crud(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Cook One',
            'email' => 'cook@example.com',
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ])->assertRedirect('/users');

        $cook = User::where('email', 'cook@example.com')->firstOrFail();
        $this->assertNotSame('a-long-password-1', $cook->password);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Dup', 'email' => 'cook@example.com',
            'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
        ])->assertSessionHasErrors('email');

        $oldHash = $cook->password;
        $this->actingAs($admin)->put("/users/{$cook->id}", [
            'name' => 'Cook Uno', 'email' => 'cook@example.com', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect('/users');
        $this->assertSame('Cook Uno', $cook->fresh()->name);
        $this->assertSame($oldHash, $cook->fresh()->password);

        $this->actingAs($admin)->delete("/users/{$cook->id}")->assertRedirect('/users');
        $this->assertModelMissing($cook);
    }

    public function test_a_user_cannot_delete_their_own_account_from_user_management(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertSessionHas('error');
        $this->assertModelExists($admin);
    }

    public function test_product_requires_a_category_and_records_stock_movements(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Beverages']);

        $this->actingAs($user)->post('/products', [
            'name' => 'Water', 'sku' => 'INV-200', 'quantity' => 5, 'reorder_level' => 2, 'unit_price' => 1,
        ])->assertSessionHasErrors('category_id');

        $this->actingAs($user)->post('/products', [
            'name' => 'Water', 'sku' => 'INV-200', 'category_id' => $category->id,
            'quantity' => 5, 'reorder_level' => 2, 'unit_price' => 1,
        ])->assertRedirect('/products');

        $product = Product::where('sku', 'INV-200')->firstOrFail();
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'stock_in', 'quantity' => 5,
        ]);

        $this->assertSame(1, InventoryTransaction::where('product_id', $product->id)->count());
    }

    public function test_editing_a_product_does_not_change_its_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['quantity' => 10]);

        $this->actingAs($user)->put("/products/{$product->id}", [
            'name' => 'Renamed Rice', 'sku' => 'INV-100', 'category_id' => $product->category_id,
            'quantity' => 999, 'reorder_level' => 5, 'unit_price' => 2,
        ])->assertRedirect('/products');

        $this->assertSame('Renamed Rice', $product->fresh()->name);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_stock_in_and_stock_out_adjust_quantity_and_log_the_movement(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['quantity' => 10]);

        $this->actingAs($user)->get("/products/{$product->id}/edit")->assertOk()->assertSee('Adjust Stock');

        $this->actingAs($user)->post("/products/{$product->id}/adjust-stock", [
            'type' => 'stock_in', 'quantity' => 5, 'reference' => 'Delivery INV-1042',
        ])->assertRedirect("/products/{$product->id}/edit")->assertSessionHas('success');
        $this->assertSame(15, $product->fresh()->quantity);

        $this->actingAs($user)->post("/products/{$product->id}/adjust-stock", [
            'type' => 'stock_out', 'quantity' => 4, 'reference' => '',
        ])->assertSessionHasNoErrors();
        $this->assertSame(11, $product->fresh()->quantity);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'user_id' => $user->id, 'type' => 'stock_in',
            'quantity' => 5, 'reference' => 'Delivery INV-1042',
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'type' => 'stock_out', 'quantity' => 4, 'reference' => 'Manual adjustment',
        ]);
    }

    public function test_stock_out_cannot_exceed_the_quantity_on_hand(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['quantity' => 3]);

        $this->actingAs($user)->post("/products/{$product->id}/adjust-stock", [
            'type' => 'stock_out', 'quantity' => 4,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_stock_adjustment_is_validated(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        foreach ([
            [[], ['type', 'quantity']],
            [['type' => 'sideways', 'quantity' => 1], ['type']],
            [['type' => 'stock_in', 'quantity' => 0], ['quantity']],
            [['type' => 'stock_in', 'quantity' => 'abc'], ['quantity']],
        ] as [$data, $errors]) {
            $this->actingAs($user)->post("/products/{$product->id}/adjust-stock", $data)->assertSessionHasErrors($errors);
        }

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_inventory_filters_by_category_and_brand_and_exports_csv(): void
    {
        $user = User::factory()->create();
        $seafood = Category::create(['name' => 'Seafood']);
        $brand = Brand::create(['name' => 'Blue Harbor']);
        $this->product(['name' => 'Shrimp', 'sku' => 'S-1', 'category_id' => $seafood->id, 'brand_id' => $brand->id]);
        $this->product(['name' => 'Rice', 'sku' => 'R-1']);

        $this->actingAs($user)->get("/products?category={$seafood->id}")
            ->assertSee('Shrimp')->assertDontSee('Rice');

        $this->actingAs($user)->get("/products?brand={$brand->id}")
            ->assertSee('Shrimp')->assertDontSee('Rice');

        $csv = $this->actingAs($user)->get('/products-export')->streamedContent();
        $this->assertStringContainsString('SKU,Name,Category,Brand', $csv);
        $this->assertStringContainsString('Blue Harbor', $csv);
    }

    public function test_profile_can_be_updated_password_changed_and_account_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'New Name', 'email' => 'new@example.com'])
            ->assertRedirect('/profile')->assertSessionHas('success');
        $this->assertSame('New Name', $user->fresh()->name);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'another-long-pass-9',
            'password_confirmation' => 'another-long-pass-9',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->actingAs($user)->delete('/profile', ['password' => 'another-long-pass-9'])->assertRedirect('/');
        $this->assertModelMissing($user);
    }

    public function test_dashboard_shows_stock_by_category_and_brand(): void
    {
        $user = User::factory()->create();
        $seafood = Category::create(['name' => 'Seafood']);
        $dry = Category::create(['name' => 'Dry Goods']);
        Category::create(['name' => 'Empty Category']);
        $brand = Brand::create(['name' => 'Blue Harbor']);

        $this->product(['name' => 'Shrimp', 'sku' => 'S-1', 'category_id' => $seafood->id, 'brand_id' => $brand->id, 'quantity' => 4, 'reorder_level' => 5, 'unit_price' => 10]);
        $this->product(['name' => 'Tuna', 'sku' => 'S-2', 'category_id' => $seafood->id, 'brand_id' => $brand->id, 'quantity' => 0, 'reorder_level' => 5, 'unit_price' => 20]);
        $this->product(['name' => 'Rice', 'sku' => 'R-1', 'category_id' => $dry->id, 'quantity' => 100, 'reorder_level' => 5, 'unit_price' => 2]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Stock by Category')->assertSee('Stock by Brand');

        $byCategory = $response->viewData('stockByCategory')->keyBy('name');
        $this->assertSame(2, (int) $byCategory['Seafood']->product_count);
        $this->assertSame(4, (int) $byCategory['Seafood']->units);
        $this->assertEquals(40.0, (float) $byCategory['Seafood']->stock_value);
        $this->assertSame(1, (int) $byCategory['Seafood']->low_count);
        $this->assertSame(1, (int) $byCategory['Seafood']->out_count);
        $this->assertSame(0, (int) $byCategory['Empty Category']->product_count);
        $this->assertSame(0, (int) $byCategory['Empty Category']->out_count);

        $byBrand = $response->viewData('stockByBrand')->keyBy('name');
        $this->assertSame(2, (int) $byBrand['Blue Harbor']->product_count);
        $this->assertSame(1, (int) $byBrand['No brand']->product_count);
        $this->assertSame(100, (int) $byBrand['No brand']->units);
        $this->assertSame(0, (int) $byBrand['No brand']->out_count);
    }
}
