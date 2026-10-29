<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

/** Kind of payment party, sent as {@see PartyInfo::$partyType}. */
enum PartyType: string
{
    /** A natural person. */
    case Natural = 'natural';

    /** A legal entity. Part of the contract, but not accepted by the API yet. */
    case Legal = 'legal';
}
