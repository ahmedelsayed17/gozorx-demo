<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
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
            'status' => 'cancelled',
        ]);

        $this->assertNotNull($supplierOrder->fresh()->stock_released_at);

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

    public function test_expired_order_with_timely_receipt_pending_review_is_not_cancelled(): void
    {
        $buyer = User::factory()->create();
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Individual Nursery',
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
        $product->reserved_quantity = 10;
        $product->save();

        $deadline = now()->subMinute();

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'TEST-RECEIPT-REVIEW-001',
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
            'deposit_expires_at' => $deadline,
        ]);

        $order->individualItem()->create([
            'product_id' => $product->id,
            'product_name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
            'receipt_path' => 'payment-receipts/test/receipt.png',
            'receipt_uploaded_at' => $deadline->copy()->subMinute(),
        ]);

        $exitCode = Artisan::call('app:expire-unpaid-orders');

        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
            'stock_released_at' => null,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 10,
        ]);
    }

    public function test_expired_individual_order_with_late_pending_receipt_is_cancelled_and_stock_released(): void
    {
        $buyer = User::factory()->create();
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Late Receipt Nursery',
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

        $deadline = now()->subMinutes(2);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'TEST-LATE-RECEIPT-001',
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
            'deposit_expires_at' => $deadline,
        ]);

        $order->individualItem()->create([
            'product_id' => $product->id,
            'product_name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
            'receipt_path' => 'payment-receipts/test/late-receipt.png',
            'receipt_uploaded_at' => $deadline->copy()->addMinute(),
        ]);

        $exitCode = Artisan::call('app:expire-unpaid-orders');

        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'stock_released_at' => null,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);
    }


    public function test_expired_individual_order_with_rejected_receipt_releases_reserved_stock_once(): void
    {
        $buyer = User::factory()->create();
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Individual Nursery',
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

        $deadline = now()->subMinutes(2);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'TEST-INDIVIDUAL-EXPIRED-001',
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
            'deposit_expires_at' => $deadline,
        ]);

        $order->individualItem()->create([
            'product_id' => $product->id,
            'product_name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'rejected',
            'receipt_path' => 'payment-receipts/test/rejected.png',
            'receipt_uploaded_at' => $deadline->copy()->addMinute(),
        ]);

        $exitCode = Artisan::call('app:expire-unpaid-orders');

        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $this->assertNotNull($order->fresh()->stock_released_at);

        Artisan::call('app:expire-unpaid-orders');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'available_quantity' => 100,
            'reserved_quantity' => 0,
        ]);
    }

public function test_expired_individual_order_without_receipt_is_cancelled_and_stock_released_once(): void
{
    $buyer = User::factory()->create();
    $supplierUser = User::factory()->create();

    $supplier = $supplierUser->supplierProfile()->create([
        'business_name' => 'Individual Nursery',
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
        'order_number' => 'TEST-NO-RECEIPT-001',
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

    $order->individualItem()->create([
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

    $this->assertNotNull($order->fresh()->stock_released_at);

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

public function test_expired_individual_order_with_insufficient_reserved_stock_is_not_cancelled(): void
{
    $buyer = User::factory()->create();
    $supplierUser = User::factory()->create();

    $supplier = $supplierUser->supplierProfile()->create([
        'business_name' => 'Individual Nursery',
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

    $product->reserved_quantity = 5;
    $product->save();

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'order_number' => 'TEST-INSUFFICIENT-STOCK-001',
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

    $order->individualItem()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'شتلة مانجو',
        'unit_name' => 'شتلة',
        'unit_price' => 100,
        'quantity' => 10,
        'line_total' => 1000,
    ]);

    $exitCode = Artisan::call('app:expire-unpaid-orders');

    $this->assertSame(1, $exitCode);
    $this->assertStringContainsString(
        'Insufficient reserved stock',
        Artisan::output()
    );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending_payment',
        'stock_released_at' => null,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'available_quantity' => 100,
        'reserved_quantity' => 5,
    ]);
}

    public function test_legacy_multi_product_expiration_rolls_back_all_stock_releases_if_one_product_is_insufficient(): void
    {
        $buyer = User::factory()->create();
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Multi Product Nursery',
            'status' => 'approved',
        ]);

        $firstProduct = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'reserved_quantity' => 10,
            'status' => 'published',
        ]);
            $firstProduct->reserved_quantity = 10;
            $firstProduct->save();
        $secondProduct = Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة زيتون',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'reserved_quantity' => 5,
            'status' => 'published',
        ]);
             $secondProduct->reserved_quantity = 5;
             $secondProduct->save();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'TEST-MULTI-ROLLBACK-001',
            'status' => 'pending_payment',
            'payment_method' => 'instapay',
            'payment_status' => 'pending',
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '01000000000',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Test Address',
            'subtotal' => 2000,
            'shipping_total' => 0,
            'grand_total' => 2000,
            'deposit_amount' => 600,
            'balance_amount' => 1400,
            'deposit_expires_at' => now()->subMinute(),
        ]);

        $supplierOrder = $order->supplierOrders()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'subtotal' => 2000,
            'shipping_total' => 0,
            'grand_total' => 2000,
        ]);

        $supplierOrder->items()->create([
            'product_id' => $firstProduct->id,
            'product_name_ar' => 'شتلة مانجو',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        $supplierOrder->items()->create([
            'product_id' => $secondProduct->id,
            'product_name_ar' => 'شتلة زيتون',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        $exitCode = Artisan::call('app:expire-unpaid-orders');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Insufficient reserved stock',
            Artisan::output()
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
            'stock_released_at' => null,
        ]);

        $this->assertDatabaseHas('supplier_orders', [
            'id' => $supplierOrder->id,
            'status' => 'pending',
            'stock_released_at' => null,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'reserved_quantity' => 10,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $secondProduct->id,
            'reserved_quantity' => 5,
        ]);
    }

   
    }


