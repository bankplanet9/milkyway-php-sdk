<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments;

/** Controls {@see MilkywayPaymentsClient::waitForCompletion()} polling. All durations are in seconds. */
final class PollOptions
{
    /**
     * @param float $initialDelay      Delay before the first re-poll. Default 1s.
     * @param float $maxDelay          Maximum delay between polls (backoff is capped here). Default 30s.
     * @param float $backoffMultiplier Multiplier applied to the delay after each poll. Default 2.0.
     * @param float $timeout           Overall polling budget in seconds. When exceeded, the last observed
     *                                 status is returned even if non-terminal. Default 300s (5 minutes).
     */
    public function __construct(
        public readonly float $initialDelay = 1.0,
        public readonly float $maxDelay = 30.0,
        public readonly float $backoffMultiplier = 2.0,
        public readonly float $timeout = 300.0,
    ) {
    }
}
