<?php

namespace Tests\Browser;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser test for the JS behaviour PHPUnit can't reach: on the Gate-In form the
 * "No-seal reason" field is hidden by default and appears only when the box is
 * Laden (directly, or via a laden Job Type), then hides again for Empty.
 *
 * Setup (one-time):
 *   composer require --dev laravel/dusk
 *   php artisan dusk:install
 *   php artisan dusk:chrome-driver
 * Run:
 *   php artisan dusk --filter=SealReasonRevealTest
 *
 * Cache note: under Dusk the served app keeps its own settings cache. Use a shared
 * cache driver in .env.dusk.local (CACHE_DRIVER=file or database) so the
 * flushCache() below is visible to the browser's requests.
 */
class SealReasonRevealTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_no_seal_reason_reveals_only_for_laden(): void
    {
        // Ensure the settings row exists (Dusk migrates a fresh, unseeded DB), then
        // turn the policy on for the served app to read.
        CompanySetting::current();                 // firstOrCreate the row
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();

        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/yard/gate')
                ->waitFor('#cargoStatusIn')
                // Hidden for the default cargo status (Empty). assertMissing passes
                // for an element that is present but not displayed (d-none).
                ->assertMissing('#noSealWrapIn')
                // Select Laden → the reveal fires and the field appears.
                ->select('cargo_status', 'laden')
                ->waitFor('#noSealWrapIn')
                ->assertVisible('#noSealWrapIn')
                // Back to Empty → it hides again.
                ->select('cargo_status', 'empty')
                ->waitUntilMissing('#noSealWrapIn');
        });
    }

    public function test_no_seal_reason_reveals_when_a_laden_job_type_is_chosen(): void
    {
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();

        $admin = User::factory()->systemAdmin()->create();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/yard/gate')
                ->waitFor('#cargoStatusIn')
                ->assertMissing('#noSealWrapIn');

            // Pick the first Job Type whose data-cargo-hint is 'laden'; selecting it
            // auto-sets the cargo status, which must trigger the reveal.
            $ladenValue = $browser->script(
                "return (function () {" .
                "  var sel = document.getElementById('jobTypeSelect');" .
                "  if (!sel) return '';" .
                "  for (var i = 0; i < sel.options.length; i++) {" .
                "    if (sel.options[i].getAttribute('data-cargo-hint') === 'laden') return sel.options[i].value;" .
                "  }" .
                "  return '';" .
                "})();"
            )[0];

            if ($ladenValue === '') {
                $this->markTestSkipped('No seeded Job Type carries a laden cargo hint.');
            }

            $browser->select('job_type_id', $ladenValue)
                ->waitFor('#noSealWrapIn')
                ->assertVisible('#noSealWrapIn');
        });
    }
}
