<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Throwable;

/**
 * HTTP 401 — missing, malformed, or invalid access token (including failed
 * token acquisition).
 */
final class MilkywayAuthException extends MilkywayApiException
{
    public function __construct(string $message, ?string $responseBody = null, ?Throwable $previous = null)
    {
        parent::__construct(401, $message, $responseBody, $previous);
    }
}
