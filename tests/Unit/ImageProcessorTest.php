<?php

namespace Tests\Unit;

use App\Exceptions\InvalidImageException;
use App\Support\ImageProcessor;
use App\Support\ProcessedImage;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ImageProcessorTest extends TestCase
{
    private ImageProcessor $processor;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ImageProcessor;
        $this->fixturesDir = dirname(__DIR__).'/Fixtures';
    }

    public function test_extract_exif_from_gps_fixture_returns_correct_coordinates_and_timestamp(): void
    {
        $fixturePath = $this->fixturesDir.'/sample_with_gps.jpg';
        $this->assertFileExists($fixturePath);

        $exif = $this->processor->extractExif($fixturePath, 'image/jpeg');

        // Lat: -6.893 (6 deg 53 min 34.8 sec South)
        $this->assertNotNull($exif['latitude']);
        $this->assertEqualsWithDelta(-6.893, $exif['latitude'], 0.0001);

        // Lon: 108.407 (108 deg 24 min 25.2 sec East)
        $this->assertNotNull($exif['longitude']);
        $this->assertEqualsWithDelta(108.407, $exif['longitude'], 0.0001);

        // Timestamp: 2026:10:28 14:30:00
        $this->assertInstanceOf(CarbonImmutable::class, $exif['captured_at']);
        $this->assertSame('2026-10-28 14:30:00', $exif['captured_at']->format('Y-m-d H:i:s'));
    }

    public function test_extract_exif_from_png_safely_returns_null_metadata(): void
    {
        $fixturePath = $this->fixturesDir.'/sample_no_exif.png';
        $this->assertFileExists($fixturePath);

        $exif = $this->processor->extractExif($fixturePath, 'image/png');

        $this->assertNull($exif['latitude']);
        $this->assertNull($exif['longitude']);
        $this->assertNull($exif['captured_at']);
    }

    public function test_corrupt_image_file_throws_invalid_image_exception(): void
    {
        $fixturePath = $this->fixturesDir.'/corrupt_image.jpg';
        $this->assertFileExists($fixturePath);

        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('Berkas gambar tidak valid atau rusak.');

        $this->processor->process($fixturePath);
    }

    public function test_non_existent_file_throws_invalid_image_exception(): void
    {
        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('Berkas gambar tidak valid atau rusak.');

        $this->processor->process($this->fixturesDir.'/file_that_does_not_exist.jpg');
    }

    public function test_process_png_retains_png_mime_and_extension(): void
    {
        $fixturePath = $this->fixturesDir.'/sample_no_exif.png';

        $processed = $this->processor->process($fixturePath);

        try {
            $this->assertInstanceOf(ProcessedImage::class, $processed);
            $this->assertSame('image/png', $processed->mimeType);
            $this->assertSame('png', $processed->extension());
            $this->assertFileExists($processed->tempPath);
            $this->assertGreaterThan(0, $processed->fileSize);
        } finally {
            $processed->cleanup();
        }
    }

    public function test_process_jpg_retains_jpeg_mime_and_extension_with_exif_extracted(): void
    {
        $fixturePath = $this->fixturesDir.'/sample_with_gps.jpg';

        $processed = $this->processor->process($fixturePath);

        try {
            $this->assertInstanceOf(ProcessedImage::class, $processed);
            $this->assertSame('image/jpeg', $processed->mimeType);
            $this->assertSame('jpg', $processed->extension());
            $this->assertFileExists($processed->tempPath);
            $this->assertGreaterThan(0, $processed->fileSize);

            // EXIF extracted before compression
            $this->assertNotNull($processed->latitude);
            $this->assertEqualsWithDelta(-6.893, $processed->latitude, 0.0001);
            $this->assertNotNull($processed->longitude);
            $this->assertEqualsWithDelta(108.407, $processed->longitude, 0.0001);
            $this->assertInstanceOf(CarbonImmutable::class, $processed->capturedAt);
        } finally {
            $processed->cleanup();
        }
    }

    public function test_processed_image_cleanup_is_idempotent_and_safe(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_cleanup_');
        file_put_contents($tempFile, 'temporary_test_data');
        $this->assertFileExists($tempFile);

        $processed = new ProcessedImage(
            tempPath: $tempFile,
            mimeType: 'image/jpeg',
            fileSize: 19,
        );

        // First call removes file
        $processed->cleanup();
        $this->assertFileDoesNotExist($tempFile);

        // Second call is safe and does not throw any error
        $processed->cleanup();
        $this->assertFileDoesNotExist($tempFile);
    }
}
