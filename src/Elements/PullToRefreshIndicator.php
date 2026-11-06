<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressMode;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * Sentinel the pull-to-refresh renderer reads as an overlay declaration.
 *
 * Holds one `PullToRefreshPhase` per lifecycle phase. Motion and progress mode
 * fall back to the container's values natively when not set here.
 */
class PullToRefreshIndicator extends Element
{
    protected string $type = 'pull_to_refresh_indicator';

    private PullToRefreshAppearance $appearance;

    public function __construct()
    {
        $this->appearance = PullToRefreshAppearance::make();
    }

    public static function make(): static
    {
        return new static;
    }

    public function shape(IndicatorShape|string $shape): static
    {
        $this->appearance->shape($shape);

        return $this;
    }

    public function motion(Motion|string $motion): static
    {
        $this->appearance->motion($motion);

        return $this;
    }

    public function progressMode(ProgressMode|string $mode): static
    {
        $this->appearance->progressMode($mode);

        return $this;
    }

    public function phase(PullToRefreshPhase $phase): static
    {
        return $this->addChild($phase);
    }

    /** Accept only phases, one per name. */
    public function addChild(Element $child): static
    {
        if (! $child instanceof PullToRefreshPhase) {
            throw new InvalidArgumentException('Pull-to-refresh indicator children must be pull-to-refresh phases.');
        }

        foreach ($this->getChildren() as $existing) {
            if ($existing instanceof PullToRefreshPhase && $existing->name() === $child->name()) {
                throw new InvalidArgumentException("Pull-to-refresh indicator already defines the {$child->name()->value} phase.");
            }
        }

        return parent::addChild($child);
    }

    public function applyAttributes(array $attrs): void
    {
        // A composed indicator draws its own progress, so `progress-style` does not apply here.
        $this->appearance->applyAttributes(array_intersect_key($attrs, array_flip(['shape', 'motion', 'progress-mode'])));
    }

    /** Validate every phase and layer before the tree crosses the PHP/native boundary. */
    public function validateComposition(): void
    {
        foreach ($this->getChildren() as $phase) {
            if (! $phase instanceof PullToRefreshPhase) {
                throw new InvalidArgumentException('Pull-to-refresh indicator children must be phases.');
            }

            $phase->validateComposition();
        }
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return $this->appearance->toArray();
    }
}
