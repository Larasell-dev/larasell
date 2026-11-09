<?php

namespace Larasell\Larasell\Media\Actions;

/** The Mux account to use. A null secret keeps the one saved before. */
final readonly class UpdateMuxSettingsPayload
{
    public function __construct(
        public string $tokenId,
        public ?string $tokenSecret,
        public ?string $webhookSecret,
        public ?string $signingKeyId,
        public ?string $signingKey,
        public bool $useForVideos,
    ) {}
}
