<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Bankplanet9\MilkywayPayments\Internal\Json;

/**
 * HTTP 400 — request validation failed (bad amount, missing field,
 * unresolvable FX rate, a recipient the bank calls invalid, ...).
 *
 * When the failure concerns one request field, {@see self::getField()} names it
 * by its JSON name or path (e.g. `amount_credit`, `currency_credit`,
 * `recipient_id`, `sender_info.date_of_birth`).
 */
final class MilkywayValidationException extends MilkywayApiException
{
    private ?string $field;

    public function __construct(string $message, ?string $responseBody = null)
    {
        parent::__construct(400, $message, $responseBody);
        $this->field = $responseBody !== null ? Json::extractStringField($responseBody, 'field') : null;
    }

    /** The JSON name of the request field at fault (`field` of the body), when given. */
    public function getField(): ?string
    {
        return $this->field;
    }
}
