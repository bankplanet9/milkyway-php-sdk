<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Brick\Math\BigDecimal;

/**
 * Bridges {@see BigDecimal} money values into request JSON as raw JSON *numbers*
 * (not strings, not floats) so full precision survives the wire.
 *
 * PHP cannot natively encode an arbitrary-precision decimal as a JSON number:
 * json_encode would render a string as `"100.00"` (wrong type) and a float would
 * lose precision. So a money value is encoded as a {@see RawNumber} sentinel which
 * the request serializer expands into the exact decimal literal.
 */
final class MoneyJson
{
    /** Wrap a decimal so the serializer emits it as a bare JSON number. */
    public static function number(BigDecimal $value): RawNumber
    {
        return new RawNumber((string) $value);
    }
}
