<?php

namespace Larasell\Larasell\Audit\Chain;

/**
 * The result of checking the audit log's chain. A broken chain is a result too, not an error, so
 * nothing retries the check until it happens to pass.
 *
 * @internal
 */
final readonly class Verification
{
    /**
     * @param  ?int  $seq  The last record checked when passed, the one the chain broke at otherwise.
     * @param  ?string  $reason  missing_event, hash_mismatch, link_mismatch or head_mismatch.
     */
    private function __construct(
        public bool $passed,
        public ?int $seq,
        public ?string $hash,
        public ?string $reason,
        public int $verified,
    ) {}

    public static function passed(?int $seq, ?string $hash, int $verified): self
    {
        return new self(true, $seq, $hash, null, $verified);
    }

    public static function broken(int $seq, string $reason, int $verified): self
    {
        return new self(false, $seq, null, $reason, $verified);
    }
}
