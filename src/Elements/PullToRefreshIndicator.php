<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\PhaseName;
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

    /** Create an empty indicator declaration; add at least a `pulling` phase. */
    public static function make(): static
    {
        return new static;
    }

    /** Silhouette of the indicator container; overrides the container's `shape`. */
    public function shape(IndicatorShape|string $shape): static
    {
        $this->appearance->shape($shape);

        return $this;
    }

    /** Motion for this indicator's layers; falls back to the container's `motion` natively. */
    public function motion(Motion|string $motion): static
    {
        $this->appearance->motion($motion);

        return $this;
    }

    /** Progress mode for this indicator's layers; falls back to the container's `progress-mode` natively. */
    public function progressMode(ProgressMode|string $mode): static
    {
        $this->appearance->progressMode($mode);

        return $this;
    }

    /** Declare what to draw in one lifecycle phase. */
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

    /**
     * Read `shape`, `motion`, and `progress-mode`. A composed indicator draws its
     * own progress, so `progress-style` is ignored here.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function applyAttributes(array $attrs): void
    {
        $this->appearance->applyAttributes(array_intersect_key($attrs, array_flip(['shape', 'motion', 'progress-mode'])));
    }

    /**
     * Validate every phase and layer before the tree crosses the PHP/native boundary.
     *
     * The `pulling` phase is required because renderers fall back to it for every
     * phase the indicator leaves out.
     */
    public function validateComposition(): void
    {
        /** @var list<PullToRefreshPhase> $phases `addChild` only accepts phases. */
        $phases = $this->getChildren();

        $names = array_map(fn (PullToRefreshPhase $phase): PhaseName => $phase->name(), $phases);
        if (! in_array(PhaseName::Pulling, $names, true)) {
            throw new InvalidArgumentException('Pull-to-refresh indicator must define a pulling phase; renderers fall back to it for every omitted phase.');
        }

        foreach ($phases as $phase) {
            $phase->validateComposition();
        }
    }

    /** @return array{shape?: string, motion?: string, progress_mode?: string} */
    protected function resolveProps(CallbackRegistry $registry): array
    {
        return $this->appearance->toArray();
    }
}
