<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use Tests\Support\FeatureTestCase;

/**
 * The driver gate-pass WhatsApp link must point at this tenant's real host.
 * route() derives the host from the request/APP_URL, which behind a proxy or on
 * a shared domain can come out wrong (e.g. www.gensoftcyms.com instead of the
 * crown.gensoftcyms.com subdomain). An operator-set base URL pins it.
 */
class GatePassLinkTest extends FeatureTestCase
{
    public function test_configured_base_url_overrides_the_host_but_keeps_the_route_path(): void
    {
        $cs = CompanySetting::current();
        $cs->update(['gatepass_base_url' => 'https://crown.gensoftcyms.com']);

        // Path stays route-driven (/g/{code}); only scheme+host come from the base.
        $expectedPath = parse_url(route('gp.short', 'rmplf6vesile'), PHP_URL_PATH);
        $this->assertSame(
            'https://crown.gensoftcyms.com' . $expectedPath,
            $cs->fresh()->gatePassUrl('rmplf6vesile')
        );
    }

    public function test_falls_back_to_route_when_base_url_is_blank(): void
    {
        $cs = CompanySetting::current();
        $cs->update(['gatepass_base_url' => null]);

        $this->assertSame(route('gp.short', 'rmplf6vesile'), $cs->fresh()->gatePassUrl('rmplf6vesile'));
    }

    public function test_settings_form_normalises_a_bare_host_to_https_without_trailing_slash(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('settings.company.update'), [
            'company_name'      => 'Crown Yard',
            'gatepass_base_url' => 'crown.gensoftcyms.com/',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://crown.gensoftcyms.com', CompanySetting::current()->gatepass_base_url);
    }
}
