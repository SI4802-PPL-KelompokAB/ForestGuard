<?php

// PBI-27: Halaman admin untuk melihat hasil pengelompokan insiden.

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi27IncidentClusteringTest extends DuskTestCase
{
    /** TC-PBI27-11: Admin membuka daftar, peta, dan ringkasan kelompok insiden. */
    public function test_admin_can_view_incident_clusters(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsAdmin()
                ->visitRoute('admin.incident-clusters.index')
                ->waitForText('Pengelompokan Insiden')
                ->assertSee('Kelompok laporan berdasarkan jarak koordinat menggunakan Haversine.')
                ->assertSee('Proses clustering')
                ->assertPresent('#incident-cluster-map')
                ->assertSee('CENTROID')
                ->assertSee('JUMLAH LAPORAN')
                ->pause(500);
        });
    }

    /** TC-PBI27-12: Ranger dan citizen tidak boleh membuka halaman admin. */
    public function test_non_admin_cannot_access_incident_clusters(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsRanger()
                ->visitRoute('admin.incident-clusters.index')
                ->assertSee('403')
                ->loginAsCitizen()
                ->visitRoute('admin.incident-clusters.index')
                ->assertSee('403');
        });
    }
}
