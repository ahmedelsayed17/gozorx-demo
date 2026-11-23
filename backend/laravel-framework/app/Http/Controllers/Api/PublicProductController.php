<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['supplier:id,business_name', 'category:id,name_ar,name_en', 'images'])
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

        $products->getCollection()->transform(function ($product) {
            $product->image_url = $product->image_path
                ? Storage::disk('public')->url($product->image_path)
                : null;

            $product->images->each(function ($image) {
                $image->image_url = Storage::disk('public')->url($image->image_path);
            });

            return $product;
        });

        return response()->json($products);
    }
}
