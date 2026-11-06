<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

use InvalidArgumentException;

/**
 * Shared parsing for the package's string-backed enums.
 *
 * Blade attributes and fluent setters accept either a case or its raw value;
 * raw values are trimmed and lower-cased so `"Orbital "` resolves like `orbital`.
 */
trait ParsesInput
{
    /** Resolve a case from a raw attribute value, failing with the attribute's name and the allowed values. */
    public static function parse(self|string $value, string $attribute): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtolower(trim($value)))
            ?? throw new InvalidArgumentException("Pull-to-refresh {$attribute} must be ".self::describeValues().'.');
    }

    /**
     * Every raw value in declaration order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Human-readable list such as "idle, success, or error". */
    private static function describeValues(): string
    {
        $values = self::values();
        $last = array_pop($values);

        return $values === [] ? $last : implode(', ', $values).', or '.$last;
    }
}
