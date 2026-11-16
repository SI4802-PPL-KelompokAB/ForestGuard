<?php

namespace Tests\Browser;

use App\Models\Patrol;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi25LocationPickerTest extends DuskTestCase
{
    public function test_map_starts_without_default_marker_and_shows_pin_after_map_click(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.create')
                ->waitFor('.leaflet-container', 15)
                ->assertPresent('.leaflet-container')
                ->assertMissing('.leaflet-marker-icon')
                ->assertInputValue('#latitude', '')
                ->assertInputValue('#longitude', '');

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                picker.__locationMap.setView([-6.7890000, 107.6540000], 15);
                picker.__locationMap.fire('click', {
                    latlng: { lat: -6.7890000, lng: 107.6540000 },
                });
            JS);

            $browser->assertPresent('.leaflet-marker-icon')
                ->assertInputValue('#latitude', '-6.7890000')
                ->assertInputValue('#longitude', '107.6540000');
        });
    }

    public function test_gps_and_manual_location_work_when_geolocation_is_unavailable_or_denied(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.create')
                ->waitFor('.leaflet-container', 15);

            $browser->script(<<<'JS'
                Object.defineProperty(navigator, 'geolocation', {
                    configurable: true,
                    value: {
                        getCurrentPosition: function (success) {
                            success({
                                coords: {
                                    latitude: -6.7890000,
                                    longitude: 107.6540000,
                                    accuracy: 18,
                                },
                            });
                        },
                    },
                });
            JS);

            $browser->press('Ambil Lokasi Saat Ini')
                ->waitUntil('document.querySelector("#latitude").value === "-6.7890000"', 15)
                ->assertInputValue('#latitude', '-6.7890000')
                ->assertInputValue('#longitude', '107.6540000');

            $browser->script(<<<'JS'
                Object.defineProperty(navigator, 'geolocation', {
                    configurable: true,
                    value: undefined,
                });
            JS);

            $browser->press('Ambil Lokasi Saat Ini')
                ->waitForText('Geolocation tidak didukung pada browser Anda.', 15);

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                picker.__locationMap.setView([-6.8000000, 107.6200000], 15);
                picker.__locationMap.fire('click', {
                    latlng: { lat: -6.8000000, lng: 107.6200000 },
                });
            JS);

            $browser->assertInputValue('#latitude', '-6.8000000')
                ->assertInputValue('#longitude', '107.6200000');

            $browser->script(<<<'JS'
                Object.defineProperty(navigator, 'geolocation', {
                    configurable: true,
                    value: {
                        getCurrentPosition: function (_, error) {
                            error({ message: 'Izin lokasi ditolak.' });
                        },
                    },
                });
            JS);

            $browser->press('Ambil Lokasi Saat Ini')
                ->waitForText('Izin lokasi ditolak.', 15);

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                picker.__locationMap.setView([-6.9000000, 107.6000000], 15);
                picker.__locationMap.fire('click', {
                    latlng: { lat: -6.9000000, lng: 107.6000000 },
                });
            JS);

            $browser->assertInputValue('#latitude', '-6.9000000')
                ->assertInputValue('#longitude', '107.6000000');
        });
    }

    public function test_dragging_pin_and_editing_coordinates_keep_marker_in_sync(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.create')
                ->waitFor('.leaflet-container', 15);

            $browser->script(<<<'JS'
                    const picker = document.querySelector('[data-location-picker]');
                    picker.__locationMap.setView([-6.7890000, 107.6540000], 15);
                    picker.__locationMap.fire('click', {
                        latlng: { lat: -6.7890000, lng: 107.6540000 },
                    });
            JS);

            $browser->script(<<<'JS'
                const latInput = document.querySelector('#latitude');
                const lngInput = document.querySelector('#longitude');
                latInput.value = '-6.7574000';
                lngInput.value = '107.6199200';
                latInput.dispatchEvent(new Event('input', { bubbles: true }));
                lngInput.dispatchEvent(new Event('input', { bubbles: true }));
            JS);

            $browser->waitUntil('Number(document.querySelector("#latitude").value) === -6.7574', 15)
                ->assertInputValue('#longitude', '107.6199200')
                ->assertScript(<<<'JS'
                    (() => {
                        const picker = document.querySelector('[data-location-picker]');
                        return Number(picker.__locationMarker.getLatLng().lat.toFixed(7)) === -6.7574
                            && Number(picker.__locationMarker.getLatLng().lng.toFixed(7)) === 107.61992;
                    })()
                JS);

            $browser->script(<<<'JS'
                window.dragStartLat = Number(document.querySelector('#latitude').value);
                window.dragStartLng = Number(document.querySelector('#longitude').value);
                document.querySelector('.leaflet-marker-icon').scrollIntoView({ block: 'center' });
            JS);

            $marker = $browser->driver->findElement(WebDriverBy::cssSelector('.leaflet-marker-icon'));
            $browser->driver->action()
                ->moveToElement($marker)
                ->clickAndHold()
                ->moveByOffset(30, 20)
                ->release()
                ->perform();

            $browser->assertScript(<<<'JS'
                (() => {
                    const picker = document.querySelector('[data-location-picker]');
                    return Math.abs(Number(document.querySelector('#latitude').value) - window.dragStartLat) > 0.000001
                        && Math.abs(Number(document.querySelector('#longitude').value) - window.dragStartLng) > 0.000001
                        && Number(document.querySelector('#latitude').value) === Number(picker.__locationMarker.getLatLng().lat.toFixed(7))
                        && Number(document.querySelector('#longitude').value) === Number(picker.__locationMarker.getLatLng().lng.toFixed(7));
                })()
            JS);

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                window.markerLatBeforeInvalidInput = picker.__locationMarker.getLatLng().lat;
                window.markerLngBeforeInvalidInput = picker.__locationMarker.getLatLng().lng;
                const latInput = document.querySelector('#latitude');
                latInput.value = 'bukan-angka';
                latInput.dispatchEvent(new Event('input', { bubbles: true }));
            JS);

            $browser->assertScript(<<<'JS'
                (() => {
                    const markerPosition = document.querySelector('[data-location-picker]').__locationMarker.getLatLng();
                    return markerPosition.lat === window.markerLatBeforeInvalidInput
                        && markerPosition.lng === window.markerLngBeforeInvalidInput;
                })()
            JS);
        });
    }

    public function test_ranger_incident_form_uses_location_picker_without_default_coordinates(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsRanger()
                ->visitRoute('ranger.incidents.create')
                ->waitFor('.leaflet-container', 15)
                ->assertPresent('.leaflet-container')
                ->assertMissing('.leaflet-marker-icon')
                ->assertInputValue('#latitude', '')
                ->assertInputValue('#longitude', '')
                ->assertSee('Pilih lokasi dengan pin pada peta atau ambil lokasi perangkat.');

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                picker.__locationMap.setView([-6.7890000, 107.6540000], 15);
                picker.__locationMap.fire('click', {
                    latlng: { lat: -6.7890000, lng: 107.6540000 },
                });
            JS);

            $browser->assertPresent('.leaflet-marker-icon')
                ->assertInputValue('#latitude', '-6.7890000')
                ->assertInputValue('#longitude', '107.6540000');
        });
    }

    public function test_ranger_observation_form_shows_empty_location_picker_and_selects_map_point_on_mobile(): void
    {
        $patrol = Patrol::query()
            ->whereHas('ranger', fn ($query) => $query->where('email', 'ranger1@forestguard.test'))
            ->where('status', 'Berlangsung')
            ->first();

        if ($patrol === null) {
            $this->markTestSkipped('Tidak ada patroli Berlangsung untuk ranger1@forestguard.test.');
        }

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAsRanger()
                ->resize(360, 800)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->scrollIntoView('[data-observation-details] summary')
                ->click('[data-observation-details] summary')
                ->waitFor('[data-location-picker] .leaflet-container', 15)
                ->assertPresent('[data-location-picker] .leaflet-container')
                ->assertMissing('[data-location-picker] .leaflet-marker-icon')
                ->assertInputValue('[data-location-lat]', '')
                ->assertInputValue('[data-location-lng]', '')
                ->assertSee('Pilih lokasi dengan pin pada peta atau ambil lokasi perangkat.')
                ->assertScript(<<<'JS'
                    (() => {
                        return document.documentElement.scrollWidth <= window.innerWidth;
                    })()
                JS);

            $browser->script(<<<'JS'
                const picker = document.querySelector('[data-location-picker]');
                picker.__locationMap.setView([-6.75740, 107.61992], 15);
                picker.__locationMap.fire('click', {
                    latlng: { lat: -6.75740, lng: 107.61992 },
                });
            JS);

            $browser->assertPresent('[data-location-picker] .leaflet-marker-icon')
                ->assertInputValue('[data-location-lat]', '-6.7574000')
                ->assertInputValue('[data-location-lng]', '107.6199200')
                ->assertScript(<<<'JS'
                    (() => {
                        return document.querySelector("[data-observation-form] [name=location_recorded_at]").value !== "";
                    })()
                JS);
        });
    }
}
