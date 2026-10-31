<?php

namespace Tests\Feature;

use App\Models\CashShift;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class CashShiftGuardTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    public function test_second_open_shift_for_the_same_user_is_rejected(): void
    {
        $reception = $this->makeUser('reception');

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/cash-shifts', ['opening_cash' => 100000])
            ->assertStatus(201);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/cash-shifts', ['opening_cash' => 100000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('opening_cash');

        $this->assertSame(1, CashShift::where('user_id', $reception->id)->whereNull('closed_at')->count());
    }

    public function test_database_rejects_a_second_open_shift_even_without_the_service(): void
    {
        $reception = $this->makeUser('reception');

        CashShift::create([
            'user_id' => $reception->id,
            'open_user_id' => $reception->id,
            'opened_at' => now(),
            'opening_cash' => 0,
        ]);

        $this->expectException(QueryException::class);

        CashShift::create([
            'user_id' => $reception->id,
            'open_user_id' => $reception->id,
            'opened_at' => now(),
            'opening_cash' => 0,
        ]);
    }

    public function test_closing_a_shift_frees_the_slot_for_a_new_one(): void
    {
        $reception = $this->makeUser('reception');

        $first = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/cash-shifts', ['opening_cash' => 50000])
            ->assertStatus(201)
            ->json('shift.id');

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/reception/cash-shifts/{$first}/close", ['counted_cash' => 50000])
            ->assertOk();

        $this->assertNull(CashShift::find($first)->open_user_id);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/reception/cash-shifts', ['opening_cash' => 50000])
            ->assertStatus(201);

        $this->assertSame(1, CashShift::where('user_id', $reception->id)->whereNull('closed_at')->count());
        $this->assertSame(2, CashShift::where('user_id', $reception->id)->count());
    }
}
