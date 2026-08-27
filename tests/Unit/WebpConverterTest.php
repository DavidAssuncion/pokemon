<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WebpConverter;
use PHPUnit\Framework\TestCase;

class WebpConverterTest extends TestCase
{
    public function test_available_returns_boolean_and_backend_never_empty(): void
    {
        $converter = new WebpConverter();

        $this->assertNotSame('', $converter->backend());

        if ($converter->available()) {
            $this->assertContains($converter->backend(), ['gd', 'imagick', 'cwebp', 'convert']);
        } else {
            $this->assertSame('none', $converter->backend());
        }
    }

    public function test_convert_png_to_webp(): void
    {
        $converter = new WebpConverter();

        if (! $converter->available()) {
            $this->markTestSkipped('No GD/Imagick/CLI backend available for WebP conversion.');
        }

        $dir = sys_get_temp_dir().'/webp-converter-'.uniqid();
        mkdir($dir);

        try {
            $png = $dir.'/input.png';
            $webp = $dir.'/output.webp';
            // PNG 1x1 válido (base64) para no depender de GD para generarlo.
            file_put_contents($png, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                true
            ));

            $this->assertTrue($converter->convert($png, $webp));
            $this->assertFileExists($webp);
        } finally {
            $files = glob($dir.'/*');

            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            rmdir($dir);
        }
    }
}
