# NativePHP Pull to Refresh

Native pull-to-refresh EDGE container for NativePHP Mobile 4.5+ on iOS and Android. The component owns one vertical scroll container, renders an animated native indicator, and invokes a method on the parent Native Component when a refresh is accepted.

The consuming app owns data fetching and publishes the `refreshing` and `result` states. This package does not make network requests and does not provide a JavaScript API or custom bridge functions.

## Requirements

- PHP 8.3+
- NativePHP Mobile `^4.5`
- NativePHP Mobile UI `^0.6`
- iOS 18.2+
- Android API 26+

## Installation

### Published package

Install the stable release from Composer:

```sh
composer require momotombo/nativephp-pull-to-refresh:^1.1
```

Register the NativePHP plugin in the consuming app. Publish the NativePHP plugin provider first if the app does not have one yet:

```sh
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register momotombo/nativephp-pull-to-refresh
php artisan native:plugin:list
```

The provider publishing command is normally a one-time setup. Do not register the same plugin repeatedly in the same app.

Rebuild the native app after the plugin is registered and whenever `nativephp.json`, Kotlin, Swift, or native dependencies change:

```sh
php artisan native:run ios
php artisan native:run android
```

### Local package development

Add the package as a Composer path repository in the host app's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/momotombo/nativephp-pull-to-refresh",
      "options": { "symlink": true }
    }
  ]
}
```

Then install the development version and register it:

```sh
composer require momotombo/nativephp-pull-to-refresh:@dev
php artisan native:plugin:register momotombo/nativephp-pull-to-refresh
```

PHP changes are available immediately through the path repository. Native changes require a new native build; restarting PHP or refreshing Composer does not replace the installed Kotlin or Swift renderer.

## Blade usage

Use the component as the only vertical scroll container for the screen. Give it available height so short or empty content can still be pulled.

```blade
<native:pull-to-refresh
    :refreshing="$refreshing"
    :result="$refreshResult"
    on-refresh="refreshFeed"
    class="w-full flex-1"
>
    @forelse ($items as $item)
        <native:row class="w-full p-4">
            <native:text>{{ $item['title'] }}</native:text>
        </native:row>
    @empty
        <native:column class="w-full items-center justify-center p-8">
            <native:text>No items yet. Pull down to check again.</native:text>
        </native:column>
    @endforelse
</native:pull-to-refresh>
```

`on-refresh` names a method on the parent Native Component. The app should reject re-entry, set `refreshing` before the request, reset `result` to `idle`, and publish `success` or `error` when the request finishes:

```php
use Native\Mobile\Edge\NativeComponent;
use Throwable;

class FeedScreen extends NativeComponent
{
    public bool $refreshing = false;
    public string $refreshResult = 'idle';
    public array $items = [];

    public function refreshFeed(): void
    {
        if ($this->refreshing) {
            return;
        }

        $this->refreshing = true;
        $this->refreshResult = 'idle';

        try {
            $this->items = app(FeedRepository::class)->latest();
            $this->refreshResult = 'success';
        } catch (Throwable $exception) {
            report($exception);
            $this->refreshResult = 'error';
        } finally {
            $this->refreshing = false;
        }
    }
}
```

Replace `FeedRepository` with the app's data source. The package only coordinates the native gesture and callback; it does not manage application data, caching, authentication, or retries.

## PHP fluent API

The same element can be created programmatically when building an EDGE tree in PHP:

```php
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefresh;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshAppearance;
use Momotombo\NativePhpPullToRefresh\Enums\IndicatorShape;
use Momotombo\NativePhpPullToRefresh\Enums\Motion;
use Momotombo\NativePhpPullToRefresh\Enums\Preset;
use Momotombo\NativePhpPullToRefresh\Enums\ProgressStyle;
use Native\Mobile\Edge\Elements\Row;
use Native\Mobile\Edge\Elements\Text;

