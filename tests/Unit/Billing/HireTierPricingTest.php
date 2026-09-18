<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\HireTierPricing as P;
use PHPUnit\Framework\TestCase;

/**
 * Tiered hire pricing, as arithmetic.
 *
 * A unit test with no database, because the class takes plain numbers and
 * returns plain numbers — the same shape as {@see \App\Services\Billing\ManualPricing}
 * and for the same reason: the arithmetic is the design.
 *
 * The rule these all turn on is that **a monthly rate is a block price, not
 * thirty daily rates**. Where 30 days cost 30,000 and day 31 onward costs 1,200,
 * the implied daily rate inside the block is 1,000. That gap is the commercial
 * incentive to take a longer hire, and deriving either rate from the other would
 * destroy it.
 */
class HireTierPricingTest extends TestCase
{
    /** 1 month @ 30,000, then daily @ 1,200 — the agreement in the requirement. */
    private const MONTHLY = ['unit' => 'monthly', 'rate' => 30000.0, 'max_units' => 1];
    private const DAILY   = ['unit' => 'daily',   'rate' => 1200.0,  'max_units' => null];

    private function tiers(): array
    {
        return [self::MONTHLY, self::DAILY];
    }

    // ── The worked example ──────────────────────────────────────────────────

    /** 37 days is one month plus seven days, not 37 daily charges. */
    public function test_thirty_seven_days_is_one_month_plus_seven(): void
    {
        $r = P::price(37, $this->tiers());

        $this->assertSame(38400.0, $r['total']);
        $this->assertSame('1 month + 7 days', P::describe($r));
        $this->assertSame(37, $r['priced_days']);
        $this->assertSame([], $r['warnings']);
    }

    public function test_exactly_one_month_uses_only_the_monthly_tier(): void
    {
        $r = P::price(30, $this->tiers());

        $this->assertSame(30000.0, $r['total']);
        $this->assertCount(1, $r['segments']);
    }

    public function test_one_day_over_the_month_adds_a_single_daily(): void
    {
        $this->assertSame(31200.0, P::price(31, $this->tiers())['total']);
    }

    // ── The partial-block rule: the same 20 days, three contracts ───────────

    public function test_a_partial_month_falls_back_to_daily_by_default(): void
    {
        $r = P::price(20, $this->tiers(), P::PARTIAL_DAILY_FALLBACK);

        $this->assertSame(24000.0, $r['total'], '20 x 1,200 — it never reached a month.');
        $this->assertSame('20 days', P::describe($r));
    }

    public function test_a_partial_month_can_be_charged_as_a_whole_block(): void
    {
        $r = P::price(20, $this->tiers(), P::PARTIAL_WHOLE_BLOCK);

        $this->assertSame(30000.0, $r['total'], 'The month is a block and it was bought.');
        $this->assertTrue($r['segments'][0]['is_partial']);
    }

    public function test_a_partial_month_can_be_pro_rated(): void
    {
        $this->assertSame(20000.0, P::price(20, $this->tiers(), P::PARTIAL_PRO_RATA)['total'],
            '30,000 x 20/30.');
    }

    /**
     * The three rules on identical facts: 20,000 to 30,000 on the same 20 days.
     * This is why the rule is stored on the agreement rather than chosen when
     * somebody opens the billing screen.
     */
    public function test_the_three_rules_give_three_different_answers(): void
    {
        $totals = array_map(
            fn (string $rule) => P::price(20, $this->tiers(), $rule)['total'],
            [P::PARTIAL_PRO_RATA, P::PARTIAL_DAILY_FALLBACK, P::PARTIAL_WHOLE_BLOCK],
        );

        $this->assertSame([20000.0, 24000.0, 30000.0], $totals);
    }

    // ── Multi-unit and reordered tiers ──────────────────────────────────────

    public function test_a_two_month_tier_is_consumed_before_the_daily_one(): void
    {
        $r = P::price(75, [
            ['unit' => 'monthly', 'rate' => 30000.0, 'max_units' => 2],
            self::DAILY,
        ]);

        $this->assertSame(78000.0, $r['total'], '2 months (60d) + 15 daily.');
        $this->assertSame('2 months + 15 days', P::describe($r));
    }

