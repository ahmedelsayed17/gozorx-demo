<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentAdminReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'order_number' => 'PAY-ADMIN-001',
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

        $path = 'payment-receipts/' . $order->id . '/receipt.jpg';
        Storage::disk('local')->put($path, 'test receipt');

        $payment = $order->paymentTransactions()->create([
            'payment_type' => 'deposit',
            'payment_method' => 'instapay',
            'amount' => 300,
            'status' => 'pending',
            'receipt_path' => $path,
        ]);

        return compact('buyer', 'admin', 'order', 'payment', 'path');
    }

    public function test_non_admin_cannot_access_pending_deposit_receipts(): void
    {
        $data = $this->createPayment();

        $response = $this->actingAs($data['buyer'], 'sanctum')
            ->getJson('/api/v1/admin/payments/deposit-receipts');

        $response->assertForbidden();
    }

    public function test_admin_can_list_pending_deposit_receipts(): void
    {
        $data = $this->createPayment();

        $response = $this->actingAs($data['admin'], 'sanctum')
            ->getJson('/api/v1/admin/payments/deposit-receipts');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $data['payment']->id)
            ->assertJsonPath('data.0.payment_type', 'deposit');
    }

    public function test_admin_can_approve_deposit_and_confirm_order(): void
    {
        $data = $this->createPayment();

        $response = $this->actingAs($data['admin'], 'sanctum')
            ->patchJson(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/decision',
                ['decision' => 'approved']
            );

        $response->assertOk()
            ->assertJsonPath('result.decision', 'approved')
            ->assertJsonPath('result.order_status', 'confirmed')
            ->assertJsonPath('result.payment_status', 'partially_paid');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $data['payment']->id,
            'status' => 'approved',
            'reviewed_by' => $data['admin']->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'confirmed',
            'payment_status' => 'partially_paid',
            'balance_amount' => 700,
        ]);
    }

    public function test_admin_can_reject_receipt_without_cancelling_order(): void
    {
        $data = $this->createPayment();

        $response = $this->actingAs($data['admin'], 'sanctum')
            ->patchJson(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/decision',
                [
                    'decision' => 'rejected',
                    'review_note' => 'Receipt amount is unclear.',
                ]
            );

        $response->assertOk()
            ->assertJsonPath('result.decision', 'rejected')
            ->assertJsonPath('result.order_status', 'pending_payment')
            ->assertJsonPath('result.payment_status', 'pending')
            ->assertJsonPath('result.review_note', 'Receipt amount is unclear.');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $data['payment']->id,
            'status' => 'rejected',
            'reviewed_by' => $data['admin']->id,
            'review_note' => 'Receipt amount is unclear.',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);
    }

    public function test_admin_cannot_reject_receipt_without_a_reason(): void
    {
        $data = $this->createPayment();

        $response = $this->actingAs($data['admin'], 'sanctum')
            ->patchJson(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/decision',
                ['decision' => 'rejected']
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('review_note');
    }

    public function test_buyer_can_reupload_receipt_after_rejection_without_changing_deadline_or_reason(): void
    {
        Storage::fake('local');

        $data = $this->createPayment();

        $originalDeadline = $data['order']->deposit_expires_at->toISOString();
        $oldReceiptPath = $data['path'];
        $rejectionReason = 'Receipt amount is unclear.';

        $rejectResponse = $this->actingAs($data['admin'], 'sanctum')
            ->patchJson(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/decision',
                [
                    'decision' => 'rejected',
                    'review_note' => $rejectionReason,
                ]
            );

        $rejectResponse->assertOk();

        $uploadResponse = $this->actingAs($data['buyer'], 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/orders/' . $data['order']->id . '/deposit-receipt', [
                'receipt' => UploadedFile::fake()->create(
                    'replacement.jpg',
                    100,
                    'image/jpeg'
                ),
                'transaction_reference' => 'NEW-TRANSFER-123',
            ]);

        $uploadResponse->assertOk()
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.receipt_uploaded', true);

        $payment = PaymentTransaction::findOrFail($data['payment']->id);
        $order = Order::findOrFail($data['order']->id);

        $this->assertEquals('pending', $payment->status);
        $this->assertEquals($rejectionReason, $payment->review_note);
        $this->assertEquals('NEW-TRANSFER-123', $payment->transaction_reference);

        $this->assertNotEquals($oldReceiptPath, $payment->receipt_path);
        Storage::disk('local')->assertMissing($oldReceiptPath);
        Storage::disk('local')->assertExists($payment->receipt_path);

        $this->assertEquals(
            $originalDeadline,
            $order->deposit_expires_at->toISOString()
        );

        $this->assertEquals('pending_payment', $order->status);
        $this->assertEquals('pending', $order->payment_status);
    }


    public function test_admin_can_view_deposit_receipt_file(): void
    {
        Storage::fake('local');

        $data = $this->createPayment();

        $response = $this->actingAs($data['admin'], 'sanctum')
            ->get(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/receipt'
            );

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=utf-8');
    }

    public function test_buyer_cannot_view_admin_deposit_receipt_file(): void
    {
        Storage::fake('local');

        $data = $this->createPayment();

        $response = $this->actingAs($data['buyer'], 'sanctum')
            ->getJson(
                '/api/v1/admin/payments/deposit-receipts/' .
                $data['payment']->id . '/receipt'
            );

        $response->assertForbidden();
    }

}