PullToRefresh::make()
    ->class('w-full flex-1')
    ->preset(Preset::Orbital)
    ->appearance(
        PullToRefreshAppearance::make()
            ->shape(IndicatorShape::Blob)
            ->progressStyle(ProgressStyle::Arc)
            ->motion(Motion::Spring)
    )
    ->refreshing($this->refreshing)
    ->result($this->refreshResult)
    ->onRefresh('refreshFeed')
    ->addChild(Row::make(Text::make('Recent activity')));
```

Every option that takes a fixed set of values accepts either the enum case or its string value (`'blob'` and `IndicatorShape::Blob` are equivalent). The enums live in `Momotombo\NativePhpPullToRefresh\Enums`: `Preset`, `IndicatorShape`, `ProgressStyle`, `Motion`, `ProgressMode`, `RefreshResult`, `AndroidIndicatorIcon`, `PhaseName`, and `LayerRole`.

Public builder methods are:

`preset`, `appearance`, `shape`, `progressStyle`, `motion`, `progressMode`, `indicator`, `threshold`, `indicatorSize`, `indicatorStrokeWidth`, `animationDuration`, `indicatorIconIos`, `indicatorIconAndroid`, `indicatorAsset`, `indicatorLottie`, `accentColor`, `containerColor`, `trackColor`, `refreshing`, `result`, `resultDuration`, `refreshActionLabel`, `pullLabel`, `releaseLabel`, `refreshingLabel`, `successLabel`, `errorLabel`, and `onRefresh`.

## Options

| Attribute | Default | Description |
| --- | --- | --- |
| `preset` | `spring` | `spring`, `liquid`, `minimal`, `orbital`, `wave`, or `pulse`. Supplies the default `progress-style` and `motion` (see below). |
| `shape` | `circle` | `circle`, `capsule`, `rounded`, or `blob`. Radii scale with the container on both platforms; `capsule` makes the container 1.6× wider than tall so it reads as a pill. |
| `progress-style` | From preset | `ring`, `arc` (orbital arc with a satellite), `wave` (liquid fill), `dots` (eight dots), or `none` (glyph only). Used by the built-in indicator. |
| `motion` | From preset | `none`, `spring` (bouncy), `liquid` (eased), `pulse` (breathes while loading), or `spin` (linear). |
| `progress-mode` | `scrub` | `scrub` follows the gesture, `indeterminate` loops as soon as the pull starts, `none` draws a full indicator without tracking the gesture. |
| `threshold` | `88` | Pull distance in points/dp. Android uses it as the activation distance; iOS uses it to scale custom progress while UIKit controls activation. |
| `indicator-size` | `32` | Indicator diameter in points/dp. The container around it is `indicator-size + 20` on iOS and `max(40, indicator-size + 8)` on Android, where Material's default box is 40dp. |
| `indicator-stroke-width` | `2.5` | Progress and status mark thickness. |
| `animation-duration` | `240` | Native transition duration in milliseconds. `0` disables non-essential motion. |
| `accent-color` | Theme accent | Progress, glyph, and artwork color. Accepts a color string or ARGB integer. |
| `container-color` | Theme surface variant | Indicator background color. |
| `indicator-track-color` | Theme outline variant | Inactive progress track color. |
| `refreshing` | `false` | App-controlled loading state. Keep it true until the request finishes. |
| `result` | `idle` | `idle`, `success`, or `error`. |
| `result-duration` | `500` | Success/error feedback duration in milliseconds. `0` hides the feedback. |
| `on-refresh` | — | Parent Native Component method called after an accepted gesture or accessibility action. |
| `indicator-icon-ios` | — | iOS SF Symbol name. |
| `indicator-icon-android` | — | Android icon: `refresh`, `sync`, `arrow_downward`, `download`, `cloud_download`, or `local_shipping`. |
| `indicator-asset` | — | Relative path under the consuming app's `public/` directory to a PNG, SVG, or GIF. |
| `indicator-lottie` | — | Relative path under the consuming app's `public/` directory to a Lottie JSON file. |
| Accessibility labels | English defaults | `refresh-action-label`, `pull-label`, `release-label`, `refreshing-label`, `success-label`, and `error-label`. |

Each preset resolves `progress-style` and `motion` unless you set them yourself; empty Blade bindings also fall back to the preset.

| Preset | `progress-style` | `motion` |
| --- | --- | --- |
| `spring` | `ring` | `spring` |
| `liquid` | `wave` | `liquid` |
| `minimal` | `ring` (thin) | `spin` |
| `orbital` | `arc` | `spin` |
| `wave` | `wave` | `liquid` |
| `pulse` | `dots` | `pulse` |

A composed `native:pull-to-refresh-indicator` inherits `motion` and `progress-mode` from the container unless it declares its own. While looping, `progress` and `track` layers spin, `artwork` spins with `motion="spin"` and breathes with `motion="pulse"`. Reduced-motion settings and `motion="none"` stop every loop, including Lottie and GIF playback, which then show a still frame.

Example:

```blade
<native:pull-to-refresh
    preset="orbital"
    shape="blob"
    progress-style="arc"
    motion="spring"
    accent-color="#7C3AED"
    container-color="#F5F3FF"
    :refreshing="$refreshing"
    :result="$refreshResult"
    on-refresh="refreshFeed"
    class="w-full flex-1"
