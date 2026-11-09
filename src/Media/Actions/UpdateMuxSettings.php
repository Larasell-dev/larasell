<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\Events\MuxSettingsUpdated;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\MuxSettings;

/** Saves the Mux account, which then wins over the config file's, and whether new videos go to Mux. */
final readonly class UpdateMuxSettings
{
    public function __construct(private MuxSettings $settings, private MediaProviders $providers) {}

    public function handle(UpdateMuxSettingsPayload $payload): void
    {
        $this->settings->save([
            'token_id' => $payload->tokenId,
            'token_secret' => $payload->tokenSecret,
            'webhook_secret' => $payload->webhookSecret,
            'signing_key_id' => $payload->signingKeyId,
            'signing_key' => $payload->signingKey,
            'use_for_videos' => $payload->useForVideos,
        ]);

        // The next upload or webhook in this process uses the new account.
        $this->providers->forget(MuxSettings::PROVIDER);

        MuxSettingsUpdated::dispatch($payload->useForVideos);
    }
}
