<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'fruit-trees',
                'name_ar' => 'الأشجار المثمرة والشتلات المطعومة',
                'name_en' => 'Fruit Trees & Grafted Seedlings',
                'children' => [
                    ['slug' => 'deciduous-fruit', 'name_ar' => 'أشجار الفاكهة المتساقطة', 'name_en' => 'Deciduous Fruit Trees'],
                    ['slug' => 'tropical-fruit', 'name_ar' => 'أشجار الفاكهة الاستوائية', 'name_en' => 'Tropical Fruit Trees'],
                    ['slug' => 'fruit-vines', 'name_ar' => 'المتسلقات المثمرة', 'name_en' => 'Fruit Vines'],
                ],
            ],
            [
                'slug' => 'ornamental-landscape',
                'name_ar' => 'أشجار الزينة وتنسيق الحدائق',
                'name_en' => 'Ornamental Trees & Landscaping',
                'children' => [
                    ['slug' => 'shade-trees', 'name_ar' => 'أشجار الظل', 'name_en' => 'Shade Trees'],
                    ['slug' => 'flowering-trees', 'name_ar' => 'الأشجار المزهرة', 'name_en' => 'Flowering Trees'],
                    ['slug' => 'palms', 'name_ar' => 'النخيل', 'name_en' => 'Palm Trees'],
                    ['slug' => 'ornamental-shrubs', 'name_ar' => 'الشجيرات والأسوار النباتية', 'name_en' => 'Ornamental Shrubs & Hedges'],
                    ['slug' => 'climbing-plants', 'name_ar' => 'النباتات المتسلقة', 'name_en' => 'Climbing Plants'],
                ],
            ],
            [
                'slug' => 'forest-timber',
                'name_ar' => 'أشجار الغابات والأخشاب',
                'name_en' => 'Forest & Timber Trees',
                'children' => [
                    ['slug' => 'timber-trees', 'name_ar' => 'أشجار إنتاج الأخشاب', 'name_en' => 'Timber Trees'],
                    ['slug' => 'fast-growing-trees', 'name_ar' => 'الأشجار سريعة النمو', 'name_en' => 'Fast-Growing Trees'],
                    ['slug' => 'windbreak-trees', 'name_ar' => 'أشجار مصدات الرياح', 'name_en' => 'Windbreak Trees'],
                ],
            ],
            [
                'slug' => 'olives-citrus-specialty',
                 'name_ar' => 'أشجار الزيتون والموالح والمحاصيل المتخصصة',
                'name_en' => 'Olives, Citrus & Specialty Crops',
                'children' => [
                    ['slug' => 'olive-trees', 'name_ar' => 'أشجار الزيتون', 'name_en' => 'Olive Trees'],
                    ['slug' => 'citrus-trees', 'name_ar' => 'أشجار الموالح', 'name_en' => 'Citrus Trees'],
                    ['slug' => 'nut-trees', 'name_ar' => 'أشجار المكسرات', 'name_en' => 'Nut Trees'],
                    ['slug' => 'medicinal-aromatic', 'name_ar' => 'النباتات الطبية والعطرية', 'name_en' => 'Medicinal & Aromatic Plants'],
                ],
            ],
        ];

        foreach ($categories as $item) {
            $children = $item['children'];
            unset($item['children']);

            $parent = Category::updateOrCreate(
                ['slug' => $item['slug']],
                $item + ['is_active' => true]
            );

            foreach ($children as $child) {
                Category::updateOrCreate(
                    ['slug' => $child['slug']],
                    $child + [
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
