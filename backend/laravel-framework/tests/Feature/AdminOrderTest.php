<?php

namespace Tests\Feature;

use App\Models\Order;
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

        $order->paymentTransactions()->create(array_merge([
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

        $firstOrder = $this->createTestOrder($buyerOne, 'ADMIN-ORDER-001');
        $secondOrder = $this->createTestOrder($buyerTwo, 'ADMIN-ORDER-002');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/orders');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_number', $secondOrder->order_number)
            ->assertJsonPath('data.1.order_number', $firstOrder->order_number)
            ->assertJsonPath('data.0.buyer.id', $buyerTwo->id);
    }

    public function test_admin_can_filter_orders_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'full_name' => 'Searchable Buyer',
        ]);

        $this->createTestOrder($buyer, 'SEARCH-ORDER-001');
        $this->createTestOrder($buyer, 'OTHER-ORDER-002', 'cancelled');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/orders?status=pending_payment&search=SEARCH-ORDER');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'SEARCH-ORDER-001');
    }

    public function test_admin_can_view_order_details_without_private_receipt_path(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer']);

        $order = $this->createTestOrder($buyer, 'ADMIN-DETAIL-001', 'pending_payment', [
            'receipt_path' => 'private/receipts/secret-receipt.jpg',
            'transaction_reference' => 'TRANSFER-123',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.buyer.id', $buyer->id)
            ->assertJsonPath('order.deposit_transaction.status', 'pending')
            ->assertJsonPath('order.deposit_transaction.receipt_uploaded', true)
            ->assertJsonPath(
                'order.deposit_transaction.transaction_reference',
                'TRANSFER-123'
            );

        $orderData = $response->json('order');

        $this->assertArrayNotHasKey('payment_transactions', $orderData);
        $this->assertArrayNotHasKey('receipt_path', $orderData);
        $this->assertArrayNotHasKey(
            'receipt_path',
            $orderData['deposit_transaction']
        );
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
        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
    }
}
