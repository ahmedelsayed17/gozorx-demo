<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GozorxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'phone' => '01000000001',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'buyer@example.com',
            'role' => 'buyer',
        ]);
    }

    public function test_buyer_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password12345',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'Password12345',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_buyer_can_apply_as_supplier(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/supplier/apply', [
                'business_name' => 'Test Nursery',
                'business_type' => 'Nursery',
            ])
            ->assertCreated()
            ->assertJsonPath('supplier.status', 'pending_approval');

        $this->assertDatabaseHas('supplier_profiles', [
            'user_id' => $buyer->id,
            'status' => 'pending_approval',
        ]);
    }

    public function test_buyer_cannot_access_admin_suppliers(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/admin/suppliers')
            ->assertForbidden();
    }

    public function test_pending_supplier_cannot_add_product(): void
    {
        $user = User::factory()->create();

        $user->supplierProfile()->create([
            'business_name' => 'Pending Nursery',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/supplier/products', [
                'name_ar' => 'شجرة مانجو',
                'unit_name' => 'شتلة',
                'unit_price' => 150,
                'minimum_order_quantity' => 5,
            ])
            ->assertForbidden();
    }

    public function test_approved_supplier_can_add_product(): void
    {
        $user = User::factory()->create();

        $user->supplierProfile()->create([
            'business_name' => 'Approved Nursery',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/supplier/products', [
                'name_ar' => 'شجرة مانجو',
                'unit_name' => 'شتلة',
                'unit_price' => 150,
                'minimum_order_quantity' => 5,
                'available_quantity' => 100,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'name_ar' => 'شجرة مانجو',
            'unit_price' => 150,
            'available_quantity' => 100,
            'status' => 'draft',
        ]);
    }

    public function test_approved_supplier_can_add_product_with_zero_stock(): void
    {
        $user = User::factory()->create();

        $user->supplierProfile()->create([
            'business_name' => 'Zero Stock Nursery',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/supplier/products', [
                'name_ar' => 'شتلة غير متاحة حاليًا',
                'unit_name' => 'شتلة',
                'unit_price' => 150,
                'minimum_order_quantity' => 1,
                'available_quantity' => 0,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'name_ar' => 'شتلة غير متاحة حاليًا',
            'available_quantity' => 0,
        ]);
    }

    public function test_supplier_must_provide_stock_quantity_when_adding_product(): void
    {
        $user = User::factory()->create();

        $user->supplierProfile()->create([
            'business_name' => 'Missing Stock Nursery',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/supplier/products', [
                'name_ar' => 'شتلة بدون كمية',
                'unit_name' => 'شتلة',
                'unit_price' => 150,
                'minimum_order_quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['available_quantity']);
    }

    public function test_admin_can_approve_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create();

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'New Nursery',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/suppliers/{$supplier->id}/decision", [
                'decision' => 'approved',
                'review_note' => 'Approved for testing',
            ])
            ->assertOk();

        $this->assertDatabaseHas('supplier_profiles', [
            'id' => $supplier->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'supplier_profile',
            'entity_id' => $supplier->id,
            'action' => 'supplier.approved',
        ]);
    }
}
