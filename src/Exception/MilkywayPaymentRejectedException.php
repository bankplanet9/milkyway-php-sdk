<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

/**
 * HTTP 422 `ErrPaymentRejected` — the recipient bank refused the payment on its
 * merits (e.g. a limit or a blocked account) at precheck or pay. The request was
 * well formed, but repeating it will not change the answer, so it is never
 * retried. Nothing was created or debited; the bank's own reason is not disclosed.
 */
final class MilkywayPaymentRejectedException extends MilkywayApiException
{
    public function __construct(string $message, ?string $responseBody = null)
    {
        parent::__construct(422, $message, $responseBody);
    }
}
