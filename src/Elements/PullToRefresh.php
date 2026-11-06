<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Momotombo\NativePhpPullToRefresh\Enums\AndroidIndicatorIcon;
use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\Preset;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressMode;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressStyle;
use Momotombo\NativePhpPullToRefresh\Enums\RefreshResult;
use Momotombo\NativePhpPullToRefresh\Support\Input;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * EDGE element definition for the native pull-to-refresh container.
 */
class PullToRefresh extends Element
{
    /**
     * Props serialized when the author leaves them unset. `progress_style` and
     * `motion` are absent on purpose: the preset supplies them.
     */
    public const DEFAULTS = [
        'preset' => 'spring',
        'threshold' => 88.0,
        'indicator_size' => 32.0,
        'indicator_stroke_width' => 2.5,
        'animation_duration' => 240,
        'shape' => 'circle',
        'progress_mode' => 'scrub',
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

    /** Accessibility label setters keyed by their Blade attribute. */
    private const LABEL_SETTERS = [
        'refresh-action-label' => 'refreshActionLabel',
        'pull-label' => 'pullLabel',
        'release-label' => 'releaseLabel',
        'refreshing-label' => 'refreshingLabel',
        'success-label' => 'successLabel',
        'error-label' => 'errorLabel',
    ];

    protected string $type = 'pull_to_refresh';

    protected array $componentProps = [];

    /**
     * Advances on every serialization so the render that follows the refresh
     * callback is never byte-identical to the previous one. NativePHP drops
     * identical frames entirely, which would otherwise leave native renderers
     * with no signal that a no-change refresh finished.
     */
    private static int $renderSequence = 0;

    /** Visual options set through `appearance()`, the shortcut setters, or Blade. */
    private PullToRefreshAppearance $appearance;

    public function __construct()
    {
        $this->appearance = PullToRefreshAppearance::make();
    }

    /** Create an empty pull-to-refresh EDGE element. */
    public static function make(): static
    {
        return new static;
    }

    /** Select the indicator treatment; it also supplies default progress style and motion. */
    public function preset(Preset|string $preset): static
    {
        $this->componentProps['preset'] = Preset::parse($preset, 'preset')->value;

        return $this;
    }

    /** Apply every visual option that was set on `$appearance`. */
    public function appearance(PullToRefreshAppearance $appearance): static
    {
        $this->appearance->merge($appearance);

        return $this;
    }

    /** Select the silhouette of the indicator container. */
    public function shape(IndicatorShape|string $shape): static
    {
        $this->appearance->shape($shape);

        return $this;
    }

    /** Select how the built-in indicator draws progress. */
    public function progressStyle(ProgressStyle|string $style): static
    {
        $this->appearance->progressStyle($style);

        return $this;
    }

    /** Select the non-essential native motion treatment. */
    public function motion(Motion|string $motion): static
    {
        $this->appearance->motion($motion);

        return $this;
    }

    /** Decide whether progress follows the gesture, loops, or stays full. */
    public function progressMode(ProgressMode|string $mode): static
    {
        $this->appearance->progressMode($mode);

        return $this;
    }

    /** Attach one Blade/PHP-composed indicator declaration to this scroll container. */
    public function indicator(PullToRefreshIndicator $indicator): static
    {
        return $this->addChild($indicator);
    }

    /** Keep composition sentinels at the container root and prevent duplicates. */
    public function addChild(Element $child): static
    {
        $type = $child->getType();
        if (in_array($type, ['pull_to_refresh_phase', 'pull_to_refresh_layer'], true)) {
            throw new InvalidArgumentException("{$type} must be nested inside a pull-to-refresh-indicator.");
        }

        if ($type === 'pull_to_refresh_indicator') {
            if (! $child instanceof PullToRefreshIndicator) {
                throw new InvalidArgumentException('Pull-to-refresh indicator must use PullToRefreshIndicator.');
            }

            foreach ($this->getChildren() as $existing) {
                if ($existing->getType() === 'pull_to_refresh_indicator') {
                    throw new InvalidArgumentException('Pull-to-refresh accepts only one custom indicator.');
                }
            }
        }

        return parent::addChild($child);
    }

    /** Set the distance in points/dp used to normalize pull progress. */
    public function threshold(mixed $points): static
    {
        $this->componentProps['threshold'] = Input::positiveNumber($points, 'threshold');

        return $this;
    }

    /** Set the indicator diameter in points/dp. */
    public function indicatorSize(mixed $points): static
    {
        $this->componentProps['indicator_size'] = Input::positiveNumber($points, 'indicator-size');

        return $this;
    }

    /** Set the custom progress stroke width in points/dp. */
    public function indicatorStrokeWidth(mixed $points): static
    {
        $this->componentProps['indicator_stroke_width'] = Input::positiveNumber($points, 'indicator-stroke-width');

        return $this;
    }

    /** Set the duration in milliseconds for non-essential indicator transitions. */
    public function animationDuration(mixed $milliseconds): static
    {
        $this->componentProps['animation_duration'] = Input::milliseconds($milliseconds, 'animation-duration');

        return $this;
    }

    /** Set the iOS SF Symbol used inside the custom indicator. */
    public function indicatorIconIos(string $symbol): static
    {
        $this->assertVisualSource('native-icon');
        $this->componentProps['indicator_icon_ios'] = Input::nonEmptyString($symbol, 'indicator-icon-ios', 'must name an SF Symbol');

        return $this;
    }

    /** Set the supported Material icon used inside the Android indicator. */
    public function indicatorIconAndroid(AndroidIndicatorIcon|string $icon): static
    {
        $icon = AndroidIndicatorIcon::parse($icon, 'indicator-icon-android');

        $this->assertVisualSource('native-icon');
        $this->componentProps['indicator_icon_android'] = $icon->value;

        return $this;
    }

    /** Set a local PNG, SVG, or GIF path relative to the consuming app's public directory. */
    public function indicatorAsset(string $path): static
    {
        $path = Input::publicAsset($path, 'indicator-asset', ['png', 'svg', 'gif'], 'a PNG, SVG, or GIF');

        $this->assertVisualSource('asset');
        $this->componentProps['indicator_asset'] = $path;

        return $this;
    }

    /** Set a local Lottie JSON path relative to the consuming app's public directory. */
    public function indicatorLottie(string $path): static
    {
        $path = Input::publicAsset($path, 'indicator-lottie', ['json'], 'a Lottie JSON file');

        $this->assertVisualSource('lottie');
        $this->componentProps['indicator_lottie'] = $path;

        return $this;
    }

    /** Set the accent color for progress, glyphs, and indicator artwork. */
    public function accentColor(string|int $color): static
    {
        $this->componentProps['accent_color'] = Input::color($color, 'accent-color');

        return $this;
    }

    /** Set the indicator's circular background color. */
    public function containerColor(string|int $color): static
    {
        $this->componentProps['container_color'] = Input::color($color, 'container-color');

        return $this;
    }

    /** Set the inactive progress track color; renderers fall back to the theme when omitted. */
    public function trackColor(string|int $color): static
    {
        $this->componentProps['track_color'] = Input::color($color, 'indicator-track-color');

        return $this;
    }

    /** Publish the app-owned loading state used to lock further refresh requests. */
    public function refreshing(bool $refreshing): static
    {
        $this->componentProps['refreshing'] = $refreshing;

        return $this;
    }

    /** Publish the latest refresh outcome; use idle before starting another request. */
    public function result(RefreshResult|string $result): static
    {
        $this->componentProps['result'] = RefreshResult::parse($result, 'result')->value;

        return $this;
    }

    /** Set how long success/error feedback remains visible, in milliseconds. */
    public function resultDuration(mixed $milliseconds): static
    {
        $this->componentProps['result_duration'] = Input::milliseconds($milliseconds, 'result-duration');

        return $this;
    }

    /** Set a parent Native Component method to invoke after an accepted refresh gesture. */
    public function onRefresh(string $method): static
    {
        $this->componentProps['on_refresh'] = Input::nonEmptyString($method, 'on-refresh', 'must name a component method');

        return $this;
    }

    /**
     * Map Blade's kebab-case attributes to the serialized native props.
     *
     * Optional visuals (shape, progress style, motion, progress mode, icons,
     * assets, track color) treat blank conditional bindings as "not set", so
     * presets and theme defaults still apply. Unrelated attributes are left to
     * NativeElementCollector for layout and style handling.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function applyAttributes(array $attrs): void
    {
        $setters = [
            'preset' => fn (mixed $value) => $this->preset(Input::string($value, 'preset')),
            'threshold' => fn (mixed $value) => $this->threshold($value),
            'indicator-size' => fn (mixed $value) => $this->indicatorSize($value),
            'indicator-stroke-width' => fn (mixed $value) => $this->indicatorStrokeWidth($value),
            'animation-duration' => fn (mixed $value) => $this->animationDuration($value),
            'accent-color' => fn (mixed $value) => $this->accentColor(Input::color($value, 'accent-color')),
            'container-color' => fn (mixed $value) => $this->containerColor(Input::color($value, 'container-color')),
            'refreshing' => fn (mixed $value) => $this->refreshing(Input::boolean($value, 'refreshing')),
            'result' => fn (mixed $value) => $this->result(Input::string($value, 'result')),
            'result-duration' => fn (mixed $value) => $this->resultDuration($value),
        ];

        foreach ($setters as $attribute => $setter) {
            if (array_key_exists($attribute, $attrs)) {
                $setter($attrs[$attribute]);
            }
        }

        $this->appearance->applyAttributes($attrs);

        $optionalSetters = [
            'indicator-icon-ios' => fn (mixed $value) => $this->indicatorIconIos(Input::string($value, 'indicator-icon-ios')),
            'indicator-icon-android' => fn (mixed $value) => $this->indicatorIconAndroid(Input::string($value, 'indicator-icon-android')),
            'indicator-asset' => fn (mixed $value) => $this->indicatorAsset(Input::string($value, 'indicator-asset')),
            'indicator-lottie' => fn (mixed $value) => $this->indicatorLottie(Input::string($value, 'indicator-lottie')),
            'indicator-track-color' => fn (mixed $value) => $this->trackColor(Input::color($value, 'indicator-track-color')),
        ];

        foreach ($optionalSetters as $attribute => $setter) {
            if (Input::has($attrs, $attribute)) {
                $setter($attrs[$attribute]);
            }
        }

        foreach (self::LABEL_SETTERS as $attribute => $setter) {
            if (array_key_exists($attribute, $attrs)) {
                $this->{$setter}(Input::string($attrs[$attribute], $attribute));
            }
        }

        $refreshAttribute = array_key_exists('on-refresh', $attrs) ? 'on-refresh' : 'on_refresh';
        if (Input::has($attrs, $refreshAttribute)) {
            $this->onRefresh(Input::string($attrs[$refreshAttribute], 'on-refresh'));
        }
    }

    /**
     * Merge defaults, preset visuals, and explicit props, then swap the PHP
     * method name for NativePHP's callback id.
     *
     * @return array<string, string|int|float|bool>
     */
    protected function resolveProps(CallbackRegistry $registry): array
    {
        $this->validateCustomIndicator();

        $preset = Preset::from($this->componentProps['preset'] ?? self::DEFAULTS['preset']);

        $props = [
            ...self::DEFAULTS,
            'progress_style' => $preset->defaultProgressStyle()->value,
            'motion' => $preset->defaultMotion()->value,
            ...$this->componentProps,
            ...$this->appearance->toArray(),
        ];

        if (isset($this->componentProps['on_refresh'])) {
            $props['on_refresh'] = $registry->register($this->componentProps['on_refresh']);
        }

        self::$renderSequence = self::$renderSequence >= 2_000_000_000 ? 1 : self::$renderSequence + 1;
        $props['render_sequence'] = self::$renderSequence;

        return $props;
    }

    /** Validate the declarative overlay before it crosses the PHP/native boundary. */
    private function validateCustomIndicator(): void
    {
        foreach ($this->getChildren() as $child) {
            if ($child instanceof PullToRefreshIndicator) {
                $child->validateComposition();
            }
        }
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

    /** Override the localized name exposed for the native accessibility refresh action. */
    public function refreshActionLabel(string $label): static
    {
        $this->componentProps['refresh_action_label'] = Input::nonEmptyString($label, 'refresh-action-label');

        return $this;
    }

    /** Override the accessibility announcement while the list is waiting for a pull. */
    public function pullLabel(string $label): static
    {
        $this->componentProps['pull_label'] = Input::nonEmptyString($label, 'pull-label');

        return $this;
    }

    /** Override the accessibility announcement when the gesture reaches its activation point. */
    public function releaseLabel(string $label): static
    {
        $this->componentProps['release_label'] = Input::nonEmptyString($label, 'release-label');

        return $this;
    }

    /** Override the accessibility announcement while the app refreshes its content. */
    public function refreshingLabel(string $label): static
    {
        $this->componentProps['refreshing_label'] = Input::nonEmptyString($label, 'refreshing-label');

        return $this;
    }

    /** Override the accessibility announcement for a successful refresh. */
    public function successLabel(string $label): static
    {
        $this->componentProps['success_label'] = Input::nonEmptyString($label, 'success-label');

        return $this;
    }

    /** Override the accessibility announcement for a failed refresh. */
    public function errorLabel(string $label): static
    {
        $this->componentProps['error_label'] = Input::nonEmptyString($label, 'error-label');

        return $this;
    }
}
