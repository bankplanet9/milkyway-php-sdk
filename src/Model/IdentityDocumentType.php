<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/**
 * Identity document kind, sent as {@see PartyInfo::$identityDocumentType} — a closed
 * vocabulary, one-to-one with the IVMS101 national identifier types.
 */
enum IdentityDocumentType: string
{
    /** Passport (IVMS101 CCPT). */
    case Passport = 'passport';

    /** National identity card (IVMS101 IDCD). */
    case NationalId = 'national_id';

    /** Driver's license (IVMS101 DRLC). */
    case DriverLicense = 'driver_license';

    /** Residence permit (IVMS101 ARNU). */
    case ResidencePermit = 'residence_permit';

    /** Tax identification number (IVMS101 TXID). */
    case TaxId = 'tax_id';

    /** Social security number (IVMS101 SOCS). */
    case SocialSecurity = 'social_security';

    /** Any other document (IVMS101 MISC). */
    case Other = 'other';
}
