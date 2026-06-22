<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Throwable;

/** HTTP 5xx — the API or a downstream recipient service is unavailable. */
final class MilkywayServiceUnavailableException extends MilkywayApiException
{
    public function __construct(int $statusCode, string $message, ?string $responseBody = null, ?Throwable $previous = null)
    {
        parent::__construct($statusCode, $message, $responseBody, $previous);
    }
}
