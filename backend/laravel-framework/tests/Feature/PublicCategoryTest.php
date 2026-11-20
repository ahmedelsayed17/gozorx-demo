<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_view_active_categories_with_children(): void
    {
        $parent = Category::create([
            'name_ar' => 'أشجار الفاكهة',
            'name_en' => 'Fruit Trees',
            'slug' => 'fruit-trees',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::create([
            'parent_id' => $parent->id,
            'name_ar' => 'أشجار الموالح',
            'name_en' => 'Citrus Trees',
            'slug' => 'citrus-trees',
            'is_active' => true,
        ]);

        Category::create([
            'name_ar' => 'قسم غير نشط',
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_ar', 'أشجار الفاكهة')
            ->assertJsonPath('data.0.children.0.name_ar', 'أشجار الموالح');
    }
}
