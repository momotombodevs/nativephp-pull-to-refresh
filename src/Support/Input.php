<?php

namespace Momotombo\NativePhpPullToRefresh\Support;

use InvalidArgumentException;

/**
 * Validation shared by every element at the Blade and fluent API boundary.
 *
 * Each helper fails with an `InvalidArgumentException` that names the
 * kebab-case attribute, so errors read the same from Blade and from PHP.
 */
final class Input
{
    /** Treat absent, null, false, and blank conditional Blade bindings as "not set". */
    public static function has(array $attributes, string $name): bool
    {
        if (! array_key_exists($name, $attributes)) {
            return false;
        }

        $value = $attributes[$name];

        return $value !== null && $value !== false && (! is_string($value) || trim($value) !== '');
    }

    /** Require a string with an attribute-specific error. */
    public static function string(mixed $value, string $name): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a string.");
        }

        return $value;
    }

    /** Require text that still has content after trimming, such as accessibility labels. */
    public static function nonEmptyString(string $value, string $name): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("Pull-to-refresh {$name} cannot be empty.");
        }

        return $value;
    }

    /** Preserve EDGE color strings and ARGB integers while rejecting other wire types. */
    public static function color(mixed $value, string $name): string|int
    {
        if (is_string($value) && trim($value) === '') {
            throw new InvalidArgumentException("Pull-to-refresh {$name} cannot be empty.");
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a color string or ARGB integer.");
        }

        return $value;
    }

    /** Parse Blade's boolean spellings without silently coercing arbitrary values. */
    public static function boolean(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw new InvalidArgumentException("Pull-to-refresh {$name} must be boolean.");
    }

    /** Normalize a finite dimension greater than zero. */
    public static function positiveNumber(mixed $value, string $name): float
    {
        $number = is_numeric($value) ? (float) $value : NAN;
        if (! is_finite($number) || $number <= 0) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a number greater than zero.");
        }

        return $number;
    }

    /** Normalize a whole number of milliseconds, rejecting fractions and negatives. */
    public static function milliseconds(mixed $value, string $name): int
    {
        $integer = is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($integer === false || $integer < 0) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a non-negative integer in milliseconds.");
        }

        return $integer;
    }

    /**
     * Normalize a path relative to the consuming app's `public/` directory.
     *
     * Rejects URL schemes, absolute paths, empty segments, traversal, NUL bytes,
     * and extensions outside `$extensions`.
     *
     * @param  list<string>  $extensions  Lower-case extensions without the dot.
     * @param  string  $fileKind  Phrase used in the extension error, e.g. "a PNG, SVG, or GIF".
     */
    public static function publicAsset(string $path, string $name, array $extensions, string $fileKind): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $segments = explode('/', $path);

        if (
            $path === '' || str_starts_with($path, '/')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1
            || in_array('..', $segments, true) || in_array('', $segments, true)
            || str_contains($path, "\0")
        ) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a relative path inside public/ without parent-directory segments or a URL scheme.");
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must point to {$fileKind} under public/.");
        }

        return $path;
    }
}
