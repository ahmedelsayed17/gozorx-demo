<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    public function index()
    {
        $products = Product::with('supplier.user')
            ->where('status', 'draft')
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    public function decide(Request $request, Product $product)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($product->status !== 'draft') {
            return response()->json([
                'message' => 'هذا المنتج تمت مراجعته بالفعل.',
            ], 422);
        }

        if ($data['decision'] === 'approved' &&
            $product->supplier->status !== 'approved') {
            return response()->json([
                'message' => 'لا يمكن نشر منتج مورد غير معتمد.',
            ], 422);
        }

        DB::transaction(function () use ($request, $product, $data) {
            $product->update([
                'status' => $data['decision'] === 'approved'
                    ? 'published'
                    : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id,
                'action' => 'product.' . $data['decision'],
                'entity_type' => 'product',
                'entity_id' => $product->id,
                'details' => json_encode([
                    'review_note' => $data['review_note'] ?? null,
                ]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'تمت مراجعة المنتج بنجاح.',
            'product' => $product->fresh(),
        ]);
    }
}
