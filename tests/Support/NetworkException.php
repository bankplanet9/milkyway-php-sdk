<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/** A PSR-18 network exception for simulating transport-level failures in tests. */
final class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message = 'network down')
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
