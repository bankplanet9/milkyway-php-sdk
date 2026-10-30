<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Throwable;

/**
 * HTTP 502 `ErrRecipientBankError` — the recipient bank's connector failed in a
 * way MilkyWay could not classify (precheck, pay or cancel). Nothing was created,
 * debited or cancelled; MilkyWay is alerted. Retry later or route the payment
 * through another service.
 *
 * Extends {@see MilkywayServiceUnavailableException}, so existing 5xx handlers
 * still catch it, and like every 5xx it is retried automatically first (except
 * `pay()` without an Idempotency-Key).
 */
final class MilkywayRecipientBankException extends MilkywayServiceUnavailableException
{
    public function __construct(string $message, ?string $responseBody = null, ?Throwable $previous = null)
    {
        parent::__construct(502, $message, $responseBody, $previous);
    }
}
