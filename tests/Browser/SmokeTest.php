<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SmokeTest extends DuskTestCase
{
    /**
     * Smoke test: halaman login terbuka dengan benar.
     */
    public function test_login_page_opens_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitForText('Masuk ke akun')
                ->assertSee('Daftar sebagai warga')
                ->assertPresent('input#email')
                ->assertPresent('input#password')
                ->assertPresent('button[type="submit"]');
        });
    }

    /**
     * Smoke test: login helper works for admin, ranger, citizen.
     */
    public function test_role_logins_work_via_helpers(): void
    {
        $this->browse(function (Browser $browser) {
            // Admin login
            $this->loginAsAdmin($browser)
                ->assertSee('Dasbor')
                ->press('Keluar')
                ->waitForRoute('login');

            // Ranger login
            $this->loginAsRanger($browser)
                ->assertSee('Beranda Ranger')
                ->press('Keluar')
                ->waitForRoute('login');

            // Citizen login
            $this->loginAsCitizen($browser)
                ->assertSee('Laporan Saya')
                ->press('Keluar')
                ->waitForRoute('login');
        });
    }
}
