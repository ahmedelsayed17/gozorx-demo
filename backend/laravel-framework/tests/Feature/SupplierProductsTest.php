<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductsTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(string $status = 'approved'): array
    {
        $user = User::factory()->create(['role' => 'supplier']);

        $supplier = SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Nursery',
            'business_type' => 'nursery',
            'address' => 'Cairo',
            'status' => $status,
        ]);

        return [$user, $supplier];
    }

    private function createProduct(SupplierProfile $supplier, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'name_en' => 'Mango Seedling',
            'description' => 'منتج للاختبار',
            'unit_name' => 'شتلة',
            'unit_price' => 350,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'draft',
        ], $attributes));
    }

    private function validUpdatePayload(): array
    {
        return [
            'name_ar' => 'شتلة زيتون',
            'name_en' => 'Olive Seedling',
            'description' => 'وصف محدث',
            'unit_name' => 'شتلة',
            'unit_price' => 250,
            'minimum_order_quantity' => 10,
            'available_quantity' => 80,
        ];
    }

    public function test_supplier_sees_only_own_products(): void
    {
        [$userA, $supplierA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $ownProduct = $this->createProduct($supplierA);
        $foreignProduct = $this->createProduct($supplierB, [
            'name_ar' => 'منتج مورد آخر',
        ]);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/supplier/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownProduct->id])
            ->assertJsonMissing(['id' => $foreignProduct->id]);
    }

    public function test_supplier_can_view_own_product_details(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct($supplier);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/supplier/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.name_ar', 'شتلة مانجو');
    }

    public function test_supplier_cannot_view_another_suppliers_product(): void
    {
        [$userA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $foreignProduct = $this->createProduct($supplierB);

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/supplier/products/{$foreignProduct->id}")
            ->assertNotFound();
    }

    public function test_editing_published_product_returns_it_to_draft(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $admin = User::factory()->create();

        $product = $this->createProduct($supplier, [
            'status' => 'published',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/supplier/products/{$product->id}", $this->validUpdatePayload())
            ->assertOk()
            ->assertJsonPath('product.status', 'draft')
            ->assertJsonPath('product.name_ar', 'شتلة زيتون')
            ->assertJsonPath('product.reviewed_by', null)
            ->assertJsonPath('product.reviewed_at', null);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function test_supplier_cannot_edit_another_suppliers_product(): void
    {
        [$userA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $foreignProduct = $this->createProduct($supplierB);

        $this->actingAs($userA, 'sanctum')
            ->putJson(
                "/api/v1/supplier/products/{$foreignProduct->id}",
                $this->validUpdatePayload()
            )
            ->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $foreignProduct->id,
            'supplier_id' => $supplierB->id,
            'name_ar' => 'شتلة مانجو',
        ]);
    }

    public function test_buyer_without_supplier_profile_cannot_manage_products(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/supplier/products')
            ->assertForbidden();
    }

    public function test_unapproved_supplier_cannot_manage_products(): void
    {
        [$user] = $this->createSupplier('pending_approval');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/supplier/products')
            ->assertForbidden();
    }
}
