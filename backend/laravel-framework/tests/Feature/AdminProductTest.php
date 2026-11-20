<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_product_and_publish_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Test Nursery',
            'status' => 'approved',
        ]);

        $product = $supplier->products()->create([
            'name_ar' => 'شجرة مانجو',
            'unit_price' => 150,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$product->id}/decision", [
                'decision' => 'approved',
                'review_note' => 'منتج مطابق',
            ])
            ->assertOk()
            ->assertJsonPath('product.status', 'published');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'published',
            'reviewed_by' => $admin->id,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment(['name_ar' => 'شجرة مانجو']);
    }

    public function test_admin_can_reject_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Test Nursery',
            'status' => 'approved',
        ]);

        $product = $supplier->products()->create([
            'name_ar' => 'شجرة ليمون',
            'unit_price' => 100,
            'minimum_order_quantity' => 3,
            'available_quantity' => 100,
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$product->id}/decision", [
                'decision' => 'rejected',
                'review_note' => 'بيانات المنتج غير مكتملة',
            ])
            ->assertOk()
            ->assertJsonPath('product.status', 'rejected');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'rejected',
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonMissing(['name_ar' => 'شجرة ليمون']);
    }

    public function test_buyer_cannot_review_products(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/admin/products')
            ->assertForbidden();
    }
}
