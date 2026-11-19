<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:vodafone_cash,instapay'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'governorate' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (!$request->user()->is_active) {
            throw ValidationException::withMessages([
                'account' => ['هذا الحساب غير نشط.'],
            ]);
        }

        return DB::transaction(function () use ($validated, $request) {
            $productIds = collect($validated['items'])
                ->pluck('product_id')
                ->all();

            $products = Product::whereIn('id', $productIds)
                ->with('supplier')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $itemsBySupplier = [];
            $subtotal = 0;

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if (!$product || $product->status !== 'published') {
                    throw ValidationException::withMessages([
                        'items' => ['أحد المنتجات غير متاح للشراء.'],
                    ]);
                }

                if (!$product->supplier || $product->supplier->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'items' => ['المورد الخاص بأحد المنتجات غير معتمد.'],
                    ]);
                }

                if ($product->available_quantity === null) {
                    throw ValidationException::withMessages([
                        'items' => ['كمية أحد المنتجات غير محددة.'],
                    ]);
                }

                if ($quantity < $product->minimum_order_quantity) {
                    throw ValidationException::withMessages([
                        'items' => ['الكمية المطلوبة أقل من الحد الأدنى للمنتج.'],
                    ]);
                }

                $available = (int) $product->available_quantity
                    - (int) $product->reserved_quantity;

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => ['الكمية المطلوبة غير متوفرة في المخزون.'],
                    ]);
                }

                $lineTotal = (float) $product->unit_price * $quantity;
                $subtotal += $lineTotal;

                $itemsBySupplier[$product->supplier_id][] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $shippingTotal = 0;
            $grandTotal = round($subtotal + $shippingTotal, 2);
            $depositAmount = round($grandTotal * 0.30, 2);
            $balanceAmount = round($grandTotal - $depositAmount, 2);

            $order = Order::create([
                'buyer_id' => $request->user()->id,
                'order_number' => 'GX-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
                'status' => 'pending_payment',
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'governorate' => $validated['governorate'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'deposit_amount' => $depositAmount,
                'balance_amount' => $balanceAmount,
                'deposit_expires_at' => now()->addHours(24),
            ]);

            $order->paymentTransactions()->create([
                'payment_type' => 'deposit',
                'payment_method' => $validated['payment_method'],
                'amount' => $depositAmount,
                'status' => 'pending',
            ]);

            foreach ($itemsBySupplier as $supplierId => $supplierItems) {
                $supplierSubtotal = round(
                    collect($supplierItems)->sum('line_total'),
                    2
                );

                $supplierOrder = $order->supplierOrders()->create([
                    'supplier_id' => $supplierId,
                    'status' => 'pending',
                    'subtotal' => $supplierSubtotal,
                    'shipping_total' => 0,
                    'grand_total' => $supplierSubtotal,
                ]);

                foreach ($supplierItems as $item) {
                    $product = $item['product'];
                    $quantity = $item['quantity'];

                    $supplierOrder->items()->create([
                        'product_id' => $product->id,
                        'product_name_ar' => $product->name_ar,
                        'product_name_en' => $product->name_en,
                        'unit_name' => $product->unit_name,
                        'unit_price' => $product->unit_price,
                        'quantity' => $quantity,
                        'line_total' => $item['line_total'],
                    ]);

                    $product->increment('reserved_quantity', $quantity);
                }
            }

            return response()->json([
                'message' => 'تم إنشاء الطلب وحجز الكميات لمدة 24 ساعة.',
                'order' => $order->load('supplierOrders.items'),
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $orders = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->with([
                'supplierOrders.items',
                'paymentTransactions' => function ($query) {
                    $query->where('payment_type', 'deposit')->latest('id');
                },
            ])
            ->latest('id')
            ->get();

        $orders->each(function ($order) {
            $deposit = $order->paymentTransactions->first();

            $receiptUploaded = (bool) ($deposit?->receipt_path);

            $order->setAttribute('receipt_uploaded', $receiptUploaded);
            $order->setAttribute('deposit_transaction', $deposit ? [
                'status' => $deposit->status,
                'review_note' => $deposit->review_note,
                'transaction_reference' => $deposit->transaction_reference,
                'receipt_uploaded' => $receiptUploaded,
            ] : null);

            $order->unsetRelation('paymentTransactions');
        });

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless(
            (int) $order->buyer_id === (int) $request->user()->id,
            404
        );

        $order->load('supplierOrders.items', 'supplierOrders.supplier.user');

        $depositTransaction = $order->paymentTransactions()
            ->where('payment_type', 'deposit')
            ->latest('id')
            ->first();

        $receiptUploaded = (bool) ($depositTransaction?->receipt_path);

        $order->setAttribute('receipt_uploaded', $receiptUploaded);

        $order->setAttribute('deposit_transaction', $depositTransaction ? [
            'status' => $depositTransaction->status,
            'review_note' => $depositTransaction->review_note,
            'transaction_reference' => $depositTransaction->transaction_reference,
            'receipt_uploaded' => $receiptUploaded,
        ] : null);

        return response()->json([
            'order' => $order,
        ]);
    }

}
