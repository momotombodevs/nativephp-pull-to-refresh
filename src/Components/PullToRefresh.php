<?php

namespace Momotombo\NativePhpPullToRefresh\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

/**
 * A scrollable container with a customizable native pull-to-refresh indicator.
 */
class PullToRefresh extends NativeBladeComponent
{
    protected bool $isSelfClosing = false;

    /** Return the EDGE node type declared by the plugin manifest. */
    protected function elementType(): string
    {
        return 'pull_to_refresh';
    }
}
