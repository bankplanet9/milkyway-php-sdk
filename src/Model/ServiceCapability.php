<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/**
 * One payout service a destination offers, as reported by discovery: the payout
 * method, a human hint for the recipient identifier, and the currencies the
 * recipient can be credited in.
 *
 * The `data` JSON Schema is intentionally NOT carried here — the discovery list
 * omits it; fetch it on demand via {@see \Bankplanet9\MilkywayPayments\MilkywayPaymentsClient::serviceSchema()}.
 */
final class ServiceCapability
{
    /**
     * @param string $serviceId    Service id (e.g. card-payout).
     * @param string $payoutMethod Payout method the service delivers over.
     * @param string|null $recipient A human description of the recipient identifier the sender supplies.
     * @param list<string>|null $payoutCurrencies Currencies the recipient can be credited in.
     */
    public function __construct(
        public readonly string $serviceId,
        public readonly string $payoutMethod,
        public readonly ?string $recipient = null,
        public readonly ?array $payoutCurrencies = null,
    ) {
    }

    /**
     * @param array<string, mixed> $obj A single service-capability object from a decoded response.
     */
    public static function fromArray(array $obj): self
    {
        return new self(
            isset($obj['service_id']) && is_string($obj['service_id']) ? $obj['service_id'] : '',
            isset($obj['payout_method']) && is_string($obj['payout_method']) ? $obj['payout_method'] : '',
            isset($obj['recipient']) && is_string($obj['recipient']) ? $obj['recipient'] : null,
            self::stringList($obj, 'payout_currencies'),
        );
    }

    /**
     * @param array<string, mixed> $obj
     * @return list<string>|null
     */
    private static function stringList(array $obj, string $key): ?array
    {
        if (!isset($obj[$key]) || !is_array($obj[$key])) {
            return null;
        }
        $out = [];
        foreach ($obj[$key] as $value) {
            if (is_string($value)) {
                $out[] = $value;
            }
        }
        return $out;
    }
}
