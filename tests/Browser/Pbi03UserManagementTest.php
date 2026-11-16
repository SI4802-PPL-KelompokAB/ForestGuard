<?php

namespace Tests\Browser;

use App\Models\Role;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * PBI-03 Manajemen Pengguna (Admin) - Laravel Dusk.
 *
 * Prasyarat:
 *  - .env.dusk.local: MAIL_MAILER=log (atau array), APP_ENV=testing/local
 *  - DatabaseSeeder membuat roles + user fixture (13 user: 8 gmail + 5 forestguard.test), antara lain:
 *      admin@forestguard.test   (Admin Hutan)   -> admin
 *      ranger1@forestguard.test (Ranger Satu)   -> ranger
 *      citizen1@forestguard.test(Warga Satu)    -> citizen
 *  - Jalankan: php artisan dusk --filter=Pbi03UserManagementTest
 *
 * Strategi locator: baris tabel selalu dicari lewat EMAIL (unik) setelah halaman
 * difilter ?q=<email>, sehingga tidak terpengaruh paginasi (10 baris/halaman)
 * maupun nama yang mirip.
 *
 * Catatan: TC-PBI03-02b (login dengan sandi dari email), Mail::fake(), request
 * DELETE langsung ke ID sendiri, dan DB::transaction sebaiknya diuji lewat
 * Feature test, bukan Dusk.
 */
