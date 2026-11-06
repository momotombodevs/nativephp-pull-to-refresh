<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressMode;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressStyle;
use Momotombo\NativePhpPullToRefresh\Support\Input;

/**
 * Typed visual options shared by the container, the composed indicator, and Blade.
 *
 * Only options that were set explicitly are serialized, so presets can still
 * supply defaults for the rest.
 */
final class PullToRefreshAppearance
{
    private ?IndicatorShape $shape = null;

    private ?ProgressStyle $progressStyle = null;

    private ?Motion $motion = null;

    private ?ProgressMode $progressMode = null;

    public static function make(): self
    {
        return new self;
    }

    /** Silhouette of the indicator container. */
    public function shape(IndicatorShape|string $shape): self
    {
        $this->shape = IndicatorShape::parse($shape, 'shape');

        return $this;
    }

    /** How the built-in indicator draws progress. */
    public function progressStyle(ProgressStyle|string $style): self
    {
        $this->progressStyle = ProgressStyle::parse($style, 'progress-style');

        return $this;
    }

    /** Non-essential animation treatment. */
    public function motion(Motion|string $motion): self
    {
        $this->motion = Motion::parse($motion, 'motion');

        return $this;
    }

    /** Whether progress follows the gesture, loops, or stays full. */
    public function progressMode(ProgressMode|string $mode): self
    {
        $this->progressMode = ProgressMode::parse($mode, 'progress-mode');

        return $this;
    }

    /**
     * Read the kebab-case Blade attributes this object understands; blank bindings are ignored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyAttributes(array $attributes): self
    {
        foreach (['shape' => 'shape', 'progress-style' => 'progressStyle', 'motion' => 'motion', 'progress-mode' => 'progressMode'] as $attribute => $setter) {
            if (Input::has($attributes, $attribute)) {
                $this->{$setter}(Input::string($attributes[$attribute], $attribute));
            }
        }

        return $this;
    }

    /** Copy every option that was set on `$other`, keeping this object's values for the rest. */
    public function merge(self $other): self
    {
        $this->shape = $other->shape ?? $this->shape;
        $this->progressStyle = $other->progressStyle ?? $this->progressStyle;
        $this->motion = $other->motion ?? $this->motion;
        $this->progressMode = $other->progressMode ?? $this->progressMode;

        return $this;
    }

    /**
     * Wire props for the options that were set.
     *
     * @return array{shape?: string, progress_style?: string, motion?: string, progress_mode?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'shape' => $this->shape?->value,
            'progress_style' => $this->progressStyle?->value,
            'motion' => $this->motion?->value,
            'progress_mode' => $this->progressMode?->value,
        ], fn (?string $value): bool => $value !== null);
    }
}
