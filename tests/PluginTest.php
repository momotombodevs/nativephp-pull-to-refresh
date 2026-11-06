<?php

use Momotombo\NativePhpPullToRefresh\Elements\PullToRefresh;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshAppearance;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshIndicator;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshLayer;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshPhase;
use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\PhaseName;
use Momotombo\NativePhpPullToRefresh\Enums\Preset;
use Momotombo\NativePhpPullToRefresh\NativePhpPullToRefreshServiceProvider;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\ElementRegistry;
use Native\Mobile\Edge\Elements\Text;
use Native\Mobile\Edge\NativeElementCollector;

beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifest = json_decode(file_get_contents($this->pluginPath.'/nativephp.json'), true, flags: JSON_THROW_ON_ERROR);
});

describe('plugin manifest and package', function () {
    it('declares a valid non-self-closing pull-to-refresh component', function () {
        expect($this->manifest['namespace'])->toBe('MomotomboPullToRefresh');
        expect($this->manifest['components'])->toHaveCount(4);
        expect($this->manifest['components'][0])->toMatchArray([
            'type' => 'pull_to_refresh',
            'element' => 'Momotombo\\NativePhpPullToRefresh\\Elements\\PullToRefresh',
            'blade' => 'Momotombo\\NativePhpPullToRefresh\\Components\\PullToRefresh',
            'ios_renderer' => 'NativePhpPullToRefreshRenderer',
            'self_closing' => false,
        ]);

        expect($this->manifest['components'][0]['android_renderer'])
            ->toBe('dev.momotombo.plugins.nativephp_pull_to_refresh.ui.NativePhpPullToRefreshRenderer');
        expect(array_column($this->manifest['components'], 'type'))
            ->toBe(['pull_to_refresh', 'pull_to_refresh_indicator', 'pull_to_refresh_phase', 'pull_to_refresh_layer']);
        foreach (array_slice($this->manifest['components'], 1) as $component) {
            expect($component)->toMatchArray([
                'android_renderer' => 'com.nativephp.plugins.native_ui.ui.EmptyRenderer',
                'ios_renderer' => 'NativeUIEmptyRenderer',
                'self_closing' => false,
            ]);
        }
        expect($this->manifest['android']['dependencies']['implementation'])
            ->toContain('io.coil-kt.coil3:coil-svg:3.1.0')
            ->toContain('io.coil-kt.coil3:coil-gif:3.1.0')
            ->toContain('com.airbnb.android:lottie-compose:6.7.1');
        expect($this->manifest['ios']['dependencies']['swift_packages'])
            ->toContain([
                'url' => 'https://github.com/swhitty/SwiftDraw.git',
                'version' => '0.28.0',
                'products' => ['SwiftDraw'],
            ])
            ->toContain([
                'url' => 'https://github.com/SDWebImage/SDWebImageSwiftUI.git',
                'version' => '3.1.4',
                'products' => ['SDWebImageSwiftUI'],
            ])
            ->toContain([
                'url' => 'https://github.com/airbnb/lottie-spm.git',
                'version' => '4.6.1',
                'products' => ['Lottie'],
            ]);
    });

    it('uses canonical Composer metadata and valid renderer paths', function () {
        $composer = json_decode(file_get_contents($this->pluginPath.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['require']['nativephp/mobile'])->toBe('^4.5');
        expect($composer['require']['nativephp/mobile-ui'])->toBe('^0.6');
        expect($composer['autoload']['psr-4']['Momotombo\\NativePhpPullToRefresh\\'])->toBe('src/');
        expect(file_exists($this->pluginPath.'/src/Elements/PullToRefresh.php'))->toBeTrue();
        expect(file_exists($this->pluginPath.'/src/Components/PullToRefresh.php'))->toBeTrue();
        expect(file_exists($this->pluginPath.'/src/NativePhpPullToRefreshServiceProvider.php'))->toBeTrue();
        expect(class_exists(NativePhpPullToRefreshServiceProvider::class))->toBeTrue();
        expect(file_exists($this->pluginPath.'/resources/android/Momotombo/NativePhpPullToRefreshRenderer.kt'))->toBeTrue();
        expect(file_exists($this->pluginPath.'/resources/ios/NativePhpPullToRefreshRenderer.swift'))->toBeTrue();
    });

    it('wires the track color through both native renderers', function () {
        $ios = file_get_contents($this->pluginPath.'/resources/ios/NativePhpPullToRefreshRenderer.swift');
        $android = file_get_contents($this->pluginPath.'/resources/android/Momotombo/NativePhpPullToRefreshRenderer.kt');

        expect($ios)
            ->toContain('getColor("track_color"')
            ->toContain('theme.outlineVariant')
            ->toContain('trackColor: trackColor');
        expect($android)
            ->toContain('getColor("track_color"')
            ->toContain('theme.outlineVariant')
            ->toContain('trackColor = trackColor')
            ->toContain('pullState.animateToHidden()');
    });

    it('feeds the render sequence into each platform lifecycle', function () {
        // The lifecycle logic itself is exercised by tests/NativeLifecycleTest.php;
        // this only guards that both renderers read the prop and hand it over.
        $ios = file_get_contents($this->pluginPath.'/resources/ios/NativePhpPullToRefreshRenderer.swift');
        $android = file_get_contents($this->pluginPath.'/resources/android/Momotombo/NativePhpPullToRefreshRenderer.kt');

        expect($ios)->toContain('getInt("render_sequence"')->toContain('renderSequenceChanged:');
        expect($android)->toContain('getInt("render_sequence"')->toContain('renderSequence = renderSequence');
    });
});

describe('pull-to-refresh element', function () {
    it('serializes default props and keeps its children', function () {
        $child = new class extends Element
        {
            protected string $type = 'test_child';
        };

        $node = PullToRefresh::make()
            ->addChild($child)
            ->toArray(new CallbackRegistry);

        expect($node['type'])->toBe('pull_to_refresh');
        expect($node['props'])->toMatchArray([
            'preset' => 'spring',
            'threshold' => 88.0,
            'indicator_size' => 32.0,
            'indicator_stroke_width' => 2.5,
            'animation_duration' => 240,
            'shape' => 'circle',
            'progress_style' => 'ring',
            'motion' => 'spring',
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
        ]);
        expect($node['children'])->toHaveCount(1);
        expect($node['children'][0]['type'])->toBe('test_child');
    });

    it('advances the render sequence so a no-change render is never identical', function () {
        $element = PullToRefresh::make()->onRefresh('refreshFeed');

        $first = $element->toArray(new CallbackRegistry)['props']['render_sequence'];
        $second = $element->toArray(new CallbackRegistry)['props']['render_sequence'];

        expect($first)->toBeInt()->toBeGreaterThan(0);
        expect($second)->toBe($first + 1);
    });

    it('maps Blade attributes and registers the refresh callback', function () {
        $element = PullToRefresh::make();
        $element->applyAttributes([
            'preset' => 'liquid',
            'threshold' => '96',
            'indicator-size' => '36',
            'indicator-stroke-width' => '3.5',
            'animation-duration' => '360',
            'shape' => 'blob',
            'progress-style' => 'arc',
            'motion' => 'spin',
            'progress-mode' => 'indeterminate',
            'accent-color' => '#123456',
            'container-color' => 'transparent',
            'indicator-track-color' => '#BFDBFE',
            'refreshing' => 'true',
            'result' => 'success',
            'result-duration' => '750',
            'refresh-action-label' => 'Actualizar ahora',
            'pull-label' => 'Desliza para actualizar',
            'release-label' => 'Soltá para actualizar',
            'refreshing-label' => 'Actualizando',
            'success-label' => 'Actualización lista',
            'error-label' => 'No se pudo actualizar',
            'on-refresh' => 'refreshFeed',
        ]);

        $registry = new CallbackRegistry;
        $props = $element->getResolvedProps($registry);

        expect($props)->toMatchArray([
            'preset' => 'liquid',
            'threshold' => 96.0,
            'indicator_size' => 36.0,
            'indicator_stroke_width' => 3.5,
            'animation_duration' => 360,
            'shape' => 'blob',
            'progress_style' => 'arc',
            'motion' => 'spin',
            'progress_mode' => 'indeterminate',
            'accent_color' => '#123456',
            'container_color' => 'transparent',
            'track_color' => '#BFDBFE',
            'refreshing' => true,
            'result' => 'success',
            'result_duration' => 750,
            'refresh_action_label' => 'Actualizar ahora',
            'pull_label' => 'Desliza para actualizar',
            'release_label' => 'Soltá para actualizar',
            'refreshing_label' => 'Actualizando',
            'success_label' => 'Actualización lista',
            'error_label' => 'No se pudo actualizar',
        ]);
        expect($props['on_refresh'])->toBeInt()->toBeGreaterThan(0);
        expect($registry->resolve($props['on_refresh']))->toBeArray();
    });

    it('maps platform native icon attributes', function () {
        $element = PullToRefresh::make();
        $element->applyAttributes([
            'indicator-icon-ios' => 'arrow.clockwise.circle.fill',
            'indicator-icon-android' => 'Refresh',
        ]);

        expect($element->getResolvedProps(new CallbackRegistry))
            ->toMatchArray([
                'indicator_icon_ios' => 'arrow.clockwise.circle.fill',
                'indicator_icon_android' => 'refresh',
            ]);
    });

    it('rejects Android icons that the native renderer does not support', function () {
        expect(fn () => PullToRefresh::make()->indicatorIconAndroid('archive'))
            ->toThrow(InvalidArgumentException::class, 'indicator-icon-android must be refresh, sync');
    });

    it('ignores empty optional visual attributes from conditional Blade bindings', function () {
        $element = PullToRefresh::make();
        $element->applyAttributes([
            'indicator-icon-ios' => '',
            'indicator-icon-android' => null,
            'indicator-asset' => '',
            'indicator-lottie' => null,
        ]);

        $props = $element->getResolvedProps(new CallbackRegistry);

        expect(array_intersect(array_keys($props), [
            'indicator_icon_ios',
            'indicator_icon_android',
            'indicator_asset',
            'indicator_lottie',
        ]))->toBe([]);
    });

    it('maps local PNG, SVG, GIF, and Lottie asset attributes', function () {
        $element = PullToRefresh::make();
        $element->applyAttributes(['indicator-asset' => './refresh/indicator.svg']);

        expect($element->getResolvedProps(new CallbackRegistry)['indicator_asset'])
            ->toBe('refresh/indicator.svg');
        expect(PullToRefresh::make()->indicatorAsset('refresh/indicator.png')->getResolvedProps(new CallbackRegistry)['indicator_asset'])
            ->toBe('refresh/indicator.png');
        expect(PullToRefresh::make()->indicatorAsset('refresh/indicator.gif')->getResolvedProps(new CallbackRegistry)['indicator_asset'])
            ->toBe('refresh/indicator.gif');
        expect(PullToRefresh::make()->indicatorLottie('refresh/indicator.json')->getResolvedProps(new CallbackRegistry)['indicator_lottie'])
            ->toBe('refresh/indicator.json');
    });

    it('rejects invalid asset paths, unsupported formats, and mixed visual sources', function () {
        expect(fn () => PullToRefresh::make()->indicatorAsset('https://example.com/icon.png'))
            ->toThrow(InvalidArgumentException::class, 'relative path inside public/');
        expect(fn () => PullToRefresh::make()->indicatorAsset('../icon.png'))
            ->toThrow(InvalidArgumentException::class, 'relative path inside public/');
        expect(fn () => PullToRefresh::make()->indicatorAsset('/absolute/icon.png'))
            ->toThrow(InvalidArgumentException::class, 'relative path inside public/');
        expect(fn () => PullToRefresh::make()->indicatorAsset('icons/icon.webp'))
            ->toThrow(InvalidArgumentException::class, 'PNG, SVG, or GIF');
        expect(fn () => PullToRefresh::make()->indicatorLottie('animations/loader.gif'))
            ->toThrow(InvalidArgumentException::class, 'Lottie JSON');
        expect(fn () => PullToRefresh::make()->indicatorAsset('icons/icon.png')->indicatorLottie('animations/loader.json'))
            ->toThrow(InvalidArgumentException::class, 'one custom indicator source');
        expect(fn () => PullToRefresh::make()->indicatorIconIos('arrow.clockwise')->indicatorAsset('icons/icon.png'))
            ->toThrow(InvalidArgumentException::class, 'one custom indicator source');
    });

    it('supports the fluent Element API', function () {
        $props = PullToRefresh::make()
            ->preset('minimal')
            ->threshold(72)
            ->indicatorSize(28)
            ->indicatorStrokeWidth(3)
            ->animationDuration(180)
            ->appearance(
                PullToRefreshAppearance::make()
                    ->shape('capsule')
                    ->progressStyle('dots')
                    ->motion('pulse')
                    ->progressMode('none')
            )
            ->accentColor(0xFF112233)
            ->containerColor('#F8FAFC')
            ->trackColor('#E2E8F0')
            ->refreshing(true)
            ->result('error')
            ->resultDuration(900)
            ->onRefresh('refreshFeed')
            ->refreshActionLabel('Actualizar actividad')
            ->pullLabel('Pull feed')
            ->releaseLabel('Release feed')
            ->refreshingLabel('Loading feed')
            ->successLabel('Feed loaded')
            ->errorLabel('Feed failed')
            ->getResolvedProps(new CallbackRegistry);

        expect($props)->toMatchArray([
            'preset' => 'minimal',
            'threshold' => 72.0,
            'indicator_size' => 28.0,
            'indicator_stroke_width' => 3.0,
            'animation_duration' => 180,
            'shape' => 'capsule',
            'progress_style' => 'dots',
            'motion' => 'pulse',
            'progress_mode' => 'none',
            'accent_color' => 0xFF112233,
            'container_color' => '#F8FAFC',
            'track_color' => '#E2E8F0',
            'refreshing' => true,
            'result' => 'error',
            'result_duration' => 900,
            'refresh_action_label' => 'Actualizar actividad',
            'pull_label' => 'Pull feed',
            'release_label' => 'Release feed',
            'refreshing_label' => 'Loading feed',
            'success_label' => 'Feed loaded',
            'error_label' => 'Feed failed',
        ]);
    });

    it('rejects unsupported presets and results', function () {
        expect(fn () => PullToRefresh::make()->preset('spinner'))
            ->toThrow(InvalidArgumentException::class, 'preset must be liquid, spring, minimal, orbital, wave, or pulse');
        expect(fn () => PullToRefresh::make()->result('failed'))
            ->toThrow(InvalidArgumentException::class, 'result must be idle, success, or error');
    });

    it('rejects invalid dimensions, duration, and booleans', function () {
        expect(fn () => PullToRefresh::make()->applyAttributes(['threshold' => '0']))
            ->toThrow(InvalidArgumentException::class, 'threshold must be a number greater than zero');
        expect(fn () => PullToRefresh::make()->applyAttributes(['indicator-size' => '-1']))
            ->toThrow(InvalidArgumentException::class, 'indicator-size must be a number greater than zero');
        expect(fn () => PullToRefresh::make()->applyAttributes(['indicator-stroke-width' => '0']))
            ->toThrow(InvalidArgumentException::class, 'indicator-stroke-width must be a number greater than zero');
        expect(fn () => PullToRefresh::make()->applyAttributes(['animation-duration' => '-1']))
            ->toThrow(InvalidArgumentException::class, 'animation-duration must be a non-negative integer');
        expect(fn () => PullToRefresh::make()->applyAttributes(['result-duration' => '-1']))
            ->toThrow(InvalidArgumentException::class, 'result-duration must be a non-negative integer');
        expect(fn () => PullToRefresh::make()->applyAttributes(['refreshing' => 'sometimes']))
            ->toThrow(InvalidArgumentException::class, 'refreshing must be boolean');
        expect(fn () => PullToRefresh::make()->trackColor('  '))
            ->toThrow(InvalidArgumentException::class, 'track-color cannot be empty');
        expect(fn () => PullToRefresh::make()->accentColor('  '))
            ->toThrow(InvalidArgumentException::class, 'accent-color cannot be empty');
        expect(fn () => PullToRefresh::make()->applyAttributes(['indicator-track-color' => []]))
            ->toThrow(InvalidArgumentException::class, 'indicator-track-color must be a color string or ARGB integer');
        expect(fn () => PullToRefresh::make()->applyAttributes(['pull-label' => '  ']))
            ->toThrow(InvalidArgumentException::class, 'pull-label cannot be empty');
        expect(fn () => PullToRefresh::make()->applyAttributes(['refresh-action-label' => '  ']))
            ->toThrow(InvalidArgumentException::class, 'refresh-action-label cannot be empty');
    });
});

describe('pull-to-refresh visual composition', function () {
    it('collects the Blade sentinel tree without adding it to scrollable content', function () {
        foreach ($this->manifest['components'] as $component) {
            ElementRegistry::register($component['type'], $component['element']);
        }
        ElementRegistry::register('text', Text::class);

        NativeElementCollector::reset();
        NativeElementCollector::open('pull_to_refresh', ['on-refresh' => 'refreshFeed']);
        NativeElementCollector::open('pull_to_refresh_indicator', ['shape' => 'blob', 'motion' => 'spring']);
        NativeElementCollector::open('pull_to_refresh_phase', ['name' => 'pulling']);
        NativeElementCollector::open('pull_to_refresh_layer', ['role' => 'label']);
        NativeElementCollector::leaf('text', ['text' => 'Desliza para actualizar']);
        NativeElementCollector::close();
        NativeElementCollector::close();
        NativeElementCollector::close();
        NativeElementCollector::leaf('text', ['text' => 'Scrollable content']);
        NativeElementCollector::close();

        $node = NativeElementCollector::collect()->toArray(new CallbackRegistry);

        expect($node['children'][0]['type'])->toBe('pull_to_refresh_indicator');
        expect($node['children'][0]['props'])->toMatchArray(['shape' => 'blob', 'motion' => 'spring']);
        expect($node['children'][0]['children'][0]['props']['name'])->toBe('pulling');
        expect($node['children'][1])->toMatchArray(['type' => 'text']);
    });

    it('serializes a typed indicator tree separately from scroll content', function () {
        $visual = new class extends Element
        {
            protected string $type = 'test_visual';
        };
        $content = new class extends Element
        {
            protected string $type = 'test_content';
        };

        $node = PullToRefresh::make()
            ->indicator(
                PullToRefreshIndicator::make()
                    ->shape('blob')
                    ->motion('spring')
                    ->progressMode('scrub')
                    ->phase(
                        PullToRefreshPhase::make('pulling')
                            ->layer(PullToRefreshLayer::make('artwork')->addChild($visual))
                    )
            )
            ->addChild($content)
            ->toArray(new CallbackRegistry);

        expect($node['children'][0]['type'])->toBe('pull_to_refresh_indicator');
        expect($node['children'][0]['children'][0]['props']['name'])->toBe('pulling');
        expect($node['children'][0]['children'][0]['children'][0]['props']['role'])->toBe('artwork');
        expect($node['children'][1]['type'])->toBe('test_content');
    });

    it('rejects duplicate indicators, phases, and scrollable layer content', function () {
        expect(fn () => PullToRefresh::make()
            ->indicator(PullToRefreshIndicator::make())
            ->indicator(PullToRefreshIndicator::make()))
            ->toThrow(InvalidArgumentException::class, 'only one custom indicator');

        expect(fn () => PullToRefreshIndicator::make()
            ->phase(PullToRefreshPhase::make('pulling'))
            ->phase(PullToRefreshPhase::make('pulling')))
            ->toThrow(InvalidArgumentException::class, 'already defines the pulling phase');

        $scroll = new class extends Element
        {
            protected string $type = 'scroll_view';
        };
        expect(fn () => PullToRefresh::make()->indicator(
            PullToRefreshIndicator::make()->phase(
                PullToRefreshPhase::make('pulling')->layer(PullToRefreshLayer::make('artwork')->addChild($scroll))
            )
        )->toArray(new CallbackRegistry))
            ->toThrow(InvalidArgumentException::class, 'scroll_view cannot be used');
    });

    it('derives progress style and motion from the preset unless set explicitly', function () {
        $resolved = fn (PullToRefresh $element): array => array_intersect_key(
            $element->toArray(new CallbackRegistry)['props'],
            array_flip(['progress_style', 'motion']),
        );

        expect($resolved(PullToRefresh::make()))->toBe(['progress_style' => 'ring', 'motion' => 'spring']);
        expect($resolved(PullToRefresh::make()->preset('liquid')))->toBe(['progress_style' => 'wave', 'motion' => 'liquid']);
        expect($resolved(PullToRefresh::make()->preset('minimal')))->toBe(['progress_style' => 'ring', 'motion' => 'spin']);
        expect($resolved(PullToRefresh::make()->preset('orbital')))->toBe(['progress_style' => 'arc', 'motion' => 'spin']);
        expect($resolved(PullToRefresh::make()->preset('wave')))->toBe(['progress_style' => 'wave', 'motion' => 'liquid']);
        expect($resolved(PullToRefresh::make()->preset('pulse')))->toBe(['progress_style' => 'dots', 'motion' => 'pulse']);

        expect($resolved(PullToRefresh::make()->progressStyle('ring')->preset('pulse')->motion('none')))
            ->toBe(['progress_style' => 'ring', 'motion' => 'none']);

        $blade = PullToRefresh::make();
        $blade->applyAttributes(['preset' => 'orbital', 'shape' => '', 'progress-style' => '', 'motion' => null, 'progress-mode' => '']);
        expect($blade->toArray(new CallbackRegistry)['props'])->toMatchArray([
            'shape' => 'circle',
            'progress_style' => 'arc',
            'motion' => 'spin',
            'progress_mode' => 'scrub',
        ]);
    });

    it('accepts enum cases wherever a raw value is accepted', function () {
        $node = PullToRefresh::make()
            ->preset(Preset::Orbital)
            ->shape(IndicatorShape::Blob)
            ->indicator(PullToRefreshIndicator::make()->motion(Motion::Pulse)->phase(PullToRefreshPhase::make(PhaseName::Pulling)))
            ->toArray(new CallbackRegistry);

        expect($node['props'])->toMatchArray(['preset' => 'orbital', 'shape' => 'blob', 'progress_style' => 'arc', 'motion' => 'spin']);
        expect($node['children'][0]['props'])->toBe(['motion' => 'pulse']);
        expect($node['children'][0]['children'][0]['props'])->toBe(['name' => 'pulling']);
    });

    it('validates the visual API enums and layer assets', function () {
        expect(fn () => PullToRefresh::make()->shape('square'))
            ->toThrow(InvalidArgumentException::class, 'shape must be circle, capsule, rounded, or blob');
        expect(fn () => PullToRefreshIndicator::make()->progressMode('manual'))
            ->toThrow(InvalidArgumentException::class, 'progress-mode must be scrub, indeterminate, or none');
        expect(fn () => PullToRefreshLayer::make('artwork')->asset('../secret.png'))
            ->toThrow(InvalidArgumentException::class, 'relative path inside public');
        expect(fn () => PullToRefreshLayer::make('artwork')->asset('refresh/logo.png')->lottie('refresh/loading.json'))
            ->toThrow(InvalidArgumentException::class, 'one visual source');
    });

    it('rejects phases and layers placed directly in the container', function () {
        expect(fn () => PullToRefresh::make()->addChild(PullToRefreshPhase::make('pulling')))
            ->toThrow(InvalidArgumentException::class, 'pull_to_refresh_phase must be nested inside a pull-to-refresh-indicator');
        expect(fn () => PullToRefresh::make()->addChild(PullToRefreshLayer::make('artwork')))
            ->toThrow(InvalidArgumentException::class, 'pull_to_refresh_layer must be nested inside a pull-to-refresh-indicator');
    });

    it('requires a pulling phase because every omitted phase falls back to it', function () {
        expect(fn () => PullToRefresh::make()
            ->indicator(PullToRefreshIndicator::make()->phase(PullToRefreshPhase::make('refreshing')))
            ->toArray(new CallbackRegistry))
            ->toThrow(InvalidArgumentException::class, 'must define a pulling phase');
    });

    it('rejects interactive callbacks anywhere inside a layer', function () {
        $tappable = new class extends Element
        {
            protected string $type = 'test_tappable';

            protected function resolveProps(CallbackRegistry $registry): array
            {
                return ['on_change' => 42];
            }
        };
        $wrapper = new class extends Element
        {
            protected string $type = 'test_wrapper';
        };

        expect(fn () => PullToRefresh::make()->indicator(
            PullToRefreshIndicator::make()->phase(
                PullToRefreshPhase::make('pulling')->layer(PullToRefreshLayer::make('artwork')->addChild($wrapper->addChild($tappable)))
            )
        )->toArray(new CallbackRegistry))
            ->toThrow(InvalidArgumentException::class, 'Interactive callbacks cannot be used inside a pull-to-refresh layer');
    });

    it('keeps the v1 error messages for missing icon and refresh method names', function () {
        expect(fn () => PullToRefresh::make()->indicatorIconIos('  '))
            ->toThrow(InvalidArgumentException::class, 'indicator-icon-ios must name an SF Symbol');
        expect(fn () => PullToRefresh::make()->onRefresh(''))
            ->toThrow(InvalidArgumentException::class, 'on-refresh must name a component method');
    });
});
