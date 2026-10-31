<?php

namespace Tests\Feature;

use App\Models\HeldCart;
use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\OrderReturn;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class HeldCartAndExchangeTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    /** سبد معلق: ذخیره، فهرست، بازیابی و محدودیت‌ها */

    public function test_held_cart_is_saved_listed_and_does_not_reserve_stock(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/held-carts', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => 4]],
                'notes' => 'منتظر تأیید',
            ])
            ->assertStatus(201);

        $this->assertSame(10, (int) $product->fresh()->stock_quantity, 'holding must not reserve stock');

        $this->actingAs($reception, 'sanctum')
            ->getJson('/api/reception/held-carts')
            ->assertOk()
            ->assertJsonPath('held_carts.0.items_count', 4)
            ->assertJsonPath('held_carts.0.customer.membership_number', $customer->membership_number)
            ->assertJsonPath('held_carts.0.notes', 'منتظر تأیید');
    }

    public function test_duplicate_lines_are_merged_when_holding(): void
    {
        $reception = $this->makeUser('reception');
        $product = $this->makeProduct(['stock_quantity' => 10]);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/held-carts', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertStatus(201);

        $cart = HeldCart::firstOrFail();
        $this->assertCount(1, $cart->items);
        $this->assertSame(5, (int) $cart->items[0]['quantity']);
    }

    public function test_inactive_product_cannot_be_held(): void
    {
        $reception = $this->makeUser('reception');
        $product = $this->makeProduct(['is_active' => false, 'stock_quantity' => 10]);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/held-carts', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, HeldCart::count());
    }

    public function test_at_most_twenty_held_carts_per_reception(): void
    {
        $reception = $this->makeUser('reception');
        $product = $this->makeProduct(['stock_quantity' => 10]);

        for ($i = 0; $i < 20; $i++) {
            HeldCart::create([
                'user_id' => $reception->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        }

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/held-carts', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);

        $this->assertSame(20, HeldCart::count());
    }

    public function test_resume_caps_quantity_at_branch_stock_and_deletes_the_cart(): void
    {
        $reception = $this->makeUser('reception');
        $product = $this->makeProduct(['stock_quantity' => 3]);

        $cart = HeldCart::create([
            'user_id' => $reception->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/held-carts/{$cart->id}/resume")
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 3);

        $this->assertCount(1, $response->json('adjusted'));
        $this->assertSame([], $response->json('skipped'));
        $this->assertNull(HeldCart::find($cart->id), 'a resumed cart is removed');

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/held-carts/{$cart->id}/resume")
            ->assertNotFound();
    }

    public function test_resume_skips_products_that_are_now_inactive_or_out_of_stock(): void
    {
        $reception = $this->makeUser('reception');
        $inactive = $this->makeProduct(['stock_quantity' => 5]);
        $empty = $this->makeProduct(['stock_quantity' => 0]);
        $ok = $this->makeProduct(['stock_quantity' => 5]);

        $cart = HeldCart::create([
            'user_id' => $reception->id,
            'items' => [
                ['product_id' => $inactive->id, 'quantity' => 1],
                ['product_id' => $empty->id, 'quantity' => 1],
                ['product_id' => $ok->id, 'quantity' => 2],
            ],
        ]);
        $inactive->update(['is_active' => false]);

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/held-carts/{$cart->id}/resume")
            ->assertOk();

        $this->assertCount(2, $response->json('skipped'));
        $this->assertCount(1, $response->json('items'));
        $this->assertSame($ok->id, $response->json('items.0.product_id'));
    }

    public function test_another_reception_cannot_see_or_resume_a_held_cart(): void
    {
        $owner = $this->makeUser('reception');
        $other = $this->makeUser('reception');
        $product = $this->makeProduct(['stock_quantity' => 5]);

        $cart = HeldCart::create([
            'user_id' => $owner->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/reception/held-carts')
            ->assertOk()
            ->assertJsonCount(0, 'held_carts');

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/reception/held-carts/{$cart->id}/resume")
            ->assertNotFound();

        $this->assertNotNull(HeldCart::find($cart->id));
    }

    /** تعویض کالا: بازگشت + سفارش جدید در یک تراکنش */

    private function placeOrder($reception, $customer, Product $product, int $quantity): Order
    {
        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', [
                'customer_membership_number' => $customer->membership_number,
                'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
                'apply_discounts' => false,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        return Order::latest('id')->firstOrFail();
    }

    public function test_exchange_returns_old_item_sells_new_one_and_records_the_difference(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);
        $order = $this->placeOrder($reception, $customer, $product, 2);
        $item = $order->items()->firstOrFail();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                'new_items' => [['product_id' => $product->id, 'quantity' => 2]],
                'reason' => 'سایز نامناسب',
            ])
            ->assertStatus(201)
            ->assertJsonPath('return.refund_amount', 10000)
            ->assertJsonPath('new_order.total_amount', 20000)
            ->assertJsonPath('difference', 10000);

        // موجودی: ۱۰ - ۲ (فروش) + ۱ (بازگشت) - ۲ (فروش جدید) = ۷
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);

        $exchange = OrderExchange::firstOrFail();
        $this->assertSame($order->id, $exchange->original_order_id);
        $this->assertEqualsWithDelta(10000.0, (float) $exchange->difference, 0.01);
        $this->assertSame(1, OrderReturn::count());
        $this->assertSame(2, Order::count());
    }

    public function test_exchange_with_a_cheaper_replacement_gives_a_negative_difference(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $expensive = $this->makeProduct(['price' => 20000, 'stock_quantity' => 5]);
        $cheap = $this->makeProduct(['price' => 5000, 'stock_quantity' => 5]);
        $order = $this->placeOrder($reception, $customer, $expensive, 1);
        $item = $order->items()->firstOrFail();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                'new_items' => [['product_id' => $cheap->id, 'quantity' => 1]],
                'reason' => 'تعویض با کالای ارزان‌تر',
            ])
            ->assertStatus(201)
            ->assertJsonPath('difference', -15000);

        $this->assertEqualsWithDelta(-15000.0, (float) OrderExchange::firstOrFail()->difference, 0.01);
    }

    public function test_exchange_is_rolled_back_when_the_new_items_are_out_of_stock(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 5]);
        $order = $this->placeOrder($reception, $customer, $product, 2);
        $item = $order->items()->firstOrFail();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                'new_items' => [['product_id' => $product->id, 'quantity' => 50]],
                'reason' => 'تست موجودی',
            ])
            ->assertStatus(422);

        // هیچ‌کدام از مراحل نباید ماندگار شود: بازگشت، سفارش جدید یا ردیف تعویض
        $this->assertSame(0, OrderReturn::count());
        $this->assertSame(0, OrderExchange::count());
        $this->assertSame(1, Order::count());
        $this->assertSame(0, (int) $item->fresh()->returned_quantity);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity, 'stock must match the original sale only');
    }

    public function test_exchange_cannot_return_more_than_was_bought(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);
        $order = $this->placeOrder($reception, $customer, $product, 2);
        $item = $order->items()->firstOrFail();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 3]],
                'new_items' => [['product_id' => $product->id, 'quantity' => 1]],
                'reason' => 'بیش از خرید',
            ])
            ->assertStatus(422);

        $this->assertSame(0, OrderExchange::count());
        $this->assertSame(1, Order::count());
    }

    public function test_exchange_created_order_cannot_be_cancelled_on_its_own(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);
        $order = $this->placeOrder($reception, $customer, $product, 1);
        $item = $order->items()->firstOrFail();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                'new_items' => [['product_id' => $product->id, 'quantity' => 1]],
                'reason' => 'تعویض',
            ])
            ->assertStatus(201);

        $newOrder = OrderExchange::firstOrFail()->newOrder;

        $this->actingAs($reception, 'sanctum')
            ->deleteJson("/api/reception/orders/{$newOrder->id}")
            ->assertStatus(422);

        $this->assertNotNull(Order::find($newOrder->id));
    }

    public function test_another_reception_cannot_exchange_an_order(): void
    {
        config(['tax.vat_rate' => 0]);
        $owner = $this->makeUser('reception');
        $other = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);
        $order = $this->placeOrder($owner, $customer, $product, 1);
        $item = $order->items()->firstOrFail();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/reception/orders/{$order->id}/exchanges", [
                'return_items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                'new_items' => [['product_id' => $product->id, 'quantity' => 1]],
                'reason' => 'تلاش غیرمجاز',
            ])
            ->assertNotFound();

        $this->assertSame(0, OrderExchange::count());
    }

    /** همگام‌سازی آفلاین: ارسال دوباره‌ی یک سفارش نباید موجودی را دوبار کم کند */

    public function test_resending_an_offline_order_with_the_same_client_uuid_is_not_double_counted(): void
    {
        config(['tax.vat_rate' => 0]);
        $reception = $this->makeUser('reception');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(['price' => 10000, 'stock_quantity' => 10]);
        $payload = [
            'customer_membership_number' => $customer->membership_number,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'apply_discounts' => false,
            'payment_method' => 'cash',
            'client_uuid' => '7f0c9a6e-2b1d-4c3e-9a8f-112233445566',
        ];

        $this->actingAs($reception, 'sanctum')->postJson('/api/reception/orders', $payload)->assertStatus(201);
        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/orders', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, Order::count());
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
        $this->assertEquals(30000, (float) $customer->fresh()->total_purchases);
    }
}
