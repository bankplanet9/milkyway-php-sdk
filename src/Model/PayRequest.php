<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Brick\Math\BigDecimal;

/**
 * Request to initiate a cross-bank payment. The credit party (your institution)
 * is resolved from the access token's `azp` claim — do not send
 * `third_party_id_credit`.
 */
final class PayRequest
{
    /**
     * @param string $thirdPartyIdDebit Recipient bank id.
     * @param string $serviceId         Service id (e.g. card-payout).
     * @param string $senderId          Sender identifier within the chosen service.
     * @param string $recipientId       Recipient identifier within the chosen service.
     * @param BigDecimal $amountCredit  Amount in the credit currency (must be > 0).
     * @param string $currencyCredit    Credit currency (ISO-4217), e.g. USD.
     * @param array<string, mixed>|null $data Service-specific payload validated server-side
     *                                  against the service's JSON Schema. Omitted from the wire when null.
     */
    public function __construct(
        public readonly string $thirdPartyIdDebit,
        public readonly string $serviceId,
        public readonly string $senderId,
        public readonly string $recipientId,
        public readonly BigDecimal $amountCredit,
        public readonly string $currencyCredit,
        public readonly ?array $data = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'third_party_id_debit' => $this->thirdPartyIdDebit,
            'service_id' => $this->serviceId,
            'sender_id' => $this->senderId,
            'recipient_id' => $this->recipientId,
            'amount_credit' => MoneyJson::number($this->amountCredit),
            'currency_credit' => $this->currencyCredit,
        ];
        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }
        return $payload;
    }
}
