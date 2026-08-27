<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\WebpConverterInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizeIconsToWebp extends Command
{
    protected $signature = 'iconos:optimize-webp
        {--dir=public/images/iconos : Directory containing root-level PNG icons}
        {--out=public/images/iconos_webp : Output directory for the generated WebP files}';

    protected $description = 'Convert root-level PNG icons to WebP (alpha preserved, originals kept, idempotent)';

    public function __construct(
        private readonly WebpConverterInterface $converter
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->converter->available()) {
            $this->error('No image conversion backend available (GD, Imagick or cwebp/convert CLI). Install one and retry.');

            return self::FAILURE;
        }

        $dir = $this->option('dir');
        $out = $this->option('out');

        if (! is_string($dir) || ! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return self::FAILURE;
        }

        if (! is_string($out)) {
            $this->error('Output directory option must be a string.');

            return self::FAILURE;
        }

        if (! $this->ensureWritableOutput($out)) {
            $this->error("Output directory is not writable: {$out}");

            return self::FAILURE;
        }

        $result = $this->process($dir, $out);

        $this->info("Backend: {$this->converter->backend()}");
        $this->info("Converted: {$result['converted']}");
        $this->info("Skipped: {$result['skipped']}");
        $this->info("Errors: {$result['errors']}");

        return self::SUCCESS;
    }

    /**
     * Convierte los PNG de la raíz de $dir (sin tocar subdirectorios) a $out.
     * Idempotente: si el .webp ya existe EN LA SALIDA, no reconvierte.
     * Los PNG originales se conservan siempre.
     *
     * @return array{converted: int, skipped: int, errors: int}
     */
    public function process(string $dir, string $out): array
    {
        $result = ['converted' => 0, 'skipped' => 0, 'errors' => 0];

        if (! $this->ensureWritableOutput($out)) {
            return $result;
        }

        $pngs = glob($dir.'/*.png');

        if ($pngs === false) {
            return $result;
        }

        foreach ($pngs as $png) {
            $webp = $out.'/'.basename((string) preg_replace('/\.png$/i', '.webp', $png));

            if (file_exists($webp)) {
                $result['skipped']++;

                continue;
            }

            if ($this->converter->convert($png, $webp)) {
                $result['converted']++;
            } else {
                $result['errors']++;
            }
        }

        return $result;
    }

    private function ensureWritableOutput(string $out): bool
    {
        if (! is_dir($out) && ! File::makeDirectory($out, 0755, true, true)) {
            return false;
        }

        return is_writable($out);
    }
}
