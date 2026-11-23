<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Order::query()
            ->with([
                'buyer:id,full_name,name,email,phone',
                'supplierOrders.supplier:id,user_id,business_name,status',
                'supplierOrders.supplier.user:id,full_name,name,email,phone',
                'supplierOrders.items',
                'paymentTransactions' => function ($query) {
                    $query->where('payment_type', 'deposit')
                        ->latest('id');
                },
            ])
            ->latest('id');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%")
                    ->orWhereHas('buyer', function ($buyerQuery) use ($search) {
                        $buyerQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(20);

        $orders->getCollection()->each(function ($order) {
            $this->attachSafeDepositData($order);
        });

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load([
            'buyer:id,full_name,name,email,phone',
            'supplierOrders.supplier:id,user_id,business_name,status',
            'supplierOrders.supplier.user:id,full_name,name,email,phone',
            'supplierOrders.items',
            'paymentTransactions' => function ($query) {
                $query->where('payment_type', 'deposit')
                    ->latest('id');
            },
        ]);

        $this->attachSafeDepositData($order);

        return response()->json([
            'order' => $order,
        ]);
    }

    private function attachSafeDepositData(Order $order): void
    {
        $deposit = $order->paymentTransactions->first();

        $order->setAttribute('receipt_uploaded', (bool) ($deposit?->receipt_path));

        $order->setAttribute('deposit_transaction', $deposit ? [
            'id' => $deposit->id,
            'payment_method' => $deposit->payment_method,
            'amount' => $deposit->amount,
            'status' => $deposit->status,
            'transaction_reference' => $deposit->transaction_reference,
            'review_note' => $deposit->review_note,
            'receipt_uploaded' => (bool) $deposit->receipt_path,
            'reviewed_at' => $deposit->reviewed_at,
        ] : null);

        $order->unsetRelation('paymentTransactions');
    }
}
