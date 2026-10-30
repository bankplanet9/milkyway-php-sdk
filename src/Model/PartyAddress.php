<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;

/** A postal address. Text values are at most 256 characters. */
final class PartyAddress
{
    /**
     * @param string|null $country       ISO 3166-1 alpha-2, upper case. Required when an address is sent.
     * @param list<string>|null $line    An address held as free text, one line per element (at most 7 lines).
     */
    public function __construct(
        public readonly ?string $country = null,
        public readonly ?string $city = null,
        public readonly ?string $street = null,
        public readonly ?string $building = null,
        public readonly ?string $postalCode = null,
        public readonly ?array $line = null,
    ) {
    }

    /**
     * The wire object with null and empty values left out; an empty array when
     * nothing is set.
     *
     * @return array<string, string|list<string>>
     */
    public function toArray(): array
    {
        return Json::withoutEmpty([
            'country' => $this->country,
            'city' => $this->city,
            'street' => $this->street,
            'building' => $this->building,
            'postal_code' => $this->postalCode,
            'line' => $this->line,
        ]);
    }
}
