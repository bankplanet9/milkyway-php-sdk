<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/**
 * A payout destination in the discovery catalog: one participant (recipient bank)
 * and the payout services it offers, aggregated by core across its adapters.
 */
final class Destination
{
    /**
     * @param string $thirdPartyId The participant's ThirdParty id — use it as `thirdPartyIdDebit` when paying.
     * @param string|null $partnerName Human-readable participant name.
     * @param string|null $country ISO 3166-1 alpha-2 country code (e.g. TJ).
     * @param list<ServiceCapability> $services Payout services the participant offers.
     */
    public function __construct(
        public readonly string $thirdPartyId,
        public readonly ?string $partnerName,
        public readonly ?string $country,
        public readonly array $services,
    ) {
    }

    /**
     * @param array<string, mixed> $obj A single destination object from a decoded response.
     */
    public static function fromArray(array $obj): self
    {
        $services = [];
        if (isset($obj['services']) && is_array($obj['services'])) {
            foreach ($obj['services'] as $service) {
                if (is_array($service)) {
                    /** @var array<string, mixed> $service */
                    $services[] = ServiceCapability::fromArray($service);
                }
            }
        }

        return new self(
            isset($obj['third_party_id']) && is_string($obj['third_party_id']) ? $obj['third_party_id'] : '',
            isset($obj['partner_name']) && is_string($obj['partner_name']) ? $obj['partner_name'] : null,
            isset($obj['country']) && is_string($obj['country']) ? $obj['country'] : null,
            $services,
        );
    }

    /**
     * Parse the discovery list response body (`{"destinations": [...]}`) into a
     * list of destinations. A missing or empty catalog yields an empty list.
     *
     * @return list<self>
     */
    public static function listFromJson(string $body): array
    {
        $obj = \Bankplanet9\MilkywayPayments\Internal\Json::decodeObject($body);

        $destinations = [];
        if (isset($obj['destinations']) && is_array($obj['destinations'])) {
            foreach ($obj['destinations'] as $destination) {
                if (is_array($destination)) {
                    /** @var array<string, mixed> $destination */
                    $destinations[] = self::fromArray($destination);
                }
            }
        }
        return $destinations;
    }
}
