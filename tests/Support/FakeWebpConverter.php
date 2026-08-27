<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\WebpConverterInterface;

final class FakeWebpConverter implements WebpConverterInterface
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    public function __construct(private readonly bool $available)
    {
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function backend(): string
    {
        return 'fake';
    }

    public function convert(string $input, string $output): bool
    {
        $this->calls[] = [$input, $output];
        file_put_contents($output, 'webp');

        return true;
    }
}
