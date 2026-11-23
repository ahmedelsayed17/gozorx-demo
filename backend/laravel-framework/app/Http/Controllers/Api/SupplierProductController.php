<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierProductController extends Controller
{
    private function supplier(Request $request)
    {
        $supplier = $request->user()->supplierProfile;

        abort_unless(
            $supplier && $supplier->status === 'approved',
            403,
            'Supplier approval required.'
        );

        return $supplier;
    }

    private function saveProductImages(Product $item, array $images): void
    {
        foreach ($images as $image) {
            $path = $image->store("products/{$item->supplier_id}", 'public');

            $isPrimary = !$item->image_path;

            $item->images()->create([
                'image_path' => $path,
                'is_primary' => $isPrimary,
                'sort_order' => $item->images()->count(),
            ]);

            if ($isPrimary) {
                $item->update(['image_path' => $path]);
            }
        }
    }

    public function index(Request $request)
    {
        $supplier = $this->supplier($request);

        $products = $supplier->products()
            ->with(['category:id,name_ar,name_en', 'images'])
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    public function show(Request $request, int $product)
    {
        $supplier = $this->supplier($request);

        $item = $supplier->products()
            ->with(['category:id,name_ar,name_en', 'images'])
            ->whereKey($product)
            ->firstOrFail();

        return response()->json(['product' => $item]);
    }

    public function store(Request $request)
    {
        $supplier = $this->supplier($request);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:220'],
            'name_en' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string'],
            'unit_name' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'available_quantity' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $images = $request->file('images', []);

        if ($request->hasFile('image')) {
            $images[] = $request->file('image');
        }

        abort_if(count($images) > 5, 422, 'Maximum 5 product images allowed.');

        unset($data['image'], $data['images']);

        $item = $supplier->products()->create($data + [
            'status' => 'draft',
        ]);

        if ($images) {
            $this->saveProductImages($item, $images);
        }

        return response()->json([
            'message' => 'تم إنشاء المنتج كمسودة.',
            'product' => $item->fresh()->load('images'),
        ], 201);
    }

    public function update(Request $request, int $product)
    {
        $supplier = $this->supplier($request);
        $item = $supplier->products()->whereKey($product)->firstOrFail();

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:220'],
            'name_en' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string'],
            'unit_name' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'available_quantity' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delete_image_ids' => ['nullable', 'array', 'max:5'],
            'delete_image_ids.*' => ['integer'],
        ]);

        $newImages = $request->file('images', []);

        if ($request->hasFile('image')) {
            $newImages[] = $request->file('image');
        }

        // Backfill legacy image_path so it can be managed like other images.
        if ($item->images()->count() === 0 && $item->image_path) {
            $item->images()->create([
                'image_path' => $item->image_path,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        $deleteIds = $data['delete_image_ids'] ?? [];
        unset($data['image'], $data['images'], $data['delete_image_ids']);

        $imagesToDelete = $item->images()->whereIn('id', $deleteIds)->get();

        abort_if(
            $imagesToDelete->count() !== count(array_unique($deleteIds)),
            422,
            'One or more selected images do not belong to this product.'
        );

        $remainingCount = $item->images()->count() - $imagesToDelete->count();

        abort_if(
            $remainingCount + count($newImages) > 5,
            422,
            'Maximum 5 product images allowed.'
        );

        foreach ($imagesToDelete as $image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();
        }

        // Reorder remaining images and set the first one as primary.
        $remainingImages = $item->images()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($remainingImages as $index => $image) {
            $image->update([
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }

        $item->update([
            'image_path' => $remainingImages->first()?->image_path,
        ]);

        $data['status'] = 'draft';
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        $item->update($data);

        if ($newImages) {
            $this->saveProductImages($item, $newImages);
        }

        return response()->json([
            'message' => 'تم تحديث المنتج وإرساله للمراجعة من جديد.',
            'product' => $item->fresh()->load('images'),
        ]);
    }
}
