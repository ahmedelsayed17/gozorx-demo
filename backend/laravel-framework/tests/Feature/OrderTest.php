<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_order_and_reserve_stock(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Test Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('order.status', 'pending_payment')
            ->assertJsonPath('order.deposit_amount', '300.00')
            ->assertJsonPath('order.balance_amount', '700.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 10,
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('supplier_orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payment_transactions', 1);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $response->json('order.id'),
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
            'receipt_path' => null,
        ]);
    }

    public function test_buyer_cannot_order_more_than_available_stock(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 10,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Test Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 11,
                    ],
                ],
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 0,
        ]);
    }


    public function test_buyer_cannot_order_below_minimum_quantity(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Test Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 4,
                    ],
                ],
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 0,
        ]);
    }


    public function test_buyer_cannot_order_from_unapproved_supplier(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Pending Nursery',
            'status' => 'pending',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Test Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 0,
        ]);
    }


    public function test_checkout_creates_separate_supplier_orders(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierOneUser = User::factory()->create();
        $supplierOne = $supplierOneUser->supplierProfile()->create([
            'business_name' => 'Nursery One',
            'status' => 'approved',
        ]);

        $supplierTwoUser = User::factory()->create();
        $supplierTwo = $supplierTwoUser->supplierProfile()->create([
            'business_name' => 'Nursery Two',
            'status' => 'approved',
        ]);

        $productOne = Product::create([
            'supplier_id' => $supplierOne->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $productTwo = Product::create([
            'supplier_id' => $supplierTwo->id,
            'name_ar' => 'شتلة زيتون',
            'unit_name' => 'شتلة',
            'unit_price' => 200,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Test Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Test Address',
                'items' => [
                    ['product_id' => $productOne->id, 'quantity' => 10],
                    ['product_id' => $productTwo->id, 'quantity' => 5],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('order.grand_total', '2000.00')
            ->assertJsonPath('order.deposit_amount', '600.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('supplier_orders', 2);
        $this->assertDatabaseCount('order_items', 2);

        $this->assertDatabaseHas('supplier_orders', [
            'supplier_id' => $supplierOne->id,
            'grand_total' => 1000,
        ]);

        $this->assertDatabaseHas('supplier_orders', [
            'supplier_id' => $supplierTwo->id,
            'grand_total' => 1000,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productOne->id,
            'reserved_quantity' => 10,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productTwo->id,
            'reserved_quantity' => 5,
        ]);
    }
    public function test_reserved_stock_is_not_available_for_another_buyer(): void
    {
        $buyerOne = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $buyerTwo = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
            'status' => 'published',
        ]);

        $firstResponse = $this->actingAs($buyerOne, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'First Buyer',
                'recipient_phone' => '01000000000',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'First Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $firstResponse->assertCreated();

        $secondResponse = $this->actingAs($buyerTwo, 'sanctum')
            ->postJson('/api/v1/orders', [
                'payment_method' => 'instapay',
                'recipient_name' => 'Second Buyer',
                'recipient_phone' => '01000000001',
                'governorate' => 'Cairo',
                'city' => 'Nasr City',
                'address' => 'Second Address',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 91,
                    ],
                ],
            ]);

        $secondResponse->assertUnprocessable();

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 10,
        ]);
    }
}