>
    <!-- EDGE children -->
</native:pull-to-refresh>
```

## Composed indicators in Blade

For a fully custom visual indicator, add exactly one `native:pull-to-refresh-indicator`. It is a declarative sentinel: it is consumed by the parent and never becomes a row in the scrollable content. Its `pulling` phase is required as the base (the element throws without one); missing phases reuse that composition.

```blade
<native:pull-to-refresh threshold="88" on-refresh="refreshFeed" class="w-full flex-1">
    <native:pull-to-refresh-indicator shape="blob" motion="spring" progress-mode="scrub">
        <native:pull-to-refresh-phase name="pulling">
            <native:pull-to-refresh-layer role="background">
                <native:stack class="items-center justify-center">
                    <native:image src="refresh/background.svg" />
                </native:stack>
            </native:pull-to-refresh-layer>
            <native:pull-to-refresh-layer role="artwork">
                <native:image src="refresh/logo.svg" width="42" height="42" />
            </native:pull-to-refresh-layer>
            <native:pull-to-refresh-layer role="label">
                <native:text>Desliza para actualizar</native:text>
            </native:pull-to-refresh-layer>
        </native:pull-to-refresh-phase>

        <native:pull-to-refresh-phase name="release">
            <native:pull-to-refresh-layer role="progress">
                <native:icon name="arrow-upward" />
            </native:pull-to-refresh-layer>
        </native:pull-to-refresh-phase>

        <native:pull-to-refresh-phase name="refreshing">
            <native:pull-to-refresh-layer role="artwork" lottie="refresh/loading.json" />
        </native:pull-to-refresh-phase>

        <native:pull-to-refresh-phase name="success">
            <native:pull-to-refresh-layer role="status">
                <native:icon name="check-circle" />
                <native:text>Actualizado</native:text>
            </native:pull-to-refresh-layer>
        </native:pull-to-refresh-phase>

        <native:pull-to-refresh-phase name="error">
            <native:pull-to-refresh-layer role="status">
                <native:icon name="error" />
                <native:text>Error al actualizar</native:text>
            </native:pull-to-refresh-layer>
        </native:pull-to-refresh-phase>
    </native:pull-to-refresh-indicator>

    <native:column>
        <!-- scrollable EDGE content -->
    </native:column>
</native:pull-to-refresh>
```

Available phases are `pulling`, `release`, `refreshing`, `success`, and `error`. Layer roles are `background`, `track`, `progress`, `artwork`, `label`, and `status`; the active phase decides which layers draw, and each role reacts to progress and motion: `background` grows with the pull, `progress` turns with it (or spins while looping), `track` spins while looping, `artwork` follows the motion (spring/liquid scale, spin, pulse), `label` stays hidden until the pull starts, and `status` draws as-is for `success`/`error` phases. Layers can contain existing visual EDGE components, including `stack`, `row`, `image`, `icon`, `text`, `progress-bar`, and `canvas`.

Do not add a scroll view, another pull-to-refresh, or interactive callbacks inside a layer. The PHP validator rejects those structures. A custom composition replaces only the visual indicator: gesture activation, callbacks, accessibility, reduced-motion behavior, `refreshing`, `result`, and `result-duration` remain owned by the parent container.

The typed API creates the identical EDGE tree:

```php
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshIndicator;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshLayer;
use Momotombo\NativePhpPullToRefresh\Elements\PullToRefreshPhase;
use Native\Mobile\Edge\Elements\Image;

