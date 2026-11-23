<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $request->user()->supplierProfile;

        abort_unless(
            $supplier && $supplier->status === 'approved',
            403,
            'يجب اعتماد حساب المورد أولًا.'
        );

        $individualOrders = Order::query()
            ->where('supplier_id', $supplier->id)
            ->with([
                'individualItem',
            ])
            ->latest('id')
            ->get()
            ->map(function (Order $order) {
                return [
                    'type' => 'individual',
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'recipient_name' => $this->recipientFirstName($order->recipient_name),
                    'governorate' => $order->governorate,
                    'city' => $order->city,
                    'subtotal' => $order->subtotal,
                    'shipping_total' => $order->shipping_total,
                    'grand_total' => $order->grand_total,
                    'item' => $order->individualItem,
                ];
            });

        $legacyOrders = SupplierOrder::query()
            ->where('supplier_id', $supplier->id)
            ->with([
                'order',
                'items',
            ])
            ->latest('id')
            ->get()
            ->map(function (SupplierOrder $supplierOrder) {
                $order = $supplierOrder->order;

                return [
                    'type' => 'multi_supplier',
                    'id' => $supplierOrder->id,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'status' => $supplierOrder->status,
                    'payment_status' => $order?->payment_status,
                    'created_at' => $supplierOrder->created_at,
                    'recipient_name' => $this->recipientFirstName($order?->recipient_name),
                    'governorate' => $order?->governorate,
                    'city' => $order?->city,
                    'subtotal' => $supplierOrder->subtotal,
                    'shipping_total' => $supplierOrder->shipping_total,
                    'grand_total' => $supplierOrder->grand_total,
                    'cancellation_reason' => $supplierOrder->cancellation_reason,
                    'items' => $supplierOrder->items,
                ];
            });

        $orders = $individualOrders
            ->concat($legacyOrders)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, string $type, int $id)
    {
        $supplier = $request->user()->supplierProfile;

        abort_unless(
            $supplier && $supplier->status === 'approved',
            403,
            'يجب اعتماد حساب المورد أولًا.'
        );

        if ($type === 'individual') {
            $order = Order::query()
                ->where('supplier_id', $supplier->id)
                ->whereKey($id)
                ->with(['individualItem'])
                ->firstOrFail();

            return response()->json([
                'type' => 'individual',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'recipient_name' => $this->recipientFirstName($order->recipient_name),
                    'governorate' => $order->governorate,
                    'city' => $order->city,
                    'subtotal' => $order->subtotal,
                    'shipping_total' => $order->shipping_total,
                    'grand_total' => $order->grand_total,
                    'deposit_amount' => $order->deposit_amount,
                    'balance_amount' => $order->balance_amount,
                    'item' => $order->individualItem,
                ],
            ]);
        }

        if ($type === 'multi_supplier') {
            $supplierOrder = SupplierOrder::query()
                ->where('supplier_id', $supplier->id)
                ->whereKey($id)
                ->with(['order', 'items'])
                ->firstOrFail();

            $order = $supplierOrder->order;

            return response()->json([
                'type' => 'multi_supplier',
                'order' => [
                    'id' => $supplierOrder->id,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'status' => $supplierOrder->status,
                    'payment_status' => $order?->payment_status,
                    'created_at' => $supplierOrder->created_at,
                    'recipient_name' => $this->recipientFirstName($order?->recipient_name),
                    'governorate' => $order?->governorate,
                    'city' => $order?->city,
                    'subtotal' => $supplierOrder->subtotal,
                    'shipping_total' => $supplierOrder->shipping_total,
                    'grand_total' => $supplierOrder->grand_total,
                    'deposit_amount' => $supplierOrder->deposit_amount,
                    'balance_amount' => $supplierOrder->balance_amount,
                    'cancellation_reason' => $supplierOrder->cancellation_reason,
                    'items' => $supplierOrder->items,
                ],
            ]);
        }

        abort(404, 'نوع الطلب غير صحيح.');
    }

    private function recipientFirstName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\\s+/u', $name);

        return $parts[0] ?? null;
    }

}
