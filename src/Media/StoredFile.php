<?php

namespace Larasell\Larasell\Media;

/** Where MediaStorage put a file: a disk, or another storage's own name for itself, and the path in it. */
final readonly class StoredFile
{
    public function __construct(public string $disk, public string $path) {}
}
