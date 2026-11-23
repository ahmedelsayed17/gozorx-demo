<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\SupplierProfile;
use App\Models\Product;

class SupplierDashboardTest extends TestCase
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

    public function test_approved_supplier_can_view_dashboard(): void
    {
        [$user, $supplier] = $this->createSupplier();

        Product::create([
            'supplier_id' => $supplier->id,
            'name_ar' => 'شتلة مانجو',
            'name_en' => 'Mango Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 350,
            'minimum_order_quantity' => 5,
            'available_quantity' => 100,
            'status' => 'published',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard')
            ->assertOk()
            ->assertJsonPath('supplier.business_name', 'Test Nursery')
            ->assertJsonPath('products.total', 1)
            ->assertJsonPath('products.published', 1)
            ->assertJsonPath('orders.total', 0);
    }

    public function test_buyer_without_supplier_profile_cannot_view_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard')
            ->assertForbidden();
    }

    public function test_unapproved_supplier_cannot_view_dashboard(): void
    {
        [$user] = $this->createSupplier('pending_approval');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard')
            ->assertForbidden();
    }

    public function test_supplier_dashboard_only_counts_own_products(): void
    {
        [$userA, $supplierA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        foreach ([$supplierA, $supplierB] as $supplier) {
            Product::create([
                'supplier_id' => $supplier->id,
                'name_ar' => 'شتلة اختبار',
                'name_en' => 'Test Seedling',
                'unit_name' => 'شتلة',
                'unit_price' => 200,
                'minimum_order_quantity' => 5,
                'available_quantity' => 50,
                'status' => 'published',
            ]);
        }

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard')
            ->assertOk()
            ->assertJsonPath('products.total', 1);
    }
}
