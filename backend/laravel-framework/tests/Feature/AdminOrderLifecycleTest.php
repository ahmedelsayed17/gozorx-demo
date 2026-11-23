<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createLifecycleScenario(
        string $status = 'confirmed'
    ): array {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Lifecycle Nursery',
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

        $product->reserved_quantity = 10;
        $product->save();

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'supplier_id' => $supplier->id,
            'order_number' => 'LIFECYCLE-' . uniqid(),
            'status' => $status,
            'payment_method' => 'instapay',
            'payment_status' => 'partially_paid',
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '01000000000',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Test Address',
            'subtotal' => 1000,
            'shipping_total' => 100,
            'grand_total' => 1100,
            'deposit_amount' => 330,
            'balance_amount' => 770,
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

        PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 330,
            'status' => 'approved',
        ]);

        return compact('admin', 'buyer', 'supplier', 'product', 'order');
    }

    public function test_admin_can_advance_order_to_processing(): void
    {
        $scenario = $this->createLifecycleScenario('confirmed');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->patchJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/status",
                ['status' => 'processing']
            )
            ->assertOk()
            ->assertJsonPath('order.status', 'processing');

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'processing',
        ]);
    }

    public function test_admin_cannot_skip_order_status(): void
    {
        $scenario = $this->createLifecycleScenario('confirmed');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->patchJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/status",
                ['status' => 'shipped']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_admin_can_mark_shipped_order_as_delivered(): void
    {
        $scenario = $this->createLifecycleScenario('shipped');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->patchJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/status",
                ['status' => 'delivered']
            )
            ->assertOk()
            ->assertJsonPath('order.status', 'delivered');

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'delivered',
        ]);

        $this->assertNotNull($scenario['order']->fresh()->delivered_at);
        $this->assertNull($scenario['order']->fresh()->completed_at);
    }

    public function test_admin_cannot_complete_order_through_status_endpoint(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->patchJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/status",
                ['status' => 'completed']
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'delivered',
        ]);
    }

    public function test_admin_cannot_collect_balance_before_delivery(): void
    {
        $scenario = $this->createLifecycleScenario('shipped');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 770,
                    'payment_method' => 'cash',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'shipped',
        ]);

        $this->assertDatabaseMissing('payment_transactions', [
            'order_id' => $scenario['order']->id,
            'payment_type' => 'balance',
        ]);
    }

    public function test_admin_cannot_collect_an_incorrect_balance_amount(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 500,
                    'payment_method' => 'cash',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseMissing('payment_transactions', [
            'order_id' => $scenario['order']->id,
            'payment_type' => 'balance',
        ]);
    }

    public function test_admin_can_collect_balance_and_complete_order(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 770,
                    'payment_method' => 'cash',
                ]
            )
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.payment_status', 'paid');

        $order = $scenario['order']->fresh();
        $product = $scenario['product']->fresh();

        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->stock_committed_at);

        $this->assertSame(90, $product->available_quantity);
        $this->assertSame(0, $product->reserved_quantity);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'balance',
            'payment_method' => 'cash',
            'amount' => 770,
            'status' => 'approved',
            'collected_by' => $scenario['admin']->id,
        ]);

        $this->assertNotNull(
            $order->paymentTransactions()
                ->where('payment_type', 'balance')
                ->first()
                ->collected_at
        );
    }

    public function test_admin_cannot_collect_balance_twice(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $url = "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance";

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson($url, [
                'amount' => 770,
                'payment_method' => 'cash',
            ])
            ->assertOk();

        $this->postJson($url, [
            'amount' => 770,
            'payment_method' => 'cash',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $scenario['order']->id,
            'status' => 'completed',
        ]);

        $this->assertSame(90, $scenario['product']->fresh()->available_quantity);
        $this->assertSame(0, $scenario['product']->fresh()->reserved_quantity);

        $this->assertSame(
            1,
            $scenario['order']->paymentTransactions()
                ->where('payment_type', 'balance')
                ->count()
        );
    }

    public function test_admin_can_collect_balance_using_instapay(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 770,
                    'payment_method' => 'instapay',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $scenario['order']->id,
            'payment_type' => 'balance',
            'payment_method' => 'instapay',
            'amount' => 770,
            'collected_by' => $scenario['admin']->id,
        ]);
    }

    public function test_admin_order_responses_include_safe_balance_transaction_data(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 770,
                    'payment_method' => 'cash',
                ]
            )
            ->assertOk();

        $details = $this->getJson(
            "/api/v1/admin/orders/{$scenario['order']->id}"
        );

        $details->assertOk()
            ->assertJsonPath('order.balance_transaction.payment_method', 'cash')
            ->assertJsonPath('order.balance_transaction.status', 'approved')
            ->assertJsonPath(
                'order.balance_transaction.collected_by.id',
                $scenario['admin']->id
            )
            ->assertJsonStructure([
                'order' => [
                    'balance_transaction' => [
                        'id',
                        'payment_method',
                        'amount',
                        'status',
                        'collected_at',
                        'collected_by' => ['id', 'name'],
                    ],
                ],
            ])
            ->assertJsonMissingPath('order.balance_transaction.receipt_path')
            ->assertJsonMissingPath('order.payment_transactions');

        $list = $this->getJson('/api/v1/admin/orders');

        $list->assertOk()
            ->assertJsonPath('data.0.balance_transaction.payment_method', 'cash')
            ->assertJsonPath(
                'data.0.balance_transaction.collected_by.id',
                $scenario['admin']->id
            )
            ->assertJsonMissingPath('data.0.balance_transaction.receipt_path')
            ->assertJsonMissingPath('data.0.payment_transactions');
    }


    public function test_admin_can_collect_balance_for_legacy_multi_supplier_order(): void
    {
        $scenario = $this->createLifecycleScenario('delivered');

        $scenario['order']->individualItem()->delete();

        $firstProduct = $scenario['product'];
        $firstProduct->reserved_quantity = 4;
        $firstProduct->save();

        $secondSupplierUser = User::factory()->create();
        $secondSupplier = $secondSupplierUser->supplierProfile()->create([
            'business_name' => 'Second Lifecycle Nursery',
            'status' => 'approved',
        ]);

        $secondProduct = Product::create([
            'supplier_id' => $secondSupplier->id,
            'name_ar' => 'شتلة زيتون',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 1,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $secondProduct->reserved_quantity = 3;
        $secondProduct->save();

        $firstSupplierOrder = SupplierOrder::create([
            'order_id' => $scenario['order']->id,
            'supplier_id' => $scenario['supplier']->id,
            'status' => 'delivered',
            'subtotal' => 400,
            'shipping_total' => 0,
            'grand_total' => 400,
            'deposit_amount' => 120,
            'balance_amount' => 280,
        ]);

        $secondSupplierOrder = SupplierOrder::create([
            'order_id' => $scenario['order']->id,
            'supplier_id' => $secondSupplier->id,
            'status' => 'delivered',
            'subtotal' => 300,
            'shipping_total' => 0,
            'grand_total' => 300,
            'deposit_amount' => 90,
            'balance_amount' => 210,
        ]);

        OrderItem::create([
            'supplier_order_id' => $firstSupplierOrder->id,
            'product_id' => $firstProduct->id,
            'product_name_ar' => 'شتلة مانجو',
            'product_name_en' => 'Mango Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 4,
            'line_total' => 400,
        ]);

        OrderItem::create([
            'supplier_order_id' => $secondSupplierOrder->id,
            'product_id' => $secondProduct->id,
            'product_name_ar' => 'شتلة زيتون',
            'product_name_en' => 'Olive Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 3,
            'line_total' => 300,
        ]);

        $this->actingAs($scenario['admin'], 'sanctum')
            ->postJson(
                "/api/v1/admin/orders/{$scenario['order']->id}/collect-balance",
                [
                    'amount' => 770,
                    'payment_method' => 'cash',
                ]
            )
            ->assertOk()
            ->assertJsonPath('order.status', 'completed');

        $this->assertSame(96, $firstProduct->fresh()->available_quantity);
        $this->assertSame(0, $firstProduct->fresh()->reserved_quantity);

        $this->assertSame(97, $secondProduct->fresh()->available_quantity);
        $this->assertSame(0, $secondProduct->fresh()->reserved_quantity);

        $this->assertNotNull($scenario['order']->fresh()->stock_committed_at);

        $this->assertSame(
            1,
            $scenario['order']->paymentTransactions()
                ->where('payment_type', 'balance')
                ->count()
        );
    }

}
