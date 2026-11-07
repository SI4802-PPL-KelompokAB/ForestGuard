<?php

namespace Tests;

use Database\Seeders\UserSeeder;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }

        Browser::$waitSeconds = 15;

        Browser::macro('loginAsAdmin', function () {
            /** @var Browser $this */
            $this->logout();
            $this->driver->manage()->deleteAllCookies();

            return $this->visit('/login')
                ->waitFor('input#email')
                ->type('email', 'admin@forestguard.test')
                ->type('password', UserSeeder::DEV_PASSWORD)
                ->press('Masuk')
                ->waitForRoute('admin.dashboard');
        });

        Browser::macro('loginAsRanger', function () {
            /** @var Browser $this */
            $this->logout();
            $this->driver->manage()->deleteAllCookies();

            return $this->visit('/login')
                ->waitFor('input#email')
                ->type('email', 'ranger1@forestguard.test')
                ->type('password', UserSeeder::DEV_PASSWORD)
                ->press('Masuk')
                ->waitForRoute('ranger.home');
        });

        Browser::macro('loginAsCitizen', function () {
            /** @var Browser $this */
            $this->logout();
            $this->driver->manage()->deleteAllCookies();

            return $this->visit('/login')
                ->waitFor('input#email')
                ->type('email', 'citizen1@forestguard.test')
                ->type('password', UserSeeder::DEV_PASSWORD)
                ->press('Masuk')
                ->waitForRoute('citizen.home');
        });
    }

    public function loginAsAdmin(Browser $browser): Browser
    {
        return $browser->loginAsAdmin();
    }

    public function loginAsRanger(Browser $browser): Browser
    {
        return $browser->loginAsRanger();
    }

    public function loginAsCitizen(Browser $browser): Browser
    {
        return $browser->loginAsCitizen();
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
