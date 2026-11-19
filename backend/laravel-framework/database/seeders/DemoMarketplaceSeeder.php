<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo-supplier@gozorx.test'],
            [
                'name' => 'GOZORX Demo Supplier',
                'full_name' => 'مورد تجريبي',
                'phone' => '01000000001',
                'password' => Hash::make('Demo@123456'),
                'role' => 'buyer',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'is_active' => true,
            ]
        );

        $supplier = SupplierProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'مشتل جرين بلازا التجريبي',
                'business_type' => 'nursery',
                'description' => 'مورد تجريبي لاختبار منصة GOZORX',
                'status' => 'approved',
                'reviewed_at' => now(),
            ]
        );

        $products = [
            [
                'slug' => 'deciduous-fruit',
                'name_ar' => 'شتلة تين مثمرة',
                'name_en' => 'Fruit Bearing Fig Tree',
                'description' => 'شتلة تين مناسبة للحدائق والأراضي الزراعية.',
                'unit_price' => 250,
                'minimum_order_quantity' => 5,
                'available_quantity' => 100,
            ],
            [
                'slug' => 'tropical-fruit',
                'name_ar' => 'شتلة مانجو مطعومة',
                'name_en' => 'Grafted Mango Tree',
                'description' => 'شتلة مانجو مطعومة للاستخدام الزراعي والحدائق.',
                'unit_price' => 350,
                'minimum_order_quantity' => 5,
                'available_quantity' => 80,
            ],
            [
                'slug' => 'shade-trees',
                'name_ar' => 'شتلة كاسيا نودوزا',
                'name_en' => 'Cassia Nodosa Tree',
                'description' => 'شجرة زينة مناسبة للحدائق والمساحات المفتوحة.',
                'unit_price' => 300,
                'minimum_order_quantity' => 3,
                'available_quantity' => 60,
            ],
            [
                'slug' => 'olive-trees',
                'name_ar' => 'شتلة زيتون',
                'name_en' => 'Olive Tree Seedling',
                'description' => 'شتلة زيتون مناسبة للزراعة في الأراضي والحدائق.',
                'unit_price' => 200,
                'minimum_order_quantity' => 10,
                'available_quantity' => 150,
            ],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', $item['slug'])
                ->where('is_active', true)
                ->firstOrFail();

            Product::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'name_en' => $item['name_en'],
                ],
                [
                    'category_id' => $category->id,
                    'name_ar' => $item['name_ar'],
                    'description' => $item['description'],
                    'unit_name' => 'شتلة',
                    'unit_price' => $item['unit_price'],
                    'minimum_order_quantity' => $item['minimum_order_quantity'],
                    'available_quantity' => $item['available_quantity'],
                    'status' => 'published',
                    'reviewed_at' => now(),
                ]
            );
        }
    }
}
