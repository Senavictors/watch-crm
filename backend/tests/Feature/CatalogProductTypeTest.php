<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Quality;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_box_model_without_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/models', [
                'name' => 'Caixa Rolex',
                'brandId' => $brand->id,
                'productType' => WatchModel::TYPE_BOX,
            ])
            ->assertCreated()
            ->assertJsonPath('productType', WatchModel::TYPE_BOX)
            ->assertJsonPath('qualityId', null);
    }

    public function test_watch_model_requires_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/models', [
                'name' => 'Submariner',
                'brandId' => $brand->id,
                'productType' => WatchModel::TYPE_WATCH,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('qualityId');
    }

    public function test_product_payload_exposes_box_type_without_quality(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $boxModel = WatchModel::factory()->box()->create([
            'brand_id' => $brand->id,
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
                'productType' => WatchModel::TYPE_BOX,
                'modelQualityName' => null,
            ]);
    }

    public function test_box_model_name_must_be_unique_per_brand_and_type(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        WatchModel::factory()->box()->create([
            'brand_id' => $brand->id,
            'name' => 'Caixa Oyster',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/models', [
                'name' => 'Caixa Oyster',
                'brandId' => $brand->id,
                'productType' => WatchModel::TYPE_BOX,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_watch_models_can_repeat_name_with_different_qualities(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $qualityA = Quality::factory()->create();
        $qualityB = Quality::factory()->create();

        WatchModel::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'GMT Master',
            'quality_id' => $qualityA->id,
            'quality_key' => $qualityA->id,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/models', [
                'name' => 'GMT Master',
                'brandId' => $brand->id,
                'productType' => WatchModel::TYPE_WATCH,
                'qualityId' => $qualityB->id,
            ])
            ->assertCreated()
            ->assertJsonPath('qualityId', $qualityB->id);
    }
}
