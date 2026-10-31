<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCardAuditMaskTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_CODE = 'GCABCDEFGHIJK';

    public function test_created_gift_card_audit_entry_never_contains_the_full_code(): void
    {
        $card = GiftCard::create([
            'code' => self::FULL_CODE,
            'initial_amount' => 100000,
            'balance' => 100000,
            'is_active' => true,
        ]);

        $log = AuditLog::where('subject_type', 'GiftCard')
            ->where('subject_id', $card->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertStringNotContainsString(self::FULL_CODE, (string) json_encode($log->changes, JSON_UNESCAPED_UNICODE));
        $this->assertSame('***HIJK', $log->changes['code']['new']);
        $this->assertStringNotContainsString(self::FULL_CODE, (string) $log->subject_label);
    }

    public function test_updated_gift_card_audit_entry_never_contains_the_full_code(): void
    {
        $card = GiftCard::create([
            'code' => self::FULL_CODE,
            'initial_amount' => 50000,
            'balance' => 50000,
            'is_active' => true,
        ]);

        $card->update(['is_active' => false]);

        $log = AuditLog::where('subject_type', 'GiftCard')
            ->where('subject_id', $card->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertStringNotContainsString(self::FULL_CODE, (string) json_encode($log->changes, JSON_UNESCAPED_UNICODE));
    }

    public function test_deleted_gift_card_audit_entry_never_contains_the_full_code(): void
    {
        $card = GiftCard::create([
            'code' => self::FULL_CODE,
            'initial_amount' => 50000,
            'balance' => 50000,
            'is_active' => true,
        ]);

        $card->delete();

        $log = AuditLog::where('subject_type', 'GiftCard')
            ->where('subject_id', $card->id)
            ->where('action', 'deleted')
            ->firstOrFail();

        $this->assertStringNotContainsString(self::FULL_CODE, (string) json_encode($log->changes, JSON_UNESCAPED_UNICODE));
    }

    public function test_migration_masks_full_codes_already_stored_in_the_audit_log(): void
    {
        $legacy = AuditLog::create([
            'action' => 'created',
            'subject_type' => 'GiftCard',
            'subject_id' => 999,
            'subject_label' => 'گیفت کارت ***HIJK',
            'description' => 'ایجاد',
            'changes' => ['code' => ['old' => null, 'new' => self::FULL_CODE]],
            'created_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_11_06_100001_mask_gift_card_codes_in_audit_logs.php');
        $migration->up();

        $legacy->refresh();

        $this->assertSame('***HIJK', $legacy->changes['code']['new']);
        $this->assertStringNotContainsString(self::FULL_CODE, (string) json_encode($legacy->changes, JSON_UNESCAPED_UNICODE));

        // اجرای دوباره نباید مقدار ماسک‌شده را دوباره تغییر دهد
        $migration->up();
        $legacy->refresh();
        $this->assertSame('***HIJK', $legacy->changes['code']['new']);
    }
}
