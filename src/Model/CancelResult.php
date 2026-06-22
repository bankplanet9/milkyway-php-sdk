<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;
use Bankplanet9\MilkywayPayments\TransactionStatus;

/** Result of a cancellation request: the resulting transaction status. */
final class CancelResult
{
    public function __construct(
        public readonly TransactionStatus $status,
    ) {
    }

    public static function fromJson(string $body): self
    {
        $obj = Json::decodeObject($body);
        $statusCode = isset($obj['status']) ? (int) $obj['status'] : 0;

        return new self(TransactionStatus::from($statusCode));
    }
}