class Pbi03UserManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    private const PASSWORD_SEEDER = 'Password123!'; // UserSeeder::DEV_PASSWORD
    private const LOGIN_BUTTON    = 'Masuk';        // teks tombol di halaman login

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // DatabaseSeeder -> roles + user fixture
    }

    // ---------- Helper ----------

    private function admin(): User
    {
        return User::where('email', 'admin@forestguard.test')->firstOrFail();
    }

    private function loginAsAdminUser(Browser $browser): Browser
    {
        return $browser->loginAs($this->admin());
    }

    /** Buka daftar pengguna terfilter agar hanya baris target yang tampil. */
    private function visitFiltered(Browser $browser, string $email, string $status = ''): Browser
    {
        $query = ['q' => $email];
        if ($status !== '') {
            $query['status'] = $status;
        }

        return $browser->visit('/admin/pengguna?' . http_build_query($query))
            ->waitForText($email);
    }

    private function actionElements(Browser $browser, string $email, string $label): array
    {
        $xpath = "//tr[contains(normalize-space(.), '{$email}')]"
            . "//*[(self::a or self::button or self::input) "
            . "and (contains(normalize-space(.), '{$label}') or @value='{$label}')]";

        return $browser->driver->findElements(WebDriverBy::xpath($xpath));
    }

    /** Klik tombol/link aksi (Ubah/Hapus/Pulihkan) pada baris dengan email $email. */
    private function clickRowAction(Browser $browser, string $email, string $label): void
    {
        $els = $this->actionElements($browser, $email, $label);
        $this->assertNotEmpty($els, "Tombol {$label} tidak ditemukan pada baris {$email}");
        $els[0]->click();
    }

    /** True hanya jika tombol ada DAN terlihat oleh pengguna. */
    private function rowActionVisible(Browser $browser, string $email, string $label): bool
    {
        foreach ($this->actionElements($browser, $email, $label) as $el) {
            if ($el->isDisplayed()) {
                return true;
            }
        }

        return false;
    }

    /** Matikan validasi HTML5 agar validasi server (HTTP 422) bisa diuji. */
    private function disableHtml5Validation(Browser $browser): void
    {
        $browser->script("document.querySelectorAll('form').forEach(f => f.noValidate = true);");
    }

    // ---------- TC-PBI03-01 : Index ----------

    /** TC-PBI03-01a */
    public function test_admin_can_see_user_index(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                ->assertSee('Kelola akun admin, ranger, dan warga.')
                ->assertSeeLink('Tambah akun')
                ->assertSee('Admin Hutan')
                ->assertSee('Ranger Satu')
                ->assertSee('Warga Satu')
                ->assertSee('Aktif');
        });
    }

    /** TC-PBI03-01d : empty state */
    public function test_search_with_no_result_shows_empty_state(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna?q=NamaTidakAda')
                ->waitForText('Tidak ada pengguna yang sesuai filter.')
                ->assertDontSee('Ranger Satu');
        });
    }

    // ---------- TC-PBI03-02 : Create ----------

    /** TC-PBI03-02a */
    public function test_admin_can_create_new_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                ->clickLink('Tambah akun')
                ->waitForRoute('admin.users.create')
                ->type('name', 'Ranger Dusk Baru')
                ->type('email', 'ranger.dusk@forestguard.test')
                ->select('role', Role::RANGER)
                ->press('Tambah akun')
                ->waitForRoute('admin.users.index')
                ->assertSee('Akun berhasil dibuat. Kata sandi sementara telah dikirim ke email.');
        });

        $this->assertDatabaseHas('users', [
            'email'     => 'ranger.dusk@forestguard.test',
            'is_active' => true,
        ]);
    }

    // ---------- TC-PBI03-03 : Validasi input ----------

    /** TC-PBI03-03a : form kosong */
    public function test_create_user_with_empty_form_shows_errors(): void
    {
        $usersBefore = User::count();

        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna/buat')
                ->waitFor('form');

            $this->disableHtml5Validation($browser);

            $browser->press('Tambah akun')
                ->pause(1500) // beri waktu server memproses; validasi gagal -> tetap di form
                ->assertPathIs('/admin/pengguna/buat');
        });

        $this->assertSame($usersBefore, User::count());
    }

    /** TC-PBI03-03b : format salah */
    public function test_create_user_with_invalid_format_shows_errors(): void
    {
        $usersBefore = User::count();

        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna/buat')
                ->waitFor('form')
                ->type('name', 'ab')
                ->type('email', 'bukan-email');

            $this->disableHtml5Validation($browser);

            // role tidak valid disuntik lewat JS karena <select> hanya berisi opsi sah
            $browser->script(
                "var s=document.querySelector('[name=role]');"
                . "var o=document.createElement('option');o.value='invalid_role';o.text='x';"
                . "s.appendChild(o);s.value='invalid_role';"
            );

            $browser->press('Tambah akun')
                ->waitFor('.text-red-600, .invalid-feedback, [role=alert]') // sesuaikan class error Anda
                ->assertPathIs('/admin/pengguna/buat');
        });

        $this->assertSame($usersBefore, User::count());
    }

    // ---------- TC-PBI03-04 : Email unik ----------

    /** TC-PBI03-04a */
    public function test_create_user_with_duplicate_email_is_rejected(): void
    {
        $usersBefore = User::count();

        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna/buat')
                ->type('name', 'Duplikat')
                ->type('email', 'admin@forestguard.test')
                ->select('role', Role::RANGER)
                ->press('Tambah akun')
                ->waitForText('Email sudah')
                ->assertInputValue('name', 'Duplikat'); // input valid dipertahankan
        });

        $this->assertSame($usersBefore, User::count());
    }

    // ---------- TC-PBI03-05 : Filter & cari ----------

    /** TC-PBI03-05a : filter peran */
    public function test_admin_can_filter_by_role(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                ->select('peran', Role::RANGER)
                ->press('Terapkan')
                ->waitForText('Ranger Satu')
                ->assertDontSee('Warga Satu');
        });
    }

    /** TC-PBI03-05b : pencarian parsial */
    public function test_admin_can_search_by_name(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna')
                ->type('q', 'Satu')
                ->press('Terapkan')
                ->waitForText('Ranger Satu')
                ->within('table', function (Browser $table) {
                    $table->assertSee('Warga Satu')
                        ->assertDontSee('Admin Hutan');
                });
        });
    }

    // ---------- TC-PBI03-06 : Update ----------

    /** TC-PBI03-06a */
    public function test_admin_can_update_user_and_deactivate(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser);
            $this->visitFiltered($browser, 'ranger1@forestguard.test');

            $this->clickRowAction($browser, 'ranger1@forestguard.test', 'Ubah');

            $browser->waitForText('Ubah pengguna')
                ->type('name', 'Ranger Satu Edit')
                ->select('is_active', '0') // edit.blade.php pakai <select>, bukan checkbox
                ->press('Simpan perubahan')
                ->waitForRoute('admin.users.index')
                ->assertSee('Pengguna berhasil diperbarui.');
        });

        $this->assertDatabaseHas('users', [
            'email'     => 'ranger1@forestguard.test',
            'name'      => 'Ranger Satu Edit',
            'is_active' => false,
        ]);
    }

    /** TC-PBI03-06b : akun nonaktif tidak bisa login */
    public function test_inactive_user_cannot_login(): void
    {
        User::where('email', 'ranger1@forestguard.test')->update(['is_active' => false]);

        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->type('email', 'ranger1@forestguard.test')
                ->type('password', self::PASSWORD_SEEDER)
                ->press(self::LOGIN_BUTTON)
                ->waitForText('Akun Anda tidak aktif.')
                ->assertPathIs('/login');
        });
    }

    // ---------- TC-PBI03-07 / 09 : Soft delete & restore ----------

    /** TC-PBI03-07a + TC-PBI03-09a */
    public function test_admin_can_soft_delete_and_restore_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser);
            $this->visitFiltered($browser, 'ranger1@forestguard.test');

            $this->clickRowAction($browser, 'ranger1@forestguard.test', 'Hapus');

            $browser->acceptDialog() // jika konfirmasi berupa modal custom, ganti dengan klik tombol modal
                ->waitForText('Pengguna berhasil dihapus.');

            $this->assertNotNull(
                User::withTrashed()->where('email', 'ranger1@forestguard.test')->first()->deleted_at
            );

            // muncul di filter "Terhapus", lalu dipulihkan
            $this->visitFiltered($browser, 'ranger1@forestguard.test', 'terhapus');
            $this->clickRowAction($browser, 'ranger1@forestguard.test', 'Pulihkan');

            $browser->waitForText('Pengguna berhasil dipulihkan.');
        });

        $this->assertNull(User::where('email', 'ranger1@forestguard.test')->first()->deleted_at);
    }

    // ---------- TC-PBI03-08 : Self-deletion protection ----------

    /** TC-PBI03-08a (sisi UI: tombol Hapus disembunyikan) */
    public function test_admin_cannot_delete_self(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser);

            $this->visitFiltered($browser, 'admin@forestguard.test');
            $this->assertTrue($this->rowActionVisible($browser, 'admin@forestguard.test', 'Ubah'));
            $this->assertFalse(
                $this->rowActionVisible($browser, 'admin@forestguard.test', 'Hapus'),
                'Tombol Hapus tampil pada baris akun admin yang sedang login (AC03-08)'
            );

            // baris akun lain tetap punya tombol Hapus
            $this->visitFiltered($browser, 'ranger1@forestguard.test');
            $this->assertTrue($this->rowActionVisible($browser, 'ranger1@forestguard.test', 'Hapus'));
        });
    }

    // ---------- TC-PBI03-10 : Otorisasi ----------

    /** TC-PBI03-10a : guest -> redirect login */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/admin/pengguna')
                ->assertPathIs('/login');
        });
    }

    /** TC-PBI03-10b : ranger -> 403 */
    public function test_ranger_gets_403(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('email', 'ranger1@forestguard.test')->firstOrFail())
                ->visit('/admin/pengguna')
                ->assertSee('403');
        });
    }

    /** TC-PBI03-10c : citizen -> 403 */
    public function test_citizen_gets_403(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('email', 'citizen1@forestguard.test')->firstOrFail())
                ->visit('/admin/pengguna/buat')
                ->assertSee('403');
        });
    }

    // ---------- TC-PBI03-11 : Responsif & keyboard ----------

    /** TC-PBI03-11a */
    public function test_layout_is_responsive(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)->visit('/admin/pengguna');

            foreach ([[360, 800], [1280, 800]] as [$w, $h]) {
                $browser->resize($w, $h)
                    ->waitForText('Manajemen pengguna')
                    ->assertSee('Ranger Satu');

                // halaman tidak boleh overflow horizontal (tabel scroll di dalam wrapper)
                $overflow = $browser->script(
                    'return document.documentElement.scrollWidth > document.documentElement.clientWidth;'
                )[0];
                $this->assertFalse($overflow, "Halaman overflow horizontal pada lebar {$w}px");
            }
        });
    }

    /** TC-PBI03-11b : navigasi keyboard */
    public function test_form_is_keyboard_navigable(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/pengguna/buat')
                ->waitFor('form')
                ->click('input[name=name]')
                ->keys('input[name=name]', 'Ranger Keyboard', '{tab}')
                ->keys('input[name=email]', 'keyboard@forestguard.test', '{tab}');

            $focused = $browser->script('return document.activeElement.name;')[0];
            $this->assertSame('role', $focused);
        });
    }
}
