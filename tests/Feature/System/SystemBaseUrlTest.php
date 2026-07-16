<?php

namespace Tests\Feature\System;

use App\Models\CompanySetting;
use App\Support\BaseUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\Support\FeatureTestCase;

/**
 * The operator-configured System Base URL must govern EVERY generated absolute
 * URL (routes, signed verification links, portal/QR links, WhatsApp), and keep
 * signed links validating even when the incoming request host is wrong (proxy /
 * shared-domain). Unset → default behaviour, unchanged.
 */
class SystemBaseUrlTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        // forceRootUrl is process-global — clear it so it can't leak to other tests.
        URL::forceRootUrl(null);
        URL::forceScheme(null);
        parent::tearDown();
    }

    public function test_configured_base_url_forces_generated_link_host(): void
    {
        $cs = CompanySetting::current();
        $cs->update(['app_base_url' => 'https://crown.gensoftcyms.com']);

        BaseUrl::forceGeneration('https://crown.gensoftcyms.com');

        $this->assertStringStartsWith('https://crown.gensoftcyms.com/', route('gp.short', 'abc123'));
        $this->assertStringStartsWith('https://crown.gensoftcyms.com/', $cs->fresh()->gatePassUrl('abc123'));
    }

    public function test_signed_link_validates_after_request_host_is_normalised(): void
    {
        CompanySetting::current()->update(['app_base_url' => 'https://crown.gensoftcyms.com']);
        BaseUrl::forceGeneration('https://crown.gensoftcyms.com');

        // Generated on the correct (base) host.
        $signed = URL::signedRoute('documents.verify', ['type' => 'invoice', 'id' => 7]);
        $this->assertStringStartsWith('https://crown.gensoftcyms.com/', $signed);

        // Request arrives on the WRONG host (as the proxy would present it).
        $wrong = str_replace('https://crown.gensoftcyms.com', 'http://www.gensoftcyms.com', $signed);
        $request = Request::create($wrong);
        $this->assertFalse(URL::hasValidSignature($request), 'Signature should not match the wrong host.');

        // After normalising the request host to the base, the signature validates.
        BaseUrl::normalizeRequest($request, 'https://crown.gensoftcyms.com');
        $this->assertTrue(URL::hasValidSignature($request), 'Signature must validate once the host is normalised.');
    }

    public function test_no_base_url_leaves_generation_on_the_request_host(): void
    {
        CompanySetting::current()->update(['app_base_url' => null]);

        // resolve() returns null and forceGeneration is a no-op.
        $this->assertNull(BaseUrl::resolve());
        BaseUrl::forceGeneration();
        $this->assertStringContainsString('/g/abc123', route('gp.short', 'abc123'));
    }

    public function test_settings_form_normalises_and_stores_app_base_url(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('settings.company.update'), [
            'company_name' => 'Crown Yard',
            'app_base_url' => 'crown.gensoftcyms.com/',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://crown.gensoftcyms.com', CompanySetting::current()->app_base_url);
    }
}
