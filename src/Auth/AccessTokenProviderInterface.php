<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Auth;

/** Supplies bearer access tokens for the Payments API. */
interface AccessTokenProviderInterface
{
    /**
     * Return a valid access token. When $forceRefresh is true, any cached token is
     * discarded and a fresh one is acquired (used on a 401 refresh-and-replay).
     */
    public function getAccessToken(bool $forceRefresh = false): string;
}
