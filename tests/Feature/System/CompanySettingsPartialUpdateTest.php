<?php

namespace Tests\Feature\System;

use App\Models\CompanySetting;
use App\Support\MrStatusCatalogue as Cat;
use Tests\Support\FeatureTestCase;

/**
 * Company Settings: a partial submit must not reset the fields it never carried.
 *
 * Three forms on that screen post to the same update action — the logo, icon
 * and product-icon uploads each send only company_name and their file. The
 * action assigned nine boolean toggles, the base URL and the M&R thresholds
 * unconditionally, reading every absent field as "off" or "blank".
 *
 * So uploading a logo silently switched off guard post, seal enforcement,
 * reefer PTI and export-booking enforcement, and blanked the base URL that
 * emailed gate-pass links are built from. Nothing on screen said so.
 *
 * Absence now means "the submitted form does not own this field". The main form
 * sends a hidden 0 alongside every checkbox so an unchecked box stays present
 * and can still be turned off — which the second test guards, because a fix
 * that made toggles impossible to switch off would be worse than the bug.
 */
class CompanySettingsPartialUpdateTest extends FeatureTestCase
{
    /** Every flag the update action can write. */
    private const FLAGS = [
        'enable_digital_approvals',
        'enable_guard_post',
        'enforce_export_booking',
        'enforce_reefer_pti',
        'require_seal_for_laden',
        'require_ot_receipt',
        'enable_gatepass_whatsapp',
        'guardpost_warn_no_capture',
        'enforce_storage_limit',
    ];

    private function settingsWithEverythingOn(): CompanySetting
    {
        $settings = CompanySetting::current();

        $settings->forceFill(array_merge(
            array_fill_keys(self::FLAGS, true),
            [
                'company_name'      => 'Acme Depot',
                'app_base_url'      => 'https://yard.example.com',
                'mr_age_thresholds' => [Cat::AWAITING_QC => 9],
            ]
        ))->save();

        CompanySetting::flushCache();

        return $settings;
    }

    public function test_an_upload_only_submit_leaves_every_other_setting_alone(): void
    {
        $this->actingAsSystemAdmin();
        $this->settingsWithEverythingOn();

        // Exactly what the logo/icon/product-icon forms send.
        $this->post(route('settings.company.update'), [
            'company_name' => 'Acme Depot',
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();
        $after = CompanySetting::current();

        foreach (self::FLAGS as $flag) {
            $this->assertTrue((bool) $after->{$flag},
                "Uploading a logo must not switch off {$flag}.");
        }

        $this->assertSame('https://yard.example.com', $after->app_base_url,
            'Blanking the base URL breaks every emailed gate-pass link.');

        $this->assertSame([Cat::AWAITING_QC => 9], $after->mr_age_thresholds,
            'Configured overdue thresholds must survive an unrelated submit.');
    }

    /** The fix must not make a toggle impossible to switch off. */
    public function test_the_main_form_can_still_switch_a_toggle_off(): void
    {
        $this->actingAsSystemAdmin();
        $this->settingsWithEverythingOn();

        // The main form submits a hidden 0 for every checkbox; an unchecked box
        // simply means the 0 is the only value sent.
        $payload = array_merge(
            array_fill_keys(self::FLAGS, '1'),
            [
                'company_name'      => 'Acme Depot',
                'enable_guard_post' => '0',   // unchecked
                'app_base_url'      => 'https://yard.example.com',
            ]
        );

        $this->post(route('settings.company.update'), $payload)->assertSessionHasNoErrors();

        CompanySetting::flushCache();
        $after = CompanySetting::current();

        $this->assertFalse((bool) $after->enable_guard_post, 'An unchecked toggle must still turn off.');
        $this->assertTrue((bool) $after->enforce_reefer_pti, 'The others are untouched.');
    }

    public function test_the_main_form_can_still_clear_the_base_url(): void
    {
        $this->actingAsSystemAdmin();
        $this->settingsWithEverythingOn();

        $this->post(route('settings.company.update'), [
            'company_name' => 'Acme Depot',
            'app_base_url' => '',
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();

        $this->assertNull(CompanySetting::current()->app_base_url,
            'Submitting the field empty is a deliberate clear, unlike omitting it.');
    }

    public function test_thresholds_round_trip_and_drop_blanks(): void
    {
        $this->actingAsSystemAdmin();
        $this->settingsWithEverythingOn();

        $this->post(route('settings.company.update'), [
            'company_name'      => 'Acme Depot',
            'mr_age_thresholds' => [
                Cat::AWAITING_QC        => '4',
                Cat::REPAIR_IN_PROGRESS => '',        // left blank → default applies
                'not_a_real_status'     => '5',       // ignored
            ],
        ])->assertSessionHasNoErrors();

        CompanySetting::flushCache();
        $stored = CompanySetting::current()->mr_age_thresholds;

        $this->assertSame([Cat::AWAITING_QC => 4], $stored,
            'Only real, filled-in stages are stored; the rest fall back to the shipped defaults.');
    }

    public function test_an_out_of_range_threshold_is_rejected(): void
    {
        $this->actingAsSystemAdmin();
        $this->settingsWithEverythingOn();

        $this->post(route('settings.company.update'), [
            'company_name'      => 'Acme Depot',
            'mr_age_thresholds' => [Cat::AWAITING_QC => '0'],
        ])->assertSessionHasErrors('mr_age_thresholds.' . Cat::AWAITING_QC);

        CompanySetting::flushCache();

        $this->assertSame([Cat::AWAITING_QC => 9], CompanySetting::current()->mr_age_thresholds,
            'A rejected submit changes nothing — zero days would flag every container instantly.');
    }
}
