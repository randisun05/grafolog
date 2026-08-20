<?php

namespace Tests\Unit;

use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_amount_off_rounds_correctly(): void
    {
        $code = DiscountCode::create(['code' => 'P10', 'type' => 'percentage', 'value' => 10]);

        $this->assertSame(4900, $code->amountOff(49000));
    }

    public function test_fixed_amount_off_never_exceeds_base_price(): void
    {
        $code = DiscountCode::create(['code' => 'F100K', 'type' => 'fixed', 'value' => 100000]);

        $this->assertSame(49000, $code->amountOff(49000));
    }

    public function test_inactive_code_is_invalid(): void
    {
        $code = DiscountCode::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 1000, 'is_active' => false]);

        $this->assertFalse($code->isValidFor('comprehensive'));
    }

    public function test_expired_code_is_invalid(): void
    {
        $code = DiscountCode::create([
            'code' => 'EXPIRED', 'type' => 'fixed', 'value' => 1000,
            'valid_until' => now()->subDay(),
        ]);

        $this->assertFalse($code->isValidFor('comprehensive'));
    }

    public function test_not_yet_valid_code_is_invalid(): void
    {
        $code = DiscountCode::create([
            'code' => 'FUTURE', 'type' => 'fixed', 'value' => 1000,
            'valid_from' => now()->addDay(),
        ]);

        $this->assertFalse($code->isValidFor('comprehensive'));
    }

    public function test_exhausted_quota_is_invalid(): void
    {
        $code = DiscountCode::create([
            'code' => 'MAXED', 'type' => 'fixed', 'value' => 1000,
            'max_uses' => 2, 'used_count' => 2,
        ]);

        $this->assertFalse($code->isValidFor('comprehensive'));
    }

    public function test_tier_restriction_is_enforced(): void
    {
        $code = DiscountCode::create([
            'code' => 'MASTERONLY', 'type' => 'fixed', 'value' => 1000,
            'applicable_tiers' => ['master'],
        ]);

        $this->assertFalse($code->isValidFor('comprehensive'));
        $this->assertTrue($code->isValidFor('master'));
    }

    public function test_null_applicable_tiers_means_all_tiers(): void
    {
        $code = DiscountCode::create(['code' => 'ALLTIERS', 'type' => 'fixed', 'value' => 1000]);

        $this->assertTrue($code->isValidFor('comprehensive'));
        $this->assertTrue($code->isValidFor('master'));
    }

    public function test_increment_usage(): void
    {
        $code = DiscountCode::create(['code' => 'USE1', 'type' => 'fixed', 'value' => 1000, 'used_count' => 0]);

        $code->incrementUsage();

        $this->assertSame(1, $code->fresh()->used_count);
    }
}
