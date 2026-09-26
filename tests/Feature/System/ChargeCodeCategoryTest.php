<?php

namespace Tests\Feature\System;

use App\Models\ChargeCode;
use Tests\Support\FeatureTestCase;

/**
 * A seeded charge code's category has to be one the model knows.
 *
 * `ChargeCodeSeeder` writes the category as a plain string, and the column is a
 * `varchar`, so the database accepts anything. Everything downstream does not:
 * `ChargeCodeController` validates `category` against the keys of
 * `ChargeCode::CATEGORIES` on both store and update, the edit form builds its
 * dropdown from the same keys, and the index filters on it.
 *
 * So a code seeded into a category the constant does not list is a code whose
 * category no operator can see, and whose first edit either fails validation or
 * silently moves it somewhere else. That is exactly what happened when LHIRE
 * and SHIRE were seeded under `hire`: two codes the billing screens resolve by
 * name, sitting in a category the master screen could not represent.
 *
 * Pinned generally rather than for those two codes, because the next code added
 * to the seeder will be added the same way.
 */
class ChargeCodeCategoryTest extends FeatureTestCase
{
    public function test_every_seeded_category_is_one_the_model_lists(): void
    {
        $seeded = ChargeCode::query()->whereNotNull('category')->distinct()->pluck('category')->all();

        $this->assertNotEmpty($seeded, 'The baseline seeds charge codes; if not, this test proves nothing.');

        $this->assertSame(
            [],
            array_values(array_diff($seeded, array_keys(ChargeCode::CATEGORIES))),
            'Seeded into a category the master screen cannot offer or validate.',
        );
    }

    public function test_every_category_has_a_badge(): void
    {
        $this->assertSame(
            [],
            array_values(array_diff(
                array_keys(ChargeCode::CATEGORIES),
                array_keys(ChargeCode::CATEGORY_BADGES),
            )),
        );
    }

    /** The two the hire billing resolves by code. Both directions, kept apart. */
    public function test_the_hire_codes_are_seeded_in_the_hire_category(): void
    {
        foreach (['LHIRE', 'SHIRE'] as $code) {
            $charge = ChargeCode::where('code', $code)->first();

            $this->assertNotNull($charge, "{$code} is missing — the hire billing screens resolve it by code.");
            $this->assertSame('hire', $charge->category);
            $this->assertSame('per_day', $charge->rate_type);
            $this->assertSame('Hire & Rental', $charge->category_label);
        }
    }

    /**
     * The failure this would have produced on the master screen: a system admin
     * opening LHIRE, changing the description, and saving it back.
     */
    public function test_a_hire_code_survives_being_edited_on_the_master_screen(): void
    {
        $this->actingAsSystemAdmin();

        $charge = ChargeCode::where('code', 'LHIRE')->firstOrFail();

        $this->patch(route('masters.charge-codes.update', $charge), [
            'code'        => 'LHIRE',
            'description' => 'Container Lease / On-Hire Fee',
            'category'    => 'hire',
            'rate_type'   => 'per_day',
            'tax_code_id' => $charge->tax_code_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('hire', $charge->fresh()->category);
    }
}
