<?php

namespace Tests\Feature;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_order_without_reserving_stock_before_shipping_pricing(): void
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
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.status', 'pending_shipping_cost')
            ->assertJsonPath('orders.0.subtotal', '1000.00')
            ->assertJsonPath('orders.0.grand_total', '0.00')
            ->assertJsonPath('orders.0.deposit_amount', '0.00')
            ->assertJsonPath('orders.0.balance_amount', '0.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('individual_order_items', 1);
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('orders.0.id'),
            'supplier_id' => $supplier->id,
            'status' => 'pending_shipping_cost',
            'subtotal' => 1000,
            'shipping_total' => 0,
            'grand_total' => 0,
            'deposit_amount' => 0,
            'balance_amount' => 0,
        ]);
        $this->assertDatabaseCount('payment_transactions', 0);
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


    public function test_checkout_creates_independent_orders_for_each_product(): void
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
            'unit_price' => 100.1,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $productTwo = Product::create([
            'supplier_id' => $supplierTwo->id,
            'name_ar' => 'شتلة زيتون',
            'unit_name' => 'شتلة',
            'unit_price' => 200.3,
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
                    ['product_id' => $productTwo->id, 'quantity' => 10],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'orders')
            ->assertJsonFragment(['status' => 'pending_shipping_cost']);

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('individual_order_items', 2);
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('orders', [
            'supplier_id' => $supplierOne->id,
            'status' => 'pending_shipping_cost',
            'subtotal' => 1001,
            'shipping_total' => 0,
            'grand_total' => 0,
            'deposit_amount' => 0,
            'balance_amount' => 0,
        ]);

        $this->assertDatabaseHas('orders', [
            'supplier_id' => $supplierTwo->id,
            'status' => 'pending_shipping_cost',
            'subtotal' => 2003,
            'shipping_total' => 0,
            'grand_total' => 0,
            'deposit_amount' => 0,
            'balance_amount' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productOne->id,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productTwo->id,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseCount('payment_transactions', 0);
    }
    public function test_pending_orders_do_not_reserve_stock_before_shipping_is_priced(): void
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

        $secondResponse->assertCreated();

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('individual_order_items', 2);
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_buyer_order_responses_expose_only_public_supplier_profile_data(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create([
            'name' => 'Private Supplier Name',
            'full_name' => 'Private Supplier Full Name',
            'email' => 'private-supplier@example.com',
            'phone' => '01099999999',
        ]);

        $reviewer = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Public Nursery Name',
            'business_type' => 'مشتل',
            'description' => 'Public nursery description',
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => 'Internal review note',
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

        $payload = [
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
        ];

        $createResponse = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $createResponse->assertCreated()
            ->assertJsonPath('orders.0.supplier.business_name', 'Public Nursery Name')
            ->assertJsonMissingPath('orders.0.supplier.user')
            ->assertJsonMissingPath('orders.0.supplier.user_id')
            ->assertJsonMissingPath('orders.0.supplier.review_note')
            ->assertJsonMissingPath('orders.0.supplier.reviewed_by')
            ->assertJsonMissingPath('orders.0.supplier.reviewed_at');

        $orderId = $createResponse->json('orders.0.id');

        $indexResponse = $this->getJson('/api/v1/orders');

        $indexResponse->assertOk()
            ->assertJsonPath('orders.0.supplier.business_name', 'Public Nursery Name')
            ->assertJsonMissingPath('orders.0.supplier.user')
            ->assertJsonMissingPath('orders.0.supplier.user_id')
            ->assertJsonMissingPath('orders.0.supplier.review_note')
            ->assertJsonMissingPath('orders.0.supplier.reviewed_by')
            ->assertJsonMissingPath('orders.0.supplier.reviewed_at');

        $showResponse = $this->getJson("/api/v1/orders/{$orderId}");

        $showResponse->assertOk()
            ->assertJsonPath('order.supplier.business_name', 'Public Nursery Name')
            ->assertJsonMissingPath('order.supplier.user')
            ->assertJsonMissingPath('order.supplier.user_id')
            ->assertJsonMissingPath('order.supplier.review_note')
            ->assertJsonMissingPath('order.supplier.reviewed_by')
            ->assertJsonMissingPath('order.supplier.reviewed_at');

        $this->assertDatabaseHas('supplier_profiles', [
            'id' => $supplier->id,
            'user_id' => $supplierUser->id,
            'review_note' => 'Internal review note',
        ]);
    }




    public function test_buyer_can_view_legacy_supplier_orders_without_private_supplier_data(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $supplierUser = User::factory()->create();
        $reviewer = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Legacy Public Nursery',
            'business_type' => 'nursery',
            'description' => 'Public nursery description',
            'status' => 'approved',
            'review_note' => 'Private admin review note',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'supplier_id' => $supplier->id,
            'order_number' => 'LEGACY-BUYER-001',
            'status' => 'pending_payment',
            'payment_method' => 'instapay',
            'payment_status' => 'pending',
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '01000000000',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Test Address',
            'subtotal' => 1000,
            'shipping_total' => 0,
            'grand_total' => 1000,
            'deposit_amount' => 300,
            'balance_amount' => 700,
            'deposit_expires_at' => now()->addHours(24),
        ]);

        $supplierOrder = $order->supplierOrders()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending_payment',
            'subtotal' => 1000,
            'shipping_total' => 0,
            'grand_total' => 1000,
        ]);

        $supplierOrder->items()->create([
            'product_id' => null,
            'product_name_ar' => 'شتلة مانجو',
            'product_name_en' => 'Mango seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        $this->actingAs($buyer, 'sanctum');

        $indexResponse = $this->getJson('/api/v1/orders');

        $indexResponse->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.supplier_orders.0.supplier.business_name', 'Legacy Public Nursery')
            ->assertJsonPath('orders.0.supplier_orders.0.items.0.product_name_ar', 'شتلة مانجو')
            ->assertJsonMissingPath('orders.0.supplier_orders.0.supplier.user')
            ->assertJsonMissingPath('orders.0.supplier_orders.0.supplier.user_id')
            ->assertJsonMissingPath('orders.0.supplier_orders.0.supplier.review_note')
            ->assertJsonMissingPath('orders.0.supplier_orders.0.supplier.reviewed_by')
            ->assertJsonMissingPath('orders.0.supplier_orders.0.supplier.reviewed_at');

        $showResponse = $this->getJson('/api/v1/orders/' . $order->id);

        $showResponse->assertOk()
            ->assertJsonPath('order.supplier_orders.0.supplier.business_name', 'Legacy Public Nursery')
            ->assertJsonPath('order.supplier_orders.0.items.0.product_name_ar', 'شتلة مانجو')
            ->assertJsonMissingPath('order.supplier_orders.0.supplier.user')
            ->assertJsonMissingPath('order.supplier_orders.0.supplier.user_id')
            ->assertJsonMissingPath('order.supplier_orders.0.supplier.review_note')
            ->assertJsonMissingPath('order.supplier_orders.0.supplier.reviewed_by')
            ->assertJsonMissingPath('order.supplier_orders.0.supplier.reviewed_at');
    }
    }
