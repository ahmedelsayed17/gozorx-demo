<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierOrder;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(string $status = 'approved'): array
    {
        $user = User::factory()->create(['role' => 'supplier']);

        $supplier = $user->supplierProfile()->create([
            'business_name' => 'Test Nursery',
            'status' => $status,
        ]);

        return [$user, $supplier];
    }

    private function createOrder(User $buyer, ?int $supplierId, string $number): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'supplier_id' => $supplierId,
            'order_number' => $number,
            'status' => 'pending_shipping_cost',
            'payment_method' => 'instapay',
            'payment_status' => 'pending',
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '01000000000',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Test Address',
            'delivery_notes' => 'Private delivery note',
            'subtotal' => 1000,
            'shipping_total' => 100,
            'grand_total' => 1100,
            'deposit_amount' => 330,
            'balance_amount' => 770,
        ]);
    }

    private function createIndividualOrder(
        User $buyer,
        SupplierProfile $supplier,
        string $number
    ): Order {
        $order = $this->createOrder($buyer, $supplier->id, $number);

        $order->individualItem()->create([
            'product_id' => null,
            'product_name_ar' => 'شتلة مانجو',
            'product_name_en' => 'Mango Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        return $order;
    }

    private function createLegacySupplierOrder(
        User $buyer,
        SupplierProfile $supplier,
        string $number
    ): SupplierOrder {
        $parentOrder = $this->createOrder($buyer, null, $number);

        $supplierOrder = SupplierOrder::create([
            'order_id' => $parentOrder->id,
            'supplier_id' => $supplier->id,
            'status' => 'pending_shipping_cost',
            'subtotal' => 1000,
            'shipping_total' => 100,
            'grand_total' => 1100,
            'deposit_amount' => 330,
            'balance_amount' => 770,
        ]);

        OrderItem::create([
            'supplier_order_id' => $supplierOrder->id,
            'product_id' => null,
            'product_name_ar' => 'شتلة زيتون',
            'product_name_en' => 'Olive Seedling',
            'unit_name' => 'شتلة',
            'unit_price' => 100,
            'quantity' => 10,
            'line_total' => 1000,
        ]);

        return $supplierOrder;
    }

    private function assertSupplierPayloadProtectsBuyerData(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ([
            '"recipient_phone"',
            '"address"',
            '"delivery_notes"',
            '01000000000',
            'Test Address',
            'Private delivery note',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $json);
        }
    }

    public function test_supplier_sees_only_own_individual_and_legacy_orders(): void
    {
        [$userA, $supplierA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $buyer = User::factory()->create();

        $ownIndividual = $this->createIndividualOrder(
            $buyer,
            $supplierA,
            'OWN-INDIVIDUAL-001'
        );

        $this->createIndividualOrder(
            $buyer,
            $supplierB,
            'OTHER-INDIVIDUAL-001'
        );

        $ownLegacy = $this->createLegacySupplierOrder(
            $buyer,
            $supplierA,
            'OWN-LEGACY-001'
        );

        $this->createLegacySupplierOrder(
            $buyer,
            $supplierB,
            'OTHER-LEGACY-001'
        );

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/supplier/orders');

        $response->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonFragment(['order_number' => 'OWN-INDIVIDUAL-001'])
            ->assertJsonFragment(['order_number' => 'OWN-LEGACY-001'])
            ->assertJsonFragment(['recipient_name' => 'Test'])
            ->assertJsonFragment(['governorate' => 'Cairo'])
            ->assertJsonFragment(['city' => 'Nasr City'])
            ->assertJsonMissing(['order_number' => 'OTHER-INDIVIDUAL-001'])
            ->assertJsonMissing(['order_number' => 'OTHER-LEGACY-001']);

        $this->assertSupplierPayloadProtectsBuyerData($response->json());
    }

    public function test_supplier_can_view_own_individual_order_details(): void
    {
        [$user, $supplier] = $this->createSupplier();

        $buyer = User::factory()->create();

        $order = $this->createIndividualOrder(
            $buyer,
            $supplier,
            'DETAIL-INDIVIDUAL-001'
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/supplier/orders/individual/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('order.order_number', 'DETAIL-INDIVIDUAL-001')
            ->assertJsonPath('order.recipient_name', 'Test')
            ->assertJsonPath('order.governorate', 'Cairo')
            ->assertJsonPath('order.city', 'Nasr City')
            ->assertJsonPath('order.item.product_name_ar', 'شتلة مانجو');

        $this->assertSupplierPayloadProtectsBuyerData($response->json());
    }

    public function test_supplier_cannot_view_another_suppliers_individual_order(): void
    {
        [$userA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $buyer = User::factory()->create();

        $foreignOrder = $this->createIndividualOrder(
            $buyer,
            $supplierB,
            'FOREIGN-INDIVIDUAL-001'
        );

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/supplier/orders/individual/{$foreignOrder->id}")
            ->assertNotFound();
    }

    public function test_supplier_can_view_own_legacy_order_details(): void
    {
        [$user, $supplier] = $this->createSupplier();

        $buyer = User::factory()->create();

        $supplierOrder = $this->createLegacySupplierOrder(
            $buyer,
            $supplier,
            'DETAIL-LEGACY-001'
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/supplier/orders/multi_supplier/{$supplierOrder->id}");

        $response->assertOk()
            ->assertJsonPath('order.order_number', 'DETAIL-LEGACY-001')
            ->assertJsonPath('order.recipient_name', 'Test')
            ->assertJsonPath('order.governorate', 'Cairo')
            ->assertJsonPath('order.city', 'Nasr City')
            ->assertJsonPath('order.items.0.product_name_ar', 'شتلة زيتون');

        $this->assertSupplierPayloadProtectsBuyerData($response->json());
    }

    public function test_supplier_cannot_view_another_suppliers_legacy_order(): void
    {
        [$userA] = $this->createSupplier();
        [, $supplierB] = $this->createSupplier();

        $buyer = User::factory()->create();

        $foreignSupplierOrder = $this->createLegacySupplierOrder(
            $buyer,
            $supplierB,
            'FOREIGN-LEGACY-001'
        );

        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/supplier/orders/multi_supplier/{$foreignSupplierOrder->id}")
            ->assertNotFound();
    }

    public function test_buyer_without_supplier_profile_cannot_access_supplier_orders(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/v1/supplier/orders')
            ->assertForbidden();
    }

    public function test_unapproved_supplier_cannot_access_supplier_orders(): void
    {
        [$user] = $this->createSupplier('pending_approval');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/supplier/orders')
            ->assertForbidden();
    }
}
