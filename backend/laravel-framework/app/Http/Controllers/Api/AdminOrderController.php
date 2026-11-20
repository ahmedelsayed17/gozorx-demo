<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\PaymentTransaction;
use App\Models\OrderShippingHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                'supplier:id,user_id,business_name,status',
                'supplier.user:id,full_name,name,email,phone',
                'individualItem',
                // Legacy orders compatibility
    'supplierOrders.supplier:id,user_id,business_name,status',
    'supplierOrders.supplier.user:id,full_name,name,email,phone',
    'supplierOrders.items',
                'shippingHistories.admin:id,full_name,name,email',
                'paymentTransactions' => function ($query) {
                    $query->where('payment_type', 'deposit')->latest('id');
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
            'supplier:id,user_id,business_name,status',
            'supplier.user:id,full_name,name,email,phone',
            'individualItem',
            // Legacy orders compatibility
'supplierOrders.supplier:id,user_id,business_name,status',
'supplierOrders.supplier.user:id,full_name,name,email,phone',
'supplierOrders.items',

            'shippingHistories.admin:id,full_name,name,email',
            'paymentTransactions' => function ($query) {
                $query->where('payment_type', 'deposit')->latest('id');
            },
        ]);

        $this->attachSafeDepositData($order);

        return response()->json(['order' => $order]);
    }

    public function setShippingCost(Request $request, Order $order)
    {
        $validated = $request->validate([
            'shipping_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $order, $validated) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'pending_shipping_cost') {
                throw ValidationException::withMessages([
                    'order' => 'Shipping cost can only be set while the order is awaiting shipping pricing.',
                ]);
            }

            $item = $lockedOrder->individualItem()->first();

            if (!$item) {
                throw ValidationException::withMessages([
                    'order' => 'Individual order item was not found.',
                ]);
            }

            if (!$item->product_id) {
                throw ValidationException::withMessages([
                    'product' => 'The product is no longer available for stock reservation.',
                ]);
            }

            $product = Product::query()
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'product' => 'The product could not be found.',
                ]);
            }

            $quantity = (int) $item->quantity;
            $available = (int) $product->available_quantity;
            $reserved = (int) $product->reserved_quantity;
            $remaining = $available - $reserved;

            if ($quantity < 1 || $remaining < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => 'The requested quantity is no longer available. Shipping cost was not saved.',
                ]);
            }

            $shippingCost = round((float) $validated['shipping_cost'], 2);
            $subtotal = (float) $lockedOrder->subtotal;
            $grandTotal = round($subtotal + $shippingCost, 2);

            $depositAmount = (float) round(
                $grandTotal * 0.30,
                0,
                PHP_ROUND_HALF_UP
            );

            $balanceAmount = round($grandTotal - $depositAmount, 2);
            $now = now();

            OrderShippingHistory::create([
                'order_id' => $lockedOrder->id,
                'admin_id' => $request->user()->id,
                'old_shipping_cost' => null,
                'new_shipping_cost' => $shippingCost,
                'internal_notes' => $validated['internal_notes'] ?? null,
            ]);

            $product->increment('reserved_quantity', $quantity);

            $lockedOrder->shipping_total = $shippingCost;
            $lockedOrder->grand_total = $grandTotal;
            $lockedOrder->deposit_amount = $depositAmount;
            $lockedOrder->balance_amount = $balanceAmount;
            $lockedOrder->shipping_cost_set_at = $now;
            $lockedOrder->deposit_expires_at = $now->copy()->addHours(24);
            $lockedOrder->stock_released_at = null;
            $lockedOrder->stock_committed_at = null;
            $lockedOrder->status = 'pending_payment';
            $lockedOrder->payment_status = 'pending';
            $lockedOrder->save();

            PaymentTransaction::create([
                'order_id' => $lockedOrder->id,
                'payment_type' => 'deposit',
                'payment_method' => $lockedOrder->payment_method,
                'amount' => $depositAmount,
                'status' => 'pending',
            ]);

            return [
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'deposit_amount' => $depositAmount,
                'balance_amount' => $balanceAmount,
                'deposit_expires_at' => $lockedOrder->deposit_expires_at,
                'status' => $lockedOrder->status,
                'payment_status' => $lockedOrder->payment_status,
            ];
        });

        return response()->json([
            'message' => 'Shipping cost saved. Deposit and stock reservation are now active.',
            'order' => $result,
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
