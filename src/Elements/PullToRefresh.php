<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * EDGE element definition for the native pull-to-refresh container.
 */
class PullToRefresh extends Element
{
    public const DEFAULTS = [
        'preset' => 'spring',
        'threshold' => 88.0,
        'indicator_size' => 32.0,
        'indicator_stroke_width' => 2.5,
        'animation_duration' => 240,
        'track_color' => '',
        'refreshing' => false,
        'result' => 'idle',
        'result_duration' => 500,
        'refresh_action_label' => 'Refresh',
        'pull_label' => 'Pull to refresh',
        'release_label' => 'Release to refresh',
        'refreshing_label' => 'Refreshing',
        'success_label' => 'Refresh complete',
        'error_label' => 'Refresh failed',
    ];

    private const PRESETS = ['liquid', 'spring', 'minimal'];
    private const RESULTS = ['idle', 'success', 'error'];

    protected string $type = 'pull_to_refresh';

    protected array $componentProps = [];

    /** Create an empty pull-to-refresh EDGE element. */
    public static function make(): static
    {
        return new static;
    }

    /** Select the native indicator treatment used while pulling and refreshing. */
    public function preset(string $preset): static
    {
        $preset = strtolower(trim($preset));
        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Pull-to-refresh preset must be liquid, spring, or minimal.');
        }

        $this->componentProps['preset'] = $preset;

