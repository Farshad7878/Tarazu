<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class OrderNumberRetryTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    public function test_order_is_retried_after_one_order_number_collision_without_double_stock_use(): void
    {
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['stock_quantity' => 10, 'price' => 1000]);

        // یک سفارش موجود تا شماره‌اش را برای تلاش اول تکراری کنیم
        $existing = $this->makeCustomer();
        $existingOrder = Order::create([
            'customer_id' => $existing->id, 'reception_id' => $reception->id,
            'subtotal' => 0, 'discount_amount' => 0, 'total_amount' => 0,
        ]);

        $collisions = 0;
        Order::creating(function (Order $order) use (&$collisions, $existingOrder) {
            if ($collisions === 0) {
                $order->order_number = $existingOrder->order_number;
                $collisions++;
            }
        });

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'apply_discounts' => false,
            ])
            ->assertStatus(201);

        $this->assertSame(1, $collisions);
        $this->assertSame(8, (int) $product->fresh()->stock_quantity);
        $this->assertEquals(2000, (float) $customer->fresh()->total_purchases);
    }
}