PullToRefresh::make()
    ->indicator(
        PullToRefreshIndicator::make()
            ->phase(
                PullToRefreshPhase::make('pulling')
                    ->layer(
                        PullToRefreshLayer::make('artwork')
                            ->addChild(Image::make('refresh/logo.svg'))
                    )
            )
    );
```

## Custom artwork

Put artwork in the consuming app's `public/` directory and pass a relative path:

```blade
<native:pull-to-refresh
    indicator-asset="pull-to-refresh/refresh.svg"
    :refreshing="$refreshing"
    :result="$refreshResult"
    on-refresh="refreshFeed"
    class="w-full flex-1"
>
    <!-- EDGE children -->
</native:pull-to-refresh>
```

Supported formats are PNG, SVG, GIF, and Lottie JSON. Use exactly one visual source: the platform icon pair, `indicator-asset`, or `indicator-lottie`. A composed layer can instead use its local `asset` or `lottie` attribute. URLs, absolute paths, parent-directory segments (`..`), empty path segments, and unsupported extensions are rejected. The package does not copy application assets; the native renderer resolves them from the consuming app.

## Layout, accessibility, and platform behavior

- The component owns one vertical scroll container. Do not nest another vertical scroller inside it.
- The container needs available height, commonly `class="w-full flex-1"`, for short or empty content to accept a pull.
- VoiceOver and TalkBack can trigger refresh through the localized `refresh-action-label`.
- Reduced-motion settings are respected by both renderers.
- Android uses Material 3 pull-to-refresh and applies `threshold` to activation.
- iOS uses UIKit `UIRefreshControl` for activation. `threshold` scales the custom visual progress but does not change UIKit's native activation distance.
- Native dependencies are declared in [nativephp.json](nativephp.json). The host app must be rebuilt after changing that manifest or either native renderer.

If the screen already has a vertical list or scroll view, use that screen's existing refresh mechanism instead of nesting it inside this component.


A refresh whose callback changes nothing still completes: NativePHP skips publishing identical frames, so the element serializes a `render_sequence` that advances on every render and the renderers treat a newer render as the callback's answer. Two consequences: screens using this component always publish at least one changed node per render, and any unrelated render of the same screen that lands while a refresh is pending also completes it. Keep refresh handlers idempotent (for example, return early while `$this->refreshing` is true).

## Validation and development commands

From the package repository:

```sh
composer validate --strict
composer install
composer test
```

`composer test` also compiles and runs the native refresh-lifecycle checks in `tests/Native`: the Kotlin one needs a JDK and a Kotlin compiler in the Gradle cache (any Android build leaves one), the Swift one needs Xcode. Each is skipped when its toolchain is missing. Run only the PHP tests with `vendor/bin/pest --exclude-group=native`.

From a NativePHP host app:

```sh
php artisan native:plugin:validate packages/momotombo/nativephp-pull-to-refresh
php artisan native:plugin:list
php artisan native:run ios
php artisan native:run android
```

`native:plugin:validate` validates the plugin manifest and declarations. It does not replace a native build or device acceptance test. After changing Kotlin, Swift, or native dependencies, rebuild the target platform before evaluating behavior.

## Support

- Issues and bug reports: [GitHub Issues](https://github.com/momotombodevs/nativephp-pull-to-refresh/issues)
- Source code: [GitHub repository](https://github.com/momotombodevs/nativephp-pull-to-refresh)

When reporting a native issue, include the package version, NativePHP Mobile version, platform, OS/API version, renderer configuration, and the relevant build log. Do not include secrets or private application data.

## License

MIT; see [LICENSE](LICENSE).
