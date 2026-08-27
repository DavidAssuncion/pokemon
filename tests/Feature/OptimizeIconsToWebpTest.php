<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\OptimizeIconsToWebp;
use App\Support\WebpConverter;
use App\Support\WebpConverterInterface;
use Tests\Support\FakeWebpConverter;
use Tests\TestCase;

class OptimizeIconsToWebpTest extends TestCase
{
    public function test_process_converts_only_root_pngs_into_out_and_is_idempotent(): void
    {
        $dir = sys_get_temp_dir().'/iconos-optimize-'.uniqid();
        $out = sys_get_temp_dir().'/iconos-webp-'.uniqid();
        mkdir($dir.'/subdir', 0777, true);

        copy(public_path('images/iconos/1.png'), $dir.'/1.png');
        copy(public_path('images/iconos/2.png'), $dir.'/2.png');
        copy(public_path('images/iconos/1.png'), $dir.'/subdir/3.png');

        try {
            $converter = new FakeWebpConverter(true);
            $command = new OptimizeIconsToWebp($converter);

            $first = $command->process($dir, $out);
            $this->assertSame(['converted' => 2, 'skipped' => 0, 'errors' => 0], $first);
            // Los WebP van SOLO a la carpeta de salida
            $this->assertFileExists($out.'/1.webp');
            $this->assertFileExists($out.'/2.webp');
            $this->assertFileDoesNotExist($out.'/subdir/3.webp');
            $this->assertFileDoesNotExist($dir.'/1.webp');
            // Los PNG originales se conservan y el subdir del input no se toca
            $this->assertFileExists($dir.'/1.png');
            $this->assertFileExists($dir.'/2.png');
            $this->assertFileExists($dir.'/subdir/3.png');
            $this->assertCount(2, $converter->calls);

            $second = $command->process($dir, $out);
            $this->assertSame(['converted' => 0, 'skipped' => 2, 'errors' => 0], $second);
        } finally {
            foreach ([$out, $dir] as $path) {
                $files = glob($path.'/*');

                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }

                $subFiles = glob($path.'/subdir/*');

                if ($subFiles !== false) {
                    foreach ($subFiles as $file) {
                        unlink($file);
                    }
                }

                if (is_dir($path.'/subdir')) {
                    rmdir($path.'/subdir');
                }

                if (is_dir($path)) {
                    rmdir($path);
                }
            }
        }
    }

    public function test_command_reports_converted_counts(): void
    {
        $dir = sys_get_temp_dir().'/iconos-optimize-'.uniqid();
        $out = sys_get_temp_dir().'/iconos-webp-'.uniqid();
        mkdir($dir);
        copy(public_path('images/iconos/1.png'), $dir.'/1.png');

        try {
            $converter = new FakeWebpConverter(true);
            $this->app->instance(WebpConverterInterface::class, $converter);

            $this->artisan('iconos:optimize-webp', ['--dir' => $dir, '--out' => $out])
                ->expectsOutput('Converted: 1')
                ->expectsOutput('Skipped: 0')
                ->expectsOutput('Errors: 0')
                ->assertExitCode(0);

            $this->assertFileExists($out.'/1.webp');
            $this->assertFileDoesNotExist($dir.'/1.webp');
        } finally {
            foreach ([$out, $dir] as $path) {
                $files = glob($path.'/*');

                if ($files !== false) {
                    foreach ($files as $file) {
                        unlink($file);
                    }
                }

                if (is_dir($path)) {
                    rmdir($path);
                }
            }
        }
    }

    public function test_real_conversion_with_cwebp(): void
    {
        $converter = new WebpConverter();

        if (! $converter->available()) {
            $this->markTestSkipped('No WebP conversion backend available (GD, Imagick or cwebp/convert CLI).');
        }

        $dir = sys_get_temp_dir().'/iconos-optimize-'.uniqid();
        $out = sys_get_temp_dir().'/iconos-webp-'.uniqid();
        mkdir($dir);
        copy(public_path('images/iconos/1.png'), $dir.'/1.png');

        try {
            $command = new OptimizeIconsToWebp($converter);

            $result = $command->process($dir, $out);

            $this->assertSame(['converted' => 1, 'skipped' => 0, 'errors' => 0], $result);
            $this->assertFileExists($out.'/1.webp');
            $this->assertGreaterThan(0, filesize($out.'/1.webp'));
            // El PNG original se conserva intacto
            $this->assertFileExists($dir.'/1.png');
        } finally {
            foreach ([$out, $dir] as $path) {
                $files = glob($path.'/*');

                if ($files !== false) {
                    foreach ($files as $file) {
                        unlink($file);
                    }
                }

                if (is_dir($path)) {
                    rmdir($path);
                }
            }
        }
    }

    public function test_command_returns_failure_without_backend(): void
    {
        $converter = new WebpConverter();

        if ($converter->available()) {
            $this->markTestSkipped('A conversion backend is available; failure path not applicable here.');
        }

        $this->artisan('iconos:optimize-webp')->assertExitCode(1);
    }

    public function test_htaccess_iconos_sets_cache_headers(): void
    {
        $path = public_path('images/iconos/.htaccess');

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('max-age=31536000', $content);
        $this->assertStringContainsString('immutable', $content);
        $this->assertStringContainsString('webp', $content);
    }

    public function test_htaccess_iconos_webp_sets_cache_headers(): void
    {
        $path = public_path('images/iconos_webp/.htaccess');

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('max-age=31536000', $content);
        $this->assertStringContainsString('immutable', $content);
        $this->assertStringContainsString('webp', $content);
    }
}
