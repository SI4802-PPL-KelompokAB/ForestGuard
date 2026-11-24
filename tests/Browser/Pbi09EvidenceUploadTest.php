<?php

namespace Tests\Browser;

use App\Models\Evidence;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use RuntimeException;
use Tests\DuskTestCase;

class Pbi09EvidenceUploadTest extends DuskTestCase
{
    private const JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AT//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AT//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2Q==';

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r9sAAAAASUVORK5CYII=';

    private ?string $temporaryDirectory = null;

    /** @var list<string> */
    private array $temporaryFiles = [];

    private string $validJpegPath;

    private string $largePngPath;

    private string $pdfPath;

    private string $executablePath;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('filesystems.evidence_disk') !== 'local') {
            throw new RuntimeException('Set EVIDENCE_DISK=local for Dusk evidence persistence tests.');
        }

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dusk-pbi09-'.Str::uuid();

        if (! mkdir($this->temporaryDirectory, 0700)) {
            throw new RuntimeException('Tidak dapat membuat direktori fixture sementara.');
        }

        $this->validJpegPath = $this->createJpegFixture();
        $this->largePngPath = $this->createLargePngFixture();
        $this->pdfPath = $this->writeTemporaryFile(
            'dokumen.pdf',
            "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"
        );
        $this->executablePath = $this->writeTemporaryFile(
            'script.exe',
            'MZ'.str_repeat("\0", 126).'DUSK-PBI09 test executable'
        );
    }

    protected function tearDown(): void
    {
        try {
            $failedPaths = [];

            foreach ($this->temporaryFiles as $path) {
                if (is_file($path) && ! unlink($path)) {
                    $failedPaths[] = $path;
                }
            }

            if (
                $this->temporaryDirectory !== null
                && is_dir($this->temporaryDirectory)
                && ! rmdir($this->temporaryDirectory)
            ) {
                $failedPaths[] = $this->temporaryDirectory;
            }

            if ($failedPaths !== []) {
                throw new RuntimeException(
                    'Tidak dapat menghapus fixture sementara: '.implode(', ', $failedPaths)
                );
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_tc_09_01_valid_jpg_is_selected_and_previewed_without_submission(): void
    {
        $this->browse(function (Browser $browser): void {
            $clientUuid = $this->prepareCitizenIncidentForm($browser, 'TC09-01');
            $before = $this->persistenceSnapshot();

            $browser->attach('#photo', $this->validJpegPath)
                ->waitUntil('document.querySelector("#photoPreview").classList.contains("hidden") === false', 15)
                ->assertVisible('#photoPreview')
                ->assertScript(<<<'JS'
                    document.querySelector('#photo').files.length === 1
                        && document.querySelector('#photo').files[0].name === 'bukti.jpg'
                        && document.querySelector('#photoPreview img').src.startsWith('data:image/jpeg')
                JS);

            $this->assertFalse(
                Incident::query()->where('client_uuid', $clientUuid)->exists(),
                'Laporan tidak boleh dibuat sebelum form dikirim.'
            );
            $this->assertSame($before, $this->persistenceSnapshot());
        });
    }

    public function test_tc_09_04_valid_submission_persists_report_and_private_evidence(): void
    {
        $this->browse(function (Browser $browser): void {
            $clientUuid = $this->prepareCitizenIncidentForm($browser, 'TC09-04');
            $before = $this->persistenceSnapshot();

            $browser->attach('#photo', $this->validJpegPath)
                ->press('Kirim Laporan')
                ->waitForText('Laporan berhasil terkirim', 15)
                ->assertSee('Laporan berhasil terkirim');

            $incident = Incident::query()->where('client_uuid', $clientUuid)->firstOrFail();
            $evidence = Evidence::query()->where('incident_id', $incident->id)->sole();

            $this->assertSame($clientUuid, $incident->client_uuid);
            $this->assertSame($incident->id, $evidence->incident_id);
            $after = $this->persistenceSnapshot();
            $this->assertSame([$evidence->file_path], array_values(array_diff($after['files'], $before['files'])));

            $photoSelector = 'img[src="'.route('evidence.show', $evidence).'"]';
            $browser->loginAsAdmin()
                ->visitRoute('admin.incidents.show', $incident)
                ->waitFor($photoSelector)
                ->waitUntil(
                    'document.querySelector('.json_encode($photoSelector, JSON_THROW_ON_ERROR).')?.naturalWidth > 0',
                    15,
                    'Foto bukti privat tidak berhasil dimuat pada detail kasus.',
                )
                ->assertVisible($photoSelector);
        });
    }

    public function test_tc_09_02_oversized_png_shows_server_error_without_persisting_data(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->submitInvalidPhotoAndAssertNoPersistence(
                $browser,
                $this->largePngPath,
                'TC09-02',
                'validation.uploaded'
            );
        });
    }

    public function test_tc_09_03_pdf_is_rejected_without_persisting_data(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->submitInvalidPhotoAndAssertNoPersistence(
                $browser,
                $this->pdfPath,
                'TC09-03-PDF',
                'File bukti harus berupa format gambar (jpeg, png, jpg).'
            );
        });
    }

    public function test_tc_09_03_executable_is_rejected_without_persisting_data(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->submitInvalidPhotoAndAssertNoPersistence(
                $browser,
                $this->executablePath,
                'TC09-03-EXE',
                'File bukti harus berupa format gambar (jpeg, png, jpg).'
            );
        });
    }

    private function prepareCitizenIncidentForm(Browser $browser, string $scenario): string
    {
        $browser->loginAsCitizen()
            ->visitRoute('citizen.incidents.create')
            ->waitFor('#photo', 15)
            ->waitFor('#latitude', 15)
            ->assertAttribute('#photo', 'accept', 'image/jpeg,image/png,image/jpg');

        $incidentTypeId = IncidentType::query()->value('id');
        $forestAreaId = ForestArea::query()->value('id');

        if ($incidentTypeId === null || $forestAreaId === null) {
            throw new RuntimeException('Data referensi laporan belum tersedia di database.');
        }

        $browser->radio('incident_type_id', (string) $incidentTypeId)
            ->select('forest_area_id', (string) $forestAreaId)
            ->type('description', 'DUSK-PBI09 '.$scenario.' uraian laporan yang valid untuk pengujian.');

        $browser->script(<<<'JS'
            const occurredAt = document.querySelector('#occurred_at');
            occurredAt.value = occurredAt.max;
            occurredAt.dispatchEvent(new Event('input', { bubbles: true }));

            const latitude = document.querySelector('#latitude');
            const longitude = document.querySelector('#longitude');
            latitude.value = '-6.7574000';
            longitude.value = '107.6199200';
            latitude.dispatchEvent(new Event('input', { bubbles: true }));
            longitude.dispatchEvent(new Event('input', { bubbles: true }));
        JS);

        $clientUuid = $browser->value('#client_uuid');

        if (! is_string($clientUuid) || $clientUuid === '') {
            throw new RuntimeException('Client UUID laporan tidak ditemukan pada form.');
        }

        return $clientUuid;
    }

    private function submitInvalidPhotoAndAssertNoPersistence(
        Browser $browser,
        string $filePath,
        string $scenario,
        string $expectedMessage
    ): void {
        $clientUuid = $this->prepareCitizenIncidentForm($browser, $scenario);
        $before = $this->persistenceSnapshot();

        $browser->attach('#photo', $filePath)
            ->press('Kirim Laporan')
            ->waitForText($expectedMessage, 15)
            ->assertSee($expectedMessage);

        $this->assertFalse(
            Incident::query()->where('client_uuid', $clientUuid)->exists(),
            'Laporan tidak boleh tersimpan ketika validasi foto gagal.'
        );
        $this->assertFalse(
            Evidence::query()
                ->whereIn(
                    'incident_id',
                    Incident::query()->select('id')->where('client_uuid', $clientUuid)
                )
                ->exists(),
            'Bukti foto tidak boleh tersimpan ketika validasi foto gagal.'
        );
        $this->assertSame($before, $this->persistenceSnapshot());
    }

    /**
     * @return array{incidents: int, evidences: int, files: list<string>}
     */
    private function persistenceSnapshot(): array
    {
        $files = Storage::disk('local')->allFiles('evidences');
        sort($files, SORT_STRING);

        return [
            'incidents' => Incident::query()->count(),
            'evidences' => Evidence::query()->count(),
            'files' => $files,
        ];
    }

    private function createJpegFixture(): string
    {
        $jpeg = base64_decode(self::JPEG_BASE64, true);

        if (! is_string($jpeg)) {
            throw new RuntimeException('Fixture JPEG tidak dapat didekode.');
        }

        $targetBytes = 2 * 1024 * 1024;

        if (strlen($jpeg) >= $targetBytes) {
            throw new RuntimeException('Fixture JPEG dasar terlalu besar.');
        }

        return $this->writeTemporaryFile(
            'bukti.jpg',
            $jpeg.str_repeat("\0", $targetBytes - strlen($jpeg))
        );
    }

    private function createLargePngFixture(): string
    {
        $png = base64_decode(self::PNG_BASE64, true);

        if (! is_string($png)) {
            throw new RuntimeException('Fixture PNG tidak dapat didekode.');
        }

        $iendOffset = strrpos($png, "\x00\x00\x00\x00IEND");

        if ($iendOffset === false) {
            throw new RuntimeException('Fixture PNG dasar tidak memiliki chunk IEND.');
        }

        $targetBytes = 6 * 1024 * 1024;
        $keyword = "Comment\0";
        $textLength = $targetBytes - strlen($png) - 12 - strlen($keyword);

        if ($textLength < 0) {
            throw new RuntimeException('Fixture PNG dasar terlalu besar.');
        }

        $chunkData = $keyword.str_repeat('A', $textLength);
        $chunkContents = 'tEXt'.$chunkData;
        $textChunk = pack('N', strlen($chunkData))
            .$chunkContents
            .pack('H*', hash('crc32b', $chunkContents));
        $largePng = substr($png, 0, $iendOffset).$textChunk.substr($png, $iendOffset);

        if (strlen($largePng) !== $targetBytes) {
            throw new RuntimeException('Ukuran fixture PNG tidak sesuai.');
        }

        return $this->writeTemporaryFile('foto_besar.png', $largePng);
    }

    private function writeTemporaryFile(string $filename, string $contents): string
    {
        if ($this->temporaryDirectory === null) {
            throw new RuntimeException('Direktori fixture sementara belum dibuat.');
        }

        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$filename;
        $this->temporaryFiles[] = $path;
        $writtenBytes = file_put_contents($path, $contents);

        if ($writtenBytes !== strlen($contents)) {
            throw new RuntimeException("Tidak dapat menulis fixture {$filename}.");
        }

        return $path;
    }
}
