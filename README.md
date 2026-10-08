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
composer require momotombo/nativephp-pull-to-refresh:^1.0
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
use Native\Mobile\Edge\Elements\Row;
use Native\Mobile\Edge\Elements\Text;

PullToRefresh::make()
    ->class('w-full flex-1')
    ->preset('spring')
    ->refreshing($this->refreshing)
    ->result($this->refreshResult)
    ->onRefresh('refreshFeed')
    ->addChild(Row::make(Text::make('Recent activity')));
```

Public builder methods are:

`preset`, `threshold`, `indicatorSize`, `indicatorStrokeWidth`, `animationDuration`, `indicatorIconIos`, `indicatorIconAndroid`, `indicatorAsset`, `indicatorLottie`, `accentColor`, `containerColor`, `trackColor`, `refreshing`, `result`, `resultDuration`, `refreshActionLabel`, `pullLabel`, `releaseLabel`, `refreshingLabel`, `successLabel`, `errorLabel`, and `onRefresh`.

## Options

| Attribute | Default | Description |
| --- | --- | --- |
| `preset` | `spring` | `liquid`, `spring`, or `minimal`. |
| `threshold` | `88` | Pull distance in points/dp. Android uses it as the activation distance; iOS uses it to scale custom progress while UIKit controls activation. |
| `indicator-size` | `32` | Indicator diameter in points/dp. |
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

Example:

```blade
<native:pull-to-refresh
    preset="liquid"
    accent-color="#2563EB"
    indicator-track-color="#BFDBFE"
    indicator-icon-ios="arrow.clockwise.circle.fill"
    indicator-icon-android="refresh"
    :refreshing="$refreshing"
    :result="$refreshResult"
    on-refresh="refreshFeed"
    class="w-full flex-1"
>
    <!-- EDGE children -->
</native:pull-to-refresh>
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

Supported formats are PNG, SVG, GIF, and Lottie JSON. Use exactly one visual source: the platform icon pair, `indicator-asset`, or `indicator-lottie`. URLs, absolute paths, parent-directory segments (`..`), empty path segments, and unsupported extensions are rejected. The package does not copy application assets; the native renderer resolves them from the consuming app.

## Layout, accessibility, and platform behavior

- The component owns one vertical scroll container. Do not nest another vertical scroller inside it.
- The container needs available height, commonly `class="w-full flex-1"`, for short or empty content to accept a pull.
- VoiceOver and TalkBack can trigger refresh through the localized `refresh-action-label`.
- Reduced-motion settings are respected by both renderers.
- Android uses Material 3 pull-to-refresh and applies `threshold` to activation.
- iOS uses UIKit `UIRefreshControl` for activation. `threshold` scales the custom visual progress but does not change UIKit's native activation distance.
- Native dependencies are declared in [nativephp.json](nativephp.json). The host app must be rebuilt after changing that manifest or either native renderer.

If the screen already has a vertical list or scroll view, use that screen's existing refresh mechanism instead of nesting it inside this component.

## Validation and development commands

From the package repository:

```sh
composer validate --strict
composer install
composer test
```

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
