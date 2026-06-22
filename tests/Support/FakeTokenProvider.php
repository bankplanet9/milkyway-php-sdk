<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests\Support;

use Bankplanet9\MilkywayPayments\Auth\AccessTokenProviderInterface;

/** Token provider stub returning a fixed token and counting forced refreshes. */
final class FakeTokenProvider implements AccessTokenProviderInterface
{
    public int $callCount = 0;
    public int $forceRefreshCount = 0;

    public function __construct(public string $token = 'test-token')
    {
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        $this->callCount++;
        if ($forceRefresh) {
            $this->forceRefreshCount++;
        }
        return $this->token;
    }
}
