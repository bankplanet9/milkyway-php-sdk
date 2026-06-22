<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

/** HTTP 404 — transaction not found, or not owned by your institution. */
final class MilkywayNotFoundException extends MilkywayApiException
{
    public function __construct(string $message, ?string $responseBody = null)
    {
        parent::__construct(404, $message, $responseBody);
    }
}
