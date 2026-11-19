<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_sees_only_published_products_from_approved_suppliers(): void
    {
        $approvedUser = User::factory()->create();

        $approvedSupplier = $approvedUser->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $approvedSupplier->products()->create([
            'name_ar' => 'شجرة مانجو',
            'unit_price' => 150,
            'minimum_order_quantity' => 5,
            'status' => 'published',
        ]);

        $approvedSupplier->products()->create([
            'name_ar' => 'شجرة ليمون مسودة',
            'unit_price' => 100,
            'minimum_order_quantity' => 3,
            'status' => 'draft',
        ]);

        $pendingUser = User::factory()->create();

        $pendingSupplier = $pendingUser->supplierProfile()->create([
            'business_name' => 'Pending Nursery',
            'status' => 'pending_approval',
        ]);

        $pendingSupplier->products()->create([
            'name_ar' => 'شجرة جوافة',
            'unit_price' => 120,
            'minimum_order_quantity' => 2,
            'status' => 'published',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_ar', 'شجرة مانجو')
            ->assertJsonPath('data.0.supplier.business_name', 'Approved Nursery');
    }

    public function test_visitor_can_search_published_products(): void
    {
        $user = User::factory()->create();

        $supplier = $user->supplierProfile()->create([
            'business_name' => 'Search Nursery',
            'status' => 'approved',
        ]);

        $supplier->products()->create([
            'name_ar' => 'شجرة مانجو',
            'unit_price' => 150,
            'minimum_order_quantity' => 5,
            'status' => 'published',
        ]);

        $supplier->products()->create([
            'name_ar' => 'شجرة ليمون',
            'unit_price' => 100,
            'minimum_order_quantity' => 3,
            'status' => 'published',
        ]);

        $this->getJson('/api/v1/products?search=مانجو')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_ar', 'شجرة مانجو');
    }
}
