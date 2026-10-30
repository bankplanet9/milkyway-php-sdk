<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Bankplanet9\MilkywayPayments\Internal\Json;
use Throwable;

/**
 * HTTP 5xx — the API or a downstream recipient service is unavailable (also a
 * network failure, with status 0). Retried automatically before it is thrown.
 *
 * A 503 from precheck, pay or cancel names the recipient service that could not
 * price, take or cancel the payment: {@see self::getServiceId()} and
 * {@see self::getThirdPartyId()}. A 502 is thrown as the subclass
 * {@see MilkywayRecipientBankException}, so catching this class covers it too.
 */
class MilkywayServiceUnavailableException extends MilkywayApiException
{
    private ?string $serviceId;

    private ?string $thirdPartyId;

    public function __construct(int $statusCode, string $message, ?string $responseBody = null, ?Throwable $previous = null)
    {
        parent::__construct($statusCode, $message, $responseBody, $previous);
        $this->serviceId = $responseBody !== null ? Json::extractStringField($responseBody, 'service_id') : null;
        $this->thirdPartyId = $responseBody !== null ? Json::extractStringField($responseBody, 'third_party_id') : null;
    }

    /** The recipient service that failed (`service_id` of a 503 body), when given. */
    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }

    /** The institution owning that service (`third_party_id` of a 503 body), when given. */
    public function getThirdPartyId(): ?string
    {
        return $this->thirdPartyId;
    }
}
