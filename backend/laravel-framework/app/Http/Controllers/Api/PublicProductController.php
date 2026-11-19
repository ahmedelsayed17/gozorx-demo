<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['supplier:id,business_name', 'category:id,name_ar,name_en'])
            ->where('status', 'published')
            ->whereHas('supplier', function ($supplier) {
                $supplier->where('status', 'approved');
            });

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $products = $query
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }
}
