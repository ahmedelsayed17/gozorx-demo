<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithDeposit(
        User $buyer,
        array $orderOverrides = [],
        array $paymentOverrides = []
    ): Order {
        $order = Order::create(array_merge([
            'buyer_id' => $buyer->id,
            'order_number' => 'PAY-TEST-001',
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
        ], $orderOverrides));

        $order->paymentTransactions()->create(array_merge([
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
        ], $paymentOverrides));

        return $order;
    }

    public function test_buyer_can_upload_deposit_receipt_successfully(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer);

        $response = $this->actingAs($buyer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $order->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'receipt.jpg',
                    100,
                    'image/jpeg'
                ),
                'transaction_reference' => 'TRANSFER-12345',
            ]);

        $response->assertOk()
            ->assertJsonPath('payment.payment_type', 'deposit')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.transaction_reference', 'TRANSFER-12345')
            ->assertJsonPath('payment.receipt_uploaded', true);

        $transaction = $order->paymentTransactions()->first();

        $this->assertNotNull($transaction->receipt_path);

        Storage::disk('local')->assertExists($transaction->receipt_path);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'payment_type' => 'deposit',
            'status' => 'pending',
            'transaction_reference' => 'TRANSFER-12345',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);
    }

    public function test_buyer_cannot_upload_receipt_for_another_buyers_order(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $anotherBuyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($owner);

        $response = $this->actingAs($anotherBuyer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $order->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'receipt.jpg',
                    100,
                    'image/jpeg'
                ),
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'receipt_path' => null,
        ]);
    }

    public function test_buyer_cannot_upload_receipt_after_deposit_deadline(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer, [
            'deposit_expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $order->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'receipt.jpg',
                    100,
                    'image/jpeg'
                ),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'receipt_path' => null,
        ]);
    }

    public function test_buyer_cannot_upload_receipt_after_deposit_is_approved(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer, [], [
            'status' => 'approved',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $order->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'receipt.jpg',
                    100,
                    'image/jpeg'
                ),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('payment');
    }

    public function test_buyer_cannot_upload_unsupported_receipt_file(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer);

        $response = $this->actingAs($buyer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $order->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'receipt.txt',
                    10,
                    'text/plain'
                ),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'receipt_path' => null,
        ]);
    }

    public function test_buyer_can_retrieve_their_own_order(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id);

        $response->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonPath('order.receipt_uploaded', false);
    }

    public function test_buyer_cannot_retrieve_another_buyers_order(): void
    {
        $owner = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $anotherBuyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($owner);

        $response = $this->actingAs($anotherBuyer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id);

        $response->assertNotFound();
    }

    public function test_retrieved_order_reports_when_deposit_receipt_was_uploaded(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $order = $this->createOrderWithDeposit($buyer, [], [
            'receipt_path' => 'receipts/test-receipt.jpg',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id);

        $response->assertOk()
            ->assertJsonPath('order.receipt_uploaded', true);
    }

}
