<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function createTestOrder(
        User $buyer,
        string $number,
        string $status = 'pending_payment',
        array $paymentOverrides = []
    ): Order {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => $number,
            'status' => $status,
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

        PaymentTransaction::create(array_merge([
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
        ], $paymentOverrides));

        return $order;
    }

    public function test_admin_can_view_all_orders_from_all_buyers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyerOne = User::factory()->create(['role' => 'buyer']);
        $buyerTwo = User::factory()->create(['role' => 'buyer']);

        $this->createTestOrder($buyerOne, 'ADMIN-VIEW-001');
        $this->createTestOrder($buyerTwo, 'ADMIN-VIEW-002');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonFragment(['order_number' => 'ADMIN-VIEW-001'])
            ->assertJsonFragment(['order_number' => 'ADMIN-VIEW-002']);
    }

    public function test_admin_can_filter_orders_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->createTestOrder(
            $buyer,
            'FILTER-PENDING-001',
            'pending_payment'
        );

        $this->createTestOrder(
            $buyer,
            'FILTER-SHIPPING-001',
            'pending_shipping_cost'
        );

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?status=pending_payment')
            ->assertOk()
            ->assertJsonFragment([
                'order_number' => 'FILTER-PENDING-001',
            ])
            ->assertJsonMissing([
                'order_number' => 'FILTER-SHIPPING-001',
            ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?search=FILTER-SHIPPING-001')
            ->assertOk()
            ->assertJsonFragment([
                'order_number' => 'FILTER-SHIPPING-001',
            ]);
    }

    public function test_admin_can_view_order_details_without_private_receipt_path(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer']);

        $order = $this->createTestOrder(
            $buyer,
            'ADMIN-DETAILS-001',
            'pending_payment',
            [
                'receipt_path' => 'private/receipts/test-receipt.jpg',
            ]
        );

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonMissing([
                'receipt_path' => 'private/receipts/test-receipt.jpg',
            ]);
    }
public function test_admin_can_view_individual_order_item_and_supplier_details(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $buyer = User::factory()->create(['role' => 'buyer']);
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

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'supplier_id' => $supplier->id,
        'order_number' => 'ADMIN-INDIVIDUAL-001',
        'status' => 'pending_shipping_cost',
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
    ]);

    $order->individualItem()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'شتلة مانجو',
        'product_name_en' => 'Mango Seedling',
        'unit_name' => 'شتلة',
        'unit_price' => 100,
        'quantity' => 10,
        'line_total' => 1000,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('order.order_number', 'ADMIN-INDIVIDUAL-001')
        ->assertJsonPath('order.individual_item.product_name_ar', 'شتلة مانجو')
        ->assertJsonPath('order.individual_item.quantity', 10)
        ->assertJsonPath('order.individual_item.unit_price', '100.00')
        ->assertJsonPath('order.supplier.business_name', 'Approved Nursery')
        ->assertJsonPath('order.supplier.status', 'approved')
        ->assertJsonPath('order.supplier.user.email', $supplierUser->email);
}

public function test_buyer_cannot_access_admin_orders(): void
{
    $buyer = User::factory()->create(['role' => 'buyer']);
        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/admin/orders')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $this->getJson('/api/v1/admin/orders')
            ->assertUnauthorized();
    }

    public function test_admin_can_set_shipping_cost_only_once_and_reserve_stock_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer']);

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

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'supplier_id' => $supplier->id,
            'order_number' => 'SHIPPING-ONCE-001',
            'status' => 'pending_shipping_cost',
            'payment_method' => 'instapay',
            'payment_status' => 'pending',
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '01000000000',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Test Address',
            'subtotal' => 1000,
            'shipping_total' => 0,
            'grand_total' => 0,
            'deposit_amount' => 0,
            'balance_amount' => 0,
        ]);

        $order->individualItem()->create([
            'product_id' => $product->id,
            'product_name_ar' => 'شتلة مانجو',
            'product_name_en' => 'Mango Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        $endpoint = "/api/v1/admin/orders/{$order->id}/shipping-cost";

        $payload = [
            'shipping_cost' => 100,
            'internal_notes' => 'Test shipping cost',
        ];

        $this->actingAs($admin, 'sanctum')
            ->patchJson($endpoint, $payload)
            ->assertOk()
            ->assertJsonPath('order.subtotal', 1000)
            ->assertJsonPath('order.shipping_cost', 100)
            ->assertJsonPath('order.grand_total', 1100)
            ->assertJsonPath('order.deposit_amount', 330)
            ->assertJsonPath('order.balance_amount', 770)
            ->assertJsonPath('order.status', 'pending_payment')
            ->assertJsonPath('order.payment_status', 'pending');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 10,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_total' => 100,
            'grand_total' => 1100,
            'deposit_amount' => 330,
            'balance_amount' => 770,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseCount('payment_transactions', 1);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'amount' => 330,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_shipping_histories', 1);

        // A second attempt must be rejected without changing anything.
        $this->actingAs($admin, 'sanctum')
            ->patchJson($endpoint, [
                'shipping_cost' => 200,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 10,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_total' => 100,
            'grand_total' => 1100,
            'deposit_amount' => 330,
            'balance_amount' => 770,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('order_shipping_histories', 1);
    }
}