    /**
     * Whole units are consumed first and only the remainder meets the rule: 50
     * days against a two-month tier is one whole month plus 20 days, and those
     * 20 fall through.
     */
    public function test_whole_units_are_taken_before_the_partial_rule_applies(): void
    {
        $r = P::price(50, [
            ['unit' => 'monthly', 'rate' => 30000.0, 'max_units' => 2],
            self::DAILY,
        ]);

        $this->assertSame(54000.0, $r['total']);
        $this->assertSame('1 month + 20 days', P::describe($r));
    }

    /** Daily first, then monthly — the operator chooses which bills first. */
    public function test_a_daily_tier_can_come_first(): void
    {
        $r = P::price(37, [
            ['unit' => 'daily',   'rate' => 1500.0,  'max_units' => 7],
            ['unit' => 'monthly', 'rate' => 28000.0, 'max_units' => null],
        ]);

        $this->assertSame(38500.0, $r['total'], '7 x 1,500 then one clean month.');
        $this->assertSame('7 days + 1 month', P::describe($r));
    }

    /** A free introductory period is a tier priced at zero. */
    public function test_a_zero_rated_tier_gives_free_days(): void
    {
        $r = P::price(12, [
            ['unit' => 'daily', 'rate' => 0.0, 'max_units' => 5],
            self::DAILY,
        ]);

        $this->assertSame(8400.0, $r['total'], 'Five free, seven charged.');
    }

    /**
     * `whole_block` is harsh on purpose, and worth pinning so nobody "fixes" it:
     * three days past a whole month buy a second month. That is what a block
     * price means, and it is why daily fallback is the default.
     */
    public function test_whole_block_charges_a_second_month_for_a_few_days_over(): void
    {
        $r = P::price(40, [
            ['unit' => 'daily',   'rate' => 1500.0,  'max_units' => 7],
            ['unit' => 'monthly', 'rate' => 28000.0, 'max_units' => null],
        ], P::PARTIAL_WHOLE_BLOCK);

        $this->assertSame(66500.0, $r['total']);
        $this->assertSame('7 days + 1 month + 1 month (part)', P::describe($r));
    }

    // ── It must never bill silently short ───────────────────────────────────

    /**
     * Every tier capped and they do not cover the hire. The shortfall is
     * reported rather than dropped — a bill quietly missing 25 days is the
     * failure this whole design guards against.
     */
    public function test_days_outside_every_tier_are_reported_not_dropped(): void
    {
        $r = P::price(60, [
            self::MONTHLY,
            ['unit' => 'daily', 'rate' => 1200.0, 'max_units' => 5],
        ]);

        $this->assertSame(36000.0, $r['total']);
        $this->assertSame(35, $r['priced_days']);
        $this->assertSame(25, $r['unpriced_days']);
        $this->assertCount(1, $r['warnings']);
    }

    /**
     * Daily fallback with nothing to fall back to. Charging the block is wrong
     * by the agreement, but billing zero would be wrong and silent, so it
     * charges and says so.
     */
    public function test_a_fallback_with_no_later_tier_charges_and_warns(): void
    {
        $r = P::price(20, [['unit' => 'monthly', 'rate' => 30000.0, 'max_units' => null]]);

        $this->assertSame(30000.0, $r['total']);
        $this->assertCount(1, $r['warnings']);
        $this->assertStringContainsString('no later tier', $r['warnings'][0]);
    }

    public function test_an_agreement_with_no_tiers_prices_nothing_and_says_so(): void
    {
        $r = P::price(10, []);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame(10, $r['unpriced_days']);
        $this->assertCount(1, $r['warnings']);
    }

    public function test_a_zero_day_hire_is_not_a_warning(): void
    {
        $r = P::price(0, $this->tiers());

        $this->assertSame(0.0, $r['total']);
        $this->assertSame([], $r['warnings']);
        $this->assertSame('Nothing to charge', P::describe($r));
    }

    // ── The month length is a setting ───────────────────────────────────────

    public function test_the_month_length_changes_where_the_tier_breaks(): void
    {
        $at30 = P::price(30, $this->tiers(), P::PARTIAL_DAILY_FALLBACK, 30);
        $at28 = P::price(30, $this->tiers(), P::PARTIAL_DAILY_FALLBACK, 28);

        $this->assertSame(30000.0, $at30['total'], 'Exactly one month.');
        $this->assertSame(32400.0, $at28['total'], 'One 28-day month, then two daily.');
    }
}
