<?php

namespace Larasell\Larasell\Media;

/**
 * Where a provider keeps a file: the provider's name in larasell.media.providers, the disk for
 * providers built on Laravel disks, and its reference there, a path or e.g. a Mux asset ID.
 */
final readonly class StoredFile
{
    /** @param  array<string, mixed>  $metadata  the provider's own, e.g. a Mux playback ID */
    public function __construct(
        public string $provider,
        public ?string $disk,
        public string $path,
        public AssetStatus $status = AssetStatus::Ready,
        public array $metadata = [],
    ) {}
}
