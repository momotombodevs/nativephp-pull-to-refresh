<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Momotombo\NativePhpPullToRefresh\Enums\PhaseName;
use Momotombo\NativePhpPullToRefresh\Support\Input;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/** One visual lifecycle phase inside a pull-to-refresh indicator declaration. */
class PullToRefreshPhase extends Element
{
    protected string $type = 'pull_to_refresh_phase';

    private PhaseName $phaseName = PhaseName::Pulling;

    public static function make(PhaseName|string $name): static
    {
        $phase = new static;
        $phase->phaseName = PhaseName::parse($name, 'phase name');

        return $phase;
    }

    public function name(): PhaseName
    {
        return $this->phaseName;
    }

    public function layer(PullToRefreshLayer $layer): static
    {
        return $this->addChild($layer);
    }

    /** Accept only layers. */
    public function addChild(Element $child): static
    {
        if (! $child instanceof PullToRefreshLayer) {
            throw new InvalidArgumentException('Pull-to-refresh phase children must be pull-to-refresh layers.');
        }

        return parent::addChild($child);
    }

    public function applyAttributes(array $attrs): void
    {
        if (array_key_exists('name', $attrs)) {
            $this->phaseName = PhaseName::parse(Input::string($attrs['name'], 'phase name'), 'phase name');
        }
    }

    public function validateComposition(): void
    {
        foreach ($this->getChildren() as $layer) {
            if (! $layer instanceof PullToRefreshLayer) {
                throw new InvalidArgumentException('Pull-to-refresh phase children must be layers.');
            }

            $layer->validateComposition();
        }
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return ['name' => $this->phaseName->value];
    }
}
