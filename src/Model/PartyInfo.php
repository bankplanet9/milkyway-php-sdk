<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;

/**
 * The canonical identity of a payment party — your customer (`sender_info`, the
 * originator) or the beneficiary (`recipient_info`). The shape is the same for every
 * recipient bank and service (IVMS101-anchored); the account, card or phone stays in
 * `senderId` / `recipientId`. Send what you have — which fields a service requires is
 * its {@see ServiceCapability::$partyProfile} from discovery.
 *
 * Null and empty values, including an empty name or address, are left off the wire;
 * the server refuses any key outside this model.
 */
final class PartyInfo
{
    /**
     * @param PartyType $partyType Always sent. {@see PartyType::Legal} is not accepted by the API yet.
     * @param string|null $dateOfBirth ISO 8601 date, YYYY-MM-DD (e.g. 1990-04-18).
     * @param IdentityDocumentType|null $identityDocumentType Required with $identityDocumentNumber.
     * @param string|null $identityDocumentNumber Series and number together, as written in the document.
     * @param string|null $identityDocumentCountry Issuing country, ISO 3166-1 alpha-2, upper case (e.g. TJ).
     * @param string|null $countryOfResidence ISO 3166-1 alpha-2, upper case (e.g. AE).
     * @param PartyAddress|null $address When sent, its country is required.
     */
    public function __construct(
        public readonly PartyType $partyType = PartyType::Natural,
        public readonly ?PartyName $name = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?IdentityDocumentType $identityDocumentType = null,
        public readonly ?string $identityDocumentNumber = null,
        public readonly ?string $identityDocumentCountry = null,
        public readonly ?string $countryOfResidence = null,
        public readonly ?PartyAddress $address = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::withoutEmpty([
            'party_type' => $this->partyType->value,
            'name' => $this->name?->toArray(),
            'date_of_birth' => $this->dateOfBirth,
            'identity_document_type' => $this->identityDocumentType?->value,
            'identity_document_number' => $this->identityDocumentNumber,
            'identity_document_country' => $this->identityDocumentCountry,
            'country_of_residence' => $this->countryOfResidence,
            'address' => $this->address?->toArray(),
        ]);
    }
}
