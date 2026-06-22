<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

/**
 * HTTP 402 — rejected because the payment would breach a block-action exposure
 * limit (effective limit = configured limit + funded deposit balance).
 */
final class MilkywayExposureBlockedException extends MilkywayApiException
{
    public function __construct(string $message, ?string $responseBody = null)
    {
        parent::__construct(402, $message, $responseBody);
    }
}
