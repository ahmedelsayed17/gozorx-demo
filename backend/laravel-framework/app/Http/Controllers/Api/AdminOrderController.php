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
                    $query->whereIn('payment_type', ['deposit', 'balance'])
                        ->with('collector:id,full_name,name')
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
            'supplier:id,user_id,business_name,status',
            'supplier.user:id,full_name,name,email,phone',
            'individualItem',
            // Legacy orders compatibility
'supplierOrders.supplier:id,user_id,business_name,status',
'supplierOrders.supplier.user:id,full_name,name,email,phone',
'supplierOrders.items',

            'shippingHistories.admin:id,full_name,name,email',
            'paymentTransactions' => function ($query) {
                $query->whereIn('payment_type', ['deposit', 'balance'])
                    ->with('collector:id,full_name,name')
                    ->latest('id');
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


    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:processing,shipped,delivered'],
        ]);

        $updatedOrder = DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $transitions = [
                'confirmed' => 'processing',
                'processing' => 'shipped',
                'shipped' => 'delivered',
            ];

            $nextStatus = $transitions[$lockedOrder->status] ?? null;

            if ($nextStatus !== $validated['status']) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid order status transition.',
                ]);
            }

            $lockedOrder->status = $nextStatus;

            if ($nextStatus === 'delivered') {
                $lockedOrder->delivered_at = now();
            }

            $lockedOrder->save();

            return $lockedOrder->fresh();
        });

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $updatedOrder,
        ]);
    }

    public function collectBalance(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'payment_method' => ['required', 'string', 'in:vodafone_cash,instapay,cash'],
        ]);

        $updatedOrder = DB::transaction(function () use ($request, $order, $validated) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order' => 'Balance can only be collected after delivery.',
                ]);
            }

            if ($lockedOrder->stock_committed_at !== null) {
                throw ValidationException::withMessages([
                    'order' => 'This order has already been completed.',
                ]);
            }

            $expectedAmount = number_format((float) $lockedOrder->balance_amount, 2, '.', '');
            $receivedAmount = number_format((float) $validated['amount'], 2, '.', '');

            if ($receivedAmount !== $expectedAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'The collected amount must exactly match the remaining balance.',
                ]);
            }

            $existingBalance = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->where('payment_type', 'balance')
                ->exists();

            if ($existingBalance) {
                throw ValidationException::withMessages([
                    'order' => 'The remaining balance has already been collected.',
                ]);
            }

            $productQuantities = [];

            $individualItem = $lockedOrder->individualItem()->first();

            if ($individualItem) {
                if (!$individualItem->product_id) {
                    throw ValidationException::withMessages([
                        'product' => 'The order product could not be found for stock completion.',
                    ]);
                }

                $productQuantities[$individualItem->product_id] =
                    ($productQuantities[$individualItem->product_id] ?? 0)
                    + (int) $individualItem->quantity;
            } else {
                $supplierOrders = $lockedOrder->supplierOrders()
                    ->with('items')
                    ->get();

                foreach ($supplierOrders as $supplierOrder) {
                    foreach ($supplierOrder->items as $legacyItem) {
                        if (!$legacyItem->product_id) {
                            throw ValidationException::withMessages([
                                'product' => 'An order product could not be found for stock completion.',
                            ]);
                        }

                        $productQuantities[$legacyItem->product_id] =
                            ($productQuantities[$legacyItem->product_id] ?? 0)
                            + (int) $legacyItem->quantity;
                    }
                }
            }

            if (empty($productQuantities)) {
                throw ValidationException::withMessages([
                    'product' => 'No order products were found for stock completion.',
                ]);
            }

            ksort($productQuantities);

            $products = Product::query()
                ->whereIn('id', array_keys($productQuantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productQuantities)) {
                throw ValidationException::withMessages([
                    'product' => 'One or more order products no longer exist.',
                ]);
            }

            foreach ($productQuantities as $productId => $quantity) {
                $product = $products->get($productId);
                $available = (int) $product->available_quantity;
                $reserved = (int) $product->reserved_quantity;

                if ($quantity < 1 || $available < $quantity || $reserved < $quantity) {
                    throw ValidationException::withMessages([
                        'stock' => 'Stock quantities are insufficient to complete this order.',
                    ]);
                }
            }

            $now = now();

            PaymentTransaction::create([
                'order_id' => $lockedOrder->id,
                'payment_type' => 'balance',
                'payment_method' => $validated['payment_method'],
                'amount' => $lockedOrder->balance_amount,
                'status' => 'approved',
                'collected_by' => $request->user()->id,
                'collected_at' => $now,
            ]);

            foreach ($productQuantities as $productId => $quantity) {
                $product = $products->get($productId);
                $product->available_quantity =
                    (int) $product->available_quantity - $quantity;
                $product->reserved_quantity =
                    (int) $product->reserved_quantity - $quantity;
                $product->save();
            }

            $lockedOrder->payment_status = 'paid';
            $lockedOrder->status = 'completed';
            $lockedOrder->completed_at = $now;
            $lockedOrder->stock_committed_at = $now;
            $lockedOrder->save();

            return $lockedOrder->fresh();
        });

        return response()->json([
            'message' => 'Remaining balance collected and order completed successfully.',
            'order' => $updatedOrder,
        ]);
    }

    private function attachSafeDepositData(Order $order): void
    {
        $deposit = $order->paymentTransactions
            ->firstWhere('payment_type', 'deposit');

        $balance = $order->paymentTransactions
            ->firstWhere('payment_type', 'balance');

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

        $order->setAttribute('balance_transaction', $balance ? [
            'id' => $balance->id,
            'payment_method' => $balance->payment_method,
            'amount' => $balance->amount,
            'status' => $balance->status,
            'collected_at' => $balance->collected_at,
            'collected_by' => $balance->collector ? [
                'id' => $balance->collector->id,
                'name' => $balance->collector->full_name ?: $balance->collector->name,
            ] : null,
        ] : null);

        $order->unsetRelation('paymentTransactions');
    }
}
