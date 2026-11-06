<?php

namespace Momotombo\NativePhpPullToRefresh\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

/** Blade `<native:pull-to-refresh-indicator>`: a composed overlay declaration inside the container. */
class PullToRefreshIndicator extends NativeBladeComponent
{
    protected bool $isSelfClosing = false;

    /** Return the EDGE node type declared by the plugin manifest. */
    protected function elementType(): string
    {
        return 'pull_to_refresh_indicator';
    }
}
