<?php

namespace Momotombo\NativePhpPullToRefresh;

use Illuminate\Support\ServiceProvider;

/**
 * Package discovery entry point. NativePHP registers the EDGE element and renderers
 * from nativephp.json, so this provider intentionally adds no container bindings.
 */
class NativePhpPullToRefreshServiceProvider extends ServiceProvider {}
