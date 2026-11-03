<?php

namespace App\Services\Foundry;

use RuntimeException;

class FoundryFailure extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $failureCode,
        string $safeMessage,
        public readonly array $safeContext = [],
    ) {
        parent::__construct($safeMessage);
    }
}