<?php

namespace Tests\Feature;

use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GozorxApiTest extends TestCase
{
    use RefreshDatabase;

    private function supplierDocumentUploads(): array
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII='
        );

        return [
            'commercial_register' => UploadedFile::fake()->create(
                'commercial_register.pdf',
                100,
                'application/pdf'
            ),
            'tax_card' => UploadedFile::fake()->create(
                'tax_card.pdf',
                100,
                'application/pdf'
            ),
            'personal_photo' => UploadedFile::fake()->createWithContent(
                'personal_photo.png',
                $png
            ),
            'national_id' => UploadedFile::fake()->createWithContent(
                'national_id.png',
                $png
            ),
        ];
    }

    private function createApprovedSupplierDocuments($supplier, User $admin): void
    {
        foreach (SupplierDocument::TYPES as $type) {
            $supplier->documents()->create([
                'document_type' => $type,
                'file_path' => "supplier-documents/{$supplier->id}/{$type}.pdf",
                'original_filename' => "{$type}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => 100,
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);
        }
    }

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

    public function test_buyer_cannot_apply_as_supplier_on_the_same_account(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer, 'sanctum')
            ->post('/api/v1/supplier/apply', array_merge([
                'business_name' => 'Test Nursery',
                'business_type' => 'Nursery',
                'address' => 'Cairo, Egypt',
                'latitude' => 30.0444,
                'longitude' => 31.2357,
            ], $this->supplierDocumentUploads()));

        $response->assertForbidden();

        $this->assertDatabaseMissing('supplier_profiles', [
            'user_id' => $buyer->id,
        ]);

        $this->assertDatabaseCount('supplier_documents', 0);
    }

    public function test_supplier_can_register_with_a_separate_supplier_account(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/v1/supplier/register', array_merge([
            'full_name' => 'Test Supplier',
            'email' => 'supplier@example.com',
            'phone' => '01000000002',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
            'business_name' => 'Test Nursery',
            'business_type' => 'Nursery',
            'address' => 'Cairo, Egypt',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ], $this->supplierDocumentUploads()));

        $response->assertCreated()
            ->assertJsonPath('user.role', 'supplier')
            ->assertJsonPath('supplier.status', 'pending_approval')
            ->assertJsonCount(4, 'supplier.documents');

        $this->assertDatabaseHas('users', [
            'email' => 'supplier@example.com',
            'role' => 'supplier',
        ]);

        $this->assertDatabaseHas('supplier_profiles', [
            'business_name' => 'Test Nursery',
            'status' => 'pending_approval',
            'address' => 'Cairo, Egypt',
        ]);

        $this->assertDatabaseCount('supplier_documents', 4);
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
        $user = User::factory()->create(['role' => 'supplier']);

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
        $user = User::factory()->create(['role' => 'supplier']);

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
        $user = User::factory()->create(['role' => 'supplier']);

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
        $user = User::factory()->create(['role' => 'supplier']);

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
        $supplierUser = User::factory()->create(['role' => 'supplier']);

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'New Nursery',
            'status' => 'pending_approval',
        ]);

        $this->createApprovedSupplierDocuments($supplier, $admin);

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

    public function test_approving_all_supplier_documents_automatically_approves_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create(['role' => 'supplier']);

        $supplier = $supplierUser->supplierProfile()->create([
            'business_name' => 'Automatic Approval Nursery',
            'status' => 'pending_approval',
        ]);

        foreach (SupplierDocument::TYPES as $type) {
            $document = $supplier->documents()->create([
                'document_type' => $type,
                'file_path' => "supplier-documents/{$supplier->id}/{$type}.pdf",
                'original_filename' => "{$type}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => 100,
                'status' => 'pending_review',
                'submitted_at' => now(),
            ]);

            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/v1/admin/supplier-documents/{$document->id}/decision", [
                    'decision' => 'approved',
                ])
                ->assertOk();
        }

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
