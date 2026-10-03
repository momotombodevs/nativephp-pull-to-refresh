# NativePHP Pull to Refresh

A native pull-to-refresh EDGE container for NativePHP Mobile 4.5+ on iOS and Android. It owns vertical scrolling and calls a method on its parent Native Component when a refresh is accepted. Your app fetches the data and publishes the loading and result states.

## Requirements

- PHP 8.3+
- NativePHP Mobile `^4.5`
- NativePHP Mobile UI `^0.6`
- iOS 18.2+ or Android API 26+

## Install

For local development, add the package path to the host app's `composer.json`:

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

Then install and register the plugin:

```sh
composer require momotombo/nativephp-pull-to-refresh:@dev
php artisan native:plugin:register momotombo/nativephp-pull-to-refresh
```

Native dependencies are declared in `nativephp.json`. Register NativePHP Mobile UI in the host app if your EDGE children use its elements, such as `native:row` or `native:text`.

## Blade example

Use the element as the vertical scroll container for NativePHP EDGE children. Give it available height so short or empty content can still be pulled.

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

`on-refresh` names a method on the parent Native Component. The app owns the request and must publish `refreshing` and `result`:

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

Replace `FeedRepository` with the app's data source. Reset `result` to `idle` before each request.

## Customize the indicator

Choose a preset, colors, or platform icons with Blade attributes:

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

For custom artwork, put a PNG, SVG, GIF, or Lottie JSON file under the host app's `public/` directory and pass its relative path, for example `indicator-asset="pull-to-refresh/refresh.svg"` or `indicator-lottie="pull-to-refresh/refresh.json"`. Use either the platform icon pair, one `indicator-asset`, or one `indicator-lottie`. Remote URLs, absolute paths, and paths containing `..` are rejected.

When constructing an EDGE tree in PHP, use the fluent element:

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

## Options

| Attribute | Default | Purpose |
| --- | --- | --- |
| `preset` | `spring` | `liquid`, `spring`, or `minimal` indicator. |
| `threshold` | `88` | Pull distance in points/dp. Android uses it as the activation distance; iOS uses it to scale indicator progress while UIKit controls activation. |
| `indicator-size` | `32` | Indicator diameter in points/dp. |
| `indicator-stroke-width` | `2.5` | Progress and status mark thickness in points/dp. |
| `animation-duration` | `240` | Native transition and loading animation duration in milliseconds; `0` disables non-essential motion. |
| `accent-color`, `container-color`, `indicator-track-color` | Theme colors | Indicator, background, and inactive track colors. Accept a color string or ARGB integer. |
| `refreshing` | `false` | App-controlled loading state. Keep true until the request finishes. |
| `result` | `idle` | `idle`, `success`, or `error`. |
| `result-duration` | `500` | Success/error feedback time in milliseconds; `0` hides it. |
| `on-refresh` | — | Parent Native Component method called after a valid pull or accessibility action. |
| `indicator-icon-ios`, `indicator-icon-android` | — | iOS SF Symbol and Android icon (`refresh`, `sync`, `arrow_downward`, `download`, `cloud_download`, or `local_shipping`). |
| `indicator-asset` | — | Relative path under `public/` to a PNG, SVG, or GIF. |
| `indicator-lottie` | — | Relative path under `public/` to a Lottie JSON file. |
| Accessibility labels | English defaults | Override with `refresh-action-label`, `pull-label`, `release-label`, `refreshing-label`, `success-label`, and `error-label`. |

The component owns one vertical scroll container; do not nest another vertical scroller inside it. VoiceOver and TalkBack can start a refresh with the localized `refresh-action-label`. Reduced-motion settings are respected by both renderers.

## Validate and build

After registering the plugin or changing its manifest or native renderer, validate it and rebuild the target platform:

```sh
php artisan native:plugin:validate packages/momotombo/nativephp-pull-to-refresh
php artisan native:run ios
php artisan native:run android
```

## License

MIT; see [LICENSE](LICENSE).
