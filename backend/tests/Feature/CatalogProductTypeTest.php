<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Quality;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductTypeTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public function test_can_create_model_in_category_without_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $boxes = Category::factory()->withoutQuality()->create(['name' => 'Caixas Teste']);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/models', [
                'name' => 'Caixa Rolex',
                'brandId' => $brand->id,
                'categoryId' => $boxes->id,
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('categoryId', $boxes->id)
            ->assertJsonPath('qualityId', null);
    }

    public function test_model_in_category_with_quality_requires_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $watches = Category::factory()->create(['name' => 'Relógios Teste', 'has_quality' => true]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/models', [
                'name' => 'Submariner',
                'brandId' => $brand->id,
                'categoryId' => $watches->id,
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('qualityId');
    }

    public function test_product_payload_exposes_category_without_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $boxes = Category::factory()->withoutQuality()->create(['name' => 'Caixas Teste']);
        $boxModel = WatchModel::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $boxes->id,
            'quality_id' => null,
            'name' => 'Caixa Daytona',
        ]);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'model_id' => $boxModel->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $product->id,
                'categoryName' => 'Caixas Teste',
                'categoryHasQuality' => false,
                'modelQualityName' => null,
            ]);
    }

    public function test_model_name_must_be_unique_per_brand_and_category(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $boxes = Category::factory()->withoutQuality()->create(['name' => 'Caixas Teste']);
        WatchModel::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $boxes->id,
            'quality_id' => null,
            'name' => 'Caixa Oyster',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/models', [
                'name' => 'Caixa Oyster',
                'brandId' => $brand->id,
                'categoryId' => $boxes->id,
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_models_in_quality_category_can_repeat_name_with_different_qualities(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $watches = Category::factory()->create(['name' => 'Relógios Teste', 'has_quality' => true]);
        $qualityA = Quality::factory()->create();
        $qualityB = Quality::factory()->create();

        WatchModel::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $watches->id,
            'name' => 'GMT Master',
            'quality_id' => $qualityA->id,
            'quality_key' => $qualityA->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/models', [
                'name' => 'GMT Master',
                'brandId' => $brand->id,
                'categoryId' => $watches->id,
                'qualityId' => $qualityB->id,
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('qualityId', $qualityB->id);
    }
}
