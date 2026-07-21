<?php

namespace Tests\Browser;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Artisan;
use Tests\DuskTestCase;

/**
 * Base for browser (Dusk) tests.
 *
 * Database reset: uses `migrate:fresh --seed` (up-only) rather than the
 * DatabaseMigrations trait. This app has irreversible down() migrations (e.g. an
 * index needed by a foreign key on handling_tariff_rates), so a rollback-based
 * reset fails; migrate:fresh drops all tables and re-runs up migrations, which is
 * what the feature suite effectively does too. Seeding gives pages their master
 * data (job types, equipment, customers, zones, settings row).
 *
 * Watch it run: set DUSK_HEADLESS=false in .env.dusk.local to open a visible
 * Chrome window (default is headless).
 */
abstract class BrowserTestCase extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fresh schema + baseline data on the (throwaway) Dusk database.
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    /**
     * Build the Chrome driver. Headless by default; DUSK_HEADLESS=false shows the
     * window. The extra flags disable Chrome background networking/sync so it
     * doesn't stall on Google GCM registration retries (the slow, noisy
     * DEPRECATED_ENDPOINT lines).
     */
    protected function driver(): RemoteWebDriver
    {
        $headless = filter_var(env('DUSK_HEADLESS', true), FILTER_VALIDATE_BOOLEAN);

        $args = [
            '--window-size=1400,1000',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-background-networking',
            '--disable-sync',
            '--disable-default-apps',
            '--disable-extensions',
            '--no-first-run',
            '--no-default-browser-check',
        ];

        if ($headless) {
            $args[] = '--headless=new';
        }

        $options = (new ChromeOptions)->addArguments($args);

        return RemoteWebDriver::create(
            env('DUSK_DRIVER_URL') ?: 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options)
        );
    }
}
