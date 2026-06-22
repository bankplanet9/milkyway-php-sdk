<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

/**
 * HTTP 400 — request validation failed (bad amount, missing field,
 * unresolvable FX rate, ...).
 */
final class MilkywayValidationException extends MilkywayApiException
{
    public function __construct(string $message, ?string $responseBody = null)
    {
        parent::__construct(400, $message, $responseBody);
    }
}
