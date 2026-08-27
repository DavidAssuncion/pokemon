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
    public function test_process_converts_only_root_pngs_and_is_idempotent(): void
    {
        $dir = sys_get_temp_dir().'/iconos-optimize-'.uniqid();
        mkdir($dir.'/subdir', 0777, true);

        copy(public_path('images/iconos/1.png'), $dir.'/1.png');
        copy(public_path('images/iconos/2.png'), $dir.'/2.png');
        copy(public_path('images/iconos/1.png'), $dir.'/subdir/3.png');

        try {
            $converter = new FakeWebpConverter(true);
            $command = new OptimizeIconsToWebp($converter);

            $first = $command->process($dir);
            $this->assertSame(['converted' => 2, 'skipped' => 0, 'errors' => 0], $first);
            $this->assertFileExists($dir.'/1.webp');
            $this->assertFileExists($dir.'/2.webp');
            $this->assertFileDoesNotExist($dir.'/subdir/3.webp');
            $this->assertCount(2, $converter->calls);

            $second = $command->process($dir);
            $this->assertSame(['converted' => 0, 'skipped' => 2, 'errors' => 0], $second);
        } finally {
            $rootFiles = glob($dir.'/*');

            if ($rootFiles !== false) {
                foreach ($rootFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            $subFiles = glob($dir.'/subdir/*');

            if ($subFiles !== false) {
                foreach ($subFiles as $file) {
                    unlink($file);
                }
            }

            rmdir($dir.'/subdir');
            rmdir($dir);
        }
    }

    public function test_command_reports_converted_counts(): void
    {
        $dir = sys_get_temp_dir().'/iconos-optimize-'.uniqid();
        mkdir($dir);
        copy(public_path('images/iconos/1.png'), $dir.'/1.png');

        try {
            $converter = new FakeWebpConverter(true);
            $this->app->instance(WebpConverterInterface::class, $converter);

            $this->artisan('iconos:optimize-webp', ['--dir' => $dir])
                ->expectsOutput('Converted: 1')
                ->expectsOutput('Skipped: 0')
                ->expectsOutput('Errors: 0')
                ->assertExitCode(0);

            $this->assertFileExists($dir.'/1.webp');
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

    public function test_command_returns_failure_without_backend(): void
    {
        $converter = new WebpConverter();

        if ($converter->available()) {
            $this->markTestSkipped('A conversion backend is available; failure path not applicable here.');
        }

        $this->artisan('iconos:optimize-webp')->assertExitCode(1);
    }

    public function test_htaccess_sets_cache_headers(): void
    {
        $path = public_path('images/iconos/.htaccess');

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('max-age=31536000', $content);
        $this->assertStringContainsString('immutable', $content);
        $this->assertStringContainsString('webp', $content);
    }
}
