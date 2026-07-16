<?php

namespace Tests\Feature\Repair;

use Tests\Support\FeatureTestCase;

/**
 * The "Pull From Assessment Rules" search must find corrosion rules by either
 * term — the rule names now say "Corrosion", and the search also matches the
 * damage/component/repair names so the synonym "Rust" still works too.
 */
class AssessmentRuleSearchTest extends FeatureTestCase
{
    public function test_search_finds_corrosion_rules(): void
    {
        $this->actingAsSystemAdmin();

        $res = $this->getJson(route('masters.damage-assessment-rules.search', ['q' => 'Corrosion']));
        $res->assertOk();

        $rules = collect($res->json('rules'));
        $this->assertNotEmpty($rules, 'Searching "Corrosion" should return the RST rules.');
        $this->assertTrue($rules->every(fn ($r) => $r['damage_code'] === 'RST'));
        // The renamed rule names now carry "Corrosion".
        $this->assertTrue($rules->contains(fn ($r) => str_contains($r['name'], 'Corrosion')));
    }

    public function test_search_still_finds_them_by_the_rust_synonym(): void
    {
        $this->actingAsSystemAdmin();

        // Rule names no longer contain "Rust"; the match now comes from the damage
        // name "Corrosion / Rust" via the broadened search.
        $res = $this->getJson(route('masters.damage-assessment-rules.search', ['q' => 'Rust']));
        $res->assertOk();

        $rules = collect($res->json('rules'));
        $this->assertNotEmpty($rules, 'Searching "Rust" should still return the corrosion rules.');
        $this->assertTrue($rules->every(fn ($r) => $r['damage_code'] === 'RST'));
    }
}
