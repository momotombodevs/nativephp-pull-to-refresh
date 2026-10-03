## momotombo/nativephp-pull-to-refresh

Native pull-to-refresh EDGE container for NativePHP Mobile 4.5+ on Android and iOS.

### Registering the component

Register the package with `php artisan native:plugin:register momotombo/nativephp-pull-to-refresh` after installing it. Rebuild the native app after changing Kotlin or Swift renderer code.

### Blade usage

Use the native component as the vertical scroll container for EDGE children. Keep refresh state in the Laravel Native Component and set `result` back to `idle` before each refresh.

@verbatim
<code-snippet name="Pull-to-refresh EDGE container" lang="blade">
<native:pull-to-refresh
    preset="spring"
    threshold="88"
    indicator-size="32"
    indicator-stroke-width="2.5"
    animation-duration="240"
    indicator-track-color="#CBD5E1"
    :refreshing="$refreshing"
    :result="$refreshResult"
    result-duration="500"
    refresh-action-label="Refresh"
    pull-label="Pull to refresh"
    release-label="Release to refresh"
    refreshing-label="Refreshing"
    success-label="Refresh complete"
    error-label="Refresh failed"
    on-refresh="refreshFeed"
>
    <native:row>
        <native:text>Scrollable EDGE content</native:text>
    </native:row>
</native:pull-to-refresh>
</code-snippet>
@endverbatim

### Props

`preset` accepts `liquid`, `spring`, or `minimal` (default `spring`). `threshold` (default `88`) and `indicator-size` (default `32`) must be greater than zero. `accent-color` and `container-color` default to the app theme. `refreshing` defaults to `false`; `result` accepts `idle`, `success`, or `error` (default `idle`); `result-duration` defaults to `500` milliseconds. `on-refresh` names a method on the parent Native Component.

`indicator-stroke-width` (default `2.5`) controls the progress and status-mark thickness in points/dp. `animation-duration` (default `240`) controls native transitions and the native loading ring; `0` disables those transitions, while Lottie keeps its authored playback timing. `indicator-track-color` controls the inactive arc and defaults to the theme outline variant. `refresh-action-label`, `pull-label`, `release-label`, `refreshing-label`, `success-label`, and `error-label` customize localized accessibility action/state labels. Native icons may be set together for iOS and Android; choose either that icon pair, one `indicator-asset`, or one `indicator-lottie` source.

`threshold` normalizes the custom progress indicator on iOS; UIKit chooses iOS activation distance and physical resistance. On Android, Material 3 uses `threshold` as the refresh activation distance and owns its native scroll resistance. These native gesture physics are intentionally not overridden.

The component owns one vertical scroll container, retains its EDGE children, and delegates feed loading to the parent method. Keep each refresh idempotent at the application layer and publish `refreshing` and `result` back to the component. No permissions or secrets are required. Image and Lottie paths are relative to the consuming app's `public/` directory; remote URLs and parent-directory traversal are rejected.

The PHP `PullToRefresh` builder exposes `make`, `preset`, `threshold`, `indicatorSize`, `indicatorStrokeWidth`, `animationDuration`, `indicatorIconIos`, `indicatorIconAndroid`, `indicatorAsset`, `indicatorLottie`, `accentColor`, `containerColor`, `trackColor`, `refreshing`, `result`, `resultDuration`, `refreshActionLabel`, `pullLabel`, `releaseLabel`, `refreshingLabel`, `successLabel`, `errorLabel`, and `onRefresh`. Add regular NativePHP EDGE children with `addChild`.

The iOS renderer uses a UIKit table view with `UIRefreshControl`, so direct EDGE children are virtualized native rows. UIKit controls the activation distance and physical drag resistance; `threshold` normalizes custom visual progress there. Android uses Material 3 nested scrolling and applies `threshold` to activation. Rebuild each native platform after changing plugin renderers or manifest dependencies.
