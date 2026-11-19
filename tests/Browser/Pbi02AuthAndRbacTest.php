<?php

namespace Tests\Browser;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * PBI-02 Autentikasi Pengguna & RBAC - Laravel Dusk.
 *
 * Cakupan pengujian:
 *  - Halaman login terbuka dengan benar (Smoke Test)
 *  - Registrasi mandiri warga (Citizen Registration - UC-02)
 *  - Validasi formulir registrasi warga
 *  - Login & logout per peran (Admin, Ranger, Citizen - UC-01)
 *  - Validasi kegagalan login (Kredensial salah / anti user-enumeration)
 *  - Penolakan login akun nonaktif
 *  - Penegakan otorisasi peran (RBAC & proteksi rute)
 */
class Pbi02AuthAndRbacTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * TC-PBI02-01: Smoke test halaman login dapat dibuka dan elemen form lengkap.
     */
    public function test_login_page_opens_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitForText('Masuk ke akun')
                ->assertSee('Daftar sebagai warga')
                ->assertPresent('input#email')
                ->assertPresent('input#password')
                ->assertPresent('button[type="submit"]');
        });
    }

    /**
     * TC-PBI02-02: Warga dapat mendaftar akun baru, auto-login, dan diarahkan ke Laporan Saya.
     */
    public function test_citizen_can_register_successfully_and_is_redirected_to_home(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitForText('Daftar sebagai warga')
                ->clickLink('Daftar sebagai warga')
                ->waitForRoute('register')
                ->waitForText('Daftar sebagai warga')
                ->type('name', 'Warga Dusk Baru')
                ->type('email', 'warga.dusk@forestguard.test')
                ->type('password', 'Password123!')
                ->type('password_confirmation', 'Password123!')
                ->press('Daftar')
                ->waitForRoute('citizen.home')
                ->assertPathIs('/citizen/laporan')
                ->assertSee('Laporan Saya')
                ->assertSee('Warga Dusk Baru');
        });

        $this->assertDatabaseHas('users', [
            'email' => 'warga.dusk@forestguard.test',
            'name' => 'Warga Dusk Baru',
            'is_active' => true,
        ]);
    }

    /**
     * TC-PBI02-03: Validasi formulir registrasi menampilkan pesan kesalahan jika input tidak valid.
     */
    public function test_registration_validation_errors_displayed(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/register')
                ->waitForText('Daftar sebagai warga');

            // Nonaktifkan validasi HTML5 agar validasi server dapat diuji
            $browser->script("document.querySelectorAll('form').forEach(f => f.noValidate = true);");

            // 1. Password mismatch & nama terlalu pendek (< 3 karakter)
            $browser->type('name', 'Ab')
                ->type('email', 'invalid-email')
                ->type('password', '123')
                ->type('password_confirmation', 'different')
                ->press('Daftar')
                ->pause(1000)
                ->assertPathIs('/register')
                ->assertSee('Nama lengkap minimal 3 karakter.');

            // 2. Email duplikat (menggunakan email seeder yang sudah terdaftar)
            $browser->type('name', 'Warga Valid')
                ->type('email', 'citizen1@forestguard.test')
                ->type('password', 'Password123!')
                ->type('password_confirmation', 'Password123!')
                ->press('Daftar')
                ->pause(1000)
                ->assertPathIs('/register')
                ->assertSee('Email sudah terdaftar.');
        });
    }

    /**
     * TC-PBI02-04: Login sukses per peran (Admin, Ranger, Citizen) dan navigasi Logout.
     */
    public function test_role_logins_and_logout_work_via_helpers(): void
    {
        $this->browse(function (Browser $browser) {
            // 1. Admin login -> Dasbor -> Logout
            $this->loginAsAdmin($browser)
                ->assertPathIs('/admin/dashboard')
                ->assertSee('Dasbor')
                ->press('Keluar')
                ->waitForRoute('login')
                ->assertPathIs('/login');

            // 2. Ranger login -> Beranda Ranger -> Logout
            $this->loginAsRanger($browser)
                ->assertPathIs('/ranger')
                ->assertSee('Beranda Ranger')
                ->press('Keluar')
                ->waitForRoute('login')
                ->assertPathIs('/login');

            // 3. Citizen login -> Laporan Saya -> Logout
            $this->loginAsCitizen($browser)
                ->assertPathIs('/citizen/laporan')
                ->assertSee('Laporan Saya')
                ->press('Keluar')
                ->waitForRoute('login')
                ->assertPathIs('/login');
        });
    }

    /**
     * TC-PBI02-05: Kredensial tidak valid menampilkan pesan error identik untuk mencegah user enumeration.
     */
    public function test_login_with_invalid_credentials_shows_error(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            // Password salah untuk akun yang ada
            $browser->visit('/login')
                ->waitFor('input#email')
                ->type('email', 'citizen1@forestguard.test')
                ->type('password', 'SalahPassword123!')
                ->press('Masuk')
                ->waitForText('Email atau kata sandi salah')
                ->assertSee('Email atau kata sandi salah')
                ->assertPathIs('/login');

            // Email tidak terdaftar
            $browser->type('email', 'tidakada@forestguard.test')
                ->type('password', UserSeeder::DEV_PASSWORD)
                ->press('Masuk')
                ->waitForText('Email atau kata sandi salah')
                ->assertSee('Email atau kata sandi salah')
                ->assertPathIs('/login');
        });
    }

    /**
     * TC-PBI02-06: Akun yang dinonaktifkan ditolak saat login dengan pesan menghubungi Admin.
     */
    public function test_inactive_user_cannot_login(): void
    {
        User::where('email', 'citizen2@forestguard.test')->update(['is_active' => false]);

        $this->browse(function (Browser $browser) {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitFor('input#email')
                ->type('email', 'citizen2@forestguard.test')
                ->type('password', UserSeeder::DEV_PASSWORD)
                ->press('Masuk')
                ->waitForText('Akun Anda tidak aktif.')
                ->assertSee('Akun Anda tidak aktif. Hubungi Admin untuk mengaktifkan kembali.')
                ->assertPathIs('/login');
        });
    }

    /**
     * TC-PBI02-07: Penegakan RBAC membatasi akses URL lintas peran dan pengunjung tanpa login.
     */
    public function test_rbac_restricts_unauthorized_cross_role_access(): void
    {
        $this->browse(function (Browser $browser) {
            // 1. Citizen login mencoba membuka area Admin dan Ranger -> HTTP 403
            $this->loginAsCitizen($browser);

            $browser->visit('/admin/dashboard')
                ->waitForText('Akses ditolak')
                ->assertSee('HTTP 403');

            $browser->visit('/ranger')
                ->waitForText('Akses ditolak')
                ->assertSee('HTTP 403');

            $browser->press('Keluar');

            // 2. Guest mengakses rute terproteksi langsung dialihkan ke /login
            $browser->logout();
            $browser->visit('/admin/dashboard')
                ->assertPathIs('/login');

            $browser->visit('/ranger')
                ->assertPathIs('/login');

            $browser->visit('/citizen/laporan')
                ->assertPathIs('/login');
        });
    }
}
