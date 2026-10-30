<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments;

/**
 * Lifecycle status of a payment transaction, mirroring the integer codes
 * returned by the Payments API (postcheck / cancel).
 */
enum TransactionStatus: int
{
    /** Accepted, not yet completed. Keep polling. */
    case Pending = 0;

    /** Completed successfully (terminal). */
    case Done = 1;

    /** Cancelled (terminal). */
    case Cancelled = 2;

    /** Failed (terminal). The result's `error` carries the reason code ({@see StatusReason}). */
    case Failed = 3;

    /** Cancellation requested, awaiting confirmation. */
    case CancelPending = 4;

    /** Timed out / stuck (terminal); requires operator attention. */
    case Stuck = 5;

    /**
     * True when the status will not change without external action —
     * Done, Cancelled, Failed, or Stuck. Polling should stop once a terminal
     * status is reached.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Done, self::Cancelled, self::Failed, self::Stuck => true,
            default => false,
        };
    }
}
