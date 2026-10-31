<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class ProductDeleteGuardTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    public function test_unused_product_can_be_deleted(): void
    {
        $admin = $this->makeUser('admin');
        $product = $this->makeProduct(['created_by' => $admin->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk();
    }

    public function test_product_used_as_gift_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin');
        $product = $this->makeProduct(['created_by' => $admin->id]);

        Discount::create([
            'name' => 'Gift lock', 'type' => 'free_product', 'free_product_id' => $product->id,
            'target' => 'all', 'condition_type' => 'manual', 'message' => 'm', 'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertStatus(422);

        $this->assertTrue(Product::whereKey($product->id)->exists());
    }
}
