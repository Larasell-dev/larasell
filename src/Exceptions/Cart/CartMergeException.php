<?php

namespace Larasell\Larasell\Exceptions\Cart;

final class CartMergeException extends CartException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly string $reason,
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason,
            ...$this->context,
        ];
    }
}
