<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_order_is_cancelled_and_reserved_stock_is_released_only_once(): void
    {
        $buyer = User::factory()->create();

        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Test Nursery',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'reserved_quantity' => 10,
            'status' => 'published',
        ]);
        $product->reserved_quantity = 10;
        $product->save();

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'TEST-EXPIRED-001',
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
            'deposit_expires_at' => now()->subMinute(),
        ]);

        $supplierOrder = $order->supplierOrders()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'subtotal' => 1000,
            'shipping_total' => 0,
            'grand_total' => 1000,
        ]);

        $supplierOrder->items()->create([
            'product_id' => $product->id,
            'product_name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        $exitCode = Artisan::call('app:expire-unpaid-orders');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('supplier_orders', [
            'id' => $supplierOrder->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        Artisan::call('app:expire-unpaid-orders');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);
    }
}
