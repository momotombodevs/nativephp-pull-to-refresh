<?php

namespace Momotombo\NativePhpPullToRefresh\Elements;

use InvalidArgumentException;
use Momotombo\NativePhpPullToRefresh\Enums\LayerRole;
use Momotombo\NativePhpPullToRefresh\Support\Input;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * Visual layer rendered by the pull-to-refresh overlay in its active phase.
 *
 * A layer may hold one local asset or Lottie file plus visual EDGE children.
 * Scroll containers, nested pull-to-refresh nodes, and interactive callbacks
 * are rejected because the overlay never receives touches.
 */
class PullToRefreshLayer extends Element
{
    private const FORBIDDEN_TYPES = [
        'scroll_view', 'virtual_list', 'lazy_grid', 'pull_to_refresh', 'refreshable',
        'pull_to_refresh_indicator', 'pull_to_refresh_phase', 'pull_to_refresh_layer',
    ];

    protected string $type = 'pull_to_refresh_layer';

    private LayerRole $role = LayerRole::Artwork;

    private ?string $asset = null;

    private ?string $lottie = null;

    public static function make(LayerRole|string $role): static
    {
        $layer = new static;
        $layer->role = LayerRole::parse($role, 'layer role');

        return $layer;
    }

    /** Draw a local PNG, SVG, or GIF from the consuming app's public directory. */
    public function asset(string $path): static
    {
        $this->assertNoVisualSource();
        $this->asset = Input::publicAsset($path, 'layer asset', ['png', 'svg', 'gif'], 'a PNG, SVG, or GIF');

        return $this;
    }

    /** Play a local Lottie JSON file from the consuming app's public directory. */
    public function lottie(string $path): static
    {
        $this->assertNoVisualSource();
        $this->lottie = Input::publicAsset($path, 'layer lottie', ['json'], 'a Lottie JSON file');

        return $this;
    }

    public function applyAttributes(array $attrs): void
    {
        if (array_key_exists('role', $attrs)) {
            $this->role = LayerRole::parse(Input::string($attrs['role'], 'layer role'), 'layer role');
        }

        if (Input::has($attrs, 'asset')) {
            $this->asset(Input::string($attrs['asset'], 'layer asset'));
        }

        if (Input::has($attrs, 'lottie')) {
            $this->lottie(Input::string($attrs['lottie'], 'layer lottie'));
        }
    }

    /** Serialize each child once and reject forbidden or interactive nodes anywhere below it. */
    public function validateComposition(): void
    {
        foreach ($this->getChildren() as $child) {
            $nextId = 1;
            $emittedIds = [];
            $hashes = [];
            self::assertVisualOnly($child->toArray(new CallbackRegistry, $nextId, '', 0, $emittedIds, $hashes));
        }
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return array_filter([
            'role' => $this->role->value,
            'asset' => $this->asset,
            'lottie' => $this->lottie,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * Walk one serialized subtree.
     *
     * @param  array{type?: string, props?: array<string, mixed>, children?: list<array<string, mixed>>, on_press?: int, on_long_press?: int}  $node
     */
    private static function assertVisualOnly(array $node): void
    {
        $type = $node['type'] ?? '';
        if (in_array($type, self::FORBIDDEN_TYPES, true)) {
            throw new InvalidArgumentException("{$type} cannot be used inside a pull-to-refresh layer.");
        }

        $hasCallback = isset($node['on_press']) || isset($node['on_long_press']);
        foreach ($node['props'] ?? [] as $key => $value) {
            $hasCallback = $hasCallback || (str_starts_with((string) $key, 'on_') && (int) $value !== 0);
        }

        if ($hasCallback) {
            throw new InvalidArgumentException('Interactive callbacks cannot be used inside a pull-to-refresh layer.');
        }

        foreach ($node['children'] ?? [] as $child) {
            self::assertVisualOnly($child);
        }
    }

    private function assertNoVisualSource(): void
    {
        if ($this->asset !== null || $this->lottie !== null) {
            throw new InvalidArgumentException('Pull-to-refresh layer accepts one visual source: asset or lottie.');
        }
    }
}
