<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplierProductController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable','integer','exists:categories,id'],
            'name_ar' => ['required','string','max:220'],
            'name_en' => ['nullable','string','max:220'],
            'description' => ['nullable','string'],
            'unit_name' => ['required','string','max:50'],
            'unit_price' => ['required','numeric','min:0'],
            'minimum_order_quantity' => ['required','integer','min:1'],
            'available_quantity' => ['nullable','integer','min:0'],
        ]);

        $supplier = $request->user()->supplierProfile;
        abort_unless($supplier && $supplier->status === 'approved', 403, 'Supplier approval required.');

        $product = $supplier->products()->create($data + ['status' => 'draft']);
        return response()->json(['message' => 'تم إنشاء المنتج كمسودة.', 'product' => $product], 201);
    }
}
