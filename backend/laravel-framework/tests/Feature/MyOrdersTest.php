<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function createTestOrder(
        User $buyer,
        string $number,
        array $paymentOverrides = []
    ): Order {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => $number,
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

        $order->paymentTransactions()->create(array_merge([
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
        ], $paymentOverrides));

        return $order;
    }

    public function test_buyer_can_only_see_their_own_orders(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $anotherBuyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $olderOrder = $this->createTestOrder($buyer, 'MY-ORDER-OLD');
        $newerOrder = $this->createTestOrder($buyer, 'MY-ORDER-NEW');
        $this->createTestOrder($anotherBuyer, 'OTHER-BUYER-ORDER');

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.order_number', $newerOrder->order_number)
            ->assertJsonPath('orders.1.order_number', $olderOrder->order_number)
            ->assertJsonMissing([
                'order_number' => 'OTHER-BUYER-ORDER',
            ]);
    }

    public function test_orders_list_shows_receipt_status_without_exposing_private_path(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createTestOrder($buyer, 'MY-RECEIPT-ORDER', [
            'receipt_path' => 'private/receipts/secret-receipt.jpg',
            'transaction_reference' => 'TRANSFER-123',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('orders.0.id', $order->id)
            ->assertJsonPath('orders.0.receipt_uploaded', true)
            ->assertJsonPath('orders.0.deposit_transaction.status', 'pending')
            ->assertJsonPath(
                'orders.0.deposit_transaction.transaction_reference',
                'TRANSFER-123'
            );

        $orderData = $response->json('orders.0');

        $this->assertArrayNotHasKey('payment_transactions', $orderData);
        $this->assertArrayNotHasKey('receipt_path', $orderData);
        $this->assertArrayNotHasKey(
            'receipt_path',
            $orderData['deposit_transaction']
        );
    }

    public function test_guest_cannot_access_orders_list(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
