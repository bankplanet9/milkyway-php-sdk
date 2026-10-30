<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/**
 * Field requirements for one party object. Fields are named by their path within the
 * object — `name.first`, `name.last`, `name.full`, `date_of_birth`,
 * `identity_document_type`, `identity_document_number`, `identity_document_country`,
 * `country_of_residence`, `address.country`, `address.city`, `address.street`,
 * `address.building`, `address.postal_code`, `address.line`. A field in neither list
 * is not used by the bank.
 */
final class PartyRequirements
{
    /**
     * @param list<string>|null $required Fields a precheck or pay must carry; a missing one is
     *                                    refused with 400 naming it (e.g. sender_info.date_of_birth).
     * @param list<string>|null $accepted Fields the bank uses when present.
     * @param list<string>|null $identityDocumentTypes The {@see IdentityDocumentType} values the bank
     *                                    takes. Null means any; another kind is refused with 400.
     */
    public function __construct(
        public readonly ?array $required = null,
        public readonly ?array $accepted = null,
        public readonly ?array $identityDocumentTypes = null,
    ) {
    }

    /**
     * @param array<string, mixed> $obj A single party-requirements object from a decoded response.
     */
    public static function fromArray(array $obj): self
    {
        return new self(
            self::stringList($obj, 'required'),
            self::stringList($obj, 'accepted'),
            self::stringList($obj, 'identity_document_types'),
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
