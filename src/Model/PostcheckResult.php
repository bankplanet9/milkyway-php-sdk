<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;
use Bankplanet9\MilkywayPayments\TransactionStatus;

/** Current status of a payment transaction. */
final class PostcheckResult
{
    public function __construct(
        public readonly int $transactionId,
        public readonly TransactionStatus $status,
        /**
         * Code of the recipient bank's reason for a terminal outcome, when it gave
         * one — compare against the {@see \Bankplanet9\MilkywayPayments\StatusReason}
         * constants. Never free text; null when no reason was given.
         */
        public readonly ?string $error = null,
    ) {
    }

    public static function fromJson(string $body): self
    {
        $obj = Json::decodeObject($body);

        $transactionId = isset($obj['transaction_id']) ? (int) $obj['transaction_id'] : 0;
        $statusCode = isset($obj['status']) ? (int) $obj['status'] : 0;
        $error = isset($obj['error']) && is_string($obj['error']) ? $obj['error'] : null;

        return new self($transactionId, TransactionStatus::from($statusCode), $error);
    }
}
