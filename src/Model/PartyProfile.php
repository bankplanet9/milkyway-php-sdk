<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/**
 * Which {@see PartyInfo} fields a service's recipient bank requires and accepts — the
 * same profile the bank enforces at precheck and pay. A party object the profile does
 * not list needs no fields.
 */
final class PartyProfile
{
    /**
     * @param PartyRequirements|null $senderInfo    Requirements for `senderInfo` (your customer), when listed.
     * @param PartyRequirements|null $recipientInfo Requirements for `recipientInfo` (the beneficiary), when listed.
     */
    public function __construct(
        public readonly ?PartyRequirements $senderInfo = null,
        public readonly ?PartyRequirements $recipientInfo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $obj A single party-profile object from a decoded response.
     */
    public static function fromArray(array $obj): self
    {
        return new self(self::requirements($obj, 'sender_info'), self::requirements($obj, 'recipient_info'));
    }

    /** @param array<string, mixed> $obj */
    private static function requirements(array $obj, string $key): ?PartyRequirements
    {
        if (!isset($obj[$key]) || !is_array($obj[$key])) {
            return null;
        }
        /** @var array<string, mixed> $requirements */
        $requirements = $obj[$key];
        return PartyRequirements::fromArray($requirements);
    }
}
