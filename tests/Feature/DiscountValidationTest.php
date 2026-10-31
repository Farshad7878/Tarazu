<?php

namespace Tests\Feature;

use App\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class DiscountValidationTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    private function admin()
    {
        return $this->makeUser('admin');
    }

    public function test_percentage_without_value_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'name' => 'No value', 'type' => 'percentage', 'target' => 'all',
                'condition_type' => 'manual', 'message' => 'm',
            ])
            ->assertStatus(422);
    }

    public function test_percentage_above_100_is_rejected_on_value_field(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'name' => 'Too much', 'type' => 'percentage', 'value' => 150, 'target' => 'all',
                'condition_type' => 'manual', 'message' => 'm',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_fixed_amount_above_100_is_allowed(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'name' => 'Big fixed', 'type' => 'fixed_amount', 'value' => 500000, 'target' => 'all',
                'condition_type' => 'manual', 'message' => 'm',
            ])
            ->assertStatus(201);

        $this->assertSame(500000.0, (float) Discount::where('name', 'Big fixed')->value('value'));
    }

    public function test_irrelevant_fields_are_cleared(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'name' => 'Clean', 'type' => 'percentage', 'value' => 15, 'free_product_id' => $product->id,
                'target' => 'all', 'condition_type' => 'manual', 'condition_value' => 99, 'message' => 'm',
            ])
            ->assertStatus(201);

        $discount = Discount::where('name', 'Clean')->firstOrFail();
        $this->assertNull($discount->free_product_id);
        $this->assertNull($discount->condition_value);
    }

    public function test_free_product_requires_a_product(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/discounts', [
                'name' => 'Gift', 'type' => 'free_product', 'target' => 'all',
                'condition_type' => 'manual', 'message' => 'm',
            ])
            ->assertStatus(422);
    }
}
