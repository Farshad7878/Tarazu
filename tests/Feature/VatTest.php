<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class VatTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    public function test_tax_is_extracted_from_inclusive_price_without_changing_the_total(): void
    {
        config(['tax.vat_rate' => 9]);

        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 109000, 'stock_quantity' => 10]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'apply_discounts' => false,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $order = Order::findOrFail($response->json('order.id'));

        // ۲ × ۱۰۹٬۰۰۰ = ۲۱۸٬۰۰۰ شامل ۹٪ مالیات؛ مالیات ۱۸٬۰۰۰ و مبلغ پرداختی بدون تغییر
        $this->assertEquals(218000, (float) $order->total_amount);
        $this->assertEquals(18000, (float) $order->tax_amount);
        $this->assertEquals(9, (float) $order->tax_rate);
        $this->assertEquals(18000, (float) $response->json('order.tax_amount'));
        $this->assertEquals(218000, (float) $response->json('order.payable_amount'));
    }

    public function test_tax_is_calculated_on_the_amount_left_after_loyalty_redemption(): void
    {
        config(['tax.vat_rate' => 9]);

        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $customer->forceFill(['loyalty_points' => 500])->save();
        $product = $this->makeProduct(['price' => 109000, 'stock_quantity' => 10]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'apply_discounts' => false,
                'redeem_points' => 100,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        // ۲۱۸٬۰۰۰ − ۱۰۰٬۰۰۰ (۱۰۰ امتیاز) = ۱۱۸٬۰۰۰؛ مالیات این مبلغ ۹٬۷۴۳٫۱۲
        $order = Order::findOrFail($response->json('order.id'));
        $this->assertEquals(118000, (float) $order->total_amount);
        $this->assertEquals(9743.12, (float) $order->tax_amount);
    }

    public function test_partial_and_full_returns_refund_tax_that_adds_up_to_the_original_tax(): void
    {
        config(['tax.vat_rate' => 9]);

        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 109000, 'stock_quantity' => 10]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'apply_discounts' => false,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $order = Order::with('items')->findOrFail($response->json('order.id'));
        $itemId = $order->items->first()->id;

        $partial = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
                'reason' => 'بازگشت جزئی',
                'restock' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals(9000, (float) $partial->json('return.tax_refund'));
        $order->refresh();
        $this->assertEquals(109000, (float) $order->total_amount);
        $this->assertEquals(9000, (float) $order->tax_amount);

        $full = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
                'reason' => 'بازگشت کامل',
                'restock' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals(9000, (float) $full->json('return.tax_refund'));
        $order->refresh();
        $this->assertEquals(0, (float) $order->total_amount);
        $this->assertEquals(0, (float) $order->tax_amount);
        $this->assertEquals(18000, (float) OrderReturn::where('order_id', $order->id)->sum('tax_refund'));
    }

    public function test_zero_rate_keeps_the_tax_fields_at_zero(): void
    {
        config(['tax.vat_rate' => 0]);

        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 109000, 'stock_quantity' => 10]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'apply_discounts' => false,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $order = Order::findOrFail($response->json('order.id'));
        $this->assertEquals(109000, (float) $order->total_amount);
        $this->assertEquals(0, (float) $order->tax_amount);
        $this->assertEquals(0, (float) $order->tax_rate);
    }

    public function test_changing_the_rate_later_does_not_change_an_existing_orders_tax(): void
    {
        config(['tax.vat_rate' => 0]);

        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 109000, 'stock_quantity' => 10]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'apply_discounts' => false,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        // نرخ بعد از فروش تغییر می‌کند؛ سفارش قبلی باید بدون مالیات بماند
        config(['tax.vat_rate' => 9]);

        $order = Order::with('items')->findOrFail($response->json('order.id'));
        $return = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
                'reason' => 'تغییر نرخ',
                'restock' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals(0, (float) $return->json('return.tax_refund'));
        $this->assertEquals(0, (float) $order->fresh()->tax_rate);
    }

    public function test_invalid_rates_are_treated_as_disabled(): void
    {
        $tax = app(TaxService::class);

        config(['tax.vat_rate' => 100]);
        $this->assertSame(0.0, $tax->rate());

        config(['tax.vat_rate' => -5]);
        $this->assertSame(0.0, $tax->rate());

        config(['tax.vat_rate' => 9]);
        $this->assertSame(9.0, $tax->rate());
        $this->assertSame(8.26, $tax->includedIn(100, 9.0));
        $this->assertSame(0.0, $tax->includedIn(-50, 9.0));
    }

    public function test_product_list_exposes_the_vat_rate_for_the_frontend(): void
    {
        config(['tax.vat_rate' => 9]);

        $reception = $this->makeUser('reception');

        $this->actingAs($reception, 'sanctum')
            ->getJson('/api/reception/products')
            ->assertOk()
            ->assertJsonPath('vat_rate', 9);
    }
}
