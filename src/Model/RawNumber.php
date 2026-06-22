<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use JsonSerializable;

/**
 * A pre-validated numeric literal that must be emitted as a bare JSON number.
 *
 * It serializes to a unique placeholder string; {@see \Bankplanet9\MilkywayPayments\Internal\RequestJson}
 * then substitutes the placeholder with the literal value, producing valid JSON
 * with the decimal as an unquoted number at full precision.
 */
final class RawNumber implements JsonSerializable
{
    private string $placeholder;

    public function __construct(public readonly string $literal)
    {
        // Collision-resistant, JSON-string-safe placeholder unique to this instance.
        $this->placeholder = '@@RAWNUM:' . bin2hex(random_bytes(8)) . ':' . $literal . '@@';
    }

    public function placeholder(): string
    {
        return $this->placeholder;
    }

    public function jsonSerialize(): string
    {
        return $this->placeholder;
    }
}
