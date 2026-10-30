<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments;

/**
 * Codes the recipient bank may give as the reason for a terminal outcome, found
 * in {@see Model\PostcheckResult::$error}. The field stays a plain string so a
 * code added later by the API still reads; compare it against these constants.
 */
final class StatusReason
{
    /** The bank declined the payment (also any reason it gave without a code). */
    public const PAYMENT_REJECTED = 'ErrPaymentRejected';

    /** The bank does not know the recipient. */
    public const RECIPIENT_NOT_FOUND = 'ErrRecipientNotFound';

    /** The bank considers the recipient identifier invalid. */
    public const RECIPIENT_INVALID = 'ErrRecipientInvalid';

    /** The bank's service was unavailable. */
    public const SERVICE_UNAVAILABLE = 'ErrServiceUnavailable';

    /** The bank refused to cancel the payment. */
    public const PAYMENT_CANNOT_BE_CANCELED = 'ErrPaymentCannotBeCanceled';

    private function __construct()
    {
    }
}
