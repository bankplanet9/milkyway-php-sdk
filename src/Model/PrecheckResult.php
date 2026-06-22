<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;
use Brick\Math\BigDecimal;

/**
 * Quote for a prospective payment. The FX markup is already applied and is
 * locked onto the transaction if you proceed to `pay`.
 */
final class PrecheckResult
{
    /**
     * @param BigDecimal $amountCredit Amount in the credit currency.
     * @param BigDecimal $amountDebit  Amount the recipient bank is debited, in the debit currency.
     * @param BigDecimal $rate         Quoted exchange rate: 1 CREDIT = rate DEBIT.
     * @param BigDecimal $commission   Commission charged, in the credit currency.
     */
    public function __construct(
        public readonly ?string $thirdPartyId,
        public readonly ?string $serviceId,
        public readonly ?string $recipientId,
        public readonly BigDecimal $amountCredit,
        public readonly ?string $currencyCredit,
        public readonly BigDecimal $amountDebit,
        public readonly ?string $currencyDebit,
        public readonly BigDecimal $rate,
        public readonly BigDecimal $commission,
    ) {
    }

    /**
     * Build from a raw response body, reading monetary fields from their literal
     * JSON tokens so precision is preserved.
     */
    public static function fromJson(string $body): self
    {
        $obj = Json::decodeObject($body);

        return new self(
            self::stringOrNull($obj, 'third_party_id'),
            self::stringOrNull($obj, 'service_id'),
            self::stringOrNull($obj, 'recipient_id'),
            self::decimal($body, 'amount_credit'),
            self::stringOrNull($obj, 'currency_credit'),
            self::decimal($body, 'amount_debit'),
            self::stringOrNull($obj, 'currency_debit'),
            self::decimal($body, 'rate'),
            self::decimal($body, 'commission'),
        );
    }

    /** @param array<string, mixed> $obj */
    private static function stringOrNull(array $obj, string $key): ?string
    {
        return isset($obj[$key]) && is_string($obj[$key]) ? $obj[$key] : null;
    }

    private static function decimal(string $body, string $key): BigDecimal
    {
        $literal = Json::decimalString($body, $key);
        return $literal === null ? BigDecimal::zero() : BigDecimal::of($literal);
    }
}
