<?php

namespace Tests;

use Database\Seeders\UserSeeder;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Chrome\ChromeProcess;
use Laravel\Dusk\TestCase as BaseTestCase;
use Symfony\Component\Process\Process;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::prepare();
    }

    public static function prepare(): void
    {
        if (! static::runningInSail() && (! static::$chromeProcess || ! static::$chromeProcess->isRunning())) {
            static::startChromeDriver(['--port=9515']);
        }

        Browser::$waitSeconds = 15;
        Browser::$supportsRemoteLogs = [];

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

        Browser::macro('slow', function (?int $ms = null) {
            /** @var Browser $this */
            $enabled = env('DUSK_SLOW_MODE') ?? $_ENV['DUSK_SLOW_MODE'] ?? $_SERVER['DUSK_SLOW_MODE'] ?? true;
            if ($enabled === 'true' || $enabled === true || $enabled === '1' || $enabled === 1) {
                $delay = $ms ?? (int) (env('DUSK_SLOW_MS') ?? $_ENV['DUSK_SLOW_MS'] ?? $_SERVER['DUSK_SLOW_MS'] ?? 2000);
                $this->pause($delay);
            }

            return $this;
        });

        Browser::macro('stepNotice', function (string $text, ?int $ms = 2500) {
            /** @var Browser $this */
            $escaped = json_encode($text);
            $this->script("
                let banner = document.getElementById('dusk-step-banner');
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id = 'dusk-step-banner';
                    banner.style.position = 'fixed';
                    banner.style.bottom = '28px';
                    banner.style.left = '50%';
                    banner.style.transform = 'translateX(-50%)';
                    banner.style.backgroundColor = '#0f172a';
                    banner.style.color = '#38bdf8';
                    banner.style.padding = '12px 24px';
                    banner.style.borderRadius = '9999px';
                    banner.style.boxShadow = '0 10px 30px rgba(0,0,0,0.6)';
                    banner.style.fontFamily = 'system-ui, -apple-system, sans-serif';
                    banner.style.fontSize = '14px';
                    banner.style.fontWeight = '600';
                    banner.style.zIndex = '9999999';
                    banner.style.border = '2px solid #0284c7';
                    banner.style.display = 'flex';
                    banner.style.alignItems = 'center';
                    banner.style.gap = '10px';
                    banner.style.transition = 'all 0.3s ease';
                    document.body.appendChild(banner);
                }
                banner.innerHTML = '<span style=\"width:10px;height:10px;background:#22c55e;border-radius:50%;display:inline-block;box-shadow:0 0 10px #22c55e;\"></span> ' + {$escaped};
            ");
            $this->pause($ms);

            return $this;
        });

        Browser::macro('highlight', function (string $selector, ?int $ms = 1500) {
            /** @var Browser $this */
            $escaped = json_encode($selector);
            $this->script("
                const el = document.querySelector({$escaped});
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const originalOutline = el.style.outline;
                    const originalBoxShadow = el.style.boxShadow;
                    el.style.outline = '4px solid #10b981';
                    el.style.boxShadow = '0 0 25px rgba(16, 185, 129, 0.7)';
                    el.style.transition = 'all 0.4s ease';
                    setTimeout(() => {
                        el.style.outline = originalOutline;
                        el.style.boxShadow = originalBoxShadow;
                    }, {$ms});
                }
            ");
            $this->pause($ms);

            return $this;
        });
    }

    public function slow(Browser $browser, ?int $ms = null): Browser
    {
        return $browser->slow($ms);
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
     * Override default DatabaseMigrations handling to prevent rollback from dropping tables
     * during Dusk test suites running against a shared web server and persistent database.
     *
     * @return void
     */
    public function runDatabaseMigrations()
    {
        $this->artisan('migrate');
    }

    /**
     * Build the process to run the Chromedriver without process timeout.
     *
     * @return Process
     */
    protected static function buildChromeProcess(array $arguments = [])
    {
        $process = (new ChromeProcess(static::$chromeDriver))->toProcess($arguments);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        return $process;
    }

    /**
     * Determine whether the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return true;
    }

    /**
     * Always disable headless mode so the browser window GUI is always visible on screen.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return true;
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            '--no-sandbox',
            '--disable-dev-shm-usage',
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        $options->setExperimentalOption('prefs', [
            'profile.default_content_setting_values.geolocation' => 1,
        ]);

        $capabilities = DesiredCapabilities::chrome()->setCapability(
            ChromeOptions::CAPABILITY, $options
        );
        $capabilities->setCapability('goog:loggingPrefs', ['browser' => 'ALL']);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            $capabilities
        );
    }
}
