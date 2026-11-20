<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
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
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (!$request->user()->is_active) {
            throw ValidationException::withMessages([
                'account' => ['هذا الحساب غير نشط.'],
            ]);
        }

        $orders = DB::transaction(function () use ($validated, $request) {
            $productIds = collect($validated['items'])
                ->pluck('product_id')
                ->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->with('supplier')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $createdOrders = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if (!$product || $product->status !== 'published') {
                    throw ValidationException::withMessages([
                        'items' => ['أحد المنتجات غير متاح للشراء.'],
                    ]);
                }

                if (
                    !$product->supplier ||
                    $product->supplier->status !== 'approved'
                ) {
                    throw ValidationException::withMessages([
                        'items' => ['المورد الخاص بأحد المنتجات غير معتمد.'],
                    ]);
                }

                if ($product->available_quantity === null) {
                    throw ValidationException::withMessages([
                        'items' => ['كمية أحد المنتجات غير محددة.'],
                    ]);
                }

                if ($quantity < (int) $product->minimum_order_quantity) {
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

                $lineTotal = round(
                    (float) $product->unit_price * $quantity,
                    2
                );

                // Every distinct product creates its own independent order.
                $order = Order::create([
                    'buyer_id' => $request->user()->id,
                    'supplier_id' => $product->supplier_id,
                    'order_number' => 'GX-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
                    'status' => 'pending_shipping_cost',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => 'pending',
                    'recipient_name' => $validated['recipient_name'],
                    'recipient_phone' => $validated['recipient_phone'],
                    'governorate' => $validated['governorate'],
                    'city' => $validated['city'],
                    'address' => $validated['address'],
                    'delivery_notes' => $validated['delivery_notes'] ?? null,
                    'subtotal' => $lineTotal,
                    'shipping_total' => 0,
                    'grand_total' => 0,
                    'deposit_amount' => 0,
                    'balance_amount' => 0,
                    'deposit_expires_at' => null,
                    'shipping_cost_set_at' => null,
                ]);

                $order->individualItem()->create([
                    'product_id' => $product->id,
                    'product_name_ar' => $product->name_ar,
                    'product_name_en' => $product->name_en,
                    'unit_name' => $product->unit_name,
                    'unit_price' => $product->unit_price,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);

                $createdOrders[] = $order;
            }

            return collect($createdOrders);
        });

              $orders->each(function ($order) {
            $order->load([
                'individualItem',
                'supplier:id,business_name,business_type,description,status',
            ]);
        });

        return response()->json([
            'message' => 'تم إنشاء طلب مستقل لكل منتج بنجاح. سيتم تحديد تكلفة الشحن لكل طلب بشكل منفصل.',
            'orders' => $orders,
        ], 201);
    }

    public function index(Request $request)
    {
        $orders = Order::query()
            ->where('buyer_id', $request->user()->id)
           ->with([
    'individualItem',
    'supplier:id,business_name,business_type,description,status',
    'supplierOrders.supplier:id,business_name,business_type,description,status',
    'supplierOrders.items',
                'paymentTransactions' => function ($query) {
                    $query->where('payment_type', 'deposit')
                        ->latest('id');
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
$order->load([
    'individualItem',
    'supplier:id,business_name,business_type,description,status',
    'supplierOrders.supplier:id,business_name,business_type,description,status',
    'supplierOrders.items',
]);

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
