<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The Mux account was saved in the panel. Carries no credentials. */
final readonly class MuxSettingsUpdated
{
    use Dispatchable;

    public function __construct(public bool $useForVideos) {}
}
