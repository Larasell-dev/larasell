<?php

namespace Larasell\Larasell\Media;

/**
 * Checks the Mux-Signature header of a webhook: "t=<unix time>,v1=<HMAC-SHA256 of "t.body">",
 * made with the webhook's signing secret, and no older than the tolerance, so a captured request
 * can't be replayed later.
 */
final readonly class MuxWebhookSignature
{
    public function __construct(private string $secret, private int $toleranceSeconds = 300) {}

    public function valid(string $body, string $header, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($this->secret === '' || $timestamp === 0 || abs(($now ?? time()) - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
