<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function uploadDepositReceipt(Request $request, Order $order)
    {
        $validated = $request->validate([
            'receipt' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:160',
            ],
        ]);

        $receiptPath = null;
        $oldReceiptPath = null;

        try {
            DB::transaction(function () use (
                $request,
                $order,
                $validated,
                &$receiptPath,
                &$oldReceiptPath
            ) {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedOrder->buyer_id !== (int) $request->user()->id) {
                    abort(403, 'You are not allowed to upload a receipt for this order.');
                }

                if ($lockedOrder->status !== 'pending_payment') {
                    throw ValidationException::withMessages([
                        'order' => 'This order is not awaiting deposit payment.',
                    ]);
                }

                if (
                    !$lockedOrder->deposit_expires_at ||
                    $lockedOrder->deposit_expires_at->lte(now())
                ) {
                    throw ValidationException::withMessages([
                        'order' => 'The deposit payment deadline has expired.',
                    ]);
                }

                $depositTransaction = $lockedOrder->paymentTransactions()
                    ->where('payment_type', 'deposit')
                    ->lockForUpdate()
                    ->first();

                if (!$depositTransaction) {
                    throw ValidationException::withMessages([
                        'payment' => 'Deposit transaction was not found.',
                    ]);
                }

                if (!in_array($depositTransaction->status, ['pending', 'rejected'], true)) {
                    throw ValidationException::withMessages([
                        'payment' => 'This deposit transaction cannot accept a receipt.',
                    ]);
                }

                if (
                    $depositTransaction->status === 'pending' &&
                    $depositTransaction->receipt_path
                ) {
                    throw ValidationException::withMessages([
                        'payment' => 'Your receipt is already awaiting admin review.',
                    ]);
                }

                $oldReceiptPath = $depositTransaction->receipt_path;

                $receiptPath = $request->file('receipt')->store(
                    'payment-receipts/' . $lockedOrder->id,
                    'local'
                );

                if (!$receiptPath) {
                    throw new \RuntimeException('Receipt file could not be stored.');
                }

                $depositTransaction->update([
                    'receipt_path' => $receiptPath,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'status' => 'pending',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw $exception;
        }

        if ($oldReceiptPath && $oldReceiptPath !== $receiptPath) {
            Storage::disk('local')->delete($oldReceiptPath);
        }

        return response()->json([
            'message' => 'Receipt uploaded successfully and is awaiting admin review.',
            'payment' => [
                'payment_type' => 'deposit',
                'status' => 'pending',
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'receipt_uploaded' => true,
            ],
        ], 200);
    }

    public function pendingDepositReceipts()
    {
        $payments = PaymentTransaction::query()
            ->with(['order.buyer'])
            ->where('payment_type', 'deposit')
            ->where('status', 'pending')
            ->whereNotNull('receipt_path')
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function showDepositReceipt(PaymentTransaction $paymentTransaction)
    {
        abort_unless(
            $paymentTransaction->payment_type === 'deposit' &&
            $paymentTransaction->receipt_path,
            404
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($paymentTransaction->receipt_path), 404);

        return response()->file(
            $disk->path($paymentTransaction->receipt_path)
        );
    }

    public function decideDeposit(Request $request, PaymentTransaction $paymentTransaction)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:decision,rejected',
            ],
        ]);

        $result = DB::transaction(function () use (
            $request,
            $paymentTransaction,
            $validated
        ) {
            $order = Order::query()
                ->whereKey($paymentTransaction->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = PaymentTransaction::query()
                ->whereKey($paymentTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->payment_type !== 'deposit') {
                throw ValidationException::withMessages([
                    'payment' => 'Only deposit transactions can be reviewed here.',
                ]);
            }

            if (!$payment->receipt_path) {
                throw ValidationException::withMessages([
                    'receipt' => 'No receipt has been uploaded for this payment.',
                ]);
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is not awaiting review.',
                ]);
            }

            if ($order->status !== 'pending_payment') {
                throw ValidationException::withMessages([
                    'order' => 'This order is no longer awaiting deposit payment.',
                ]);
            }

            if (
                !$order->deposit_expires_at ||
                $order->deposit_expires_at->lte(now())
            ) {
                throw ValidationException::withMessages([
                    'order' => 'The deposit payment deadline has expired.',
                ]);
            }

            $payment->reviewed_by = $request->user()->id;
            $payment->reviewed_at = now();

            if ($validated['decision'] === 'rejected') {
                $payment->review_note = $validated['review_note'];
            }

            $payment->status = $validated['decision'];
            $payment->save();

            if ($validated['decision'] === 'approved') {
                $order->status = 'confirmed';
                $order->payment_status = 'partially_paid';
                $order->save();

                return [
                    'decision' => 'approved',
                    'order_status' => $order->status,
                    'payment_status' => $order->payment_status,
                ];
            }

            return [
                'decision' => 'rejected',
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'review_note' => $payment->review_note,
            ];
        });

        return response()->json([
            'message' => $result['decision'] === 'approved'
                ? 'Deposit approved and order confirmed.'
                : 'Receipt rejected. The buyer can upload a new receipt before the original deadline.',
            'result' => $result,
        ]);
    }
}
