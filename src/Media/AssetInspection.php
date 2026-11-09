<?php

namespace Larasell\Larasell\Media;

/**
 * What a provider found out about a file: by checking it, or from its webhook. Null leaves what
 * the asset knows as it is.
 */
final readonly class AssetInspection
{
    /** @param  array<string, mixed>  $metadata  merged into the asset's */
    public function __construct(
        public AssetStatus $status,
        public ?string $path = null,
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $checksum = null,
        public array $metadata = [],
        public ?string $failure = null,
    ) {}

    public static function failed(string $reason): self
    {
        return new self(AssetStatus::Failed, failure: $reason);
    }
}
