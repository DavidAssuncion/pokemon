<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Conversor PNG -> WebP con fallback de backends:
 * GD (imagewebp) -> Imagick -> CLI (cwebp / convert).
 *
 * Todos los backends preservan el canal alfa (transparencia).
 */
final class WebpConverter implements WebpConverterInterface
{
    public function available(): bool
    {
        return $this->gdAvailable() || $this->imagickAvailable() || $this->cliBinary() !== null;
    }

    public function backend(): string
    {
        if ($this->gdAvailable()) {
            return 'gd';
        }

        if ($this->imagickAvailable()) {
            return 'imagick';
        }

        return $this->cliBinary() ?? 'none';
    }

    public function convert(string $input, string $output): bool
    {
        if ($this->gdAvailable()) {
            return $this->convertWithGd($input, $output);
        }

        if ($this->imagickAvailable()) {
            return $this->convertWithImagick($input, $output);
        }

        $binary = $this->cliBinary();

        if ($binary !== null) {
            return $this->convertWithCli($binary, $input, $output);
        }

        throw new RuntimeException('No WebP conversion backend available (GD, Imagick or cwebp/convert CLI).');
    }

    private function gdAvailable(): bool
    {
        return function_exists('imagecreatefrompng') && function_exists('imagewebp');
    }

    private function imagickAvailable(): bool
    {
        return class_exists(\Imagick::class);
    }

    private function convertWithGd(string $input, string $output): bool
    {
        $image = imagecreatefrompng($input);

        if ($image === false) {
            return false;
        }

        try {
            imagepalettetotruecolor($image);
            imagesavealpha($image, true);

            return imagewebp($image, $output, 80);
        } finally {
            imagedestroy($image);
        }
    }

    private function convertWithImagick(string $input, string $output): bool
    {
        $image = new \Imagick($input);
        $image->setImageFormat('webp');
        $image->setOption('webp:alpha-quality', '100');
        $result = $image->writeImage($output);
        $image->clear();

        return $result;
    }

    private function convertWithCli(string $binary, string $input, string $output): bool
    {
        $command = $binary === 'cwebp'
            ? escapeshellarg($binary).' -q 80 '.escapeshellarg($input).' -o '.escapeshellarg($output)
            : escapeshellarg($binary).' '.escapeshellarg($input).' -quality 80 '.escapeshellarg($output);

        exec($command.' 2>&1', $outputLines, $exitCode);

        return $exitCode === 0 && file_exists($output);
    }

    private function cliBinary(): ?string
    {
        foreach (['cwebp', 'convert'] as $binary) {
            $output = [];
            exec('command -v '.$binary.' 2>/dev/null', $output, $exitCode);

            if ($exitCode === 0 && $output !== []) {
                return $binary;
            }
        }

        return null;
    }
}