        return $this;
    }

    /** Set the distance in points/dp used to normalize pull progress. */
    public function threshold(mixed $points): static
    {
        $this->componentProps['threshold'] = self::positiveNumber($points, 'threshold');

        return $this;
    }

    /** Set the indicator diameter in points/dp. */
    public function indicatorSize(mixed $points): static
    {
        $this->componentProps['indicator_size'] = self::positiveNumber($points, 'indicator-size');

        return $this;
    }

    /** Set the custom progress stroke width in points/dp. */
    public function indicatorStrokeWidth(mixed $points): static
    {
        $this->componentProps['indicator_stroke_width'] = self::positiveNumber($points, 'indicator-stroke-width');

        return $this;
    }

    /** Set the duration in milliseconds for non-essential indicator transitions. */
    public function animationDuration(mixed $milliseconds): static
    {
        $this->componentProps['animation_duration'] = self::nonNegativeInteger($milliseconds, 'animation-duration');

        return $this;
    }

    /** Set the iOS SF Symbol used inside the custom indicator. */
    public function indicatorIconIos(string $symbol): static
    {
        $symbol = trim($symbol);
        if ($symbol === '') {
            throw new InvalidArgumentException('Pull-to-refresh indicator-icon-ios must name an SF Symbol.');
        }

        $this->assertVisualSource('native-icon');
        $this->componentProps['indicator_icon_ios'] = $symbol;

        return $this;
    }

    /** Set the supported Material icon used inside the Android indicator. */
    public function indicatorIconAndroid(string $icon): static
    {
        $icon = strtolower(trim($icon));
        if ($icon === '') {
            throw new InvalidArgumentException('Pull-to-refresh indicator-icon-android must name a supported Material icon.');
        }

        $this->assertVisualSource('native-icon');
        $this->componentProps['indicator_icon_android'] = $icon;

        return $this;
    }

    /** Set a local PNG, SVG, or GIF path relative to the consuming app's public directory. */
    public function indicatorAsset(string $path): static
    {
        $path = self::localAssetPath($path, 'indicator-asset');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'svg', 'gif'], true)) {
            throw new InvalidArgumentException('Pull-to-refresh indicator-asset must point to a PNG, SVG, or GIF under public/.');
        }

        $this->assertVisualSource('asset');
        $this->componentProps['indicator_asset'] = $path;

        return $this;
    }

    /** Set a local Lottie JSON path relative to the consuming app's public directory. */
    public function indicatorLottie(string $path): static
    {
        $path = self::localAssetPath($path, 'indicator-lottie');
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            throw new InvalidArgumentException('Pull-to-refresh indicator-lottie must point to a Lottie JSON file under public/.');
        }

        $this->assertVisualSource('lottie');
        $this->componentProps['indicator_lottie'] = $path;

        return $this;
    }

    /** Set the accent color for progress, glyphs, and indicator artwork. */
    public function accentColor(string|int $color): static
    {
        $this->componentProps['accent_color'] = self::colorValue($color, 'accent-color');

        return $this;
    }

    /** Set the indicator's circular background color. */
    public function containerColor(string|int $color): static
    {
        $this->componentProps['container_color'] = self::colorValue($color, 'container-color');

        return $this;
    }

    /** Set the inactive progress track color; renderers fall back to the theme when omitted. */
    public function trackColor(string|int $color): static
    {
        if (is_string($color) && trim($color) === '') {
            throw new InvalidArgumentException('Pull-to-refresh track-color cannot be empty.');
        }

        $this->componentProps['track_color'] = self::colorValue($color, 'track-color');

        return $this;
    }

    /** Publish the app-owned loading state used to lock further refresh requests. */
    public function refreshing(bool $refreshing): static
    {
        $this->componentProps['refreshing'] = $refreshing;

        return $this;
    }

    /** Publish the latest refresh outcome; use idle before starting another request. */
    public function result(string $result): static
    {
        $result = strtolower(trim($result));
        if (! in_array($result, self::RESULTS, true)) {
            throw new InvalidArgumentException('Pull-to-refresh result must be idle, success, or error.');
        }

        $this->componentProps['result'] = $result;

        return $this;
    }

    /** Set how long success/error feedback remains visible, in milliseconds. */
    public function resultDuration(mixed $milliseconds): static
    {
        $this->componentProps['result_duration'] = self::nonNegativeInteger($milliseconds, 'result-duration');

        return $this;
    }

    /** Set a parent Native Component method to invoke after an accepted refresh gesture. */
    public function onRefresh(string $method): static
    {
        $method = trim($method);
        if ($method === '') {
            throw new InvalidArgumentException('Pull-to-refresh on-refresh must name a component method.');
        }

        $this->componentProps['on_refresh'] = $method;

        return $this;
    }

    /**
     * Map Blade's kebab-case attributes to the serialized native props.
     * Unrelated attributes are left to NativeElementCollector for layout/style handling.
     */
    public function applyAttributes(array $attrs): void
    {
        if (array_key_exists('preset', $attrs)) {
            $this->preset(self::stringValue($attrs['preset'], 'preset'));
        }

        if (array_key_exists('threshold', $attrs)) {
            $this->threshold($attrs['threshold']);
        }

        if (array_key_exists('indicator-size', $attrs)) {
            $this->indicatorSize($attrs['indicator-size']);
        }

        if (array_key_exists('indicator-stroke-width', $attrs)) {
            $this->indicatorStrokeWidth($attrs['indicator-stroke-width']);
        }

        if (array_key_exists('animation-duration', $attrs)) {
            $this->animationDuration($attrs['animation-duration']);
        }

        if (self::hasNonEmptyAttribute($attrs, 'indicator-icon-ios')) {
            $this->indicatorIconIos(self::stringValue($attrs['indicator-icon-ios'], 'indicator-icon-ios'));
        }

        if (self::hasNonEmptyAttribute($attrs, 'indicator-icon-android')) {
            $this->indicatorIconAndroid(self::stringValue($attrs['indicator-icon-android'], 'indicator-icon-android'));
        }

        if (self::hasNonEmptyAttribute($attrs, 'indicator-asset')) {
            $this->indicatorAsset(self::stringValue($attrs['indicator-asset'], 'indicator-asset'));
        }

        if (self::hasNonEmptyAttribute($attrs, 'indicator-lottie')) {
            $this->indicatorLottie(self::stringValue($attrs['indicator-lottie'], 'indicator-lottie'));
        }

        if (array_key_exists('accent-color', $attrs)) {
            $this->accentColor(self::colorValue($attrs['accent-color'], 'accent-color'));
        }

        if (array_key_exists('container-color', $attrs)) {
            $this->containerColor(self::colorValue($attrs['container-color'], 'container-color'));
        }

        if (self::hasNonEmptyAttribute($attrs, 'indicator-track-color')) {
            $this->trackColor(self::colorValue($attrs['indicator-track-color'], 'indicator-track-color'));
        }

        if (array_key_exists('refreshing', $attrs)) {
            $this->refreshing(self::booleanValue($attrs['refreshing'], 'refreshing'));
        }

        if (array_key_exists('result', $attrs)) {
            $this->result(self::stringValue($attrs['result'], 'result'));
        }

        if (array_key_exists('result-duration', $attrs)) {
            $this->resultDuration($attrs['result-duration']);
        }

        foreach ([
            'refresh-action-label' => 'refreshActionLabel',
            'pull-label' => 'pullLabel',
            'release-label' => 'releaseLabel',
            'refreshing-label' => 'refreshingLabel',
            'success-label' => 'successLabel',
            'error-label' => 'errorLabel',
        ] as $attribute => $setter) {
            if (array_key_exists($attribute, $attrs)) {
                $this->{$setter}(self::stringValue($attrs[$attribute], $attribute));
            }
        }

        $refreshMethod = $attrs['on-refresh'] ?? $attrs['on_refresh'] ?? null;
        if ($refreshMethod !== null && $refreshMethod !== false && $refreshMethod !== '') {
            $this->onRefresh(self::stringValue($refreshMethod, 'on-refresh'));
        }
    }

    /** Add stable defaults and replace the PHP method name with NativePHP's callback id. */
    protected function resolveProps(CallbackRegistry $registry): array
    {
        $props = [
            ...self::DEFAULTS,
            ...$this->componentProps,
        ];

        if (isset($this->componentProps['on_refresh'])) {
            $props['on_refresh'] = $registry->register($this->componentProps['on_refresh']);
        }

        return $props;
    }

    /** Keep a platform icon pair, a local image, and Lottie mutually exclusive as visual sources. */
    private function assertVisualSource(string $requestedSource): void
    {
        $existingSource = match (true) {
            isset($this->componentProps['indicator_asset']) => 'asset',
            isset($this->componentProps['indicator_lottie']) => 'lottie',
            isset($this->componentProps['indicator_icon_ios']) || isset($this->componentProps['indicator_icon_android']) => 'native-icon',
            default => null,
        };

        if ($existingSource !== null && $existingSource !== $requestedSource) {
            throw new InvalidArgumentException('Pull-to-refresh accepts one custom indicator source: native icons, indicator-asset, or indicator-lottie.');
        }
    }

    /** Require non-empty accessibility text so assistive technology always has a useful state. */
    private static function nonEmptyLabel(string $label, string $name): string
    {
        $label = trim($label);
        if ($label === '') {
            throw new InvalidArgumentException("Pull-to-refresh {$name} cannot be empty.");
        }

        return $label;
    }

    /** Normalize local asset paths and reject schemes, absolute paths, empty segments, and traversal. */
    private static function localAssetPath(string $path, string $name): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $segments = explode('/', $path);

        if (
            $path === '' || str_starts_with($path, '/') ||
            preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1 ||
            in_array('..', $segments, true) || in_array('', $segments, true) ||
            str_contains($path, "\0")
        ) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a relative path inside public/ without parent-directory segments or a URL scheme.");
        }

        return $path;
    }

    /** Treat absent and empty conditional Blade bindings as an unset optional prop. */
    private static function hasNonEmptyAttribute(array $attrs, string $name): bool
    {
        if (! array_key_exists($name, $attrs)) {
            return false;
        }

        $value = $attrs[$name];

        return $value !== null && $value !== false && (! is_string($value) || trim($value) !== '');
    }

    /** Require a string at the Blade attribute boundary with a prop-specific error. */
    private static function stringValue(mixed $value, string $name): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a string.");
        }

        return $value;
    }

    /** Preserve supported EDGE color strings and ARGB integers while rejecting other wire types. */
    private static function colorValue(mixed $value, string $name): string|int
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
    private static function booleanValue(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($boolean === null) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be boolean.");
        }

        return $boolean;
    }

    /** Normalize a finite positive dimension into the numeric value sent to native renderers. */
    private static function positiveNumber(mixed $value, string $name): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a number greater than zero.");
        }

        $number = (float) $value;
        if (! is_finite($number) || $number <= 0) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a number greater than zero.");
        }

        return $number;
    }

    /** Normalize non-negative millisecond values while rejecting fractional or non-numeric input. */
    private static function nonNegativeInteger(mixed $value, string $name): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a non-negative integer in milliseconds.");
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 0) {
            throw new InvalidArgumentException("Pull-to-refresh {$name} must be a non-negative integer in milliseconds.");
        }

        return $integer;
    }

    /** Override the localized name exposed for the native accessibility refresh action. */
    public function refreshActionLabel(string $label): static
    {
        $this->componentProps['refresh_action_label'] = self::nonEmptyLabel($label, 'refresh-action-label');

        return $this;
    }

    /** Override the accessibility announcement while the list is waiting for a pull. */
    public function pullLabel(string $label): static
    {
        $this->componentProps['pull_label'] = self::nonEmptyLabel($label, 'pull-label');

        return $this;
    }

    /** Override the accessibility announcement when the gesture reaches its activation point. */
    public function releaseLabel(string $label): static
    {
        $this->componentProps['release_label'] = self::nonEmptyLabel($label, 'release-label');

        return $this;
    }

    /** Override the accessibility announcement while the app refreshes its content. */
    public function refreshingLabel(string $label): static
    {
        $this->componentProps['refreshing_label'] = self::nonEmptyLabel($label, 'refreshing-label');

        return $this;
    }

    /** Override the accessibility announcement for a successful refresh. */
    public function successLabel(string $label): static
    {
        $this->componentProps['success_label'] = self::nonEmptyLabel($label, 'success-label');

        return $this;
    }

    /** Override the accessibility announcement for a failed refresh. */
    public function errorLabel(string $label): static
    {
        $this->componentProps['error_label'] = self::nonEmptyLabel($label, 'error-label');

        return $this;
    }
}
