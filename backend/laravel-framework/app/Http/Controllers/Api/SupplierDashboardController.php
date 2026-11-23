<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierDashboardController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $request->user()->supplierProfile;

        // The supplier.approved middleware guarantees an approved profile.
        abort_unless($supplier, 403, 'Supplier approval required.');

        $products = $supplier->products();

        // Collect this supplier's orders from both supported order structures.
        $orderIds = DB::table('orders')
            ->where('supplier_id', $supplier->id)
            ->select('id')
            ->union(
                DB::table('supplier_orders')
                    ->where('supplier_id', $supplier->id)
                    ->select('order_id')
            );

        $orders = DB::table('orders')
            ->whereIn('id', $orderIds);

        $activeStatuses = [
            'pending_shipping_cost',
            'pending_payment',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
        ];

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'business_type' => $supplier->business_type,
                'description' => $supplier->description,
                'address' => $supplier->address,
                'status' => $supplier->status,
            ],
            'products' => [
                'total' => (clone $products)->count(),
                'published' => (clone $products)->where('status', 'published')->count(),
                'draft' => (clone $products)->where('status', 'draft')->count(),
                'rejected' => (clone $products)->where('status', 'rejected')->count(),
            ],
            'orders' => [
                'total' => (clone $orders)->count(),
                'active' => (clone $orders)->whereIn('status', $activeStatuses)->count(),
                'completed' => (clone $orders)->where('status', 'completed')->count(),
            ],
        ]);
    }
}
